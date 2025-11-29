/**
 * Navindbhudiya ProductRecommendation
 * RequireJS Configuration
 */
var config = {
    map: {
        '*': {
            'personalizedSlider': 'Navindbhudiya_ProductRecommendation/js/personalized-slider',
            'Navindbhudiya_ProductRecommendation/js/personalized-slider': 'Navindbhudiya_ProductRecommendation/js/personalized-slider'
        }
    },
    shim: {
        'Navindbhudiya_ProductRecommendation/js/personalized-slider': {
            deps: ['jquery', 'underscore']
        }
    }
};
