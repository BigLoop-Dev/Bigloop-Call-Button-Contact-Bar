<?php
/**
 * Capability handling.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Grants the plugin's own capability dynamically, so no roles are modified in the database.
 */
final class Permissions {

	/** Capability required to manage widgets, settings and analytics. */
	public const MANAGE = 'manage_bigloop_contact';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Hooks the dynamic capability grant.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'user_has_cap', array( $this, 'grant' ), 10, 4 );
	}

	/**
	 * Grants MANAGE to administrators (and optionally editors).
	 *
	 * @param array<string,bool> $allcaps All capabilities of the user.
	 * @param string[]           $caps    Required primitive capabilities.
	 * @param array<int,mixed>   $args    Requested capability and context.
	 * @param \WP_User           $user    User.
	 * @return array<string,bool>
	 */
	public function grant( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( self::MANAGE, (array) $caps, true ) ) {
			return $allcaps;
		}

		$granted = ! empty( $allcaps['manage_options'] );

		if ( ! $granted && $this->settings->get( 'access.allow_editors' ) ) {
			$granted = ! empty( $allcaps['edit_others_pages'] );
		}

		/**
		 * Filters whether a user may manage Bigloop Contact widgets and settings.
		 *
		 * @since 0.1.0
		 *
		 * @param bool     $granted Whether the capability is granted.
		 * @param \WP_User $user    User.
		 */
		$allcaps[ self::MANAGE ] = (bool) apply_filters( 'bigloop_cb/can_manage', $granted, $user );

		return $allcaps;
	}

	/**
	 * Whether the current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
