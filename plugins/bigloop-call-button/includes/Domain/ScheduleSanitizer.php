<?php
/**
 * Validates schedule documents.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Domain;

use Bigloop\CallButton\Scheduler\ScheduleEngine;
use Bigloop\CallButton\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Schedule document: { schema, timezone, weekly, date_range, holidays, special }. See ScheduleEngine.
 * timezone '' means "use the site's timezone" (resolved at evaluation time, so changing the site setting applies).
 */
final class ScheduleSanitizer {

	public const SCHEMA = 1;

	/**
	 * Defaults: Monday–Friday 9:00–17:00.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		$work = array( array( '09:00', '17:00' ) );
		return array(
			'schema'     => self::SCHEMA,
			'timezone'   => '',
			'weekly'     => array(
				'mon' => $work,
				'tue' => $work,
				'wed' => $work,
				'thu' => $work,
				'fri' => $work,
				'sat' => array(),
				'sun' => array(),
			),
			'date_range' => array(
				'from' => '',
				'to'   => '',
			),
			'holidays'   => array(),
			'special'    => array(),
		);
	}

	/**
	 * Validates { title, config }.
	 *
	 * @param mixed $input Raw.
	 * @return array{data: array{title:string, enabled:bool, config:array<string,mixed>}, errors: array<string,string>}
	 */
	public function sanitize( $input ): array {
		$in     = is_array( $input ) ? $input : array();
		$raw    = is_array( $in['config'] ?? null ) ? $in['config'] : array();
		$errors = array();
		$out    = self::defaults();

		$title = Sanitize::text( $in['title'] ?? '', 100 );
		if ( null === $title || '' === $title ) {
			$errors['title'] = __( 'Give the schedule a name, e.g. "Office hours".', 'bigloop-call-button' );
			$title           = (string) $title;
		}

		$tz = $raw['timezone'] ?? '';
		if ( ! is_string( $tz ) || ( '' !== $tz && ! in_array( $tz, \DateTimeZone::listIdentifiers(), true ) && ! preg_match( '/^[+-](0\d|1[0-4]):[0-5]\d$/', $tz ) ) ) {
			$errors['timezone'] = __( 'Choose a timezone from the list.', 'bigloop-call-button' );
		} else {
			$out['timezone'] = $tz;
		}

		$weekly = is_array( $raw['weekly'] ?? null ) ? $raw['weekly'] : array();
		foreach ( ScheduleEngine::DAYS as $day ) {
			if ( array_key_exists( $day, $weekly ) ) {
				$out['weekly'][ $day ] = $this->intervals( $weekly[ $day ], "weekly.{$day}", $errors );
			}
		}

		$range = is_array( $raw['date_range'] ?? null ) ? $raw['date_range'] : array();
		foreach ( array( 'from', 'to' ) as $k ) {
			$d = $this->date( $range[ $k ] ?? '' );
			if ( null === $d ) {
				$errors[ "date_range.{$k}" ] = __( 'Use a valid date.', 'bigloop-call-button' );
			} else {
				$out['date_range'][ $k ] = $d;
			}
		}
		if ( '' !== $out['date_range']['from'] && '' !== $out['date_range']['to'] && $out['date_range']['to'] < $out['date_range']['from'] ) {
			$errors['date_range.to'] = __( 'The end date must be on or after the start date.', 'bigloop-call-button' );
		}

		foreach ( array_values( is_array( $raw['holidays'] ?? null ) ? $raw['holidays'] : array() ) as $i => $h ) {
			$from  = $this->date( $h['from'] ?? '' );
			$to    = $this->date( $h['to'] ?? '' );
			$label = Sanitize::text( $h['label'] ?? '', 80 );
			if ( null === $from || '' === $from ) {
				$errors[ "holidays.{$i}.from" ] = __( 'Choose the first closed day.', 'bigloop-call-button' );
				continue;
			}
			if ( null === $to ) {
				$errors[ "holidays.{$i}.to" ] = __( 'Use a valid date.', 'bigloop-call-button' );
				continue;
			}
			if ( '' !== $to && $to < $from ) {
				$errors[ "holidays.{$i}.to" ] = __( 'The last day must be on or after the first day.', 'bigloop-call-button' );
				continue;
			}
			$out['holidays'][] = array(
				'from'  => $from,
				'to'    => '' !== $to ? $to : $from,
				'label' => (string) $label,
			);
		}

		$seen = array();
		foreach ( array_values( is_array( $raw['special'] ?? null ) ? $raw['special'] : array() ) as $i => $sp ) {
			$date = $this->date( $sp['date'] ?? '' );
			if ( null === $date || '' === $date ) {
				$errors[ "special.{$i}.date" ] = __( 'Choose a date.', 'bigloop-call-button' );
				continue;
			}
			if ( isset( $seen[ $date ] ) ) {
				$errors[ "special.{$i}.date" ] = __( 'This date already has special hours.', 'bigloop-call-button' );
				continue;
			}
			$seen[ $date ]    = true;
			$out['special'][] = array(
				'date'      => $date,
				'intervals' => $this->intervals( $sp['intervals'] ?? array(), "special.{$i}.intervals", $errors ),
				'label'     => (string) Sanitize::text( $sp['label'] ?? '', 80 ),
			);
		}
		usort(
			$out['special'],
			static function ( $a, $b ) {
				return strcmp( $a['date'], $b['date'] );
			}
		);

		return array(
			'data'   => array(
				'title'   => (string) $title,
				'enabled' => true,
				'config'  => $out,
			),
			'errors' => $errors,
		);
	}

	/**
	 * Validates a list of [start, end] intervals.
	 *
	 * @param mixed                $raw    Raw.
	 * @param string               $path   Error path.
	 * @param array<string,string> $errors Errors.
	 * @return array<int, array{0:string,1:string}>
	 */
	private function intervals( $raw, string $path, array &$errors ): array {
		$out = array();
		foreach ( array_values( is_array( $raw ) ? array_slice( $raw, 0, 12 ) : array() ) as $i => $pair ) {
			$start = is_array( $pair ) ? (string) ( $pair[0] ?? '' ) : '';
			$end   = is_array( $pair ) ? (string) ( $pair[1] ?? '' ) : '';
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $start ) || ! preg_match( '/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/', $end ) ) {
				$errors[ "{$path}.{$i}" ] = __( 'Use times like 09:00 and 17:30.', 'bigloop-call-button' );
				continue;
			}
			if ( $start === $end ) {
				$errors[ "{$path}.{$i}" ] = __( 'Opening and closing times must differ.', 'bigloop-call-button' );
				continue;
			}
			$out[] = array( $start, $end );
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a[0], $b[0] );
			}
		);
		return $out;
	}

	/**
	 * Y-m-d date, '' for empty, null when invalid.
	 *
	 * @param mixed $raw Raw.
	 * @return string|null
	 */
	private function date( $raw ): ?string {
		if ( '' === $raw || null === $raw ) {
			return '';
		}
		if ( ! is_string( $raw ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return $raw;
	}
}
