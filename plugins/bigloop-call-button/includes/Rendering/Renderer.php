<?php
/**
 * Renders compiled widgets to HTML + scoped CSS.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Rendering;

use Bigloop\CallButton\Actions\FieldValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Markup contract (stable class names; front-end CSS lives in assets/src/frontend/frontend.scss):
 *
 * - Floating single button:  div#blcb-w-{id}.blcb.blcb-w.blcb-w--button > a.blcb-fab
 * - Expandable menu:         div#blcb-w-{id}.blcb.blcb-w.blcb-w--menu > details.blcb-menu > summary.blcb-fab + ul.blcb-menu__list > li > a.blcb-item
 *   (native <details> works without JavaScript; the runtime adds Escape / outside-click handling)
 * - Contact bar:             nav#blcb-w-{id}.blcb.blcb-w.blcb-w--bar > ul.blcb-bar__list > li > a.blcb-bar__link
 * - Inline (shortcode/block): div.blcb.blcb-inline > a.blcb-inline__btn
 *
 * Breakpoints: mobile ≤ 767px, tablet 768–1024px, desktop ≥ 1025px.
 */
final class Renderer {

	public const MOBILE_MAX = 767;
	public const TABLET_MAX = 1024;

	/**
	 * Inline instance counter.
	 *
	 * @var int
	 */
	private $inline_count = 0;

	/**
	 * Floating widget markup (button, menu or bar).
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return string
	 */
	public function floating( array $w ): string {
		switch ( $w['config']['type'] ?? '' ) {
			case 'button':
				return $this->button( $w );
			case 'bar':
				return $this->bar( $w );
			default:
				return $this->menu( $w );
		}
	}

	/**
	 * Whether an action is visible right now given the widget's schedule state (server-side first paint;
	 * the browser re-checks against the embedded windows so cached pages stay correct).
	 *
	 * @param array<string,mixed> $w      Widget.
	 * @param array<string,mixed> $action Action.
	 * @return bool
	 */
	private function action_visible( array $w, array $action ): bool {
		$sched = $w['runtime']['sched'] ?? null;
		$when  = (string) ( $action['when'] ?? 'always' );
		if ( ! $sched || 'always' === $when ) {
			return true;
		}
		return ( 'open' === $when ) === (bool) $sched['open'];
	}

	/**
	 * Data attributes for an item that depends on the schedule.
	 *
	 * @param array<string,mixed> $w      Widget.
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private function item_attrs( array $w, array $action ): string {
		if ( empty( $w['runtime']['sched'] ) || 'always' === ( $action['when'] ?? 'always' ) ) {
			return '';
		}
		return ' data-blcb-when="' . esc_attr( (string) $action['when'] ) . '"' . ( $this->action_visible( $w, $action ) ? '' : ' hidden' );
	}

	/**
	 * Root data attributes for browser-side gates (schedule, client rules, reveal) and the initial hidden state.
	 *
	 * @param array<string,mixed> $w Widget.
	 * @return string
	 */
	private function runtime_attrs( array $w ): string {
		$rt     = (array) ( $w['runtime'] ?? array() );
		$attrs  = '';
		$hidden = false;

		if ( ! empty( $rt['sched'] ) ) {
			$attrs .= ' data-blcb-sched="' . esc_attr( (string) wp_json_encode( $rt['sched']['windows'] ) ) . '"';
			$attrs .= ' data-blcb-state="' . ( $rt['sched']['open'] ? 'open' : 'closed' ) . '"';
			if ( ! empty( $rt['sched']['hide_closed'] ) ) {
				$attrs .= ' data-blcb-hide-closed="1"';
				$hidden = $hidden || ! $rt['sched']['open'];
			}
			$visible = array_filter( $w['actions'], fn( $a ) => $this->action_visible( $w, $a ) );
			$hidden  = $hidden || ! $visible;
		}
		if ( ! empty( $rt['client'] ) ) {
			$attrs .= ' data-blcb-client="' . esc_attr( (string) wp_json_encode( $rt['client'] ) ) . '"';
			$hidden = true;
		}
		if ( ! empty( $rt['geo'] ) ) {
			$attrs .= ' data-blcb-geo="' . esc_url( (string) $rt['geo'] ) . '"';
		}
		if ( ! empty( $rt['reveal'] ) ) {
			$attrs .= ' data-blcb-reveal="' . esc_attr( (string) wp_json_encode( $rt['reveal'] ) ) . '"';
			$hidden = true;
		}
		return $attrs . ( $hidden ? ' hidden' : '' );
	}

