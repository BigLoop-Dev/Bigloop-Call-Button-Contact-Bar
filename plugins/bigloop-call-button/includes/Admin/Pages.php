<?php
/**
 * Admin page registry (single source of truth for menu + SPA routes).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Each admin page is a real WordPress submenu page (so reloads, bookmarks and menu highlighting work);
 * the React app reads the route and switches screens without reloading.
 */
final class Pages {

	public const PARENT = 'bigloop-contact';

	/**
	 * Page definitions keyed by route.
	 *
	 * @return array<string, array{slug: string, title: string, menu: string}>
	 */
	public static function all(): array {
		$pages = array(
			'overview'  => array(
				'slug'  => self::PARENT,
				'title' => __( 'Overview', 'bigloop-call-button' ),
				'menu'  => __( 'Overview', 'bigloop-call-button' ),
			),
			'widgets'   => array(
				'slug'  => 'bigloop-contact-widgets',
				'title' => __( 'Contact Widgets', 'bigloop-call-button' ),
				'menu'  => __( 'Contact Widgets', 'bigloop-call-button' ),
			),
			'new'       => array(
				'slug'  => 'bigloop-contact-new',
				'title' => __( 'Create Widget', 'bigloop-call-button' ),
				'menu'  => __( 'Create Widget', 'bigloop-call-button' ),
			),
			'actions'   => array(
				'slug'  => 'bigloop-contact-actions',
				'title' => __( 'Actions', 'bigloop-call-button' ),
				'menu'  => __( 'Actions', 'bigloop-call-button' ),
			),
			'schedules' => array(
				'slug'  => 'bigloop-contact-schedules',
				'title' => __( 'Schedules', 'bigloop-call-button' ),
				'menu'  => __( 'Schedules', 'bigloop-call-button' ),
			),
			'analytics' => array(
				'slug'  => 'bigloop-contact-analytics',
				'title' => __( 'Analytics', 'bigloop-call-button' ),
				'menu'  => __( 'Analytics', 'bigloop-call-button' ),
			),
			'settings'  => array(
				'slug'  => 'bigloop-contact-settings',
				'title' => __( 'Settings', 'bigloop-call-button' ),
				'menu'  => __( 'Settings', 'bigloop-call-button' ),
			),
			'help'      => array(
				'slug'  => 'bigloop-contact-help',
				'title' => __( 'Help & Support', 'bigloop-call-button' ),
				'menu'  => __( 'Help & Support', 'bigloop-call-button' ),
			),
			'pro'       => array(
				'slug'  => 'bigloop-contact-pro',
				'title' => __( 'Pro & Licensing', 'bigloop-call-button' ),
				'menu'  => __( 'Pro & Licensing', 'bigloop-call-button' ),
			),
		);

		/**
		 * Filters admin pages. Pro may relabel or add pages.
		 *
		 * @since 0.1.0
		 *
		 * @param array $pages Pages keyed by route.
		 */
		return (array) apply_filters( 'bigloop_cb/admin_pages', $pages );
	}

	/**
	 * Route for a page slug, or null.
	 *
	 * @param string $slug Page slug.
	 * @return string|null
	 */
	public static function route_for_slug( string $slug ): ?string {
		foreach ( self::all() as $route => $page ) {
			if ( $page['slug'] === $slug ) {
				return $route;
			}
		}
		return null;
	}
}
