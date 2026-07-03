import argparse
import csv
import json
import re
import unicodedata
from collections import defaultdict
from dataclasses import dataclass
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any, Dict, List, Optional, Set, Tuple
BASE_DIR=Path(__file__).resolve().parent
CATALOG_PATH=BASE_DIR/"catalog.json"
STOPWORDS={"oplata","opłata","platnosc","platnoscia","platba","plata","purchase","card","karta","visa","mastercard","debit","credit","payment","transakcja","transaction","pos","retail","market","supermarket","hypermarket","hyper","store","shop","sklep","nr","no","sp","z","oo","o","ltd","llc","sa","sro","trc","trk","city","online","express","ul","str","street","av","avenue","moskva","moscow","warszawa","warsaw","gdansk","bialystok","almaty","london","sofia"}
CYRILLIC_TO_LATIN={"а":"a","б":"b","в":"v","г":"g","д":"d","е":"e","ё":"e","ж":"zh","з":"z","и":"i","й":"i","к":"k","л":"l","м":"m","н":"n","о":"o","п":"p","р":"r","с":"s","т":"t","у":"u","ф":"f","х":"h","ц":"ts","ч":"ch","ш":"sh","щ":"sch","ъ":"","ы":"y","ь":"","э":"e","ю":"yu","я":"ya","і":"i","ї":"i","є":"e","ґ":"g"}
def strip_accents(value:str)->str:
    normalized=unicodedata.normalize("NFKD",value)
    return "".join(char for char in normalized if not unicodedata.combining(char))
def transliterate(value:str)->str:
    return "".join(CYRILLIC_TO_LATIN.get(char,char) for char in value)
def normalize_text(value:str)->str:
    value=transliterate(strip_accents(value).casefold())
    value=re.sub(r"[^a-z0-9]+"," ",value)
    tokens=[token for token in value.split() if token not in STOPWORDS and not token.isdigit()]
    return " ".join(tokens)
def compact_text(value:str)->str:
    return value.replace(" ","")
def ordered_token_match(text:str,alias:str)->float:
    text_tokens=text.split()
    alias_tokens=alias.split()
    if not text_tokens or not alias_tokens:
        return 0.0
    index=0
    score=0.0
    for alias_token in alias_tokens:
        matched=False
        while index<len(text_tokens):
            text_token=text_tokens[index]
            index+=1
            if alias_token==text_token or alias_token in text_token or text_token in alias_token:
                score+=1.0
                matched=True
                break
            if SequenceMatcher(None,alias_token,text_token).ratio()>=0.9:
                score+=0.85
                matched=True
                break
        if not matched:
            break
    return score/len(alias_tokens)
def ngrams(value:str,size:int=3)->Set[str]:
    if not value:
        return set()
    if len(value)<=size:
        return {value}
    return {value[index:index+size] for index in range(len(value)-size+1)}
def jaccard_score(left:str,right:str)->float:
    left_ngrams=ngrams(left)
    right_ngrams=ngrams(right)
    if not left_ngrams or not right_ngrams:
        return 0.0
    return len(left_ngrams&right_ngrams)/len(left_ngrams|right_ngrams)
def partial_ratio(left:str,right:str)->float:
    if not left or not right:
        return 0.0
    short,long=(left,right) if len(left)<=len(right) else (right,left)
    if short in long:
        return 1.0
    window=max(len(short)+2,1)
    if len(long)<=window:
        return SequenceMatcher(None,short,long).ratio()
    best=0.0
    for index in range(len(long)-window+1):
        candidate=long[index:index+window]
        best=max(best,SequenceMatcher(None,short,candidate).ratio())
    return best
def token_overlap(text:str,alias:str)->float:
    text_tokens=text.split()
    alias_tokens=alias.split()
    if not text_tokens or not alias_tokens:
        return 0.0
    total=0.0
    for alias_token in alias_tokens:
        best=0.0
        for text_token in text_tokens:
            if alias_token==text_token or alias_token in text_token or text_token in alias_token:
                best=1.0
                break
            best=max(best,SequenceMatcher(None,alias_token,text_token).ratio())
        total+=best
    return total/len(alias_tokens)
