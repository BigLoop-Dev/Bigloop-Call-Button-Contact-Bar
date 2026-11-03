<?php
/**
 * Admin menu and React app loader.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Admin;

use Bigloop\CallButton\API\Api;
use Bigloop\CallButton\Core\Permissions;
use Bigloop\CallButton\REST\RestServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Registers menu pages and loads the admin bundle on plugin screens only.
 */
final class AdminApp {

	public const HANDLE = 'bigloop-call-button-admin';

	/**
	 * Hook suffixes of the plugin's admin pages.
	 *
	 * @var string[]
	 */
	private $hooks = array();

	/**
	 * Registers the top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$pages  = Pages::all();
		$parent = Pages::PARENT;

		$this->hooks[] = (string) add_menu_page(
			__( 'Bigloop Contact', 'bigloop-call-button' ),
			__( 'Bigloop Contact', 'bigloop-call-button' ),
			Permissions::MANAGE,
			$parent,
			array( $this, 'render' ),
			$this->menu_icon(),
			58.7
		);

		foreach ( $pages as $page ) {
			$hook = add_submenu_page(
				$parent,
				$page['title'] . ' ‹ ' . __( 'Bigloop Contact', 'bigloop-call-button' ),
				$page['menu'],
				Permissions::MANAGE,
				$page['slug'],
				array( $this, 'render' )
			);
			if ( $hook ) {
				$this->hooks[] = (string) $hook;
			}
		}
	}

	/**
	 * Whether the current admin screen belongs to the plugin.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return bool
	 */
	private function is_plugin_screen( string $hook_suffix ): bool {
		return in_array( $hook_suffix, $this->hooks, true );
	}

	/**
	 * Mount point. Everything else is rendered by React.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="wrap blcb-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Bigloop Contact', 'bigloop-call-button' ) . '</h1>';
		echo '<div id="blcb-admin-root" class="blcb-app" aria-live="polite"><div class="blcb-boot"><span class="blcb-boot__spinner" aria-hidden="true"></span>' . esc_html__( 'Loading…', 'bigloop-call-button' ) . '</div></div>';
		echo '<noscript><div class="notice notice-error"><p>' . esc_html__( 'Bigloop Contact needs JavaScript enabled in your browser to show its dashboard.', 'bigloop-call-button' ) . '</p></div></noscript>';
		echo '</div>';
	}

	/**
	 * Enqueues the admin bundle on plugin screens.
	 *
	 * @param string $hook_suffix Current hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ): void {
		if ( ! $this->is_plugin_screen( (string) $hook_suffix ) ) {
			return;
		}

		$asset_file = BLCB_DIR . 'assets/build/admin.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Bigloop Contact admin assets are missing. If you installed from source, run "npm run build".', 'bigloop-call-button' ) . '</p></div>';
				}
			);
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( self::HANDLE, BLCB_URL . 'assets/build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( self::HANDLE, 'bigloop-call-button', BLCB_DIR . 'languages' );

		if ( is_readable( BLCB_DIR . 'assets/build/admin.css' ) ) {
			wp_enqueue_style( self::HANDLE, BLCB_URL . 'assets/build/admin.css', array(), $asset['version'] );
			wp_style_add_data( self::HANDLE, 'rtl', 'replace' );
		}

		wp_add_inline_script( self::HANDLE, 'window.blcbAdmin = ' . wp_json_encode( $this->boot_data() ) . ';', 'before' );
	}

	/**
	 * Data the admin app needs at boot.
	 *
	 * @return array<string, mixed>
	 */
	private function boot_data(): array {
		$pages = array();
		foreach ( Pages::all() as $route => $page ) {
			$pages[ $route ] = array(
				'slug'  => $page['slug'],
				'title' => $page['title'],
				'url'   => admin_url( 'admin.php?page=' . $page['slug'] ),
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing parameter.
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		$user = wp_get_current_user();

		/**
		 * Filters external links shown in the dashboard.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string,string> $links Links.
		 */
		$links = (array) apply_filters(
			'bigloop_cb/admin_links',
			array(
				'website' => 'https://bigloop.dev/',
				'support' => 'https://wordpress.org/support/plugin/bigloop-call-button/',
				'review'  => 'https://wordpress.org/support/plugin/bigloop-call-button/reviews/#new-post',
			)
		);

		return array(
			'route'         => Pages::route_for_slug( $slug ) ?? 'overview',
			'pages'         => $pages,
			'version'       => BLCB_VERSION,
			'apiVersion'    => Api::VERSION,
			'restNamespace' => RestServiceProvider::REST_NAMESPACE,
			'links'         => array_map( 'esc_url_raw', $links ),
			'user'          => array( 'displayName' => $user->display_name ),
			'siteName'      => get_bloginfo( 'name' ),
			'homeUrl'       => home_url( '/' ),
			'iconsUrl'      => BLCB_URL . 'assets/icons/icons.json?ver=' . rawurlencode( BLCB_VERSION ),
			'frontendCss'   => BLCB_URL . 'assets/build/frontend.css?ver=' . rawurlencode( BLCB_VERSION ),
			'frontendJs'    => BLCB_URL . 'assets/build/frontend.js?ver=' . rawurlencode( BLCB_VERSION ),
			/**
			 * Filters notices shown inside the plugin's screens (never site-wide).
			 *
			 * @since 0.5.0
			 *
			 * @param array $notices Notices: { id, url, meta }.
			 */
			'notices'       => array_values( (array) apply_filters( 'bigloop_cb/admin_boot_notices', array() ) ),
		);
	}

	/**
	 * Adds a body class on plugin screens for full-bleed layout styles.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, $this->hooks, true ) ) {
			$classes .= ' blcb-admin-screen';
		}
		return (string) $classes;
	}

	/**
	 * Adds "Dashboard" and "Settings" links on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ): array {
		$pages = Pages::all();
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . $pages['overview']['slug'] ) ), esc_html__( 'Dashboard', 'bigloop-call-button' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . $pages['settings']['slug'] ) ), esc_html__( 'Settings', 'bigloop-call-button' ) )
		);
		return (array) $links;
	}

	/**
	 * Menu icon as an SVG data URI (WordPress recolours it to match the admin scheme).
	 *
	 * @return string
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1.5c-4.7 0-8.5 3.4-8.5 7.6 0 2.2 1 4.1 2.7 5.5l-.6 3.4 3.6-1.8c.9.3 1.8.4 2.8.4 4.7 0 8.5-3.4 8.5-7.5S14.7 1.5 10 1.5zm3.4 10.2-.6.6c-.4.4-1.6.6-3.6-.7-1.2-.8-2.2-1.9-2.9-3.1-.9-1.6-.6-2.6-.2-3l.6-.6c.3-.3.7-.3 1 0l1 1.1c.3.3.3.7 0 1l-.4.4c.3.8 1.2 1.7 2 2l.4-.4c.3-.3.7-.3 1 0l1.1 1c.3.3.3.7 0 1z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- standard WP menu icon format.
	}
}
