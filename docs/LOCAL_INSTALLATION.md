# Local Installation Guide (Without Docker)

This guide explains how to install ChromaDB and the Embedding Service locally without using Docker.

## Prerequisites

- Python 3.10+ installed
- pip (Python package manager)
- A terminal/command prompt

## Option 1: Install ChromaDB Locally

### Step 1: Install ChromaDB

```bash
# Create a virtual environment (recommended)
python3 -m venv chromadb-env
source chromadb-env/bin/activate  # On Windows: chromadb-env\Scripts\activate

# Install ChromaDB
pip install chromadb==0.4.24
```

### Step 2: Run ChromaDB Server

```bash
# Activate virtual environment if not already active
source chromadb-env/bin/activate

# Run ChromaDB server
chroma run --host 0.0.0.0 --port 8000 --path ./chromadb_data
```

The server will start at `http://localhost:8000`

### Step 3: Install Embedding Service

In a new terminal:

```bash
# Create a separate virtual environment
python3 -m venv embedding-env
source embedding-env/bin/activate

# Install dependencies
pip install flask gunicorn sentence-transformers

# Create the app.py file (copy from docker/embedding-service/app.py)
# Or run directly:
cd /path/to/magento/app/code/Navindbhudiya/ProductRecommendation/docker/embedding-service
python app.py
```

The embedding service will run at `http://localhost:8001`

### Step 4: Configure Magento

In Magento admin: Stores > Configuration > Navindbhudiya > AI Product Recommendation

Set:
- **ChromaDB Host**: `localhost` (or `127.0.0.1`)
- **ChromaDB Port**: `8000`

Or via CLI:
```bash
bin/magento config:set product_recommendation/chromadb/host localhost
bin/magento config:set product_recommendation/chromadb/port 8000
bin/magento cache:flush
```

---

## Option 2: Use systemd Services (Linux Production)

### ChromaDB Service

Create `/etc/systemd/system/chromadb.service`:

```ini
[Unit]
Description=ChromaDB Vector Database
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/chromadb
Environment=PATH=/opt/chromadb/venv/bin
ExecStart=/opt/chromadb/venv/bin/chroma run --host 127.0.0.1 --port 8000 --path /opt/chromadb/data
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### Embedding Service

Create `/etc/systemd/system/embedding-service.service`:

```ini
[Unit]
Description=Embedding Service for AI Recommendations
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/embedding-service
Environment=PATH=/opt/embedding-service/venv/bin
ExecStart=/opt/embedding-service/venv/bin/gunicorn --bind 127.0.0.1:8001 --workers 2 --timeout 120 app:app
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable chromadb embedding-service
sudo systemctl start chromadb embedding-service
```

---

## Option 3: Use Supervisor (Alternative to systemd)

### Install Supervisor

```bash
sudo apt-get install supervisor
```

### Configure ChromaDB

Create `/etc/supervisor/conf.d/chromadb.conf`:

```ini
[program:chromadb]
command=/opt/chromadb/venv/bin/chroma run --host 127.0.0.1 --port 8000 --path /opt/chromadb/data
directory=/opt/chromadb
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/chromadb.err.log
stdout_logfile=/var/log/chromadb.out.log
```

### Configure Embedding Service

Create `/etc/supervisor/conf.d/embedding-service.conf`:

```ini
[program:embedding-service]
command=/opt/embedding-service/venv/bin/gunicorn --bind 127.0.0.1:8001 --workers 2 --timeout 120 app:app
directory=/opt/embedding-service
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/embedding-service.err.log
stdout_logfile=/var/log/embedding-service.out.log
```

Apply:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## Testing

### Test ChromaDB

```bash
curl http://localhost:8000/api/v1/heartbeat
# Should return: {"nanosecond heartbeat": ...}
```

### Test Embedding Service

```bash
curl -X POST http://localhost:8001/embed \
  -H "Content-Type: application/json" \
  -d '{"texts": ["test product description"]}'
# Should return: {"embeddings": [[0.123, -0.456, ...]]}
```

### Test from Magento

```bash
bin/magento recommendation:test
```

---

## Troubleshooting

### ChromaDB won't start

```bash
# Check if port is in use
lsof -i :8000

# Check logs
journalctl -u chromadb -f
```

### Embedding service memory issues

The model uses ~500MB RAM. Reduce workers if needed:
```bash
gunicorn --bind 127.0.0.1:8001 --workers 1 --timeout 120 app:app
```

### Connection refused from Magento

If Magento is in Docker (Warden) but services are local:
- Use host IP instead of `localhost`
- On Mac: use `host.docker.internal`
- On Linux: use the host's IP address

```bash
# Find your host IP
ip addr show docker0
# Or
hostname -I
```

Then configure:
```bash
bin/magento config:set product_recommendation/chromadb/host 172.17.0.1
```

---

## Quick Start Script

Save this as `start-local.sh`:

```bash
#!/bin/bash

# Start ChromaDB
echo "Starting ChromaDB..."
cd /opt/chromadb
source venv/bin/activate
nohup chroma run --host 127.0.0.1 --port 8000 --path ./data > chromadb.log 2>&1 &
echo "ChromaDB PID: $!"

# Start Embedding Service
echo "Starting Embedding Service..."
cd /opt/embedding-service
source venv/bin/activate
nohup gunicorn --bind 127.0.0.1:8001 --workers 1 --timeout 120 app:app > embedding.log 2>&1 &
echo "Embedding Service PID: $!"

echo "Services started. Test with:"
echo "  curl http://localhost:8000/api/v1/heartbeat"
echo "  curl http://localhost:8001/health"
```

Make executable:
```bash
chmod +x start-local.sh
./start-local.sh
```
