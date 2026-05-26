<?php

/**
 * Minimal iCal parser that extracts VEVENT data.
 *
 * Handles:
 *   - RFC 5545 line unfolding
 *   - Property parameters (e.g. DTSTART;TZID=Europe/Helsinki:...)
 *   - UTC timestamps (ending in Z)
 *   - Local timestamps with a named timezone
 *   - Date-only values (YYYYMMDD)
 *   - Standard iCal text escapes
 */
class Nimenhuuto_iCal_Parser {

	public function parse( string $ical ): array {
		$lines    = preg_split( '/\r\n|\r|\n/', $ical );
		$unfolded = $this->unfold_lines( $lines );

		$events      = [];
		$in_event    = false;
		$current     = [];

		foreach ( $unfolded as $line ) {
			$line = rtrim( $line );

			if ( $line === 'BEGIN:VEVENT' ) {
				$in_event = true;
				$current  = [];
				continue;
			}

			if ( $line === 'END:VEVENT' ) {
				if ( $in_event && ! empty( $current['start'] ) ) {
					$events[] = $current;
				}
				$in_event = false;
				continue;
			}

			if ( ! $in_event ) {
				continue;
			}

			[ $prop, $value ] = $this->split_property( $line );

			switch ( $prop['name'] ) {
				case 'SUMMARY':
					$current['title'] = $this->unescape( $value );
					break;
				case 'DTSTART':
					$current['start'] = $this->parse_dt( $value, $prop['params'] );
					break;
				case 'DTEND':
					$current['end'] = $this->parse_dt( $value, $prop['params'] );
					break;
				case 'LOCATION':
					$current['location'] = $this->unescape( $value );
					break;
				case 'DESCRIPTION':
					$current['description'] = $this->unescape( $value );
					break;
				case 'UID':
					$current['uid'] = $value;
					break;
			}
		}

		return $events;
	}

	// -------------------------------------------------------------------------

	private function unfold_lines( array $lines ): array {
		$result = [];

		foreach ( $lines as $line ) {
			if ( ! empty( $result ) && ( str_starts_with( $line, ' ' ) || str_starts_with( $line, "\t" ) ) ) {
				$result[ count( $result ) - 1 ] .= substr( $line, 1 );
			} else {
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Split "PROPERTY;PARAM=VAL:value" into property descriptor and raw value.
	 */
	private function split_property( string $line ): array {
		$colon = strpos( $line, ':' );
		if ( $colon === false ) {
			return [ [ 'name' => '', 'params' => [] ], '' ];
		}

		$prop_part = substr( $line, 0, $colon );
		$value     = substr( $line, $colon + 1 );

		$parts      = explode( ';', $prop_part );
		$name       = strtoupper( array_shift( $parts ) );
		$params     = [];

		foreach ( $parts as $param ) {
			$eq = strpos( $param, '=' );
			if ( $eq !== false ) {
				$key          = strtoupper( substr( $param, 0, $eq ) );
				$params[$key] = substr( $param, $eq + 1 );
			}
		}

		return [ [ 'name' => $name, 'params' => $params ], $value ];
	}

	private function parse_dt( string $value, array $params ): ?DateTime {
		try {
			$tzid = $params['TZID'] ?? null;
			$site_tz = wp_timezone();

			if ( strlen( $value ) === 8 ) {
				// Date-only: YYYYMMDD — treat as midnight in site timezone.
				$tz = $tzid ? new DateTimeZone( $tzid ) : $site_tz;
				$dt = DateTime::createFromFormat( 'Ymd', $value, $tz );
			} elseif ( str_ends_with( $value, 'Z' ) ) {
				// UTC float time.
				$dt = DateTime::createFromFormat( 'Ymd\THis\Z', $value, new DateTimeZone( 'UTC' ) );
				if ( $dt ) {
					$dt->setTimezone( $site_tz );
				}
			} else {
				// Local time, possibly with TZID.
				$tz = $tzid ? new DateTimeZone( $tzid ) : $site_tz;
				$dt = DateTime::createFromFormat( 'Ymd\THis', $value, $tz );
				if ( $dt ) {
					$dt->setTimezone( $site_tz );
				}
			}

			return $dt ?: null;
		} catch ( \Exception $e ) {
			return null;
		}
	}

	private function unescape( string $value ): string {
		return str_replace(
			[ '\\n', '\\N', '\\,', '\\;', '\\\\' ],
			[ "\n", "\n", ',', ';', '\\' ],
			$value
		);
	}
}
