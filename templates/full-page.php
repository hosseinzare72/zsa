<?php
/**
 * قالب تمام‌صفحهٔ افزونه ZSA Portfolio Manager
 *
 * این فایل فقط محتوای برگه را چاپ می‌کند: بدون هدر، فوتر، سایدبار،
 * دیدگاه‌ها، مسیر صفحه یا هر چیز دیگری از قالب سایت.
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme = 'light' === ZSA_PM_Settings::get( 'full_theme', 'dark' ) ? 'light' : 'dark';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="zsa-pm-html-<?php echo esc_attr( $theme ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'zsa-pm-body zsa-pm-theme-' . $theme ); ?>>
<?php wp_body_open(); ?>

<a class="zsa-pm-skip-link" href="#zsa-pm-main"><?php esc_html_e( 'پرش به محتوای اصلی', 'zsa-pm' ); ?></a>

<div id="zsa-pm-canvas" class="zsa-pm-canvas zsa-pm-theme-<?php echo esc_attr( $theme ); ?>">
	<main id="zsa-pm-main" class="zsa-pm-main" role="main">
		<?php
		while ( have_posts() ) {
			the_post();

			if ( post_password_required() ) {
				echo '<div class="zsa-pm-locked">' . get_the_password_form() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				continue;
			}

			the_content();
		}
		?>
	</main>
</div>

<?php wp_footer(); ?>
</body>
</html>
