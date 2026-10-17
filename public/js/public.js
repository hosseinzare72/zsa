/*
 * اسکریپت فرانت‌اند ZSA Portfolio Manager (Vanilla JS)
 * - شمارنده‌ها با requestAnimationFrame و اعداد فارسی
 * - انیمیشن ورود با IntersectionObserver
 * - احترام به prefers-reduced-motion
 * عدد نهایی شمارنده‌ها از سمت سرور در HTML نوشته شده است؛
 * پس اگر انیمیشن خاموش یا JS ناموفق باشد، محتوا درست و کامل دیده می‌شود.
 */
( function () {
	'use strict';

	var cfg = window.zsaPM || {};
	var reduced = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( cfg.animations === 0 || reduced || ! ( 'IntersectionObserver' in window ) ) {
		return;
	}

	function toFa( n ) {
		return String( n ).replace( /\d/g, function ( d ) {
			return '۰۱۲۳۴۵۶۷۸۹'[ d ];
		} );
	}

	function show( el, val ) {
		el.textContent = toFa( val ) + ( el.getAttribute( 'data-suffix' ) || '' );
	}

	function runCounter( el ) {
		var target = parseInt( el.getAttribute( 'data-count' ), 10 ) || 0;
		var t0 = performance.now();
		var dur = 1500;

		function step( t ) {
			var k = Math.min( ( t - t0 ) / dur, 1 );
			show( el, Math.round( target * ( 1 - Math.pow( 1 - k, 3 ) ) ) );
			if ( k < 1 ) {
				requestAnimationFrame( step );
			}
		}
		requestAnimationFrame( step );
	}

	document.documentElement.classList.add( 'zsa-pm-js' );

	var counters = document.querySelectorAll( '[data-count]' );
	counters.forEach( function ( el ) {
		show( el, 0 );
	} );

	var obs = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}
				var el = entry.target;
				el.classList.add( 'is-in' );
				var c = el.querySelector( '[data-count]' );
				if ( c ) {
					runCounter( c );
				}
				obs.unobserve( el );
				// پس از پایان انیمیشن، کلاس‌های انیمیشن حذف می‌شوند تا افکت hover عادی کار کند
				setTimeout( function () {
					el.classList.remove( 'zsa-pm-anim', 'is-in' );
				}, 900 );
			} );
		},
		{ threshold: 0.15 }
	);

	document.querySelectorAll( '.zsa-pm-anim' ).forEach( function ( el ) {
		obs.observe( el );
	} );
} )();
