<?php

/**
 * Fetches and caches the next upcoming event for a Nimenhuuto account.
 *
 * Converts webcal:// URLs to https:// before fetching.
 * Results are cached in WordPress transients for one hour.
 */
class Nimenhuuto_Fetcher {

	private const CACHE_TTL    = HOUR_IN_SECONDS;
	private const CACHE_PREFIX = 'wp_nimenhuuto_';

	public function get_next_event( array $account ): ?array {
		$cache_key = self::CACHE_PREFIX . md5( $account['id'] );
		$cached    = get_transient( $cache_key );

		// Transient stores false/empty string when there is no upcoming event,
		// and an array when there is one. A missing transient returns boolean false.
		if ( $cached !== false ) {
			return is_array( $cached ) ? $cached : null;
		}

		$events = $this->fetch_events( $account );
		$next   = $this->find_next_event( $events );

		// Cache a sentinel so we don't hammer the server when there's no event.
		set_transient( $cache_key, $next ?? '', self::CACHE_TTL );

		return $next;
	}

	public function clear_cache( string $account_id ): void {
		delete_transient( self::CACHE_PREFIX . md5( $account_id ) );
	}

	// -------------------------------------------------------------------------

	private function fetch_events( array $account ): array {
		$url = $this->resolve_ical_url( $account );

		if ( empty( $url ) ) {
			return [];
		}

		$response = wp_remote_get( $url, [
			'timeout'    => 15,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
		] );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return [];
		}

		$body   = wp_remote_retrieve_body( $response );
		$parser = new Nimenhuuto_iCal_Parser();

		return $parser->parse( $body );
	}

	/**
	 * Returns the iCal URL to fetch, preferring the explicitly configured URL,
	 * then falling back to deriving it from the base Nimenhuuto URL.
	 * Converts webcal:// → https://.
	 */
	private function resolve_ical_url( array $account ): string {
		$url = ! empty( $account['ical_url'] )
			? $account['ical_url']
			: rtrim( $account['url'], '/' ) . '/calendar/ical';

		return $this->webcal_to_https( $url );
	}

	private function webcal_to_https( string $url ): string {
		if ( strncasecmp( $url, 'webcal://', 9 ) === 0 ) {
			return 'https://' . substr( $url, strlen( 'webcal://' ) );
		}
		return $url;
	}

	private function find_next_event( array $events ): ?array {
		if ( empty( $events ) ) {
			return null;
		}

		$now = new DateTime( 'now', wp_timezone() );

		$future = array_filter( $events, function ( $event ) use ( $now ) {
			$start = $event['start'] ?? null;
			// Include events starting now or in the future.
			return $start instanceof DateTimeInterface && $start >= $now;
		} );

		if ( empty( $future ) ) {
			return null;
		}

		usort( $future, fn( $a, $b ) => $a['start'] <=> $b['start'] );

		return reset( $future );
	}
}
