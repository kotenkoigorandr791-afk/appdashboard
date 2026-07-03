#!/usr/bin/env python3
import argparse
import hashlib
import json
import re
import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'lib'))
from pg_sqlite_compat import connect_user
import subprocess
import time
import unicodedata
from datetime import datetime
from difflib import SequenceMatcher
from pathlib import Path
from urllib.request import urlopen
import os
try:
    from merchant_classifier import MerchantClassifier,normalize_text
except ModuleNotFoundError:
    from utils.merchant_classifier import MerchantClassifier,normalize_text
SUPPORTED_CURRENCIES={"USD","EUR","PLN","UAH","GBP","BYN","RUB","KZT","BGN","CZK","RON","CHF","HUF","TRY","AED","JPY","CNY"}
RAW_CURRENCY_ALIASES={"USD":"USD","US$":"USD","$":"USD","DOLLAR":"USD","DOLLARS":"USD","EUR":"EUR","EURO":"EUR","€":"EUR","PLN":"PLN","ZŁ":"PLN","ZL":"PLN","zl":"PLN","UAH":"UAH","ГРН":"UAH","грн":"UAH","₴":"UAH","GBP":"GBP","£":"GBP","POUND":"GBP","BYN":"BYN","BR":"BYN","БР":"BYN","RUB":"RUB","RUR":"RUB","₽":"RUB","РУБ":"RUB","руб":"RUB","KZT":"KZT","₸":"KZT","ТЕНГЕ":"KZT","тенге":"KZT","BGN":"BGN","ЛВ":"BGN","лв":"BGN","CZK":"CZK","KČ":"CZK","Kc":"CZK","RON":"RON","LEI":"RON","CHF":"CHF","FR":"CHF","HUF":"HUF","FT":"HUF","TRY":"TRY","₺":"TRY","AED":"AED","JPY":"JPY","¥":"JPY","CNY":"CNY","元":"CNY"}
MONTHS={"jan":1,"january":1,"янв":1,"січ":1,"feb":2,"february":2,"фев":2,"лют":2,"mar":3,"march":3,"мар":3,"бер":3,"apr":4,"april":4,"апр":4,"кві":4,"may":5,"май":5,"тра":5,"jun":6,"june":6,"июн":6,"чер":6,"jul":7,"july":7,"июл":7,"лип":7,"aug":8,"august":8,"авг":8,"сер":8,"sep":9,"sept":9,"september":9,"сен":9,"вер":9,"oct":10,"october":10,"окт":10,"жов":10,"nov":11,"november":11,"ноя":11,"лис":11,"dec":12,"december":12,"дек":12,"гру":12}
WALLET_AMOUNT_FRAC=re.compile(r"^\d+\.\d{2}$")
OCR_DIGIT=r"[\dOЗзOoI]"
AMOUNT_OCR_PART=rf"(?:{OCR_DIGIT}+)[.,](?:{OCR_DIGIT}{{2}})(?!(?:{OCR_DIGIT}))"
LEGAL_SUFFIXES={"stiftung","co","ko","kg","sp","z","oo","ltd","llc","inc","gmbh","sro","s.r.o","sa","s.a","ooo","тов","пп","фоп"}
_EASYOCR_READER=None
_MERCHANT_CLASSIFIER=None
def now_iso():
    return datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
def write_status(path,payload):
    tmp=path.with_suffix(path.suffix+".tmp")
    tmp.write_text(json.dumps(payload,ensure_ascii=False,indent=2),encoding="utf-8")
    tmp.replace(path)
def normalize_space(value):
    return re.sub(r"\s+"," ",value).strip()
def strip_diacritics(value):
    return "".join(ch for ch in unicodedata.normalize("NFD",value) if unicodedata.category(ch)!="Mn")
def latinize_homoglyphs(value):
    mp={"\u0430":"a","\u0410":"A","\u0435":"e","\u0415":"E","\u043e":"o","\u041e":"O","\u0440":"p","\u0420":"P","\u0441":"c","\u0421":"C","\u0443":"y","\u0423":"Y","\u0445":"x","\u0425":"X","\u043a":"k","\u041a":"K","\u043c":"m","\u041c":"M","\u0442":"t","\u0422":"T","\u0432":"b","\u0412":"B","\u043d":"n","\u041d":"N","\u0456":"i","\u0406":"I","\u0437":"z","\u0417":"Z","\u0457":"i","\u0407":"I","\u0451":"e","\u0401":"E","\u044a":"","\u042a":"","\u044c":"","\u042c":"","\u044b":"y","\u042b":"Y","\u0454":"e","\u0404":"E"}
    return "".join(mp.get(c,c) for c in value)
