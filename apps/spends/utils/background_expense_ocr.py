#!/usr/bin/env python3
import argparse,json,re,subprocess,time
from pathlib import Path
MONTHS={"Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"}
CURRENCY_RE=re.compile(r"\b([A-Z]{3})\s*([0-9]+(?:[.,][0-9]{1,2})?)\b")
def normalize_merchant(value:str)->str:
    return re.sub(r"\s+"," ",value.strip())
def normalize_amount(raw:str)->str:
    return f"{float(raw.replace(',','.')):.2f}"
def parse_records_from_lines(lines):
    records=[];i=0
    while i<len(lines):
        line=lines[i].strip()
        amount_match=CURRENCY_RE.search(line)
        if not amount_match:
            i+=1
            continue
        merchant=normalize_merchant(CURRENCY_RE.sub("",line).strip())
        date=""
        if i+1<len(lines):
            parts=lines[i+1].strip().split()
            if len(parts)==2 and parts[0] in MONTHS and parts[1].isdigit():
                date=f"{parts[0]} {int(parts[1])}"
        currency,amount=amount_match.group(1),normalize_amount(amount_match.group(2))
        if merchant and currency:
            records.append({"merchant":merchant,"date":date,"currency":currency,"amount":amount})
        i+=1
    return records
def unique_records(records):
    seen=set();out=[]
    for r in records:
        key=(r["merchant"].upper(),r["date"],r["currency"],r["amount"])
        if key in seen:
            continue
        seen.add(key);out.append(r)
    return out
def ocr_image_tesseract(image_path:Path)->str:
    cmd=["tesseract",str(image_path),"stdout","--psm","6"]
    result=subprocess.run(cmd,capture_output=True,text=True,check=False)
    if result.returncode!=0:
        raise RuntimeError(f"OCR failed for {image_path.name}: {result.stderr.strip()}")
    return result.stdout
def collect_text_from_images(images):
    chunks=[]
    for p in images:
        chunks.append(ocr_image_tesseract(Path(p)))
    return "\n".join(chunks)
def parse_from_text(text:str):
    lines=[x.strip() for x in text.splitlines() if x.strip()]
    return unique_records(parse_records_from_lines(lines))
def write_report(records,out_path:Path):
    payload={"generated_at":time.strftime("%Y-%m-%dT%H:%M:%SZ",time.gmtime()),"total_records":len(records),"records":records}
    out_path.write_text(json.dumps(payload,ensure_ascii=False,indent=2),encoding="utf-8")
def run_once(images,output,ocr_text_file=None):
    text=Path(ocr_text_file).read_text(encoding="utf-8") if ocr_text_file else collect_text_from_images(images)
    records=parse_from_text(text)
    write_report(records,Path(output))
    return len(records)
def run_background(images,output,interval_sec,ocr_text_file=None):
    while True:
        try:
            run_once(images,output,ocr_text_file)
        except Exception:
            pass
        time.sleep(interval_sec)
def main():
    parser=argparse.ArgumentParser(description="Background OCR parser for expenses.")
    parser.add_argument("--images",nargs="+",required=True)
    parser.add_argument("--output",default="purchases_report.json")
    parser.add_argument("--ocr-text-file")
    parser.add_argument("--background",action="store_true")
    parser.add_argument("--interval-sec",type=int,default=30)
    args=parser.parse_args()
    if args.background:
        run_background(args.images,args.output,args.interval_sec,args.ocr_text_file);return
    count=run_once(args.images,args.output,args.ocr_text_file)
    print(f"Saved {count} records to {args.output}")
if __name__=="__main__":
    main()
