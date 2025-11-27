# AI Product Recommendation for Magento 2

Intelligent product recommendations powered by AI vector embeddings and ChromaDB. This module uses semantic similarity to automatically suggest related, cross-sell, and up-sell products based on product descriptions, attributes, and categories.

## Key Features

- **AI-Powered Recommendations**: Uses vector embeddings (all-MiniLM-L6-v2) to find semantically similar products
- **ChromaDB v0.4.24 Integration**: Fast vector similarity search with persistent storage
- **Embedding Service**: Python-based embedding service using sentence-transformers
- **Automatic Product Indexing**: Products are automatically indexed when saved or via cron
- **Smart Caching**: Recommendations are cached for optimal performance
- **Fully Configurable**: Complete admin interface for all settings
- **CLI Tools**: Command-line tools for testing, indexing, and debugging
- **Fallback Support**: Falls back to native Magento recommendations if AI is unavailable
- **Multiple Recommendation Types**: Related Products, Cross-sell, Up-sell

## Requirements

- **Magento**: 2.4.x (Community Edition)
- **PHP**: 8.1 or higher
- **ChromaDB**: v0.4.24 (Docker container)
- **Embedding Service**: Python container with sentence-transformers
- **Docker**: Required for ChromaDB and embedding service
- **Composer**: For PHP dependencies

## Installation

### 1. Copy Module Files

```bash
# Copy module to Magento app/code directory
mkdir -p app/code/Navindbhudiya/ProductRecommendation
cp -r path/to/module/* app/code/Navindbhudiya/ProductRecommendation/
```

### 2. Setup Docker Containers

**For Warden Users:**
```bash
# Copy Warden configuration
cp app/code/Navindbhudiya/ProductRecommendation/docker/warden-env.yml .warden/warden-env.yml

# Start Warden environment
warden env up -d

# Wait for embedding service to load model
docker logs $(docker ps -qf name=embedding) -f
```

**For Standalone Docker:**
```bash
# Navigate to docker directory
cd app/code/Navindbhudiya/ProductRecommendation/docker/

# Start containers
docker-compose up -d

# Check logs
docker logs chromadb
docker logs embedding-service
```

### 3. Enable Module

```bash
# If using Warden
warden shell

# Enable module
bin/magento module:enable Navindbhudiya_ProductRecommendation

# Run setup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f

# Clear cache
bin/magento cache:flush
```

### 4. Verify Installation

```bash
# Test connections
bin/magento recommendation:test

# You should see:
# ✓ ChromaDB connection successful
# ✓ Embedding provider available
# ✓ Generated embedding with 384 dimensions
```

## Configuration

Navigate to: **Stores → Configuration → Navindbhudiya → AI Product Recommendation**

### General Settings
- **Enable Module**: Turn recommendations on/off
- **Debug Mode**: Enable detailed logging for troubleshooting

### ChromaDB Configuration
- **ChromaDB Host**: Hostname (default: `chromadb`)
- **ChromaDB Port**: Port number (default: `8000`)
- **Collection Name**: Collection for embeddings (default: `magento_products`)

### Embedding Configuration
- **Embedding Provider**: ChromaDB with all-MiniLM-L6-v2 (384 dimensions)
- **Product Attributes**: Attributes to include in embeddings (name, description, etc.)
- **Include Categories**: Include category names in product text

### Recommendation Settings
- **Enable AI Related Products**: Use AI for related products
- **Enable AI Cross-sell Products**: Use AI for cross-sell
- **Enable AI Up-sell Products**: Use AI for up-sell
- **Product Counts**: Number of recommendations per type
- **Similarity Threshold**: Minimum similarity score (0.0 - 1.0)
- **Price Threshold**: For up-sell, minimum price increase percentage

### Cache Settings
- **Enable Cache**: Cache recommendations for better performance
- **Cache Lifetime**: How long to cache (default: 3600 seconds)

## Usage

### Indexing Products

**Manual Indexing:**
```bash
bin/magento recommendation:index
```

**Automatic Indexing:**
Products are automatically indexed when:
- A product is saved in the admin
- The cron job runs (configurable schedule)

### Testing and Debugging

**Test Connection:**
```bash
bin/magento recommendation:test
```

**Get Similar Products:**
```bash
# By product ID
bin/magento recommendation:similar 123

# By text query
bin/magento recommendation:similar --query "red dress cotton"
```

**Clear Collection:**
```bash
# Clear all embeddings (requires confirmation)
bin/magento recommendation:clear

# Force clear without confirmation
bin/magento recommendation:clear --force
```

### CLI Commands Summary

| Command | Description |
|---------|-------------|
| `recommendation:test` | Test ChromaDB and embedding service connections |
| `recommendation:index` | Index all products |
| `recommendation:similar <id>` | Get similar products by ID |
| `recommendation:similar --query "text"` | Get similar products by text query |
| `recommendation:clear` | Clear all product embeddings |

## How It Works

