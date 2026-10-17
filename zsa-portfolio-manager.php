<?php
/**
 * Plugin Name:       ZSA Portfolio Manager
 * Plugin URI:        https://kafezare.ir/zsa-studio/
 * Description:       مدیریت کامل پورتفولیوی استودیوی ZSA — اپلیکیشن، بازی، وب‌سایت و محصول دیجیتال — به‌صورت بومی از پیشخوان وردپرس. فوق‌سبک، فارسی، RTL، تمام‌صفحه و سازگار با هاست اشتراکی اقتصادی.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            حسین زارع
 * Author URI:        https://kafezare.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       zsa-pm
 * Domain Path:       /languages
 *
 * @package ZSA_Portfolio_Manager
 */

// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ثابت‌های افزونه
define( 'ZSA_PM_VERSION', '1.1.0' );
define( 'ZSA_PM_FILE', __FILE__ );
define( 'ZSA_PM_PATH', plugin_dir_path( __FILE__ ) );
define( 'ZSA_PM_URL', plugin_dir_url( __FILE__ ) );
define( 'ZSA_PM_BASENAME', plugin_basename( __FILE__ ) );
define( 'ZSA_PM_SLUG', 'zsa-portfolio-manager' );

/**
 * لودر اصلی افزونه — الگوی Singleton
 */
final class ZSA_Portfolio_Manager {

	/**
	 * نمونه واحد کلاس
	 *
	 * @var ZSA_Portfolio_Manager|null
	 */
	private static ?ZSA_Portfolio_Manager $instance = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_Portfolio_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده — راه‌اندازی هوک‌ها
	 */
	private function __construct() {
		// لود خودکار کلاس‌ها
		spl_autoload_register( array( $this, 'autoload' ) );

		// هوک فعال‌سازی
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// راه‌اندازی اجزا
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * بارگذاری خودکار کلاس‌ها از پوشه includes
	 *
	 * @param string $class_name نام کلاس.
	 */
	public function autoload( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'ZSA_PM_' ) ) {
			return;
		}

		$file_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$file_path = ZSA_PM_PATH . 'includes/' . $file_name;

		if ( is_readable( $file_path ) ) {
			require_once $file_path;
			return;
		}

		// تلاش دوم: نادیده‌گرفتن بزرگی/کوچکی حروف (برای هاست‌های حساس به نام فایل)
		$candidates = glob( ZSA_PM_PATH . 'includes/class-*.php' );
		if ( is_array( $candidates ) ) {
			$needle = strtolower( str_replace( '_', '-', $class_name ) );
			foreach ( $candidates as $candidate ) {
				if ( strtolower( basename( $candidate ) ) === 'class-' . $needle . '.php' ) {
					require_once $candidate;
					return;
				}
			}
		}
	}

	/**
	 * مقداردهی اولیه پس از بارگذاری افزونه‌های دیگر
	 */
	public function init(): void {
		// بارگذاری ترجمه
		load_plugin_textdomain( 'zsa-pm', false, dirname( ZSA_PM_BASENAME ) . '/languages' );

		// مقداردهی اجزا
		ZSA_PM_CPT::instance();
		ZSA_PM_Taxonomy::instance();
		ZSA_PM_Metaboxes::instance();
		ZSA_PM_Settings::instance();
		ZSA_PM_Shortcodes::instance();
		ZSA_PM_Schema::instance();
		ZSA_PM_Cache::instance();
		ZSA_PM_Importer::instance();
		ZSA_PM_Seo::instance();
		ZSA_PM_Block::instance();
		ZSA_PM_FullPage::instance();

		// ارتقای نسخه: فلاش یک‌بارهٔ پیوندهای یکتا و ساخت ترم‌ها
		add_action( 'init', array( $this, 'maybe_upgrade' ), 99 );
	}

	/**
	 * ارتقای خودکار پس از به‌روزرسانی فایل‌های افزونه (بدون نیاز به غیرفعال/فعال‌سازی)
	 */
	public function maybe_upgrade(): void {
		$installed = (string) get_option( 'zsa_pm_version', '' );

		if ( ZSA_PM_VERSION === $installed ) {
			return;
		}

		ZSA_PM_Taxonomy::seed_default_terms();
		ZSA_PM_Settings::install_defaults();

		// ترم‌های تازهٔ نسخه‌های جدید به پروژه‌های قدیمی آسیب نمی‌زنند
		if ( '' === $installed ) {
			update_option( 'zsa_pm_version', ZSA_PM_VERSION, false );
			return;
		}

		// فلاش در شات‌داون انجام می‌شود تا از نوشتنِ سنگین در init جلوگیری شود
		add_action( 'shutdown', array( $this, 'late_flush' ) );
		update_option( 'zsa_pm_version', ZSA_PM_VERSION, false );
	}

	/**
	 * فلاش ریرایت در پایان درخواست (یک‌بار)
	 */
	public function late_flush(): void {
		flush_rewrite_rules( false );
	}

	/**
	 * فعال‌سازی افزونه — ساخت پیش‌فرضها
	 */
	public function activate(): void {
		// در لحظهٔ فعال‌سازی هوک init قبلاً اجرا شده است؛ پس ثبت را مستقیم انجام می‌دهیم
		ZSA_PM_CPT::instance()->register();
		ZSA_PM_Taxonomy::instance()->register();

		// ساخت ترم‌های پیش‌فرض
		ZSA_PM_Taxonomy::seed_default_terms();

		// ساخت تنظیمات پیش‌فرض (فقط کلیدهای گمشده اضافه می‌شوند)
		ZSA_PM_Settings::install_defaults();

		// فلاش ریرایت
		flush_rewrite_rules();
		update_option( 'zsa_pm_version', ZSA_PM_VERSION, false );
	}

	/**
	 * غیرفعال‌سازی افزونه
	 */
public function deactivate(): void {
	// کش را پاک کن (با بررسی وجود کلاس)
	if ( class_exists( 'ZSA_PM_Cache' ) ) {
		ZSA_PM_Cache::flush_all();
	}

	// فلاش ریرایت
	flush_rewrite_rules();
}
}

/**
 * راه‌اندازی افزونه
 */
ZSA_Portfolio_Manager::instance();