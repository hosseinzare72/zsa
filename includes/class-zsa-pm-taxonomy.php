<?php
/**
 * کلاس ثبت طبقه‌بندی نوع پروژه
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت و مدیریت تاکسونومی zsa_project_type
 */
final class ZSA_PM_Taxonomy {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Taxonomy|null
	 */
	private static ?ZSA_PM_Taxonomy $instance = null;

	/**
	 * کلید تاکسونومی
	 */
	public const TAXONOMY = 'zsa_project_type';

	/**
	 * ترم‌های پیش‌فرض
	 *
	 * @return array<string, array{emoji:string, label:string}>
	 */
	public static function default_terms(): array {
		return array(
			'app'             => array(
				'emoji' => '📱',
				'label' => 'اپلیکیشن',
			),
			'game'            => array(
				'emoji' => '🎮',
				'label' => 'بازی',
			),
			'website'         => array(
				'emoji' => '🌐',
				'label' => 'وب‌سایت',
			),
			'wordpress'       => array(
				'emoji' => '🧩',
				'label' => 'وردپرس',
			),
			'digital-product' => array(
				'emoji' => '💡',
				'label' => 'محصول دیجیتال',
			),
			'ecosystem'       => array(
				'emoji' => '⭐',
				'label' => 'اکوسیستم',
			),
		);
	}

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Taxonomy {
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
	 * ثبت تاکسونومی
	 */
	public function register(): void {
		$labels = array(
			'name'              => _x( 'نوع پروژه', 'نام تاکسونومی', 'zsa-pm' ),
			'singular_name'     => _x( 'نوع پروژه', 'نام مفرد', 'zsa-pm' ),
			'search_items'      => __( 'جستجوی نوع پروژه', 'zsa-pm' ),
			'all_items'         => __( 'همه انواع', 'zsa-pm' ),
			'edit_item'         => __( 'ویرایش نوع', 'zsa-pm' ),
			'update_item'       => __( 'به‌روزرسانی نوع', 'zsa-pm' ),
			'add_new_item'      => __( 'افزودن نوع جدید', 'zsa-pm' ),
			'new_item_name'     => __( 'نام نوع جدید', 'zsa-pm' ),
			'menu_name'         => __( 'نوع پروژه', 'zsa-pm' ),
			'parent_item'       => null,
			'parent_item_colon' => null,
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rest_base'         => 'zsa-project-types',
			'query_var'         => true,
			'rewrite'           => array(
				'slug'         => 'project-type',
				'with_front'   => false,
				'hierarchical' => false,
			),
			'public'            => true,
		);

		register_taxonomy( self::TAXONOMY, array( ZSA_PM_CPT::POST_TYPE ), $args );
	}

	/**
	 * ساخت ترم‌های پیش‌فرض هنگام فعال‌سازی
	 */
	public static function seed_default_terms(): void {
		$terms = self::default_terms();

		foreach ( $terms as $slug => $info ) {
			if ( ! term_exists( $slug, self::TAXONOMY ) ) {
				wp_insert_term(
					$info['label'],
					self::TAXONOMY,
					array(
						'slug'        => $slug,
						'description' => $info['label'],
					)
				);
			}
		}
	}
}