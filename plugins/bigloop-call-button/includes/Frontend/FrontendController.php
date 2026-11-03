<?php
/**
 * Decides which widgets appear on a page and prints them.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Frontend;

use Bigloop\CallButton\Conditions\ConditionRegistry;
use Bigloop\CallButton\Conditions\Context;
use Bigloop\CallButton\Core\Permissions;
use Bigloop\CallButton\Scheduler\ScheduleService;
use Bigloop\CallButton\Rendering\Renderer;
use Bigloop\CallButton\Rendering\RuntimeCompiler;

defined( 'ABSPATH' ) || exit;

/**
 * Flow: `wp` → choose widgets (filterable, so display rules can plug in) → enqueue assets only if something renders
 * → `wp_footer` prints one scoped <style> block and the widget markup.
 *
 * Preview: administrators can add `?blcb_preview={widget id}` to any front-end URL to see one widget, even disabled.
 */
final class FrontendController {

	public const STYLE_HANDLE  = 'bigloop-call-button';
	public const SCRIPT_HANDLE = 'bigloop-call-button';

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
	 * Widgets to print in the footer, keyed by id.
	 *
	 * @var array<int, array<string,mixed>>
	 */
	private $queue = array();

	/**
	 * Preview widget id (0 = not previewing).
	 *
	 * @var int
	 */
	private $preview = 0;

	/**
	 * Whether the footer has been printed.
	 *
	 * @var bool
	 */
	private $printed = false;

	/**
	 * Display-rule conditions.
	 *
	 * @var ConditionRegistry|null
	 */
	private $conditions;

	/**
	 * Schedules.
	 *
	 * @var ScheduleService|null
	 */
	private $schedules;

	/**
	 * Request context (lazy).
	 *
	 * @var Context|null
	 */
	private $context = null;

	/**
	 * Constructor.
	 *
	 * @param RuntimeCompiler        $compiler   Compiler.
	 * @param Renderer               $renderer   Renderer.
	 * @param ConditionRegistry|null $conditions Conditions.
	 * @param ScheduleService|null   $schedules  Schedules.
	 */
	public function __construct( RuntimeCompiler $compiler, Renderer $renderer, ?ConditionRegistry $conditions = null, ?ScheduleService $schedules = null ) {
		$this->compiler   = $compiler;
		$this->renderer   = $renderer;
		$this->conditions = $conditions;
		$this->schedules  = $schedules;
	}

	/**
	 * Request context, built once.
	 *
	 * @return Context
	 */
	private function context(): Context {
		if ( null === $this->context ) {
			$this->context = Context::from_wp();
		}
		return $this->context;
	}

	/**
	 * Adds request-time data (display rules, schedule state, reveal) to a compiled widget.
	 *
	 * @param array<string,mixed> $widget      Compiled widget.
	 * @param bool                $apply_rules Apply display rules (false for previews and explicit embeds).
	 * @return array<string,mixed>|null Null when the widget must not render on this request.
	 */
	public function with_runtime( array $widget, bool $apply_rules = true ): ?array {
		$c       = (array) $widget['config'];
		$runtime = array(
			'client' => null,
			'sched'  => null,
			'reveal' => null,
			'geo'    => '',
		);

		if ( $apply_rules && $this->conditions ) {
			$result = $this->conditions->evaluate( (array) ( $c['rules'] ?? array() ), $this->context() );
			if ( 'hide' === $result['result'] ) {
				return null;
			}
			if ( 'client' === $result['result'] ) {
				$runtime['client'] = array(
					'match' => 'any' === ( $c['rules']['match'] ?? 'all' ) ? 'any' : 'all',
					'items' => $result['client'],
				);
				foreach ( $result['client'] as $rule ) {
					if ( 'country' === $rule['type'] ) {
						$runtime['geo'] = rest_url( 'bigloop-call-button/v1/geo' );
					}
				}
			}
		}

		if ( ! empty( $c['schedule']['enabled'] ) && $this->schedules ) {
			$state = $this->schedules->state( (int) ( $c['schedule']['schedule_id'] ?? 0 ) );
			if ( $state ) {
				$runtime['sched'] = $state + array( 'hide_closed' => 'hide' === ( $c['schedule']['when_closed'] ?? 'swap' ) );
			}
		}

		if ( $apply_rules ) {
			$reveal = (array) ( $c['reveal'] ?? array() );
			if ( ! empty( $reveal['scroll'] ) || ! empty( $reveal['delay'] ) ) {
				$runtime['reveal'] = array(
					'scroll' => (int) ( $reveal['scroll'] ?? 0 ),
					'unit'   => '%' === ( $reveal['scroll_unit'] ?? 'px' ) ? '%' : 'px',
					'delay'  => (int) ( $reveal['delay'] ?? 0 ),
				);
			}
		}

		$widget['runtime'] = $runtime;
		return $widget;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp', array( $this, 'prepare' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'print_footer' ), 20 );
	}

