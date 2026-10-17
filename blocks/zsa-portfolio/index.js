/*
 * اسکریپت ویرایشگر بلاک گوتنبرگ ZSA Portfolio
 * سازگار با WP 6.5+
 */
( function ( wp ) {
	'use strict';
	const el = wp.element.createElement;
	const __ = ( text ) => text; // ترجمه در زمان build انجام می‌شود

	const registerBlockType = wp.blocks.registerBlockType;
	const InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
	const PanelBody = wp.components.PanelBody;
	const SelectControl = wp.components.SelectControl;
	const TextControl = wp.components.TextControl;
	const ToggleControl = wp.components.ToggleControl;
	const RangeControl = wp.components.RangeControl;

	registerBlockType( 'zsa-pm/portfolio', {
		title: 'پورتفولیو ZSA',
		icon: 'portfolio',
		category: 'widgets',
		attributes: {
			mode: { type: 'string', default: 'projects' },
			type: { type: 'string', default: '' },
			columns: { type: 'number', default: 0 },
			limit: { type: 'number', default: 0 },
			fullpage: { type: 'boolean', default: false },
		},
		edit: function ( props ) {
			const a = props.attributes;
			return el(
				'div',
				{ className: 'zsa-pm-block-edit', dir: 'rtl' },
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'تنظیمات پورتفولیو', initialOpen: true },
						el( SelectControl, {
							label: 'حالت نمایش',
							value: a.mode,
							options: [
								{ label: 'پروژه‌های منتشرشده', value: 'projects' },
								{ label: 'وب‌سایت‌ها (موکاپ)', value: 'web' },
								{ label: 'به‌زودی', value: 'soon' },
								{ label: 'شمارنده‌ها', value: 'stats' },
								{ label: 'صفحهٔ کامل (هیرو + همهٔ بخش‌ها)', value: 'page' },
							],
							onChange: function ( v ) {
								props.setAttributes( { mode: v } );
							},
						} ),
						'page' === a.mode && el( ToggleControl, {
							label: 'تمام‌صفحه (حذف هدر/فوتر قالب با CSS)',
							checked: !! a.fullpage,
							onChange: function ( v ) { props.setAttributes( { fullpage: v } ); },
						} ),
						( a.mode === 'projects' || a.mode === 'page' ) && el( TextControl, {
							label: 'نوع (app, game, wordpress — با کاما جدا کنید)',
							value: a.type,
							onChange: function ( v ) { props.setAttributes( { type: v } ); },
						} ),
						( a.mode === 'projects' || a.mode === 'web' || a.mode === 'page' ) && el( RangeControl, {
							label: 'تعداد ستون‌ها (۰ = طبق تنظیمات)',
							value: a.columns,
							min: 0,
							max: 4,
							onChange: function ( v ) { props.setAttributes( { columns: v } ); },
						} ),
						'stats' !== a.mode && el( RangeControl, {
							label: 'تعداد (۰ = نامحدود)',
							value: a.limit,
							min: 0,
							max: 50,
							onChange: function ( v ) { props.setAttributes( { limit: v } ); },
						} )
					)
				),
				el(
					'div',
					{ className: 'zsa-pm-block-preview' },
					el( 'strong', null, '📦 پورتفولیو ZSA — ' + a.mode ),
					a.type ? el( 'p', null, 'فیلتر نوع: ' + a.type ) : null,
					'page' === a.mode && el( 'p', null, 'تمام‌صفحه: ' + ( a.fullpage ? 'بله' : 'خیر' ) ),
					'stats' !== a.mode && el( 'p', null, 'ستون‌ها: ' + ( a.columns || 'خودکار' ) + ' | تعداد: ' + ( a.limit || 'نامحدود' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
