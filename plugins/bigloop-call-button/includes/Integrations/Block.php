<?php
/**
 * Gutenberg block "bigloop/contact-widget" (dynamic, server-rendered).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Integrations;

use Bigloop\CallButton\Frontend\FrontendController;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block from blocks/contact-widget/block.json; rendering shares the shortcode's embed logic.
 */
final class Block {

	public const EDITOR_HANDLE = 'bigloop-call-button-block-editor';

	/**
	 * Shortcode (shared embed logic).
	 *
	 * @var Shortcode
	 */
	private $shortcode;

	/**
	 * Constructor.
	 *
	 * @param Shortcode $shortcode Shortcode.
	 */
	public function __construct( Shortcode $shortcode ) {
		$this->shortcode = $shortcode;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/**
	 * Registers the editor script and the block.
	 *
	 * @return void
	 */
	public function register(): void {
		$asset_file = BLCB_DIR . 'assets/build/block.asset.php';
		if ( is_readable( $asset_file ) ) {
			$asset = require $asset_file;
			wp_register_script( self::EDITOR_HANDLE, BLCB_URL . 'assets/build/block.js', $asset['dependencies'], $asset['version'], true );
			wp_set_script_translations( self::EDITOR_HANDLE, 'bigloop-call-button', BLCB_DIR . 'languages' );
		}

		register_block_type(
			BLCB_DIR . 'blocks/contact-widget',
			array(
				'render_callback' => array( $this, 'render' ),
				'editor_script'   => self::EDITOR_HANDLE,
				'style'           => FrontendController::STYLE_HANDLE,
			)
		);
	}

	/**
	 * Server render.
	 *
	 * @param array<string,mixed> $attributes Attributes.
	 * @return string
	 */
	public function render( $attributes ): string {
		$html = $this->shortcode->embed(
			absint( $attributes['widgetId'] ?? 0 ),
			'floating' === ( $attributes['display'] ?? 'inline' ) ? 'floating' : 'inline',
			(string) ( $attributes['align'] ?? 'left' )
		);
		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}
}
