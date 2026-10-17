<?php
/**
 * ثبت بلوک گوتنبرگ «پورتفولیو ZSA»
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت بلوک از روی block.json (رندر سمت سرور)
 */
final class ZSA_PM_Block {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Block|null
	 */
	private static ?ZSA_PM_Block $instance = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Block {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * ثبت بلوک
	 */
	public function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type( ZSA_PM_PATH . 'blocks/zsa-portfolio' );
	}
}