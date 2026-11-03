<?php
/**
 * Validates contact widget documents.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Domain;

use Bigloop\CallButton\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Widget document (JSON in post meta; title = post_title; enabled = post_status publish):
 *
 *   {
 *     schema, type: button|menu|bar,
 *     actions: [ { id, label } ],          // ordered references to the action library, label overrides
 *     content, appearance, position: { desktop, mobile }, bar, visibility, advanced,
 *     ext: { … }                            // extension data (e.g. ext.pro), preserved untouched
 *   }
 *
 * Every leaf is validated against SPEC. Invalid leaves produce an error at their dotted path; missing leaves
 * fall back to type defaults; unknown keys are dropped (except `ext`).
 */
final class WidgetSanitizer {

	public const SCHEMA = 3;
	public const TYPES  = array( 'button', 'menu', 'bar' );
	public const SHAPES = array( 'circle', 'squircle', 'rounded', 'square', 'pill', 'custom' );
	/** Sentinel for nullable leaves ("inherit from the larger screen"). */
	private const NULL_VALUE = '__blcb_null__';

	public const ANCHORS = array( 'bottom-right', 'bottom-left', 'bottom-center', 'top-right', 'top-left', 'middle-right', 'middle-left' );

	/**
	 * Leaf specs: [ kind, ...constraints ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function spec(): array {
		$pos     = array(
			'anchor'   => array( 'enum', self::ANCHORS ),
			'offset_x' => array( 'int', 0, 400 ),
			'offset_y' => array( 'int', 0, 400 ),
		);
		$weights = array( '400', '500', '600', '700', '800' );
		return array(
			'content'    => array(
				'label'      => array( 'text', 40 ),
				'show_label' => array( 'bool' ),
				'icon'       => array( 'icon' ),
				'close_icon' => array( 'icon' ),
				'menu_label' => array( 'text', 80 ),
			),
			'appearance' => array(
				'shape'            => array( 'enum', self::SHAPES ),
				'radius'           => array( 'int', 0, 100 ),
				'size'             => array( 'int', 32, 120 ),
				'icon_size'        => array( 'int', 12, 64 ),
				'bg'               => array( 'color' ),
				'fg'               => array( 'color' ),
				'shadow'           => array( 'enum', array( 'none', 'sm', 'md', 'lg' ) ),
				'pulse'            => array( 'bool' ),
				'item_labels'      => array( 'enum', array( 'always', 'never' ) ),
				'size_tablet'      => array( 'intn', 32, 120 ),
				'size_mobile'      => array( 'intn', 32, 120 ),
				'icon_size_tablet' => array( 'intn', 12, 64 ),
				'icon_size_mobile' => array( 'intn', 12, 64 ),
				'gap'              => array( 'int', 4, 32 ),
				'style'            => array( 'enum', array( 'solid', 'outline', 'minimal', 'transparent', 'gradient' ) ),
				'gradient_to'      => array( 'color' ),
				'gradient_angle'   => array( 'int', 0, 360 ),
				'hover_bg'         => array( 'color' ),
				'hover_fg'         => array( 'color' ),
				'active_bg'        => array( 'color' ),
				'border_width'     => array( 'int', 0, 8 ),
				'border_color'     => array( 'color' ),
				'label_bg'         => array( 'color' ),
				'label_fg'         => array( 'color' ),
				'tooltip_bg'       => array( 'color' ),
				'tooltip_fg'       => array( 'color' ),
			),
			'typography' => array(
				'font'           => array( 'enum', array( 'system', 'inherit', 'serif', 'rounded', 'mono', 'custom' ) ),
				'custom_font'    => array( 'font' ),
				'size'           => array( 'int', 10, 28 ),
				'size_tablet'    => array( 'intn', 10, 28 ),
				'size_mobile'    => array( 'intn', 10, 28 ),
				'weight'         => array( 'enum', $weights ),
				'line_height'    => array( 'int', 100, 200 ),
				'letter_spacing' => array( 'int', -20, 60 ),
				'transform'      => array( 'enum', array( 'none', 'uppercase', 'capitalize', 'lowercase' ) ),
				'align'          => array( 'enum', array( 'left', 'center', 'right' ) ),
			),
			'effects'    => array(
				'hover'       => array( 'enum', array( 'lift', 'grow', 'glow', 'none' ) ),
				'entrance'    => array( 'enum', array( 'none', 'fade', 'slide', 'zoom', 'bounce' ) ),
				'rotate_icon' => array( 'bool' ),
				'attention'   => array( 'enum', array( 'none', 'pulse', 'bounce', 'shake' ) ),
				'animations'  => array( 'bool' ),
			),
			'position'   => array(
				'desktop'       => $pos,
				'tablet'        => $pos,
				'mobile'        => $pos,
				'tablet_custom' => array( 'bool' ),
			),
			'bar'        => array(
				'placement'         => array( 'enum', array( 'bottom', 'top' ) ),
				'width'             => array( 'enum', array( 'full', 'content' ) ),
				'layout'            => array( 'enum', array( 'icon-above', 'icon-beside', 'icon-only', 'text-only' ) ),
				'height'            => array( 'int', 40, 120 ),
				'bg'                => array( 'color' ),
				'fg'                => array( 'color' ),
				'use_action_colors' => array( 'bool' ),
				'dividers'          => array( 'bool' ),
				'reserve_space'     => array( 'bool' ),
				'divider_color'     => array( 'color' ),
				'padding'           => array( 'int', 0, 24 ),
				'equal_width'       => array( 'bool' ),
				'icon_size'         => array( 'int', 14, 40 ),
				'height_mobile'     => array( 'intn', 40, 120 ),
				'shadow'            => array( 'bool' ),
			),
			'visibility' => array(
				'desktop' => array( 'bool' ),
				'tablet'  => array( 'bool' ),
				'mobile'  => array( 'bool' ),
			),
			'advanced'   => array(
				'z_index'   => array( 'int', 1, 2147483647 ),
				'css_class' => array( 'classes' ),
			),
			'schedule'   => array(
				'enabled'     => array( 'bool' ),
				'when_closed' => array( 'enum', array( 'hide', 'swap' ) ),
			),
			'reveal'     => array(
				'scroll'      => array( 'int', 0, 20000 ),
				'scroll_unit' => array( 'enum', array( 'px', '%' ) ),
				'delay'       => array( 'int', 0, 600 ),
			),
		);
	}

	/**
	 * Defaults for a widget type.
	 *
	 * @param string $type Widget type.
	 * @return array<string,mixed>
	 */
	public static function defaults( string $type ): array {
		$is_bar = 'bar' === $type;
		return array(
			'schema'     => self::SCHEMA,
			'type'       => $type,
			'actions'    => array(),
			'content'    => array(
				'label'      => '',
				'show_label' => false,
				'icon'       => 'menu' === $type ? 'message-circle' : '',
				'close_icon' => 'x',
				'menu_label' => '',
			),
			'appearance' => array(
				'shape'            => 'circle',
				'radius'           => 16,
				'size'             => 56,
				'icon_size'        => 24,
				'bg'               => '',
				'fg'               => '#ffffff',
				'shadow'           => 'md',
				'pulse'            => false,
				'item_labels'      => 'always',
				'size_tablet'      => null,
				'size_mobile'      => null,
				'icon_size_tablet' => null,
				'icon_size_mobile' => null,
				'gap'              => 10,
				'style'            => 'solid',
				'gradient_to'      => '',
				'gradient_angle'   => 135,
				'hover_bg'         => '',
				'hover_fg'         => '',
				'active_bg'        => '',
				'border_width'     => 0,
				'border_color'     => '',
				'label_bg'         => '#ffffff',
				'label_fg'         => '#0f172a',
				'tooltip_bg'       => '#0f172a',
				'tooltip_fg'       => '#ffffff',
			),
			'typography' => array(
				'font'           => 'system',
				'custom_font'    => '',
				'size'           => 14,
				'size_tablet'    => null,
				'size_mobile'    => null,
				'weight'         => '600',
				'line_height'    => 120,
				'letter_spacing' => 0,
				'transform'      => 'none',
				'align'          => 'center',
			),
			'effects'    => array(
				'hover'       => 'lift',
				'entrance'    => 'fade',
				'rotate_icon' => true,
				'attention'   => 'none',
				'animations'  => true,
			),
			'position'   => array(
				'desktop'       => array(
					'anchor'   => 'bottom-right',
					'offset_x' => 24,
					'offset_y' => 24,
				),
				'tablet'        => array(
					'anchor'   => 'bottom-right',
					'offset_x' => 20,
					'offset_y' => 20,
				),
				'mobile'        => array(
					'anchor'   => 'bottom-right',
					'offset_x' => 16,
					'offset_y' => 16,
				),
				'tablet_custom' => false,
			),
			'bar'        => array(
				'placement'         => 'bottom',
				'width'             => 'full',
				'layout'            => 'icon-above',
				'height'            => 60,
				'bg'                => '#ffffff',
				'fg'                => '#0f172a',
				'use_action_colors' => false,
				'dividers'          => true,
				'reserve_space'     => true,
				'divider_color'     => '',
				'padding'           => 6,
				'equal_width'       => true,
				'icon_size'         => 22,
				'height_mobile'     => null,
				'shadow'            => true,
			),
			'visibility' => array(
				'desktop' => ! $is_bar,
				'tablet'  => true,
				'mobile'  => true,
			),
			'advanced'   => array(
				'z_index'   => 99990,
				'css_class' => '',
			),
			'schedule'   => array(
				'enabled'     => false,
				'schedule_id' => 0,
				'when_closed' => 'swap',
			),
			'reveal'     => array(
				'scroll'      => 0,
				'scroll_unit' => 'px',
				'delay'       => 0,
			),
			'rules'      => array(
				'match' => 'all',
				'items' => array(),
			),
			'ext'        => array(),
		);
	}

