<?php
/**
 * Display-rule conditions: registry, validation and evaluation.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Rules document on a widget:  { match: all|any, items: [ { type, op, value } ] }   (empty items = everywhere)
 *
 * Evaluation is three-valued: server conditions are decided now; client conditions (URL parameters, country)
 * are passed to the browser. The result tells the renderer to show, hide, or defer to the visitor's browser.
 */
final class ConditionRegistry {

	public const MAX_RULES = 30;

	/**
	 * Conditions.
	 *
	 * @var array<string, Condition>
	 */
	private $conditions = array();

	/**
	 * Registers a condition.
	 *
	 * @param Condition $condition Condition.
	 * @return void
	 */
	public function register( Condition $condition ): void {
		$this->conditions[ $condition->id() ] = $condition;
	}

	/**
	 * Condition by id.
	 *
	 * @param string $id Id.
	 * @return Condition|null
	 */
	public function get( string $id ): ?Condition {
		return $this->conditions[ $id ] ?? null;
	}

	/**
	 * All conditions.
	 *
	 * @return array<string, Condition>
	 */
	public function all(): array {
		return $this->conditions;
	}

	/**
	 * Evaluates a rules document.
	 *
	 * @param array<string,mixed> $rules Rules.
	 * @param Context             $ctx   Context.
	 * @return array{result: string, client: array<int, array<string,mixed>>} result: show|hide|client.
	 */
	public function evaluate( array $rules, Context $ctx ): array {
		$items = array_values( (array) ( $rules['items'] ?? array() ) );
		$any   = 'any' === ( $rules['match'] ?? 'all' );
		if ( ! $items ) {
			return array(
				'result' => 'show',
				'client' => array(),
			);
		}

		$client = array();
		foreach ( $items as $rule ) {
			$condition = $this->get( (string) ( $rule['type'] ?? '' ) );
			if ( ! $condition || ! $condition->available() ) {
				// Unknown/unavailable conditions never match (safe default: don't show unexpectedly).
				$result = false;
			} elseif ( 'client' === $condition->side() ) {
				$client[] = $rule;
				continue;
			} else {
				$result = $condition->evaluate( $rule, $ctx );
			}

			if ( $any && $result ) {
				return array(
					'result' => 'show',
					'client' => array(),
				);
			}
			if ( ! $any && ! $result ) {
				return array(
					'result' => 'hide',
					'client' => array(),
				);
			}
		}

		if ( ! $client ) {
			return array(
				'result' => $any ? 'hide' : 'show',
				'client' => array(),
			);
		}
		return array(
			'result' => 'client',
			'client' => $client,
		);
	}

	/**
	 * Validates a rules document.
	 *
	 * @param mixed $raw Raw.
	 * @return array{rules: array<string,mixed>, errors: array<string,string>}
	 */
	public function sanitize( $raw ): array {
		$raw    = is_array( $raw ) ? $raw : array();
		$errors = array();
		$items  = array();
		foreach ( array_values( is_array( $raw['items'] ?? null ) ? array_slice( $raw['items'], 0, self::MAX_RULES ) : array() ) as $i => $rule ) {
			$result = $this->sanitize_rule( $rule );
			if ( isset( $result['error'] ) ) {
				$errors[ "rules.items.{$i}" ] = $result['error'];
				continue;
			}
			$items[] = $result['rule'];
		}
		return array(
			'rules'  => array(
				'match' => 'any' === ( $raw['match'] ?? 'all' ) ? 'any' : 'all',
				'items' => $items,
			),
			'errors' => $errors,
		);
	}

