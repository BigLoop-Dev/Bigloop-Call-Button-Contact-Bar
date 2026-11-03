<?php
/**
 * Request facts used by server-side conditions.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable snapshot of the current request. Built from WordPress in from_wp(), or directly in tests.
 */
final class Context {

	/**
	 * Facts.
	 *
	 * @var array<string,mixed>
	 */
	private $facts;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $facts {
	 *     Facts about the current request.
	 *
	 *     @type int      $object_id   Queried singular object ID (0 if none).
	 *     @type string   $post_type   Post type of the singular object or archive.
	 *     @type bool     $singular    Singular view.
	 *     @type array    $terms       Taxonomy => term IDs of the singular object, or of the archive term.
	 *     @type string   $path        Request path, e.g. "/services/plumbing/".
	 *     @type bool     $logged_in   Visitor logged in.
	 *     @type string[] $special     Flags: front, blog, search, 404, archive.
	 *     @type string[] $woo         Flags: shop, cart, checkout, account, product.
	 * }
	 */
	public function __construct( array $facts ) {
		$this->facts = $facts + array(
			'object_id' => 0,
			'post_type' => '',
			'singular'  => false,
			'terms'     => array(),
			'path'      => '/',
			'logged_in' => false,
			'special'   => array(),
			'woo'       => array(),
		);
	}

	/**
	 * Fact accessor.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->facts[ $key ] ?? null;
	}

	/**
	 * Builds the context from the main WordPress query (call on/after the `wp` action).
	 *
	 * @return self
	 */
	public static function from_wp(): self {
		$object    = get_queried_object();
		$singular  = is_singular();
		$object_id = $singular ? (int) get_queried_object_id() : 0;
		$post_type = $singular && $object ? (string) get_post_type( $object ) : '';
		$terms     = array();

		if ( $singular && $object_id ) {
			foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
				$ids = wp_get_object_terms( $object_id, $taxonomy, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $ids ) && $ids ) {
					$terms[ $taxonomy ] = array_map( 'intval', $ids );
				}
			}
		} elseif ( $object instanceof \WP_Term ) {
			$terms[ $object->taxonomy ] = array( (int) $object->term_id );
		} elseif ( is_post_type_archive() ) {
			$post_type = (string) get_query_var( 'post_type' );
		}

		$special = array();
		if ( is_front_page() ) {
			$special[] = 'front';
		}
		if ( is_home() ) {
			$special[] = 'blog';
		}
		if ( is_search() ) {
			$special[] = 'search';
		}
		if ( is_404() ) {
			$special[] = '404';
		}
		if ( is_archive() ) {
			$special[] = 'archive';
		}

		$woo = array();
		if ( function_exists( 'is_woocommerce' ) ) {
			if ( function_exists( 'is_shop' ) && is_shop() ) {
				$woo[] = 'shop';
			}
			if ( function_exists( 'is_cart' ) && is_cart() ) {
				$woo[] = 'cart';
			}
			if ( function_exists( 'is_checkout' ) && is_checkout() ) {
				$woo[] = 'checkout';
			}
			if ( function_exists( 'is_account_page' ) && is_account_page() ) {
				$woo[] = 'account';
			}
			if ( function_exists( 'is_product' ) && is_product() ) {
				$woo[] = 'product';
			}
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the path is used and compared as text.
		$path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );

		return new self(
			array(
				'object_id' => $object_id,
				'post_type' => $post_type,
				'singular'  => $singular,
				'terms'     => $terms,
				'path'      => '' !== $path ? $path : '/',
				'logged_in' => is_user_logged_in(),
				'special'   => $special,
				'woo'       => $woo,
			)
		);
	}
}
