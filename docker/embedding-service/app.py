"""
Embedding Service for Magento 2 AI Product Recommendations

This lightweight service provides embeddings using sentence-transformers.
It's required because ChromaDB REST API doesn't support server-side embedding.

Usage:
    docker-compose up embedding-service

Endpoints:
    POST /embed - Generate embeddings for texts
    GET /health - Health check
"""

from flask import Flask, request, jsonify
from sentence_transformers import SentenceTransformer
import logging
import os

app = Flask(__name__)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Load model on startup (all-MiniLM-L6-v2 is fast and good quality)
MODEL_NAME = os.environ.get('EMBEDDING_MODEL', 'all-MiniLM-L6-v2')
model = None

def get_model():
    global model
    if model is None:
        logger.info(f"Loading model: {MODEL_NAME}")
        model = SentenceTransformer(MODEL_NAME)
        logger.info(f"Model loaded. Embedding dimension: {model.get_sentence_embedding_dimension()}")
    return model


@app.route('/health', methods=['GET'])
def health():
    """Health check endpoint"""
    try:
        m = get_model()
        return jsonify({
            'status': 'ok',
            'model': MODEL_NAME,
            'dimension': m.get_sentence_embedding_dimension()
        })
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        return jsonify({
            'status': 'error',
            'error': str(e)
        }), 500


@app.route('/embed', methods=['POST'])
def embed():
    """Generate embeddings for texts"""
    try:
        data = request.get_json()
        
        if not data or 'texts' not in data:
            return jsonify({'error': 'Missing "texts" field'}), 400
        
        texts = data['texts']
        
        if not isinstance(texts, list):
            return jsonify({'error': '"texts" must be a list'}), 400
        
        if len(texts) == 0:
            return jsonify({'embeddings': []})
        
        logger.info(f"Generating embeddings for {len(texts)} texts")
        
        # Get model
        m = get_model()
        
        # Generate embeddings
        embeddings = m.encode(texts, convert_to_numpy=True, show_progress_bar=False)
        
        # Convert to list of lists for JSON serialization
        embeddings_list = embeddings.tolist()
        
        logger.info(f"Generated {len(embeddings_list)} embeddings (dim: {len(embeddings_list[0])})")
        
        return jsonify({'embeddings': embeddings_list})
        
    except Exception as e:
        logger.error(f"Error generating embeddings: {str(e)}")
        return jsonify({'error': str(e)}), 500


@app.route('/', methods=['GET'])
def index():
    """Root endpoint"""
    return jsonify({
        'service': 'Embedding Service',
        'version': '1.0.0',
        'endpoints': {
            'POST /embed': 'Generate embeddings for texts',
            'GET /health': 'Health check'
        }
    })


# Pre-load model on startup
with app.app_context():
    try:
        get_model()
    except Exception as e:
        logger.error(f"Failed to load model on startup: {str(e)}")


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8001, debug=False)
