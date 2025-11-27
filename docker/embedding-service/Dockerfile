FROM python:3.10-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install latest compatible versions
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir \
    flask==3.0.0 \
    gunicorn==21.2.0 \
    sentence-transformers

# Copy application
COPY app.py .

# Download model at build time (faster startup)
RUN python -c "from sentence_transformers import SentenceTransformer; print('Loading model...'); m = SentenceTransformer('all-MiniLM-L6-v2'); print(f'Model loaded, dim: {m.get_sentence_embedding_dimension()}')"

EXPOSE 8001

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8001/health || exit 1

# Use gunicorn for production
CMD ["gunicorn", "--bind", "0.0.0.0:8001", "--workers", "1", "--timeout", "120", "app:app"]
