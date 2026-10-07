#!/usr/bin/env python3
import argparse
import hashlib
import json
import re
import sys
import os
import subprocess
import tempfile
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..', 'lib'))
from pg_sqlite_compat import connect_user
import time
import unicodedata
from datetime import datetime
from difflib import SequenceMatcher
from pathlib import Path
from urllib.request import Request,urlopen
import os
os.environ.setdefault("TOKENIZERS_PARALLELISM","false")
os.environ.setdefault("OMP_NUM_THREADS","2")
os.environ.setdefault("MKL_NUM_THREADS","2")
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
_MERCHANT_CLASSIFIER=None
_AI_CONFIG=None
_AI_MATCH_CACHE={}
class OcrCancelled(Exception):
    pass
def now_iso():
    return datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
def write_status(path,payload):
    tmp=path.with_suffix(path.suffix+".tmp")
    tmp.write_text(json.dumps(payload,ensure_ascii=False,indent=2),encoding="utf-8")
    tmp.replace(path)
def read_status(path):
    try:
        data=json.loads(path.read_text(encoding="utf-8"))
        return data if isinstance(data,dict) else {}
    except Exception:
        return {}
def update_status(path,updates):
    data=read_status(path)
    data.update(updates)
    data["updated_at"]=now_iso()
    write_status(path,data)
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
def load_ai_config():
    global _AI_CONFIG
    if _AI_CONFIG is not None:
        return _AI_CONFIG
    _AI_CONFIG={}
    try:
        from pg_db import get_conn
        conn=get_conn()
        try:
            with conn.cursor() as cur:
                cur.execute("SELECT key,value FROM app_ai_config WHERE key IN ('api_key','api_endpoint','default_model','models')")
                rows=cur.fetchall()
            cfg={}
            for row in rows:
                key=row["key"] if isinstance(row,dict) else row[0]
                value=row["value"] if isinstance(row,dict) else row[1]
                if value:
                    cfg[str(key)]=str(value)
            endpoint=cfg.get("api_endpoint") or (os.environ.get("AI_PUBLIC_URL", "").rstrip("/") + "/v1/chat/completions" if os.environ.get("AI_PUBLIC_URL") else "")
            if "/chat/completions" not in endpoint:
                endpoint=endpoint.rstrip("/")+"/chat/completions"
            models=[]
            raw_models=cfg.get("models","").strip()
            if raw_models:
                try:
                    parsed=json.loads(raw_models)
                    if isinstance(parsed,list):
                        models=[normalize_space(str(item)) for item in parsed if normalize_space(str(item))]
                except Exception:
                    models=[normalize_space(item) for item in re.split(r"[\r\n,]+",raw_models) if normalize_space(item)]
            model=models[0] if models else normalize_space(cfg.get("default_model",""))
            if not model:
                model="github/gpt-4o-mini"
            _AI_CONFIG={"api_key":cfg.get("api_key",""),"api_endpoint":endpoint,"model":model}
        finally:
            conn.close()
    except Exception:
        _AI_CONFIG={}
    return _AI_CONFIG
def extract_json_object(text):
    text=str(text or "").strip()
    if not text:
        return None
    if text.startswith("```"):
        text=re.sub(r"^```(?:json)?\s*|\s*```$","",text,flags=re.I|re.S).strip()
    if not text.startswith("{"):
        m=re.search(r"\{[\s\S]*\}",text)
        if not m:
            return None
        text=m.group(0)
    try:
        data=json.loads(text)
        return data if isinstance(data,dict) else None
    except Exception:
        return None