	/**
	 * Fills keys added in newer schema versions so older stored documents render and edit correctly.
	 * Stored values win; only missing keys are taken from the type defaults.
	 *
	 * @param array<string,mixed> $config Stored config.
	 * @return array<string,mixed>
	 */
	public static function upgrade( array $config ): array {
		$type = in_array( $config['type'] ?? '', self::TYPES, true ) ? $config['type'] : 'menu';
		$out  = self::merge( self::defaults( $type ), $config );
		// Schema 1 → 2: the old "pulse" switch becomes the attention effect.
		if ( ! empty( $config['appearance']['pulse'] ) && empty( $config['effects']['attention'] ) ) {
			$out['effects']['attention'] = 'pulse';
		}
		// Schema 2 → 3: action references gain "when" (always/open/closed).
		$out['actions'] = array_map(
			static function ( $ref ) {
				return is_array( $ref ) ? $ref + array(
					'label' => '',
					'when'  => 'always',
				) : $ref;
			},
			(array) $out['actions']
		);
		$out['schema']  = self::SCHEMA;
		return $out;
	}

	/**
	 * Recursive merge where $over wins for scalar/list values and associative arrays merge.
	 *
	 * @param array<string,mixed> $base Base.
	 * @param array<string,mixed> $over Overrides.
	 * @return array<string,mixed>
	 */
	public static function merge( array $base, array $over ): array {
		foreach ( $over as $k => $v ) {
			if ( is_array( $v ) && isset( $base[ $k ] ) && is_array( $base[ $k ] ) && $base[ $k ] && array_keys( $base[ $k ] ) !== range( 0, count( $base[ $k ] ) - 1 ) ) {
				$base[ $k ] = self::merge( $base[ $k ], $v );
			} else {
				$base[ $k ] = $v;
			}
		}
		return $base;
	}