	/**
	 * Registers front-end assets (also used by the block editor for inline previews).
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$asset = is_readable( BLCB_DIR . 'assets/build/frontend.asset.php' ) ? require BLCB_DIR . 'assets/build/frontend.asset.php' : array(
			'dependencies' => array(),
			'version'      => BLCB_VERSION,
		);
		wp_register_style( self::STYLE_HANDLE, BLCB_URL . 'assets/build/frontend.css', array(), $asset['version'] );
		wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
		wp_register_script(
			self::SCRIPT_HANDLE,
			BLCB_URL . 'assets/build/frontend.js',
			array(),
			$asset['version'],
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Whether this request should render widgets at all.
	 *
	 * @return bool
	 */
	private function is_renderable_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || is_robots() || is_trackback() || is_favicon() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		/**
		 * Filters whether floating widgets may render on this request.
		 *
		 * @since 0.2.0
		 *
		 * @param bool $render Whether to render.
		 */
		return (bool) apply_filters( 'bigloop_cb/render_request', true );
	}

	/**
	 * Chooses widgets for the current request.
	 *
	 * @return void
	 */
	public function prepare(): void {
		if ( ! $this->is_renderable_request() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch, capability-checked.
		$preview = isset( $_GET['blcb_preview'] ) ? absint( wp_unslash( $_GET['blcb_preview'] ) ) : 0;
		if ( $preview && Permissions::current_user_can_manage() ) {
			$widget = $this->compiler->compile_by_id( $preview );
			$widget = $widget ? $this->with_runtime( $widget, false ) : null;
			if ( $widget ) {
				$this->preview           = $preview;
				$this->queue[ $preview ] = $widget;
			}
			return;
		}

		foreach ( $this->compiler->snapshot()['widgets'] as $widget ) {
			/**
			 * Filters whether a widget is visible on the current request (display rules, schedules, etc.).
			 *
			 * @since 0.2.0
			 *
			 * @param bool  $visible Whether visible.
			 * @param array $widget  Compiled widget.
			 */
			if ( ! apply_filters( 'bigloop_cb/widget_visible', true, $widget ) ) {
				continue;
			}
			$widget = $this->with_runtime( $widget );
			if ( $widget ) {
				$this->queue[ (int) $widget['id'] ] = $widget;
			}
		}
	}

	/**
	 * Adds a widget to this page (used by the shortcode/block "floating" mode).
	 *
	 * @param array<string,mixed> $widget Compiled widget.
	 * @return void
	 */
	public function force( array $widget ): void {
		if ( $this->preview ) {
			return;
		}
		$widget = $this->with_runtime( $widget, false );
		if ( $widget ) {
			$this->queue[ (int) $widget['id'] ] = $widget;
			$this->enqueue_now();
		}
	}

	/**
	 * Enqueues assets when anything renders.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( $this->queue ) {
			$this->enqueue_now();
		}
	}

	/**
	 * Enqueues assets (safe to call late; WordPress prints late styles in the footer).
	 *
	 * @return void
	 */
	public function enqueue_now(): void {
		wp_enqueue_style( self::STYLE_HANDLE );
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Prints scoped CSS and markup.
	 *
	 * @return void
	 */
	public function print_footer(): void {
		if ( $this->printed || ! $this->queue ) {
			return;
		}
		$this->printed = true;

		$widgets = array_values( $this->queue );
		$html    = '<style id="blcb-inline-css">' . $this->renderer->css( $widgets ) . '</style>';
		$html   .= '<div id="blcb-root" class="blcb-root">';
		foreach ( $widgets as $widget ) {
			$html .= $this->renderer->floating( $widget );
		}
		if ( $this->preview ) {
			$html .= '<div class="blcb-preview-badge" role="status">' . esc_html(
				sprintf(
					/* translators: %s: widget name */
					__( 'Preview: %s', 'bigloop-call-button' ),
					(string) $this->queue[ $this->preview ]['title']
				)
			) . '</div>';
		}
		$html .= '</div>';

		/**
		 * Filters the final front-end markup.
		 *
		 * @since 0.2.0
		 *
		 * @param string $html    Markup.
		 * @param array  $widgets Compiled widgets.
		 */
		echo apply_filters( 'bigloop_cb/frontend_html', $html, $widgets ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts; SVG icons come from the build-time sanitised library.
	}

	/**
	 * Widgets queued for this request (for tests and integrations).
	 *
	 * @return array<int, array<string,mixed>>
	 */
	public function queued(): array {
		return $this->queue;
	}
}
