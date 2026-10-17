<?php
/**
 * کلاس شورت‌کدهای فرانت‌اند
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * شورت‌کدها: [zsa_portfolio] [zsa_projects] [zsa_web_portfolio] [zsa_coming_soon] [zsa_stats] [zsa_credit]
 * به‌همراه بارگذاری شرطی CSS/JS
 */
final class ZSA_PM_Shortcodes {

	/**
	 * نام‌های تمام شورت‌کدهای افزونه
	 *
	 * @var array
	 */
	private static $tags = array( 'zsa_portfolio', 'zsa_projects', 'zsa_web_portfolio', 'zsa_coming_soon', 'zsa_stats', 'zsa_credit' );

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Shortcodes|null
	 */
	private static ?ZSA_PM_Shortcodes $instance = null;

	/**
	 * آیا فایل‌ها در این درخواست ثبت شده‌اند؟
	 *
	 * @var bool
	 */
	private static bool $assets_done = false;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Shortcodes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_shortcode( 'zsa_portfolio', array( $this, 'portfolio' ) );
		add_shortcode( 'zsa_projects', array( $this, 'projects' ) );
		add_shortcode( 'zsa_web_portfolio', array( $this, 'web_portfolio' ) );
		add_shortcode( 'zsa_coming_soon', array( $this, 'coming_soon' ) );
		add_shortcode( 'zsa_stats', array( $this, 'stats' ) );
		add_shortcode( 'zsa_credit', array( $this, 'credit' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_inline_vars' ), 5 );

		// شورت‌کدهای داخل ویجت متنی هم باید اجرا شوند
		add_filter( 'widget_text', 'do_shortcode' );
		add_filter( 'widget_text_content', 'do_shortcode' );
	}

	/* ===== ابزارها ===== */

	/**
	 * اسلاگ‌های ورودی را به آرایهٔ تمیز تبدیل می‌کند
	 *
	 * @param string $value رشتهٔ جداشده با کاما.
	 * @return array
	 */
	private function slugs( string $value ): array {
		$parts = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $value ) ) ) );
		return array_values( $parts );
	}

	/**
	 * پوستهٔ رنگی (تیره/روشن)
	 *
	 * @return string
	 */
	public static function theme(): string {
		return 'light' === ZSA_PM_Settings::get( 'full_theme', 'dark' ) ? 'light' : 'dark';
	}

	/**
	 * کلاس‌های ریشهٔ گرید (افکت hover + پوسته)
	 *
	 * @param string $base کلاس پایه.
	 * @return string
	 */
	private function grid_class( string $base ): string {
		$classes = array( $base, 'zsa-pm-theme-' . self::theme() );

		if ( (int) ZSA_PM_Settings::get( 'hover', 1 ) ) {
			$classes[] = 'zsa-pm-hover';
		}

		return implode( ' ', $classes );
	}

	/**
	 * تیتر و زیرتیتر یک بخش
	 *
	 * @param string $id       شناسه برای لینک‌دهی.
	 * @param string $title    تیتر.
	 * @param string $subtitle زیرتیتر.
	 * @param string $tag      تگ هدینگ.
	 * @return string
	 */
	private function section_head( string $id, string $title, string $subtitle = '', string $tag = 'h2' ): string {
		if ( '' === $title ) {
			return '';
		}

		$tag = in_array( $tag, array( 'h1', 'h2', 'h3' ), true ) ? $tag : 'h2';

		$html  = '<header class="zsa-pm-section-head" id="' . esc_attr( $id ) . '">';
		$html .= '<' . $tag . ' class="zsa-pm-section-title">' . esc_html( $title ) . '</' . $tag . '>';

		if ( '' !== $subtitle ) {
			$html .= '<p class="zsa-pm-section-sub">' . esc_html( $subtitle ) . '</p>';
		}

		$html .= '</header>';

		return $html;
	}

	/* ===== شورت‌کد جامع ===== */

	/**
	 * شورت‌کد [zsa_portfolio] — کل صفحه با یک شورت‌کد
	 *
	 * ویژگی‌ها:
	 *  sections    فهرست بخش‌ها با کاما: hero,stats,projects,web,soon,cta (پیش‌فرض: همه)
	 *  type        فیلتر نوع پروژه‌ها (app,game,...)
	 *  limit       تعداد (۰ = همه)
	 *  columns     ستون‌های بخش پروژه‌ها (۰ = طبق تنظیمات)
	 *  web_columns ستون‌های بخش وب‌سایت‌ها
	 *  fullpage    1 = حذف هدر/فوتر/سایدبار قالب با CSS
	 *
	 * @param array|string $atts ویژگی‌ها.
	 * @return string
	 */
	public function portfolio( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sections'    => 'hero,stats,projects,web,soon,cta',
				'type'        => '',
				'limit'       => 0,
				'columns'     => 0,
				'web_columns' => 0,
				'fullpage'    => 0,
			),
			(array) $atts,
			'zsa_portfolio'
		);

		$this->enqueue_assets();

		$sections = $this->slugs( (string) $atts['sections'] );
		if ( empty( $sections ) ) {
			$sections = array( 'hero', 'stats', 'projects', 'web', 'soon', 'cta' );
		}

		$fullpage = ! empty( $atts['fullpage'] ) && '0' !== (string) $atts['fullpage'];
		$classes  = array( 'zsa-pm-page', 'zsa-pm-theme-' . self::theme() );
		if ( $fullpage ) {
			$classes[] = 'zsa-pm-page--full';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" dir="rtl">
			<?php if ( in_array( 'hero', $sections, true ) ) : ?>
				<?php echo $this->hero(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<?php if ( in_array( 'stats', $sections, true ) ) : ?>
				<section class="zsa-pm-block" id="zsa-stats">
					<?php echo $this->stats( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endif; ?>

			<?php if ( in_array( 'projects', $sections, true ) ) : ?>
				<section class="zsa-pm-block" id="zsa-projects">
					<?php
					echo $this->section_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'zsa-projects-title',
						(string) ZSA_PM_Settings::get( 'projects_title', __( 'پروژه‌های فعال و منتشرشده', 'zsa-pm' ) ),
						(string) ZSA_PM_Settings::get( 'projects_sub', '' )
					);
					echo $this->projects( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'type'    => (string) $atts['type'],
							'limit'   => (int) $atts['limit'],
							'columns' => (int) $atts['columns'],
						)
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( in_array( 'web', $sections, true ) ) : ?>
				<section class="zsa-pm-block" id="zsa-web">
					<?php
					echo $this->section_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'zsa-web-title',
						(string) ZSA_PM_Settings::get( 'web_title', __( 'نمونه‌کارهای طراحی وب‌سایت', 'zsa-pm' ) ),
						(string) ZSA_PM_Settings::get( 'web_sub', '' )
					);
					echo $this->web_portfolio( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'limit'   => (int) $atts['limit'],
							'columns' => (int) $atts['web_columns'] > 0 ? (int) $atts['web_columns'] : 4,
						)
					);
					?>
				</section>
			<?php endif; ?>

			<?php if ( in_array( 'soon', $sections, true ) ) : ?>
				<section class="zsa-pm-block" id="zsa-soon">
					<?php
					echo $this->section_head( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'zsa-soon-title',
						(string) ZSA_PM_Settings::get( 'soon_title', __( 'پروژه‌های در دست توسعه', 'zsa-pm' ) ),
						(string) ZSA_PM_Settings::get( 'soon_sub', '' )
					);
					echo $this->coming_soon( array( 'limit' => (int) $atts['limit'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</section>
			<?php endif; ?>

			<?php if ( in_array( 'cta', $sections, true ) ) : ?>
				<?php echo $this->cta(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * بخش هیرو
	 *
	 * @return string
	 */
	private function hero(): string {
		$title   = (string) ZSA_PM_Settings::get( 'hero_title', __( 'استودیو ZSA — خالق تجربه‌های دیجیتال', 'zsa-pm' ) );
		$sub     = (string) ZSA_PM_Settings::get( 'hero_sub', __( 'طراحی و توسعه بازی، اپلیکیشن و وب‌سایت‌های حرفه‌ای', 'zsa-pm' ) );
		$b1_text = (string) ZSA_PM_Settings::get( 'hero_btn1_text', __( 'مشاهده پروژه‌ها', 'zsa-pm' ) );
		$b1_url  = (string) ZSA_PM_Settings::get( 'hero_btn1_url', '#zsa-projects' );
		$b2_text = (string) ZSA_PM_Settings::get( 'hero_btn2_text', __( 'تماس با من', 'zsa-pm' ) );
		$b2_url  = (string) ZSA_PM_Settings::get( 'hero_btn2_url', 'tel:' . ZSA_PM_Settings::phone_e164() );

		ob_start();
		?>
		<header class="zsa-pm-hero zsa-pm-anim">
			<div class="zsa-pm-hero-inner">
				<p class="zsa-pm-hero-eyebrow"><?php echo esc_html( (string) ZSA_PM_Settings::get( 'hero_eyebrow', 'ZSA Studio' ) ); ?></p>
				<h1 class="zsa-pm-hero-title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $sub ) : ?>
					<p class="zsa-pm-hero-sub"><?php echo esc_html( $sub ); ?></p>
				<?php endif; ?>
				<div class="zsa-pm-hero-actions">
					<?php if ( '' !== $b1_text ) : ?>
						<a class="zsa-pm-btn zsa-pm-btn--primary" href="<?php echo esc_url( $b1_url ); ?>"><?php echo esc_html( $b1_text ); ?></a>
					<?php endif; ?>
					<?php if ( '' !== $b2_text ) : ?>
						<a class="zsa-pm-btn zsa-pm-btn--ghost" href="<?php echo esc_url( $b2_url ); ?>"><?php echo esc_html( $b2_text ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * بخش دعوت به اقدام (CTA) پایانی
	 *
	 * @return string
	 */
	private function cta(): string {
		$title = (string) ZSA_PM_Settings::get( 'cta_title', __( 'آماده شروع یک پروژه جدید هستید؟', 'zsa-pm' ) );
		$text  = (string) ZSA_PM_Settings::get( 'cta_text', __( 'از ایده تا انتشار، کنار شما هستم.', 'zsa-pm' ) );
		$phone = (string) ZSA_PM_Settings::get( 'phone', '09351063668' );

		ob_start();
		?>
		<section class="zsa-pm-cta zsa-pm-anim" id="zsa-cta">
			<h2><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== $text ) : ?>
				<p><?php echo esc_html( $text ); ?></p>
			<?php endif; ?>
			<a class="zsa-pm-btn zsa-pm-btn--primary" href="<?php echo esc_url( 'tel:' . ZSA_PM_Settings::phone_e164() ); ?>">
				<?php echo esc_html( ZSA_PM_Data::fa( $phone ) ); ?>
			</a>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/* ===== شورت‌کدهای جزئی ===== */

	/**
	 * شورت‌کد [zsa_projects] — کارت‌های پروژه‌های منتشرشدهٔ غیر وب‌سایتی
	 *
	 * @param array|string $atts ویژگی‌ها: type, limit, columns.
	 * @return string
	 */
	public function projects( $atts ): string {
		$atts = shortcode_atts(
			array(
				'type'    => '',
				'limit'   => 0,
				'columns' => 0,
			),
			(array) $atts,
			'zsa_projects'
		);

		$this->enqueue_assets();

		$columns = (int) $atts['columns'] > 0 ? (int) $atts['columns'] : (int) ZSA_PM_Settings::get( 'columns', 2 );
		$columns = max( 1, min( 4, $columns ) );
		$gap     = max( 0, min( 80, (int) ZSA_PM_Settings::get( 'gap', 28 ) ) );

		$items = ZSA_PM_Data::find(
			array(
				'status'        => 'published',
				'types'         => $this->slugs( (string) $atts['type'] ),
				'exclude_types' => array( 'website' ),
				'limit'         => (int) $atts['limit'],
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $this->grid_class( 'zsa-pm-projects-grid' ) ); ?>" style="--zsa-pm-cols:<?php echo (int) $columns; ?>;--zsa-pm-gap:<?php echo (int) $gap; ?>px">
			<?php
			foreach ( $items as $zsa_item ) {
				include ZSA_PM_PATH . 'templates/card-project.php';
			}
			?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد [zsa_web_portfolio] — موکاپ مرورگری وب‌سایت‌ها
	 *
	 * @param array|string $atts ویژگی‌ها: limit, columns.
	 * @return string
	 */
	public function web_portfolio( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit'   => 0,
				'columns' => 4,
			),
			(array) $atts,
			'zsa_web_portfolio'
		);

		$this->enqueue_assets();

		$columns = (int) $atts['columns'] > 0 ? max( 1, min( 4, (int) $atts['columns'] ) ) : 4;
		$items   = ZSA_PM_Data::find(
			array(
				'status' => 'published',
				'types'  => array( 'website' ),
				'limit'  => (int) $atts['limit'],
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $this->grid_class( 'zsa-pm-web-grid' ) ); ?>" style="--zsa-pm-cols:<?php echo (int) $columns; ?>">
			<?php
			$zsa_index = 0;
			foreach ( $items as $zsa_item ) {
				include ZSA_PM_PATH . 'templates/card-web.php';
				++$zsa_index;
			}
			?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد [zsa_coming_soon] — کارت‌های «در دست توسعه»
	 *
	 * @param array|string $atts ویژگی‌ها: limit.
	 * @return string
	 */
	public function coming_soon( $atts ): string {
		$atts = shortcode_atts(
			array(
				'limit' => 0,
			),
			(array) $atts,
			'zsa_coming_soon'
		);

		$this->enqueue_assets();

		$items = ZSA_PM_Data::find(
			array(
				'status' => 'coming-soon',
				'limit'  => (int) $atts['limit'],
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $this->grid_class( 'zsa-pm-soon-grid' ) ); ?>">
			<?php
			foreach ( $items as $zsa_item ) {
				include ZSA_PM_PATH . 'templates/card-coming-soon.php';
			}
			?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد [zsa_stats] — چهار شمارندهٔ متحرک با اعداد فارسی
	 *
	 * عدد نهایی در HTML نوشته می‌شود تا بدون جاوااسکریپت هم درست دیده شود؛
	 * جاوااسکریپت فقط انیمیشن شمارش را اجرا می‌کند.
	 *
	 * @param array|string $atts بدون ویژگی.
	 * @return string
	 */
	public function stats( $atts ): string {
		unset( $atts );
		$this->enqueue_assets();

		$counts  = ZSA_PM_Data::counts();
		$quality = max( 0, min( 100, (int) ZSA_PM_Settings::get( 'quality', 100 ) ) );
		$rows    = array(
			array( $quality, '٪', __( 'تعهد به کیفیت', 'zsa-pm' ) ),
			array( $counts['web'], '', __( 'پروژه وب فعال', 'zsa-pm' ) ),
			array( $counts['published'], '', __( 'محصول منتشرشده', 'zsa-pm' ) ),
			array( $counts['soon'], '', __( 'پروژه در راه', 'zsa-pm' ) ),
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( $this->grid_class( 'zsa-pm-stats' ) ); ?>" dir="rtl">
			<div class="zsa-pm-stats-grid">
				<?php foreach ( $rows as $row ) : ?>
					<div class="zsa-pm-stat zsa-pm-anim">
						<strong data-count="<?php echo (int) $row[0]; ?>" data-suffix="<?php echo esc_attr( $row[1] ); ?>"><?php echo esc_html( ZSA_PM_Data::fa( (int) $row[0] ) . $row[1] ); ?></strong>
						<span><?php echo esc_html( $row[2] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * شورت‌کد [zsa_credit] — کردیت «حسین زارع | شماره تماس» با لینک tel:
	 *
	 * @return string
	 */
	public function credit(): string {
		$this->enqueue_assets();
		ZSA_PM_Seo::$credit_printed = true;
		return ZSA_PM_Seo::credit_html();
	}

	/* ===== بارگذاری شرطی فایل‌ها ===== */

	/**
	 * آیا صفحهٔ جاری شامل شورت‌کد/بلوک ZSA یا صفحهٔ تکی پروژه است؟
	 *
	 * هوک wp_enqueue_scripts پیش از پردازش محتوای برگه اجرا می‌شود؛
	 * بنابراین محتوای خام برگه مستقیم اسکن می‌شود. برای صفحه‌سازهایی که
	 * شورت‌کد را در post_content نمی‌گذارند، از تنظیمات «آدرس صفحهٔ
	 * پورتفولیو» و فیلتر zsa_pm_is_zsa_page کمک می‌گیریم.
	 *
	 * @return bool
	 */
	public static function is_zsa_page(): bool {
		static $result = null;

		if ( null !== $result ) {
			return $result;
		}

		$result = false;
		$post   = get_post();

		if ( is_singular( ZSA_PM_CPT::POST_TYPE ) ) {
			$result = true;
			return self::finish_check( $result, $post );
		}

		if ( ! is_singular() || ! $post ) {
			return self::finish_check( $result, $post );
		}

		$content = (string) $post->post_content;

		foreach ( self::$tags as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				$result = true;
				return self::finish_check( $result, $post );
			}
		}

		if ( function_exists( 'has_block' ) && has_block( 'zsa-pm/portfolio', $post ) ) {
			$result = true;
			return self::finish_check( $result, $post );
		}

		// برگه‌ای که در تنظیمات به‌عنوان «آدرس صفحهٔ پورتفولیو» ثبت شده است
		$portfolio = trim( (string) ZSA_PM_Settings::get( 'portfolio_url', '' ), '/' );
		if ( '' !== $portfolio ) {
			$current = trim( (string) get_permalink( $post ), '/' );
			if ( '' !== $current && 0 === strcasecmp( $current, $portfolio ) ) {
				$result = true;
				return self::finish_check( $result, $post );
			}
		}

		return self::finish_check( $result, $post );
	}

	/**
	 * اعمال فیلتر نهایی روی نتیجهٔ تشخیص صفحه
	 *
	 * @param bool         $result نتیجه.
	 * @param WP_Post|null $post   نوشتهٔ جاری.
	 * @return bool
	 */
	private static function finish_check( bool $result, $post ): bool {
		if ( $post instanceof WP_Post ) {
			$result = (bool) apply_filters( 'zsa_pm_is_zsa_page', $result, $post );
		}
		return $result;
	}

	/**
	 * آیا صفحه شامل بخش‌های پورتفولیو (نه صرفاً صفحهٔ تکی پروژه) است؟
	 *
	 * @return bool
	 */
	public static function is_portfolio_page(): bool {
		return self::is_zsa_page() && ! is_singular( ZSA_PM_CPT::POST_TYPE );
	}

	/**
	 * بارگذاری شرطی CSS و JS
	 */
	public function maybe_enqueue(): void {
		if ( self::is_zsa_page() || (int) ZSA_PM_Settings::get( 'force_assets', 0 ) ) {
			$this->enqueue_assets();
		}
	}

	/**
	 * ثبت و بارگذاری CSS/JS (یک‌بار)؛ اگر شورت‌کد در ویجت یا قالب بود، همین‌جا بارگذاری می‌شود
	 */
	public function enqueue_assets(): void {
		if ( self::$assets_done ) {
			return;
		}

		self::$assets_done = true;

		if ( ! wp_style_is( 'zsa-pm-public', 'registered' ) ) {
			wp_register_style( 'zsa-pm-public', ZSA_PM_URL . 'public/css/public.css', array(), ZSA_PM_VERSION );
		}

		wp_enqueue_style( 'zsa-pm-public' );

		$vars = sprintf(
			':root{--zsa-pm-primary:%1$s;--zsa-pm-accent:%2$s;--zsa-pm-web:%3$s;}',
			sanitize_hex_color( (string) ZSA_PM_Settings::get( 'color_primary', '#3b82f6' ) ) ?: '#3b82f6',
			sanitize_hex_color( (string) ZSA_PM_Settings::get( 'color_accent', '#f59e0b' ) ) ?: '#f59e0b',
			sanitize_hex_color( (string) ZSA_PM_Settings::get( 'color_web', '#10b981' ) ) ?: '#10b981'
		);
		wp_add_inline_style( 'zsa-pm-public', $vars );

		if ( (int) ZSA_PM_Settings::get( 'animations', 1 ) ) {
			wp_enqueue_script( 'zsa-pm-public', ZSA_PM_URL . 'public/js/public.js', array(), ZSA_PM_VERSION, true );
		}
	}

	/**
	 * چاپ تنظیمات موردنیاز جاوااسکریپت
	 */
	public function print_inline_vars(): void {
		if ( ! self::$assets_done ) {
			return;
		}

		$animations = wp_script_is( 'zsa-pm-public', 'enqueued' ) || wp_script_is( 'zsa-pm-public', 'done' ) ? 1 : 0;
		echo '<script>window.zsaPM=window.zsaPM||{};window.zsaPM.animations=' . (int) $animations . ';window.zsaPM.theme=' . wp_json_encode( self::theme() ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
