<?php
/**
 * Internal post types used as storage for widgets, actions, schedules and presets.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\PostTypes;

use Bigloop\CallButton\Core\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Non-public CPTs. All management happens through the plugin's own REST API and admin app.
 * Widget status convention: post_status "publish" = enabled, "draft" = disabled.
 */
final class PostTypes {

	public const WIDGET   = 'blcb_widget';
	public const ACTION   = 'blcb_action';
	public const SCHEDULE = 'blcb_schedule';
	public const PRESET   = 'blcb_preset';

	/**
	 * Registers the post types on init.
	 *
	 * @return void
	 */
	public function register(): void {
		$types = array(
			self::WIDGET   => __( 'Contact widgets', 'bigloop-call-button' ),
			self::ACTION   => __( 'Contact actions', 'bigloop-call-button' ),
			self::SCHEDULE => __( 'Schedules', 'bigloop-call-button' ),
			self::PRESET   => __( 'Design presets', 'bigloop-call-button' ),
		);

		$caps = array(
			'edit_post'              => Permissions::MANAGE,
			'read_post'              => Permissions::MANAGE,
			'delete_post'            => Permissions::MANAGE,
			'edit_posts'             => Permissions::MANAGE,
			'edit_others_posts'      => Permissions::MANAGE,
			'delete_posts'           => Permissions::MANAGE,
			'publish_posts'          => Permissions::MANAGE,
			'read_private_posts'     => Permissions::MANAGE,
			'delete_others_posts'    => Permissions::MANAGE,
			'delete_published_posts' => Permissions::MANAGE,
			'edit_published_posts'   => Permissions::MANAGE,
			'create_posts'           => Permissions::MANAGE,
		);

		foreach ( $types as $type => $label ) {
			register_post_type(
				$type,
				array(
					'label'               => $label,
					'public'              => false,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_ui'             => false,
					'show_in_menu'        => false,
					'show_in_nav_menus'   => false,
					'show_in_rest'        => false,
					'has_archive'         => false,
					'rewrite'             => false,
					'query_var'           => false,
					'can_export'          => true,
					'delete_with_user'    => false,
					'supports'            => array( 'title' ),
					'capabilities'        => $caps,
					'map_meta_cap'        => false,
				)
			);
		}
	}

	/**
	 * All plugin post types (used by uninstall and stats).
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::WIDGET, self::ACTION, self::SCHEDULE, self::PRESET );
	}
}
