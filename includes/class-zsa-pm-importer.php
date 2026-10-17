<?php
/**
 * کلاس مهاجرت اولیه (یک‌کلیکی)
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ساخت تمام پروژه‌های اولیه استودیوی ZSA با یک کلیک
 */
final class ZSA_PM_Importer {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Importer|null
	 */
	private static ?ZSA_PM_Importer $instance = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Importer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'wp_ajax_zsa_pm_run_migration', array( $this, 'ajax_run' ) );
	}

	/**
	 * اجرای مهاجرت از AJAX
	 */
	public function ajax_run(): void {
		check_ajax_referer( 'zsa_pm_run_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'دسترسی غیرمجاز', 'zsa-pm' ), 403 );
		}
		$result = self::run();
		wp_send_json_success( $result );
	}

	/**
	 * اجرای مهاجرت و بازگشت گزارش
	 *
	 * @return array
	 */
	public static function run(): array {
		$created = 0;
		$skipped = 0;
		$errors  = array();

		// دانلود تصویرها ممکن است زمان ببرد
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// ابتدا مطمئن شو ترم‌ها ساخته شده‌اند
		ZSA_PM_Taxonomy::seed_default_terms();

		foreach ( self::all_projects() as $row ) {
			$existing = self::find_by_title( $row['title'] );
			if ( $existing ) {
				self::backfill( (int) $existing, $row );
				++$skipped;
				continue;
			}

			$result = self::create_project( $row );
			if ( is_wp_error( $result ) ) {
				$errors[] = $row['title'] . ' → ' . $result->get_error_message();
			} else {
				++$created;
			}
		}

		// پاکسازی کش
		if ( class_exists( 'ZSA_PM_Cache' ) ) {
			ZSA_PM_Cache::flush_all();
		}

		return array(
			'created' => $created,
			'skipped' => $skipped,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: 1: created, 2: skipped */
				__( 'مهاجرت کامل شد: %1$d پروژه ساخته شد، %2$d مورد از قبل موجود بود.', 'zsa-pm' ),
				$created,
				$skipped
			),
		);
	}

	/**
	 * اسلاگ لاتین پروژه‌ها (نشانی تمیز برای سئو؛ اسلاگ فارسی به %D8%... تبدیل می‌شود)
	 *
	 * @param string $title عنوان.
	 * @return string
	 */
	private static function slug_for( string $title ): string {
		$map = array(
			'اکوسیستم دیجیتال کافه زارع' => 'kafezare-digital-ecosystem',
			'بازی واژیک'                 => 'vajik',
			'بازی واژیک ۲'              => 'vajik-2',
			'اپلیکیشن ماه بانو'         => 'moonbanu',
			'اپلیکیشن یادمان'           => 'yademan',
			'اپلیکیشن تراز'             => 'taraz',
			'کافه زارع'                  => 'site-kafezare-ir',
			'موبایل آباد'               => 'site-mobileabadbumahen-ir',
			'موبایل راک'                 => 'site-mobilerock-ir',
			'دکترونیک'                   => 'site-doctronic-ir',
			'خرازی آس'                   => 'site-kharrazias-ir',
			'صنایع فلزی چلنگر'           => 'site-chalangr-ir',
			"قالب\u{200C}سازی آریان"      => 'site-arian-can-ir',
			'عروسک روبی'                 => 'site-aroosakruby-ir',
			'راز آناهیتا'                => 'raz-anahita',
			'دورهمی ما'                  => 'dorehami-ma',
			'حکم کارتی آسا'              => 'hokm-cards-asa',
			'تخته نرد آرا'               => 'takhte-nard-ara',
			'پخش موسیقی دکترونیک'        => 'doctronic-music-player',
			'بازی دزدک فراری'            => 'dozdak-farari',
		);
		return $map[ $title ] ?? '';
	}

	/**
	 * دستهٔ Schema.org برای اپلیکیشن‌ها (مطابق فهرست مجاز گوگل)
	 *
	 * @param string $title عنوان.
	 * @return string
	 */
	private static function app_cat_for( string $title ): string {
		$map = array(
			'اکوسیستم دیجیتال کافه زارع' => 'LifestyleApplication',
			'بازی واژیک'                 => 'GameApplication',
			'بازی واژیک ۲'              => 'GameApplication',
			'اپلیکیشن ماه بانو'         => 'HealthApplication',
			'اپلیکیشن یادمان'           => 'UtilitiesApplication',
			'اپلیکیشن تراز'             => 'BusinessApplication',
		);
		return $map[ $title ] ?? '';
	}

	/**
	 * تکمیل داده‌های جدید نسخهٔ ۱.۰.۱ برای پروژه‌هایی که قبلاً ساخته شده‌اند
	 * (فقط فیلد خالی پر می‌شود؛ هیچ مقدار ویرایش‌شده‌ای بازنویسی نمی‌شود)
	 *
	 * @param int   $post_id شناسه.
	 * @param array $row     داده.
	 */
	private static function backfill( int $post_id, array $row ): void {
		$cat = self::app_cat_for( $row['title'] );
		if ( '' !== $cat && '' === (string) get_post_meta( $post_id, 'zsa_app_category', true ) ) {
			update_post_meta( $post_id, 'zsa_app_category', $cat );
		}

		if ( ! empty( $row['gradient_class'] ) && '' === (string) get_post_meta( $post_id, 'zsa_gradient_class', true ) ) {
			update_post_meta( $post_id, 'zsa_gradient_class', sanitize_key( $row['gradient_class'] ) );
		}

		// اسلاگ درصدی (فارسی) → اسلاگ لاتین
		$slug = self::slug_for( $row['title'] );
		$post = get_post( $post_id );
		if ( '' !== $slug && $post && false !== strpos( (string) $post->post_name, '%' ) ) {
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $slug,
				)
			);
		}
	}

	/**
	 * جستجوی پروژه بر اساس عنوان
	 *
	 * @param string $title عنوان.
	 * @return int|false
	 */
	private static function find_by_title( string $title ) {
		$q = new WP_Query(
			array(
				'post_type'      => ZSA_PM_CPT::POST_TYPE,
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( ! empty( $q->posts ) ) {
			return (int) $q->posts[0];
		}
		return false;
	}

	/**
	 * ساخت پروژه
	 *
	 * @param array $row داده.
	 * @return int|WP_Error
	 */
	private static function create_project( array $row ): int|WP_Error {
		$post_id = wp_insert_post(
			array(
				'post_type'    => ZSA_PM_CPT::POST_TYPE,
				'post_title'   => $row['title'],
				'post_name'    => self::slug_for( $row['title'] ),
				'post_content' => $row['description'],
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ?: 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// ترم
		if ( ! empty( $row['type'] ) ) {
			wp_set_object_terms( $post_id, array( $row['type'] ), ZSA_PM_Taxonomy::TAXONOMY );
		}

		// متا
		update_post_meta( $post_id, 'zsa_status', $row['status'] );
		update_post_meta( $post_id, 'zsa_order', $row['order'] );

		if ( ! empty( $row['badge'] ) ) {
			update_post_meta( $post_id, 'zsa_badge', $row['badge'] );
		}
		if ( ! empty( $row['badge_color'] ) ) {
			update_post_meta( $post_id, 'zsa_badge_color', $row['badge_color'] );
		}
		if ( isset( $row['short_desc'] ) ) {
			update_post_meta( $post_id, 'zsa_short_desc', mb_substr( $row['short_desc'], 0, 200 ) );
		}
		if ( ! empty( $row['card_mode'] ) ) {
			update_post_meta( $post_id, 'zsa_card_mode', $row['card_mode'] );
		}
		if ( ! empty( $row['gradient_from'] ) ) {
			update_post_meta( $post_id, 'zsa_gradient_from', $row['gradient_from'] );
		}
		if ( ! empty( $row['gradient_to'] ) ) {
			update_post_meta( $post_id, 'zsa_gradient_to', $row['gradient_to'] );
		}
		if ( ! empty( $row['icon'] ) ) {
			update_post_meta( $post_id, 'zsa_icon', $row['icon'] );
		}
		// کلاس گرادیان g1 تا g8 برای کارت‌های وب‌سایت
		if ( ! empty( $row['gradient_class'] ) ) {
			update_post_meta( $post_id, 'zsa_gradient_class', sanitize_key( $row['gradient_class'] ) );
		}
		if ( ! empty( $row['image_fit'] ) ) {
			update_post_meta( $post_id, 'zsa_image_fit', $row['image_fit'] );
		}
		if ( ! empty( $row['links'] ) ) {
			update_post_meta( $post_id, 'zsa_links', $row['links'] );
		}
		if ( ! empty( $row['platforms'] ) ) {
			update_post_meta( $post_id, 'zsa_platforms', $row['platforms'] );
		}
		$cat = self::app_cat_for( $row['title'] );
		if ( '' !== $cat ) {
			update_post_meta( $post_id, 'zsa_app_category', $cat );
		}

		// تصویر شاخص — از URL خارجی دانلود می‌شود
		if ( ! empty( $row['image'] ) ) {
			$attachment_id = self::sideload_image( $row['image'], $post_id, $row['title'] );
			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				set_post_thumbnail( $post_id, (int) $attachment_id );
				// متن جایگزین برای سئو و دسترسی‌پذیری
				update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $row['title'] );
			}
		}

		return (int) $post_id;
	}

	/**
	 * دانلود تصویر از URL و ساخت ضمیمه رسانه
	 *
	 * @param string $url      آدرس تصویر.
	 * @param int    $post_id  شناسه پست.
	 * @param string $desc     توضیح.
	 * @return int|WP_Error
	 */
	private static function sideload_image( string $url, int $post_id, string $desc ) {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// اگر تصویر از پیش ضمیمه شده (توسط خود سایت)، بررسی شود
		$existing = self::find_attachment_by_url( $url );
		if ( $existing ) {
			return (int) $existing;
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$name = is_string( $path ) ? basename( $path ) : '';
		if ( '' === $name ) {
			$name = sanitize_title( $desc ) . '.jpg';
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, $post_id, $desc );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $file_array['tmp_name'] );
			return $id;
		}

		return (int) $id;
	}

	/**
	 * یافتن شناسه ضمیمه بر اساس URL
	 *
	 * @param string $url آدرس.
	 * @return int|false
	 */
	private static function find_attachment_by_url( string $url ) {
		global $wpdb;
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path ) {
			return false;
		}
		$like = '%' . $wpdb->esc_like( basename( (string) $path ) ) . '%';
		$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND guid LIKE %s LIMIT 1", $like ) );
		return $id ?: false;
	}

	/* ===== داده‌های مهاجرت ===== */

	/**
	 * همه پروژه‌های اولیه برای مهاجرت
	 *
	 * @return array
	 */
	private static function all_projects(): array {
		return array_merge( self::published_projects(), self::coming_soon_projects() );
	}

	/**
	 * پروژه‌های منتشرشده
	 *
	 * @return array
	 */
	private static function published_projects(): array {
		$list = array();

		// ۱) اکوسیستم دیجیتال کافه زارع
		$list[] = array(
			'title'        => 'اکوسیستم دیجیتال کافه زارع',
			'type'         => 'ecosystem',
			'status'       => 'published',
			'order'        => 10,
			'badge'        => 'ویژه',
			'badge_color'  => '#f59e0b',
			'image'        => 'https://kafezare.ir/wp-content/uploads/2025/12/logocafezare.png',
			'image_fit'    => 'cover',
			'card_mode'    => 'image',
			'short_desc'   => 'طراحی و توسعه وب‌سایت و اپلیکیشن اختصاصی کافه زارع با امکانات سفارش آنلاین، معرفی کتاب و موسیقی.',
			'description'  => 'اکوسیستم کامل دیجیتال کافه زارع شامل وب‌سایت فروشگاهی، اپلیکیشن موبایل، سامانه معرفی کتاب و پلیر موسیقی اختصاصی.',
			'links'        => array(
				array(
					'label'  => 'دانلود اپلیکیشن →',
					'url'    => 'https://kafezare.ir/kafezareapp/',
					'style'  => 'primary',
					'target' => '_blank',
				),
				array(
					'label'  => 'وب‌سایت →',
					'url'    => 'https://kafezare.ir',
					'style'  => 'secondary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android', 'ios', 'pwa' ),
		);

		// ۲) بازی واژیک
		$list[] = array(
			'title'        => 'بازی واژیک',
			'type'         => 'game',
			'status'       => 'published',
			'order'        => 20,
			'badge'        => 'بازی',
			'badge_color'  => '#3b82f6',
			'image'        => 'https://kafezare.ir/wp-content/uploads/2026/08/vajik.webp',
			'image_fit'    => 'cover',
			'card_mode'    => 'image',
			'short_desc'   => 'بازی کلمات فارسی با بیش از ۱۰۰۰ مرحله، طراحی اصیل ایرانی، کاملاً آفلاین و با رعایت کامل حریم خصوصی.',
			'description'  => 'بازی کلمات فارسی واژیک با گیم‌پلی روان و بیش از ۱۰۰۰ مرحله متنوع، طراحی اصیل ایرانی، کاملاً آفلاین.',
			'links'        => array(
				array(
					'label'  => 'کافه‌بازار →',
					'url'    => 'https://cafebazaar.ir/app/com.hosseinzare.vazik',
					'style'  => 'primary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android' ),
		);

		// ۳) بازی واژیک ۲
		$list[] = array(
			'title'        => 'بازی واژیک ۲',
			'type'         => 'game',
			'status'       => 'published',
			'order'        => 30,
			'badge'        => 'بازی',
			'badge_color'  => '#3b82f6',
			'image'        => 'https://kafezare.ir/wp-content/uploads/2026/08/app_icon.webp',
			'image_fit'    => 'cover',
			'card_mode'    => 'image',
			'short_desc'   => 'نسخه جدید بازی کلمات فارسی با چالش‌های بیشتر و طراحی مدرن‌تر.',
			'description'  => 'نسخه جدید بازی کلمات فارسی واژیک با چالش‌های بیشتر، طراحی مدرن‌تر و تجربه‌ای هیجان‌انگیزتر.',
			'links'        => array(
				array(
					'label'  => 'کافه‌بازار →',
					'url'    => 'https://cafebazaar.ir/app/com.hosseinzare.vazik2',
					'style'  => 'primary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android' ),
		);

		// ۴) اپلیکیشن ماه بانو
		$list[] = array(
			'title'        => 'اپلیکیشن ماه بانو',
			'type'         => 'app',
			'status'       => 'published',
			'order'        => 40,
			'image'        => 'https://kafezare.ir/wp-content/uploads/2026/09/logo-splash.webp',
			'image_fit'    => 'contain',
			'card_mode'    => 'gradient',
			'gradient_from'=> '#4c1d95',
			'gradient_to'  => '#8b5cf6',
			'short_desc'   => 'تقویم شمسی چرخهٔ قاعدگی با پیش‌بینی فازها، بینش شخصی و همراهی محترمانهٔ همسر؛ با حریم خصوصی کامل.',
			'description'  => 'اپلیکیشن ماه بانو یک تقویم شمسی هوشمند برای چرخهٔ قاعدگی با قابلیت پیش‌بینی فازها و بینش شخصی، همراه با رعایت کامل حریم خصوصی.',
			'links'        => array(
				array(
					'label'  => '🌐 نسخه وب (PWA) →',
					'url'    => 'https://kafezare.ir/moonbanu/',
					'style'  => 'tertiary',
					'target' => '_blank',
				),
				array(
					'label'  => 'کافه‌بازار →',
					'url'    => 'https://cafebazaar.ir/app/ir.zsa.moonbanu',
					'style'  => 'primary',
					'target' => '_blank',
				),
				array(
					'label'  => 'مایکت →',
					'url'    => 'https://myket.ir/app/ir.zsa.moonbanu',
					'style'  => 'secondary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android', 'ios', 'pwa' ),
		);

		// ۵) اپلیکیشن یادمان
		$list[] = array(
			'title'        => 'اپلیکیشن یادمان',
			'type'         => 'app',
			'status'       => 'published',
			'order'        => 50,
			'badge'        => 'ابزار',
			'badge_color'  => '#06b6d4',
			'image'        => 'https://kafezare.ir/wp-content/uploads/2026/10/yademan.png',
			'image_fit'    => 'cover',
			'card_mode'    => 'image',
			'short_desc'   => 'یادآور هوشمند قسط، چک، دارو، قرار و لیست خرید با تقویم شمسی.',
			'description'  => 'اپلیکیشن یادمان یک یادآور هوشمند با تقویم شمسی برای مدیریت اقساط، چک‌ها، داروها، قرارها و لیست خرید.',
			'links'        => array(
				array(
					'label'  => 'کافه‌بازار →',
					'url'    => 'https://cafebazaar.ir/app/com.hossein.yademan',
					'style'  => 'primary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android' ),
		);

		// ۶) اپلیکیشن تراز
		$list[] = array(
			'title'        => 'اپلیکیشن تراز',
			'type'         => 'app',
			'status'       => 'published',
			'order'        => 60,
			'badge'        => 'تجاری',
			'badge_color'  => '#059669',
			'image'        => 'https://kafezare.ir/wp-content/uploads/2026/10/taraz.png',
			'image_fit'    => 'cover',
			'card_mode'    => 'image',
			'short_desc'   => 'دفتر حساب‌وکتاب آفلاین، یادآوری پیامکی و صدور فاکتور شیک برای همه مشاغل.',
			'description'  => 'اپلیکیشن تراز دفتر حساب‌وکتاب آفلاین با قابلیت یادآوری پیامکی و صدور فاکتور حرفه‌ای، مناسب همه مشاغل.',
			'links'        => array(
				array(
					'label'  => 'کافه‌بازار →',
					'url'    => 'https://cafebazaar.ir/app/ir.taraz.app',
					'style'  => 'primary',
					'target' => '_blank',
				),
			),
			'platforms'    => array( 'android' ),
		);

		// ۷ تا ۱۴) وب‌سایت‌ها
		$sites_data = array(
			array(
				'title'  => 'کافه زارع',
				'domain'=> 'kafezare.ir',
				'icon'   => '☕',
				'tag'    => 'فروشگاهی ووکامرس',
				'class'  => 'g1',
				'order'  => 70,
			),
			array(
				'title'  => 'موبایل آباد',
				'domain'=> 'mobileabadbumahen.ir',
				'icon'   => '📱',
				'tag'    => 'فروشگاهی موبایل',
				'class'  => 'g2',
				'order'  => 71,
			),
			array(
				'title'  => 'موبایل راک',
				'domain'=> 'mobilerock.ir',
				'icon'   => '📲',
				'tag'    => 'فروشگاهی موبایل',
				'class'  => 'g3',
				'order'  => 72,
			),
			array(
				'title'  => 'دکترونیک',
				'domain'=> 'doctronic.ir',
				'icon'   => '🔌',
				'tag'    => 'فروشگاهی الکترونیک',
				'class'  => 'g4',
				'order'  => 73,
			),
			array(
				'title'  => 'خرازی آس',
				'domain'=> 'kharrazias.ir',
				'icon'   => '🧵',
				'tag'    => 'فروشگاهی خرازی',
				'class'  => 'g5',
				'order'  => 74,
			),
			array(
				'title'  => 'صنایع فلزی چلنگر',
				'domain'=> 'chalangr.ir',
				'icon'   => '⚙️',
				'tag'    => 'شرکتی صنعتی',
				'class'  => 'g6',
				'order'  => 75,
			),
			array(
				'title'  => 'قالب‌سازی آریان',
				'domain'=> 'arian-can.ir',
				'icon'   => '🏭',
				'tag'    => 'شرکتی صنعتی',
				'class'  => 'g7',
				'order'  => 76,
			),
			array(
				'title'  => 'عروسک روبی',
				'domain'=> 'aroosakruby.ir',
				'icon'   => '🧸',
				'tag'    => 'فروشگاهی',
				'class'  => 'g8',
				'order'  => 77,
			),
		);

		foreach ( $sites_data as $s ) {
			$list[] = array(
				'title'       => $s['title'],
				'type'        => 'website',
				'status'      => 'published',
				'order'       => $s['order'],
				'card_mode'   => 'gradient',
				'icon'        => $s['icon'],
				'gradient_class' => $s['class'], // کلاس g1..g8 برای استایل اختصاصی
				'short_desc'  => $s['tag'],
				'description' => 'وب‌سایت فعال ' . $s['title'] . ' (' . $s['domain'] . ') — ' . $s['tag'],
				'links'       => array(
					array(
						'label'  => 'مشاهده ←',
						'url'    => 'https://' . $s['domain'],
						'style'  => 'primary',
						'target' => '_blank',
					),
				),
			);
		}

		return $list;
	}

	/**
	 * پروژه‌های در دست توسعه
	 *
	 * @return array
	 */
	private static function coming_soon_projects(): array {
		return array(
			array(
				'title'       => 'راز آناهیتا',
				'type'        => 'game',
				'status'      => 'coming-soon',
				'order'       => 100,
				'icon'        => '🔮',
				'short_desc'  => 'بازی ماجراجویی و معمایی با داستان ایرانی',
				'description' => 'بازی ماجراجویی و معمایی راز آناهیتا با داستانی اصیل ایرانی.',
			),
			array(
				'title'       => 'دورهمی ما',
				'type'        => 'game',
				'status'      => 'coming-soon',
				'order'       => 110,
				'icon'        => '🎲',
				'short_desc'  => 'بازی گروهی و سرگرم‌کننده برای لحظات شاد',
				'description' => 'بازی گروهی و سرگرم‌کننده دورهمی ما برای لحظات شاد در جمع دوستان و خانواده.',
			),
			array(
				'title'       => 'حکم کارتی آسا',
				'type'        => 'game',
				'status'      => 'coming-soon',
				'order'       => 120,
				'icon'        => '🃏',
				'short_desc'  => 'تجربه‌ای نوستالژیک و دیجیتال از بازی محبوب حکم',
				'description' => 'تجربه‌ای نوستالژیک و دیجیتال از بازی محبوب حکم با طراحی مدرن.',
			),
			array(
				'title'       => 'تخته نرد آرا',
				'type'        => 'game',
				'status'      => 'coming-soon',
				'order'       => 130,
				'icon'        => '🎲',
				'short_desc'  => 'بازی کلاسیک تخته نرد با طراحی مدرن و آنلاین',
				'description' => 'بازی کلاسیک تخته نرد آرا با طراحی مدرن، امکان بازی آنلاین و گرافیک جذاب.',
			),
			array(
				'title'       => 'پخش موسیقی دکترونیک',
				'type'        => 'digital-product',
				'status'      => 'coming-soon',
				'order'       => 140,
				'icon'        => '🎵',
				'short_desc'  => 'پلیر موسیقی حرفه‌ای و استودیویی با کیفیت بالا برای تجربه‌ای شنیداری بی‌نظیر',
				'description' => 'پلیر موسیقی حرفه‌ای و استودیویی دکترونیک با کیفیت بالا برای تجربه‌ای شنیداری بی‌نظیر.',
			),
			array(
				'title'       => 'بازی دزدک فراری',
				'type'        => 'game',
				'status'      => 'coming-soon',
				'order'       => 150,
				'icon'        => '🏃',
				'short_desc'  => 'بازی هیجان‌انگیز و جذاب با چالش حل مکعب‌ها و ماجراجویی‌های پرهیجان',
				'description' => 'بازی هیجان‌انگیز و جذاب دزدک فراری با چالش حل مکعب‌ها و ماجراجویی‌های پرهیجان.',
			),
		);
	}
}