def ocr_wallet_record_texts(path):
    try:
        from PIL import Image
        import numpy as np
    except Exception:
        return []
    try:
        image=Image.open(path).convert("RGB")
        gray=np.asarray(image.convert("L"))
        height,width=gray.shape
        y0=max(0,int(height*0.10))
        y1=min(height,int(height*0.94))
        x0=max(0,int(width*0.05))
        x1=min(width,int(width*0.95))
        dark=(gray[y0:y1,x0:x1]<180).sum(axis=1)
        mask=dark>max(20,int((x1-x0)*0.025))
        groups=[]
        start=None
        for idx,active in enumerate(mask):
            if active and start is None:
                start=idx
            if (not active or idx==len(mask)-1) and start is not None:
                end=idx if not active else idx+1
                if end-start>5:
                    groups.append([start+y0,end+y0])
                start=None
        merged=[]
        for top,bottom in groups:
            if not merged or top-merged[-1][1]>80:
                merged.append([top,bottom])
            else:
                merged[-1][1]=bottom
        if len(merged)<2:
            return []
        centers=[(top+bottom)//2 for top,bottom in merged]
        texts=[]
        for idx,(top,bottom) in enumerate(merged):
            crop_top=max(0,(centers[idx-1]+centers[idx])//2 if idx else top-70)
            crop_bottom=min(height,(centers[idx]+centers[idx+1])//2 if idx+1<len(centers) else bottom+70)
            row=image.crop((max(0,int(width*0.04)),crop_top,min(width,int(width*0.96)),crop_bottom))
            tmp=tempfile.NamedTemporaryFile(prefix="spends-row-",suffix=".png",delete=False)
            tmp_path=tmp.name
            tmp.close()
            try:
                row.save(tmp_path)
                completed=subprocess.run(["tesseract",tmp_path,"stdout","--psm","6"],capture_output=True,text=True,timeout=12)
                text=normalize_space((completed.stdout or "").replace("\n"," "))
                if text:
                    texts.append(text)
            finally:
                try:
                    os.unlink(tmp_path)
                except Exception:
                    pass
        return texts
    except Exception:
        return []

def recognize_image(path,existing_stores,base_currency,default_date):
    record_texts=ocr_wallet_record_texts(path)
    if record_texts:
        text="\n".join(record_texts)
    else:
        try:
            completed=subprocess.run(["tesseract",str(path),"stdout","--psm","6"],capture_output=True,text=True,timeout=30)
            text=(completed.stdout or "").strip()
            if completed.returncode!=0 and not text:
                raise RuntimeError((completed.stderr or "tesseract_failed")[-500:])
        except Exception as exc:
            return [],f"[ocr_failed] {exc}"
    rows=[]
    stores=[str(x) for x in existing_stores if str(x).strip()]
    for date,merchant,amount,currency in extract_wallet_transactions(text.splitlines(),default_date):
        merchant=clean_wallet_merchant(merchant)
        currency=(currency or infer_currency_from_text(text,base_currency) or base_currency).upper()
        matched,score=best_store_match(merchant,stores)
        rows.append({"date":date,"merchant_raw":merchant,"store":matched or "","amount":round(float(amount),2),"currency":currency,"confidence":score if matched else 0.0,"suggested_store":"" if matched else merchant,"date_unresolved":False})
    return rows,text

def ai_resolve_store_match(merchant,existing,ocr_text=""):
    cfg=load_ai_config()
    if not cfg or not cfg.get("api_key") or not existing:
        return None
    cache_key=(normalize_space(merchant).casefold(),tuple(existing))
    if cache_key in _AI_MATCH_CACHE:
        return _AI_MATCH_CACHE[cache_key]
    stores=[str(x) for x in existing if str(x).strip()]
    prompt={"merchant_from_ocr":merchant,"existing_stores":stores[:300],"ocr_text_excerpt":str(ocr_text or "")[:6000],"task":"Match merchant_from_ocr to exactly one existing store only if it is clearly the same merchant. If no existing store fits, suggest a clean store name to create. Return JSON only: {\"action\":\"match|create|unknown\",\"store\":\"...\",\"confidence\":0.0}. For action=match store must be copied exactly from existing_stores."}
    payload={"model":cfg["model"],"messages":[{"role":"system","content":"You normalize OCR merchant names for an expense tracker. Return strict JSON only."},{"role":"user","content":json.dumps(prompt,ensure_ascii=False)}],"temperature":0.0,"max_tokens":300,"stream":False}
    try:
        req=Request(cfg["api_endpoint"],data=json.dumps(payload,ensure_ascii=False).encode("utf-8"),headers={"Content-Type":"application/json","Authorization":"Bearer "+cfg["api_key"]},method="POST")
        with urlopen(req,timeout=25) as resp:
            raw=resp.read().decode("utf-8","replace")
        decoded=json.loads(raw)
        content=decoded.get("choices",[{}])[0].get("message",{}).get("content","")
        data=extract_json_object(content)
        if not data:
            return None
        action=str(data.get("action","")).lower()
        store=normalize_space(str(data.get("store","")))
        confidence=float(data.get("confidence") or 0.0)
        result=None
        if action=="match" and store in stores and confidence>=0.72:
            result={"matched":store,"confidence":confidence,"ai_matched":True}
        elif action=="create" and store and confidence>=0.55:
            result={"matched":None,"confidence":confidence,"suggested_store":store,"ai_matched":True}
        _AI_MATCH_CACHE[cache_key]=result
        return result
    except Exception:
        _AI_MATCH_CACHE[cache_key]=None
        return None
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
    line=normalize_us_thousands_amount_substrings(normalize_space(line))
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
    line=re.sub(r"^(?:total|suma|sum|итого|разом|до\s*сплати|до\s*оплаты)\s*[:\-]?\s*","",line,flags=re.IGNORECASE)
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
    m=re.fullmatch(rf"(?P<amount>{AMOUNT_OCR_PART})", line)
    if m:
        amount=parse_wallet_amount_decimal(m.group("amount"))
        if amount is not None:
            return amount,""
    return None
def clean_wallet_merchant(line):
    s=normalize_space(line)
    s=re.sub(r"^[^\wА-Яа-яІіЇїЄє]+","",s)
    s=re.sub(r"^(?:A|Ay|ye|ve|a|>)\s+","",s)
    s=re.sub(r"\s*(?:[|:;\-–—]\s*)?\b(?:0?[1-9]|[12]\d|3[01])\s*$","",s)
    folded=latinize_homoglyphs(strip_diacritics(s.lower()))
    s=s.replace("Co.KG","Co. KG")
    s=normalize_space(" ".join(t for t in s.split() if not (len(t)==1 and t.lower() in {"a","е"})))
    return normalize_space(s)
def parse_wallet_merchant_day_hint(line,default_date):
    s=normalize_space(line)
    m=re.search(r"(?:[|:;\-–—]\s*|\s+)(0?[1-9]|[12]\d|3[01])\s*$",s)
    if not m:
        return clean_wallet_merchant(s),None
    day=int(m.group(1))
    year=datetime.utcnow().year
    month=datetime.utcnow().month
    if re.match(r"^\d{4}-\d{2}-\d{2}$",str(default_date)):
        try:
            d0=datetime.strptime(default_date,"%Y-%m-%d")
            year=d0.year
            month=d0.month
        except ValueError:
            pass
    try:
        hinted=datetime(year,month,day).strftime("%Y-%m-%d")
    except ValueError:
        hinted=None
    return clean_wallet_merchant(s[:m.start()]),hinted
def normalize_wallet_date_line(line):
    s=normalize_space(line)
    if parse_month_day_line(s):
        return s
    m=re.match(r"^[^\d]*(\d{1,2})$",s)
    if m and re.search(r"[уціії]",s.lower()):
        day=m.group(1)
        if len(day)==1 and "і" in s.lower():
            day="1"+day
        return f"Jul {day}"
    return ""
def find_next_month_day(lines,start,limit=4):
    for j in range(start,min(len(lines),start+limit+1)):
        md=parse_month_day_line(lines[j])
        if md:
            return md
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
def find_merchant_back_with_date(lines,idx,default_date):
    for j in range(idx-1,-1,-1):
        t=lines[j]
        if is_noise_line(t):
            continue
        if parse_month_day_line(t):
            continue
        if parse_amount_line_only(t):
            continue
        merchant,date_hint=parse_wallet_merchant_day_hint(t,default_date)
        if is_valid_merchant_line(merchant):
            return merchant,date_hint
    return "",None
def wallet_year(default_date):
    if re.match(r"^\d{4}-\d{2}-\d{2}$",str(default_date)):
        try:
            return datetime.strptime(default_date,"%Y-%m-%d").year
        except ValueError:
            pass
    return datetime.utcnow().year

def wallet_date(month,day,default_date):
    try:
        return datetime(wallet_year(default_date),month,int(day)).strftime("%Y-%m-%d")
    except ValueError:
        return default_date

def clean_wallet_segment_merchant(value):
    s=clean_wallet_merchant(value)
    s=re.sub(r"^\d{1,2}:\d{2}\s*", "", s)
    s=re.sub(r"^(?:po|a:)\s+", "", s, flags=re.IGNORECASE)
    s=re.sub(r"\b(?:QR|ED|Et|cz|ETT|ses)\b", " ", s, flags=re.IGNORECASE)
    s=re.sub(r"^[^A-Za-zА-Яа-яІіЇїЄє]+", "", s)
    known=["ALIEXPRESS.COM","Biedronka","Lidl Stiftung","Lidl","MPK KRAKOW KKM","Castorama","Auchan","Stokrotka","Stocrotka"]
    folded=s.lower()
    best=None
    best_pos=-1
    for name in known:
        pos=folded.rfind(name.lower())
        if pos>best_pos:
            best=name
            best_pos=pos
    if best and best_pos>=0:
        tail=s[best_pos:]
        if best.lower().startswith("lidl") and "stiftung" in tail.lower():
            return "Lidl Stiftung & Co. KG"
        return best
    parts=[part for part in re.split(r"\s{2,}|[<>—=]+",s) if normalize_space(part)]
    return clean_wallet_merchant(parts[-1] if parts else s)

def parse_wallet_ocr_line(line,default_date):
    text=normalize_space(line)
    if not text:
        return None
    text=re.sub(r"\bS\s+ep\b","Sep",text,flags=re.IGNORECASE)
    text=re.sub(r"\bE\s*T\s*T\b","",text,flags=re.IGNORECASE)
    month_names="Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec"
    pattern=re.compile(rf"(?P<currency>{CURRENCY_TOKEN_PATTERN})\s*(?P<amount>{AMOUNT_OCR_PART})(?:\s+[^\s]{{1,8}}){{0,3}}\s+(?P<month>{month_names})\s+(?P<day>\d{{1,2}})",re.IGNORECASE)
    match=pattern.search(text)
    if match:
        merchant=clean_wallet_segment_merchant(text[:match.start()])
        amount=parse_wallet_amount_decimal(match.group("amount"))
        currency=normalize_currency_token(match.group("currency"))
        month=MONTHS.get(match.group("month").lower()[:3])
        if merchant and amount is not None and currency and month:
            return wallet_date(month,match.group("day"),default_date),merchant,amount,currency
    reverse=re.compile(rf"(?P<currency>{CURRENCY_TOKEN_PATTERN})\s*(?P<amount>{AMOUNT_OCR_PART})(?P<merchant>.+?)\s+(?P<month>{month_names})\s+(?P<day>\d{{1,2}})",re.IGNORECASE)
    match=reverse.search(text)
    if not match:
        return None
    merchant=clean_wallet_segment_merchant(match.group("merchant"))
    amount=parse_wallet_amount_decimal(match.group("amount"))
    currency=normalize_currency_token(match.group("currency"))
    month=MONTHS.get(match.group("month").lower()[:3])
    if not merchant or amount is None or not currency or not month:
        return None
    return wallet_date(month,match.group("day"),default_date),merchant,amount,currency

def split_wallet_ocr_segments(text,default_date):
    rows=[]
    for line in str(text or "").splitlines():
        row=parse_wallet_ocr_line(line,default_date)
        if row:
            rows.append(row)
    if rows:
        return rows
    text=normalize_space(text)
    text=re.sub(r"\bS\s+ep\b","Sep",text,flags=re.IGNORECASE)
    month_names="Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec"
    dated=re.compile(rf"(?P<currency>{CURRENCY_TOKEN_PATTERN})\s*(?P<amount>{AMOUNT_OCR_PART})(?:\s+[^\s]{{1,8}}){{0,3}}\s+(?P<month>{month_names})\s+(?P<day>\d{{1,2}})",re.IGNORECASE)
    last_end=0
    for match in dated.finditer(text):
        merchant=clean_wallet_segment_merchant(text[last_end:match.start()])
        amount=parse_wallet_amount_decimal(match.group("amount"))
        currency=normalize_currency_token(match.group("currency"))
        month=MONTHS.get(match.group("month").lower()[:3])
        if merchant and amount is not None and currency and month:
            rows.append((wallet_date(month,match.group("day"),default_date),merchant,amount,currency))
        last_end=match.end()
    return rows

def extract_wallet_transactions(lines,default_date):
    text="\n".join(normalize_wallet_date_line(line) or normalize_space(line) for line in lines if normalize_space(line))
    segmented=split_wallet_ocr_segments(text,default_date)
    if segmented:
        return segmented
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
            merchant,date_hint=find_merchant_back_with_date(lines,i,last_date)
            merchant=merchant or last_merchant
            if merchant and is_valid_merchant_line(merchant):
                tx_date=date_hint or last_date
                nxt=find_next_month_day(lines,i+1)
                if nxt:
                    tx_date=nxt
                    last_date=nxt
                txs.append((tx_date,merchant,amount,currency or ""))
            i+=1
            continue
        same=parse_amount_and_currency(line)
        if same:
            amount,currency,merchant=same
            date_hint=None
            if not merchant:
                merchant=last_merchant
            else:
                merchant,date_hint=parse_wallet_merchant_day_hint(merchant,last_date)
            if not is_valid_merchant_line(merchant):
                merchant,date_hint=find_merchant_back_with_date(lines,i,last_date)
                merchant=merchant or last_merchant
            if merchant and is_valid_merchant_line(merchant):
                tx_date=date_hint or last_date
                nxt=find_next_month_day(lines,i+1)
                if nxt:
                    tx_date=nxt
                    last_date=nxt
                txs.append((tx_date,merchant,amount,currency))
            i+=1
            continue
        merchant,date_hint=parse_wallet_merchant_day_hint(line,last_date)
        if is_valid_merchant_line(merchant):
            last_merchant=merchant
            if date_hint:
                last_date=date_hint
        i+=1
    return txs
def ocr_signature(date,store,amount,raw):
    payload=f"{date}|{normalize_space(store).lower()}|{amount:.2f}|{normalize_space(raw).lower()}"
    return hashlib.sha1(payload.encode("utf-8")).hexdigest()
def register_ocr_entry(conn,signature,date,store,amount,raw,image_name):
    cur=conn.execute("INSERT INTO ocr_entries(signature,date,store,amount,ocr_raw_store,image_name,created_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT (signature) DO NOTHING RETURNING signature",(signature,date,store,amount,raw,image_name,now_iso()))
    return cur.fetchone() is not None
def store_match_tokens(value):
    text=canonical_merchant(value)
    aliases={
        "biedronka":{"biedronka","bieronka","bedronka","biedr0nka","a biedronka"},
        "lidl":{"lidl","lidl stiftung","lidl stiftung kg"},
        "castorama":{"castorama"},
        "auchan":{"auchan"},
        "stokrotka":{"stokrotka","stocrotka"},
    }
    tokens={text}
    for canonical,items in aliases.items():
        if text in items or any(item in text for item in items):
            tokens.add(canonical)
    return {x for x in tokens if x}

def best_store_match(merchant,existing,min_score=0.72):
    if not existing:
        return None,0.0
    merchant_tokens=store_match_tokens(merchant)
    if not merchant_tokens:
        return None,0.0
    for name in existing:
        if merchant_tokens & store_match_tokens(name):
            return name,1.0
    score=0.0
    best=None
    canon=canonical_merchant(merchant)
    for name in existing:
        ratio=SequenceMatcher(None,canon,canonical_merchant(name)).ratio()
        if ratio>score:
            score=ratio
            best=name
    return (best,score) if best and score>=min_score else (None,score)

def best_store_label_match(label,existing,min_score=0.84):
    return best_store_match(label,existing,min_score)
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
def db_row_value(row,key,index=0):
    if isinstance(row,dict):
        return row.get(key)
    return row[index]

def load_existing_stores(conn):
    try:
        rows=conn.execute("SELECT name FROM stores ORDER BY sort_order ASC, id ASC").fetchall()
        return [str(db_row_value(r,"name") or "") for r in rows if db_row_value(r,"name")]
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
def queue_jobs(root):
    jobs=[]
    for status_file in root.glob("*/*/status.json"):
        status=read_status(status_file)
        state=str(status.get("state") or "")
        if state in {"queued","processing"}:
            jobs.append(status_file.parent)
    return sorted(jobs,key=lambda p:p.name)
def queue_position(job_dir):
    jobs=queue_jobs(job_dir.parent.parent)
    try:
        return jobs.index(job_dir)+1
    except ValueError:
        return 1
def lock_ocr_worker(job_dir,status_path,base_status,check_cancel):
    lock_path=job_dir.parent.parent/"expense_ocr_worker.lock"
    lock_file=open(lock_path,"w")
    import fcntl
    while True:
        check_cancel()
        try:
            fcntl.flock(lock_file,fcntl.LOCK_EX|fcntl.LOCK_NB)
            return lock_file
        except BlockingIOError:
            pos=queue_position(job_dir)
            payload=dict(base_status)
            payload.update({"state":"queued","phase":"queued","queue_position":pos,"message":"queued","updated_at":now_iso()})
            write_status(status_path,payload)
            time.sleep(2)
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
    def check_cancel():
        if not job_dir.exists() or (job_dir/"cancel_requested").exists():
            raise OcrCancelled("canceled")
    status={"state":"queued","phase":"queued","queue_position":queue_position(job_dir),"progress":{"total":len(uploads),"processed":0,"percent":0},"base_currency":base_currency,"warnings":[],"records":[],"updated_at":now_iso()}
    write_status(status_path,status)
    def push_progress(done_images,image_fraction):
        check_cancel()
        if not uploads:
            pct=100
            processed=0
        else:
            frac=max(0.0,min(float(image_fraction),1.0))
            pct=round(((done_images+frac)/len(uploads))*100,2)
            processed=min(done_images,len(uploads))
        write_status(status_path,{"state":"processing","phase":"processing","queue_position":0,"progress":{"total":len(uploads),"processed":processed,"percent":pct},"base_currency":base_currency,"warnings":warnings[-20:],"records":records[-100:],"recognized":recognized[-100:],"unresolved":unresolved[-50:],"added_count":added_count,"duplicate_count":duplicate_count,"updated_at":now_iso()})
    lock_file=lock_ocr_worker(job_dir,status_path,status,check_cancel)
    update_status(status_path,{"state":"processing","phase":"processing","queue_position":0,"message":"processing","progress":{"total":len(uploads),"processed":0,"percent":0},"base_currency":base_currency,"warnings":[],"records":[]})
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
    seen_upload_transactions={}
    timings={}
    for i,img in enumerate(uploads,start=1):
        check_cancel()
        dedup_seen=set()
        push_progress(i-1,0.02)
        try:
            default_date=datetime.utcnow().strftime("%Y-%m-%d")
            txs,ocr_output=recognize_image(img,existing_stores,base_currency,default_date)
            try:
                pass
            except Exception:
                pass
            push_progress(i-1,0.87)
            ocr_chunks.append(f"=== {img.name} ===\n{ocr_output}")
            push_progress(i-1,0.93)
            found=len(txs)>0
            for tx in txs:
                date=tx["date"]
                merchant=tx["merchant_raw"]
                amount=tx["amount"]
                currency=tx["currency"]
                raw_norm=normalize_space(merchant)
                tx_key=(date,canonical_merchant(raw_norm) or normalize_text(raw_norm),round(float(amount),2),currency)
                date_unresolved=bool(tx.get("date_unresolved"))
                row_id=f"{i}:{tx_key[0]}:{tx_key[1]}:{tx_key[2]}" if date_unresolved else ""
                seen_image=seen_upload_transactions.get(tx_key)
                if seen_image is not None and seen_image!=i:
                    duplicate_count+=1
                    recognized.append({"date":date,"merchant_raw":raw_norm,"amount":round(amount,2),"currency":currency,"status":"duplicate"})
                    continue
                seen_upload_transactions.setdefault(tx_key,i)
                matched=tx.get("store") or ""
                score=tx.get("confidence",0.0)
                if not matched:
                    item={"date":date,"merchant_raw":merchant,"amount":round(amount,2),"currency":currency,"confidence":round(score,2)}
                    if date_unresolved:
                        item["date_unresolved"]=True
                        item["ocr_row_id"]=row_id
                    if tx.get("suggested_store"):
                        item["suggested_store"]=tx["suggested_store"]
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
                records.append({"date":date,"store":matched,"amount":converted,"currency":base_currency,"merchant_raw":raw_norm,"status":"added","date_unresolved":date_unresolved,"ocr_row_id":row_id})
                recognized.append({"date":date,"store":matched,"merchant_raw":raw_norm,"amount":converted,"currency":base_currency,"status":"added","date_unresolved":date_unresolved,"ocr_row_id":row_id})
            push_progress(i-1,0.97)
            if not found:
                warnings.append({"code":"no_amount_in_file","file":img.name})
        except Exception as exc:
            if isinstance(exc,OcrCancelled):
                raise
            warnings.append({"code":"file_error","file":img.name,"message":str(exc)})
            ocr_chunks.append(f"=== {img.name} ===\n[error] {str(exc)}")
        push_progress(i,1)
    check_cancel()
    for (date,merchant),amount in grouped.items():
        raw=" | ".join(sorted(x for x in grouped_raw.get((date,merchant),set()) if x))
        conn.execute("INSERT INTO expenses(date,store,amount,entry_source,ocr_raw_store) VALUES(?,?,?,?,?) ON CONFLICT(date,store) DO UPDATE SET amount=ROUND(amount+excluded.amount,2),entry_source='ocr',ocr_raw_store=CASE WHEN COALESCE(expenses.ocr_raw_store,'')='' THEN excluded.ocr_raw_store WHEN COALESCE(excluded.ocr_raw_store,'')='' THEN expenses.ocr_raw_store ELSE expenses.ocr_raw_store||' | '||excluded.ocr_raw_store END",(date,merchant,amount,"ocr",raw))
        records.append({"date":date,"store":merchant,"amount":amount,"currency":base_currency})
    conn.commit()
    conn.close()
    records.sort(key=lambda x:(x["date"],x.get("store","")))
    final={"state":"done","progress":{"total":len(uploads),"processed":len(uploads),"percent":100},"base_currency":base_currency,"warnings":warnings,"records":records,"recognized":recognized,"unresolved":unresolved,"added_count":added_count,"duplicate_count":duplicate_count,"timings":timings,"ocr_text":"\n\n".join(ocr_chunks),"updated_at":now_iso()}
    write_status(status_path,final)
def main():
    parser=argparse.ArgumentParser()
    parser.add_argument("--job-dir")
    parser.add_argument("--user-email")
    parser.add_argument("--base-currency")
    args=parser.parse_args()
    if not args.job_dir or not args.user_email or not args.base_currency:
        parser.error("--job-dir, --user-email and --base-currency required")
    job_dir=Path(args.job_dir)
    status_path=job_dir/"status.json"
    try:
        process(job_dir,args.user_email,args.base_currency.upper())
    except OcrCancelled:
        if status_path.parent.exists():
            write_status(status_path,{"state":"canceled","message":"canceled","updated_at":now_iso()})
    except Exception as exc:
        if status_path.parent.exists():
            write_status(status_path,{"state":"error","message":str(exc),"updated_at":now_iso()})
if __name__=="__main__":
    main()
