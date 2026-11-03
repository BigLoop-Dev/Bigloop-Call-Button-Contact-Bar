<?php
/**
 * Compiles enabled widgets into a render-ready snapshot.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Rendering;

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Actions\FieldValidator;
use Bigloop\CallButton\Icons\IconRegistry;
use Bigloop\CallButton\Repositories\ActionRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The snapshot (one autoloaded option) holds everything the front end needs: resolved actions, links,
 * icon SVGs and colours. Front-end requests therefore run no extra queries. It is rebuilt whenever a widget,
 * action or setting changes, and lazily when the plugin version changes.
 */
final class RuntimeCompiler {

	public const OPTION = 'blcb_runtime';

	/**
	 * Widgets.
	 *
	 * @var WidgetRepository
	 */
	private $widgets;

	/**
	 * Actions.
	 *
	 * @var ActionRepository
	 */
	private $actions;

	/**
	 * Types.
	 *
	 * @var ActionTypeRegistry
	 */
	private $types;

	/**
	 * Icons.
	 *
	 * @var IconRegistry
	 */
	private $icons;

	/**
	 * In-request cache.
	 *
	 * @var array<string,mixed>|null
	 */
	private $snapshot = null;

	/**
	 * Constructor.
	 *
	 * @param WidgetRepository   $widgets Widgets.
	 * @param ActionRepository   $actions Actions.
	 * @param ActionTypeRegistry $types   Types.
	 * @param IconRegistry       $icons   Icons.
	 */
	public function __construct( WidgetRepository $widgets, ActionRepository $actions, ActionTypeRegistry $types, IconRegistry $icons ) {
		$this->widgets = $widgets;
		$this->actions = $actions;
		$this->types   = $types;
		$this->icons   = $icons;
	}

	/**
	 * Current snapshot (rebuilt if missing or stale).
	 *
	 * @return array{version: string, compiled_at: int, widgets: array<int, array<string,mixed>>}
	 */
	public function snapshot(): array {
		if ( null !== $this->snapshot ) {
			return $this->snapshot;
		}
		$stored = get_option( self::OPTION );
		// Also stale when the set of action types changed (e.g. an add-on that registers types was turned off).
		if ( is_array( $stored ) && ( $stored['version'] ?? '' ) === BLCB_VERSION && ( $stored['types'] ?? '' ) === $this->types_signature() && isset( $stored['widgets'] ) ) {
			$this->snapshot = $stored;
			return $stored;
		}
		return $this->rebuild();
	}

	/**
	 * Short signature of the registered action type ids.
	 *
	 * @return string
	 */
	private function types_signature(): string {
		$ids = array_keys( $this->types->all() );
		sort( $ids );
		return substr( md5( implode( ',', $ids ) ), 0, 12 );
	}

	/**
	 * Rebuilds and stores the snapshot.
	 *
	 * @return array{version: string, compiled_at: int, widgets: array<int, array<string,mixed>>}
	 */
	public function rebuild(): array {
		$compiled = array();
		foreach ( $this->widgets->list( array( 'status' => 'enabled' ) )['items'] as $widget ) {
			$entry = $this->compile_widget( $widget );
			if ( $entry ) {
				$compiled[] = $entry;
			}
		}
		$this->snapshot = array(
			'version'     => BLCB_VERSION,
			'types'       => $this->types_signature(),
			'compiled_at' => time(),
			'widgets'     => $compiled,
		);
		update_option( self::OPTION, $this->snapshot, true );

		/**
		 * Fires after the front-end snapshot is rebuilt.
		 *
		 * @since 0.2.0
		 *
		 * @param array $snapshot Snapshot.
		 */
		do_action( 'bigloop_cb/runtime_compiled', $this->snapshot );

		return $this->snapshot;
	}

	/**
	 * Compiles one widget regardless of its status (used for previews).
	 *
	 * @param int $id Widget id.
	 * @return array<string,mixed>|null
	 */
	public function compile_by_id( int $id ): ?array {
		$widget = $this->widgets->find( $id );
		return $widget ? $this->compile_widget( $widget ) : null;
	}

