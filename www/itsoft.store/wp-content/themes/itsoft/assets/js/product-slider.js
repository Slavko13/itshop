document.addEventListener('DOMContentLoaded', function(){
    // Check if we're on a product page and our gallery exists
    const galleryThumbsElement = document.querySelector('.product-gallery__thumbs');
    const galleryMainElement = document.querySelector('.product-gallery__main');
    
    if (galleryMainElement) {
        const slideCount = Number(document.querySelector('.product-gallery')?.dataset.galleryCount || 1);
        let thumbs = null;

		if (galleryThumbsElement && slideCount > 1 && typeof window.Swiper === 'function') {
			thumbs = new window.Swiper('.product-gallery__thumbs', {
				direction           : 'vertical',
				slidesPerView       : 4,
				spaceBetween        : 14,
				watchOverflow       : true,
				loop                : false,
				watchSlidesProgress : true,
				freeMode            : true,
				navigation          : {
					nextEl: '.product-gallery__thumbs .swiper-button-next',
					prevEl: '.product-gallery__thumbs .swiper-button-prev',
				},
				breakpoints: {
					0:{slidesPerView:3},
					480:{slidesPerView:4},
					768:{slidesPerView:5}
				}
			});
		}

		if (slideCount > 1 && typeof window.Swiper === 'function') {
			new window.Swiper('.product-gallery__main', {
				spaceBetween: 10,
				loop: true,
				keyboard: { enabled: true },
				...(thumbs ? { thumbs: { swiper: thumbs } } : {})
			});
		}
        
		if (window.Fancybox && typeof window.Fancybox.bind === 'function') {
			window.Fancybox.bind("[data-fancybox]", {
				// Custom options can be added here
			});
		}
    }
});