def normalize_currency_key(value):
    value=latinize_homoglyphs(normalize_space(str(value).lower()))
    value=strip_diacritics(value).replace("ł","l")
    return re.sub(r"[.\s]+","",value)
NORMALIZED_CURRENCY_ALIASES={normalize_currency_key(alias):code for alias,code in RAW_CURRENCY_ALIASES.items()}
CURRENCY_TOKEN_PATTERN="|".join(re.escape(alias) for alias in sorted(RAW_CURRENCY_ALIASES,key=len,reverse=True))
AMOUNT_PATTERNS=[re.compile(rf"(?P<currency>{CURRENCY_TOKEN_PATTERN})\s*(?P<amount>{AMOUNT_OCR_PART})",re.IGNORECASE),re.compile(rf"(?P<amount>{AMOUNT_OCR_PART})\s*(?P<currency>{CURRENCY_TOKEN_PATTERN})",re.IGNORECASE)]
WORD_CURRENCY_ALIASES=sorted({(normalize_currency_key(alias),code) for alias,code in RAW_CURRENCY_ALIASES.items() if normalize_currency_key(alias).isalpha() and len(normalize_currency_key(alias))>=2},key=lambda item:len(item[0]),reverse=True)
SYMBOL_CURRENCY_ALIASES=[(alias,code) for alias,code in RAW_CURRENCY_ALIASES.items() if not normalize_currency_key(alias).isalpha()]
def normalize_currency_token(token):
    return NORMALIZED_CURRENCY_ALIASES.get(normalize_currency_key(token),"")
def infer_currency_from_text(text,default_currency=""):
    raw_text=str(text or "")
    normalized=strip_diacritics(latinize_homoglyphs(raw_text.lower())).replace("ł","l")
    scores={}
    for alias,code in SYMBOL_CURRENCY_ALIASES:
        hits=raw_text.count(alias)
        if hits:
            scores[code]=scores.get(code,0)+hits*max(1,len(alias))
    for alias,code in WORD_CURRENCY_ALIASES:
        hits=len(re.findall(rf"(?<![a-z]){re.escape(alias)}(?![a-z])",normalized))
        if hits:
            scores[code]=scores.get(code,0)+hits*len(alias)
    base=(default_currency or "").upper()
    if not scores:
        return base if base in SUPPORTED_CURRENCIES else ""
    return max(scores.items(),key=lambda item:(item[1],item[0]==base))[0]
def canonical_merchant(value):
    value=latinize_homoglyphs(normalize_space(value.lower()))
    value=re.sub(r"[^\w\s]+"," ",value,flags=re.UNICODE)
    tokens=[t for t in value.split() if t and t not in LEGAL_SUFFIXES]
    return normalize_space(" ".join(tokens))
def display_catalog_merchant(value):
    return normalize_space(str(value or "").replace("_"," "))
def get_merchant_classifier():
    global _MERCHANT_CLASSIFIER
    if _MERCHANT_CLASSIFIER is None:
        _MERCHANT_CLASSIFIER=MerchantClassifier()
    return _MERCHANT_CLASSIFIER
def normalize_ocr_amount_str(raw):
    s=str(raw).replace("O","0").replace("o","0").replace("I","1").replace("l","1")
    s="".join("7" if c in "Zz" else c for c in s)
    s="".join("3" if c in "Зз" else c for c in s)
    return s
def parse_wallet_amount_decimal(raw):
    s=normalize_ocr_amount_str(str(raw)).strip().replace(" ","")
    if not s:
        return None
    if "," in s and "." in s:
        if s.rfind(".")>s.rfind(","):
            s=s.replace(",","")
        else:
            s=s.replace(".","").replace(",",".")
    elif "," in s:
        parts=s.split(",")
        if len(parts)==2 and len(parts[1])==2 and parts[1].isdigit():
            s=parts[0].replace(".","")+"."+parts[1]
        else:
            s=s.replace(",","")
    if not WALLET_AMOUNT_FRAC.match(s):
        return None
    try:
        return float(s)
    except ValueError:
        return None
