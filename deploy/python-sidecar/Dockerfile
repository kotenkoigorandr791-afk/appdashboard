FROM python:3.12-slim-bookworm

ENV DEBIAN_FRONTEND=noninteractive
ENV PIP_DEFAULT_TIMEOUT=300
ENV PIP_RETRIES=10
ENV PYTHONUNBUFFERED=1
ENV MODELS_ROOT=/opt/ApplicationData/models
ENV HF_HOME=/opt/ApplicationData/models/huggingface
ENV U2NET_HOME=/opt/ApplicationData/models/rembg
ENV XDG_CACHE_HOME=/opt/ApplicationData/models/cache
ENV XDG_DATA_HOME=/opt/ApplicationData/models

RUN apt-get update && apt-get install -y --no-install-recommends \
    ffmpeg libgl1 libglib2.0-0 fonts-dejavu-core curl perl xz-utils \
    tesseract-ocr tesseract-ocr-eng tesseract-ocr-rus tesseract-ocr-ukr tesseract-ocr-bel \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    workdir="$(mktemp -d)"; \
    curl -fsSL https://mirror.ctan.org/systems/texlive/tlnet/install-tl-unx.tar.gz | tar -xz -C "$workdir"; \
    installer="$(find "$workdir" -maxdepth 2 -type f -name install-tl -print -quit)"; \
    perl "$installer" --no-interaction --scheme=small --texdir=/opt/texlive; \
    texbin="$(find /opt/texlive -type d -path '*/bin/*-linux' -print -quit)"; \
    ln -sfn "$texbin" /opt/texlive/tlbin; \
    packages="latexmk collection-latexrecommended collection-latexextra collection-fontsrecommended collection-langcyrillic cm-super luatex"; \
    for attempt in 1 2 3; do \
        if /opt/texlive/tlbin/tlmgr install $packages; then break; fi; \
        if [ "$attempt" = 3 ]; then exit 1; fi; \
        sleep 15; \
    done; \
    /opt/texlive/tlbin/mktexlsr; \
    rm -rf "$workdir"

ENV PATH=/opt/texlive/tlbin:${PATH}

# Fail the image build instead of the user's first compile. The editor sends
# Cyrillic documents, and pdflatex with \usepackage[T2A]{fontenc} dies with
# "Encoding file `t2aenc.def' not found" unless collection-langcyrillic (the
# cyrillic package) is installed -- scheme=small does not include it. Both
# engines the sidecar selects are exercised here: pdflatex for Latin/short
# documents, lualatex + fontspec for Unicode text.
RUN set -eux; \
    work=/tmp/latex-smoke; mkdir -p "$work"; cd "$work"; \
    printf '\\documentclass{article}\n\\usepackage[T2A]{fontenc}\n\\usepackage[utf8]{inputenc}\n\\usepackage[russian,english]{babel}\n\\begin{document}\nПривет, мир! Тест 0123.\n\\end{document}\n' > t2a.tex; \
    printf '\\documentclass{article}\n\\usepackage{fontspec}\n\\setmainfont{DejaVu Serif}\n\\begin{document}\nПривет, мир! Тест 0123.\n\\end{document}\n' > lua.tex; \
    test -n "$(kpsewhich t2aenc.def)"; \
    latexmk -pdf -interaction=nonstopmode -halt-on-error -quiet t2a.tex; \
    latexmk -lualatex -interaction=nonstopmode -halt-on-error -quiet lua.tex; \
    test -s t2a.pdf; \
    test -s lua.pdf; \
    cd /; rm -rf "$work"

WORKDIR /app

COPY deploy/python-sidecar/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY lib/pg_db.py lib/pg_sqlite_compat.py lib/pg_user_db.py /app/lib/
COPY apps/cloud/tts_generate.py /app/apps/cloud/
COPY apps/recipes/bg_remove.py /app/apps/recipes/
COPY apps/spends/utils/parser_bridge.py apps/spends/utils/expense_ocr_worker.py apps/spends/utils/merchant_classifier.py /app/apps/spends/utils/
COPY deploy/python-sidecar/process_pdf.py deploy/python-sidecar/server.py deploy/python-sidecar/entrypoint.sh /app/
RUN chmod +x /app/entrypoint.sh && chmod -R a+rX /app

# A fresh named volume inherits the ownership of its mountpoint from the image. Without
# these directories the container runs as www-data against a root-owned volume, cannot
# create its data dirs and exits on start -- the first `docker compose up` on a new host.
RUN mkdir -p /opt/ApplicationData /var/www && chown -R www-data:www-data /opt/ApplicationData /var/www

USER www-data

EXPOSE 8100
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8100/health || exit 1
ENTRYPOINT ["/app/entrypoint.sh"]
