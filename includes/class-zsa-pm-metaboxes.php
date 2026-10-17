<?php
/**
 * کلاس متاباکس‌های پروژه
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تعریف متاباکس‌های آکاردئونی پروژه با پیش‌نمایش زنده
 */
final class ZSA_PM_Metaboxes {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Metaboxes|null
	 */
	private static ?ZSA_PM_Metaboxes $instance = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Metaboxes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_' . ZSA_PM_CPT::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action('wp_ajax_zsa_pm_quick_edit', array( $this, 'ajax_quick_edit' ) );
	}

	/**
	 * ثبت متاباکس
	 */
	public function register(): void {
		add_meta_box(
			'zsa_pm_main',
			__( 'تنظیمات پروژه ZSA', 'zsa-pm' ),
			array( $this, 'render' ),
			ZSA_PM_CPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * بارگذاری فایل‌های مدیر
	 *
	 * @param string $hook هوک صفحه.
	 */
	public function enqueue( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ZSA_PM_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style(
			'zsa-pm-admin',
			ZSA_PM_URL . 'admin/css/admin.css',
			array(),
			ZSA_PM_VERSION
		);

		wp_enqueue_script(
			'zsa-pm-admin',
			ZSA_PM_URL . 'admin/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			ZSA_PM_VERSION,
			true
		);
	}

	/**
	 * رندر متاباکس آکاردئونی
	 *
	 * @param WP_Post $post پست جاری.
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( 'zsa_pm_save', 'zsa_pm_nonce' );

		$status      = (string) get_post_meta( $post->ID, 'zsa_status', true );
		$badge       = (string) get_post_meta( $post->ID, 'zsa_badge', true );
		$badge_color = (string) get_post_meta( $post->ID, 'zsa_badge_color', true );
		$short_desc  = (string) get_post_meta( $post->ID, 'zsa_short_desc', true );
		$order       = (int) get_post_meta( $post->ID, 'zsa_order', true );
		$links       = get_post_meta( $post->ID, 'zsa_links', true );
		$card_mode   = (string) get_post_meta( $post->ID, 'zsa_card_mode', true );
		$grad_from   = (string) get_post_meta( $post->ID, 'zsa_gradient_from', true );
		$grad_to     = (string) get_post_meta( $post->ID, 'zsa_gradient_to', true );
		$icon        = (string) get_post_meta( $post->ID, 'zsa_icon', true );
		$image_fit   = (string) get_post_meta( $post->ID, 'zsa_image_fit', true );
		$platforms   = get_post_meta( $post->ID, 'zsa_platforms', true );
		$grad_class  = (string) get_post_meta( $post->ID, 'zsa_gradient_class', true );
		$app_cat     = (string) get_post_meta( $post->ID, 'zsa_app_category', true );

		if ( ! is_array( $links ) ) {
			$links = array();
		}
		if ( ! is_array( $platforms ) ) {
			$platforms = array();
		}

		$defaults = array(
			'status'      => 'published',
			'badge'       => '',
			'badge_color' => '#f59e0b',
			'order'       => 10,
			'card_mode'   => 'image',
			'grad_from'   => '#4c1d95',
			'grad_to'     => '#8b5cf6',
			'image_fit'   => 'cover',
		);

		// فقط وقتی متا اصلاً وجود ندارد مقدار پیش‌فرض بگذار؛
		// در غیر این صورت مقدار ۰ یا رشتهٔ خالیِ کاربر بازنویسی می‌شد
		$status      = '' !== $status ? $status : $defaults['status'];
		$badge_color = '' !== $badge_color ? $badge_color : $defaults['badge_color'];
		$card_mode   = '' !== $card_mode ? $card_mode : $defaults['card_mode'];
		$grad_from   = '' !== $grad_from ? $grad_from : $defaults['grad_from'];
		$grad_to     = '' !== $grad_to ? $grad_to : $defaults['grad_to'];
		$image_fit   = '' !== $image_fit ? $image_fit : $defaults['image_fit'];

		if ( '' === (string) get_post_meta( $post->ID, 'zsa_order', true ) ) {
			$order = $defaults['order'];
		}

		$platform_options = array(
			'android' => 'Android',
			'ios'     => 'iOS',
			'pwa'     => 'Web PWA',
			'windows' => 'Windows',
		);

		?>
		<div id="zsa-pm-metabox" class="zsa-pm-acc" dir="rtl">
			<!-- پیش‌نمایش زنده -->
			<aside class="zsa-pm-preview">
				<h3><?php esc_html_e( 'پیش‌نمایش زنده', 'zsa-pm' ); ?></h3>
				<div class="zsa-pm-preview-card" data-preview>
					<div class="zsa-pm-preview-img" data-preview-img>
						<?php
						if ( has_post_thumbnail( $post ) ) {
							echo get_the_post_thumbnail( $post->ID, 'medium', array( 'style' => 'object-fit:' . esc_attr( $image_fit ) ) );
						}
						?>
					</div>
					<div class="zsa-pm-preview-body">
						<span class="zsa-pm-preview-badge" data-preview-badge style="background:<?php echo esc_attr( $badge_color ); ?>">
							<?php echo esc_html( $badge ?: 'بج' ); ?>
						</span>
						<h4 data-preview-title><?php echo esc_html( $post->post_title ?: 'عنوان پروژه' ); ?></h4>
						<p data-preview-desc><?php echo esc_html( $short_desc ?: mb_substr( wp_strip_all_tags( $post->post_content ), 0, 100 ) ); ?></p>
					</div>
				</div>
			</aside>

			<!-- پنل تنظیمات -->
			<div class="zsa-pm-panels">
				<!-- آیتم ۱: وضعیت و ترتیب -->
				<div class="zsa-pm-section open">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">⚙️ <?php esc_html_e( 'وضعیت و ترتیب', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">−</span>
					</header>
					<div class="zsa-pm-section-body">
						<div class="zsa-pm-grid">
							<div class="zsa-pm-field">
								<label for="zsa_status"><?php esc_html_e( 'وضعیت نمایش', 'zsa-pm' ); ?></label>
								<select name="zsa_status" id="zsa_status">
									<option value="published" <?php selected( $status, 'published' ); ?>><?php esc_html_e( 'منتشرشده', 'zsa-pm' ); ?></option>
									<option value="coming-soon" <?php selected( $status, 'coming-soon' ); ?>><?php esc_html_e( 'به‌زودی (در دست توسعه)', 'zsa-pm' ); ?></option>
								</select>
							</div>
							<div class="zsa-pm-field">
								<label for="zsa_order"><?php esc_html_e( 'ترتیب نمایش', 'zsa-pm' ); ?></label>
								<input type="number" name="zsa_order" id="zsa_order" value="<?php echo esc_attr( (string) $order ); ?>" min="0" max="9999">
								<small><?php esc_html_e( 'عدد کمتر = نمایش بالاتر', 'zsa-pm' ); ?></small>
							</div>
						</div>
					</div>
				</div>

				<!-- آیتم ۲: بج -->
				<div class="zsa-pm-section">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">🏷️ <?php esc_html_e( 'بج کارت', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">+</span>
					</header>
					<div class="zsa-pm-section-body">
						<div class="zsa-pm-grid">
							<div class="zsa-pm-field">
								<label for="zsa_badge"><?php esc_html_e( 'متن بج', 'zsa-pm' ); ?></label>
								<input type="text" name="zsa_badge" id="zsa_badge" value="<?php echo esc_attr( $badge ); ?>" maxlength="20" data-live="badge">
							</div>
							<div class="zsa-pm-field">
								<label for="zsa_badge_color"><?php esc_html_e( 'رنگ بج', 'zsa-pm' ); ?></label>
								<input type="text" name="zsa_badge_color" id="zsa_badge_color" value="<?php echo esc_attr( $badge_color ); ?>" class="zsa-pm-color" data-live="badge_color">
							</div>
						</div>
					</div>
				</div>

				<!-- آیتم ۳: توضیح کوتاه -->
				<div class="zsa-pm-section">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">📝 <?php esc_html_e( 'توضیح کوتاه', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">+</span>
					</header>
					<div class="zsa-pm-section-body">
						<div class="zsa-pm-field">
							<label for="zsa_short_desc">
								<?php esc_html_e( 'متن توضیح', 'zsa-pm' ); ?>
								<span class="zsa-pm-counter"><span data-counter><?php echo (int) mb_strlen( $short_desc ); ?></span>/۲۰۰</span>
							</label>
							<textarea name="zsa_short_desc" id="zsa_short_desc" rows="4" maxlength="200" data-live="desc"><?php echo esc_textarea( $short_desc ); ?></textarea>
						</div>
					</div>
				</div>

				<!-- آیتم ۴: لینک‌ها -->
				<div class="zsa-pm-section">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">🔗 <?php esc_html_e( 'لینک‌ها (حداکثر ۴)', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">+</span>
					</header>
					<div class="zsa-pm-section-body">
						<div id="zsa-pm-links" data-max="4">
							<?php
							for ( $i = 0; $i < 4; $i++ ) :
								$label   = isset( $links[ $i ]['label'] ) ? (string) $links[ $i ]['label'] : '';
								$url     = isset( $links[ $i ]['url'] ) ? (string) $links[ $i ]['url'] : '';
								$style   = isset( $links[ $i ]['style'] ) ? (string) $links[ $i ]['style'] : 'primary';
								$target  = isset( $links[ $i ]['target'] ) ? (string) $links[ $i ]['target'] : '_blank';
								?>
								<div class="zsa-pm-link-row" data-index="<?php echo (int) $i; ?>">
									<div class="zsa-pm-grid zsa-pm-grid--4">
										<input type="text" name="zsa_links[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'مثال: کافه‌بازار', 'zsa-pm' ); ?>">
										<input type="url" name="zsa_links[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://">
										<select name="zsa_links[<?php echo (int) $i; ?>][style]">
											<option value="primary" <?php selected( $style, 'primary' ); ?>><?php esc_html_e( 'اصلی (آبی)', 'zsa-pm' ); ?></option>
											<option value="secondary" <?php selected( $style, 'secondary' ); ?>><?php esc_html_e( 'ثانویه (شفاف)', 'zsa-pm' ); ?></option>
											<option value="tertiary" <?php selected( $style, 'tertiary' ); ?>><?php esc_html_e( 'سوم (سبز)', 'zsa-pm' ); ?></option>
										</select>
										<select name="zsa_links[<?php echo (int) $i; ?>][target]">
											<option value="_blank" <?php selected( $target, '_blank' ); ?>><?php esc_html_e( 'تب جدید', 'zsa-pm' ); ?></option>
											<option value="_self" <?php selected( $target, '_self' ); ?>><?php esc_html_e( 'همان تب', 'zsa-pm' ); ?></option>
										</select>
									</div>
								</div>
								<?php
							endfor;
							?>
						</div>
					</div>
				</div>

				<!-- آیتم ۵: حالت کارت -->
				<div class="zsa-pm-section">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">🎨 <?php esc_html_e( 'حالت کارت', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">+</span>
					</header>
					<div class="zsa-pm-section-body">
						<div class="zsa-pm-field">
							<label for="zsa_card_mode"><?php esc_html_e( 'نحوه نمایش تصویر', 'zsa-pm' ); ?></label>
							<select name="zsa_card_mode" id="zsa_card_mode">
								<option value="image" <?php selected( $card_mode, 'image' ); ?>><?php esc_html_e( 'تصویر شاخص', 'zsa-pm' ); ?></option>
								<option value="gradient" <?php selected( $card_mode, 'gradient' ); ?>><?php esc_html_e( 'گرادیان + تصویر', 'zsa-pm' ); ?></option>
								<option value="icon" <?php selected( $card_mode, 'icon' ); ?>><?php esc_html_e( 'ایموجی / آیکن', 'zsa-pm' ); ?></option>
							</select>
						</div>
						<div class="zsa-pm-grid">
							<div class="zsa-pm-field">
								<label for="zsa_gradient_from"><?php esc_html_e( 'گرادیان از', 'zsa-pm' ); ?></label>
								<input type="text" name="zsa_gradient_from" id="zsa_gradient_from" value="<?php echo esc_attr( $grad_from ); ?>" class="zsa-pm-color">
							</div>
							<div class="zsa-pm-field">
								<label for="zsa_gradient_to"><?php esc_html_e( 'گرادیان به', 'zsa-pm' ); ?></label>
								<input type="text" name="zsa_gradient_to" id="zsa_gradient_to" value="<?php echo esc_attr( $grad_to ); ?>" class="zsa-pm-color">
							</div>

							<div class="zsa-pm-field">
								<label for="zsa_gradient_class"><?php esc_html_e( 'گرادیان آماده (کارت وب‌سایت)', 'zsa-pm' ); ?></label>
								<select name="zsa_gradient_class" id="zsa_gradient_class">
									<option value=""><?php esc_html_e( 'خودکار (به‌ترتیب)', 'zsa-pm' ); ?></option>
									<?php for ( $g = 1; $g <= 8; $g++ ) : ?>
										<option value="g<?php echo (int) $g; ?>" <?php selected( $grad_class, 'g' . $g ); ?>>g<?php echo (int) $g; ?></option>
									<?php endfor; ?>
								</select>
							</div>
						</div>
						<div class="zsa-pm-grid">
							<div class="zsa-pm-field">
								<label for="zsa_icon"><?php esc_html_e( 'ایموجی / کاراکتر', 'zsa-pm' ); ?></label>
								<input type="text" name="zsa_icon" id="zsa_icon" value="<?php echo esc_attr( $icon ); ?>" maxlength="4" placeholder="☕">
							</div>
							<div class="zsa-pm-field">
								<label for="zsa_image_fit"><?php esc_html_e( 'نحوه جای‌گذاری تصویر', 'zsa-pm' ); ?></label>
								<select name="zsa_image_fit" id="zsa_image_fit">
									<option value="cover" <?php selected( $image_fit, 'cover' ); ?>><?php esc_html_e( 'پوشش (Cover)', 'zsa-pm' ); ?></option>
									<option value="contain" <?php selected( $image_fit, 'contain' ); ?>><?php esc_html_e( 'محصور (Contain)', 'zsa-pm' ); ?></option>
								</select>
							</div>
						</div>
					</div>
				</div>

				<!-- آیتم ۶: پلتفرم‌ها -->
				<div class="zsa-pm-section">
					<header class="zsa-pm-section-head">
						<span class="zsa-pm-section-title">📱 <?php esc_html_e( 'پلتفرم‌ها', 'zsa-pm' ); ?></span>
						<span class="zsa-pm-section-toggle">+</span>
					</header>
					<div class="zsa-pm-section-body">
						<div class="zsa-pm-field">
							<label for="zsa_app_category"><?php esc_html_e( 'دستهٔ اپلیکیشن (برای Schema.org)', 'zsa-pm' ); ?></label>
							<select name="zsa_app_category" id="zsa_app_category">
								<option value=""><?php esc_html_e( 'خودکار (بازی ← GameApplication، بقیه ← UtilitiesApplication)', 'zsa-pm' ); ?></option>
								<?php foreach ( ZSA_PM_Metaboxes::app_categories() as $ck => $cl ) : ?>
									<option value="<?php echo esc_attr( $ck ); ?>" <?php selected( $app_cat, $ck ); ?>><?php echo esc_html( $cl ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="zsa-pm-checkbox-group">
							<?php foreach ( $platform_options as $key => $label ) : ?>
								<label class="zsa-pm-check">
									<input type="checkbox" name="zsa_platforms[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $platforms, true ), true ); ?>>
										<span><?php echo esc_html( $label ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * دسته‌های معتبر applicationCategory (مطابق فهرست گوگل)
	 *
	 * @return array<string, string>
	 */
	public static function app_categories(): array {
		return array(
			'GameApplication'          => 'بازی (GameApplication)',
			'HealthApplication'        => 'سلامت (HealthApplication)',
			'UtilitiesApplication'     => 'ابزار (UtilitiesApplication)',
			'BusinessApplication'      => 'کسب‌وکار (BusinessApplication)',
			'FinanceApplication'       => 'مالی (FinanceApplication)',
			'LifestyleApplication'     => 'سبک زندگی (LifestyleApplication)',
			'ShoppingApplication'      => 'خرید (ShoppingApplication)',
			'EducationalApplication'   => 'آموزشی (EducationalApplication)',
			'EntertainmentApplication' => 'سرگرمی (EntertainmentApplication)',
			'MultimediaApplication'    => 'چندرسانه‌ای (MultimediaApplication)',
			'SocialNetworkingApplication' => 'شبکه اجتماعی (SocialNetworkingApplication)',
			'CommunicationApplication' => 'ارتباطات (CommunicationApplication)',
		);
	}

	/**
	 * ذخیره متاباکس‌ها
	 *
	 * @param int     $post_id شناسه پست.
	 * @param WP_Post $post پست.
	 */
	public function save( int $post_id, WP_Post $post ): void {
		// بررسی nonce
		if ( ! isset( $_POST['zsa_pm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zsa_pm_nonce'] ) ), 'zsa_pm_save' ) ) {
			return;
		}

		// بررسی autosave و بازنگاری‌ها (revision)
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// بررسی دسترسی
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// ذخیره فیلدهای ساده
		if ( isset( $_POST['zsa_status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_POST['zsa_status'] ) );
			update_post_meta( $post_id, 'zsa_status', in_array( $status, array( 'published', 'coming-soon' ), true ) ? $status : 'published' );
		}

		if ( isset( $_POST['zsa_badge'] ) ) {
			update_post_meta( $post_id, 'zsa_badge', sanitize_text_field( wp_unslash( $_POST['zsa_badge'] ) ) );
		}

		if ( isset( $_POST['zsa_badge_color'] ) ) {
			$color = sanitize_text_field( wp_unslash( $_POST['zsa_badge_color'] ) );
			update_post_meta( $post_id, 'zsa_badge_color', sanitize_hex_color( $color ) ?: '#f59e0b' );
		}

		if ( isset( $_POST['zsa_short_desc'] ) ) {
			$desc = sanitize_textarea_field( wp_unslash( $_POST['zsa_short_desc'] ) );
			$desc = mb_substr( $desc, 0, 200 );
			update_post_meta( $post_id, 'zsa_short_desc', $desc );
		}

		if ( isset( $_POST['zsa_order'] ) ) {
			update_post_meta( $post_id, 'zsa_order', absint( $_POST['zsa_order'] ) );
		}

		if ( isset( $_POST['zsa_card_mode'] ) ) {
			$mode = sanitize_text_field( wp_unslash( $_POST['zsa_card_mode'] ) );
			update_post_meta( $post_id, 'zsa_card_mode', in_array( $mode, array( 'image', 'gradient', 'icon' ), true ) ? $mode : 'image' );
		}

		if ( isset( $_POST['zsa_gradient_from'] ) ) {
			$color = sanitize_text_field( wp_unslash( $_POST['zsa_gradient_from'] ) );
			update_post_meta( $post_id, 'zsa_gradient_from', sanitize_hex_color( $color ) ?: '#4c1d95' );
		}

		if ( isset( $_POST['zsa_gradient_to'] ) ) {
			$color = sanitize_text_field( wp_unslash( $_POST['zsa_gradient_to'] ) );
			update_post_meta( $post_id, 'zsa_gradient_to', sanitize_hex_color( $color ) ?: '#8b5cf6' );
		}

		if ( isset( $_POST['zsa_icon'] ) ) {
			update_post_meta( $post_id, 'zsa_icon', sanitize_text_field( wp_unslash( $_POST['zsa_icon'] ) ) );
		}

		if ( isset( $_POST['zsa_image_fit'] ) ) {
			$fit = sanitize_text_field( wp_unslash( $_POST['zsa_image_fit'] ) );
			update_post_meta( $post_id, 'zsa_image_fit', in_array( $fit, array( 'cover', 'contain' ), true ) ? $fit : 'cover' );
		}

		// لینک‌ها
		if ( isset( $_POST['zsa_links'] ) && is_array( $_POST['zsa_links'] ) ) {
			$raw    = wp_unslash( $_POST['zsa_links'] );
			$clean  = array();
			$styles = array( 'primary', 'secondary', 'tertiary' );

			foreach ( (array) $raw as $row ) {
				$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
				$url   = isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '';
				if ( '' === $label || '' === $url ) {
					continue;
				}
				$style  = isset( $row['style'] ) ? sanitize_text_field( $row['style'] ) : 'primary';
				$target = isset( $row['target'] ) ? sanitize_text_field( $row['target'] ) : '_blank';
				$clean[] = array(
					'label'  => $label,
					'url'    => $url,
					'style'  => in_array( $style, $styles, true ) ? $style : 'primary',
					'target' => in_array( $target, array( '_blank', '_self' ), true ) ? $target : '_blank',
				);
			}

			update_post_meta( $post_id, 'zsa_links', array_slice( $clean, 0, 4 ) );
		} else {
			delete_post_meta( $post_id, 'zsa_links' );
		}

		// گرادیان آماده و دستهٔ اپلیکیشن
		$gc = isset( $_POST['zsa_gradient_class'] ) ? sanitize_key( wp_unslash( $_POST['zsa_gradient_class'] ) ) : '';
		if ( preg_match( '/^g[1-8]$/', $gc ) ) {
			update_post_meta( $post_id, 'zsa_gradient_class', $gc );
		} else {
			delete_post_meta( $post_id, 'zsa_gradient_class' );
		}

		$ac = isset( $_POST['zsa_app_category'] ) ? sanitize_text_field( wp_unslash( $_POST['zsa_app_category'] ) ) : '';
		if ( isset( self::app_categories()[ $ac ] ) ) {
			update_post_meta( $post_id, 'zsa_app_category', $ac );
		} else {
			delete_post_meta( $post_id, 'zsa_app_category' );
		}

		// پلتفرم‌ها
		if ( isset( $_POST['zsa_platforms'] ) && is_array( $_POST['zsa_platforms'] ) ) {
			$raw  = wp_unslash( $_POST['zsa_platforms'] );
			$keys = array( 'android', 'ios', 'pwa', 'windows' );
			$sel  = array();
			foreach ( (array) $raw as $k ) {
				$k = sanitize_text_field( $k );
				if ( in_array( $k, $keys, true ) ) {
					$sel[] = $k;
				}
			}
			update_post_meta( $post_id, 'zsa_platforms', $sel );
		} else {
			delete_post_meta( $post_id, 'zsa_platforms' );
		}

		// پاک‌سازی کش
		if ( class_exists( 'ZSA_PM_Cache' ) ) {
			ZSA_PM_Cache::flush_all();
		}
	}
	/**
	 * ویرایش سریع ترتیب/وضعیت از لیست
	 */
	public function ajax_quick_edit(): void {
		check_ajax_referer( 'zsa_pm_quick_edit', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$field   = isset( $_POST['field'] ) ? sanitize_key( $_POST['field'] ) : '';
		$value   = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $post_id || ! in_array( $field, array( 'zsa_order', 'zsa_status' ), true ) ) {
			wp_send_json_error( __( 'پارامتر نامعتبر', 'zsa-pm' ) );
		}

		if ( ZSA_PM_CPT::POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( __( 'دسترسی غیرمجاز', 'zsa-pm' ), 403 );
		}

		if ( 'zsa_status' === $field && ! in_array( $value, array( 'published', 'coming-soon' ), true ) ) {
			wp_send_json_error( __( 'وضعیت نامعتبر', 'zsa-pm' ) );
		}

		update_post_meta( $post_id, $field, 'zsa_order' === $field ? absint( $value ) : $value );
		
		if ( class_exists( 'ZSA_PM_Cache' ) ) {
			ZSA_PM_Cache::flush_all();
		}

		wp_send_json_success( __( 'ذخیره شد', 'zsa-pm' ) );
	}