	/**
	 * Single floating button.
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return string
	 */
	private function button( array $w ): string {
		$c          = $w['config'];
		$action     = $w['actions'][0];
		$show_label = ! empty( $c['content']['show_label'] );
		$label      = '' !== (string) ( $c['content']['label'] ?? '' ) ? (string) $c['content']['label'] : (string) $action['label'];
		$icon       = '' !== $w['icons']['main'] ? $w['icons']['main'] : $action['icon'];

		$inner = '<span class="blcb-fab__icon">' . $icon . '</span>';
		if ( $show_label ) {
			$inner .= '<span class="blcb-fab__label">' . esc_html( $label ) . '</span>';
		}
		$inner .= $this->tooltip( $action );

		$aria = '' !== $action['aria'] ? $action['aria'] : $label;

		return sprintf(
			'<div id="%1$s" class="%2$s" data-blcb-widget="%3$d"%5$s><span class="blcb-btnwrap"%6$s>%4$s</span></div>',
			esc_attr( 'blcb-w-' . $w['id'] ),
			esc_attr( $this->root_classes( $w, 'button', $show_label ? array( 'blcb-has-label' ) : array() ) ),
			(int) $w['id'],
			$this->link( $action, 'blcb-fab', $inner, $show_label && $aria === $label ? '' : $aria, $w['id'] ),
			$this->runtime_attrs( $w ),
			$this->item_attrs( $w, $action )
		);
	}

	/**
	 * Expandable menu.
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return string
	 */
	private function menu( array $w ): string {
		$c          = $w['config'];
		$show_label = ! empty( $c['content']['show_label'] );
		$label      = '' !== (string) ( $c['content']['label'] ?? '' ) ? (string) $c['content']['label'] : __( 'Contact us', 'bigloop-call-button' );
		$menu_label = '' !== (string) ( $c['content']['menu_label'] ?? '' ) ? (string) $c['content']['menu_label'] : __( 'Contact options', 'bigloop-call-button' );
		$hide_items = 'never' === ( $c['appearance']['item_labels'] ?? 'always' );

		$items = '';
		foreach ( $w['actions'] as $action ) {
			$inner  = '<span class="blcb-item__icon" style="' . esc_attr( $this->action_vars( $action ) ) . '">' . $action['icon'] . '</span>';
			$inner .= '<span class="blcb-item__label' . ( $hide_items ? ' blcb-sr' : '' ) . '">' . esc_html( $action['label'] ) . '</span>';
			$inner .= $hide_items ? $this->tooltip( $action, $action['label'] ) : '';
			$aria   = ( '' !== $action['aria'] && $action['aria'] !== $action['label'] ) ? $action['aria'] : '';
			$items .= '<li class="blcb-menu__item"' . $this->item_attrs( $w, $action ) . '>' . $this->link( $action, 'blcb-item', $inner, $aria, $w['id'] ) . '</li>';
		}

		$summary  = '<summary class="blcb-fab" aria-label="' . esc_attr( $label ) . '">';
		$summary .= '<span class="blcb-fab__icon blcb-fab__icon--open">' . $w['icons']['toggle'] . '</span>';
		$summary .= '<span class="blcb-fab__icon blcb-fab__icon--close">' . $w['icons']['close'] . '</span>';
		if ( $show_label ) {
			$summary .= '<span class="blcb-fab__label" aria-hidden="true">' . esc_html( $label ) . '</span>';
		}
		$summary .= '</summary>';

		return sprintf(
			'<div id="%1$s" class="%2$s" data-blcb-widget="%3$d"%7$s><details class="blcb-menu">%4$s<ul class="blcb-menu__list" aria-label="%5$s">%6$s</ul></details></div>',
			esc_attr( 'blcb-w-' . $w['id'] ),
			esc_attr( $this->root_classes( $w, 'menu', $show_label ? array( 'blcb-has-label' ) : array() ) ),
			(int) $w['id'],
			$summary,
			esc_attr( $menu_label ),
			$items,
			$this->runtime_attrs( $w )
		);
	}

