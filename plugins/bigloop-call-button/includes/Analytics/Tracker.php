<?php
/**
 * Records click events sent by the front-end beacon.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Analytics;

use Bigloop\CallButton\Database\Schema;
use Bigloop\CallButton\Rendering\RuntimeCompiler;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Privacy: stores widget, action, action type, device class (desktop/tablet/mobile), the page's post ID and the time.
 * No IP address, user agent, cookie, referrer, full URL or user ID is stored.
 *
 * Abuse: the endpoint is public (nonces break on cached pages), so every event is checked against the live snapshot
 * (the widget must be enabled and contain the action), and each client is rate-limited with a short-lived transient
 * keyed by a salted hash of its IP address. The hash is never written to the events table.
 */
final class Tracker {

	/** Max events per client per window. */
	public const RATE_LIMIT = 30;

	/** Rate-limit window in seconds. */
	public const RATE_WINDOW = 600;

	/**
	 * Database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Snapshot.
	 *
	 * @var RuntimeCompiler
	 */
	private $compiler;

	/**
	 * Constructor.
	 *
	 * @param \wpdb           $wpdb     Database.
	 * @param Settings        $settings Settings.
	 * @param RuntimeCompiler $compiler Snapshot.
	 */
	public function __construct( $wpdb, Settings $settings, RuntimeCompiler $compiler ) {
		$this->wpdb     = $wpdb;
		$this->settings = $settings;
		$this->compiler = $compiler;
	}

	/**
	 * Whether tracking is on.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return (bool) $this->settings->get( 'analytics.enabled', true );
	}

	/**
	 * Validates and stores one click.
	 *
	 * @param array<string,mixed> $input  { w: widget id, a: action id, d: 1|2|3, o: post id }.
	 * @param string              $client Client identifier for rate limiting (e.g. IP address); never stored.
	 * @return string One of: recorded, disabled, invalid, limited, skipped.
	 */
	public function record( array $input, string $client ): string {
		// Extension data (x) is passed through to listeners but never stored by Free.
		if ( ! $this->enabled() ) {
			return 'disabled';
		}

		$widget_id = absint( $input['w'] ?? 0 );
		$action_id = absint( $input['a'] ?? 0 );
		$device    = absint( $input['d'] ?? 0 );
		$object_id = absint( $input['o'] ?? 0 );
		if ( ! $widget_id || ! $action_id || $device < 1 || $device > 3 ) {
			return 'invalid';
		}

		$action_type = $this->resolve( $widget_id, $action_id );
		if ( null === $action_type ) {
			return 'invalid';
		}

		if ( $object_id ) {
			$status = get_post_status( $object_id );
			if ( 'publish' !== $status ) {
				$object_id = 0;
			}
		}

		if ( ! $this->allow( $client ) ) {
			return 'limited';
		}

		$event = array(
			'widget_id'   => $widget_id,
			'action_id'   => $action_id,
			'action_type' => $action_type,
			'device'      => $device,
			'object_id'   => $object_id ? $object_id : null,
		);

		/**
		 * Filters a click event before it is stored. Return false to skip it.
		 *
		 * @since 0.5.0
		 *
		 * @param array|false $event Event data (widget_id, action_id, action_type, device, object_id).
		 */
		$event = apply_filters( 'bigloop_cb/track_event', $event );
		if ( ! is_array( $event ) ) {
			return 'skipped';
		}

		$event['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$this->wpdb->insert( Schema::events( $this->wpdb ), $event, array( '%d', '%d', '%s', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$event['id'] = (int) $this->wpdb->insert_id;

		/**
		 * Fires after a click is stored.
		 *
		 * @since 0.5.0
		 * @since 0.6.0 Added $extra and the event id.
		 *
		 * @param array $event Stored event, including its id.
		 * @param array $extra Extension data sent by front-end listeners of `bigloop:before-track`
		 *                     (untrusted: at most 20 scalar values, each up to 200 characters).
		 */
		do_action( 'bigloop_cb/click_tracked', $event, self::extra( $input['x'] ?? null ) );

		return 'recorded';
	}

	/**
	 * Action type when the widget is live and uses the action, else null.
	 *
	 * @param int $widget_id Widget.
	 * @param int $action_id Action.
	 * @return string|null
	 */
	private function resolve( int $widget_id, int $action_id ): ?string {
		foreach ( $this->compiler->snapshot()['widgets'] as $widget ) {
			if ( (int) $widget['id'] !== $widget_id ) {
				continue;
			}
			foreach ( (array) $widget['actions'] as $action ) {
				if ( (int) $action['id'] === $action_id ) {
					return (string) $action['type'];
				}
			}
			return null;
		}
		return null;
	}

	/**
	 * Fixed-window rate limit per client.
	 *
	 * @param string $client Client identifier.
	 * @return bool
	 */
	private function allow( string $client ): bool {
		if ( '' === $client ) {
			return true;
		}
		$key   = 'blcb_rl_' . substr( hash_hmac( 'sha256', $client, wp_salt( 'nonce' ) ), 0, 24 );
		$count = (int) get_transient( $key );
		/**
		 * Filters the max clicks recorded per visitor every 10 minutes.
		 *
		 * @since 0.5.0
		 *
		 * @param int $limit Limit.
		 */
		if ( $count >= (int) apply_filters( 'bigloop_cb/track_rate_limit', self::RATE_LIMIT ) ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * Cleans extension data from the beacon: a flat map of at most 20 short scalar values.
	 *
	 * @param mixed $raw Raw value.
	 * @return array<string,string>
	 */
	public static function extra( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $key => $value ) {
			if ( count( $out ) >= 20 ) {
				break;
			}
			$key = substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $key ) ), 0, 32 );
			if ( '' === $key || ! is_scalar( $value ) ) {
				continue;
			}
			$out[ $key ] = substr( sanitize_text_field( (string) $value ), 0, 200 );
		}
		return $out;
	}
}
