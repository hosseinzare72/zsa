<?php
/**
 * کلاس صفحه تنظیمات افزونه
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مدیریت صفحه تنظیمات با تب‌های: عمومی، نمایش، رنگ‌ها، ابزارها
 */
final class ZSA_PM_Settings {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Settings|null
	 */
	private static ?ZSA_PM_Settings $instance = null;

	/**
	 * کلید گزینه
	 */
	public const OPTION_KEY = 'zsa_pm_settings';

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_zsa_pm_run_migration', array( $this, 'ajax_migration' ) );
		add_action( 'wp_ajax_zsa_pm_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_zsa_pm_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_zsa_pm_import', array( $this, 'ajax_import' ) );
	}

	/**
	 * افزودن زیرمنو
	 */
	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . ZSA_PM_CPT::POST_TYPE,
			__( 'تنظیمات ZSA', 'zsa-pm' ),
			__( 'تنظیمات', 'zsa-pm' ),
			'manage_options',
			'zsa-pm-settings',
			array( $this, 'render' )
		);
	}

	/**
	 * ثبت تنظیمات با Settings API
	 */
	public function register(): void {
		register_setting(
			'zsa_pm_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		// تب ۱: عمومی
		add_settings_section( 'zsa_pm_general', __( 'عمومی', 'zsa-pm' ), '__return_false', 'zsa-pm-settings-general' );
		add_settings_field( 'phone', __( 'شماره تماس کردیت', 'zsa-pm' ), array( $this, 'field_phone' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'portfolio_url', __( 'آدرس صفحهٔ پورتفولیو', 'zsa-pm' ), array( $this, 'field_portfolio_url' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'telegram', __( 'لینک تلگرام', 'zsa-pm' ), array( $this, 'field_telegram' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'schema', __( 'Schema.org', 'zsa-pm' ), array( $this, 'field_schema' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'animations', __( 'انیمیشن و شمارنده', 'zsa-pm' ), array( $this, 'field_animations' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'credit_auto', __( 'کردیت پایین صفحه', 'zsa-pm' ), array( $this, 'field_credit_auto' ), 'zsa-pm-settings-general', 'zsa_pm_general' );
		add_settings_field( 'quality', __( 'عدد تعهد به کیفیت', 'zsa-pm' ), array( $this, 'field_quality' ), 'zsa-pm-settings-general', 'zsa_pm_general' );

		// تب ۲: نمایش
		add_settings_section( 'zsa_pm_display', __( 'نمایش', 'zsa-pm' ), '__return_false', 'zsa-pm-settings-display' );
		add_settings_field( 'columns', __( 'تعداد ستون‌ها', 'zsa-pm' ), array( $this, 'field_columns' ), 'zsa-pm-settings-display', 'zsa_pm_display' );
		add_settings_field( 'gap', __( 'فاصله کارت‌ها', 'zsa-pm' ), array( $this, 'field_gap' ), 'zsa-pm-settings-display', 'zsa_pm_display' );
		add_settings_field( 'hover', __( 'افکت Hover', 'zsa-pm' ), array( $this, 'field_hover' ), 'zsa-pm-settings-display', 'zsa_pm_display' );

		// تب ۳: رنگ‌ها
		add_settings_section( 'zsa_pm_colors', __( 'رنگ‌ها', 'zsa-pm' ), array( $this, 'section_colors_desc' ), 'zsa-pm-settings-colors' );
		add_settings_field( 'color_primary', __( 'رنگ اصلی (Primary)', 'zsa-pm' ), array( $this, 'field_color_primary' ), 'zsa-pm-settings-colors', 'zsa_pm_colors' );
		add_settings_field( 'color_accent', __( 'رنگ تاکید (Accent)', 'zsa-pm' ), array( $this, 'field_color_accent' ), 'zsa-pm-settings-colors', 'zsa_pm_colors' );
		add_settings_field( 'color_web', __( 'رنگ وب (Web)', 'zsa-pm' ), array( $this, 'field_color_web' ), 'zsa-pm-settings-colors', 'zsa_pm_colors' );

		// تب ۴: تمام‌صفحه
		add_settings_section( 'zsa_pm_full', __( 'حالت تمام‌صفحه', 'zsa-pm' ), array( $this, 'section_full_desc' ), 'zsa-pm-settings-fullpage' );
		add_settings_field( 'full_theme', __( 'پوستهٔ رنگی', 'zsa-pm' ), array( $this, 'field_full_theme' ), 'zsa-pm-settings-fullpage', 'zsa_pm_full' );
		add_settings_field( 'chrome_off', __( 'حذف هدر/فوتر قالب', 'zsa-pm' ), array( $this, 'field_chrome_off' ), 'zsa-pm-settings-fullpage', 'zsa_pm_full' );
		add_settings_field( 'hide_adminbar', __( 'نوار مدیریت', 'zsa-pm' ), array( $this, 'field_hide_adminbar' ), 'zsa-pm-settings-fullpage', 'zsa_pm_full' );
		add_settings_field( 'force_assets', __( 'بارگذاری همیشگی فایل‌ها', 'zsa-pm' ), array( $this, 'field_force_assets' ), 'zsa-pm-settings-fullpage', 'zsa_pm_full' );

		// تب ۵: متن‌ها
		add_settings_section( 'zsa_pm_copy', __( 'متن‌های صفحه', 'zsa-pm' ), array( $this, 'section_copy_desc' ), 'zsa-pm-settings-copy' );
		add_settings_field( 'hero_title', __( 'تیتر اصلی (هیرو)', 'zsa-pm' ), array( $this, 'field_hero_title' ), 'zsa-pm-settings-copy', 'zsa_pm_copy' );
		add_settings_field( 'hero_sub', __( 'زیرتیتر هیرو', 'zsa-pm' ), array( $this, 'field_hero_sub' ), 'zsa-pm-settings-copy', 'zsa_pm_copy' );
		add_settings_field( 'hero_buttons', __( 'دکمه‌های هیرو', 'zsa-pm' ), array( $this, 'field_hero_buttons' ), 'zsa-pm-settings-copy', 'zsa_pm_copy' );
		add_settings_field( 'section_titles', __( 'تیتر بخش‌ها', 'zsa-pm' ), array( $this, 'field_section_titles' ), 'zsa-pm-settings-copy', 'zsa_pm_copy' );
		add_settings_field( 'cta_copy', __( 'بخش پایانی (CTA)', 'zsa-pm' ), array( $this, 'field_cta_copy' ), 'zsa-pm-settings-copy', 'zsa_pm_copy' );
	}

	/**
	 * بارگذاری اسکریپت‌ها در صفحه تنظیمات
	 *
	 * @param string $hook هوک.
	 */
	public function enqueue( string $hook ): void {
		if ( 'zsa_project_page_zsa-pm-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style(
			'zsa-pm-admin',
			ZSA_PM_URL . 'admin/css/admin.css',
			array(),
			ZSA_PM_VERSION
		);
		wp_enqueue_script(
			'zsa-pm-admin',
			ZSA_PM_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			ZSA_PM_VERSION,
			true
		);
	}

	/**
	 * مقادیر پیش‌فرض — کردیت اجباری حسین زارع
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'phone'             => '09351063668',
			'telegram'          => 'https://t.me/zsastudio',
			'portfolio_url'     => '',
			'schema'            => 1,
			'animations'        => 1,
			'quality'           => 100,
			'credit_auto'       => 1,
			'columns'           => 2,
			'gap'               => 28,
			'hover'             => 1,
			'color_primary'     => '#3b82f6',
			'color_accent'      => '#f59e0b',
			'color_web'         => '#10b981',
			'full_theme'        => 'dark',
			'chrome_off'        => 0,
			'hide_adminbar'     => 0,
			'force_assets'      => 0,
			'hero_eyebrow'      => 'ZSA Studio',
			'hero_title'        => 'استودیو ZSA — خالق تجربه‌های دیجیتال',
			'hero_sub'          => 'طراحی و توسعه بازی، اپلیکیشن و وب‌سایت‌های حرفه‌ای',
			'hero_btn1_text'    => 'مشاهده پروژه‌ها',
			'hero_btn1_url'     => '#zsa-projects',
			'hero_btn2_text'    => 'تماس با من',
			'hero_btn2_url'     => '',
			'projects_title'    => 'پروژه‌های فعال و منتشرشده',
			'projects_sub'      => '',
			'web_title'         => 'نمونه‌کارهای طراحی وب‌سایت',
			'web_sub'           => '',
			'soon_title'        => 'پروژه‌های در دست توسعه',
			'soon_sub'          => '',
			'cta_title'         => 'آماده شروع یک پروژه جدید هستید؟',
			'cta_text'          => 'از ایده تا انتشار، کنار شما هستم.',
		);
	}

	/**
	 * نصب مقادیر پیش‌فرض (کلیدهای تازه اضافه می‌شوند، مقادیر موجود دست‌نمی‌خورند)
	 */
	public static function install_defaults(): void {
		$current = get_option( self::OPTION_KEY, false );

		if ( false === $current || ! is_array( $current ) ) {
			update_option( self::OPTION_KEY, self::defaults() );
			return;
		}

		// ارتقا: فقط کلیدهای گمشده را از پیش‌فرض‌ها پر کن
		$merged = array_merge( self::defaults(), $current );
		update_option( self::OPTION_KEY, $merged );
	}

	/**
	 * دریافت مقدار تنظیم
	 *
	 * @param string $key کلید.
	 * @param mixed  $fallback مقدار جایگزین.
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$opts = get_option( self::OPTION_KEY, self::defaults() );
		if ( ! is_array( $opts ) ) {
			$opts = self::defaults();
		}
		return $opts[ $key ] ?? $fallback;
	}

	/**
	 * پاکسازی ورودی‌ها (اصلاح‌شده برای پایداری کامل ذخیره‌سازی)
	 *
	 * @param array $input ورودی.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$current = get_option( self::OPTION_KEY, self::defaults() );
		if ( ! is_array( $current ) ) {
			$current = self::defaults();
		}
		
		// شروع با مقادیر فعلی برای جلوگیری از پاک شدن تنظیمات تب‌های دیگر
		$clean = wp_parse_args( $current, self::defaults() );
		$tab   = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : 'general';

		if ( 'general' === $tab ) {
			$clean['phone']         = self::normalize_phone( isset( $input['phone'] ) ? (string) $input['phone'] : '' ) ?: self::defaults()['phone'];
			$clean['telegram']      = isset( $input['telegram'] ) ? esc_url_raw( (string) $input['telegram'] ) : $clean['telegram'];
			$clean['portfolio_url'] = isset( $input['portfolio_url'] ) ? esc_url_raw( (string) $input['portfolio_url'] ) : '';
			$clean['schema']        = isset( $input['schema'] ) ? 1 : 0;
			$clean['animations']    = isset( $input['animations'] ) ? 1 : 0;
			$clean['credit_auto']   = isset( $input['credit_auto'] ) ? 1 : 0;
			$clean['quality']       = isset( $input['quality'] ) ? max( 0, min( 100, absint( $input['quality'] ) ) ) : 100;
		} elseif ( 'display' === $tab ) {
			$clean['columns'] = isset( $input['columns'] ) ? max( 1, min( 4, absint( $input['columns'] ) ) ) : 2;
			$clean['gap']     = isset( $input['gap'] ) ? max( 0, min( 80, absint( $input['gap'] ) ) ) : 28;
			$clean['hover']   = isset( $input['hover'] ) ? 1 : 0;
		} elseif ( 'colors' === $tab ) {
			foreach ( array( 'color_primary', 'color_accent', 'color_web' ) as $c ) {
				if ( isset( $input[ $c ] ) ) {
					$clean[ $c ] = sanitize_hex_color( sanitize_text_field( (string) $input[ $c ] ) ) ?: $clean[ $c ];
				}
			}
		} elseif ( 'fullpage' === $tab ) {
			$clean['full_theme']    = isset( $input['full_theme'] ) && 'light' === $input['full_theme'] ? 'light' : 'dark';
			$clean['chrome_off']    = isset( $input['chrome_off'] ) ? 1 : 0;
			$clean['hide_adminbar'] = isset( $input['hide_adminbar'] ) ? 1 : 0;
			$clean['force_assets']  = isset( $input['force_assets'] ) ? 1 : 0;
		} elseif ( 'copy' === $tab ) {
			$text = array(
				'hero_eyebrow', 'hero_title', 'hero_sub', 'hero_btn1_text', 'hero_btn2_text',
				'projects_title', 'projects_sub', 'web_title', 'web_sub', 'soon_title', 'soon_sub', 'cta_title', 'cta_text',
			);
			foreach ( $text as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$clean[ $k ] = sanitize_text_field( wp_unslash( (string) $input[ $k ] ) );
				}
			}
			foreach ( array( 'hero_btn1_url', 'hero_btn2_url' ) as $k ) {
				if ( isset( $input[ $k ] ) ) {
					$raw         = trim( sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) );
					$clean[ $k ] = ( '' === $raw || 0 === strpos( $raw, '#' ) || 0 === strpos( $raw, 'tel:' ) ) ? $raw : esc_url_raw( $raw );
				}
			}
		}

		if ( class_exists( 'ZSA_PM_Cache' ) ) {
			ZSA_PM_Cache::flush_all();
		}
		
		return $clean;
	}

	/**
	 * پاکسازی کامل تنظیمات واردشده از فایل JSON (فقط کلیدهای مجاز)
	 *
	 * @param array $data دادهٔ خام.
	 * @return array
	 */
	public static function sanitize_import( array $data ): array {
		$out = self::defaults();
		foreach ( array( 'schema', 'animations', 'hover', 'credit_auto' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = empty( $data[ $k ] ) ? 0 : 1;
			}
		}
		if ( isset( $data['phone'] ) ) {
			$out['phone'] = self::normalize_phone( (string) $data['phone'] ) ?: $out['phone'];
		}
		if ( isset( $data['telegram'] ) ) {
			$out['telegram'] = esc_url_raw( (string) $data['telegram'] );
		}
		if ( isset( $data['portfolio_url'] ) ) {
			$out['portfolio_url'] = esc_url_raw( (string) $data['portfolio_url'] );
		}
		if ( isset( $data['quality'] ) ) {
			$out['quality'] = max( 0, min( 100, absint( $data['quality'] ) ) );
		}
		if ( isset( $data['columns'] ) ) {
			$out['columns'] = max( 1, min( 4, absint( $data['columns'] ) ) );
		}
		if ( isset( $data['gap'] ) ) {
			$out['gap'] = max( 0, min( 80, absint( $data['gap'] ) ) );
		}
		if ( isset( $data['full_theme'] ) ) {
			$out['full_theme'] = 'light' === $data['full_theme'] ? 'light' : 'dark';
		}
		foreach ( array( 'chrome_off', 'hide_adminbar', 'force_assets' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = empty( $data[ $k ] ) ? 0 : 1;
			}
		}
		foreach ( array(
			'hero_eyebrow', 'hero_title', 'hero_sub', 'hero_btn1_text', 'hero_btn1_url',
			'hero_btn2_text', 'hero_btn2_url', 'projects_title', 'projects_sub', 'web_title',
			'web_sub', 'soon_title', 'soon_sub', 'cta_title', 'cta_text',
		) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $data[ $k ] );
			}
		}
		foreach ( array( 'color_primary', 'color_accent', 'color_web' ) as $c ) {
			if ( isset( $data[ $c ] ) ) {
				$out[ $c ] = sanitize_hex_color( sanitize_text_field( (string) $data[ $c ] ) ) ?: $out[ $c ];
			}
		}
		return $out;
	}

	/**
	 * آدرس برگهٔ پورتفولیو
	 *
	 * @return string
	 */
	public static function portfolio_url(): string {
		$u = (string) self::get( 'portfolio_url', '' );
		return '' !== $u ? $u : home_url( '/zsa-studio/' );
	}

	/**
	 * نرمال‌سازی شماره تلفن: تبدیل ارقام فارسی/عربی به لاتین و حذف نویسه‌های اضافه
	 *
	 * @param string $phone شماره خام.
	 * @return string
	 */
	public static function normalize_phone( string $phone ): string {
		$phone = strtr(
			$phone,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
		return preg_replace( '/[^0-9+]/', '', $phone ) ?? '';
	}

	/**
	 * شماره به قالب بین‌المللی برای tel: و Schema (09351063668 → +989351063668)
	 *
	 * @return string
	 */
	public static function phone_e164(): string {
		$p = self::normalize_phone( (string) self::get( 'phone', '09351063668' ) );
		if ( 0 === strpos( $p, '+' ) ) {
			return $p;
		}
		if ( 0 === strpos( $p, '0098' ) ) {
			return '+' . substr( $p, 2 );
		}
		if ( 0 === strpos( $p, '0' ) ) {
			return '+98' . substr( $p, 1 );
		}
		return '+' . $p;
	}

	/* ===== فیلدهای فرم ===== */

	public function field_phone(): void {
		$v = self::get( 'phone', '09351063668' );
		printf(
			'<input type="text" name="%s[phone]" id="zsa_pm_phone" value="%s" class="regular-text ltr" placeholder="09351063668"><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $v ),
			esc_html__( 'این شماره در فوتر و Schema.org به‌عنوان کردیت استفاده می‌شود.', 'zsa-pm' )
		);
	}

	public function field_portfolio_url(): void {
		$v = (string) self::get( 'portfolio_url', '' );
		printf(
			'<input type="url" name="%s[portfolio_url]" value="%s" class="regular-text ltr" placeholder="%s"><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_url( $v ),
			esc_attr( home_url( '/zsa-studio/' ) ),
			esc_html__( 'برگه‌ای که شورت‌کدها در آن هستند. برای مسیر صفحه (breadcrumb)، لینک بازگشت، Schema و تشخیص خودکارِ بارگذاری فایل‌ها استفاده می‌شود. خالی = /zsa-studio/', 'zsa-pm' )
		);
	}

	public function field_telegram(): void {
		$v = self::get( 'telegram', 'https://t.me/zsastudio' );
		printf(
			'<input type="url" name="%s[telegram]" value="%s" class="regular-text ltr" placeholder="https://t.me/username"><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_url( $v ),
			esc_html__( 'برای sameAs در Schema.org و شناور تلگرام.', 'zsa-pm' )
		);
	}

	public function field_schema(): void {
		$v = self::get( 'schema', 1 );
		printf(
			'<label><input type="checkbox" name="%s[schema]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'تولید خودکار JSON-LD (Organization + MobileApplication + WebSite)', 'zsa-pm' )
		);
	}

	public function field_animations(): void {
		$v = self::get( 'animations', 1 );
		printf(
			'<label><input type="checkbox" name="%s[animations]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'فعال‌سازی شمارنده‌های انیمیت‌شونده و IntersectionObserver', 'zsa-pm' )
		);
	}

	public function field_credit_auto(): void {
		$v = self::get( 'credit_auto', 1 );
		printf(
			'<label><input type="checkbox" name="%s[credit_auto]" value="1" %s> %s</label><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'نمایش خودکار کردیت «حسین زارع | شماره تماس» (لینک tel:) در پایین صفحه‌هایی که شورت‌کد دارند', 'zsa-pm' ),
			esc_html__( 'اگر کردیت را خودتان داخل HTML برگه دارید، این گزینه را خاموش کنید یا از شورت‌کد [zsa_credit] استفاده کنید.', 'zsa-pm' )
		);
	}

	public function field_quality(): void {
		$v = self::get( 'quality', 100 );
		printf(
			'<input type="number" min="0" max="100" name="%s[quality]" value="%s" class="small-text"> درصد<p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) $v ),
			esc_html__( 'عدد نمایش‌داده‌شده در شمارندهٔ «تعهد به کیفیت» خروجی شورت‌کد [zsa_stats].', 'zsa-pm' )
		);
	}

	public function field_columns(): void {
		$v = self::get( 'columns', 2 );
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[columns]">
			<?php for ( $i = 1; $i <= 4; $i++ ) : ?>
				<option value="<?php echo (int) $i; ?>" <?php selected( $v, $i ); ?>><?php echo (int) $i; ?> ستون</option>
			<?php endfor; ?>
		</select>
		<?php
	}

	public function field_gap(): void {
		$v = self::get( 'gap', 28 );
		printf(
			'<input type="number" min="0" max="80" name="%s[gap]" value="%s" class="small-text"> پیکسل',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) $v )
		);
	}

	public function field_hover(): void {
		$v = self::get( 'hover', 1 );
		printf(
			'<label><input type="checkbox" name="%s[hover]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'فعال‌سازی ترنسفورم و سایه هنگام هاور', 'zsa-pm' )
		);
	}

	public function section_colors_desc(): void {
		echo '<p>' . esc_html__( 'این متغیرها در تمام صفحات فرانت‌اند به‌صورت CSS Custom Properties تزریق می‌شوند.', 'zsa-pm' ) . '</p>';
	}

	public function field_color_primary(): void {
		$v = self::get( 'color_primary', '#3b82f6' );
		printf(
			'<input type="text" name="%s[color_primary]" class="zsa-pm-color" value="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $v )
		);
	}

	public function field_color_accent(): void {
		$v = self::get( 'color_accent', '#f59e0b' );
		printf(
			'<input type="text" name="%s[color_accent]" class="zsa-pm-color" value="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $v )
		);
	}

	public function field_color_web(): void {
		$v = self::get( 'color_web', '#10b981' );
		printf(
			'<input type="text" name="%s[color_web]" class="zsa-pm-color" value="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( $v )
		);
	}

	/* ===== فیلدهای تب تمام‌صفحه ===== */

	public function section_full_desc(): void {
		echo '<p>' . esc_html__( 'برای اینکه برگهٔ پورتفولیو «تمام‌صفحه» و بدون هدر/فوتر/سایدبار باشد، از فهرست «قالب» در بخش ویژگی‌های برگه، گزینهٔ «ZSA — تمام‌صفحه» را انتخاب کنید (بهترین و تمیزترین حالت). اگر قالب یا صفحه‌ساز اجازهٔ تغییر قالب برگه را نمی‌دهد، گزینهٔ «حذف هدر/فوتر قالب» را فعال کنید یا ویژگی fullpage="1" را به شورت‌کد اضافه کنید.', 'zsa-pm' ) . '</p>';
	}

	public function field_full_theme(): void {
		$v = self::get( 'full_theme', 'dark' );
		?>
		<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[full_theme]">
			<option value="dark" <?php selected( $v, 'dark' ); ?>><?php esc_html_e( 'تیره (پیش‌فرض)', 'zsa-pm' ); ?></option>
			<option value="light" <?php selected( $v, 'light' ); ?>><?php esc_html_e( 'روشن', 'zsa-pm' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'کارت‌ها همیشه پس‌زمینهٔ خودشان را دارند؛ بنابراین روی قالب‌های روشن هم درست دیده می‌شوند.', 'zsa-pm' ); ?></p>
		<?php
	}

	public function field_chrome_off(): void {
		$v = self::get( 'chrome_off', 0 );
		printf(
			'<label><input type="checkbox" name="%s[chrome_off]" value="1" %s> %s</label><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'پنهان‌کردن هدر، فوتر، سایدبار، دیدگاه‌ها و تیتر برگه با CSS', 'zsa-pm' ),
			esc_html__( 'فقط روی برگه‌هایی که شورت‌کد ZSA دارند اعمال می‌شود.', 'zsa-pm' )
		);
	}

	public function field_hide_adminbar(): void {
		$v = self::get( 'hide_adminbar', 0 );
		printf(
			'<label><input type="checkbox" name="%s[hide_adminbar]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'مخفی‌کردن نوار مدیریت وردپرس در صفحهٔ تمام‌صفحه', 'zsa-pm' )
		);
	}

	public function field_force_assets(): void {
		$v = self::get( 'force_assets', 0 );
		printf(
			'<label><input type="checkbox" name="%s[force_assets]" value="1" %s> %s</label><p class="description">%s</p>',
			esc_attr( self::OPTION_KEY ),
			checked( $v, 1, false ),
			esc_html__( 'بارگذاری CSS/JS در همهٔ صفحه‌ها', 'zsa-pm' ),
			esc_html__( 'فقط وقتی فعال کنید که شورت‌کد را داخل صفحه‌ساز (Elementor و مشابه) می‌گذارید و تشخیص خودکار کار نمی‌کند.', 'zsa-pm' )
		);
	}

	/* ===== فیلدهای تب متن‌ها ===== */

	public function section_copy_desc(): void {
		echo '<p>' . esc_html__( 'این متن‌ها فقط در شورت‌کد جامع [zsa_portfolio] استفاده می‌شوند. اگر خودتان تیترها را داخل برگه نوشته‌اید، نیازی به تغییر آن‌ها نیست.', 'zsa-pm' ) . '</p>';
	}

	public function field_hero_title(): void {
		printf(
			'<input type="text" name="%s[hero_title]" value="%s" class="regular-text" placeholder="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_title', '' ) ),
			esc_attr__( 'استودیو ZSA — خالق تجربه‌های دیجیتال', 'zsa-pm' )
		);
		printf(
			'<p class="description">%s</p><input type="text" name="%s[hero_eyebrow]" value="%s" class="regular-text" placeholder="ZSA Studio">',
			esc_html__( 'خط کوچک بالای تیتر', 'zsa-pm' ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_eyebrow', '' ) )
		);
	}

	public function field_hero_sub(): void {
		printf(
			'<textarea name="%s[hero_sub]" rows="3" class="large-text">%s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_textarea( (string) self::get( 'hero_sub', '' ) )
		);
	}

	public function field_hero_buttons(): void {
		printf(
			'<input type="text" name="%s[hero_btn1_text]" value="%s" class="regular-text" placeholder="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_btn1_text', '' ) ),
			esc_attr__( 'مشاهده پروژه‌ها', 'zsa-pm' )
		);
		printf(
			'<input type="text" name="%s[hero_btn1_url]" value="%s" class="regular-text ltr" placeholder="#zsa-projects">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_btn1_url', '' ) )
		);
		echo '<br><br>';
		printf(
			'<input type="text" name="%s[hero_btn2_text]" value="%s" class="regular-text" placeholder="%s">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_btn2_text', '' ) ),
			esc_attr__( 'تماس با من', 'zsa-pm' )
		);
		printf(
			'<input type="text" name="%s[hero_btn2_url]" value="%s" class="regular-text ltr" placeholder="tel:+989351063668">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'hero_btn2_url', '' ) )
		);
	}

	public function field_section_titles(): void {
		$rows = array(
			'projects_title' => __( 'پروژه‌های فعال و منتشرشده', 'zsa-pm' ),
			'projects_sub'   => __( 'زیرتیتر بخش پروژه‌ها', 'zsa-pm' ),
			'web_title'      => __( 'نمونه‌کارهای طراحی وب‌سایت', 'zsa-pm' ),
			'web_sub'        => __( 'زیرتیتر بخش وب‌سایت‌ها', 'zsa-pm' ),
			'soon_title'     => __( 'پروژه‌های در دست توسعه', 'zsa-pm' ),
			'soon_sub'       => __( 'زیرتیتر بخش در توسعه', 'zsa-pm' ),
		);

		foreach ( $rows as $key => $placeholder ) {
			printf(
				'<input type="text" name="%1$s[%2$s]" value="%3$s" class="large-text" placeholder="%4$s"><br>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $key ),
				esc_attr( (string) self::get( $key, '' ) ),
				esc_attr( $placeholder )
			);
		}
	}

	public function field_cta_copy(): void {
		printf(
			'<input type="text" name="%s[cta_title]" value="%s" class="large-text" placeholder="%s"><br><br>',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) self::get( 'cta_title', '' ) ),
			esc_attr__( 'آماده شروع یک پروژه جدید هستید؟', 'zsa-pm' )
		);
		printf(
			'<textarea name="%s[cta_text]" rows="3" class="large-text" placeholder="%s">%s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_attr__( 'از ایده تا انتشار، کنار شما هستم.', 'zsa-pm' ),
			esc_textarea( (string) self::get( 'cta_text', '' ) )
		);
	}

	/* ===== رندر صفحه ===== */

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز', 'zsa-pm' ) );
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'general'  => __( 'عمومی', 'zsa-pm' ),
			'display'  => __( 'نمایش', 'zsa-pm' ),
			'colors'   => __( 'رنگ‌ها', 'zsa-pm' ),
			'fullpage' => __( 'تمام‌صفحه', 'zsa-pm' ),
			'copy'     => __( 'متن‌ها', 'zsa-pm' ),
			'tools'    => __( 'ابزارها', 'zsa-pm' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		?>
		<div class="wrap zsa-pm-settings" dir="rtl">
			<h1>⚙️ <?php esc_html_e( 'تنظیمات ZSA Portfolio Manager', 'zsa-pm' ); ?></h1>
			<p class="description"><?php esc_html_e( 'تمام تنظیمات، متاباکس‌ها و ابزارهای مدیریت پورتفولیوی استودیوی ZSA.', 'zsa-pm' ); ?></p>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="?post_type=<?php echo esc_attr( ZSA_PM_CPT::POST_TYPE ); ?>&page=zsa-pm-settings&tab=<?php echo esc_attr( $key ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( 'tools' === $tab ) : ?>
				<?php $this->render_tools_tab(); ?>
			<?php else : ?>
				<form method="post" action="options.php" class="zsa-pm-form">
					<?php
					settings_fields( 'zsa_pm_group' );
					printf( '<input type="hidden" name="%s[_tab]" value="%s">', esc_attr( self::OPTION_KEY ), esc_attr( $tab ) );
					do_settings_sections( 'zsa-pm-settings-' . $tab );
					submit_button();
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * تب ابزارها — مهاجرت، کش، خروجی/ورودی
	 */
	private function render_tools_tab(): void {
		?>
		<div class="zsa-pm-tools">
			<div class="zsa-pm-tool-card">
				<h3>🚀 <?php esc_html_e( 'مهاجرت اولیه', 'zsa-pm' ); ?></h3>
				<p><?php esc_html_e( 'ساخت تمام پروژه‌های اولیه استودیوی ZSA با یک کلیک. اگر پروژه‌ای از قبل با همان عنوان وجود داشته باشد، ساخته نمی‌شود.', 'zsa-pm' ); ?></p>
				<button type="button" class="button button-primary" id="zsa-pm-run-migration" data-nonce="<?php echo esc_attr( wp_create_nonce( 'zsa_pm_run_migration' ) ); ?>">
					<?php esc_html_e( 'اجرای مهاجرت اولیه', 'zsa-pm' ); ?>
				</button>
				<span class="zsa-pm-tool-result" data-result></span>
			</div>

			<div class="zsa-pm-tool-card">
				<h3>🧹 <?php esc_html_e( 'پاک‌سازی کش افزونه', 'zsa-pm' ); ?></h3>
				<p><?php esc_html_e( 'تمام ترانزینت‌های کش داخلی افزونه را پاک می‌کند. در صورت تغییر محتوا، WP Fastest Cache و LiteSpeed Cache به‌صورت خودکار پاک می‌شوند.', 'zsa-pm' ); ?></p>
				<button type="button" class="button" id="zsa-pm-clear-cache" data-nonce="<?php echo esc_attr( wp_create_nonce( 'zsa_pm_clear_cache' ) ); ?>">
					<?php esc_html_e( 'پاک‌کردن کش', 'zsa-pm' ); ?>
				</button>
				<span class="zsa-pm-tool-result" data-result></span>
			</div>

			<div class="zsa-pm-tool-card">
				<h3>📦 <?php esc_html_e( 'خروجی / ورودی تنظیمات', 'zsa-pm' ); ?></h3>
				<p><?php esc_html_e( 'فایل JSON تنظیمات افزونه برای انتقال به سایت دیگر.', 'zsa-pm' ); ?></p>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'zsa_pm_export' ), admin_url( 'admin-ajax.php' ) ), 'zsa_pm_export' ) ); ?>" class="button">
					<?php esc_html_e( 'خروجی JSON', 'zsa-pm' ); ?>
				</a>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-ajax.php?action=zsa_pm_import' ) ); ?>" style="display:inline-block;margin-right:8px">
					<?php wp_nonce_field( 'zsa_pm_import' ); ?>
					<input type="file" name="import_file" accept="application/json" required>
					<button type="submit" class="button"><?php esc_html_e( 'ورودی JSON', 'zsa-pm' ); ?></button>
				</form>
				<?php
				if ( isset( $_GET['imported'] ) && '1' === $_GET['imported'] ) {
					echo '<div class="notice notice-success inline"><p>' . esc_html__( 'تنظیمات با موفقیت وارد شد.', 'zsa-pm' ) . '</p></div>';
				}
				?>
			</div>
		</div>
		<?php
	}

	/* ===== AJAX handlers ===== */

	public function ajax_migration(): void {
		check_ajax_referer( 'zsa_pm_run_migration', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'دسترسی غیرمجاز', 'zsa-pm' ), 403 );
		}
		$result = ZSA_PM_Importer::run();
		wp_send_json_success( $result );
	}

	public function ajax_clear_cache(): void {
		check_ajax_referer( 'zsa_pm_clear_cache', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'دسترسی غیرمجاز', 'zsa-pm' ), 403 );
		}
		if ( class_exists( 'ZSA_PM_Cache' ) ) {
			ZSA_PM_Cache::flush_all();
		}
		wp_send_json_success( __( 'کش پاک شد.', 'zsa-pm' ) );
	}

	public function ajax_export(): void {
		check_admin_referer( 'zsa_pm_export' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز', 'zsa-pm' ) );
		}
		$data = get_option( self::OPTION_KEY, self::defaults() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=zsa-pm-settings-' . gmdate( 'Ymd-His' ) . '.json' );
		echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}

	public function ajax_import(): void {
		check_admin_referer( 'zsa_pm_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز', 'zsa-pm' ) );
		}
		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'imported', '0' ) );
			exit;
		}
		$tmp = isset( $_FILES['import_file']['tmp_name'] ) ? (string) $_FILES['import_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = ( '' !== $tmp && is_uploaded_file( $tmp ) && filesize( $tmp ) < 65536 ) ? file_get_contents( $tmp ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data = '' !== $raw ? json_decode( $raw, true ) : null;
		if ( is_array( $data ) ) {
			update_option( self::OPTION_KEY, self::sanitize_import( $data ) );
			if ( class_exists( 'ZSA_PM_Cache' ) ) {
				ZSA_PM_Cache::flush_all();
			}
		}
		wp_safe_redirect( add_query_arg( 'imported', is_array( $data ) ? '1' : '0', wp_get_referer() ?: admin_url( 'edit.php?post_type=' . ZSA_PM_CPT::POST_TYPE . '&page=zsa-pm-settings&tab=tools' ) ) );
		exit;
	}
}