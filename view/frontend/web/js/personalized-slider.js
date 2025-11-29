/**
 * Navindbhudiya ProductRecommendation
 * Personalized Products Slider JavaScript
 */
define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (config, element) {
        var sliderId = config.sliderId || null;
        var $container = $(element);
        var $slider = sliderId ? $('#' + sliderId) : $container.find('.products-slider');
        var $slides = $slider.find('.slide-item');
        var $prevBtn = $container.find('.slider-prev');
        var $nextBtn = $container.find('.slider-next');
        var $pagination = $container.find('.slider-pagination');
        
        var currentIndex = 0;
        var slidesPerView = 4;
        var totalSlides = $slides.length;
        var maxIndex = 0;
        var autoplayInterval = null;
        var autoplayDelay = config.autoplay || 0; // 0 = disabled
        
        /**
         * Calculate slides per view based on viewport
         */
        function calculateSlidesPerView() {
            var windowWidth = $(window).width();
            
            if (windowWidth <= 480) {
                slidesPerView = 1;
            } else if (windowWidth <= 768) {
                slidesPerView = 2;
            } else if (windowWidth <= 1024) {
                slidesPerView = 3;
            } else {
                slidesPerView = 4;
            }
            
            maxIndex = Math.max(0, totalSlides - slidesPerView);
        }
        
        /**
         * Update slider position
         */
        function updateSlider() {
            var slideWidth = 100 / slidesPerView;
            var translateX = -(currentIndex * slideWidth);
            
            $slider.css('transform', 'translateX(' + translateX + '%)');
            
            // Update pagination
            $pagination.find('.dot').removeClass('active');
            $pagination.find('.dot').eq(currentIndex).addClass('active');
            
            // Update button states
            $prevBtn.prop('disabled', currentIndex === 0);
            $nextBtn.prop('disabled', currentIndex >= maxIndex);
        }
        
        /**
         * Go to previous slide
         */
        function prevSlide() {
            if (currentIndex > 0) {
                currentIndex--;
                updateSlider();
            }
        }
        
        /**
         * Go to next slide
         */
        function nextSlide() {
            if (currentIndex < maxIndex) {
                currentIndex++;
                updateSlider();
            }
        }
        
        /**
         * Go to specific slide
         */
        function goToSlide(index) {
            currentIndex = Math.max(0, Math.min(index, maxIndex));
            updateSlider();
        }
        
        /**
         * Create pagination dots
         */
        function createPagination() {
            $pagination.empty();
            
            for (var i = 0; i <= maxIndex; i++) {
                var $dot = $('<span class="dot"></span>');
                if (i === 0) {
                    $dot.addClass('active');
                }
                
                (function(index) {
                    $dot.on('click', function() {
                        goToSlide(index);
                        resetAutoplay();
                    });
                })(i);
                
                $pagination.append($dot);
            }
        }
        
        /**
         * Start autoplay
         */
        function startAutoplay() {
            if (autoplayDelay > 0) {
                autoplayInterval = setInterval(function() {
                    if (currentIndex >= maxIndex) {
                        currentIndex = 0;
                    } else {
                        currentIndex++;
                    }
                    updateSlider();
                }, autoplayDelay);
            }
        }
        
        /**
         * Stop autoplay
         */
        function stopAutoplay() {
            if (autoplayInterval) {
                clearInterval(autoplayInterval);
                autoplayInterval = null;
            }
        }
        
        /**
         * Reset autoplay
         */
        function resetAutoplay() {
            stopAutoplay();
            startAutoplay();
        }
        
        /**
         * Initialize slider
         */
        function init() {
            if (totalSlides <= 0) {
                return;
            }
            
            calculateSlidesPerView();
            createPagination();
            updateSlider();
            
            // Event listeners
            $prevBtn.on('click', function() {
                prevSlide();
                resetAutoplay();
            });
            
            $nextBtn.on('click', function() {
                nextSlide();
                resetAutoplay();
            });
            
            // Touch/swipe support
            var touchStartX = 0;
            var touchEndX = 0;
            
            $slider.on('touchstart', function(e) {
                touchStartX = e.originalEvent.touches[0].clientX;
            });
            
            $slider.on('touchend', function(e) {
                touchEndX = e.originalEvent.changedTouches[0].clientX;
                var diff = touchStartX - touchEndX;
                
                if (Math.abs(diff) > 50) {
                    if (diff > 0) {
                        nextSlide();
                    } else {
                        prevSlide();
                    }
                    resetAutoplay();
                }
            });
            
            // Keyboard navigation
            $container.on('keydown', function(e) {
                if (e.key === 'ArrowLeft') {
                    prevSlide();
                    resetAutoplay();
                } else if (e.key === 'ArrowRight') {
                    nextSlide();
                    resetAutoplay();
                }
            });
            
            // Pause on hover
            $container.on('mouseenter', stopAutoplay);
            $container.on('mouseleave', startAutoplay);
            
            // Resize handler
            $(window).on('resize', _.debounce(function() {
                calculateSlidesPerView();
                createPagination();
                
                if (currentIndex > maxIndex) {
                    currentIndex = maxIndex;
                }
                updateSlider();
            }, 200));
            
            // Start autoplay
            startAutoplay();
        }
        
        // Initialize on DOM ready
        $(document).ready(init);
    };
});
