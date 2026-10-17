<?php
/**
 * کلاس ثبت نوع نوشته سفارشی پروژه‌های ZSA
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت و مدیریت نوع نوشته سفارشی zsa_project
 */
final class ZSA_PM_CPT {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_CPT|null
	 */
	private static ?ZSA_PM_CPT $instance = null;

	/**
	 * کلید نوع نوشته
	 */
	public const POST_TYPE = 'zsa_project';

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_CPT {
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
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_order' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filter_dropdowns' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_filters' ) );
		add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_list_assets' ) );
	}

	/**
	 * ثبت نوع نوشته
	 */
	public function register(): void {
		$labels = array(
			'name'                  => _x( 'پروژه‌های ZSA', 'نام جمع نوع نوشته', 'zsa-pm' ),
			'singular_name'         => _x( 'پروژه', 'نام مفرد نوع نوشته', 'zsa-pm' ),
			'menu_name'             => _x( 'پورتفولیو ZSA', 'منوی مدیریت', 'zsa-pm' ),
			'name_admin_bar'        => _x( 'پروژه', 'نوار مدیر', 'zsa-pm' ),
			'add_new'               => __( 'افزودن پروژه', 'zsa-pm' ),
			'add_new_item'          => __( 'افزودن پروژه جدید', 'zsa-pm' ),
			'edit_item'             => __( 'ویرایش پروژه', 'zsa-pm' ),
			'new_item'              => __( 'پروژه جدید', 'zsa-pm' ),
			'view_item'             => __( 'مشاهده پروژه', 'zsa-pm' ),
			'search_items'          => __( 'جستجوی پروژه‌ها', 'zsa-pm' ),
			'not_found'             => __( 'پروژه‌ای یافت نشد', 'zsa-pm' ),
			'not_found_in_trash'    => __( 'پروژه‌ای در زباله‌دان نیست', 'zsa-pm' ),
			'all_items'             => __( 'همه پروژه‌ها', 'zsa-pm' ),
			'featured_image'        => __( 'تصویر پروژه', 'zsa-pm' ),
			'set_featured_image'    => __( 'تنظیم تصویر پروژه', 'zsa-pm' ),
			'remove_featured_image' => __( 'حذف تصویر پروژه', 'zsa-pm' ),
			'archives'              => __( 'بایگانی پروژه‌ها', 'zsa-pm' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true, // سازگاری با گوتنبرگ
			'rest_base'           => 'zsa-projects',
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_in_menu'        => true,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-portfolio',
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'rewrite'             => array(
				'slug'       => 'project',
				'with_front' => false,
			),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * تعریف ستون‌های لیست
	 *
	 * @param array $columns ستون‌های موجود.
	 * @return array
	 */
	public function columns( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'title' === $key ) {
				$new['zsa_thumb']  = __( 'تصویر', 'zsa-pm' );
				$new['zsa_type']   = __( 'نوع', 'zsa-pm' );
				$new['zsa_status'] = __( 'وضعیت', 'zsa-pm' );
				$new['zsa_order']  = __( 'ترتیب', 'zsa-pm' );
				$new['zsa_links']  = __( 'لینک‌ها', 'zsa-pm' );
			}
		}

		return $new;
	}

	/**
	 * محتوای ستون‌های سفارشی
	 *
	 * @param string $column  نام ستون.
	 * @param int    $post_id شناسه پست.
	 */
	public function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'zsa_thumb':
				if ( has_post_thumbnail( $post_id ) ) {
					echo get_the_post_thumbnail( $post_id, array( 60, 60 ) );
				} else {
					echo '<span class="zsa-pm-no-thumb">—</span>';
				}
				break;

			case 'zsa_type':
				$terms = get_the_term_list( $post_id, ZSA_PM_Taxonomy::TAXONOMY, '', ', ' );
				echo $terms ? wp_kses_post( $terms ) : '<span class="zsa-pm-muted">—</span>';
				break;

			case 'zsa_status':
				$status = (string) get_post_meta( $post_id, 'zsa_status', true );
				$status = 'coming-soon' === $status ? 'coming-soon' : 'published';
				printf(
					'<select class="zsa-pm-quick-edit" data-post-id="%1$d" data-field="zsa_status" data-nonce="%2$s" aria-label="%3$s">',
					(int) $post_id,
					esc_attr( wp_create_nonce( 'zsa_pm_quick_edit' ) ),
					esc_attr__( 'وضعیت نمایش', 'zsa-pm' )
				);
				printf(
					'<option value="published" %1$s>%2$s</option><option value="coming-soon" %3$s>%4$s</option>',
					selected( $status, 'published', false ),
					esc_html__( 'منتشرشده', 'zsa-pm' ),
					selected( $status, 'coming-soon', false ),
					esc_html__( 'به‌زودی', 'zsa-pm' )
				);
				echo '</select>';
				break;

			case 'zsa_order':
				$order = (int) get_post_meta( $post_id, 'zsa_order', true );
				// ویرایش سریعِ ترتیب بدون بازکردن صفحهٔ ویرایش
				printf(
					'<input type="number" class="zsa-pm-quick-edit small-text" min="0" max="9999" value="%1$d" data-post-id="%2$d" data-field="zsa_order" data-nonce="%3$s" aria-label="%4$s">',
					(int) $order,
					(int) $post_id,
					esc_attr( wp_create_nonce( 'zsa_pm_quick_edit' ) ),
					esc_attr__( 'ترتیب نمایش', 'zsa-pm' )
				);
				break;

			case 'zsa_links':
				$links = get_post_meta( $post_id, 'zsa_links', true );
				echo esc_html( ZSA_PM_Data::fa( is_array( $links ) ? count( $links ) : 0 ) );
				break;
		}
	}

	/**
	 * ستون‌های قابل مرتب‌سازی
	 *
	 * @param array $columns ستون‌ها.
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['zsa_order'] = 'zsa_order';
		return $columns;
	}

	/**
	 * اعمال مرتب‌سازی بر اساس ترتیب
	 *
	 * @param WP_Query $query کوئری جاری.
	 */
	public function apply_order( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = (string) $query->get( 'post_type' );
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// ترتیب‌بندی لیست مدیریت مطابق نمایش فرانت‌اند
		// («ترتیب» صعودی؛ پروژه‌های بدون مقدار آخر می‌آیند و حذف نمی‌شوند)
		$orderby = (string) $query->get( 'orderby' );
		if ( '' === $orderby || 'zsa_order' === $orderby ) {
			$query->set( 'meta_key', 'zsa_order' );
			$query->set( 'orderby', 'meta_value_num' );
			if ( '' === $orderby ) {
				$query->set( 'order', 'ASC' );
			}
		}

		// پروژه‌هایی که ترتیب ندارند نباید از لیست حذف شوند
		if ( 'meta_value_num' === (string) $query->get( 'orderby' ) ) {
			$query->set( 'meta_query', self::merge_meta_query( $query, self::order_exists_clause() ) );
		}
	}

	/**
	 * شرط «ترتیب وجود دارد یا ندارد» (برای اینکه متا JOIN پروژه‌ها را حذف نکند)
	 *
	 * @return array
	 */
	private static function order_exists_clause(): array {
		return array(
			'relation' => 'OR',
			array(
				'key'     => 'zsa_order',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'zsa_order',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * ادغام شرط متا با شرط‌های قبلی کوئری (بدون بازنویسی)
	 *
	 * @param WP_Query $query  کوئری.
	 * @param array    $clause شرط جدید.
	 * @return array
	 */
	private static function merge_meta_query( WP_Query $query, array $clause ): array {
		$existing = $query->get( 'meta_query' );

		if ( empty( $existing ) || ! is_array( $existing ) ) {
			return $clause;
		}

		return array(
			'relation' => 'AND',
			$existing,
			$clause,
		);
	}

	/**
	 * فیلترهای نوع و وضعیت بالای لیست پروژه‌ها
	 *
	 * @param string $post_type نوع نوشته.
	 */
	public function filter_dropdowns( $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		wp_dropdown_categories(
			array(
				'show_option_all' => __( 'همهٔ انواع', 'zsa-pm' ),
				'taxonomy'        => ZSA_PM_Taxonomy::TAXONOMY,
				'name'            => ZSA_PM_Taxonomy::TAXONOMY,
				'value_field'     => 'slug',
				'selected'        => isset( $_GET[ ZSA_PM_Taxonomy::TAXONOMY ] ) ? sanitize_title( wp_unslash( $_GET[ ZSA_PM_Taxonomy::TAXONOMY ] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'hide_empty'      => false,
				'hierarchical'    => false,
			)
		);

		$current = isset( $_GET['zsa_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['zsa_status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<select name="zsa_status_filter">
			<option value=""><?php esc_html_e( 'همهٔ وضعیت‌ها', 'zsa-pm' ); ?></option>
			<option value="published" <?php selected( $current, 'published' ); ?>><?php esc_html_e( 'منتشرشده', 'zsa-pm' ); ?></option>
			<option value="coming-soon" <?php selected( $current, 'coming-soon' ); ?>><?php esc_html_e( 'در دست توسعه', 'zsa-pm' ); ?></option>
		</select>
		<?php
	}

	/**
	 * اعمال فیلتر وضعیت روی کوئری لیست مدیریت
	 *
	 * @param WP_Query $query کوئری جاری.
	 */
	public function apply_filters( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== (string) $query->get( 'post_type' ) ) {
			return;
		}

		$status = isset( $_GET['zsa_status_filter'] ) ? sanitize_key( wp_unslash( $_GET['zsa_status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status, array( 'published', 'coming-soon' ), true ) ) {
			return;
		}

		$meta = array(
			array(
				'key'   => 'zsa_status',
				'value' => $status,
			),
		);
		// «منتشرشده» شامل پروژه‌هایی که وضعیت ذخیره‌نشده دارند هم می‌شود
		if ( 'published' === $status ) {
			$meta = array(
				'relation' => 'OR',
				array(
					'key'   => 'zsa_status',
					'value' => 'published',
				),
				array(
					'key'     => 'zsa_status',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		// ادغام با شرط ترتیب (نسخهٔ قبلی آن را بازنویسی می‌کرد و مرتب‌سازی از کار می‌افتاد)
		$query->set( 'meta_query', self::merge_meta_query( $query, $meta ) );
	}

	/**
	 * بارگذاری CSS/JS مخصوص لیست پروژه‌ها (ویرایش سریع ترتیب)
	 *
	 * @param string $hook هوک صفحه.
	 */
	public function enqueue_list_assets( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'zsa-pm-admin', ZSA_PM_URL . 'admin/css/admin.css', array(), ZSA_PM_VERSION );
		wp_enqueue_script( 'zsa-pm-admin', ZSA_PM_URL . 'admin/js/admin.js', array( 'jquery' ), ZSA_PM_VERSION, true );
		wp_localize_script(
			'zsa-pm-admin',
			'zsaPmAdmin',
			array(
				'quickEditNonce' => wp_create_nonce( 'zsa_pm_quick_edit' ),
				'saved'          => __( 'ذخیره شد', 'zsa-pm' ),
			)
		);
	}

	/**
	 * پیام‌های سفارشی هنگام به‌روزرسانی
	 *
	 * @param array $messages پیام‌ها.
	 * @return array
	 */
	public function updated_messages( array $messages ): array {
		$post = get_post();
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return $messages;
		}

		$messages[ self::POST_TYPE ] = array(
			1 => __( 'پروژه به‌روزرسانی شد.', 'zsa-pm' ),
			6 => __( 'پروژه منتشر شد.', 'zsa-pm' ),
			7 => __( 'پروژه ذخیره شد.', 'zsa-pm' ),
			8 => __( 'پروژه ارسال شد.', 'zsa-pm' ),
		);

		return $messages;
	}
}