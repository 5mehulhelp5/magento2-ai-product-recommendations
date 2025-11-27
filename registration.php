<?php
/**
 * Navindbhudiya ProductRecommendation
 *
 * AI-Powered Product Recommendations using ChromaDB Vector Database
 *
 * @category  Navindbhudiya
 * @package   Navindbhudiya_ProductRecommendation
 * @author    Navin Bhudiya
 * @copyright Copyright (c) 2025 Navin Bhudiya
 * @license   MIT License
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Navindbhudiya_ProductRecommendation',
    __DIR__
);
