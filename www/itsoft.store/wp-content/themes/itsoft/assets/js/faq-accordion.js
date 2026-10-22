( () => {
	'use strict';

	class ProductFaqAccordion {
		constructor( root ) {
			this.root = root;
			this.items = Array.from( root.querySelectorAll( '[data-faq-item]' ) );
			this.activeClassName = 'active';
			this.activeItem = null;
			this.handleClick = this.handleClick.bind( this );
		}

		init() {
			if ( ! this.root || ! this.items.length ) {
				return;
			}

			const presetActive = this.items.find( ( item ) => item.classList.contains( this.activeClassName ) );
			this.activeItem = presetActive || this.items[0];

			if ( ! presetActive ) {
				this.activeItem.classList.add( this.activeClassName );
			}

			this.enforceActive( this.activeItem );
			this.root.addEventListener( 'click', this.handleClick );
		}

		handleClick( event ) {
			const trigger = event.target.closest( '[data-faq-trigger]' );

			if ( ! trigger || ! this.root.contains( trigger ) ) {
				return;
			}

			const nextItem = trigger.closest( '[data-faq-item]' );

			if ( ! nextItem ) {
				return;
			}

			if ( nextItem === this.activeItem ) {
				this.enforceActive( null );
				return;
			}

			this.enforceActive( nextItem );
		}

		enforceActive( targetItem ) {
			this.items.forEach( ( item ) => {
				const isActive = item === targetItem;
				item.classList.toggle( this.activeClassName, isActive );
				this.updateAria( item, isActive );
			} );

			this.activeItem = targetItem || null;
		}

		updateAria( item, isActive ) {
			const trigger = item.querySelector( '[data-faq-trigger]' );
			const content = item.querySelector( '[data-faq-content]' );

			if ( trigger ) {
				trigger.setAttribute( 'aria-expanded', isActive ? 'true' : 'false' );
			}

			if ( content ) {
				content.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
			}
		}
	}

	const initProductFaq = () => {
		const faqRoots = document.querySelectorAll( '[data-product-faq]' );

		faqRoots.forEach( ( root ) => {
			const accordion = new ProductFaqAccordion( root );
			accordion.init();
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initProductFaq );
	} else {
		initProductFaq();
	}

} )();
