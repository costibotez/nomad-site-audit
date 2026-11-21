<?php
defined( 'ABSPATH' ) || exit;

// Reads the plugin's stored performance samples.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class NomadSA_Performance {

	const PAGE_LIMIT = 5;
	const VITALS = array(
		'lcp' => 'largest_contentful_paint',
		'inp' => 'interaction_to_next_paint',
		'cls' => 'cumulative_layout_shift',
	);

	public static function normalize_urls( $input ) {
		$urls = array();
		$home = home_url( '/' );
		$home_path = untrailingslashit( (string) wp_parse_url( $home, PHP_URL_PATH ) );
		foreach ( preg_split( '/\R/', $input ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$url = '/' === $line[0] && 0 !== strpos( $line, '//' ) ? home_url( $line ) : $line;
			$parts = wp_parse_url( $url );
			$path = $parts['path'] ?? '/';
			if ( strlen( $url ) > 512 || ! self::same_origin( $url, $home ) || esc_url_raw( $url, array( 'https', 'http' ) ) !== $url
				|| isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] )
				|| preg_match( '#(?:^|/)\.\.(?:/|$)|%(?:00|2e|2f|5c)|[[:cntrl:]\\\\]#i', $path )
				|| ( '' !== $home_path && $path !== $home_path && 0 !== strpos( $path, $home_path . '/' ) ) ) {
				return new WP_Error( 'nomadsa_performance_url', __( 'Enter public pages on this site without query strings or fragments. Use a path such as /about/ or the full URL.', 'nomad-site-audit' ) );
			}
			$urls[ $url ] = true;
			if ( count( $urls ) > self::PAGE_LIMIT ) {
				return new WP_Error( 'nomadsa_performance_limit', __( 'You can monitor up to five pages.', 'nomad-site-audit' ) );
			}
		}
		return array_keys( $urls );
	}

	private static function same_origin( $url, $home ) {
		$target = wp_parse_url( $url );
		$site = wp_parse_url( $home );
		$port = 'https' === ( $site['scheme'] ?? '' ) ? 443 : 80;
		return ! empty( $target['host'] ) && ! empty( $site['host'] )
			&& strtolower( $target['host'] ) === strtolower( $site['host'] )
			&& ( $target['scheme'] ?? '' ) === $site['scheme']
			&& ( $target['port'] ?? $port ) === ( $site['port'] ?? $port );
	}

	public static function sample( array $pages, $api_key ) {
		$samples = array();
		foreach ( $pages as $url ) {
			if ( ! self::same_origin( $url, home_url( '/' ) ) ) {
				continue;
			}
			$samples[ $url ] = self::probe( $url, $api_key );
		}
		if ( ! $samples ) {
			return new WP_Error( 'nomadsa_no_pages', __( 'Save at least one page to monitor first.', 'nomad-site-audit' ) );
		}
		return NomadSA_Audit_History::save( 'performance', array( 'pages' => $samples ) );
	}

	private static function probe( $url, $api_key ) {
		$limit = 2 * MB_IN_BYTES;
		$start = microtime( true );
		$response = wp_safe_remote_get( $url, array( 'timeout' => 2, 'redirection' => 0, 'limit_response_size' => $limit ) );
		$elapsed = (int) round( 1000 * ( microtime( true ) - $start ) );
		$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		$ok = $status >= 200 && $status < 300;
		$type = wp_remote_retrieve_header( $response, 'content-type' );
		$html = $ok && is_string( $type ) && preg_match( '#^(text/html|application/xhtml\+xml)\b#i', $type );
		$bytes = $html ? strlen( wp_remote_retrieve_body( $response ) ) : null;
		$vitals = '' !== $api_key && 'https' === wp_parse_url( $url, PHP_URL_SCHEME )
			? self::vitals( $url, $api_key )
			: array( 'status' => '' !== $api_key ? 'https_required' : 'disabled', 'metrics' => array() );
		return array(
			'status' => $status,
			'response_ms' => $ok ? $elapsed : null,
			'html_kb' => null === $bytes ? null : round( $bytes / KB_IN_BYTES, 1 ),
			'size_capped' => null !== $bytes && $bytes >= $limit,
			'vitals' => $vitals,
		);
	}

	private static function vitals( $url, $api_key ) {
		$response = wp_safe_remote_post( 'https://chromeuxreport.googleapis.com/v1/records:queryRecord', array(
			'timeout' => 3,
			'redirection' => 0,
			'limit_response_size' => 64 * KB_IN_BYTES,
			'headers' => array( 'Content-Type' => 'application/json', 'X-Goog-Api-Key' => $api_key ),
			'body' => wp_json_encode( array( 'url' => $url, 'formFactor' => 'PHONE', 'metrics' => array_values( self::VITALS ) ) ),
		) );
		$status = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return array( 'status' => 404 === $status ? 'no_data' : 'error', 'metrics' => array() );
		}

		$record = json_decode( wp_remote_retrieve_body( $response ), true )['record'] ?? array();
		$metrics = array();
		foreach ( self::VITALS as $name => $key ) {
			$value = $record['metrics'][ $key ]['percentiles']['p75'] ?? null;
			if ( is_numeric( $value ) && (float) $value >= 0 ) {
				$metrics[ $name ] = (float) $value;
			}
		}
		return array( 'status' => $metrics ? 'ok' : 'no_data', 'metrics' => $metrics );
	}

	public static function history( array $pages, $limit = 14 ) {
		global $wpdb;
		if ( ! $pages ) {
			return array();
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT created_at, payload FROM %i WHERE source = %s ORDER BY id DESC LIMIT %d', NomadSA_DB::table( 'audit_runs' ), 'performance', $limit ), ARRAY_A );
		$history = array_fill_keys( $pages, array() );
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload'], true );
			foreach ( array_intersect_key( $payload['pages'] ?? array(), $history ) as $url => $sample ) {
				$history[ $url ][] = array( 'created_at' => $row['created_at'] ) + $sample;
			}
		}
		return $history;
	}

	public static function weekly_regressions( array $pages ) {
		$alerts = array();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
		$fresh = gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS );
		foreach ( self::history( $pages, NomadSA_Audit_History::PERFORMANCE_LIMIT ) as $url => $samples ) {
			if ( count( $samples ) < 2 || $samples[0]['created_at'] < $fresh ) {
				continue;
			}
			$current = array_shift( $samples );
			foreach ( $samples as $baseline ) {
				if ( $baseline['created_at'] <= $cutoff ) {
					break;
				}
			}
			$flags = self::regressions( $current, $baseline );
			if ( $flags ) {
				$alerts[ $url ] = $flags;
			}
		}
		return $alerts;
	}

	public static function regressions( array $current, array $before ) {
		$flags = array();
		if ( null === $current['response_ms'] && null !== $before['response_ms'] ) {
			$flags[] = __( 'Page became unavailable or returned a non-success HTTP status', 'nomad-site-audit' );
		}
		if ( null !== $current['response_ms'] && null !== $before['response_ms']
			&& $current['response_ms'] >= $before['response_ms'] + max( 150, 0.25 * $before['response_ms'] ) ) {
			$flags[] = __( 'Page response slowed', 'nomad-site-audit' );
		}
		if ( null !== $current['html_kb'] && null !== $before['html_kb'] && ! $before['size_capped']
			&& $current['html_kb'] >= $before['html_kb'] + max( 25, 0.20 * $before['html_kb'] ) ) {
			$flags[] = __( 'HTML response grew', 'nomad-site-audit' );
		}
		$thresholds = array( 'lcp' => array( 2500, 250 ), 'inp' => array( 200, 50 ), 'cls' => array( 0.1, 0.05 ) );
		foreach ( $thresholds as $metric => list( $target, $change ) ) {
			$a = $current['vitals']['metrics'][ $metric ] ?? null;
			$b = $before['vitals']['metrics'][ $metric ] ?? null;
			if ( null !== $a && null !== $b && $a > $target && round( $a - $b, 4 ) >= $change ) {
				/* translators: %s: Core Web Vital abbreviation. */
				$flags[] = sprintf( __( '%s worsened', 'nomad-site-audit' ), strtoupper( $metric ) );
			}
		}
		return $flags;
	}
}