def normalize_us_thousands_amount_substrings(text):
    return re.sub(r"(\d{1,3}(?:,\d{3})+)(\.\d{2})(?!\d)",lambda m:m.group(1).replace(",","")+m.group(2),text)
def parse_amount_and_currency(line):
    line=normalize_ocr_amount_str(normalize_us_thousands_amount_substrings(line))
    for pattern in AMOUNT_PATTERNS:
        match=pattern.search(line)
        if not match:
            continue
        amount=parse_wallet_amount_decimal(match.group("amount"))
        if amount is None:
            continue
        currency=normalize_currency_token(match.groupdict().get("currency") or "")
        if currency in SUPPORTED_CURRENCIES:
            cleaned=(line[:match.start()]+line[match.end():]).strip(" -:|")
            return amount,currency,cleaned
    return None
def parse_amount_line_only(line):
    line=normalize_ocr_amount_str(normalize_us_thousands_amount_substrings(normalize_space(line)))
    for pattern in AMOUNT_PATTERNS:
        m=pattern.fullmatch(line)
        if not m:
            continue
        currency=normalize_currency_token(m.groupdict().get("currency") or "")
        if currency not in SUPPORTED_CURRENCIES:
            continue
        amount=parse_wallet_amount_decimal(m.group("amount"))
        if amount is None:
            continue
        return amount,currency
    return None
def parse_month_day_line(line):
    m=re.match(r"^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{1,2})\b",normalize_space(line),re.IGNORECASE)
    if not m:
        return None
    mon=MONTHS.get(m.group(1).lower()[:3])
    if not mon:
        return None
    day=int(m.group(2))
    y=datetime.utcnow().year
    try:
        return datetime(y,mon,day).strftime("%Y-%m-%d")
    except ValueError:
        return None
def is_noise_line(line):
    s=normalize_space(line)
    if not s:
        return True
    if s.startswith("#") or s in {"#R","<"}:
        return True
    if re.match(r"^\d{1,2}:\d{2}$",s):
        return True
    if re.match(r"^\d{1,3}$",s) and len(s)<=3:
        return True
    return False
def is_valid_merchant_line(line):
    s=normalize_space(line)
    if len(s)<3:
        return False
    if parse_month_day_line(s):
        return False
    if parse_amount_line_only(s):
        return False
    if re.match(r"^[\d.,\s]+$",s):
        return False
    if re.match(r"^[\d.,OОоЗз]+$",normalize_ocr_amount_str(s)) and len(s)<20:
        return False
    if s.startswith(".") and len(s)<8:
        return False
    if re.match(r"^[Зз]([.,]\d*)?$",s):
        return False
    if re.match(r"^[.,][O0o]{1,3}$",s):
        return False
    letters=sum(1 for c in s if ("A"<=c<="Z") or ("a"<=c<="z") or ("\u0400"<=c<="\u04ff"))
    if letters<2:
        return False
    return True
def find_merchant_back(lines,idx):
    for j in range(idx-1,-1,-1):
        t=lines[j]
        if is_noise_line(t):
            continue
        if parse_month_day_line(t):
            continue
        if parse_amount_line_only(t):
            continue
        if is_valid_merchant_line(t):
            return t
    return ""
def extract_wallet_transactions(lines,default_date):
    txs=[]
    last_merchant=""
    last_date=default_date
    i=0
    n=len(lines)
    while i<n:
        line=lines[i]
        md=parse_month_day_line(line)
        if md:
            last_date=md
            i+=1
            continue
        amt_only=parse_amount_line_only(line)
        if amt_only:
            amount,currency=amt_only
            merchant=find_merchant_back(lines,i) or last_merchant
            if not merchant or not is_valid_merchant_line(merchant):
                i+=1
                continue
            tx_date=last_date
            if i+1<n:
                nxt=parse_month_day_line(lines[i+1])
                if nxt:
                    tx_date=nxt
                    last_date=nxt
            txs.append((tx_date,merchant,amount,currency))
            i+=1
            continue
        same=parse_amount_and_currency(line)
        if same:
            amount,currency,merchant=same
            if not merchant:
                merchant=last_merchant
            elif not is_valid_merchant_line(merchant):
                merchant=find_merchant_back(lines,i) or last_merchant
            if not merchant or not is_valid_merchant_line(merchant):
                i+=1
                continue
            tx_date=last_date
            if i+1<n:
                nxt=parse_month_day_line(lines[i+1])
                if nxt:
                    tx_date=nxt
                    last_date=nxt
            txs.append((tx_date,merchant,amount,currency))
            i+=1
            continue
        if is_valid_merchant_line(line):
            last_merchant=line
        i+=1
    return txs
