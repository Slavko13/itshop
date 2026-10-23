( function () {
	'use strict';

	const copy = new Map( [
		[ 'Basket', 'Cart' ],
		[ 'View basket', 'View cart' ],
		[ 'Go to the basket', 'Go to cart' ],
		[ 'Basket totals', 'Cart totals' ],
		[ 'Your basket is currently empty.', 'Your cart is currently empty.' ],
		[ 'Goods', 'Products' ],
		[ 'Search the site', 'Search products' ],
		[ 'Initial sorting', 'Default sorting' ],
		[ 'By popularity', 'Sort by popularity' ],
		[ 'By rating', 'Sort by rating' ],
		[ 'By novelty', 'Sort by latest' ],
		[ 'By ascending price', 'Sort by price: low to high' ],
		[ 'In descending order of price', 'Sort by price: high to low' ],
		[ 'The entire catalog', 'View full catalog' ],
		[ 'We recommend purchasing', 'Recommended for you' ],
		[ 'Exchange and return', 'Returns and exchanges' ],
		[ 'Articles and instructions', 'Guides and articles' ],
		[ 'About the store', 'About us' ],
		[ 'Download programs', 'Downloads' ],
		[ 'Answers to questions', 'FAQ' ],
		[ 'Offer', 'Public offer' ],
		[ 'Should we add something useful?', 'Ready to find the right software?' ],
		[ 'The catalog contains licenses, programs, and other products. Choose the one you need—checkout will take just a few minutes.', 'Browse our licenses and software, then check out in just a few minutes.' ],
		[ 'Order and keys by e-mail', 'Order details and license keys by email' ],
		[ 'Login and registration', 'Sign in or create an account' ],
		[ 'Manage your purchases without unnecessary emails', 'Manage your purchases in one place' ],
		[ 'All orders and received licenses will be available after logging in.', 'Your orders and license keys will be available after you sign in.' ],
		[ 'Entrance', 'Sign in' ],
		[ 'Registration', 'Create account' ],
		[ 'Login to your account', 'Sign in to your account' ],
		[ 'Everything important after purchase is at hand', 'Everything you need after purchase, all in one place' ],
		[ 'Need help? Answers to questions', 'Need help? Visit our FAQ' ],
		[ 'Recovery of access', 'Account recovery' ],
		[ 'Restoring access', 'Account recovery' ],
		[ 'Get login link', 'Get a password reset link' ],
		[ 'Get a login link', 'Get a password reset link' ],
		[ 'Send link', 'Send reset link' ],
		[ 'Return to login', 'Back to sign in' ],
		[ 'Return to the entrance', 'Back to sign in' ],
		[ '← Return to the entrance', '← Back to sign in' ],
		[ 'The letter did not arrive?', 'Didn\'t receive the email?' ],
		[ 'Didn\'t receive the letter?', 'Didn\'t receive the email?' ],
		[ 'Enter your account email address and we will send you a secure link to set a new password.', 'Enter the email address for your account, and we\'ll send you a secure password reset link.' ],
		[ 'Receiving by e-mail', 'Delivered by email' ],
		[ 'The key will be sent to your e-mail after payment confirmation.', 'Your license key will be emailed after payment is confirmed.' ],
		[ 'Clear status', 'Track your order' ],
		[ 'The order is visible in your personal account', 'Track your order in your account' ],
		[ 'Help from a specialist', 'Expert support' ],
		[ 'We will advise you on purchasing and activation.', 'Get help with purchase and activation.' ],
		[ 'Regional binding:', 'Region:' ],
		[ 'The whole world', 'Worldwide' ],
		[ 'Validity period:', 'License term:' ],
		[ 'Indefinitely', 'Lifetime' ],
		[ 'How many devices can I install on?', 'How many devices can I install it on?' ],
		[ 'My question is not there', 'I have another question' ],
		[ 'This package includes:', 'Included apps:' ],
		[ 'We launch the installation using "Setup".', 'Start the installation by opening “Setup”.' ],
		[ 'Click close.', 'Click “Close”.' ],
		[ 'Check the language and bitness of your system.', 'Check your system language and architecture.' ],
		[ 'Video adapter', 'Display' ],
		[ '2Gb', '2 GB' ],
		[ '4Gb', '4 GB' ],
		[ 'Search results for the query -', 'Search results for:' ],
		[ 'Add to Basket', 'Add to cart' ],
		[ 'In the basket', 'In cart' ],
		[ 'Go to Basket', 'Go to cart' ],
		[ 'Go to basket', 'Go to cart' ],
		[ 'Proceed to ordering', 'Proceed to checkout' ],
		[ 'Place an order', 'Place order' ],
	] );

	const attributeNames = [ 'aria-label', 'placeholder', 'title', 'value' ];
	let scheduled = false;

	function isEnglish() {
		return document.cookie.split( ';' ).some( ( item ) => item.trim().startsWith( 'googtrans=/ru/en' ) )
			|| document.documentElement.classList.contains( 'translated-ltr' )
			|| document.querySelector( '[data-gt-lang="en"].gt-current-lang' );
	}

	function replaceText( root ) {
		const walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT );
		const nodes = [];

		while ( walker.nextNode() ) nodes.push( walker.currentNode );

		nodes.forEach( ( node ) => {
			const value = node.nodeValue;
			const trimmed = value.trim();
			const replacement = copy.get( trimmed );

			if ( ! replacement ) return;

			const leading = value.match( /^\s*/ )?.[ 0 ] || '';
			const trailing = value.match( /\s*$/ )?.[ 0 ] || '';
			node.nodeValue = leading + replacement + trailing;
		} );

		root.querySelectorAll( '*' ).forEach( ( element ) => {
			attributeNames.forEach( ( name ) => {
				if ( ! element.hasAttribute( name ) ) return;
				const value = element.getAttribute( name );
				if ( copy.has( value ) ) element.setAttribute( name, copy.get( value ) );
			} );
		} );
	}

	function applyCopy() {
		scheduled = false;
		if ( isEnglish() && document.body ) replaceText( document.body );
	}

	function schedule() {
		if ( scheduled ) return;
		scheduled = true;
		window.requestAnimationFrame( applyCopy );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', schedule );
	} else {
		schedule();
	}

	new MutationObserver( schedule ).observe( document.documentElement, {
		childList: true,
		subtree: true,
		characterData: true,
		attributes: true,
		attributeFilter: attributeNames,
	} );
}() );