	/**
	 * Action existence check.
	 *
	 * @var callable(int): bool
	 */
	private $action_exists;

	/**
	 * Icon existence check.
	 *
	 * @var callable(string): bool
	 */
	private $icon_exists;

	/**
	 * Display-rules validator: callable(mixed): array{rules: array, errors: array}.
	 *
	 * @var callable|null
	 */
	private $rules_sanitizer;

	/**
	 * Schedule existence check.
	 *
	 * @var callable(int): bool|null
	 */
	private $schedule_exists;

	/**
	 * Constructor.
	 *
	 * @param callable(int): bool    $action_exists   Whether an action id exists.
	 * @param callable(string): bool $icon_exists     Whether an icon id exists.
	 * @param callable|null          $rules_sanitizer Display-rules validator (ConditionRegistry::sanitize).
	 * @param callable|null          $schedule_exists Whether a schedule id exists.
	 */
	public function __construct( callable $action_exists, callable $icon_exists, ?callable $rules_sanitizer = null, ?callable $schedule_exists = null ) {
		$this->action_exists   = $action_exists;
		$this->icon_exists     = $icon_exists;
		$this->rules_sanitizer = $rules_sanitizer;
		$this->schedule_exists = $schedule_exists;
	}

	/**
	 * Validates a full widget payload: { title, enabled, config }.
	 *
	 * @param mixed $input Raw.
	 * @return array{data: array{title: string, enabled: bool, config: array<string,mixed>}, errors: array<string,string>}
	 */
	public function sanitize( $input ): array {
		$in     = is_array( $input ) ? $input : array();
		$raw    = is_array( $in['config'] ?? null ) ? $in['config'] : array();
		$errors = array();

		$type = Sanitize::enum( $raw['type'] ?? '', self::TYPES );
		if ( null === $type ) {
			$errors['type'] = __( 'Choose a widget type.', 'bigloop-call-button' );
			$type           = 'menu';
		}
		$config = self::defaults( $type );

		$title = Sanitize::text( $in['title'] ?? '', 100 );
		if ( null === $title ) {
			$errors['title'] = __( 'Use 100 characters or fewer.', 'bigloop-call-button' );
			$title           = '';
		} elseif ( '' === $title ) {
			$errors['title'] = __( 'Give the widget a name so you can find it later.', 'bigloop-call-button' );
		}

		$enabled = Sanitize::bool( $in['enabled'] ?? false );
		if ( null === $enabled ) {
			$errors['enabled'] = __( 'Must be true or false.', 'bigloop-call-button' );
			$enabled           = false;
		}

		// Sections.
		foreach ( self::spec() as $section => $fields ) {
			$this->walk( $fields, $raw[ $section ] ?? array(), $config[ $section ], $section, $errors );
		}

		// Actions.
		$config['actions'] = array();
		$seen              = array();
		$list              = is_array( $raw['actions'] ?? null ) ? array_values( $raw['actions'] ) : array();
		foreach ( $list as $i => $ref ) {
			$id = Sanitize::int( is_array( $ref ) ? ( $ref['id'] ?? null ) : $ref, 1, PHP_INT_MAX );
			if ( null === $id || ! ( $this->action_exists )( $id ) ) {
				$errors[ "actions.{$i}" ] = __( 'This action no longer exists. Remove it from the widget.', 'bigloop-call-button' );
				continue;
			}
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$label       = Sanitize::text( is_array( $ref ) ? ( $ref['label'] ?? '' ) : '', 60 );
			if ( null === $label ) {
				$errors[ "actions.{$i}.label" ] = __( 'Use 60 characters or fewer.', 'bigloop-call-button' );
				$label                          = '';
			}
			$when = Sanitize::enum( is_array( $ref ) ? ( $ref['when'] ?? 'always' ) : 'always', array( 'always', 'open', 'closed' ) );
			if ( null === $when ) {
				$errors[ "actions.{$i}.when" ] = __( 'Choose when this action is shown.', 'bigloop-call-button' );
				$when                          = 'always';
			}
			$config['actions'][] = array(
				'id'    => $id,
				'label' => $label,
				'when'  => $when,
			);
		}

		// Schedule reference.
		$schedule_id = Sanitize::int( $raw['schedule']['schedule_id'] ?? 0, 0, PHP_INT_MAX );
		if ( null === $schedule_id ) {
			$errors['schedule.schedule_id'] = __( 'Choose a schedule.', 'bigloop-call-button' );
			$schedule_id                    = 0;
		} elseif ( $schedule_id && $this->schedule_exists && ! ( $this->schedule_exists )( $schedule_id ) ) {
			$errors['schedule.schedule_id'] = __( 'This schedule no longer exists. Choose another one.', 'bigloop-call-button' );
			$schedule_id                    = 0;
		}
		$config['schedule']['schedule_id'] = $schedule_id;
		if ( $config['schedule']['enabled'] && ! $schedule_id ) {
			$errors['schedule.schedule_id'] = __( 'Choose which schedule this widget follows.', 'bigloop-call-button' );
		}

		// Display rules.
		if ( $this->rules_sanitizer && isset( $raw['rules'] ) ) {
			$result          = ( $this->rules_sanitizer )( $raw['rules'] );
			$config['rules'] = $result['rules'];
			$errors          = array_merge( $errors, $result['errors'] );
		}

		if ( 'button' === $type && count( $config['actions'] ) > 1 ) {
			$errors['actions'] = __( 'A single button opens one action. Choose "Expandable menu" or "Contact bar" to show several.', 'bigloop-call-button' );
		}
		if ( $enabled && ! $config['actions'] ) {
			$errors['actions'] = __( 'Add at least one action before enabling this widget.', 'bigloop-call-button' );
		}

		// Back-compat: schema-1 clients send appearance.pulse; schema 2 uses effects.attention.
		if ( ! empty( $raw['appearance']['pulse'] ) && ! isset( $raw['effects']['attention'] ) ) {
			$config['effects']['attention'] = 'pulse';
		}
		$config['appearance']['pulse'] = 'pulse' === $config['effects']['attention'];

		$config['ext'] = $this->clean_ext( $raw['ext'] ?? array(), 0 );

		return array(
			'data'   => array(
				'title'   => $title,
				'enabled' => $enabled,
				'config'  => $config,
			),
			'errors' => $errors,
		);
	}

