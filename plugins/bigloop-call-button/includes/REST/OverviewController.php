<?php
/**
 * Dashboard overview endpoint.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\API\Api;
use Bigloop\CallButton\Database\Migrator;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\PostTypes\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * GET /overview: counts, click stats, recent activity, system and edition status.
 */
final class OverviewController extends Controller {

	/**
	 * Stats.
	 *
	 * @var StatsReader
	 */
	private $stats;

	/**
	 * Migrator.
	 *
	 * @var Migrator
	 */
	private $migrator;

	/**
	 * Constructor.
	 *
	 * @param StatsReader $stats    Stats.
	 * @param Migrator    $migrator Migrator.
	 */
	public function __construct( StatsReader $stats, Migrator $migrator ) {
		$this->stats    = $stats;
		$this->migrator = $migrator;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			RestServiceProvider::REST_NAMESPACE,
			'/overview',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_overview' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Builds the overview.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_overview(): \WP_REST_Response {
		$widgets = wp_count_posts( PostTypes::WIDGET );
		$actions = wp_count_posts( PostTypes::ACTION );

		$enabled  = (int) ( $widgets->publish ?? 0 );
		$disabled = (int) ( $widgets->draft ?? 0 );

		$by_type = $this->stats->clicks_by_action_type( 30 );
		$recent  = $this->stats->recent_events( 8 );

		$titles = array();
		foreach ( array_unique( wp_list_pluck( $recent, 'widget_id' ) ) as $id ) {
			$titles[ $id ] = get_the_title( $id );
		}
		foreach ( $recent as &$event ) {
			$event['widget_title'] = $titles[ $event['widget_id'] ] ?? '';
		}
		unset( $event );

		/**
		 * Filters edition status shown on the dashboard. Pro reports itself here.
		 *
		 * @since 0.1.0
		 *
		 * @param array{active: bool, version: string|null, license: array|null} $pro Pro status.
		 */
		$pro = (array) apply_filters(
			'bigloop_cb/pro_status',
			array(
				'active'  => false,
				'version' => null,
				'license' => null,
			)
		);

		return rest_ensure_response(
			array(
				'widgets' => array(
					'total'    => $enabled + $disabled,
					'enabled'  => $enabled,
					'disabled' => $disabled,
				),
				'actions' => array(
					'total' => (int) ( $actions->publish ?? 0 ),
				),
				'clicks'  => array(
					'total'      => $this->stats->total_clicks(),
					'last_30'    => array_sum( wp_list_pluck( $by_type, 'clicks' ) ),
					'by_type'    => $by_type,
					'top_action' => $by_type[0] ?? null,
				),
				'recent'  => $recent,
				'system'  => array(
					'plugin_version' => BLCB_VERSION,
					'api_version'    => Api::VERSION,
					'db_version'     => $this->migrator->current_version(),
					'db_latest'      => $this->migrator->latest_version(),
					'db_error'       => $this->migrator->last_error(),
					'php_version'    => PHP_VERSION,
					'wp_version'     => get_bloginfo( 'version' ),
					'multisite'      => is_multisite(),
				),
				'pro'     => $pro,
			)
		);
	}
}
