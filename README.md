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
- **ChromaDB**: v0.4.24 or higher
- **Embedding Service**: Python service with sentence-transformers
- **Composer**: For PHP dependencies

## Installation

### 1. Install ChromaDB

ChromaDB is the vector database that powers the AI recommendations. Visit [https://www.trychroma.com/](https://www.trychroma.com/) for official documentation.

```bash
# Install ChromaDB
pip install chromadb

# Run ChromaDB server
chroma run --host 0.0.0.0 --port 8000
```

For more installation options and configuration, visit the [ChromaDB documentation](https://docs.trychroma.com/getting-started).

### 2. Install Embedding Service

The embedding service generates vector embeddings from product text using the sentence-transformers library.

```bash
# Install dependencies
pip install flask sentence-transformers

# Run the embedding service
# The service should run on port 8001 and provide an /embed endpoint
```

### 3. Copy Module Files

```bash
# Copy module to Magento app/code directory
mkdir -p app/code/Navindbhudiya/ProductRecommendation
cp -r path/to/module/* app/code/Navindbhudiya/ProductRecommendation/
```

### 4. Enable Module

```bash
# Enable module
bin/magento module:enable Navindbhudiya_ProductRecommendation

# Run setup
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f

# Clear cache
bin/magento cache:flush
```

### 5. Verify Installation

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

**Test embedding service:**
```bash
curl -X POST http://your-embedding-service:8001/embed \
  -H "Content-Type: application/json" \
  -d '{"texts": ["test product"]}'
```

Check your embedding service configuration in the admin panel.

### "ChromaDB connection failed"

**Test ChromaDB connection:**
```bash
curl http://your-chromadb-host:8000/api/v1/heartbeat
```

Verify ChromaDB host and port in **Stores > Configuration > Navindbhudiya > AI Product Recommendation > ChromaDB Configuration**.

### "422 Error from ChromaDB"

This means the code is trying to use `query_texts` without embeddings. Run the test command:
```bash
bin/magento recommendation:test
```

### Empty Recommendations

**Verify products are indexed:**
```bash
bin/magento recommendation:test
# Check "Documents indexed" count
```

**Enable debug mode and check logs:**
```bash
bin/magento config:set product_recommendation/general/debug_mode 1
tail -f var/log/product_recommendation.log
```

**Reindex products:**
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

### Complete Module Structure

```
app/code/Navindbhudiya/ProductRecommendation/
│
├── Api/                                          # Service Contracts & Interfaces
│   ├── Data/
│   │   ├── CustomerProfileInterface.php          # Customer profile data interface
│   │   ├── ProductEmbeddingInterface.php         # Product embedding data interface
│   │   └── RecommendationResultInterface.php     # Recommendation result interface
│   ├── BehaviorCollectorInterface.php            # Behavior collection contract
│   ├── ChromaClientInterface.php                 # ChromaDB client contract
│   ├── EmbeddingProviderInterface.php            # Embedding provider contract
│   ├── PersonalizedRecommendationInterface.php   # Personalized recommendations contract
│   ├── PersonalizedRecommendationManagementInterface.php  # Management interface
│   ├── ProductEmbeddingRepositoryInterface.php   # Product embedding repository contract
│   └── RecommendationServiceInterface.php        # Recommendation service contract
│
├── Block/                                        # UI Blocks
│   ├── Adminhtml/
│   │   └── System/Config/
│   │       └── TestConnection.php                # Admin test connection button
│   ├── Personalized/
│   │   └── Recommendations.php                   # Personalized recommendations block
│   └── Widget/
│       ├── PersonalizedProducts.php              # Widget for personalized products
│       └── PersonalizedRecommendations.php       # Personalized recommendations widget
│
├── Console/Command/                              # CLI Commands
│   ├── ClearCollection.php                       # Clear ChromaDB collection
│   ├── GetPersonalizedRecommendations.php        # Get personalized recommendations CLI
│   ├── GetSimilarProducts.php                    # Get similar products CLI
│   ├── IndexProducts.php                         # Index all products
│   ├── RefreshProfiles.php                       # Refresh customer profiles
│   └── TestConnection.php                        # Test connections CLI
│
├── Controller/                                   # Controllers
│   ├── Adminhtml/System/Config/
│   │   └── TestConnection.php                    # Admin test connection controller
│   └── Ajax/
│       └── Personalized.php                      # AJAX personalized recommendations
│
├── Cron/                                        # Cron Jobs
│   ├── CleanCache.php                            # Clean expired cache entries
│   ├── RefreshCustomerProfiles.php               # Refresh stale customer profiles
│   └── SyncEmbeddings.php                        # Sync product embeddings
│
├── Helper/                                      # Helpers
│   └── Config.php                                # Configuration helper
│
├── Model/                                       # Models & Data Objects
│   ├── Cache/Type/
│   │   └── Recommendation.php                    # Custom cache type
│   ├── Config/Source/
│   │   ├── EmbeddingProvider.php                 # Embedding provider dropdown
│   │   └── ProductAttributes.php                 # Product attributes dropdown
│   ├── Data/
│   │   ├── CustomerProfile.php                   # Customer profile data model
│   │   ├── ProductEmbedding.php                  # Product embedding data model
│   │   └── RecommendationResult.php              # Recommendation result data model
│   ├── Indexer/
│   │   └── ProductEmbedding.php                  # Product embedding indexer
│   ├── ResourceModel/
│   │   └── CustomerProfile/
│   │       ├── Collection.php                    # Customer profile collection
│   │       └── CustomerProfile.php               # Customer profile resource model
│   ├── PersonalizedRecommendationManagement.php  # Personalized recommendation management
│   └── Resolver/
│       └── PersonalizedRecommendations.php       # GraphQL resolver
│
├── Observer/                                    # Event Observers
│   ├── CustomerLoginRefresh.php                  # Refresh profile on customer login
│   ├── ProductDeleteBefore.php                   # Handle product deletion
│   ├── ProductMassUpdate.php                     # Handle mass product updates
│   ├── ProductSaveAfter.php                      # Index product after save
│   └── ProductViewTracker.php                    # Track product views
│
├── Plugin/                                      # Plugins (Interceptors)
│   ├── Checkout/
│   │   └── CrosssellProducts.php                 # Override cross-sell products
│   └── Product/
│       ├── RelatedProducts.php                   # Override related products
│       └── UpsellProducts.php                    # Override up-sell products
│
├── Service/                                     # Core Business Logic
│   ├── BehaviorCollector/
│   │   ├── BrowsingHistoryCollector.php          # Collect browsing history
│   │   ├── PurchaseHistoryCollector.php          # Collect purchase history
│   │   └── WishlistCollector.php                 # Collect wishlist data
│   ├── Embedding/
│   │   ├── ChromaDBEmbeddingProvider.php         # ChromaDB embedding provider
│   │   └── EmbeddingProviderFactory.php          # Embedding provider factory
│   ├── ChromaClient.php                          # ChromaDB HTTP client
│   ├── PersonalizedRecommendationService.php     # Personalized recommendation service
│   ├── ProductTextBuilder.php                    # Build product text for embeddings
│   └── RecommendationService.php                 # Main recommendation service
│
├── Setup/                                       # Database Setup (deprecated location)
│
├── docs/                                        # Documentation
│   └── LOCAL_INSTALLATION.md                     # Detailed installation guide
│
├── etc/                                         # Module Configuration
│   ├── adminhtml/
│   │   ├── routes.xml                            # Admin routes
│   │   └── system.xml                            # Admin configuration structure
│   ├── frontend/
│   │   ├── di.xml                                # Frontend dependency injection
│   │   ├── events.xml                            # Frontend events
│   │   └── routes.xml                            # Frontend routes
│   ├── acl.xml                                   # ACL permissions
│   ├── cache.xml                                 # Cache type definition
│   ├── config.xml                                # Default configuration values
│   ├── crontab.xml                               # Cron job schedule
│   ├── db_schema.xml                             # Database schema
│   ├── di.xml                                    # Dependency injection
│   ├── events.xml                                # Event observers
│   ├── graphql/                                  # GraphQL schema (if present)
│   ├── indexer.xml                               # Indexer configuration
│   ├── module.xml                                # Module declaration
│   ├── mview.xml                                 # Materialized view
│   ├── webapi.xml                                # REST API definitions
│   └── widget.xml                                # Widget definitions
│
├── view/                                        # Templates, Layouts & Assets
│   ├── adminhtml/
│   │   └── templates/system/config/
│   │       └── test_connection.phtml             # Test connection template
│   └── frontend/
│       ├── layout/
│       │   ├── cms_index_index.xml               # Homepage layout
│       │   ├── customer_account_index.xml        # Customer account layout
│       │   └── default.xml                       # Default layout
│       ├── templates/personalized/
│       │   ├── hyva/
│       │   │   └── recommendations.phtml         # Hyva theme template
│       │   ├── recommendations.phtml             # Personalized recommendations
│       │   └── slider.phtml                      # Slider template
│       ├── web/
│       │   ├── css/
│       │   │   └── personalized.css              # Personalized CSS styles
│       │   └── js/
│       │       └── personalized-slider.js        # Slider JavaScript
│       └── requirejs-config.js                   # RequireJS configuration
│
├── .gitignore                                   # Git ignore rules
├── CLAUDE.md                                    # AI assistant context file
├── LICENSE.txt                                  # Module license
├── README.md                                    # This file
├── composer.json                                # Composer dependencies
└── registration.php                             # Module registration
```

### Key Files Explained

#### Core Services
- **`Service/ChromaClient.php`**
  HTTP client for ChromaDB REST API (v0.4.x and v0.5.x compatible). Handles all vector database operations.

- **`Service/RecommendationService.php`**
  Main recommendation logic. Uses query embeddings (NOT query_texts) to find similar products.

- **`Service/PersonalizedRecommendationService.php`**
  Generates personalized recommendations based on customer behavior profiles.

- **`Service/ProductTextBuilder.php`**
  Extracts and builds text from product attributes for embedding generation.

#### Embedding Providers
- **`Service/Embedding/ChromaDBEmbeddingProvider.php`**
  Calls embedding-service to generate vectors using sentence-transformers.

- **`Service/Embedding/EmbeddingProviderFactory.php`**
  Factory for creating embedding provider instances.

#### Behavior Collectors
- **`Service/BehaviorCollector/BrowsingHistoryCollector.php`**
  Collects customer browsing history from `report_viewed_product_index`.

- **`Service/BehaviorCollector/PurchaseHistoryCollector.php`**
  Collects purchase history from `sales_order_item`.

- **`Service/BehaviorCollector/WishlistCollector.php`**
  Collects wishlist items from `wishlist_item`.

#### Indexing & Data
- **`Model/Indexer/ProductEmbedding.php`**
  Indexes products and generates embeddings. Triggered on product save or via CLI.

- **`Model/ResourceModel/CustomerProfile.php`**
  Customer profile resource model for database operations.

#### Plugins
- **`Plugin/Product/RelatedProducts.php`**
  Intercepts related product loading to inject AI recommendations.

- **`Plugin/Product/UpsellProducts.php`**
  Intercepts up-sell product loading to inject AI recommendations.

- **`Plugin/Checkout/CrosssellProducts.php`**
  Intercepts cross-sell product loading to inject AI recommendations.

#### CLI Commands
All CLI commands are in `Console/Command/`:
- `TestConnection.php` - Test ChromaDB and embedding service
- `IndexProducts.php` - Index all products
- `GetSimilarProducts.php` - Get similar products by ID or query
- `ClearCollection.php` - Clear all embeddings
- `GetPersonalizedRecommendations.php` - Get personalized recommendations
- `RefreshProfiles.php` - Refresh customer profiles

#### Configuration
- **`etc/di.xml`** - Dependency injection configuration
- **`etc/config.xml`** - Default module configuration values
- **`etc/adminhtml/system.xml`** - Admin configuration structure
- **`etc/webapi.xml`** - REST API endpoint definitions
- **`etc/db_schema.xml`** - Database table definitions

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

## Personalized Recommendations (v2.0.0)

### Overview

Version 2.0.0 introduces **AI-powered personalized recommendations** based on customer behavior. The module now tracks and analyzes:

- **Browsing History**: Products the customer has viewed
- **Purchase History**: Products the customer has bought
- **Wishlist**: Products saved to wishlist

### New Recommendation Types

| Type | Description | Data Source | Guest Support |
|------|-------------|-------------|---------------|
| **Inspired by Browsing** | Products similar to what customer has viewed | `report_viewed_product_index` + session | ✅ Yes |
| **Based on Past Purchases** | Complementary products to purchases | `sales_order_item` | ❌ No |
| **Inspired by Wishlist** | Products similar to wishlist items | `wishlist_item` | ❌ No |
| **Just For You** | Combined weighted recommendations | All sources | Partial |

### How It Works

1. **Behavior Collection**: Customer actions (views, purchases, wishlist adds) are tracked
2. **Profile Generation**: Product embeddings are averaged to create a customer profile vector
3. **Similarity Search**: ChromaDB finds products similar to the customer profile
4. **Weighted Scoring**: "Just For You" combines all behavior with configurable weights:
   - Wishlist: 40% (highest purchase intent)
   - Purchases: 35% (proven preferences)
   - Browsing: 25% (interest exploration)

### CLI Commands

```bash
# Get personalized recommendations for a customer
bin/magento recommendation:personalized 123 --type=just_for_you --limit=10

# Refresh customer profiles
bin/magento recommendation:refresh-profiles 123
bin/magento recommendation:refresh-profiles --all --stale=24
```

### REST API Endpoints

```
GET /V1/recommendation/personalized/browsing
GET /V1/recommendation/personalized/purchase
GET /V1/recommendation/personalized/wishlist
GET /V1/recommendation/personalized/justforyou
GET /V1/recommendation/personalized/guest/browsing
```

### GraphQL Query

```graphql
query {
  personalizedRecommendations(type: JUST_FOR_YOU, limit: 8) {
    items {
      product {
        name
        sku
        price_range { ... }
      }
      score
      position
    }
    total_count
    has_data
  }
}
```

### Widget

A CMS widget "AI Personalized Recommendations" is available for placement anywhere in your store via Content > Widgets.

### Admin Configuration

Navigate to **Stores > Configuration > Navindbhudiya > AI Product Recommendation > Personalized Recommendations**:

- Enable/disable each recommendation type
- Set product limits
- Configure weights for "Just For You" calculation
- Choose which pages to display on

### Database Tables

| Table | Purpose |
|-------|---------|
| `ai_customer_profile` | Stores customer behavior profile embeddings |
| `ai_personalized_recommendations` | Cached personalized recommendations |
| `ai_guest_browsing_history` | Session-based guest browsing history |

### Cron Jobs

- **Refresh Profiles**: Runs every 6 hours to refresh stale customer profiles
- **Cleanup**: Removes expired cache entries and old guest browsing history

## License

MIT License - See module files for details.

## Credits

- **Vector Database**: ChromaDB (https://www.trychroma.com/)
- **Embedding Model**: sentence-transformers/all-MiniLM-L6-v2
- **Framework**: Magento 2 Open Source

---

**Version**: 2.1.0
**Magento**: 2.4.x
**ChromaDB**: 0.4.24
**PHP**: 8.1+