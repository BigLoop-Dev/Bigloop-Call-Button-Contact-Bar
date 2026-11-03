<?php
/**
 * Built-in design presets.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Presets;

defined( 'ABSPATH' ) || exit;

/**
 * Each preset sets style sections only (appearance, typography, effects, bar); never actions, positions or
 * visibility. Presets are starting points: every value stays editable after applying.
 */
final class BuiltinPresets {

	/**
	 * Presets keyed by id.
	 *
	 * @return array<string, array{name:string, description:string, config:array<string,mixed>}>
	 */
	public static function all(): array {
		return array(
			'modern-minimal'        => array(
				'name'        => __( 'Modern Minimal', 'bigloop-call-button' ),
				'description' => __( 'Squircle shape, frosted white button, quiet shadows.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'squircle',
						'style'        => 'minimal',
						'bg'           => '#111827',
						'size'         => 54,
						'icon_size'    => 22,
						'shadow'       => 'md',
						'border_width' => 1,
						'border_color' => '#e5e7eb',
						'label_bg'     => '#ffffff',
						'label_fg'     => '#111827',
						'gap'          => 10,
					),
					'typography' => array(
						'font'           => 'system',
						'weight'         => '500',
						'size'           => 14,
						'letter_spacing' => 0,
						'transform'      => 'none',
					),
					'effects'    => array(
						'hover'       => 'lift',
						'entrance'    => 'fade',
						'attention'   => 'none',
						'rotate_icon' => true,
					),
					'bar'        => array(
						'bg'                => '#ffffff',
						'fg'                => '#111827',
						'use_action_colors' => false,
						'dividers'          => true,
						'divider_color'     => '#e5e7eb',
						'shadow'            => true,
					),
				),
			),
			'classic-call'          => array(
				'name'        => __( 'Classic Call Button', 'bigloop-call-button' ),
				'description' => __( 'The familiar green circle with a gentle pulse.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'circle',
						'style'        => 'solid',
						'bg'           => '#15803d',
						'fg'           => '#ffffff',
						'hover_bg'     => '#166534',
						'size'         => 60,
						'icon_size'    => 26,
						'shadow'       => 'md',
						'border_width' => 0,
					),
					'typography' => array(
						'font'   => 'system',
						'weight' => '700',
						'size'   => 15,
					),
					'effects'    => array(
						'hover'     => 'grow',
						'entrance'  => 'zoom',
						'attention' => 'pulse',
					),
					'bar'        => array(
						'bg'                => '#15803d',
						'fg'                => '#ffffff',
						'use_action_colors' => false,
						'dividers'          => true,
						'divider_color'     => '#22c55e',
					),
				),
			),
			'whatsapp-green'        => array(
				'name'        => __( 'WhatsApp Green', 'bigloop-call-button' ),
				'description' => __( 'WhatsApp-style green, darkened slightly so white text stays readable.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'circle',
						'style'        => 'solid',
						'bg'           => '#108241',
						'fg'           => '#ffffff',
						'hover_bg'     => '#0c6b35',
						'size'         => 60,
						'icon_size'    => 30,
						'shadow'       => 'md',
						'border_width' => 0,
					),
					'typography' => array(
						'font'   => 'system',
						'weight' => '600',
					),
					'effects'    => array(
						'hover'     => 'lift',
						'entrance'  => 'slide',
						'attention' => 'bounce',
					),
					'bar'        => array(
						'bg'                => '#108241',
						'fg'                => '#ffffff',
						'use_action_colors' => false,
						'divider_color'     => '#2fa85e',
					),
				),
			),
			'professional-business' => array(
				'name'        => __( 'Professional Business', 'bigloop-call-button' ),
				'description' => __( 'Navy squircle, refined type and a subtle border.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'squircle',
						'style'        => 'solid',
						'bg'           => '#1e3a8a',
						'fg'           => '#ffffff',
						'hover_bg'     => '#1e40af',
						'size'         => 56,
						'icon_size'    => 24,
						'shadow'       => 'lg',
						'border_width' => 0,
						'label_bg'     => '#ffffff',
						'label_fg'     => '#1e3a8a',
					),
					'typography' => array(
						'font'           => 'system',
						'weight'         => '600',
						'size'           => 14,
						'letter_spacing' => 2,
					),
					'effects'    => array(
						'hover'     => 'lift',
						'entrance'  => 'fade',
						'attention' => 'none',
					),
					'bar'        => array(
						'bg'                => '#1e3a8a',
						'fg'                => '#ffffff',
						'use_action_colors' => false,
						'divider_color'     => '#3b5bb5',
					),
				),
			),
			'medical-practice'      => array(
				'name'        => __( 'Medical Practice', 'bigloop-call-button' ),
				'description' => __( 'Calm teal, soft rounded corners, reassuring and clear.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'     => 'rounded',
						'style'     => 'solid',
						'bg'        => '#0f766e',
						'fg'        => '#ffffff',
						'hover_bg'  => '#115e59',
						'size'      => 56,
						'icon_size' => 24,
						'shadow'    => 'sm',
						'label_bg'  => '#f0fdfa',
						'label_fg'  => '#134e4a',
					),
					'typography' => array(
						'font'   => 'rounded',
						'weight' => '600',
						'size'   => 15,
					),
					'effects'    => array(
						'hover'     => 'lift',
						'entrance'  => 'fade',
						'attention' => 'none',
					),
					'bar'        => array(
						'bg'                => '#f0fdfa',
						'fg'                => '#134e4a',
						'use_action_colors' => false,
						'divider_color'     => '#99f6e4',
					),
				),
			),
			'restaurant'            => array(
				'name'        => __( 'Restaurant', 'bigloop-call-button' ),
				'description' => __( 'Warm terracotta pill with "Reserve" energy.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'          => 'pill',
						'style'          => 'gradient',
						'bg'             => '#c2410c',
						'gradient_to'    => '#9a3412',
						'gradient_angle' => 135,
						'fg'             => '#ffffff',
						'size'           => 58,
						'icon_size'      => 24,
						'shadow'         => 'lg',
						'label_bg'       => '#fff7ed',
						'label_fg'       => '#7c2d12',
					),
					'typography' => array(
						'font'   => 'serif',
						'weight' => '700',
						'size'   => 16,
					),
					'effects'    => array(
						'hover'     => 'grow',
						'entrance'  => 'bounce',
						'attention' => 'none',
					),
					'bar'        => array(
						'bg'                => '#7c2d12',
						'fg'                => '#fff7ed',
						'use_action_colors' => false,
						'divider_color'     => '#9a3412',
					),
				),
			),
			'real-estate'           => array(
				'name'        => __( 'Real Estate', 'bigloop-call-button' ),
				'description' => __( 'Charcoal and gold, premium and understated.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'square',
						'style'        => 'solid',
						'bg'           => '#1f2937',
						'fg'           => '#fbbf24',
						'hover_bg'     => '#111827',
						'size'         => 56,
						'icon_size'    => 24,
						'shadow'       => 'lg',
						'border_width' => 2,
						'border_color' => '#b45309',
						'label_bg'     => '#1f2937',
						'label_fg'     => '#fde68a',
					),
					'typography' => array(
						'font'           => 'serif',
						'weight'         => '600',
						'size'           => 14,
						'letter_spacing' => 6,
						'transform'      => 'uppercase',
					),
					'effects'    => array(
						'hover'     => 'glow',
						'entrance'  => 'fade',
						'attention' => 'none',
					),
					'bar'        => array(
						'bg'                => '#1f2937',
						'fg'                => '#fde68a',
						'use_action_colors' => false,
						'divider_color'     => '#374151',
					),
				),
			),
			'service-business'      => array(
				'name'        => __( 'Service Business', 'bigloop-call-button' ),
				'description' => __( 'Bold blue, big tap target, built for "call now".', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'     => 'pill',
						'style'     => 'solid',
						'bg'        => '#1d4ed8',
						'fg'        => '#ffffff',
						'hover_bg'  => '#1e40af',
						'size'      => 62,
						'icon_size' => 26,
						'shadow'    => 'lg',
					),
					'typography' => array(
						'font'           => 'system',
						'weight'         => '800',
						'size'           => 16,
						'transform'      => 'uppercase',
						'letter_spacing' => 4,
					),
					'effects'    => array(
						'hover'     => 'lift',
						'entrance'  => 'slide',
						'attention' => 'shake',
					),
					'bar'        => array(
						'bg'                => '#1d4ed8',
						'fg'                => '#ffffff',
						'use_action_colors' => false,
						'divider_color'     => '#3b82f6',
					),
				),
			),
			'dark-modern'           => array(
				'name'        => __( 'Dark Modern', 'bigloop-call-button' ),
				'description' => __( 'Near-black squircle with a soft glow on hover.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'        => 'squircle',
						'style'        => 'solid',
						'bg'           => '#0b0f19',
						'fg'           => '#f8fafc',
						'hover_bg'     => '#1e293b',
						'size'         => 56,
						'icon_size'    => 24,
						'shadow'       => 'lg',
						'border_width' => 1,
						'border_color' => '#334155',
						'label_bg'     => '#0b0f19',
						'label_fg'     => '#f8fafc',
						'tooltip_bg'   => '#f8fafc',
						'tooltip_fg'   => '#0b0f19',
					),
					'typography' => array(
						'font'   => 'system',
						'weight' => '500',
						'size'   => 14,
					),
					'effects'    => array(
						'hover'     => 'glow',
						'entrance'  => 'zoom',
						'attention' => 'none',
					),
					'bar'        => array(
						'bg'                => '#0b0f19',
						'fg'                => '#f8fafc',
						'use_action_colors' => false,
						'divider_color'     => '#1e293b',
					),
				),
			),
			'colorful-bar'          => array(
				'name'        => __( 'Colorful Contact Bar', 'bigloop-call-button' ),
				'description' => __( 'Each action in its own colour, edge to edge.', 'bigloop-call-button' ),
				'config'      => array(
					'appearance' => array(
						'shape'  => 'circle',
						'style'  => 'solid',
						'bg'     => '#4338ca',
						'fg'     => '#ffffff',
						'size'   => 56,
						'shadow' => 'md',
					),
					'typography' => array(
						'font'   => 'system',
						'weight' => '700',
						'size'   => 14,
					),
					'effects'    => array(
						'hover'     => 'lift',
						'entrance'  => 'slide',
						'attention' => 'none',
					),
					'bar'        => array(
						'use_action_colors' => true,
						'dividers'          => true,
						'layout'            => 'icon-above',
						'height'            => 64,
						'icon_size'         => 24,
					),
				),
			),
		);
	}
}
