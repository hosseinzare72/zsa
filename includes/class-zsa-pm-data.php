<?php
/**
 * لایهٔ داده — یک کوئری و یک ترانزینت برای کل افزونه
 *
 * تمام شورت‌کدها، اسکیما و صفحهٔ تکی پروژه از همین مجموعه‌داده استفاده می‌کنند؛
 * بنابراین با کش گرم، کل افزونه در هر صفحه فقط یک ترانزینت می‌خواند.
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ساخت و کش مجموعه‌داده پروژه‌ها
 */
final class ZSA_PM_Data {

	/**
	 * کلید ترانزینت (با پیشوند zsa_pm_ تا با پاک‌سازی کش حذف شود)
	 */
	public const KEY = 'zsa_pm_dataset_v3';

	/**
	 * کش درون‌درخواستی
	 *
	 * @var array|null
	 */
	private static ?array $memo = null;

	/**
	 * پاک‌کردن کش درون‌درخواستی (پس از flush_all)
	 */
	public static function reset(): void {
		self::$memo = null;
	}

	/**
	 * تبدیل ارقام به فارسی
	 *
	 * @param int|string $n عدد.
	 * @return string
	 */
	public static function fa( $n ): string {
		return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
	}

	/**
	 * همهٔ پروژه‌های منتشرشده (مرتب‌شده بر اساس ترتیب)
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$cached = ZSA_PM_Cache::get( self::KEY );
		if ( is_array( $cached ) ) {
			self::$memo = $cached;
			return $cached;
		}

		$items      = self::build();
		self::$memo = $items;
		ZSA_PM_Cache::set( self::KEY, $items );

		return $items;
	}

	/**
	 * فیلتر کردن مجموعه‌داده در PHP (بدون کوئری اضافه)
	 *
	 * @param array $args status: published|coming-soon، types: آرایه اسلاگ، exclude_types، limit.
	 * @return array<int, array<string, mixed>>
	 */
	public static function find( array $args = array() ): array {
		$status  = isset( $args['status'] ) ? (string) $args['status'] : 'published';
		$types   = isset( $args['types'] ) ? (array) $args['types'] : array();
		$exclude = isset( $args['exclude_types'] ) ? (array) $args['exclude_types'] : array();
		$limit   = isset( $args['limit'] ) ? (int) $args['limit'] : 0;
		$out     = array();

		foreach ( self::all() as $item ) {
			if ( $status !== $item['status'] ) {
				continue;
			}
			if ( ! empty( $types ) && ! array_intersect( $types, $item['types'] ) ) {
				continue;
			}
			if ( ! empty( $exclude ) && array_intersect( $exclude, $item['types'] ) ) {
				continue;
			}
			$out[] = $item;
			if ( $limit > 0 && count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * پیدا کردن یک آیتم با شناسه
	 *
	 * @param int $post_id شناسه.
	 * @return array|null
	 */
	public static function get_item( int $post_id ): ?array {
		foreach ( self::all() as $item ) {
			if ( $item['id'] === $post_id ) {
				return $item;
			}
		}
		return null;
	}

	/**
	 * شمارنده‌ها برای [zsa_stats]
	 *
	 * @return array{web:int, published:int, soon:int}
	 */
	public static function counts(): array {
		$web = 0;
		$pub = 0;
		$soon = 0;

		foreach ( self::all() as $item ) {
			if ( 'coming-soon' === $item['status'] ) {
				++$soon;
				continue;
			}
			if ( in_array( 'website', $item['types'], true ) ) {
				++$web;
			} else {
				++$pub;
			}
		}

		return array(
			'web'       => $web,
			'published' => $pub,
			'soon'      => $soon,
		);
	}

	/**
	 * ساخت مجموعه‌داده با حداقل کوئری (پست‌ها + متا + ترم‌ها + تصاویر)
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function build(): array {
		$query = new WP_Query(
			array(
				'post_type'              => ZSA_PM_CPT::POST_TYPE,
				'post_status'            => 'publish',
				// -1 = همهٔ پروژه‌ها (نسخهٔ قبلی روی ۱۰۰ متوقف می‌شد)
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		if ( function_exists( 'update_post_thumbnail_cache' ) ) {
			update_post_thumbnail_cache( $query );
		}

		$items = array();

		foreach ( $query->posts as $post ) {
			$id    = (int) $post->ID;
			$title = get_the_title( $post );

			// ترم‌ها
			$types  = array();
			$labels = array();
			$terms  = get_the_terms( $id, ZSA_PM_Taxonomy::TAXONOMY );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$types[]  = $term->slug;
					$labels[] = $term->name;
				}
			}

			// لینک‌ها
			$links     = array();
			$raw_links = get_post_meta( $id, 'zsa_links', true );
			if ( is_array( $raw_links ) ) {
				foreach ( $raw_links as $row ) {
					if ( empty( $row['url'] ) || empty( $row['label'] ) ) {
						continue;
					}
					$style    = isset( $row['style'] ) ? (string) $row['style'] : 'primary';
					$links[]  = array(
						'label'  => (string) $row['label'],
						'url'    => (string) $row['url'],
						'style'  => in_array( $style, array( 'primary', 'secondary', 'tertiary' ), true ) ? $style : 'primary',
						'target' => ( isset( $row['target'] ) && '_self' === $row['target'] ) ? '_self' : '_blank',
					);
				}
			}

			// تصویر شاخص
			$img = null;
			$tid = (int) get_post_thumbnail_id( $id );
			if ( $tid ) {
				$src = wp_get_attachment_image_src( $tid, 'medium_large' );
				if ( $src && ! empty( $src[0] ) ) {
					$alt   = (string) get_post_meta( $tid, '_wp_attachment_image_alt', true );
					$srcset = (string) wp_get_attachment_image_srcset( $tid, 'medium_large' );
					$sizes  = (string) wp_get_attachment_image_sizes( $tid, 'medium_large' );
					$img    = array(
						'url'    => (string) $src[0],
						'w'      => (int) $src[1],
						'h'      => (int) $src[2],
						'alt'    => '' !== $alt ? $alt : $title,
						'srcset' => $srcset,
						'sizes'  => $sizes,
					);
				}
			}

			$status = (string) get_post_meta( $id, 'zsa_status', true );
			$desc   = (string) get_post_meta( $id, 'zsa_short_desc', true );
			if ( '' === $desc ) {
				$desc = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 30, '…' );
			}

			$platforms = get_post_meta( $id, 'zsa_platforms', true );

			$items[] = array(
				'id'          => $id,
				'title'       => $title,
				'slug'        => (string) $post->post_name,
				'url'         => (string) get_permalink( $id ),
				'types'       => $types,
				'type'        => $types[0] ?? '',
				'type_label'  => $labels[0] ?? '',
				'status'      => 'coming-soon' === $status ? 'coming-soon' : 'published',
				'order'       => (int) get_post_meta( $id, 'zsa_order', true ),
				'badge'       => (string) get_post_meta( $id, 'zsa_badge', true ),
				'badge_color' => (string) get_post_meta( $id, 'zsa_badge_color', true ) ?: '#f59e0b',
				'desc'        => $desc,
				'links'       => $links,
				'mode'        => (string) get_post_meta( $id, 'zsa_card_mode', true ) ?: 'image',
				'grad_from'   => (string) get_post_meta( $id, 'zsa_gradient_from', true ) ?: '#4c1d95',
				'grad_to'     => (string) get_post_meta( $id, 'zsa_gradient_to', true ) ?: '#8b5cf6',
				'grad_class'  => (string) get_post_meta( $id, 'zsa_gradient_class', true ),
				'icon'        => (string) get_post_meta( $id, 'zsa_icon', true ),
				'fit'         => 'contain' === (string) get_post_meta( $id, 'zsa_image_fit', true ) ? 'contain' : 'cover',
				'platforms'   => is_array( $platforms ) ? array_values( $platforms ) : array(),
				'app_cat'     => (string) get_post_meta( $id, 'zsa_app_category', true ),
				'img'         => $img,
				'modified'    => (string) get_post_modified_time( 'c', true, $id ),
			);
		}

		usort(
			$items,
			static function ( array $a, array $b ): int {
				return array( $a['order'], $a['id'] ) <=> array( $b['order'], $b['id'] );
			}
		);

		return $items;
	}
}