def load_catalog(path:Path=CATALOG_PATH)->Dict[str,List[str]]:
    raw_catalog=json.loads(path.read_text(encoding="utf-8"))
    prepared={}
    for merchant,aliases in raw_catalog.items():
        normalized_aliases=sorted({normalize_text(alias) for alias in aliases if normalize_text(alias)},key=len,reverse=True)
        prepared[merchant]=normalized_aliases
    return prepared
@dataclass(frozen=True)
class MatchResult:
    merchant:str
    score:float
    alias:str
class MerchantClassifier:
    def __init__(self,catalog:Optional[Dict[str,List[str]]]=None,threshold:float=0.67)->None:
        self.catalog=catalog or load_catalog()
        self.threshold=threshold
    def _score_alias(self,text:str,alias:str)->float:
        compact_candidate=compact_text(text)
        compact_alias=compact_text(alias)
        if compact_alias and compact_alias in compact_candidate:
            return 1.0
        seq_score=partial_ratio(compact_candidate,compact_alias)
        token_score=token_overlap(text,alias)
        ordered_score=ordered_token_match(text,alias)
        gram_score=jaccard_score(compact_candidate,compact_alias)
        bonus=0.12 if ordered_score==1.0 else 0.0
        return min(1.0,0.35*seq_score+0.2*gram_score+0.2*token_score+0.25*ordered_score+bonus)
    def classify(self,description:str)->MatchResult:
        normalized=normalize_text(description)
        if not normalized:
            return MatchResult("OTHER",0.0,"")
        best=MatchResult("OTHER",0.0,"")
        for merchant,aliases in self.catalog.items():
            local_alias=""
            local_score=0.0
            for alias in aliases:
                score=self._score_alias(normalized,alias)
                if score>local_score:
                    local_score=score
                    local_alias=alias
            if local_score>best.score:
                best=MatchResult(merchant,local_score,local_alias)
        return best if best.score>=self.threshold else MatchResult("OTHER",best.score,best.alias)
    def aggregate_rows(self,rows:List[Dict[str,str]],description_key:str="description",amount_key:str="amount")->Tuple[Dict[str,float],List[Dict[str,Any]],List[Dict[str,Any]]]:
        totals=defaultdict(float)
        classified=[]
        unknown=[]
        for row in rows:
            result=self.classify(row[description_key])
            amount=float(row[amount_key])
            totals[result.merchant]+=amount
            enriched=dict(row)
            enriched["merchant"]=result.merchant
            enriched["score"]=round(result.score,4)
            enriched["matched_alias"]=result.alias
            classified.append(enriched)
            if result.merchant=="OTHER":
                unknown.append(enriched)
        return dict(sorted(totals.items())),classified,unknown
def read_csv(path:Path)->List[Dict[str,str]]:
    with path.open("r",encoding="utf-8-sig",newline="") as handle:
        return list(csv.DictReader(handle))
def format_amount(value:float)->str:
    return f"{value:.2f}"
def run_demo(input_path:Path)->str:
    classifier=MerchantClassifier()
    rows=read_csv(input_path)
    totals,classified,unknown=classifier.aggregate_rows(rows)
    lines=["Transactions:"]
    for row in classified:
        lines.append(f"{row['description']} -> {row['merchant']} score={row['score']} alias={row['matched_alias']}")
    lines.append("")
    lines.append("Totals:")
    for merchant,amount in totals.items():
        lines.append(f"{merchant}: {format_amount(amount)}")
    lines.append("")
    lines.append("Unknown:")
    for row in unknown:
        lines.append(f"{row['description']} -> score={row['score']}")
    return "\n".join(lines)
def build_parser()->argparse.ArgumentParser:
    parser=argparse.ArgumentParser(description="Lightweight multilingual merchant classifier")
    parser.add_argument("input",nargs="?",default="sample_transactions.csv",help="CSV file with description and amount columns")
    return parser
def main()->None:
    args=build_parser().parse_args()
    print(run_demo(BASE_DIR/args.input))
if __name__=="__main__":
    main()
