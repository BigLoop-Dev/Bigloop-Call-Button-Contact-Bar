<?php
/**
 * A display-rule condition type.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Data-driven definition. `side` is "server" (decided in PHP from the request: cache-safe because it depends only
 * on the URL) or "client" (decided in the visitor's browser, e.g. URL parameters or country).
 *
 * Value kinds (drive validation and the admin picker):
 *   ids:<post_type>   list of post IDs          strings:<list>  list of slugs/codes
 *   terms             { taxonomy, terms: [] }   text            one string
 *   kv                { key, value }            choice:<a|b|…>  one of the options
 *   none              no value
 */
final class Condition {

	/**
	 * Id.
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
	 * @param string              $id  Id.
	 * @param array<string,mixed> $def { label, group, operators: [op => label], value: kind, side: server|client, evaluate: callable(array $rule, Context $ctx): bool, available: bool }.
	 * @throws \InvalidArgumentException On invalid definition.
	 */
	public function __construct( string $id, array $def ) {
		if ( ! preg_match( '/^[a-z0-9_]{1,40}$/', $id ) ) {
			throw new \InvalidArgumentException( 'Invalid condition id.' );
		}
		if ( 'server' === ( $def['side'] ?? 'server' ) && ( empty( $def['evaluate'] ) || ! is_callable( $def['evaluate'] ) ) ) {
			throw new \InvalidArgumentException( 'Server conditions need an evaluate callback.' );
		}
		$this->id  = $id;
		$this->def = $def + array(
			'label'     => $id,
			'group'     => '',
			'operators' => array( 'is' => 'is' ),
			'value'     => 'none',
			'side'      => 'server',
			'available' => true,
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
	 * Side.
	 *
	 * @return string
	 */
	public function side(): string {
		return (string) $this->def['side'];
	}

	/**
	 * Value kind.
	 *
	 * @return string
	 */
	public function value_kind(): string {
		return (string) $this->def['value'];
	}

	/**
	 * Operators.
	 *
	 * @return array<string,string>
	 */
	public function operators(): array {
		return (array) $this->def['operators'];
	}

	/**
	 * Whether the condition can be used on this site (e.g. WooCommerce active, geolocation enabled).
	 *
	 * @return bool
	 */
	public function available(): bool {
		return (bool) $this->def['available'];
	}

	/**
	 * Evaluates on the server.
	 *
	 * @param array<string,mixed> $rule Rule.
	 * @param Context             $ctx  Request context.
	 * @return bool
	 */
	public function evaluate( array $rule, Context $ctx ): bool {
		return (bool) call_user_func( $this->def['evaluate'], $rule, $ctx );
	}

	/**
	 * Description for the admin UI.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'id'        => $this->id,
			'label'     => (string) $this->def['label'],
			'group'     => (string) $this->def['group'],
			'operators' => $this->operators(),
			'value'     => $this->value_kind(),
			'side'      => $this->side(),
			'available' => $this->available(),
			'note'      => (string) ( $this->def['note'] ?? '' ),
		);
	}
}