	/**
	 * Sticky contact bar.
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return string
	 */
	private function bar( array $w ): string {
		$b      = (array) $w['config']['bar'];
		$layout = (string) ( $b['layout'] ?? 'icon-above' );
		$extra  = array(
			'blcb-bar--' . ( $b['placement'] ?? 'bottom' ),
			'blcb-bar--' . ( $b['width'] ?? 'full' ),
			'blcb-bar--' . $layout,
		);
		if ( ! empty( $b['dividers'] ) ) {
			$extra[] = 'blcb-bar--dividers';
		}
		if ( ! empty( $b['use_action_colors'] ) ) {
			$extra[] = 'blcb-bar--action-colors';
		}

		$items = '';
		foreach ( $w['actions'] as $action ) {
			$inner = '';
			if ( 'text-only' !== $layout ) {
				$inner .= '<span class="blcb-bar__icon">' . $action['icon'] . '</span>';
			}
			$inner .= '<span class="blcb-bar__label' . ( 'icon-only' === $layout ? ' blcb-sr' : '' ) . '">' . esc_html( $action['label'] ) . '</span>';
			$aria   = ( '' !== $action['aria'] && $action['aria'] !== $action['label'] ) ? $action['aria'] : '';
			$items .= '<li class="blcb-bar__item" style="' . esc_attr( $this->action_vars( $action ) ) . '"' . $this->item_attrs( $w, $action ) . '>' . $this->link( $action, 'blcb-bar__link', $inner, $aria, $w['id'] ) . '</li>';
		}

		$menu_label = '' !== (string) ( $w['config']['content']['menu_label'] ?? '' ) ? (string) $w['config']['content']['menu_label'] : __( 'Contact options', 'bigloop-call-button' );

		return sprintf(
			'<nav id="%1$s" class="%2$s" data-blcb-widget="%3$d" aria-label="%4$s"%6$s><ul class="blcb-bar__list">%5$s</ul></nav>',
			esc_attr( 'blcb-w-' . $w['id'] ),
			esc_attr( $this->root_classes( $w, 'bar', $extra ) ),
			(int) $w['id'],
			esc_attr( $menu_label ),
			$items,
			$this->runtime_attrs( $w )
		);
	}

	/**
	 * Inline button group (shortcode / block).
	 *
	 * @param array<string,mixed> $w     Compiled widget.
	 * @param string              $align left|center|right.
	 * @return string
	 */
	public function inline( array $w, string $align = 'left' ) {
		++$this->inline_count;
		$c     = $w['config'];
		$shape = (string) ( $c['appearance']['shape'] ?? 'rounded' );
		$links = '';
		foreach ( $w['actions'] as $action ) {
			$inner  = '<span class="blcb-inline__icon">' . $action['icon'] . '</span>';
			$inner .= '<span class="blcb-inline__label">' . esc_html( $action['label'] ) . '</span>';
			$aria   = ( '' !== $action['aria'] && $action['aria'] !== $action['label'] ) ? $action['aria'] : '';
			$link   = $this->link( $action, 'blcb-inline__btn', $inner, $aria, $w['id'], $this->action_vars( $action ) );
			$extra  = $this->item_attrs( $w, $action );
			$links .= '' === $extra ? $link : '<span class="blcb-inline__item"' . $extra . '>' . $link . '</span>';
		}
		$classes = array( 'blcb', 'blcb-inline', 'blcb-shape--' . ( 'circle' === $shape ? 'pill' : $shape ), 'blcb-inline--' . ( in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'left' ) );
		$style   = '--blcb-radius:' . (int) ( $c['appearance']['radius'] ?? 12 ) . 'px';
		return sprintf(
			'<div class="%1$s" data-blcb-widget="%2$d" style="%3$s"%5$s>%4$s</div>',
			esc_attr( implode( ' ', $classes ) ),
			(int) $w['id'],
			esc_attr( $style ),
			$links,
			$this->runtime_attrs( $w )
		);
	}

	/**
	 * Scoped CSS for floating widgets (custom properties, positions per device, device visibility, bar spacing).
	 *
	 * @param array<int, array<string,mixed>> $widgets Compiled widgets.
	 * @return string
	 */
	public function css( array $widgets ): string {
		$css = '';
		foreach ( $widgets as $w ) {
			$css .= $this->widget_css( $w );
		}
		return $css . $this->bar_clearance_css( $widgets );
	}

