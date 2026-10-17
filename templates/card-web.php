<?php
/**
 * قالب کارت موکاپ مرورگری وب‌سایت
 *
 * @package ZSA_Portfolio_Manager
 *
 * @var array $zsa_item  آیتم از ZSA_PM_Data.
 * @var int   $zsa_index ایندکس کارت در گرید (برای گرادیان پیش‌فرض).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$it       = $zsa_item;
$zsa_index = isset( $zsa_index ) ? (int) $zsa_index : 0;
$url      = ! empty( $it['links'][0]['url'] ) ? (string) $it['links'][0]['url'] : '';
$host     = $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : '';
$domain   = '' !== $host ? $host : $it['title'];
$grad     = preg_match( '/^g[1-8]$/', $it['grad_class'] ) ? $it['grad_class'] : 'g' . ( ( $zsa_index % 8 ) + 1 );
?>
<article class="zsa-pm-web-card zsa-pm-anim">
	<div class="zsa-pm-web-bar">
		<i class="d1"></i><i class="d2"></i><i class="d3"></i>
		<span dir="ltr"><?php echo esc_html( $domain ); ?></span>
	</div>
	<div class="zsa-pm-web-shot <?php echo esc_attr( $grad ); ?>">
		<?php if ( '' !== $it['icon'] ) : ?>
			<em aria-hidden="true"><?php echo esc_html( $it['icon'] ); ?></em>
		<?php endif; ?>
		<h3><?php echo esc_html( $it['title'] ); ?></h3>
	</div>
	<div class="zsa-pm-web-foot">
		<span class="zsa-pm-web-tag"><?php echo esc_html( $it['desc'] ); ?></span>
		<?php if ( $url ) : ?>
			<a class="zsa-pm-web-link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: site name */ __( 'مشاهده وب‌سایت %s', 'zsa-pm' ), $it['title'] ) ); ?>"><?php esc_html_e( 'مشاهده ←', 'zsa-pm' ); ?></a>
		<?php endif; ?>
	</div>
</article>
