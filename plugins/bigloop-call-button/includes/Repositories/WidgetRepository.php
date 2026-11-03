<?php
/**
 * Contact widget storage.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Repositories;

use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\PostTypes\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Widgets plus helpers for action references.
 */
final class WidgetRepository extends PostRepository {

	/**
	 * {@inheritDoc}
	 */
	protected function post_type(): string {
		return PostTypes::WIDGET;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function changed_hook(): string {
		return 'bigloop_cb/widgets_changed';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Older documents are upgraded to the current schema on read (missing keys filled with defaults).
	 *
	 * @param \WP_Post $post Post.
	 */
	public function hydrate( $post ): array {
		$doc           = parent::hydrate( $post );
		$doc['config'] = WidgetSanitizer::upgrade( $doc['config'] );
		return $doc;
	}

	/**
	 * Copies a widget. The copy starts disabled so it never appears on the site unexpectedly.
	 *
	 * @param int $id Source id.
	 * @return int|\WP_Error New id.
	 */
	public function duplicate( int $id ) {
		$source = $this->find( $id );
		if ( ! $source ) {
			return new \WP_Error( 'blcb_not_found', __( 'Widget not found.', 'bigloop-call-button' ), array( 'status' => 404 ) );
		}
		return $this->create(
			array(
				/* translators: %s: widget name */
				'title'   => sprintf( __( '%s (copy)', 'bigloop-call-button' ), $source['title'] ),
				'enabled' => false,
				'config'  => $source['config'],
			)
		);
	}

	/**
	 * Widgets that reference an action.
	 *
	 * @param int $action_id Action id.
	 * @return array<int, array{id:int,title:string}>
	 */
	public function using_action( int $action_id ): array {
		$out = array();
		foreach ( $this->list()['items'] as $widget ) {
			foreach ( (array) ( $widget['config']['actions'] ?? array() ) as $ref ) {
				if ( (int) ( $ref['id'] ?? 0 ) === $action_id ) {
					$out[] = array(
						'id'    => $widget['id'],
						'title' => $widget['title'],
					);
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Removes an action from every widget that references it (called before an action is deleted).
	 *
	 * @param int $action_id Action id.
	 * @return int Number of widgets changed.
	 */
	public function remove_action_references( int $action_id ): int {
		$changed = 0;
		foreach ( $this->list()['items'] as $widget ) {
			$refs = (array) ( $widget['config']['actions'] ?? array() );
			$kept = array_values(
				array_filter(
					$refs,
					static function ( $ref ) use ( $action_id ) {
						return (int) ( $ref['id'] ?? 0 ) !== $action_id;
					}
				)
			);
			if ( count( $kept ) !== count( $refs ) ) {
				$widget['config']['actions'] = $kept;
				$this->write_config( $widget['id'], $widget['config'] );
				++$changed;
			}
		}
		if ( $changed ) {
			$this->changed( 0, 'updated' );
		}
		return $changed;
	}
}
