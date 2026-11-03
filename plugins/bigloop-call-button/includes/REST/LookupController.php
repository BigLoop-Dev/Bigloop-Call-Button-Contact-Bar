<?php
/**
 * Lookups for rule pickers + public country endpoint.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Conditions\ConditionRegistry;
use Bigloop\CallButton\Geo\GeoService;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   GET /conditions                       Condition types + post types + taxonomies (admin).
 *   GET /lookup/posts?type=&search=&include=   (admin)
 *   GET /lookup/terms?taxonomy=&search=&include= (admin)
 *   GET /geo                              Public: { country } for client-side country rules. Never cached.
 */
final class LookupController extends Controller {

	/**
	 * Conditions.
	 *
	 * @var ConditionRegistry
	 */
	private $conditions;

	/**
	 * Geo.
	 *
	 * @var GeoService
	 */
	private $geo;

	/**
	 * Constructor.
	 *
	 * @param ConditionRegistry $conditions Conditions.
	 * @param GeoService        $geo        Geo.
	 */
	public function __construct( ConditionRegistry $conditions, GeoService $geo ) {
		$this->conditions = $conditions;
		$this->geo        = $geo;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$ns   = RestServiceProvider::REST_NAMESPACE;
		$perm = array( $this, 'can_manage' );
		register_rest_route(
			$ns,
			'/conditions',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'conditions' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/lookup/posts',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'posts' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/lookup/terms',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'terms' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/geo',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'country' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Condition types and pickers' option lists.
	 *
	 * @return \WP_REST_Response
	 */
	public function conditions(): \WP_REST_Response {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' !== $pt->name ) {
				$post_types[] = array(
					'value' => $pt->name,
					'label' => $pt->labels->singular_name,
				);
			}
		}
		$taxonomies = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' !== $tax->name ) {
				$taxonomies[] = array(
					'value' => $tax->name,
					'label' => $tax->labels->singular_name,
				);
			}
		}
		return rest_ensure_response(
			array(
				'conditions'  => array_values( array_map( static fn( $c ) => $c->to_array(), $this->conditions->all() ) ),
				'post_types'  => $post_types,
				'taxonomies'  => $taxonomies,
				'geo_enabled' => $this->geo->enabled(),
				'woocommerce' => class_exists( 'WooCommerce' ),
			)
		);
	}

	/**
	 * Post search (or resolve ids to titles with include=).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function posts( \WP_REST_Request $request ): \WP_REST_Response {
		$type    = sanitize_key( (string) $request->get_param( 'type' ) ) ?: 'page';
		$include = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'include' ) ) ) );
		$args    = array(
			'post_type'        => post_type_exists( $type ) ? $type : 'page',
			'post_status'      => array( 'publish', 'private', 'future', 'draft' ),
			'posts_per_page'   => 20,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		);
		if ( $include ) {
			$args['post__in']       = $include;
			$args['posts_per_page'] = count( $include );
			$args['orderby']        = 'post__in';
		} elseif ( '' !== (string) $request->get_param( 'search' ) ) {
			$args['s'] = sanitize_text_field( (string) $request->get_param( 'search' ) );
		}
		$items = array_map(
			static function ( $post ) {
				return array(
					'value' => (int) $post->ID,
					'label' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ) ?: sprintf( '#%d', $post->ID ),
				);
			},
			get_posts( $args )
		);
		return rest_ensure_response( $items );
	}

	/**
	 * Term search (or resolve ids).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function terms( \WP_REST_Request $request ): \WP_REST_Response {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return rest_ensure_response( array() );
		}
		$include = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'include' ) ) ) );
		$args    = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 30,
		);
		if ( $include ) {
			$args['include'] = $include;
		} elseif ( '' !== (string) $request->get_param( 'search' ) ) {
			$args['search'] = sanitize_text_field( (string) $request->get_param( 'search' ) );
		}
		$terms = get_terms( $args );
		return rest_ensure_response(
			is_wp_error( $terms ) ? array() : array_map(
				static function ( $t ) {
					return array(
						'value' => (int) $t->term_id,
						'label' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ),
					);
				},
				$terms
			)
		);
	}

	/**
	 * Visitor country (public, uncached, no data stored).
	 *
	 * @return \WP_REST_Response
	 */
	public function country(): \WP_REST_Response {
		$response = rest_ensure_response( array( 'country' => $this->geo->country() ) );
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		$response->header( 'Vary', '*' );
		return $response;
	}
}
