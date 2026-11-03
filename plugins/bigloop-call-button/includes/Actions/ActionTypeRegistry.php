<?php
/**
 * Registry of contact action types.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Holds built-in and extension action types.
 *
 * Default colours are chosen so white icons/text meet WCAG contrast (≥ 4.5:1) on the button background.
 */
final class ActionTypeRegistry {

	/**
	 * Types by id.
	 *
	 * @var array<string, ActionType>
	 */
	private $types = array();

	/**
	 * Registers (or replaces) a type.
	 *
	 * @param ActionType $type Type.
	 * @return void
	 */
	public function register( ActionType $type ): void {
		$this->types[ $type->id() ] = $type;
	}

	/**
	 * Type by id.
	 *
	 * @param string $id Id.
	 * @return ActionType|null
	 */
	public function get( string $id ): ?ActionType {
		return $this->types[ $id ] ?? null;
	}

	/**
	 * All types.
	 *
	 * @return array<string, ActionType>
	 */
	public function all(): array {
		return $this->types;
	}

	/**
	 * Registry pre-filled with the built-in Free types.
	 *
	 * @return self
	 */
	public static function with_defaults(): self {
		$registry = new self();
		foreach ( self::defaults() as $id => $def ) {
			$registry->register( new ActionType( $id, $def ) );
		}
		return $registry;
	}

