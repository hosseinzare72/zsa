<?php
/**
 * حذف کامل افزونه ZSA Portfolio Manager
 *
 * هنگام حذف افزونه از پیشخوان، تمام گزینه‌ها، ترانزینت‌ها و پست‌های سفارشی پاک می‌شوند.
 *
 * @package ZSA_Portfolio_Manager
 */

// اگر مستقیماً فراخوانی نشده، خروج
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// حذف تمام پست‌های نوع نوشته سفارشی
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		'zsa_project'
	)
);

if ( ! empty( $post_ids ) ) {
	foreach ( $post_ids as $pid ) {
		wp_delete_post( (int) $pid, true );
	}
}

// حذف تمام ترم‌های تاکسونومی
$terms = get_terms(
	array(
		'taxonomy'   => 'zsa_project_type',
		'hide_empty' => false,
	)
);

if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
	foreach ( $terms as $term ) {
		wp_delete_term( (int) $term->term_id, 'zsa_project_type' );
	}
}

// حذف گزینه‌های افزونه
delete_option( 'zsa_pm_settings' );
delete_option( 'zsa_pm_version' );

// پاک‌کردن متای قالبِ برگه‌هایی که قالب تمام‌صفحهٔ افزونه را داشتند
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_wp_page_template', 'zsa-pm-fullpage.php' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

// متاهای یتیمِ پروژه‌هایی که قبلاً حذف شده‌اند (دیگر پستی ندارند)
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL AND pm.meta_key LIKE %s",
		$wpdb->esc_like( 'zsa_' ) . '%'
	)
);

// حذف تمام ترانزینت‌های افزونه
$like   = $wpdb->esc_like( '_transient_zsa_pm_' ) . '%';
$like_t = $wpdb->esc_like( '_transient_timeout_zsa_pm_' ) . '%';

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_t ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// پاکسازی کش‌های افزونه‌های کش
if ( function_exists( 'wpfc_clear_all_cache' ) ) {
	wpfc_clear_all_cache();
}
if ( has_action( 'litespeed_purge_all' ) ) {
	do_action( 'litespeed_purge_all' );
}