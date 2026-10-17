<?php
/**
 * کلاس کش ترانزینت داخلی افزونه
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * مدیریت ترانزینت‌های یک‌ساعته برای کوئری‌های شورت‌کد
 * سازگار با WP Fastest Cache و LiteSpeed Cache
 */
final class ZSA_PM_Cache {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Cache|null
	 */
	private static ?ZSA_PM_Cache $instance = null;

	/**
	 * پیشوند کش
	 */
	public const PREFIX = 'zsa_pm_';

	/**
	 * زمان انقضا به ثانیه (۱ ساعت)
	 */
	public const EXPIRY = HOUR_IN_SECONDS;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Cache {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'save_post_' . ZSA_PM_CPT::POST_TYPE, array( __CLASS__, 'flush_all' ), 20 );
		// پیش از حذف اجرا می‌شود؛ چون در deleted_post ردیف پست دیگر وجود ندارد
		// و get_post_type() همیشه false برمی‌گرداند (باگ نسخهٔ قبل)
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_removed' ), 20, 1 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_removed' ), 20, 1 );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_removed' ), 20, 1 );
		add_action( 'created_' . ZSA_PM_Taxonomy::TAXONOMY, array( __CLASS__, 'flush_all' ), 20 );
		add_action( 'edited_' . ZSA_PM_Taxonomy::TAXONOMY, array( __CLASS__, 'flush_all' ), 20 );
		add_action( 'delete_' . ZSA_PM_Taxonomy::TAXONOMY, array( __CLASS__, 'flush_all' ), 20 );
		add_action( 'set_object_terms', array( __CLASS__, 'on_terms_changed' ), 20, 6 );
	}

	/**
	 * پاک‌سازی کش فقط وقتی یک «پروژه» حذف/زباله‌دان شد (نه هر نوشته‌ای)
	 *
	 * @param int $post_id شناسه نوشته.
	 */
	public static function on_post_removed( $post_id ): void {
		if ( ZSA_PM_CPT::POST_TYPE === get_post_type( (int) $post_id ) ) {
			self::flush_all();
		}
	}

	/**
	 * پاک‌سازی کش وقتی ترم‌های یک پروژه تغییر کردند
	 *
	 * @param int    $object_id  شناسه شیء.
	 * @param array  $terms      ترم‌ها.
	 * @param array  $tt_ids     شناسه‌ها.
	 * @param string $taxonomy   تاکسونومی.
	 * @param bool   $append     الحاقی.
	 * @param array  $old_tt_ids شناسه‌های قبلی.
	 */
	public static function on_terms_changed( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ZSA_PM_Taxonomy::TAXONOMY !== $taxonomy ) {
			return;
		}
		if ( ZSA_PM_CPT::POST_TYPE === get_post_type( (int) $object_id ) ) {
			self::flush_all();
		}
	}

	/**
	 * ساخت کلید کش امن
	 *
	 * @param string $context زمینه.
	 * @param array  $args آرگومان‌ها.
	 * @return string
	 */
	public static function key( string $context, array $args = array() ): string {
		return self::PREFIX . $context . '_' . md5( wp_json_encode( $args ) );
	}

	/**
	 * دریافت مقدار از کش
	 *
	 * @param string $key کلید.
	 * @return mixed|false
	 */
	public static function get( string $key ) {
		return get_transient( $key );
	}

	/**
	 * ذخیره در کش
	 *
	 * @param string $key کلید.
	 * @param mixed  $value مقدار.
	 * @return bool
	 */
	public static function set( string $key, $value ): bool {
		return (bool) set_transient( $key, $value, self::EXPIRY );
	}

	/**
	 * کلیدهای شناخته‌شدهٔ کش (برای پاک‌سازی دقیق در کش شیء خارجی)
	 *
	 * @return array<int, string>
	 */
	private static function known_keys(): array {
		$keys = array();

		if ( class_exists( 'ZSA_PM_Data' ) ) {
			$keys[] = ZSA_PM_Data::KEY;
		}

		// کلید نسخه‌های قبلی هم پاک شود
		$keys[] = 'zsa_pm_dataset_v2';
		$keys[] = 'zsa_pm_dataset_v1';

		return array_values( array_unique( $keys ) );
	}

	/**
	 * حذف تمام ترانزینت‌های افزونه و کش‌های افزونه‌های کش
	 */
	public static function flush_all(): void {
		global $wpdb;

		// حذف ترانزینت‌های افزونه
		$like   = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$like_t = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_t ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// کلیدهای شناخته‌شده را مستقیم حذف کن تا کش شیء خارجی (Redis/Memcached) هم پاک شود
		foreach ( self::known_keys() as $k ) {
			delete_transient( $k );
		}

		// پاک‌کردن کش درون‌درخواستی مجموعه‌داده
		if ( class_exists( 'ZSA_PM_Data' ) ) {
			ZSA_PM_Data::reset();
		}

		// سازگاری با WP Fastest Cache
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		} elseif ( function_exists( 'wpfc_clear_all_cache_once' ) ) {
			wpfc_clear_all_cache_once();
		}

		// سازگاری با LiteSpeed Cache
		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			\LiteSpeed\Purge::purge_all();
		} elseif ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		// هوک عمومی برای سایر افزونه‌های کش
		do_action( 'zsa_pm_cache_flushed' );
	}
}