<?php
/**
 * A contact action type (phone, WhatsApp, email, …).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Data-driven definition: fields describe the destination form, `href` builds the link from validated values.
 * Pro and add-ons register new types with ActionTypeRegistry::register().
 */
final class ActionType {

	/**
	 * Type id.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Definition.
	 *
	 * @var array<string,mixed>
	 */
	private $def;

	/**
	 * Constructor.
	 *
	 * @param string              $id  Type id ([a-z0-9_-]).
	 * @param array<string,mixed> $def {
	 *     Type definition.
	 *
	 *     @type string   $label         Human label.
	 *     @type string   $description   One-line description.
	 *     @type string   $icon          Default icon id.
	 *     @type string   $category      UI grouping: contact | messaging | location | web.
	 *     @type array    $colors        Default ['bg' => '#hex', 'fg' => '#hex'].
	 *     @type bool     $new_tab       Default "open in new tab".
	 *     @type array    $fields        Field definitions: key => [kind, label, required, help, placeholder, max_length, pattern].
	 *     @type callable $href          function( array $values ): string.
	 * }
	 * @throws \InvalidArgumentException On invalid definitions.
	 */
	public function __construct( string $id, array $def ) {
		if ( ! preg_match( '/^[a-z0-9_-]{1,32}$/', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid action type id.' );
		}
		if ( empty( $def['href'] ) || ! is_callable( $def['href'] ) ) {
			throw new \InvalidArgumentException( 'Action type needs an href builder.' );
		}
		$this->id  = $id;
		$this->def = $def + array(
			'label'       => $id,
			'description' => '',
			'icon'        => 'link',
			'category'    => 'web',
			'colors'      => array(
				'bg' => '#4f46e5',
				'fg' => '#ffffff',
			),
			'new_tab'     => false,
			'fields'      => array(),
		);
	}

	/**
	 * Id.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return (string) $this->def['label'];
	}

	/**
	 * Default icon id.
	 *
	 * @return string
	 */
	public function icon(): string {
		return (string) $this->def['icon'];
	}

	/**
	 * Default colours.
	 *
	 * @return array{bg: string, fg: string}
	 */
	public function colors(): array {
		return (array) $this->def['colors'];
	}

	/**
	 * Default new-tab behaviour.
	 *
	 * @return bool
	 */
	public function new_tab_default(): bool {
		return (bool) $this->def['new_tab'];
	}

	/**
	 * Field definitions.
	 *
	 * @return array<string, array<string,mixed>>
	 */
	public function fields(): array {
		return (array) $this->def['fields'];
	}

	/**
	 * Validates destination values. Unknown keys are dropped.
	 *
	 * @param mixed         $raw       Raw values (object).
	 * @param string[]|null $protocols Allowed custom_url protocols.
	 * @return array{value: array<string,string>, errors: array<string,string>}
	 */
	public function validate( $raw, ?array $protocols = null ): array {
		$raw    = is_array( $raw ) ? $raw : array();
		$value  = array();
		$errors = array();
		foreach ( $this->fields() as $key => $field ) {
			$result = FieldValidator::validate( $field, $raw[ $key ] ?? null, $protocols );
			if ( $result['ok'] ) {
				$value[ $key ] = $result['value'];
			} else {
				$errors[ $key ] = $result['error'];
			}
		}
		return array(
			'value'  => $value,
			'errors' => $errors,
		);
	}

	/**
	 * Builds the link for validated values.
	 *
	 * @param array<string,string> $value Validated values.
	 * @return string
	 */
	public function href( array $value ): string {
		return (string) call_user_func( $this->def['href'], $value );
	}

	/**
	 * Description for the admin UI.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$fields = array();
		foreach ( $this->fields() as $key => $field ) {
			$fields[] = array(
				'key'         => $key,
				'kind'        => $field['kind'] ?? 'text',
				'label'       => $field['label'] ?? $key,
				'required'    => ! empty( $field['required'] ),
				'help'        => $field['help'] ?? '',
				'placeholder' => $field['placeholder'] ?? '',
				'max_length'  => $field['max_length'] ?? null,
			);
		}
		return array(
			'id'          => $this->id,
			'label'       => $this->label(),
			'description' => (string) $this->def['description'],
			'category'    => (string) $this->def['category'],
			'icon'        => $this->icon(),
			'colors'      => $this->colors(),
			'new_tab'     => $this->new_tab_default(),
			'fields'      => $fields,
		);
	}
}
