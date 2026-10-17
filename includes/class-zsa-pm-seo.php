<?php
/**
 * سئوی صفحه‌ای: صفحهٔ تکی پروژه، متا، Open Graph، robots، sitemap، کردیت
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ماژول سئو — سبک و بدون وابستگی؛ اگر افزونهٔ سئو فعال باشد متاتگ‌ها را تکرار نمی‌کند
 */
final class ZSA_PM_Seo {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Seo|null
	 */
	private static ?ZSA_PM_Seo $instance = null;

	/**
	 * آیا کردیت یک‌بار چاپ شده است؟ (جلوگیری از تکرار)
	 *
	 * @var bool
	 */
	public static bool $credit_printed = false;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Seo {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_filter( 'the_content', array( $this, 'single_content' ), 20 );
		add_action( 'wp_head', array( $this, 'head_meta' ), 5 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_posts' ), 10, 2 );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ) );
		add_action( 'wp_footer', array( $this, 'footer_credit' ), 20 );
	}

	/**
	 * آیا افزونهٔ سئوی دیگری فعال است؟
	 *
	 * @return bool
	 */
	private function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * کردیت اجباری: «حسین زارع | شماره» با لینک tel:
	 *
	 * @return string
	 */
	public static function credit_html(): string {
		$phone = (string) ZSA_PM_Settings::get( 'phone', '09351063668' );

		return '<p class="zsa-pm-credit" dir="rtl">' . esc_html__( 'طراحی و توسعه:', 'zsa-pm' ) . ' <a href="' . esc_url( 'tel:' . ZSA_PM_Settings::phone_e164() ) . '">' . esc_html( 'حسین زارع | ' . $phone ) . '</a></p>';
	}

	/**
	 * چاپ خودکار کردیت در پایین صفحه‌های ZSA
	 */
	public function footer_credit(): void {
		if ( self::$credit_printed || ! (int) ZSA_PM_Settings::get( 'credit_auto', 1 ) || ! ZSA_PM_Shortcodes::is_zsa_page() ) {
			return;
		}
		self::$credit_printed = true;
		echo self::credit_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * محتوای غنی صفحهٔ تکی پروژه: مسیر صفحه، توضیح، پلتفرم‌ها، دکمه‌ها، پروژه‌های مرتبط
	 *
	 * @param string $content محتوای ویرایشگر.
	 * @return string
	 */
	public function single_content( string $content ): string {
		if ( ! is_singular( ZSA_PM_CPT::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$it = ZSA_PM_Data::get_item( (int) get_the_ID() );
		if ( ! $it ) {
			return $content;
		}

		// تصویر شاخص در صفحهٔ تکی (نسخهٔ قبلی اصلاً نمایش داده نمی‌شد)
		$thumb = '';
		if ( has_post_thumbnail( (int) get_the_ID() ) ) {
			$thumb = get_the_post_thumbnail(
				(int) get_the_ID(),
				'large',
				array(
					'class'   => 'zsa-pm-single-thumb',
					'loading' => 'eager',
				)
			);
		}

		( ZSA_PM_Shortcodes::instance() )->enqueue_assets();

		$labels = array(
			'android' => 'Android',
			'ios'     => 'iOS',
			'web'     => 'Web (PWA)',
			'pwa'     => 'Web (PWA)',
			'windows' => 'Windows',
		);

		$portfolio = ZSA_PM_Settings::portfolio_url();
		$home      = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		ob_start();
		?>
		<div class="zsa-pm-single" dir="rtl">
			<nav class="zsa-pm-crumbs" aria-label="<?php esc_attr_e( 'مسیر صفحه', 'zsa-pm' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'خانه', 'zsa-pm' ); ?></a>
				<span aria-hidden="true">‹</span>
				<a href="<?php echo esc_url( $portfolio ); ?>">ZSA Studio</a>
				<span aria-hidden="true">‹</span>
				<span aria-current="page"><?php echo esc_html( $it['title'] ); ?></span>
			</nav>

			<?php if ( '' !== $thumb ) : ?>
				<figure class="zsa-pm-single-figure"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
			<?php endif; ?>

			<?php if ( '' !== $it['desc'] ) : ?>
				<p class="zsa-pm-single-lead"><?php echo esc_html( $it['desc'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $it['platforms'] ) ) : ?>
				<ul class="zsa-pm-platforms" aria-label="<?php esc_attr_e( 'پلتفرم‌ها', 'zsa-pm' ); ?>">
					<?php
					foreach ( $it['platforms'] as $p ) :
						$key = sanitize_key( (string) $p );
						if ( isset( $labels[ $key ] ) ) :
							?>
							<li><?php echo esc_html( $labels[ $key ] ); ?></li>
							<?php
						endif;
					endforeach;
					?>
				</ul>
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

			<div class="zsa-pm-single-content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php
			// پروژه‌های مرتبط (لینک‌سازی داخلی): حداکثر ۴ پروژهٔ منتشرشدهٔ دیگر غیر وب‌سایت
			$related = array_filter(
				ZSA_PM_Data::find(
					array(
						'status'        => 'published',
						'exclude_types' => array( 'website' ),
					)
				),
				static function ( array $r ) use ( $it ): bool {
					return $r['id'] !== $it['id'];
				}
			);
			$related = array_slice( $related, 0, 4 );
			if ( ! empty( $related ) ) :
				?>
				<aside class="zsa-pm-related">
					<h2><?php esc_html_e( 'سایر پروژه‌های ZSA Studio', 'zsa-pm' ); ?></h2>
					<ul>
						<?php foreach ( $related as $r ) : ?>
							<li><a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
					<p><a href="<?php echo esc_url( $portfolio ); ?>"><?php esc_html_e( '← بازگشت به همهٔ پروژه‌ها', 'zsa-pm' ); ?></a></p>
				</aside>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * متا دیسکریپشن، Open Graph و کردیت (متادیتا) — فقط صفحه‌های ZSA
	 */
	public function head_meta(): void {
		if ( ! ZSA_PM_Shortcodes::is_zsa_page() ) {
			return;
		}

		echo '<meta name="author" content="' . esc_attr( 'حسین زارع' ) . '">' . "\n";

		if ( $this->seo_plugin_active() || ! is_singular( ZSA_PM_CPT::POST_TYPE ) ) {
			return;
		}

		$it = ZSA_PM_Data::get_item( (int) get_the_ID() );
		if ( ! $it ) {
			return;
		}

		$desc = wp_html_excerpt( $it['desc'], 160, '…' );
		$tags = array(
			'description'    => array( 'name', $desc ),
			'og:type'        => array( 'property', 'website' ),
			'og:locale'      => array( 'property', 'fa_IR' ),
			'og:title'       => array( 'property', $it['title'] ),
			'og:description' => array( 'property', $desc ),
			'og:url'         => array( 'property', $it['url'] ),
			'twitter:card'   => array( 'name', empty( $it['img'] ) ? 'summary' : 'summary_large_image' ),
		);
		if ( ! empty( $it['img']['url'] ) ) {
			$tags['og:image'] = array( 'property', $it['img']['url'] );
		}

		foreach ( $tags as $key => $row ) {
			if ( '' === $row[1] ) {
				continue;
			}
			printf( '<meta %1$s="%2$s" content="%3$s">' . "\n", esc_attr( $row[0] ), esc_attr( $key ), esc_attr( $row[1] ) );
		}
	}

	/**
	 * noindex برای صفحه‌های نازک: پروژه‌های وب‌سایت/در توسعه و بایگانی نوع پروژه
	 *
	 * @param array $robots مقادیر robots.
	 * @return array
	 */
	public function robots( array $robots ): array {
		$thin = false;

		if ( is_tax( ZSA_PM_Taxonomy::TAXONOMY ) ) {
			$thin = true;
		} elseif ( is_singular( ZSA_PM_CPT::POST_TYPE ) ) {
			$it = ZSA_PM_Data::get_item( (int) get_the_ID() );
			if ( $it && ( 'coming-soon' === $it['status'] || in_array( 'website', $it['types'], true ) ) ) {
				$thin = true;
			}
		}

		if ( $thin ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['index'] );
		}

		return $robots;
	}

	/**
	 * حذف پروژه‌های نازک از sitemap اصلی وردپرس (و افزونه‌هایی که از آن استفاده می‌کنند)
	 *
	 * @param array  $args      آرگومان‌های WP_Query.
	 * @param string $post_type نوع نوشته.
	 * @return array
	 */
	public function sitemap_posts( array $args, string $post_type ): array {
		if ( ZSA_PM_CPT::POST_TYPE !== $post_type ) {
			return $args;
		}

		$args['tax_query']  = array(
			array(
				'taxonomy' => ZSA_PM_Taxonomy::TAXONOMY,
				'field'    => 'slug',
				'terms'    => array( 'website' ),
				'operator' => 'NOT IN',
			),
		);
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'   => 'zsa_status',
				'value' => 'published',
			),
			array(
				'key'     => 'zsa_status',
				'compare' => 'NOT EXISTS',
			),
		);

		return $args;
	}

	/**
	 * حذف بایگانی «نوع پروژه» از sitemap
	 *
	 * @param array $taxonomies تاکسونومی‌ها.
	 * @return array
	 */
	public function sitemap_taxonomies( array $taxonomies ): array {
		unset( $taxonomies[ ZSA_PM_Taxonomy::TAXONOMY ] );
		return $taxonomies;
	}
}