	/**
	 * Validates one rule.
	 *
	 * @param mixed $raw Raw rule.
	 * @return array{rule?: array<string,mixed>, error?: string}
	 */
	public function sanitize_rule( $raw ): array {
		$raw       = is_array( $raw ) ? $raw : array();
		$condition = $this->get( (string) ( $raw['type'] ?? '' ) );
		if ( ! $condition ) {
			return array( 'error' => __( 'Choose a condition.', 'bigloop-call-button' ) );
		}
		$op = (string) ( $raw['op'] ?? '' );
		if ( ! array_key_exists( $op, $condition->operators() ) ) {
			return array( 'error' => __( 'Choose how this condition should match.', 'bigloop-call-button' ) );
		}

		$kind  = $condition->value_kind();
		$value = $raw['value'] ?? null;
		$clean = null;

		if ( 0 === strpos( $kind, 'ids' ) ) {
			$ids   = array_values( array_unique( array_filter( array_map( 'intval', is_array( $value ) ? array_slice( $value, 0, 200 ) : array() ), static fn( $v ) => $v > 0 ) ) );
			$clean = $ids ?: null;
		} elseif ( 0 === strpos( $kind, 'strings' ) ) {
			$list  = array_values( array_unique( array_filter( is_array( $value ) ? array_slice( $value, 0, 300 ) : array(), static fn( $v ) => is_string( $v ) && preg_match( '/^[A-Za-z0-9_-]{1,60}$/', $v ) ) ) );
			$clean = $list ?: null;
		} elseif ( 'terms' === $kind ) {
			$tax   = is_array( $value ) && is_string( $value['taxonomy'] ?? null ) && preg_match( '/^[a-z0-9_-]{1,32}$/', $value['taxonomy'] ) ? $value['taxonomy'] : '';
			$terms = is_array( $value ) ? array_values( array_unique( array_filter( array_map( 'intval', (array) ( $value['terms'] ?? array() ) ), static fn( $v ) => $v > 0 ) ) ) : array();
			$clean = ( '' !== $tax && $terms ) ? array(
				'taxonomy' => $tax,
				'terms'    => array_slice( $terms, 0, 200 ),
			) : null;
		} elseif ( 'text' === $kind ) {
			$text  = is_string( $value ) ? trim( preg_replace( '/[\x00-\x1F\x7F<>"]/', '', $value ) ?? '' ) : '';
			$clean = ( '' !== $text && strlen( $text ) <= 200 ) ? $text : null;
		} elseif ( 'kv' === $kind ) {
			$key   = is_array( $value ) && is_string( $value['key'] ?? null ) && preg_match( '/^[A-Za-z0-9_\-\[\].]{1,60}$/', $value['key'] ) ? $value['key'] : '';
			$val   = is_array( $value ) && is_scalar( $value['value'] ?? '' ) ? trim( preg_replace( '/[\x00-\x1F\x7F<>"]/', '', (string) ( $value['value'] ?? '' ) ) ?? '' ) : '';
			$clean = '' !== $key ? array(
				'key'   => $key,
				'value' => substr( $val, 0, 200 ),
			) : null;
		} elseif ( 0 === strpos( $kind, 'choice:' ) ) {
			$choices = explode( '|', substr( $kind, 7 ) );
			$clean   = is_string( $value ) && in_array( $value, $choices, true ) ? $value : null;
		} elseif ( 'none' === $kind ) {
			$clean = '';
		}

		if ( null === $clean ) {
			return array( 'error' => __( 'Choose a value for this condition.', 'bigloop-call-button' ) );
		}
		return array(
			'rule' => array(
				'type'  => $condition->id(),
				'op'    => $op,
				'value' => $clean,
			),
		);
	}