	/**
	 * Validates a partial style document (used by design presets). Only the given sections are allowed and only
	 * keys present in the input are returned, so applying a preset never touches unrelated settings.
	 *
	 * @param mixed    $raw      Partial config, e.g. { appearance: { shape: 'squircle' } }.
	 * @param string[] $sections Allowed top-level sections.
	 * @return array{partial: array<string,mixed>, errors: array<string,string>}
	 */
	public function sanitize_partial( $raw, array $sections ): array {
		$raw     = is_array( $raw ) ? $raw : array();
		$spec    = self::spec();
		$errors  = array();
		$partial = array();
		foreach ( $sections as $section ) {
			if ( ! isset( $raw[ $section ], $spec[ $section ] ) || ! is_array( $raw[ $section ] ) ) {
				continue;
			}
			$target = self::defaults( 'menu' )[ $section ];
			$this->walk( $spec[ $section ], $raw[ $section ], $target, $section, $errors );
			$kept = array_intersect_key( $target, $raw[ $section ], $spec[ $section ] );
			foreach ( array_keys( $kept ) as $key ) {
				if ( isset( $errors[ $section . '.' . $key ] ) ) {
					unset( $kept[ $key ] ); // Invalid values are dropped, never replaced by defaults.
				}
			}
			if ( $kept ) {
				$partial[ $section ] = $kept;
			}
		}
		return array(
			'partial' => $partial,
			'errors'  => $errors,
		);
	}

