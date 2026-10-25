#!/usr/bin/env python3
import os
import subprocess
import tempfile
import base64
import re
import shutil
import time
import uuid
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import JSONResponse, Response

APP_ROOT = Path(__file__).resolve().parent
SCRIPTS = {
    "tts": APP_ROOT / "apps" / "cloud" / "tts_generate.py",
    "bg_remove": APP_ROOT / "apps" / "recipes" / "bg_remove.py",
    "parser": APP_ROOT / "apps" / "spends" / "utils" / "parser_bridge.py",
    "ocr": APP_ROOT / "apps" / "spends" / "utils" / "expense_ocr_worker.py",
    "process_pdf": APP_ROOT / "process_pdf.py",
}

app = FastAPI(title="appdashboard-py", version="1.0.0")


def _env() -> dict:
    env = os.environ.copy()
    lib = str(APP_ROOT / "lib")
    env["PYTHONPATH"] = lib + (os.pathsep + env["PYTHONPATH"] if env.get("PYTHONPATH") else "")
    env.setdefault("PYTHONUNBUFFERED", "1")
    return env


def _spawn(cmd: list[str], log_path: Optional[str] = None) -> None:
    stdout = subprocess.DEVNULL
    stderr = subprocess.STDOUT
    log_fh = None
    if log_path:
        log_fh = open(log_path, "a", encoding="utf-8")
        stdout = log_fh
        stderr = subprocess.STDOUT
    subprocess.Popen(
        cmd,
        env=_env(),
        stdout=stdout,
        stderr=stderr,
        start_new_session=True,
        close_fds=True,
    )
    if log_fh is not None:
        log_fh.close()


def _run(cmd: list[str], timeout: int = 300, cwd: Optional[str] = None, extra_env: Optional[dict] = None) -> subprocess.CompletedProcess:
    env = _env()
    if extra_env:
        env.update(extra_env)
    return subprocess.run(
        cmd,
        env=env,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        timeout=timeout,
        cwd=cwd,
        check=False,
    )


@app.get("/health")
def health():
    return {"status": "ok"}

SYNCTEX_ROOT = Path(tempfile.gettempdir()) / "synctex"
SYNCTEX_TTL = 1800


def _prune_synctex() -> None:
    now = time.time()
    if not SYNCTEX_ROOT.is_dir():
        return
    for d in SYNCTEX_ROOT.iterdir():
        if d.is_dir() and now - d.stat().st_mtime > SYNCTEX_TTL:
            shutil.rmtree(d, ignore_errors=True)


def _store_synctex(tmp: str) -> str:
    gz = Path(tmp) / "document.synctex.gz"
    if not gz.is_file():
        return ""
    _prune_synctex()
    sid = uuid.uuid4().hex
    dest = SYNCTEX_ROOT / sid
    dest.mkdir(parents=True, exist_ok=True)
    # PDF is required by synctex CLI (-o document.pdf); tex helps view queries.
    for name in ("document.synctex.gz", "document.pdf", "document.tex"):
        src = Path(tmp) / name
        if src.is_file():
            shutil.copy2(src, dest / name)
    return sid


def _synctex_dir(sid: str) -> Path:
    if not re.fullmatch(r"[0-9a-f]{32}", sid or ""):
        raise HTTPException(400, "bad id")
    d = SYNCTEX_ROOT / sid
    if not d.is_dir():
        raise HTTPException(404, "expired")
    return d


def _parse_synctex(output: str) -> dict:
    data = {}
    for line in output.splitlines():
        if ":" in line:
            k, v = line.split(":", 1)
            data.setdefault(k.strip(), v.strip())
    return data


@app.post("/latex")
async def latex_compile(
    latex: str = Form(...),
    engine: str = Form("pdflatex"),
    synctex: str = Form(""),
    format: str = Form("json"),
):
    selected = "lualatex" if engine == "lualatex" else "pdflatex"
    with tempfile.TemporaryDirectory() as tmp:
        source = Path(tmp) / "document.tex"
        source.write_text(latex, encoding="utf-8")
        mode = "-lualatex" if selected == "lualatex" else "-pdf"
        cmd = ["latexmk", mode, "-interaction=nonstopmode", "-halt-on-error"]
        if synctex:
            cmd += ["-e", f"${selected}='{selected} -synctex=1 -interaction=nonstopmode %O %S';"]
        cache = str(Path(tmp) / ".texcache")
        proc = _run(cmd + [source.name], timeout=300, cwd=tmp, extra_env={"HOME": tmp, "TEXMFVAR": cache, "TEXMFCACHE": cache, "TEXMFHOME": cache + "/home", "OSFONTDIR": "/usr/share/fonts"})
        pdf = Path(tmp) / "document.pdf"
        ok = proc.returncode == 0 and pdf.is_file()
        sid = _store_synctex(tmp) if ok and synctex else ""
        if format == "raw":
            if not ok:
                return JSONResponse(
                    {"success": False, "output": (proc.stdout or "")[-12000:], "synctex_id": ""},
                    status_code=422,
                )
            headers = {}
            if sid:
                headers["X-Synctex-Id"] = sid
            return Response(content=pdf.read_bytes(), media_type="application/pdf", headers=headers)
        return {
            "success": ok,
            "pdf": base64.b64encode(pdf.read_bytes()).decode("ascii") if pdf.is_file() else "",
            "output": (proc.stdout or "")[-12000:],
            "synctex_id": sid,
        }


