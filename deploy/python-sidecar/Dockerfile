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
    ffmpeg libgl1 libglib2.0-0 fonts-dejavu-core curl \
    tesseract-ocr tesseract-ocr-eng tesseract-ocr-rus tesseract-ocr-ukr tesseract-ocr-bel \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY deploy/python-sidecar/requirements.txt /app/requirements.txt
RUN pip install --no-cache-dir -r /app/requirements.txt

COPY lib/pg_db.py lib/pg_sqlite_compat.py lib/pg_user_db.py /app/lib/
COPY apps/cloud/tts_generate.py /app/apps/cloud/
COPY apps/recipes/bg_remove.py /app/apps/recipes/
COPY apps/spends/utils/parser_bridge.py apps/spends/utils/expense_ocr_worker.py apps/spends/utils/merchant_classifier.py /app/apps/spends/utils/
COPY deploy/python-sidecar/process_pdf.py deploy/python-sidecar/server.py deploy/python-sidecar/entrypoint.sh /app/
RUN chmod +x /app/entrypoint.sh && chmod -R a+rX /app

EXPOSE 8100
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8100/health || exit 1
ENTRYPOINT ["/app/entrypoint.sh"]