	/**
	 * Validates nested leaves into $target (which holds defaults).
	 *
	 * @param array<string,mixed>  $spec   Spec subtree.
	 * @param mixed                $raw    Raw subtree.
	 * @param array<string,mixed>  $target Target subtree (by reference).
	 * @param string               $path   Dotted path.
	 * @param array<string,string> $errors Errors (by reference).
	 * @return void
	 */
	private function walk( array $spec, $raw, array &$target, string $path, array &$errors ): void {
		$raw = is_array( $raw ) ? $raw : array();
		foreach ( $spec as $key => $rule ) {
			$p = $path . '.' . $key;
			if ( ! isset( $rule[0] ) || ! is_string( $rule[0] ) ) {
				$this->walk( $rule, $raw[ $key ] ?? array(), $target[ $key ], $p, $errors );
				continue;
			}
			if ( ! array_key_exists( $key, $raw ) ) {
				continue;
			}
			$value = $this->leaf( $rule, $raw[ $key ] );
			if ( null === $value ) {
				$errors[ $p ] = $this->leaf_error( $rule );
				continue;
			}
			$target[ $key ] = self::NULL_VALUE === $value ? null : $value;
		}
	}

	/**
	 * Validates one leaf.
	 *
	 * @param array<int,mixed> $rule Rule.
	 * @param mixed            $raw  Raw value.
	 * @return mixed|null Null when invalid.
	 */
	private function leaf( array $rule, $raw ) {
		switch ( $rule[0] ) {
			case 'text':
				return Sanitize::text( $raw, (int) $rule[1] );
			case 'bool':
				return Sanitize::bool( $raw );
			case 'int':
				return Sanitize::int( $raw, (int) $rule[1], (int) $rule[2] );
			case 'intn':
				if ( null === $raw || '' === $raw ) {
					return self::NULL_VALUE;
				}
				return Sanitize::int( $raw, (int) $rule[1], (int) $rule[2] );
			case 'font':
				if ( ! is_string( $raw ) ) {
					return null;
				}
				$font = trim( $raw );
				// A family name only (letters, digits, spaces, hyphens), never arbitrary CSS.
				return ( '' === $font || preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,59}$/', $font ) ) ? $font : null;
			case 'enum':
				return Sanitize::enum( $raw, (array) $rule[1] );
			case 'color':
				return Sanitize::color( $raw );
			case 'classes':
				return Sanitize::css_classes( $raw );
			case 'icon':
				$id = Sanitize::icon_id( $raw );
				return ( null !== $id && ( '' === $id || ( $this->icon_exists )( $id ) ) ) ? $id : null;
		}
		return null;
	}

