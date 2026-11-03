<?php
/**
 * Base repository for JSON-config documents stored in private post types.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Storage: post_title = name, post_status publish|draft = enabled|disabled, menu_order = manual order,
 * meta `_blcb_config` = JSON document, meta `_blcb_type` = type (for filtering).
 */
abstract class PostRepository {

	public const META_CONFIG = '_blcb_config';
	public const META_TYPE   = '_blcb_type';

	/**
	 * Post type.
	 *
	 * @return string
	 */
	abstract protected function post_type(): string;

	/**
	 * Action fired after any change (the runtime compiler listens).
	 *
	 * @return string
	 */
	abstract protected function changed_hook(): string;

	/**
	 * Finds one document.
	 *
	 * @param int $id Post id.
	 * @return array{id:int,title:string,enabled:bool,type:string,config:array<string,mixed>,order:int,created:string,modified:string}|null
	 */
	public function find( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $this->post_type() || ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			return null;
		}
		return $this->hydrate( $post );
	}

	/**
	 * Whether a document exists.
	 *
	 * @param int $id Post id.
	 * @return bool
	 */
	public function exists( int $id ): bool {
		return null !== $this->find( $id );
	}

	/**
	 * Lists documents.
	 *
	 * @param array<string,mixed> $args search, status (any|enabled|disabled), type, orderby (title|date|modified|order), order, page, per_page (0 = all).
	 * @return array{items: array<int, array<string,mixed>>, total: int}
	 */
	public function list( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => 'any',
				'type'     => '',
				'orderby'  => 'order',
				'order'    => 'asc',
				'page'     => 1,
				'per_page' => 0,
			)
		);

		$orderby = array(
			'title'    => 'title',
			'date'     => 'date',
			'modified' => 'modified',
			'order'    => array(
				'menu_order' => strtoupper( $args['order'] ),
				'date'       => 'DESC',
			),
		);
		$query   = array(
			'post_type'              => $this->post_type(),
			'post_status'            => array(
				'any'      => array( 'publish', 'draft' ),
				'enabled'  => 'publish',
				'disabled' => 'draft',
			)[ $args['status'] ] ?? array( 'publish', 'draft' ),
			'orderby'                => $orderby[ $args['orderby'] ] ?? $orderby['order'],
			'order'                  => 'desc' === strtolower( (string) $args['order'] ) ? 'DESC' : 'ASC',
			'posts_per_page'         => (int) $args['per_page'] > 0 ? (int) $args['per_page'] : -1,
			'paged'                  => max( 1, (int) $args['page'] ),
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => true,
		);
		if ( '' !== $args['search'] ) {
			$query['s'] = (string) $args['search'];
		}
		if ( '' !== $args['type'] ) {
			$query['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small private post type.
				array(
					'key'   => self::META_TYPE,
					'value' => (string) $args['type'],
				),
			);
		}

		$q = new \WP_Query( $query );
		return array(
			'items' => array_map( array( $this, 'hydrate' ), $q->posts ),
			'total' => (int) $q->found_posts,
		);
	}

	/**
	 * Creates a document from sanitized data.
	 *
	 * @param array{title:string,enabled:bool,config:array<string,mixed>} $data Sanitized data.
	 * @return int|\WP_Error New id.
	 */
	public function create( array $data ) {
		$max_order = (int) $this->max_order();
		$id        = wp_insert_post(
			array(
				'post_type'   => $this->post_type(),
				'post_title'  => $data['title'],
				'post_status' => $data['enabled'] ? 'publish' : 'draft',
				'menu_order'  => $max_order + 1,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$this->write_config( (int) $id, $data['config'] );
		$this->changed( (int) $id, 'created' );
		return (int) $id;
	}

	/**
	 * Replaces a document.
	 *
	 * @param int                                                         $id   Id.
	 * @param array{title:string,enabled:bool,config:array<string,mixed>} $data Sanitized data.
	 * @return true|\WP_Error
	 */
	public function update( int $id, array $data ) {
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_title'  => $data['title'],
				'post_status' => $data['enabled'] ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->write_config( $id, $data['config'] );
		$this->changed( $id, 'updated' );
		return true;
	}

	/**
	 * Enables or disables.
	 *
	 * @param int  $id      Id.
	 * @param bool $enabled Enabled.
	 * @return true|\WP_Error
	 */
	public function set_enabled( int $id, bool $enabled ) {
		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $enabled ? 'publish' : 'draft',
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->changed( $id, 'status' );
		return true;
	}

	/**
	 * Permanently deletes.
	 *
	 * @param int $id Id.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		$deleted = (bool) wp_delete_post( $id, true );
		if ( $deleted ) {
			$this->changed( $id, 'deleted' );
		}
		return $deleted;
	}

	/**
	 * Saves the manual order.
	 *
	 * @param int[] $ids Ids in order.
	 * @return void
	 */
	public function reorder( array $ids ): void {
		foreach ( array_values( $ids ) as $i => $id ) {
			if ( $this->exists( (int) $id ) ) {
				wp_update_post(
					array(
						'ID'         => (int) $id,
						'menu_order' => $i + 1,
					)
				);
			}
		}
		$this->changed( 0, 'reordered' );
	}

	/**
	 * Converts a post to a document array.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	public function hydrate( $post ): array {
		$json   = get_post_meta( $post->ID, self::META_CONFIG, true );
		$config = is_string( $json ) ? json_decode( $json, true ) : null;
		return array(
			'id'       => (int) $post->ID,
			'title'    => html_entity_decode( (string) $post->post_title, ENT_QUOTES, 'UTF-8' ),
			'enabled'  => 'publish' === $post->post_status,
			'type'     => (string) get_post_meta( $post->ID, self::META_TYPE, true ),
			'config'   => is_array( $config ) ? $config : array(),
			'order'    => (int) $post->menu_order,
			'created'  => mysql_to_rfc3339( $post->post_date_gmt ),
			'modified' => mysql_to_rfc3339( $post->post_modified_gmt ),
		);
	}

	/**
	 * Stores config JSON (slashed: post meta functions unslash their input).
	 *
	 * @param int                 $id     Id.
	 * @param array<string,mixed> $config Config.
	 * @return void
	 */
	protected function write_config( int $id, array $config ): void {
		update_post_meta( $id, self::META_CONFIG, wp_slash( (string) wp_json_encode( $config ) ) );
		update_post_meta( $id, self::META_TYPE, (string) ( $config['type'] ?? '' ) );
	}

	/**
	 * Highest menu_order in use.
	 *
	 * @return int
	 */
	private function max_order(): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_type = %s", $this->post_type() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Fires the change hook.
	 *
	 * @param int    $id     Id (0 for bulk).
	 * @param string $reason Reason.
	 * @return void
	 */
	protected function changed( int $id, string $reason ): void {
		/**
		 * Fires after a widget or action changes.
		 *
		 * @since 0.2.0
		 *
		 * @param int    $id     Document id (0 for bulk changes).
		 * @param string $reason created|updated|status|deleted|reordered.
		 */
		switch ( $this->changed_hook() ) {
			case 'bigloop_cb/widgets_changed':
				do_action( 'bigloop_cb/widgets_changed', $id, $reason );
				break;
			case 'bigloop_cb/actions_changed':
				do_action( 'bigloop_cb/actions_changed', $id, $reason );
				break;
			case 'bigloop_cb/schedules_changed':
				do_action( 'bigloop_cb/schedules_changed', $id, $reason );
				break;
			case 'bigloop_cb/presets_changed':
				do_action( 'bigloop_cb/presets_changed', $id, $reason );
				break;
		}
	}
}
