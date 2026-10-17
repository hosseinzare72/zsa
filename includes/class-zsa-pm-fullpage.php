<?php
/**
 * قالب «تمام‌صفحه» — برگه‌ای بدون هدر، فوتر، سایدبار و هر عنصر مزاحم قالب
 *
 * دو مسیر مستقل:
 *  ۱) قالب برگهٔ واقعی (پیشنهادی): از فهرست «قالب» در بخش ویژگی‌های برگه،
 *     گزینهٔ «ZSA — تمام‌صفحه» انتخاب می‌شود. در این حالت افزونه یک سند کامل
 *     و تمیز تولید می‌کند و قالب سایت اصلاً دخالتی ندارد.
 *  ۲) حالت سازگار (CSS): وقتی نمی‌توانید قالب برگه را عوض کنید (صفحه‌ساز،
 *     قالب FSE و...) با فعال‌کردن گزینهٔ «حذف هدر/فوتر قالب» یا نوشتن
 *     ویژگی fullpage="1" در شورت‌کد [zsa_portfolio]، عناصر اطراف قالب
 *     با CSS پنهان می‌شوند.
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مدیریت حالت تمام‌صفحه
 */
final class ZSA_PM_FullPage {

	/**
	 * اسلاگ قالب برگه که در متای _wp_page_template ذخیره می‌شود
	 */
	public const TEMPLATE = 'zsa-pm-fullpage.php';

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_FullPage|null
	 */
	private static ?ZSA_PM_FullPage $instance = null;

	/**
	 * آیا chrome-off در این درخواست فعال است؟
	 *
	 * @var bool|null
	 */
	private static ?bool $chrome_off = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_FullPage {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_filter( 'theme_page_templates', array( $this, 'register_template' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'show_admin_bar', array( $this, 'maybe_hide_admin_bar' ) );
		add_action( 'wp_head', array( $this, 'head_compat' ), 1 );
	}

	/**
	 * افزودن قالب به فهرست قالب‌های برگه
	 *
	 * @param array    $templates فهرست قالب‌ها.
	 * @param WP_Theme $theme     شیء قالب (استفاده نمی‌شود).
	 * @param mixed    $post      نوشتهٔ جاری.
	 * @param string   $post_type نوع نوشته.
	 * @return array
	 */
	public function register_template( $templates, $theme, $post, $post_type ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$templates[ self::TEMPLATE ] = __( 'ZSA — تمام‌صفحه (بدون هدر/فوتر)', 'zsa-pm' );
		return $templates;
	}

	/**
	 * آیا درخواست جاری باید با قالب تمام‌صفحهٔ افزونه رندر شود؟
	 *
	 * @return bool
	 */
	public static function is_full_page(): bool {
		static $result = null;

		if ( null !== $result ) {
			return $result;
		}

		$result = false;

		if ( ! is_singular() ) {
			return $result;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return $result;
		}

		if ( self::TEMPLATE === get_page_template_slug( $post ) ) {
			$result = true;
			return $result;
		}

		// اجبار از طریق فیلتر/تنظیمات (برای صفحه‌سازها)
		$forced = apply_filters( 'zsa_pm_full_page', false, $post->ID );
		if ( $forced ) {
			$result = true;
		}

		return $result;
	}

	/**
	 * بارگذاری فایل قالب تمام‌صفحه
	 *
	 * @param string $template مسیر قالب فعلی.
	 * @return string
	 */
	public function load_template( string $template ): string {
		if ( ! self::is_full_page() ) {
			return $template;
		}

		$tpl = ZSA_PM_PATH . 'templates/full-page.php';

		return is_readable( $tpl ) ? $tpl : $template;
	}

	/**
	 * آیا شورت‌کد [zsa_portfolio] با ویژگی fullpage در محتوای این برگه هست؟
	 *
	 * @return bool
	 */
	private static function has_fullpage_shortcode(): bool {
		$post = get_post();

		if ( ! $post ) {
			return false;
		}

		$content = (string) $post->post_content;

		return (bool) preg_match( '/\[\s*zsa_portfolio\b[^\]]*fullpage\s*=\s*["\']?(1|true|yes)/i', $content );
	}

	/**
	 * آیا حالت «حذف عناصر قالب با CSS» فعال است؟
	 *
	 * @return bool
	 */
	public static function is_chrome_off(): bool {
		if ( null !== self::$chrome_off ) {
			return self::$chrome_off;
		}

		self::$chrome_off = false;

		if ( ! ZSA_PM_Shortcodes::is_zsa_page() ) {
			return self::$chrome_off;
		}

		if ( self::is_full_page() ) {
			// قالب اختصاصی اصلاً عناصر قالب را صدا نمی‌زند؛ فقط کلاس بدنه لازم است
			self::$chrome_off = false;
			return self::$chrome_off;
		}

		if ( (int) ZSA_PM_Settings::get( 'chrome_off', 0 ) ) {
			self::$chrome_off = true;
			return self::$chrome_off;
		}

		if ( self::has_fullpage_shortcode() ) {
			self::$chrome_off = true;
		}

		return self::$chrome_off;
	}

	/**
	 * کلاس‌های بدنه
	 *
	 * @param array $classes کلاس‌ها.
	 * @return array
	 */
	public function body_classes( array $classes ): array {
		if ( self::is_full_page() ) {
			$classes[] = 'zsa-pm-fullpage';
			$classes[] = 'zsa-pm-no-chrome';
		} elseif ( self::is_chrome_off() ) {
			$classes[] = 'zsa-pm-chrome-off';
		}

		if ( ZSA_PM_Shortcodes::is_zsa_page() ) {
			$classes[] = 'zsa-pm-page';
		}

		return $classes;
	}

	/**
	 * مخفی‌کردن نوار مدیریت در حالت تمام‌صفحه (اختیاری)
	 *
	 * @param bool $show وضعیت فعلی.
	 * @return bool
	 */
	public function maybe_hide_admin_bar( bool $show ): bool {
		if ( ! $show ) {
			return $show;
		}

		if ( ! self::is_full_page() && ! self::is_chrome_off() ) {
			return $show;
		}

		return ! (int) ZSA_PM_Settings::get( 'hide_adminbar', 0 );
	}

	/**
	 * چند قانون حداقلی برای این‌که تمام‌صفحه واقعاً تمام‌صفحه باشد
	 */
	public function head_compat(): void {
		if ( ! self::is_full_page() && ! self::is_chrome_off() ) {
			return;
		}
		?>
		<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
		<meta name="theme-color" content="<?php echo esc_attr( 'light' === ZSA_PM_Settings::get( 'full_theme', 'dark' ) ? '#f8fafc' : '#050810' ); ?>">
		<?php
	}
}
