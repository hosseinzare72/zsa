<?php
/**
 * وابستگی‌های اسکریپت ویرایشگر بلوک (برای لود صحیح wp.* در ویرایشگر)
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
	'version'      => '1.1.0',
);