	/**
	 * Error message for an invalid leaf.
	 *
	 * @param array<int,mixed> $rule Rule.
	 * @return string
	 */
	private function leaf_error( array $rule ): string {
		switch ( $rule[0] ) {
			case 'text':
				/* translators: %d: max characters */
				return sprintf( __( 'Use %d characters or fewer.', 'bigloop-call-button' ), (int) $rule[1] );
			case 'font':
				return __( 'Enter a font family name only, e.g. Inter or Open Sans.', 'bigloop-call-button' );
			case 'int':
			case 'intn':
				/* translators: 1: minimum, 2: maximum */
				return sprintf( __( 'Enter a whole number between %1$d and %2$d.', 'bigloop-call-button' ), (int) $rule[1], (int) $rule[2] );
			case 'color':
				return __( 'Use a hex colour such as #1a73e8.', 'bigloop-call-button' );
			case 'icon':
				return __( 'Choose an icon from the library.', 'bigloop-call-button' );
			case 'enum':
				return __( 'Choose one of the available options.', 'bigloop-call-button' );
			default:
				return __( 'Invalid value.', 'bigloop-call-button' );
		}
	}

	/**
	 * Keeps extension data JSON-safe: scalars (strings as plain text), nested arrays up to depth 6.
	 *
	 * @param mixed $value Raw.
	 * @param int   $depth Depth.
	 * @return mixed
	 */
	private function clean_ext( $value, int $depth ) {
		if ( is_array( $value ) ) {
			if ( $depth >= 6 ) {
				return array();
			}
			$out = array();
			foreach ( array_slice( $value, 0, 200, true ) as $k => $v ) {
				$key = is_int( $k ) ? $k : preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $k );
				if ( '' === $key ) {
					continue;
				}
				$out[ $key ] = $this->clean_ext( $v, $depth + 1 );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}
		return Sanitize::textarea( (string) $value, 5000 ) ?? '';
	}
}