	/**
	 * Lifts floating widgets clear of a contact bar on the same screen edge and device,
	 * so a bottom bar never covers a bottom-anchored button (and likewise for top).
	 *
	 * @param array<int, array<string,mixed>> $widgets Compiled widgets.
	 * @return string
	 */
	private function bar_clearance_css( array $widgets ): string {
		$bars     = array_filter( $widgets, static fn( $w ) => 'bar' === ( $w['config']['type'] ?? '' ) );
		$floating = array_filter( $widgets, static fn( $w ) => 'bar' !== ( $w['config']['type'] ?? '' ) );
		if ( ! $bars || ! $floating ) {
			return '';
		}

		$css = '';
		foreach ( $this->device_queries() as $device => $query ) {
			// Tallest bar per edge on this device.
			$edge_height = array();
			foreach ( $bars as $bar ) {
				if ( empty( $bar['config']['visibility'][ $device ] ) ) {
					continue;
				}
				$edge                 = 'top' === ( $bar['config']['bar']['placement'] ?? 'bottom' ) ? 'top' : 'bottom';
				$base_h               = 'mobile' === $device && ! empty( $bar['config']['bar']['height_mobile'] ) ? (int) $bar['config']['bar']['height_mobile'] : (int) ( $bar['config']['bar']['height'] ?? 60 );
				$height               = $base_h + ( 'content' === ( $bar['config']['bar']['width'] ?? 'full' ) ? 12 : 0 );
				$edge_height[ $edge ] = max( $edge_height[ $edge ] ?? 0, $height );
			}
			if ( ! $edge_height ) {
				continue;
			}
			$rules = '';
			foreach ( $floating as $w ) {
				if ( empty( $w['config']['visibility'][ $device ] ) ) {
					continue;
				}
				$pos    = $this->position_for( $w['config'], $device );
				$anchor = (string) ( $pos['anchor'] ?? 'bottom-right' );
				$edge   = 0 === strpos( $anchor, 'top-' ) ? 'top' : ( 0 === strpos( $anchor, 'bottom-' ) ? 'bottom' : '' );
				if ( '' === $edge || empty( $edge_height[ $edge ] ) ) {
					continue;
				}
				$rules .= '#blcb-w-' . (int) $w['id'] . '{' . $edge . ':calc(' . ( (int) ( $pos['offset_y'] ?? 0 ) + $edge_height[ $edge ] ) . 'px + env(safe-area-inset-' . $edge . ',0px))}';
			}
			if ( '' !== $rules ) {
				$css .= '@media ' . $query . '{' . $rules . '}';
			}
		}
		return $css;
	}

	/**
	 * CSS for one widget.
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return string
	 */
	private function widget_css( array $w ): string {
		$c    = $w['config'];
		$id   = '#blcb-w-' . (int) $w['id'];
		$type = (string) $c['type'];
		$a    = (array) $c['appearance'];
		$t    = (array) ( $c['typography'] ?? array() );
		$vis  = (array) $c['visibility'];

		$out = $id . '{' . $this->declarations( $this->style_vars( $w ) ) . '}';

		// Per-device sizes (tablet and mobile inherit from the next larger screen when not set).
		$size  = $this->responsive( $a, 'size', 56 );
		$icon  = $this->responsive( $a, 'icon_size', 24 );
		$font  = $this->responsive( $t, 'size', 14 );
		$bar_h = array(
			'desktop' => (int) ( $c['bar']['height'] ?? 60 ),
			'tablet'  => (int) ( $c['bar']['height'] ?? 60 ),
			'mobile'  => (int) ( $c['bar']['height_mobile'] ?? $c['bar']['height'] ?? 60 ),
		);
		foreach ( array( 'tablet', 'mobile' ) as $device ) {
			$vars = array();
			if ( $size[ $device ] !== $size['desktop'] ) {
				$vars['--blcb-size'] = $size[ $device ] . 'px';
			}
			if ( $icon[ $device ] !== $icon['desktop'] ) {
				$vars['--blcb-icon'] = $icon[ $device ] . 'px';
			}
			if ( $font[ $device ] !== $font['desktop'] ) {
				$vars['--blcb-fs'] = $font[ $device ] . 'px';
			}
			if ( 'bar' === $type && $bar_h[ $device ] !== $bar_h['desktop'] ) {
				$vars['--blcb-bar-h'] = $bar_h[ $device ] . 'px';
			}
			if ( $vars ) {
				$out .= '@media ' . $this->device_queries()[ $device ] . '{' . $id . '{' . $this->declarations( $vars ) . '}}';
			}
		}

		// Squircle fallback (browsers without corner-shape): exact superellipse outline per pixel size.
		if ( 'squircle' === ( $a['shape'] ?? '' ) && 'bar' !== $type ) {
			foreach ( $this->device_queries() as $device => $query ) {
				$fab  = $size[ $device ];
				$item = (int) round( $fab * 0.82 );
				$out .= '@media ' . $query . '{' . $id . '{--blcb-sq-fab:path(\'' . self::squircle_path( $fab, $fab ) . '\');--blcb-sq-item:path(\'' . self::squircle_path( $item, $item ) . '\')}}';
			}
		}

		if ( 'bar' !== $type ) {
			foreach ( $this->device_queries() as $device => $query ) {
				$pos  = $this->position_for( $c, $device );
				$out .= '@media ' . $query . '{' . $id . '{' . $this->anchor_css( $pos ) . '}' . $this->panel_css( $id, (string) $pos['anchor'] ) . '}';
			}
		}

		// Device visibility.
		foreach ( $this->device_queries() as $device => $query ) {
			if ( empty( $vis[ $device ] ) ) {
				$out .= '@media ' . $query . '{' . $id . '{display:none!important}}';
			}
		}

		// Keep page content clear of the bar where it is visible.
		if ( 'bar' === $type && ! empty( $c['bar']['reserve_space'] ) ) {
			$side = 'top' === ( $c['bar']['placement'] ?? 'bottom' ) ? 'top' : 'bottom';
			foreach ( $this->device_queries() as $device => $query ) {
				if ( ! empty( $vis[ $device ] ) ) {
					// Only while the bar is actually shown (it can be hidden by its schedule or display rules).
					$out .= '@media ' . $query . '{body:has(' . $id . ':not([hidden])){padding-' . $side . ':calc(' . $bar_h[ $device ] . 'px + env(safe-area-inset-' . $side . ',0px))}}';
				}
			}
		}

		return $out;
	}