	/**
	 * Built-in conditions.
	 *
	 * @param array{woocommerce?: bool, geo?: bool} $env Environment flags.
	 * @return self
	 */
	public static function with_defaults( array $env = array() ): self {
		$r      = new self();
		$is     = array(
			'is'     => __( 'is', 'bigloop-call-button' ),
			'is_not' => __( 'is not', 'bigloop-call-button' ),
		);
		$in_ids = static function ( array $rule, Context $ctx ): bool {
			$hit = $ctx->get( 'singular' ) && in_array( (int) $ctx->get( 'object_id' ), (array) $rule['value'], true );
			return 'is_not' === $rule['op'] ? ! $hit : $hit;
		};
		$terms  = static function ( array $rule, Context $ctx ): bool {
			$have = (array) ( $ctx->get( 'terms' )[ $rule['value']['taxonomy'] ] ?? array() );
			$hit  = (bool) array_intersect( $have, (array) $rule['value']['terms'] );
			return 'is_not' === $rule['op'] ? ! $hit : $hit;
		};

		$r->register(
			new Condition(
				'page',
				array(
					'label'     => __( 'Page', 'bigloop-call-button' ),
					'group'     => __( 'Content', 'bigloop-call-button' ),
					'operators' => $is,
					'value'     => 'ids:page',
					'evaluate'  => $in_ids,
				)
			)
		);
		$r->register(
			new Condition(
				'post',
				array(
					'label'     => __( 'Post', 'bigloop-call-button' ),
					'group'     => __( 'Content', 'bigloop-call-button' ),
					'operators' => $is,
					'value'     => 'ids:post',
					'evaluate'  => $in_ids,
				)
			)
		);
		$r->register(
			new Condition(
				'post_type',
				array(
					'label'     => __( 'Post type', 'bigloop-call-button' ),
					'group'     => __( 'Content', 'bigloop-call-button' ),
					'operators' => $is,
					'value'     => 'strings:post_types',
					'evaluate'  => static function ( array $rule, Context $ctx ): bool {
						$hit = '' !== $ctx->get( 'post_type' ) && in_array( $ctx->get( 'post_type' ), (array) $rule['value'], true );
						return 'is_not' === $rule['op'] ? ! $hit : $hit;
					},
				)
			)
		);
		$r->register(
			new Condition(
				'taxonomy',
				array(
					'label'     => __( 'Category, tag or taxonomy', 'bigloop-call-button' ),
					'group'     => __( 'Content', 'bigloop-call-button' ),
					'operators' => array(
						'is'     => __( 'has / is in', 'bigloop-call-button' ),
						'is_not' => __( 'does not have', 'bigloop-call-button' ),
					),
					'value'     => 'terms',
					'evaluate'  => $terms,
				)
			)
		);
		$r->register(
			new Condition(
				'special',
				array(
					'label'     => __( 'Special page', 'bigloop-call-button' ),
					'group'     => __( 'Content', 'bigloop-call-button' ),
					'operators' => $is,
					'value'     => 'choice:front|blog|search|404|archive',
					'evaluate'  => static function ( array $rule, Context $ctx ): bool {
						$hit = in_array( $rule['value'], (array) $ctx->get( 'special' ), true );
						return 'is_not' === $rule['op'] ? ! $hit : $hit;
					},
				)
			)
		);
		$r->register(
			new Condition(
				'url',
				array(
					'label'     => __( 'URL path', 'bigloop-call-button' ),
					'group'     => __( 'Address', 'bigloop-call-button' ),
					'operators' => array(
						'contains'     => __( 'contains', 'bigloop-call-button' ),
						'not_contains' => __( 'does not contain', 'bigloop-call-button' ),
						'starts_with'  => __( 'starts with', 'bigloop-call-button' ),
						'ends_with'    => __( 'ends with', 'bigloop-call-button' ),
						'equals'       => __( 'is exactly', 'bigloop-call-button' ),
					),
					'value'     => 'text',
					'evaluate'  => static function ( array $rule, Context $ctx ): bool {
						$path   = strtolower( rawurldecode( (string) $ctx->get( 'path' ) ) );
						$needle = strtolower( (string) $rule['value'] );
						switch ( $rule['op'] ) {
							case 'not_contains':
								return false === strpos( $path, $needle );
							case 'starts_with':
								return 0 === strpos( $path, $needle );
							case 'ends_with':
								return substr( $path, -strlen( $needle ) ) === $needle;
							case 'equals':
								return rtrim( $path, '/' ) === rtrim( $needle, '/' );
							default:
								return false !== strpos( $path, $needle );
						}
					},
				)
			)
		);
		$r->register(
			new Condition(
				'url_param',
				array(
					'label'     => __( 'URL parameter', 'bigloop-call-button' ),
					'group'     => __( 'Address', 'bigloop-call-button' ),
					'operators' => array(
						'exists'     => __( 'is present', 'bigloop-call-button' ),
						'not_exists' => __( 'is not present', 'bigloop-call-button' ),
						'equals'     => __( 'equals', 'bigloop-call-button' ),
					),
					'value'     => 'kv',
					'side'      => 'client',
					'note'      => __( 'Checked in the visitor\'s browser, so it works with page caching (e.g. ?utm_source=google).', 'bigloop-call-button' ),
				)
			)
		);
		$r->register(
			new Condition(
				'login',
				array(
					'label'     => __( 'Visitor', 'bigloop-call-button' ),
					'group'     => __( 'Visitor', 'bigloop-call-button' ),
					'operators' => array( 'is' => __( 'is', 'bigloop-call-button' ) ),
					'value'     => 'choice:logged_in|logged_out',
					'evaluate'  => static function ( array $rule, Context $ctx ): bool {
						return ( 'logged_in' === $rule['value'] ) === (bool) $ctx->get( 'logged_in' );
					},
				)
			)
		);
		$r->register(
			new Condition(
				'country',
				array(
					'label'     => __( 'Country', 'bigloop-call-button' ),
					'group'     => __( 'Visitor', 'bigloop-call-button' ),
					'operators' => array(
						'in'     => __( 'is one of', 'bigloop-call-button' ),
						'not_in' => __( 'is not one of', 'bigloop-call-button' ),
					),
					'value'     => 'strings:countries',
					'side'      => 'client',
					'available' => ! empty( $env['geo'] ),
					'note'      => __( 'Needs country detection turned on in Settings → Privacy & location.', 'bigloop-call-button' ),
				)
			)
		);

		$woo = ! empty( $env['woocommerce'] );
		$r->register(
			new Condition(
				'woo_product',
				array(
					'label'     => __( 'Product', 'bigloop-call-button' ),
					'group'     => 'WooCommerce',
					'operators' => $is,
					'value'     => 'ids:product',
					'available' => $woo,
					'evaluate'  => $in_ids,
				)
			)
		);
		$r->register(
			new Condition(
				'woo_category',
				array(
					'label'     => __( 'Product category', 'bigloop-call-button' ),
					'group'     => 'WooCommerce',
					'operators' => array(
						'is'     => __( 'is in', 'bigloop-call-button' ),
						'is_not' => __( 'is not in', 'bigloop-call-button' ),
					),
					'value'     => 'terms',
					'available' => $woo,
					'evaluate'  => $terms,
				)
			)
		);
		$r->register(
			new Condition(
				'woo_page',
				array(
					'label'     => __( 'Shop page', 'bigloop-call-button' ),
					'group'     => 'WooCommerce',
					'operators' => $is,
					'value'     => 'choice:shop|product|cart|checkout|account',
					'available' => $woo,
					'evaluate'  => static function ( array $rule, Context $ctx ): bool {
						$hit = in_array( $rule['value'], (array) $ctx->get( 'woo' ), true );
						return 'is_not' === $rule['op'] ? ! $hit : $hit;
					},
				)
			)
		);
		return $r;
	}
}