	/**
	 * Resolves a widget document into render data. Returns null when no usable action remains.
	 *
	 * @param array<string,mixed> $widget Widget document.
	 * @return array<string,mixed>|null
	 */
	public function compile_widget( array $widget ): ?array {
		$config  = (array) $widget['config'];
		$actions = array();
		foreach ( (array) ( $config['actions'] ?? array() ) as $ref ) {
			$action = $this->compile_action( (int) ( $ref['id'] ?? 0 ), (string) ( $ref['label'] ?? '' ) );
			if ( $action ) {
				$action['when'] = in_array( $ref['when'] ?? 'always', array( 'open', 'closed' ), true ) ? $ref['when'] : 'always';
				$actions[]      = $action;
			}
		}
		if ( ! $actions ) {
			return null;
		}
		if ( 'button' === ( $config['type'] ?? '' ) ) {
			$actions = array_slice( $actions, 0, 1 );
		}

		$content = (array) ( $config['content'] ?? array() );
		return array(
			'id'      => (int) $widget['id'],
			'title'   => (string) $widget['title'],
			'enabled' => (bool) $widget['enabled'],
			'config'  => $config,
			'actions' => $actions,
			'icons'   => array(
				'toggle' => $this->icons->svg( (string) ( $content['icon'] ?? '' ) ?: 'message-circle' ),
				'close'  => $this->icons->svg( (string) ( $content['close_icon'] ?? '' ) ?: 'x' ),
				'main'   => '' !== (string) ( $content['icon'] ?? '' ) ? $this->icons->svg( (string) $content['icon'] ) : '',
			),
		);
	}

	/**
	 * Resolves one library action.
	 *
	 * @param int    $id             Action id.
	 * @param string $label_override Label override from the widget.
	 * @return array<string,mixed>|null
	 */
	private function compile_action( int $id, string $label_override ): ?array {
		$doc = $this->actions->find( $id );
		if ( ! $doc || ! $doc['enabled'] ) {
			return null;
		}
		$c    = (array) $doc['config'];
		$type = $this->types->get( (string) ( $c['type'] ?? '' ) );
		if ( ! $type ) {
			return null;
		}
		// Re-validate in case the type definition changed since the action was saved.
		$checked = $type->validate( $c['value'] ?? array(), $this->protocols() );
		if ( $checked['errors'] ) {
			return null;
		}
		$href = $type->href( $checked['value'] );

		$label   = '' !== $label_override ? $label_override : ( (string) ( $c['label'] ?? '' ) ?: $type->label() );
		$new_tab = isset( $c['new_tab'] ) && null !== $c['new_tab'] ? (bool) $c['new_tab'] : $type->new_tab_default();
		if ( preg_match( '/^(tel|sms|mailto|viber|facetime|facetime-audio|skype):/i', $href ) ) {
			$new_tab = false; // App handlers never need a new tab.
		}
		$icon     = (string) ( $c['icon'] ?? '' ) ?: $type->icon();
		$defaults = $type->colors();

		return array(
			'id'      => $id,
			'type'    => $type->id(),
			'label'   => $label,
			'aria'    => (string) ( $c['aria_label'] ?? '' ),
			'tooltip' => (string) ( $c['tooltip'] ?? '' ),
			'href'    => $href,
			'new_tab' => $new_tab,
			'icon'    => $this->icons->svg( $icon ) ?: $this->icons->svg( $type->icon() ),
			'bg'      => (string) ( $c['colors']['bg'] ?? '' ) ?: (string) $defaults['bg'],
			'fg'      => (string) ( $c['colors']['fg'] ?? '' ) ?: (string) $defaults['fg'],
		);
	}

	/**
	 * Allowed custom-link protocols.
	 *
	 * @return string[]
	 */
	public function protocols(): array {
		/**
		 * Filters protocols allowed in "Custom link" actions.
		 *
		 * @since 0.2.0
		 *
		 * @param string[] $protocols Lower-case schemes without colon.
		 */
		return (array) apply_filters( 'bigloop_cb/custom_url_protocols', FieldValidator::CUSTOM_PROTOCOLS );
	}
}