	/**
	 * CSS custom properties for a widget (base / desktop values).
	 *
	 * @param array<string,mixed> $w Compiled widget.
	 * @return array<string,string>
	 */
	public function style_vars( array $w ): array {
		$c    = $w['config'];
		$type = (string) $c['type'];
		$a    = (array) $c['appearance'];
		$t    = (array) ( $c['typography'] ?? array() );

		$first_bg = (string) ( $w['actions'][0]['bg'] ?? '#4338ca' );
		$color    = $this->color( $a['bg'] ?? '' ) ?: ( 'button' === $type ? $first_bg : '#4338ca' );
		$fg       = $this->color( $a['fg'] ?? '' ) ?: '#ffffff';
		$style    = (string) ( $a['style'] ?? 'solid' );

		$bg       = $color;
		$bg_image = 'none';
		$ring     = '';
		switch ( $style ) {
			case 'outline':
				$bg   = '#ffffff';
				$fg   = $color;
				$ring = '2px';
				break;
			case 'minimal':
				$bg = 'rgba(255,255,255,.94)';
				$fg = $color;
				break;
			case 'transparent':
				$bg = 'transparent';
				$fg = $color;
				break;
			case 'gradient':
				$to       = $this->color( $a['gradient_to'] ?? '' ) ?: $color;
				$bg_image = 'linear-gradient(' . (int) ( $a['gradient_angle'] ?? 135 ) . 'deg,' . $color . ',' . $to . ')';
				break;
		}
		$border_w = (int) ( $a['border_width'] ?? 0 );
		$border_c = $this->color( $a['border_color'] ?? '' ) ?: $color;
		if ( $border_w > 0 ) {
			$ring = $border_w . 'px';
		}

		$vars = array(
			'--blcb-z'         => (string) (int) ( $c['advanced']['z_index'] ?? 99990 ),
			'--blcb-size'      => (int) ( $a['size'] ?? 56 ) . 'px',
			'--blcb-icon'      => (int) ( $a['icon_size'] ?? 24 ) . 'px',
			'--blcb-gap'       => (int) ( $a['gap'] ?? 10 ) . 'px',
			'--blcb-bg'        => $bg,
			'--blcb-bg-image'  => $bg_image,
			'--blcb-fg'        => $fg,
			'--blcb-accent'    => $color,
			'--blcb-hover-bg'  => $this->color( $a['hover_bg'] ?? '' ) ?: $bg,
			'--blcb-hover-fg'  => $this->color( $a['hover_fg'] ?? '' ) ?: $fg,
			'--blcb-active-bg' => $this->color( $a['active_bg'] ?? '' ) ?: ( $this->color( $a['hover_bg'] ?? '' ) ?: $bg ),
			'--blcb-ring'      => '' !== $ring ? 'inset 0 0 0 ' . $ring . ' ' . ( 'outline' === $style && $border_w < 1 ? $color : $border_c ) : 'inset 0 0 0 0 transparent',
			'--blcb-radius'    => $this->radius( $a ),
			'--blcb-label-bg'  => $this->color( $a['label_bg'] ?? '' ) ?: '#ffffff',
			'--blcb-label-fg'  => $this->color( $a['label_fg'] ?? '' ) ?: '#0f172a',
			'--blcb-tip-bg'    => $this->color( $a['tooltip_bg'] ?? '' ) ?: '#0f172a',
			'--blcb-tip-fg'    => $this->color( $a['tooltip_fg'] ?? '' ) ?: '#ffffff',
			'--blcb-font'      => $this->font_stack( $t ),
			'--blcb-fs'        => (int) ( $t['size'] ?? 14 ) . 'px',
			'--blcb-fw'        => (string) (int) ( $t['weight'] ?? 600 ),
			'--blcb-lh'        => number_format( (int) ( $t['line_height'] ?? 120 ) / 100, 2, '.', '' ),
			'--blcb-ls'        => number_format( (int) ( $t['letter_spacing'] ?? 0 ) / 10, 1, '.', '' ) . 'px',
			'--blcb-tt'        => (string) ( $t['transform'] ?? 'none' ),
			'--blcb-ta'        => (string) ( $t['align'] ?? 'center' ),
		);

		if ( 'bar' === $type ) {
			$b                       = (array) $c['bar'];
			$bar_fg                  = $this->color( $b['fg'] ?? '' ) ?: '#0f172a';
			$vars['--blcb-bar-h']    = (int) ( $b['height'] ?? 60 ) . 'px';
			$vars['--blcb-bar-bg']   = $this->color( $b['bg'] ?? '' ) ?: '#ffffff';
			$vars['--blcb-bar-fg']   = $bar_fg;
			$vars['--blcb-bar-pad']  = (int) ( $b['padding'] ?? 6 ) . 'px';
			$vars['--blcb-bar-icon'] = (int) ( $b['icon_size'] ?? 22 ) . 'px';
			$vars['--blcb-divider']  = $this->color( $b['divider_color'] ?? '' ) ?: 'color-mix(in srgb,' . $bar_fg . ' 12%,transparent)';
		}
		return $vars;
	}