def parse_date(text,default_date):
    m_iso=re.search(r"\b(20\d{2})[-/.](\d{1,2})[-/.](\d{1,2})\b",text)
    if m_iso:
        y,m,d=int(m_iso.group(1)),int(m_iso.group(2)),int(m_iso.group(3))
        try:
            return datetime(y,m,d).strftime("%Y-%m-%d")
        except ValueError:
            pass
    m_eu=re.search(r"\b(\d{1,2})[-/.](\d{1,2})[-/.](20\d{2})\b",text)
    if m_eu:
        d,m,y=int(m_eu.group(1)),int(m_eu.group(2)),int(m_eu.group(3))
        try:
            return datetime(y,m,d).strftime("%Y-%m-%d")
        except ValueError:
            pass
    m_word=re.search(r"\b(\d{1,2})\s+([A-Za-zА-Яа-яІіЇїЄє]{3,12})\b",text)
    if m_word:
        d=int(m_word.group(1))
        mon=strip_diacritics(m_word.group(2).lower())[:3]
        month=MONTHS.get(mon)
        if month:
            y=datetime.utcnow().year
            try:
                return datetime(y,month,d).strftime("%Y-%m-%d")
            except ValueError:
                pass
    return default_date
def ocr_image(path,progress_cb=None):
    global _EASYOCR_READER
    easyocr_err=None
    try:
        import easyocr
        if _EASYOCR_READER is None:
            _EASYOCR_READER=easyocr.Reader(["en","uk","ru"],gpu=False,verbose=False)
        arr=None
        try:
            from PIL import Image
            import numpy as np
            im=Image.open(str(path)).convert("RGB")
            w,h=im.size
            if max(w,h)<1400:
                im=im.resize((min(w*2,4000),min(h*2,4000)),Image.Resampling.LANCZOS)
            arr=np.array(im)
        except Exception:
            arr=None
        chunks=[]
        if arr is not None:
            h=arr.shape[0]
            if h>1200:
                chunk_h=min(1200,max(700,h//3))
                overlap=120
                start=0
                while start<h:
                    end=min(h,start+chunk_h)
                    chunks.append(arr[start:end,:,:])
                    if end>=h:
                        break
                    start=max(0,end-overlap)
            else:
                chunks.append(arr)
        texts=[]
        if chunks:
            for idx,chunk in enumerate(chunks):
                out=_EASYOCR_READER.readtext(chunk,detail=0,paragraph=False,width_ths=0.9,height_ths=0.9)
                texts.extend([x.strip() for x in out if str(x).strip()])
                if progress_cb:
                    progress_cb((idx+1)/len(chunks))
        else:
            out=_EASYOCR_READER.readtext(str(path),detail=0,paragraph=False,width_ths=0.9,height_ths=0.9)
            texts=[x.strip() for x in out if str(x).strip()]
            if progress_cb:
                progress_cb(1.0)
        uniq=[]
        seen=set()
        for line in texts:
            key=normalize_space(line).lower()
            if not key or key in seen:
                continue
            seen.add(key)
            uniq.append(line)
        text="\n".join(uniq)
        if text:
            return text
    except Exception as exc:
        easyocr_err=str(exc)
    tesseract_bin=os.environ.get("TESSERACT_BIN","tesseract")
    cmd=[tesseract_bin,str(path),"stdout","-l","eng+ukr+rus","--psm","6"]
    try:
        res=subprocess.run(cmd,capture_output=True,text=True,check=False)
    except FileNotFoundError:
        detail=f": {easyocr_err}" if easyocr_err else ""
        raise RuntimeError(f"easyocr_not_available_and_tesseract_missing{detail}")
    if res.returncode!=0:
        raise RuntimeError(res.stderr.strip() or f"ocr failed {path.name}")
    if progress_cb:
        progress_cb(1.0)
    return res.stdout
def ocr_signature(date,store,amount,raw):
    payload=f"{date}|{normalize_space(store).lower()}|{amount:.2f}|{normalize_space(raw).lower()}"
    return hashlib.sha1(payload.encode("utf-8")).hexdigest()
def register_ocr_entry(conn,signature,date,store,amount,raw,image_name):
    before=conn.total_changes
    conn.execute("INSERT OR IGNORE INTO ocr_entries(signature,date,store,amount,ocr_raw_store,image_name,created_at) VALUES(?,?,?,?,?,?,?)",(signature,date,store,amount,raw,image_name,now_iso()))
    return conn.total_changes>before
def best_store_match(merchant,existing,min_score=0.72):
    if not existing:
        return None,0.0
    canon=canonical_merchant(merchant)
    if not canon:
        return None,0.0
    exact=[name for name in existing if canonical_merchant(name)==canon]
    if exact:
        return exact[0],1.0
    score=0.0
    best=merchant
    for name in existing:
        ratio=SequenceMatcher(None,canon,canonical_merchant(name)).ratio()
        if ratio>score:
            score=ratio
            best=name
    return (best,score) if score>=min_score else (None,score)
def best_store_label_match(label,existing,min_score=0.84):
    if not existing:
        return None,0.0
    normalized=normalize_text(label)
    if not normalized:
        return None,0.0
    for name in existing:
        if normalize_text(name)==normalized:
            return name,1.0
    best=None
    score=0.0
    for name in existing:
        ratio=SequenceMatcher(None,normalized,normalize_text(name)).ratio()
        if ratio>score:
            score=ratio
            best=name
    return (best,score) if score>=min_score else (None,score)
def resolve_store_match(merchant,existing):
    matched,score=best_store_label_match(merchant,existing)
    if matched:
        return {"matched":matched,"confidence":score}
    classifier_result=get_merchant_classifier().classify(merchant)
    suggested=""
    alias=""
    if classifier_result.merchant!="OTHER":
        suggested=display_catalog_merchant(classifier_result.merchant)
        alias=classifier_result.alias
        matched,score=best_store_label_match(suggested,existing)
        if matched:
            return {"matched":matched,"confidence":max(score,classifier_result.score),"suggested_store":suggested,"matched_alias":alias}
    matched,score=best_store_match(merchant,existing)
    if matched:
        payload={"matched":matched,"confidence":score}
        if suggested:
            payload["suggested_store"]=suggested
        if alias:
            payload["matched_alias"]=alias
        return payload
    payload={"matched":None,"confidence":max(score,classifier_result.score if suggested else 0.0)}
    if suggested:
        payload["suggested_store"]=suggested
    if alias:
        payload["matched_alias"]=alias
    return payload
def fetch_rates(base):
    with urlopen(f"https://api.exchangerate-api.com/v4/latest/{base}",timeout=10) as resp:
        payload=json.loads(resp.read().decode("utf-8"))
    return payload.get("rates",{})
def convert_to_base(amount,src,base,rates):
    if src==base:
        return amount
    if src not in rates or not rates[src]:
        raise ValueError(f"no_rate_{src}_to_{base}")
    return amount/float(rates[src])
def ensure_store(conn,name):
    cur=conn.execute("SELECT id FROM stores WHERE name=?",(name,))
    row=cur.fetchone()
    if row:
        return
    max_ord=conn.execute("SELECT COALESCE(MAX(sort_order),0)+1 FROM stores").fetchone()[0]
    conn.execute("INSERT OR IGNORE INTO stores(id,name,visible,sort_order) VALUES(?,?,1,?)",(str(int(time.time()*1000)),name,int(max_ord)))
def upsert_expense(conn,date,store,amount):
    conn.execute("INSERT INTO expenses(date,store,amount) VALUES(?,?,?) ON CONFLICT(date,store) DO UPDATE SET amount=ROUND(amount+excluded.amount,2)",(date,store,amount))
def load_existing_stores(conn):
    try:
        return [r[0] for r in conn.execute("SELECT name FROM stores").fetchall() if r[0]]
    except Exception:
        return []
def ensure_db_schema(conn):
    conn.execute("CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY,value TEXT)")
    conn.execute("CREATE TABLE IF NOT EXISTS stores(id TEXT PRIMARY KEY,name TEXT NOT NULL,visible INTEGER NOT NULL DEFAULT 1,sort_order INTEGER NOT NULL DEFAULT 0)")
    conn.execute("CREATE TABLE IF NOT EXISTS expenses(date TEXT NOT NULL,store TEXT NOT NULL,amount REAL NOT NULL DEFAULT 0,PRIMARY KEY(date,store))")
    conn.execute("CREATE TABLE IF NOT EXISTS ocr_entries(signature TEXT PRIMARY KEY,date TEXT NOT NULL,store TEXT NOT NULL,amount REAL NOT NULL,ocr_raw_store TEXT,image_name TEXT,created_at TEXT NOT NULL)")
    cols=set()
    for row in conn.execute("PRAGMA table_info(expenses)").fetchall():
        name=row[1] if not isinstance(row,dict) else row.get("name")
        if name:
            cols.add(name)
    if "entry_source" not in cols:
        conn.execute("ALTER TABLE expenses ADD COLUMN entry_source TEXT NOT NULL DEFAULT 'manual'")
    if "ocr_raw_store" not in cols:
        conn.execute("ALTER TABLE expenses ADD COLUMN ocr_raw_store TEXT")
def process(job_dir,user_email,base_currency):
    uploads=sorted((job_dir/"uploads").glob("*"))
    status_path=job_dir/"status.json"
    records=[]
    unresolved=[]
    recognized=[]
    warnings=[]
    ocr_chunks=[]
    added_count=0
    duplicate_count=0
    status={"state":"processing","progress":{"total":len(uploads),"processed":0,"percent":0},"base_currency":base_currency,"warnings":[],"records":[],"updated_at":now_iso()}
    write_status(status_path,status)
    def push_progress(done_images,image_fraction):
        if not uploads:
            pct=100
            processed=0
        else:
            frac=max(0.0,min(float(image_fraction),1.0))
            pct=round(((done_images+frac)/len(uploads))*100,2)
            processed=min(done_images,len(uploads))
        write_status(status_path,{"state":"processing","progress":{"total":len(uploads),"processed":processed,"percent":pct},"base_currency":base_currency,"warnings":warnings[-20:],"records":records[-100:],"recognized":recognized[-100:],"unresolved":unresolved[-50:],"added_count":added_count,"duplicate_count":duplicate_count,"updated_at":now_iso()})
    rates={base_currency:1.0}
    try:
        rates.update(fetch_rates(base_currency))
    except Exception:
        warnings.append({"code":"no_rates"})
    conn=connect_user('spends', user_email)
    ensure_db_schema(conn)
    conn.execute("BEGIN")
    existing_stores=load_existing_stores(conn)
    grouped={}
    grouped_raw={}
    staged_signatures=set()
    for i,img in enumerate(uploads,start=1):
        dedup_seen=set()
        push_progress(i-1,0.02)
        try:
            text=ocr_image(img,progress_cb=lambda f:push_progress(i-1,0.05+0.8*f))
            push_progress(i-1,0.87)
            ocr_chunks.append(f"=== {img.name} ===\n{text}")
            lines=[normalize_space(x) for x in text.splitlines() if normalize_space(x)]
            image_currency=infer_currency_from_text(text,base_currency)
            default_date=datetime.utcnow().strftime("%Y-%m-%d")
            txs=extract_wallet_transactions(lines,default_date)
            push_progress(i-1,0.93)
            found=len(txs)>0
            for date,merchant,amount,currency in txs:
                if currency not in SUPPORTED_CURRENCIES and image_currency:
                    currency=image_currency
                raw_norm=normalize_space(merchant)
                store_resolution=resolve_store_match(merchant,existing_stores)
                matched=store_resolution.get("matched")
                score=store_resolution.get("confidence",0.0)
                if not matched:
                    item={"date":date,"merchant_raw":merchant,"amount":round(amount,2),"currency":currency,"confidence":round(score,2)}
                    if store_resolution.get("suggested_store"):
                        item["suggested_store"]=store_resolution["suggested_store"]
                    if store_resolution.get("matched_alias"):
                        item["matched_alias"]=store_resolution["matched_alias"]
                    unresolved.append(item)
                    recognized.append({"date":date,"merchant_raw":raw_norm,"amount":round(amount,2),"currency":currency,"status":"unresolved"})
                    continue
                try:
                    converted=round(convert_to_base(amount,currency,base_currency,rates),2)
                except Exception:
                    warnings.append({"code":"skip_no_rate","merchant":merchant,"currency":currency,"amount":f"{amount:.2f}"})
                    recognized.append({"date":date,"merchant_raw":raw_norm,"amount":round(amount,2),"currency":currency,"status":"skipped"})
                    continue
                dedup_key=(date,matched,converted)
                if dedup_key in dedup_seen:
                    duplicate_count+=1
                    records.append({"date":date,"store":matched,"amount":converted,"currency":base_currency,"merchant_raw":raw_norm,"status":"duplicate"})
                    recognized.append({"date":date,"store":matched,"merchant_raw":raw_norm,"amount":converted,"currency":base_currency,"status":"duplicate"})
                    continue
                dedup_seen.add(dedup_key)
                sig=ocr_signature(date,matched,converted,raw_norm)
                if sig in staged_signatures:
                    duplicate_count+=1
                    records.append({"date":date,"store":matched,"amount":converted,"currency":base_currency,"merchant_raw":raw_norm,"status":"duplicate"})
                    recognized.append({"date":date,"store":matched,"merchant_raw":raw_norm,"amount":converted,"currency":base_currency,"status":"duplicate"})
                    continue
                is_new=register_ocr_entry(conn,sig,date,matched,converted,raw_norm,img.name)
                if not is_new:
                    duplicate_count+=1
                    warnings.append({"code":"already_imported","date":date,"store":matched,"amount":f"{converted:.2f}"})
                    records.append({"date":date,"store":matched,"amount":converted,"currency":base_currency,"merchant_raw":raw_norm,"status":"duplicate"})
                    recognized.append({"date":date,"store":matched,"merchant_raw":raw_norm,"amount":converted,"currency":base_currency,"status":"duplicate"})
                    continue
                staged_signatures.add(sig)
                added_count+=1
                key=(date,matched)
                grouped[key]=round(grouped.get(key,0.0)+converted,2)
                grouped_raw.setdefault(key,set()).add(raw_norm)
                records.append({"date":date,"store":matched,"amount":converted,"currency":base_currency,"merchant_raw":raw_norm,"status":"added"})
                recognized.append({"date":date,"store":matched,"merchant_raw":raw_norm,"amount":converted,"currency":base_currency,"status":"added"})
            push_progress(i-1,0.97)
            if not found:
                warnings.append({"code":"no_amount_in_file","file":img.name})
        except Exception as exc:
            warnings.append({"code":"file_error","file":img.name,"message":str(exc)})
            ocr_chunks.append(f"=== {img.name} ===\n[error] {str(exc)}")
        push_progress(i,1)
    for (date,merchant),amount in grouped.items():
        raw=" | ".join(sorted(x for x in grouped_raw.get((date,merchant),set()) if x))
        conn.execute("INSERT INTO expenses(date,store,amount,entry_source,ocr_raw_store) VALUES(?,?,?,?,?) ON CONFLICT(date,store) DO UPDATE SET amount=ROUND(amount+excluded.amount,2),entry_source='ocr',ocr_raw_store=CASE WHEN COALESCE(expenses.ocr_raw_store,'')='' THEN excluded.ocr_raw_store WHEN COALESCE(excluded.ocr_raw_store,'')='' THEN expenses.ocr_raw_store ELSE expenses.ocr_raw_store||' | '||excluded.ocr_raw_store END",(date,merchant,amount,"ocr",raw))
        records.append({"date":date,"store":merchant,"amount":amount,"currency":base_currency})
    conn.commit()
    conn.close()
    records.sort(key=lambda x:(x["date"],x.get("store","")))
    final={"state":"done","progress":{"total":len(uploads),"processed":len(uploads),"percent":100},"base_currency":base_currency,"warnings":warnings,"records":records,"recognized":recognized,"unresolved":unresolved,"added_count":added_count,"duplicate_count":duplicate_count,"ocr_text":"\n\n".join(ocr_chunks),"updated_at":now_iso()}
    write_status(status_path,final)
def main():
    parser=argparse.ArgumentParser()
    parser.add_argument("--job-dir",required=True)
    parser.add_argument("--user-email",required=True)
    parser.add_argument("--base-currency",required=True)
    args=parser.parse_args()
    job_dir=Path(args.job_dir)
    status_path=job_dir/"status.json"
    try:
        process(job_dir,args.user_email,args.base_currency.upper())
    except Exception as exc:
        write_status(status_path,{"state":"error","message":str(exc),"updated_at":now_iso()})
if __name__=="__main__":
    main()
