<?php
/**
 * Elementor integration loader.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Contact Widget" Elementor widget. Nothing is loaded unless Elementor fires its registration hook,
 * so sites without Elementor pay no cost.
 */
final class Elementor {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'elementor/widgets/register', array( $this, 'register' ) );
	}

	/**
	 * Registers the widget.
	 *
	 * @param object $widgets_manager \Elementor\Widgets_Manager.
	 * @return void
	 */
	public function register( $widgets_manager ): void {
		if ( ! class_exists( '\Elementor\Widget_Base' ) || ! is_object( $widgets_manager ) || ! method_exists( $widgets_manager, 'register' ) ) {
			return;
		}
		$widgets_manager->register( new ContactWidget() );
	}
}