	/**
	 * Font stack for typography settings.
	 *
	 * @param array<string,mixed> $t Typography.
	 * @return string
	 */
	private function font_stack( array $t ): string {
		$system = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
		switch ( $t['font'] ?? 'system' ) {
			case 'inherit':
				return 'inherit';
			case 'serif':
				return 'Georgia,"Times New Roman",serif';
			case 'rounded':
				return 'ui-rounded,"SF Pro Rounded","Nunito","Varela Round",' . $system;
			case 'mono':
				return 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace';
			case 'custom':
				$name = (string) ( $t['custom_font'] ?? '' );
				return preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,59}$/', $name ) ? '"' . $name . '",' . $system : $system;
			default:
				return $system;
		}
	}

	/**
	 * Desktop / tablet / mobile values with inheritance (tablet ← desktop, mobile ← tablet).
	 *
	 * @param array<string,mixed> $group   Settings group.
	 * @param string              $key     Base key.
	 * @param int                 $default Default.
	 * @return array{desktop:int, tablet:int, mobile:int}
	 */
	private function responsive( array $group, string $key, int $default ): array {
		$desktop = (int) ( $group[ $key ] ?? $default );
		$tablet  = isset( $group[ $key . '_tablet' ] ) && null !== $group[ $key . '_tablet' ] ? (int) $group[ $key . '_tablet' ] : $desktop;
		$mobile  = isset( $group[ $key . '_mobile' ] ) && null !== $group[ $key . '_mobile' ] ? (int) $group[ $key . '_mobile' ] : $tablet;
		return array(
			'desktop' => $desktop,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		);
	}

	/**
	 * Position settings used on a device (tablet uses desktop unless customised).
	 *
	 * @param array<string,mixed> $c      Config.
	 * @param string              $device desktop|tablet|mobile.
	 * @return array<string,mixed>
	 */
	public function position_for( array $c, string $device ): array {
		$p = (array) ( $c['position'] ?? array() );
		if ( 'tablet' === $device ) {
			return ! empty( $p['tablet_custom'] ) && ! empty( $p['tablet'] ) ? (array) $p['tablet'] : (array) ( $p['desktop'] ?? array() );
		}
		return (array) ( $p[ $device ] ?? $p['desktop'] ?? array() );
	}

	/**
	 * SVG path for a superellipse (|x/a|^n + |y/b|^n = 1, n = 5) filling a w×h box.
	 * Used as the squircle fallback via clip-path where `corner-shape: squircle` is unsupported.
	 *
	 * @param int   $w Width in px.
	 * @param int   $h Height in px.
	 * @param float $n Exponent.
	 * @return string
	 */
	public static function squircle_path( int $w, int $h, float $n = 5.0 ): string {
		$a     = $w / 2;
		$b     = $h / 2;
		$steps = 72;
		$d     = '';
		for ( $i = 0; $i < $steps; $i++ ) {
			$t   = 2 * M_PI * $i / $steps;
			$cos = cos( $t );
			$sin = sin( $t );
			$x   = $a + $a * ( $cos <=> 0 ) * pow( abs( $cos ), 2 / $n );
			$y   = $b + $b * ( $sin <=> 0 ) * pow( abs( $sin ), 2 / $n );
			$d  .= ( 0 === $i ? 'M' : 'L' ) . rtrim( rtrim( number_format( $x, 2, '.', '' ), '0' ), '.' ) . ' ' . rtrim( rtrim( number_format( $y, 2, '.', '' ), '0' ), '.' );
		}
		return $d . 'Z';
	}

	/**
	 * Media queries per device.
	 *
	 * @return array<string,string>
	 */
	public function device_queries(): array {
		return array(
			'mobile'  => '(max-width:' . self::MOBILE_MAX . 'px)',
			'tablet'  => '(min-width:' . ( self::MOBILE_MAX + 1 ) . 'px) and (max-width:' . self::TABLET_MAX . 'px)',
			'desktop' => '(min-width:' . ( self::TABLET_MAX + 1 ) . 'px)',
		);
	}

	/**
	 * Fixed-position rules for an anchor.
	 *
	 * @param array<string,mixed> $p Position (anchor, offset_x, offset_y).
	 * @return string
	 */
	private function anchor_css( array $p ): string {
		$x      = (int) ( $p['offset_x'] ?? 24 ) . 'px';
		$y      = (int) ( $p['offset_y'] ?? 24 ) . 'px';
		$anchor = (string) ( $p['anchor'] ?? 'bottom-right' );

		$v = 'top:auto;bottom:auto;left:auto;right:auto;transform:none;';
		switch ( $anchor ) {
			case 'bottom-left':
				return $v . "bottom:calc({$y} + env(safe-area-inset-bottom,0px));left:calc({$x} + env(safe-area-inset-left,0px));";
			case 'bottom-center':
				return $v . "bottom:calc({$y} + env(safe-area-inset-bottom,0px));left:50%;transform:translateX(-50%);";
			case 'top-right':
				return $v . "top:calc({$y} + env(safe-area-inset-top,0px));right:calc({$x} + env(safe-area-inset-right,0px));";
			case 'top-left':
				return $v . "top:calc({$y} + env(safe-area-inset-top,0px));left:calc({$x} + env(safe-area-inset-left,0px));";
			case 'middle-right':
				return $v . "top:50%;right:calc({$x} + env(safe-area-inset-right,0px));transform:translateY(-50%);";
			case 'middle-left':
				return $v . "top:50%;left:calc({$x} + env(safe-area-inset-left,0px));transform:translateY(-50%);";
			case 'bottom-right':
			default:
				return $v . "bottom:calc({$y} + env(safe-area-inset-bottom,0px));right:calc({$x} + env(safe-area-inset-right,0px));";
		}
	}

	/**
	 * Direction/alignment of the expanded menu for an anchor.
	 *
	 * @param string $id     Widget selector.
	 * @param string $anchor Anchor.
	 * @return string
	 */
	private function panel_css( string $id, string $anchor ): string {
		$down  = 0 === strpos( $anchor, 'top-' );
		$left  = false !== strpos( $anchor, 'left' );
		$mid   = 'bottom-center' === $anchor;
		$rules = $down ? 'top:calc(100% + var(--blcb-gap,12px));bottom:auto;flex-direction:column;' : 'bottom:calc(100% + var(--blcb-gap,12px));top:auto;flex-direction:column-reverse;';
		if ( $mid ) {
			$rules .= 'left:50%;right:auto;transform:translateX(-50%);align-items:center;';
		} elseif ( $left ) {
			$rules .= 'left:0;right:auto;transform:none;align-items:flex-start;';
		} else {
			$rules .= 'right:0;left:auto;transform:none;align-items:flex-end;';
		}
		$item = $left ? 'flex-direction:row;' : 'flex-direction:row-reverse;';
		return $id . ' .blcb-menu__list{' . $rules . '}' . $id . ' .blcb-item{' . $item . '}';
	}

	/**
	 * Corner radius for the widget shape.
	 *
	 * @param array<string,mixed> $a Appearance.
	 * @return string
	 */
	private function radius( array $a ): string {
		switch ( $a['shape'] ?? 'circle' ) {
			case 'circle':
			case 'pill':
				return '999px';
			case 'square':
				return '0px';
			case 'squircle':
				return '30%';
			case 'rounded':
				return '14px';
			default:
				return (int) ( $a['radius'] ?? 16 ) . 'px';
		}
	}

	/**
	 * Root classes.
	 *
	 * @param array<string,mixed> $w     Widget.
	 * @param string              $type  Type.
	 * @param string[]            $extra Extra classes.
	 * @return string
	 */
	private function root_classes( array $w, string $type, array $extra = array() ): string {
		$a       = (array) $w['config']['appearance'];
		$classes = array_merge(
			array(
				'blcb',
				'blcb-w',
				'blcb-w--' . $type,
				'blcb-shape--' . ( $a['shape'] ?? 'circle' ),
				'blcb-shadow--' . ( $a['shadow'] ?? 'md' ),
			),
			$extra
		);
		if ( 'bar' !== $type && false !== strpos( (string) ( $w['config']['position']['desktop']['anchor'] ?? '' ), 'left' ) ) {
			$classes[] = 'blcb-anchor-left';
		}
		$e         = (array) ( $w['config']['effects'] ?? array() );
		$attention = (string) ( $e['attention'] ?? ( ! empty( $a['pulse'] ) ? 'pulse' : 'none' ) );
		if ( 'bar' !== $type && 'none' !== $attention ) {
			$classes[] = 'pulse' === $attention ? 'blcb-pulse' : 'blcb-attn--' . $attention;
		}
		$classes[] = 'blcb-style--' . ( $a['style'] ?? 'solid' );
		$classes[] = 'blcb-hover--' . ( $e['hover'] ?? 'lift' );
		$classes[] = 'blcb-enter--' . ( $e['entrance'] ?? 'fade' );
		if ( ! empty( $e['rotate_icon'] ) ) {
			$classes[] = 'blcb-rotate';
		}
		if ( isset( $e['animations'] ) && ! $e['animations'] ) {
			$classes[] = 'blcb-noanim';
		}
		if ( 'bar' === $type && empty( $w['config']['bar']['equal_width'] ?? true ) ) {
			$classes[] = 'blcb-bar--natural';
		}
		if ( 'bar' === $type && isset( $w['config']['bar']['shadow'] ) && ! $w['config']['bar']['shadow'] ) {
			$classes[] = 'blcb-bar--flat';
		}
		if ( ! empty( $w['config']['advanced']['css_class'] ) ) {
			$classes[] = (string) $w['config']['advanced']['css_class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * An action link.
	 *
	 * @param array<string,mixed> $action    Compiled action.
	 * @param string              $class     Class.
	 * @param string              $inner     Inner HTML (already escaped).
	 * @param string              $aria      Accessible name override ('' = use content).
	 * @param int                 $widget_id Widget id.
	 * @param string              $style     Inline style.
	 * @return string
	 */
	private function link( array $action, string $class, string $inner, string $aria, int $widget_id, string $style = '' ): string {
		$protocols = array_unique( array_merge( wp_allowed_protocols(), FieldValidator::CUSTOM_PROTOCOLS ) );
		$attrs     = array(
			'class'            => $class,
			'href'             => esc_url( (string) $action['href'], $protocols ),
			'data-blcb-action' => (string) (int) $action['id'],
			'data-blcb-type'   => (string) $action['type'],
			'data-blcb-widget' => (string) $widget_id,
		);
		if ( '' !== $aria ) {
			$attrs['aria-label'] = $aria;
		}
		if ( ! empty( $action['new_tab'] ) ) {
			$attrs['target'] = '_blank';
			$attrs['rel']    = 'noopener noreferrer';
		}
		if ( '' !== $style ) {
			$attrs['style'] = $style;
		}
		$html = '<a';
		foreach ( $attrs as $k => $v ) {
			$html .= ' ' . $k . '="' . ( 'href' === $k ? $v : esc_attr( $v ) ) . '"';
		}
		return $html . '>' . $inner . '</a>';
	}

	/**
	 * Visual tooltip (hidden from assistive tech; the link has an accessible name already).
	 *
	 * @param array<string,mixed> $action   Action.
	 * @param string              $fallback Text when the action has no tooltip.
	 * @return string
	 */
	private function tooltip( array $action, string $fallback = '' ): string {
		$text = '' !== (string) $action['tooltip'] ? (string) $action['tooltip'] : $fallback;
		return '' === $text ? '' : '<span class="blcb-tip" aria-hidden="true">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Per-action colour variables.
	 *
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private function action_vars( array $action ): string {
		return '--blcb-a-bg:' . ( $this->color( $action['bg'] ) ?: '#4338ca' ) . ';--blcb-a-fg:' . ( $this->color( $action['fg'] ) ?: '#ffffff' );
	}

	/**
	 * Re-checks a colour before it reaches CSS.
	 *
	 * @param mixed $value Colour.
	 * @return string '' when invalid.
	 */
	private function color( $value ): string {
		return ( is_string( $value ) && preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) ? $value : '';
	}

	/**
	 * CSS declarations from a map.
	 *
	 * @param array<string,string> $vars Vars.
	 * @return string
	 */
	private function declarations( array $vars ): string {
		$out = '';
		foreach ( $vars as $k => $v ) {
			$out .= $k . ':' . $v . ';';
		}
		return $out;
	}
}