	/**
	 * Built-in type definitions.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	private static function defaults(): array {
		$q = static function ( array $params ): string {
			$params = array_filter(
				$params,
				static function ( $v ) {
					return '' !== $v && null !== $v;
				}
			);
			return $params ? '?' . implode(
				'&',
				array_map(
					static function ( $k, $v ) {
						return $k . '=' . rawurlencode( (string) $v );
					},
					array_keys( $params ),
					$params
				)
			) : '';
		};

		$intl = array(
			'kind'        => 'intl_phone',
			'label'       => __( 'Phone number (with country code)', 'bigloop-call-button' ),
			'required'    => true,
			'placeholder' => '+1 555 123 4567',
		);

		return array(
			'phone'      => array(
				'label'       => __( 'Phone call', 'bigloop-call-button' ),
				'description' => __( 'Starts a phone call on mobile devices and calling apps.', 'bigloop-call-button' ),
				'icon'        => 'phone',
				'category'    => 'contact',
				'colors'      => array(
					'bg' => '#15803d',
					'fg' => '#ffffff',
				),
				'fields'      => array(
					'number' => array(
						'kind'        => 'phone',
						'label'       => __( 'Phone number', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => '+1 555 123 4567',
						'help'        => __( 'Include the country code for visitors calling from abroad. Add an extension after a comma.', 'bigloop-call-button' ),
					),
				),
				'href'        => static function ( array $v ) {
					return 'tel:' . $v['number'];
				},
			),
			'sms'        => array(
				'label'       => __( 'SMS', 'bigloop-call-button' ),
				'description' => __( 'Opens a new text message.', 'bigloop-call-button' ),
				'icon'        => 'message-square-text',
				'category'    => 'contact',
				'colors'      => array(
					'bg' => '#1d4ed8',
					'fg' => '#ffffff',
				),
				'fields'      => array(
					'number' => array(
						'kind'        => 'phone',
						'label'       => __( 'Phone number', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => '+1 555 123 4567',
					),
					'body'   => array(
						'kind'       => 'textarea',
						'label'      => __( 'Pre-filled message (optional)', 'bigloop-call-button' ),
						'max_length' => 500,
					),
				),
				// "?&body=" is understood by both iOS and Android messaging apps.
				'href'        => static function ( array $v ) {
					return 'sms:' . $v['number'] . ( '' !== ( $v['body'] ?? '' ) ? '?&body=' . rawurlencode( $v['body'] ) : '' );
				},
			),
			'whatsapp'   => array(
				'label'       => __( 'WhatsApp', 'bigloop-call-button' ),
				'description' => __( 'Opens a WhatsApp chat with your number.', 'bigloop-call-button' ),
				'icon'        => 'whatsapp',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#0f7a5a',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'number'  => $intl,
					'message' => array(
						'kind'       => 'textarea',
						'label'      => __( 'Pre-filled message (optional)', 'bigloop-call-button' ),
						'max_length' => 500,
					),
				),
				'href'        => static function ( array $v ) use ( $q ) {
					return 'https://wa.me/' . $v['number'] . $q( array( 'text' => $v['message'] ?? '' ) );
				},
			),
			'email'      => array(
				'label'       => __( 'Email', 'bigloop-call-button' ),
				'description' => __( 'Opens a new email in the visitor\'s mail app.', 'bigloop-call-button' ),
				'icon'        => 'mail',
				'category'    => 'contact',
				'colors'      => array(
					'bg' => '#4338ca',
					'fg' => '#ffffff',
				),
				'fields'      => array(
					'address' => array(
						'kind'        => 'email',
						'label'       => __( 'Email address', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => 'hello@example.com',
					),
					'subject' => array(
						'kind'       => 'text',
						'label'      => __( 'Subject (optional)', 'bigloop-call-button' ),
						'max_length' => 200,
					),
					'body'    => array(
						'kind'       => 'textarea',
						'label'      => __( 'Message (optional)', 'bigloop-call-button' ),
						'max_length' => 1000,
					),
				),
				'href'        => static function ( array $v ) use ( $q ) {
					return 'mailto:' . $v['address'] . $q(
						array(
							'subject' => $v['subject'] ?? '',
							'body'    => $v['body'] ?? '',
						)
					);
				},
			),
			'map'        => array(
				'label'       => __( 'Map location', 'bigloop-call-button' ),
				'description' => __( 'Shows your location in Google Maps.', 'bigloop-call-button' ),
				'icon'        => 'map-pin',
				'category'    => 'location',
				'colors'      => array(
					'bg' => '#b91c1c',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'query'    => array(
						'kind'        => 'text',
						'label'       => __( 'Address, place name or coordinates', 'bigloop-call-button' ),
						'required'    => true,
						'max_length'  => 300,
						'placeholder' => '1600 Amphitheatre Pkwy, Mountain View, CA',
					),
					'place_id' => array(
						'kind'  => 'place_id',
						'label' => __( 'Google Place ID (optional, for exact matching)', 'bigloop-call-button' ),
					),
				),
				'href'        => static function ( array $v ) use ( $q ) {
					return 'https://www.google.com/maps/search/' . $q(
						array(
							'api'            => '1',
							'query'          => $v['query'],
							'query_place_id' => $v['place_id'] ?? '',
						)
					);
				},
			),
			'directions' => array(
				'label'       => __( 'Directions', 'bigloop-call-button' ),
				'description' => __( 'Opens turn-by-turn directions to your location.', 'bigloop-call-button' ),
				'icon'        => 'navigation',
				'category'    => 'location',
				'colors'      => array(
					'bg' => '#0369a1',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'destination' => array(
						'kind'        => 'text',
						'label'       => __( 'Destination address or coordinates', 'bigloop-call-button' ),
						'required'    => true,
						'max_length'  => 300,
						'placeholder' => '40.7484,-73.9857',
					),
					'place_id'    => array(
						'kind'  => 'place_id',
						'label' => __( 'Google Place ID (optional)', 'bigloop-call-button' ),
					),
				),
				'href'        => static function ( array $v ) use ( $q ) {
					return 'https://www.google.com/maps/dir/' . $q(
						array(
							'api'                  => '1',
							'destination'          => $v['destination'],
							'destination_place_id' => $v['place_id'] ?? '',
						)
					);
				},
			),
			'booking'    => array(
				'label'       => __( 'Appointment booking', 'bigloop-call-button' ),
				'description' => __( 'Links to your booking page or scheduling tool.', 'bigloop-call-button' ),
				'icon'        => 'calendar-check',
				'category'    => 'web',
				'colors'      => array(
					'bg' => '#7e22ce',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'url' => array(
						'kind'        => 'url',
						'label'       => __( 'Booking page URL', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => 'https://',
					),
				),
				'href'        => static function ( array $v ) {
					return $v['url'];
				},
			),
			'url'        => array(
				'label'       => __( 'Website link', 'bigloop-call-button' ),
				'description' => __( 'Links to any web page, such as a contact form.', 'bigloop-call-button' ),
				'icon'        => 'globe',
				'category'    => 'web',
				'colors'      => array(
					'bg' => '#334155',
					'fg' => '#ffffff',
				),
				'fields'      => array(
					'url' => array(
						'kind'        => 'url',
						'label'       => __( 'Page URL', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => 'https://',
					),
				),
				'href'        => static function ( array $v ) {
					return $v['url'];
				},
			),
			'custom'     => array(
				'label'       => __( 'Custom link', 'bigloop-call-button' ),
				'description' => __( 'Any link, including app links such as skype: or facetime:.', 'bigloop-call-button' ),
				'icon'        => 'link',
				'category'    => 'web',
				'colors'      => array(
					'bg' => '#334155',
					'fg' => '#ffffff',
				),
				'fields'      => array(
					'url' => array(
						'kind'        => 'custom_url',
						'label'       => __( 'Link', 'bigloop-call-button' ),
						'required'    => true,
						'placeholder' => 'https:// or tel: or mailto: …',
					),
				),
				'href'        => static function ( array $v ) {
					return $v['url'];
				},
			),
			'telegram'   => array(
				'label'       => __( 'Telegram', 'bigloop-call-button' ),
				'description' => __( 'Opens a Telegram chat with your username.', 'bigloop-call-button' ),
				'icon'        => 'telegram',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#1a6fa3',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'username' => array(
						'kind'          => 'handle',
						'label'         => __( 'Telegram username', 'bigloop-call-button' ),
						'required'      => true,
						'placeholder'   => '@yourname',
						'pattern'       => '/^[A-Za-z][A-Za-z0-9_]{4,31}$/',
						'pattern_error' => __( 'Telegram usernames are 5–32 characters: letters, numbers and underscores, starting with a letter.', 'bigloop-call-button' ),
					),
				),
				'href'        => static function ( array $v ) {
					return 'https://t.me/' . $v['username'];
				},
			),
			'messenger'  => array(
				'label'       => __( 'Messenger', 'bigloop-call-button' ),
				'description' => __( 'Opens a Facebook Messenger chat with your page.', 'bigloop-call-button' ),
				'icon'        => 'messenger',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#0866ff',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'username' => array(
						'kind'          => 'handle',
						'label'         => __( 'Facebook page username', 'bigloop-call-button' ),
						'required'      => true,
						'placeholder'   => 'yourpage',
						'pattern'       => '/^[A-Za-z0-9.]{1,100}$/',
						'pattern_error' => __( 'Use your page username (letters, numbers and dots), as in facebook.com/yourpage.', 'bigloop-call-button' ),
					),
				),
				'href'        => static function ( array $v ) {
					return 'https://m.me/' . $v['username'];
				},
			),
			'viber'      => array(
				'label'       => __( 'Viber', 'bigloop-call-button' ),
				'description' => __( 'Opens a Viber chat with your number (Viber app required).', 'bigloop-call-button' ),
				'icon'        => 'viber',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#665cac',
					'fg' => '#ffffff',
				),
				'fields'      => array( 'number' => $intl ),
				'href'        => static function ( array $v ) {
					return 'viber://chat?number=' . rawurlencode( '+' . $v['number'] );
				},
			),
			'signal'     => array(
				'label'       => __( 'Signal', 'bigloop-call-button' ),
				'description' => __( 'Opens a Signal chat with your number.', 'bigloop-call-button' ),
				'icon'        => 'signal',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#2563eb',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array( 'number' => $intl ),
				'href'        => static function ( array $v ) {
					return 'https://signal.me/#p/+' . $v['number'];
				},
			),
			'line'       => array(
				'label'       => __( 'LINE', 'bigloop-call-button' ),
				'description' => __( 'Opens your LINE account so visitors can add and message you.', 'bigloop-call-button' ),
				'icon'        => 'line',
				'category'    => 'messaging',
				'colors'      => array(
					'bg' => '#06803b',
					'fg' => '#ffffff',
				),
				'new_tab'     => true,
				'fields'      => array(
					'line_id' => array(
						'kind'          => 'handle',
						'label'         => __( 'LINE Official Account ID', 'bigloop-call-button' ),
						'required'      => true,
						'placeholder'   => '@yourshop',
						'pattern'       => '/^[A-Za-z0-9._-]{1,40}$/',
						'pattern_error' => __( 'Enter your LINE Official Account ID, as shown in LINE Official Account Manager (e.g. @yourshop).', 'bigloop-call-button' ),
					),
				),
				// LINE Official Account links: line.me/R/ti/p/@id.
				'href'        => static function ( array $v ) {
					return 'https://line.me/R/ti/p/' . rawurlencode( '@' . $v['line_id'] );
				},
			),
		);
	}
}
