/*
 * اسکریپت‌های صفحه مدیریت افزونه ZSA Portfolio Manager
 * Vanilla JS، وابستگی خارجی ندارد.
 */
( function () {
	'use strict';

	// آکاردئون
	document.querySelectorAll( '.zsa-pm-section-head' ).forEach( function ( head ) {
		head.addEventListener( 'click', function () {
			const section = head.parentElement;
			section.classList.toggle( 'open' );
			const toggle = head.querySelector( '.zsa-pm-section-toggle' );
			if ( toggle ) {
				toggle.textContent = section.classList.contains( 'open' ) ? '−' : '+';
			}
		} );
	} );

	// Color Picker
	if ( window.jQuery && jQuery.fn.wpColorPicker ) {
		jQuery( function ( $ ) {
			$( '.zsa-pm-color' ).wpColorPicker();
		} );
	}

	// شمارنده textarea
	const ta = document.getElementById( 'zsa_short_desc' );
	if ( ta ) {
		const counter = document.querySelector( '[data-counter]' );
		const max = parseInt( ta.getAttribute( 'maxlength' ), 10 ) || 200;
		const update = function () {
			const len = ta.value.length;
			if ( counter ) {
				counter.textContent = len;
				counter.parentElement.classList.toggle( 'warn', len > max * 0.85 && len <= max );
				counter.parentElement.classList.toggle( 'over', len > max );
			}
		};
		ta.addEventListener( 'input', update );
		update();
	}

	// پیش‌نمایش زنده
	const titleInput = document.getElementById( 'title' );
	const badgeInput = document.getElementById( 'zsa_badge' );
	const colorInput = document.getElementById( 'zsa_badge_color' );
	const descInput  = document.getElementById( 'zsa_short_desc' );
	const previewTitle = document.querySelector( '[data-preview-title]' );
	const previewBadge = document.querySelector( '[data-preview-badge]' );
	const previewDesc  = document.querySelector( '[data-preview-desc]' );

	if ( titleInput && previewTitle ) {
		titleInput.addEventListener( 'input', function () {
			previewTitle.textContent = titleInput.value || 'عنوان پروژه';
		} );
	}
	if ( badgeInput && previewBadge ) {
		badgeInput.addEventListener( 'input', function () {
			previewBadge.textContent = badgeInput.value || 'بج';
		} );
	}
	if ( colorInput && previewBadge ) {
		const syncColor = function () {
			previewBadge.style.background = colorInput.value;
		};
		colorInput.addEventListener( 'input', syncColor );
		colorInput.addEventListener( 'change', syncColor );
	}
	if ( descInput && previewDesc ) {
		descInput.addEventListener( 'input', function () {
			previewDesc.textContent = descInput.value || 'توضیح کوتاه پروژه اینجا نمایش داده می‌شود.';
		} );
	}

	// ===== ابزارها =====
	const migrateBtn = document.getElementById( 'zsa-pm-run-migration' );
	if ( migrateBtn ) {
		migrateBtn.addEventListener( 'click', function () {
			const result = migrateBtn.parentElement.querySelector( '[data-result]' );
			if ( ! window.confirm( 'آیا از اجرای مهاجرت اولیه مطمئن هستید؟' ) ) {
				return;
			}
			migrateBtn.disabled = true;
			migrateBtn.textContent = 'در حال ساخت پروژه‌ها...';
			const formData = new FormData();
			formData.append( 'action', 'zsa_pm_run_migration' );
			formData.append( 'nonce', migrateBtn.dataset.nonce );
			fetch( ajaxurl, { method: 'POST', body: formData } )
				.then( ( r ) => r.json() )
				.then( ( json ) => {
					migrateBtn.disabled = false;
					migrateBtn.textContent = 'اجرای مهاجرت اولیه';
					if ( json.success ) {
						result.textContent = '✅ ' + json.data.message;
						result.style.color = '#10b981';
						setTimeout( () => window.location.reload(), 1200 );
					} else {
						result.textContent = '❌ خطا';
						result.style.color = '#ef4444';
					}
				} )
				.catch( () => {
					migrateBtn.disabled = false;
					migrateBtn.textContent = 'اجرای مهاجرت اولیه';
					result.textContent = '❌ خطای شبکه';
					result.style.color = '#ef4444';
				} );
		} );
	}

	const clearBtn = document.getElementById( 'zsa-pm-clear-cache' );
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', function () {
			const result = clearBtn.parentElement.querySelector( '[data-result]' );
			clearBtn.disabled = true;
			const formData = new FormData();
			formData.append( 'action', 'zsa_pm_clear_cache' );
			formData.append( 'nonce', clearBtn.dataset.nonce );
			fetch( ajaxurl, { method: 'POST', body: formData } )
				.then( ( r ) => r.json() )
				.then( ( json ) => {
					clearBtn.disabled = false;
					if ( json.success ) {
						result.textContent = '✅ ' + json.data;
						result.style.color = '#10b981';
					} else {
						result.textContent = '❌ خطا';
						result.style.color = '#ef4444';
					}
				} )
				.catch( () => {
					clearBtn.disabled = false;
					result.textContent = '❌ خطای شبکه';
					result.style.color = '#ef4444';
				} );
		} );
	}

	// ===== ویرایش سریع از لیست =====
	const quickButtons = document.querySelectorAll( '.zsa-pm-quick-edit' );
	quickButtons.forEach( function ( btn ) {
		btn.addEventListener( 'change', function () {
			const fd = new FormData();
			fd.append( 'action', 'zsa_pm_quick_edit' );
			fd.append( 'nonce', btn.dataset.nonce );
			fd.append( 'post_id', btn.dataset.postId );
			fd.append( 'field', btn.dataset.field );
			fd.append( 'value', btn.value );

			const old = btn.style.borderColor;
			btn.disabled = true;
			btn.style.borderColor = '#f59e0b';

			fetch( ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( ( r ) => r.json() )
				.then( ( json ) => {
					btn.disabled = false;
					btn.style.borderColor = json && json.success ? '#10b981' : '#ef4444';
				} )
				.catch( function () {
					btn.disabled = false;
					btn.style.borderColor = '#ef4444';
					window.setTimeout( function () {
						btn.style.borderColor = old;
					}, 2500 );
				} );
		} );
	} );
} )();