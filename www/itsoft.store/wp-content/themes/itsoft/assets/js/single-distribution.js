/* Single Distribution Scripts */

document.addEventListener('DOMContentLoaded', () => {
    const initGallery = () => {
        const thumbsElement = document.querySelector('.distrib-card__gallery .product-gallery__thumbs');
        const mainElement = document.querySelector('.distrib-card__gallery .product-gallery__main');

        if (!thumbsElement || !mainElement) return;

        const thumbsSwiper = new Swiper(thumbsElement, {
            direction: 'vertical',
            slidesPerView: 4,
            spaceBetween: 14,
            watchOverflow: true,
            watchSlidesProgress: true,
            freeMode: true,
            navigation: {
                nextEl: '.product-gallery__thumbs .swiper-button-next',
                prevEl: '.product-gallery__thumbs .swiper-button-prev',
            },
            breakpoints: {
                0: { slidesPerView: 3, direction: 'horizontal' },
                480: { slidesPerView: 4, direction: 'horizontal' },
                768: { slidesPerView: 5, direction: 'vertical' }
            }
        });

        const mainSwiper = new Swiper(mainElement, {
            spaceBetween: 10,
            thumbs: {
                swiper: thumbsSwiper
            }
        });

        if (typeof Fancybox !== 'undefined') {
            Fancybox.bind('[data-fancybox="product"]', {
                // Fancybox options
            });
        }
    };

    initGallery();
});
