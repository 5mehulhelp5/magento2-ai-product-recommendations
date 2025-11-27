# AI Product Recommendation for Magento 2

- **AI-Powered Recommendations**: Uses vector embeddings to find semantically similar products
- **ChromaDB Integration**: Leverages ChromaDB for fast vector similarity search
- **Multiple Embedding Providers**:
  - ChromaDB (default, no external API needed)
  - OpenAI (text-embedding-3-small/large, ada-002)
  - Ollama (local AI, privacy-friendly)
- **Automatic Product Indexing**: Products are automatically indexed when saved
- **Smart Caching**: Recommendations are cached for optimal performance
- **Configurable**: Full admin configuration for all settings
- **CLI Tools**: Command-line tools for testing and reindexing
- **Fallback Support**: Falls back to native Magento recommendations if AI is unavailable

## Requirements

- Magento 2.4.x (Community Edition)
- PHP 8.1 or higher
- ChromaDB server (Docker recommended)
- Composer
