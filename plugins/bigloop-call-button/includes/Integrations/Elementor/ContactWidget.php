<?php
/**
 * Elementor widget: embed a Bigloop contact widget.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Integrations\Elementor;

use Bigloop\CallButton\Core\Plugin;
use Bigloop\CallButton\Integrations\Shortcode;
use Bigloop\CallButton\Repositories\WidgetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Same output as the [bigloop_contact] shortcode and the block: inline buttons, or the floating widget on this page.
 * This file is only loaded from the `elementor/widgets/register` hook, when Elementor's classes exist.
 */
final class ContactWidget extends \Elementor\Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bigloop_contact';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Contact Widget', 'bigloop-call-button' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-call-to-action';
	}

	/**
	 * Categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'call', 'phone', 'whatsapp', 'contact', 'button', 'bigloop' );
	}

	/**
	 * Widget choices for the select control.
	 *
	 * @return array<string,string>
	 */
	private function widget_options(): array {
		$options = array( '' => __( '— Choose a widget —', 'bigloop-call-button' ) );
		$repo    = Plugin::instance()->container()->get( WidgetRepository::class );
		foreach ( $repo->list( array( 'per_page' => 100 ) )['items'] as $widget ) {
			$options[ (string) $widget['id'] ] = $widget['enabled']
				? (string) $widget['title']
				/* translators: %s: widget name */
				: sprintf( __( '%s (disabled)', 'bigloop-call-button' ), (string) $widget['title'] );
		}
		return $options;
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_widget',
			array( 'label' => __( 'Contact widget', 'bigloop-call-button' ) )
		);
		$this->add_control(
			'widget_id',
			array(
				'label'   => __( 'Widget', 'bigloop-call-button' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => $this->widget_options(),
				'default' => '',
			)
		);
		$this->add_control(
			'display',
			array(
				'label'   => __( 'Display', 'bigloop-call-button' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'inline'   => __( 'Buttons in the page', 'bigloop-call-button' ),
					'floating' => __( 'Floating on this page', 'bigloop-call-button' ),
				),
				'default' => 'inline',
			)
		);
		$this->add_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'bigloop-call-button' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'left'   => array(
						'title' => __( 'Left', 'bigloop-call-button' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Center', 'bigloop-call-button' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Right', 'bigloop-call-button' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'condition' => array( 'display' => 'inline' ),
			)
		);
		$this->add_control(
			'manage_note',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => sprintf(
					/* translators: %s: link to the widgets screen */
					esc_html__( 'Design the widget and its actions in %s.', 'bigloop-call-button' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=bigloop-contact-widgets' ) ) . '" target="_blank">' . esc_html__( 'Bigloop Contact → Contact Widgets', 'bigloop-call-button' ) . '</a>'
				),
				'content_classes' => 'elementor-descriptor',
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Whether Elementor is rendering inside its editor.
	 *
	 * @return bool
	 */
	private function is_editor(): bool {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * Output.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$id       = absint( $settings['widget_id'] ?? 0 );
		$display  = 'floating' === ( $settings['display'] ?? 'inline' ) ? 'floating' : 'inline';
		$align    = in_array( $settings['align'] ?? 'left', array( 'left', 'center', 'right' ), true ) ? $settings['align'] : 'left';

		$html = $id ? Plugin::instance()->container()->get( Shortcode::class )->embed( $id, $display, $align ) : '';

		if ( '' === $html && $this->is_editor() ) {
			if ( ! $id ) {
				$message = __( 'Choose a contact widget in the panel.', 'bigloop-call-button' );
			} elseif ( 'floating' === $display ) {
				$message = __( 'This widget floats on the page. Preview the page to see it.', 'bigloop-call-button' );
			} else {
				$message = __( 'This contact widget is disabled or has no active actions.', 'bigloop-call-button' );
			}
			echo '<div class="elementor-alert elementor-alert-info">' . esc_html( $message ) . '</div>';
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the plugin's escaping renderer.
	}
}