@app.post("/synctex")
async def synctex_query(
    id: str = Form(...),
    mode: str = Form("view"),
    line: int = Form(1),
    col: int = Form(0),
    page: int = Form(1),
    x: float = Form(0),
    y: float = Form(0),
):
    d = _synctex_dir(id)
    if not (d / "document.synctex.gz").is_file():
        return {"ok": False, "error": "expired"}
    if mode == "edit":
        proc = _run(["synctex", "edit", "-o", f"{page}:{x}:{y}:document.pdf"], timeout=30, cwd=str(d))
        r = _parse_synctex(proc.stdout or "")
        if "Line" not in r:
            return {"ok": False}
        return {"ok": True, "line": int(r["Line"]), "column": max(0, int(r.get("Column", "0")))}
    proc = _run(["synctex", "view", "-i", f"{line}:{col}:document.tex", "-o", "document.pdf"], timeout=30, cwd=str(d))
    r = _parse_synctex(proc.stdout or "")
    if "Page" not in r:
        return {"ok": False}
    return {"ok": True, "page": int(r["Page"]), "x": float(r["x"]), "y": float(r["y"]), "h": float(r["h"]), "v": float(r["v"]), "w": float(r["W"]), "hh": float(r["H"])}


@app.post("/synctex/pdf")
async def synctex_pdf(id: str = Form(...)):
    d = _synctex_dir(id)
    pdf = d / "document.pdf"
    if not pdf.is_file():
        raise HTTPException(404, "pdf missing")
    # Touch mtime so active previews are not pruned mid-session.
    try:
        d.touch()
    except OSError:
        pass
    return Response(content=pdf.read_bytes(), media_type="application/pdf", headers={"X-Synctex-Id": id})


@app.post("/tts")
async def tts(
    text_file: str = Form(...),
    output_file: str = Form(...),
    language: str = Form("en"),
    speed: str = Form("1.0"),
    progress_id: str = Form(...),
    log_file: str = Form(""),
):
    script = SCRIPTS["tts"]
    if not script.is_file():
        raise HTTPException(500, "tts script missing")
    if not os.path.isfile(text_file):
        raise HTTPException(400, "text_file not found")
    cmd = ["python3", str(script), text_file, output_file, language, speed, progress_id]
    _spawn(cmd, log_file or None)
    return {"success": True, "started": True}


@app.post("/remove-bg")
async def remove_bg(file: UploadFile = File(...)):
    script = SCRIPTS["bg_remove"]
    if not script.is_file():
        raise HTTPException(500, "bg_remove script missing")
    suffix = Path(file.filename or "image.bin").suffix or ".img"
    with tempfile.TemporaryDirectory() as tmp:
        inp = Path(tmp) / f"input{suffix}"
        out = Path(tmp) / "output.png"
        inp.write_bytes(await file.read())
        proc = _run(["python3", str(script), str(inp), str(out)], timeout=300)
        if proc.returncode != 0 or not out.is_file():
            return JSONResponse(
                status_code=200,
                content={
                    "success": False,
                    "message": "background_remove_failed",
                    "details": (proc.stdout or "")[-4000:],
                    "exit_code": proc.returncode,
                },
            )
        import base64
        import json

        stats = None
        for line in (proc.stdout or "").splitlines()[::-1]:
            line = line.strip()
            if line.startswith("{"):
                try:
                    stats = json.loads(line).get("stats")
                except Exception:
                    pass
                break
        return {
            "success": True,
            "mime": "image/png",
            "filename": "background-removed.png",
            "data": base64.b64encode(out.read_bytes()).decode("ascii"),
            "stats": stats,
        }


@app.post("/parse-bank-statement")
async def parse_bank_statement(file: UploadFile = File(...)):
    script = SCRIPTS["parser"]
    if not script.is_file():
        raise HTTPException(500, "parser script missing")
    suffix = Path(file.filename or "statement.bin").suffix or ".bin"
    with tempfile.TemporaryDirectory() as tmp:
        inp = Path(tmp) / f"input{suffix}"
        out = Path(tmp) / "result.json"
        inp.write_bytes(await file.read())
        proc = _run(["python3", str(script), str(inp), str(out)], timeout=180)
        if proc.returncode != 0 or not out.is_file():
            raise HTTPException(
                500,
                detail={"message": "Parser failed", "details": (proc.stdout or "")[-4000:]},
            )
        import json

        data = json.loads(out.read_text(encoding="utf-8"))
        return data


@app.post("/ocr-expense")
async def ocr_expense(
    job_dir: str = Form(...),
    user_email: str = Form(...),
    base_currency: str = Form("USD"),
    log_file: str = Form(""),
):
    script = SCRIPTS["ocr"]
    if not script.is_file():
        raise HTTPException(500, "ocr script missing")
    if not os.path.isdir(job_dir):
        raise HTTPException(400, "job_dir not found")
    cmd = [
        "python3",
        str(script),
        "--job-dir",
        job_dir,
        "--user-email",
        user_email,
        "--base-currency",
        base_currency,
    ]
    _spawn(cmd, log_file or (str(Path(job_dir) / "worker.log")))
    return {"success": True, "started": True}


@app.post("/process-pdf")
async def process_pdf(
    markdown: UploadFile = File(...),
    font_path: str = Form("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"),
):
    script = SCRIPTS["process_pdf"]
    if not script.is_file():
        raise HTTPException(500, "process_pdf script missing")
    if not os.path.isfile(font_path):
        raise HTTPException(500, "font not found")
    with tempfile.TemporaryDirectory() as tmp:
        inp = Path(tmp) / "input.md"
        out = Path(tmp) / "out.pdf"
        inp.write_bytes(await markdown.read())
        proc = _run(["python3", str(script), str(inp), str(out), font_path], timeout=120)
        if proc.returncode != 0 or not out.is_file() or out.stat().st_size <= 0:
            raise HTTPException(
                500,
                detail={"message": "pdf generation failed", "details": (proc.stdout or "")[-4000:]},
            )
        return Response(content=out.read_bytes(), media_type="application/pdf")
