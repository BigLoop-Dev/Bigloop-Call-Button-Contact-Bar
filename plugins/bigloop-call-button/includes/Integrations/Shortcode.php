<?php
/**
 * [bigloop_contact] shortcode and the shared embed logic used by the block.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Integrations;

use Bigloop\CallButton\Frontend\FrontendController;
use Bigloop\CallButton\Rendering\Renderer;
use Bigloop\CallButton\Rendering\RuntimeCompiler;

defined( 'ABSPATH' ) || exit;

/**
 * Usage:
 *   [bigloop_contact id="12"]                      Inline buttons in the content.
 *   [bigloop_contact id="12" align="center"]       Inline, centred.
 *   [bigloop_contact id="12" display="floating"]   Shows the widget as a floating widget on this page.
 */
final class Shortcode {

	public const TAG = 'bigloop_contact';

	/**
	 * Compiler.
	 *
	 * @var RuntimeCompiler
	 */
	private $compiler;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Front end.
	 *
	 * @var FrontendController
	 */
	private $frontend;

	/**
	 * Constructor.
	 *
	 * @param RuntimeCompiler    $compiler Compiler.
	 * @param Renderer           $renderer Renderer.
	 * @param FrontendController $frontend Front end.
	 */
	public function __construct( RuntimeCompiler $compiler, Renderer $renderer, FrontendController $frontend ) {
		$this->compiler = $compiler;
		$this->renderer = $renderer;
		$this->frontend = $frontend;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_shortcode( self::TAG, array( $this, 'shortcode' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'      => 0,
				'display' => 'inline',
				'align'   => 'left',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);
		return $this->embed( absint( $atts['id'] ), (string) $atts['display'], (string) $atts['align'] );
	}

	/**
	 * Renders an embedded widget. Disabled or empty widgets render nothing.
	 *
	 * @param int    $id      Widget id.
	 * @param string $display inline|floating.
	 * @param string $align   left|center|right.
	 * @return string
	 */
	public function embed( int $id, string $display = 'inline', string $align = 'left' ): string {
		if ( ! $id ) {
			return '';
		}
		$widget = null;
		foreach ( $this->compiler->snapshot()['widgets'] as $candidate ) {
			if ( (int) $candidate['id'] === $id ) {
				$widget = $candidate;
				break;
			}
		}
		if ( ! $widget ) {
			return '';
		}
		if ( 'floating' === $display ) {
			$this->frontend->force( $widget );
			return '';
		}
		$widget = $this->frontend->with_runtime( $widget, false );
		if ( ! $widget ) {
			return '';
		}
		$this->frontend->enqueue_now();
		return $this->renderer->inline( $widget, $align );
	}
}
