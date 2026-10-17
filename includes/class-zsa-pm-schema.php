<?php
/**
 * تولید خودکار Schema.org (JSON-LD) با ساختار یکپارچهٔ @graph
 *
 * @package ZSA_Portfolio_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خروجی: Organization + CollectionPage + ItemList + BreadcrumbList + MobileApplication/SoftwareApplication/WebSite
 */
final class ZSA_PM_Schema {

	/**
	 * نمونه واحد
	 *
	 * @var ZSA_PM_Schema|null
	 */
	private static ?ZSA_PM_Schema $instance = null;

	/**
	 * دریافت نمونه واحد
	 */
	public static function instance(): ZSA_PM_Schema {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * سازنده
	 */
	private function __construct() {
		add_action( 'wp_head', array( $this, 'output' ), 30 );
	}

	/**
	 * شناسهٔ ثابت سازمان در گراف
	 *
	 * @return string
	 */
	private static function org_id(): string {
		return trailingslashit( ZSA_PM_Settings::portfolio_url() ) . '#organization';
	}

	/**
	 * گره Organization
	 *
	 * @return array
	 */
	private function organization(): array {
		$phone = ZSA_PM_Settings::phone_e164();
		$org   = array(
			'@type'        => 'Organization',
			'@id'          => self::org_id(),
			'name'         => 'ZSA Studio',
			'alternateName'=> 'Zare Studio Apps',
			'url'          => ZSA_PM_Settings::portfolio_url(),
			'description'  => 'استودیوی خلاق توسعه بازی، اپلیکیشن و وب‌سایت‌های حرفه‌ای وردپرسی.',
			'founder'      => array(
				'@type'     => 'Person',
				'name'      => 'حسین زارع',
				'telephone' => $phone,
			),
			'contactPoint' => array(
				'@type'             => 'ContactPoint',
				'telephone'         => $phone,
				'contactType'       => 'customer support',
				'availableLanguage' => 'fa',
			),
		);

		$telegram = (string) ZSA_PM_Settings::get( 'telegram', '' );
		if ( '' !== $telegram ) {
			$org['sameAs'] = array( $telegram );
		}

		return $org;
	}

	/**
	 * نگاشت پلتفرم‌ها به operatingSystem
	 *
	 * @param array $platforms پلتفرم‌های انتخاب‌شده.
	 * @return string
	 */
	private function operating_system( array $platforms ): string {
		$map = array(
			'android' => 'Android',
			'ios'     => 'iOS',
			'web'     => 'Web (PWA)',
			'pwa'     => 'Web (PWA)',
			'windows' => 'Windows',
		);
		$out = array();
		foreach ( $platforms as $p ) {
			$key = sanitize_key( (string) $p );
			if ( isset( $map[ $key ] ) ) {
				$out[] = $map[ $key ];
			}
		}
		return empty( $out ) ? 'Android' : implode( ', ', $out );
	}

	/**
	 * گرهٔ نرم‌افزار/وب‌سایت برای یک پروژه
	 *
	 * @param array $it آیتم.
	 * @return array
	 */
	private function project_node( array $it ): array {
		$is_site = in_array( 'website', $it['types'], true );
		$first   = ! empty( $it['links'][0]['url'] ) ? (string) $it['links'][0]['url'] : '';

		if ( $is_site ) {
			$node = array(
				'@type'       => 'WebSite',
				'@id'         => $first ? trailingslashit( $first ) . '#website' : $it['url'] . '#website',
				'name'        => $it['title'],
				'url'         => $first ?: $it['url'],
				'description' => $it['desc'],
				'inLanguage'  => 'fa-IR',
				'creator'     => array( '@id' => self::org_id() ),
			);
			return $node;
		}

		$is_mobile = (bool) array_intersect( array( 'app', 'game', 'ecosystem' ), $it['types'] );
		$category  = $it['app_cat'];
		if ( '' === $category ) {
			$category = in_array( 'game', $it['types'], true ) ? 'GameApplication' : 'UtilitiesApplication';
		}

		$node = array(
			'@type'               => $is_mobile ? 'MobileApplication' : 'SoftwareApplication',
			'@id'                 => $it['url'] . '#software',
			'name'                => $it['title'],
			'description'         => $it['desc'],
			'url'                 => $it['url'],
			'operatingSystem'     => $this->operating_system( $it['platforms'] ),
			'applicationCategory' => $category,
			'inLanguage'          => 'fa-IR',
			'author'              => array( '@id' => self::org_id() ),
			'publisher'           => array( '@id' => self::org_id() ),
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'IRR',
				'availability'  => 'https://schema.org/InStock',
			),
		);

		if ( ! empty( $it['img']['url'] ) ) {
			$node['image'] = $it['img']['url'];
		}
		if ( '' !== $first ) {
			$node['downloadUrl'] = $first;
		}
		if ( '' !== $it['modified'] ) {
			$node['dateModified'] = $it['modified'];
		}

		return $node;
	}

