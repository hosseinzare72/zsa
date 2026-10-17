<?php
/**
 * قالب کارت پروژه (اپلیکیشن، بازی، اکوسیستم)
 *
 * @package ZSA_Portfolio_Manager
 *
 * @var array $zsa_item آیتم از ZSA_PM_Data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$it       = $zsa_item;
$img      = $it['img'];
$use_grad = ( 'image' !== $it['mode'] ) || empty( $img );
$box      = $use_grad ? 'background:linear-gradient(135deg,' . esc_attr( $it['grad_from'] ) . ',' . esc_attr( $it['grad_to'] ) . ')' : '';
$home     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
?>
<article class="zsa-pm-project-card zsa-pm-anim">
	<div class="zsa-pm-project-img" <?php echo '' !== $box ? 'style="' . esc_attr( $box ) . '"' : ''; ?>>
		<?php if ( $img && ( 'icon' !== $it['mode'] ) ) : ?>
			<img
				class="<?php echo esc_attr( $use_grad ? 'zsa-pm-img--logo' : 'zsa-pm-img--' . $it['fit'] ); ?>"
				src="<?php echo esc_url( $img['url'] ); ?>"
				alt="<?php echo esc_attr( $img['alt'] ); ?>"
				width="<?php echo (int) $img['w']; ?>"
				height="<?php echo (int) $img['h']; ?>"
				<?php if ( ! empty( $img['srcset'] ) ) : ?>
					srcset="<?php echo esc_attr( $img['srcset'] ); ?>"
				<?php endif; ?>
				<?php if ( ! empty( $img['sizes'] ) ) : ?>
					sizes="<?php echo esc_attr( $img['sizes'] ); ?>"
				<?php endif; ?>
				loading="lazy"
				decoding="async">
		<?php elseif ( '' !== $it['icon'] ) : ?>
			<span class="zsa-pm-project-icon" aria-hidden="true"><?php echo esc_html( $it['icon'] ); ?></span>
		<?php endif; ?>

		<?php if ( '' !== $it['badge'] ) : ?>
			<span class="zsa-pm-project-badge" style="background:<?php echo esc_attr( $it['badge_color'] ); ?>"><?php echo esc_html( $it['badge'] ); ?></span>
		<?php endif; ?>
	</div>

	<div class="zsa-pm-project-body">
		<h3><a href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['title'] ); ?></a></h3>
		<?php if ( '' !== $it['desc'] ) : ?>
			<p><?php echo esc_html( $it['desc'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $it['links'] ) ) : ?>
			<div class="zsa-pm-project-links">
				<?php
				foreach ( $it['links'] as $link ) :
					$host     = (string) wp_parse_url( $link['url'], PHP_URL_HOST );
					$external = '' !== $host && $host !== $home;
					?>
					<a href="<?php echo esc_url( $link['url'] ); ?>"
						<?php echo '_blank' === $link['target'] ? 'target="_blank"' : ''; ?>
						<?php echo ( $external || '_blank' === $link['target'] ) ? 'rel="noopener noreferrer"' : ''; ?>
						class="zsa-pm-project-link zsa-pm-project-link--<?php echo esc_attr( $link['style'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