```
┌─────────────────┐     ┌──────────────────┐     ┌───────────┐
│ Product Save    │────▶│ Embedding Service │────▶│ ChromaDB  │
│ or Indexer      │     │ (port 8001)       │     │ (port 8000│
└─────────────────┘     └──────────────────┘     └───────────┘
                              │                        │
                              ▼                        │
                        Generate vector          Store vector
                        (384 dimensions)         with metadata

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

## Architecture

### Components

1. **ChromaDB (v0.4.24)**: Vector database for storing and querying product embeddings
2. **Embedding Service**: Python Flask service that generates embeddings using all-MiniLM-L6-v2
3. **ChromaClient**: PHP client for communicating with ChromaDB REST API
4. **RecommendationService**: Core service for generating recommendations
5. **Product Indexer**: Indexes products and generates embeddings
6. **Plugin System**: Integrates with Magento's product listing blocks

### Embedding Model

- **Model**: all-MiniLM-L6-v2 (sentence-transformers)
- **Dimensions**: 384
- **Performance**: ~14k sentences/second on CPU
- **Size**: ~80MB
- **Quality**: Balanced trade-off between speed and accuracy

## Troubleshooting

### "Embedding service not available"

**Check if container is running:**
```bash
docker ps | grep embedding
```

**Check logs:**
```bash
docker logs $(docker ps -qf name=embedding)
```

**Test directly:**
```bash
curl -X POST http://embedding-service:8001/embed \
  -H "Content-Type: application/json" \
  -d '{"texts": ["test product"]}'
```

### "ChromaDB connection failed"

**Check ChromaDB container:**
```bash
docker ps | grep chromadb
docker logs $(docker ps -qf name=chromadb)
```

**Test connection:**
```bash
curl http://chromadb:8000/api/v1/heartbeat
```

### "422 Error from ChromaDB"

This means the code is trying to use `query_texts` without embeddings. This should not happen with the current version. Run:
```bash
bin/magento recommendation:test
```

### Empty Recommendations

1. **Verify products are indexed:**
```bash
bin/magento recommendation:test
# Check "Documents indexed" count
```

2. **Enable debug mode** and check logs:
```bash
bin/magento config:set product_recommendation/general/debug_mode 1
tail -f var/log/product_recommendation.log
```

3. **Reindex products:**
```bash
bin/magento recommendation:clear --force
bin/magento recommendation:index
```

### Slow Indexing

The embedding service processes products sequentially. For large catalogs:
- Run indexing during off-peak hours or maintenance windows
- Use the indexer with cron scheduling
- Consider indexing in batches via CLI

## Performance Optimization

1. **Enable Caching**: Set cache lifetime to 3600+ seconds
2. **Adjust Similarity Threshold**: Higher threshold = fewer but more relevant results
3. **Limit Product Counts**: Lower counts = faster response times
4. **Use Indexes**: Ensure database indexes are optimized
5. **Monitor ChromaDB**: Check ChromaDB memory usage and performance

## Development

### File Structure

```
app/code/Navindbhudiya/ProductRecommendation/
├── Api/                    # Service contracts
├── Block/                  # UI blocks
├── Console/Command/        # CLI commands
├── Controller/             # Controllers
├── Cron/                   # Cron jobs
├── Helper/                 # Helper classes
├── Model/                  # Data models
├── Observer/               # Event observers
├── Plugin/                 # Plugins
├── Service/                # Core services
├── docker/                 # Docker configuration
├── etc/                    # Configuration XML
└── view/                   # Templates and layouts
```

### Key Files

- `Service/ChromaClient.php` - ChromaDB HTTP client (v0.4.x and v0.5.x compatible)
- `Service/RecommendationService.php` - Main recommendation logic
- `Service/Embedding/ChromaDBEmbeddingProvider.php` - Embedding generation
- `Model/Indexer/ProductEmbedding.php` - Product indexer
- `docker/embedding-service/app.py` - Python embedding service

## Technical Details

### ChromaDB Version Compatibility

The module automatically detects ChromaDB version and uses the appropriate API:
- **v0.4.x**: Legacy API (`api/v1/collections`)
- **v0.5.x+**: Multi-tenant API (`api/v1/tenants/.../databases/.../collections`)

Currently configured for: **v0.4.24**

### Embedding Generation

1. Product text is built from configurable attributes
2. Text is sent to embedding-service (Python + sentence-transformers)
3. Embedding service returns 384-dimensional vector
4. Vector is stored in ChromaDB with product metadata
5. Similarity search uses L2 distance to find similar products

### Caching Strategy

- Recommendations are cached per product ID, type, and store
- Cache is cleared when products are updated
- Cache lifetime is configurable (default: 1 hour)
- Uses Magento's cache system

## Support

For issues, questions, or contributions:
- Check the `CLAUDE.md` file for detailed technical documentation
- Review `docs/LOCAL_INSTALLATION.md` for detailed setup instructions
- Enable debug mode and check logs at `var/log/product_recommendation.log`

## License

MIT License - See module files for details.

## Credits

- **Vector Database**: ChromaDB (https://www.trychroma.com/)
- **Embedding Model**: sentence-transformers/all-MiniLM-L6-v2
- **Framework**: Magento 2 Open Source

---

**Version**: 1.0.0
**Magento**: 2.4.x
**ChromaDB**: 0.4.24
**PHP**: 8.1+