<?php
/**
 * Review and "buy me a coffee" notices, shown only inside the plugin's own screens.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Admin;

use Bigloop\CallButton\Core\Lifecycle;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\REST\Controller;
use Bigloop\CallButton\REST\RestServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Rules (WordPress.org guideline 11: no site-wide nags):
 * - Rendered by the plugin's React app, so they never appear on other admin screens.
 * - At most one notice at a time; each user can snooze ("Maybe later", 30 days) or dismiss it for good.
 * - Review: after 14 days of use and at least 10 recorded clicks, i.e. once the plugin has proven useful.
 * - Coffee: after 30 days, links to bigloop.dev; the `bigloop_cb/donate_url` filter can change or remove it.
 */
final class Notices extends Controller {

	public const META        = 'blcb_dismissed_notices';
	public const SNOOZE_DAYS = 30;

	/**
	 * Stats.
	 *
	 * @var StatsReader
	 */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param StatsReader $stats Stats.
	 */
	public function __construct( StatsReader $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'bigloop_cb/admin_boot_notices', array( $this, 'boot' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			RestServiceProvider::REST_NAMESPACE,
			'/notices/(?P<id>review|coffee)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'dismiss_request' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'mode' => array(
						'type'    => 'string',
						'enum'    => array( 'later', 'never', 'done' ),
						'default' => 'never',
					),
				),
			)
		);
	}

	/** Default "buy us a coffee" page. */
	public const DONATE_URL = 'https://bigloop.dev/buy-us-coffee/';

	/**
	 * Donation URL (empty = coffee notice disabled).
	 *
	 * @return string
	 */
	public function donate_url(): string {
		/**
		 * Filters the "buy us a coffee" link. Return '' to turn the coffee notice off.
		 *
		 * @since 0.5.0
		 *
		 * @param string $url URL.
		 */
		return esc_url_raw( (string) apply_filters( 'bigloop_cb/donate_url', self::DONATE_URL ) );
	}

	/**
	 * Notices for the current user (at most one).
	 *
	 * @param array<int, array<string,mixed>> $notices Notices from other sources.
	 * @return array<int, array<string,mixed>>
	 */
	public function boot( $notices ): array {
		$notices = (array) $notices;
		$current = $this->eligible( get_current_user_id(), time() );
		if ( $current ) {
			$notices[] = $current;
		}
		return $notices;
	}

	/**
	 * The notice a user should see now, if any.
	 *
	 * @param int $user_id User.
	 * @param int $now     Timestamp.
	 * @return array<string,mixed>|null
	 */
	public function eligible( int $user_id, int $now ): ?array {
		$installed = (int) get_option( Lifecycle::INSTALLED_AT, $now );
		$age_days  = (int) floor( max( 0, $now - $installed ) / DAY_IN_SECONDS );
		$state     = $this->state( $user_id );
		$open      = static function ( string $id ) use ( $state, $now ) {
			return ! isset( $state[ $id ] ) || ( $state[ $id ] > 0 && $state[ $id ] <= $now );
		};

		if ( $age_days >= 14 && $open( 'review' ) && $this->stats->total_clicks() >= 10 ) {
			return array(
				'id'   => 'review',
				'url'  => 'https://wordpress.org/support/plugin/bigloop-call-button/reviews/#new-post',
				'meta' => array( 'clicks' => $this->stats->total_clicks() ),
			);
		}
		$donate = $this->donate_url();
		if ( '' !== $donate && $age_days >= 30 && $open( 'coffee' ) ) {
			return array(
				'id'  => 'coffee',
				'url' => $donate,
			);
		}
		return null;
	}

	/**
	 * Per-user dismissal state: id => 0 (never again) or timestamp to show again after.
	 *
	 * @param int $user_id User.
	 * @return array<string,int>
	 */
	private function state( int $user_id ): array {
		$raw = get_user_meta( $user_id, self::META, true );
		return is_array( $raw ) ? array_map( 'intval', $raw ) : array();
	}

	/**
	 * Snoozes or dismisses a notice for a user.
	 *
	 * @param int    $user_id User.
	 * @param string $id      Notice id.
	 * @param string $mode    later|never|done.
	 * @param int    $now     Timestamp.
	 * @return void
	 */
	public function dismiss( int $user_id, string $id, string $mode, int $now ): void {
		$state        = $this->state( $user_id );
		$state[ $id ] = 'later' === $mode ? $now + self::SNOOZE_DAYS * DAY_IN_SECONDS : 0;
		update_user_meta( $user_id, self::META, $state );
	}

	/**
	 * REST: dismiss.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function dismiss_request( \WP_REST_Request $request ): \WP_REST_Response {
		$this->dismiss( get_current_user_id(), (string) $request['id'], (string) $request['mode'], time() );
		return new \WP_REST_Response( array( 'dismissed' => true ), 200 );
	}
}
