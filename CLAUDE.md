# CLAUDE.md - AI Product Recommendation Module

## Project Overview

This is a Magento 2 module (`Navindbhudiya_ProductRecommendation`) that provides AI-powered product recommendations using ChromaDB vector database. The module uses semantic similarity search to automatically generate related, cross-sell, and up-sell product recommendations.

## Tech Stack

- **Platform**: Magento 2.4.x (Community Edition)
- **PHP**: 8.1+
- **Vector Database**: ChromaDB v0.4.24
- **Embedding Service**: Python + sentence-transformers (all-MiniLM-L6-v2)
- **Local Development**: Warden (Docker-based)
- **Embedding Model**: all-MiniLM-L6-v2 (384 dimensions)

## CRITICAL: Embedding Service Requirement

**ChromaDB's REST API does NOT support `query_texts`** without a server-side embedding function. You MUST run the embedding-service container alongside ChromaDB.

The flow is:
1. Product text → Embedding Service → Vector embedding
2. Vector embedding → ChromaDB → Store/Query
3. ChromaDB returns similar products by vector distance

## Directory Structure

```
Navindbhudiya/ProductRecommendation/
├── Api/                          # Service contracts (interfaces)
├── Block/Adminhtml/              # Admin UI blocks
├── Console/Command/              # CLI commands
├── Controller/Adminhtml/         # Admin controllers
├── Cron/                         # Cron jobs
├── Helper/Config.php             # Configuration helper
├── Model/
│   ├── Indexer/                  # Product embedding indexer
│   ├── Cache/Type/               # Custom cache type
│   ├── Config/Source/            # Admin config dropdowns
│   └── Data/                     # Data models
├── Observer/                     # Event observers
├── Plugin/                       # Frontend plugins
├── Service/
│   ├── ChromaClient.php          # ChromaDB HTTP client
│   ├── RecommendationService.php # Core recommendation logic
│   ├── ProductTextBuilder.php    # Product text extraction
│   └── Embedding/                # Embedding providers
├── docker/
│   ├── embedding-service/        # Python embedding service
│   │   ├── app.py               # Flask app
│   │   └── Dockerfile
│   ├── warden-env.yml           # Warden configuration
│   └── docker-compose.yml       # Standalone docker-compose
├── etc/                          # Magento configuration XML
└── view/adminhtml/               # Admin templates
```

## Development Environment (Warden)

### Quick Setup

1. Copy docker config to your project:
```bash
cp docker/warden-env.yml /path/to/magento/.warden/warden-env.yml
```

2. Start environment:
```bash
warden env up -d
```

3. Wait for embedding service to load model (check logs):
```bash
docker logs $(docker ps -qf name=embedding) -f
```

4. Install module:
```bash
warden shell
bin/magento module:enable Navindbhudiya_ProductRecommendation
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

5. Test and index:
```bash
bin/magento recommendation:test
bin/magento recommendation:index
```

### Warden Configuration (.warden/warden-env.yml)

```yaml
version: "3.5"

services:
  chromadb:
    container_name: ${WARDEN_ENV_NAME}_chromadb
    image: chromadb/chroma:0.4.24
    restart: unless-stopped
    environment:
      - IS_PERSISTENT=TRUE
      - ANONYMIZED_TELEMETRY=FALSE
      - ALLOW_RESET=TRUE
    volumes:
      - chromadb_data:/chroma/chroma
    labels:
      - traefik.enable=false

  embedding-service:
    container_name: ${WARDEN_ENV_NAME}_embedding
    build:
      context: ./app/code/Navindbhudiya/ProductRecommendation/docker/embedding-service
      dockerfile: Dockerfile
    restart: unless-stopped
    labels:
      - traefik.enable=false
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8001/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 60s

volumes:
  chromadb_data:
```

### Common Commands

```bash
# Start environment
warden env up -d

# Check embedding service logs
docker logs $(docker ps -qf name=embedding) -f

# SSH into container
warden shell

# Test connections
bin/magento recommendation:test

# Index products
bin/magento recommendation:index

# Get similar products
bin/magento recommendation:similar 123
bin/magento recommendation:similar --query "red dress"

# Clear and rebuild
bin/magento recommendation:clear --force
bin/magento recommendation:index
```

## Troubleshooting

### "Embedding service not available" Error

1. Check if container is running:
```bash
docker ps | grep embedding
```

2. Check logs for errors:
```bash
docker logs $(docker ps -qf name=embedding)
```

3. Test embedding service directly:
```bash
curl -X POST http://embedding-service:8001/embed \
  -H "Content-Type: application/json" \
  -d '{"texts": ["test product"]}'
```

### "422 Error" from ChromaDB

This means you're sending `query_texts` without embeddings. The code should be using `query_embeddings`. Make sure you have the latest version with the fix.

### Empty Recommendations

1. Check if products are indexed:
```bash
bin/magento recommendation:test
# Look for "Documents indexed: X"
```

2. Enable debug mode:
```bash
bin/magento config:set product_recommendation/general/debug_mode 1
```

3. Check logs:
```bash
tail -f var/log/product_recommendation.log
```

### Slow Indexing

The embedding service processes texts sequentially. For large catalogs:
- Run indexing during off-peak hours
- Use the indexer during maintenance windows

## Key Configuration

### Admin Settings
`Stores > Configuration > Navindbhudiya > AI Product Recommendation`

### Config Paths
```
product_recommendation/general/enabled
product_recommendation/chromadb/host          # default: chromadb
product_recommendation/chromadb/port          # default: 8000
product_recommendation/embedding/provider     # default: chromadb
```

### Embedding Configuration

**ChromaDB with embedding-service** (Only supported provider)
- Host: `chromadb` (default)
- Port: `8000` (default)
- Embedding service: `embedding-service:8001` (automatically detected)
- Model: all-MiniLM-L6-v2 (384 dimensions)
- Collection: `magento_products` (configurable)

## How It Works

```
┌─────────────────┐     ┌──────────────────┐     ┌───────────┐
│ Product Save    │────▶│ Embedding Service │────▶│ ChromaDB  │
│ or Indexer      │     │ (port 8001)       │     │ (port 8000│
└─────────────────┘     └──────────────────┘     └───────────┘
                              │                        │
                              ▼                        │
                        Generate vector          Store vector
                        from text                with metadata
                              
┌─────────────────┐     ┌──────────────────┐     ┌───────────┐
│ Product Page    │────▶│ Embedding Service │────▶│ ChromaDB  │
│ (get related)   │     │ Generate query    │     │ Find      │
└─────────────────┘     │ embedding         │     │ similar   │
                        └──────────────────┘     └───────────┘
                                                       │
                              ┌─────────────────────────┘
                              ▼
                        Return product IDs
                        with similarity scores
```

## Files to Know

- `Service/ChromaClient.php` - HTTP client for ChromaDB
- `Service/RecommendationService.php` - Main logic (uses query_embeddings, NOT query_texts)
- `Service/Embedding/ChromaDBEmbeddingProvider.php` - Calls embedding-service
- `Model/Indexer/ProductEmbedding.php` - Indexes products with embeddings
- `docker/embedding-service/app.py` - Python Flask embedding service

## never run any warden command just share the instruction and if required to run then confirm first.
