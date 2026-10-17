<?php
/**
 * رندر سمت سرور بلاک گوتنبرگ پورتفولیو ZSA
 *
 * @package ZSA_Portfolio_Manager
 *
 * @var array $attributes ویژگی‌های بلاک.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mode     = isset( $attributes['mode'] ) ? sanitize_key( $attributes['mode'] ) : 'projects';
$type     = isset( $attributes['type'] ) ? sanitize_text_field( $attributes['type'] ) : '';
$columns  = isset( $attributes['columns'] ) ? max( 0, min( 4, (int) $attributes['columns'] ) ) : 0;
$limit    = isset( $attributes['limit'] ) ? max( 0, min( 200, (int) $attributes['limit'] ) ) : 0;
$fullpage = ! empty( $attributes['fullpage'] );

$type = preg_replace( '/[^a-z0-9,\-]/', '', strtolower( $type ) );

switch ( $mode ) {
	case 'page':
		$shortcode = sprintf(
			'[zsa_portfolio type="%1$s" limit="%2$d" columns="%3$d" fullpage="%4$d"]',
			esc_attr( (string) $type ),
			$limit,
			$columns,
			$fullpage ? 1 : 0
		);
		break;

	case 'web':
		$shortcode = sprintf(
			'[zsa_web_portfolio limit="%1$d" columns="%2$d"]',
			$limit,
			$columns
		);
		break;

	case 'soon':
		$shortcode = sprintf(
			'[zsa_coming_soon limit="%1$d"]',
			$limit
		);
		break;

	case 'stats':
		$shortcode = '[zsa_stats]';
		break;

	case 'projects':
	default:
		$shortcode = sprintf(
			'[zsa_projects type="%1$s" limit="%2$d" columns="%3$d"]',
			esc_attr( (string) $type ),
			$limit,
			$columns
		);
		break;
}

echo do_shortcode( $shortcode );