	/**
	 * ساخت گراف صفحهٔ پورتفولیو
	 *
	 * @return array
	 */
	private function portfolio_graph(): array {
		$page_url = (string) get_permalink();
		$graph    = array( $this->organization() );
		$list     = array();
		$pos      = 1;
		$items    = array_merge(
			ZSA_PM_Data::find(
				array(
					'status'        => 'published',
					'exclude_types' => array( 'website' ),
				)
			),
			ZSA_PM_Data::find(
				array(
					'status' => 'published',
					'types'  => array( 'website' ),
				)
			)
		);

		foreach ( $items as $it ) {
			$node    = $this->project_node( $it );
			$graph[] = $node;
			$list[]  = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $it['title'],
				'url'      => $node['url'],
			);
		}

		$graph[] = array(
			'@type'           => 'ItemList',
			'@id'             => $page_url . '#projects',
			'name'            => get_the_title(),
			'numberOfItems'   => count( $list ),
			'itemListElement' => $list,
		);

		$graph[] = array(
			'@type'       => 'CollectionPage',
			'@id'         => $page_url . '#webpage',
			'url'         => $page_url,
			'name'        => get_the_title(),
			'inLanguage'  => 'fa-IR',
			'about'       => array( '@id' => self::org_id() ),
			'mainEntity'  => array( '@id' => $page_url . '#projects' ),
			'breadcrumb'  => array( '@id' => $page_url . '#breadcrumb' ),
		);

		$graph[] = $this->breadcrumb( $page_url . '#breadcrumb', array( array( get_the_title(), $page_url ) ) );

		return $graph;
	}

	/**
	 * ساخت گراف صفحهٔ تکی پروژه
	 *
	 * @param array $it آیتم.
	 * @return array
	 */
	private function single_graph( array $it ): array {
		$portfolio = ZSA_PM_Settings::portfolio_url();

		return array(
			$this->organization(),
			$this->project_node( $it ),
			$this->breadcrumb(
				$it['url'] . '#breadcrumb',
				array(
					array( 'ZSA Studio', $portfolio ),
					array( $it['title'], $it['url'] ),
				)
			),
		);
	}

	/**
	 * گره BreadcrumbList
	 *
	 * @param string $id    شناسه.
	 * @param array  $trail آرایه‌ای از [نام، آدرس] پس از «خانه».
	 * @return array
	 */
	private function breadcrumb( string $id, array $trail ): array {
		$elements = array(
			array(
				'@type'    => 'ListItem',
				'position' => 1,
				'name'     => 'خانه',
				'item'     => home_url( '/' ),
			),
		);
		$pos = 2;
		foreach ( $trail as $row ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => (string) $row[0],
				'item'     => (string) $row[1],
			);
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $id,
			'itemListElement' => $elements,
		);
	}

	/**
	 * چاپ JSON-LD در head
	 */
	public function output(): void {
		if ( ! (int) ZSA_PM_Settings::get( 'schema', 1 ) || ! ZSA_PM_Shortcodes::is_zsa_page() ) {
			return;
		}

		if ( is_singular( ZSA_PM_CPT::POST_TYPE ) ) {
			$it = ZSA_PM_Data::get_item( (int) get_the_ID() );
			// صفحه‌های وب‌سایت و «در توسعه» نازک‌اند و noindex می‌شوند؛ اسکیما هم ندارند
			if ( ! $it || 'published' !== $it['status'] || in_array( 'website', $it['types'], true ) ) {
				return;
			}
			$graph = $this->single_graph( $it );
		} else {
			$graph = $this->portfolio_graph();
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);

		$json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}
}
