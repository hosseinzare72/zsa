<?php
/**
 * قالب کارت پروژه‌های در دست توسعه
 *
 * @package ZSA_Portfolio_Manager
 *
 * @var array $zsa_item آیتم از ZSA_PM_Data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$it = $zsa_item;
?>
<article class="zsa-pm-soon-card zsa-pm-anim">
	<div class="zsa-pm-soon-icon" aria-hidden="true"><?php echo esc_html( '' !== $it['icon'] ? $it['icon'] : '🚀' ); ?></div>
	<h3><?php echo esc_html( $it['title'] ); ?></h3>
	<?php if ( '' !== $it['desc'] ) : ?>
		<p><?php echo esc_html( $it['desc'] ); ?></p>
	<?php endif; ?>
	<span class="zsa-pm-soon-tag"><?php esc_html_e( 'در توسعه', 'zsa-pm' ); ?></span>
</article>
