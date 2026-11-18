<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tool registry. Audit tools only read. Fix tools change the site and use a preview then confirm pattern.
 */
class NomadSA_Tools {

	/** @var array name => definition */
	private static $tools = array();

	public static function register( $name, array $def ) {
		$def['name'] = $name;
		$def += array(
			'kind'        => 'audit',
			'title'       => $name,
			'description' => '',
			'properties'  => array(),
			'required'    => array(),
			'destructive' => false,
		);
		self::$tools[ $name ] = $def;
	}

	private static function boot() {
		if ( empty( self::$tools ) ) {
			NomadSA_Audit_Tools::register();
			NomadSA_Fix_Tools::register();
			do_action( 'nomadsa_register_tools' );
		}
	}

	public static function all() {
		self::boot();
		return self::$tools;
	}

	public static function list_for_client() {
		self::boot();
		$out = array();
		foreach ( self::$tools as $t ) {
			if ( 'fix' === $t['kind'] && ! NomadSA_OAuth::can_fix() ) {
				continue;
			}
			$props = $t['properties'];
			if ( 'fix' === $t['kind'] ) {
				$props['confirm'] = array(
					'type'        => 'boolean',
					'description' => 'Leave out or false to get a preview. Set true only after the user has approved this exact change.',
				);
			}
			$schema = array( 'type' => 'object', 'properties' => empty( $props ) ? new stdClass() : $props );
			if ( ! empty( $t['required'] ) ) {
				$schema['required'] = $t['required'];
			}
			$description = $t['description'];
			if ( 'fix' === $t['kind'] ) {
				$description .= ' Changes the live site: call without confirm first, show the preview to the user, then call with confirm true once they approve.';
			}
			$out[] = array(
				'name'        => $t['name'],
				'title'       => $t['title'],
				'description' => $description,
				'inputSchema' => $schema,
				'annotations' => array(
					'title'           => $t['title'],
					'readOnlyHint'    => 'audit' === $t['kind'],
					'destructiveHint' => 'fix' === $t['kind'] && $t['destructive'],
					'idempotentHint'  => 'audit' === $t['kind'],
					'openWorldHint'   => false,
				),
			);
		}
		return $out;
	}

	public static function call( $name, array $args ) {
		self::boot();
		if ( ! isset( self::$tools[ $name ] ) ) {
			return self::error_result( 'Unknown tool: ' . $name );
		}
		$t = self::$tools[ $name ];
		if ( 'fix' === $t['kind'] && ! NomadSA_OAuth::can_fix() ) {
			NomadSA_DB::log( $name, 'fix', 'denied', 'Fixes are not allowed for this connection.', $args );
			return self::error_result( 'Fixes are not allowed for this connection. An administrator can switch them on under Settings, Nomad Site Audit, then reconnect.' );
		}
		foreach ( $t['required'] as $req ) {
			if ( ! array_key_exists( $req, $args ) ) {
				return self::error_result( 'Missing required argument: ' . $req );
			}
		}

		$confirm = ! empty( $args['confirm'] );
		unset( $args['confirm'] );

		try {
			$result = call_user_func( $t['callback'], $args, $confirm );
		} catch ( \Throwable $e ) {
			NomadSA_DB::log( $name, $t['kind'], 'error', $e->getMessage(), $args );
			return self::error_result( 'The tool failed: ' . $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			NomadSA_DB::log( $name, $t['kind'], 'error', $result->get_error_message(), $args );
			return self::error_result( $result->get_error_message() );
		}

		$status = 'audit' === $t['kind'] ? 'read' : ( $confirm ? 'applied' : 'preview' );
		if ( 'fix' === $t['kind'] && ! $confirm ) {
			$result = array(
				'preview'      => true,
				'changes'      => $result,
				'next_step'    => 'Nothing has changed yet. Show this preview to the user and call again with confirm true only if they approve.',
			);
		}
		$summary = is_array( $result ) && isset( $result['summary'] ) ? $result['summary'] : ( is_string( $result ) ? $result : $status );
		NomadSA_DB::log( $name, $t['kind'], $status, is_string( $summary ) ? $summary : $status, $args );

		$text = is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$out  = array( 'content' => array( array( 'type' => 'text', 'text' => (string) $text ) ), 'isError' => false );
		if ( is_array( $result ) ) {
			$out['structuredContent'] = $result;
		}
		return $out;
	}

	public static function error_result( $message ) {
		return array( 'content' => array( array( 'type' => 'text', 'text' => $message ) ), 'isError' => true );
	}

	/* ---------------------------------------------------------------- Shared helpers */

	/** Paths that must never be read or written through the connector. */
	public static function is_sensitive_path( $path ) {
		$base = strtolower( basename( $path ) );
		$deny = array( 'wp-config.php', 'wp-config-sample.php', '.env', '.htpasswd', '.user.ini', 'php.ini', 'auth.json', 'id_rsa', 'id_ed25519', '.my.cnf' );
		if ( in_array( $base, $deny, true ) || 0 === strpos( $base, '.env' ) ) {
			return true;
		}
		if ( preg_match( '/\.(sql|sql\.gz|key|pem|p12|pfx|crt|sqlite|db|bak|zip|tar|gz|7z|rar|log\.\d+)$/i', $base ) ) {
			return true;
		}
		if ( preg_match( '#/(\.git|\.ssh|node_modules|updraft|backups?|ai1wm-backups)(/|$)#i', $path ) ) {
			return true;
		}
		return false;
	}

	/** Resolves a path relative to the WordPress root and keeps it inside. Returns absolute path or WP_Error. */
	public static function resolve_path( $relative, $must_exist = true ) {
		$root = wp_normalize_path( untrailingslashit( realpath( ABSPATH ) ) );
		$rel  = ltrim( wp_normalize_path( (string) $relative ), '/' );
		if ( false !== strpos( $rel, '..' ) ) {
			return new WP_Error( 'nomadsa_path', 'Paths with .. are not allowed.' );
		}
		$abs = $root . '/' . $rel;
		if ( $must_exist ) {
			$real = realpath( $abs );
			if ( false === $real ) {
				return new WP_Error( 'nomadsa_path', 'Path not found: ' . $rel );
			}
		} else {
			// The file or its folders may not exist yet: resolve the nearest existing folder.
			$existing = $abs;
			$rest     = '';
			while ( ! file_exists( $existing ) && strlen( $existing ) > strlen( $root ) ) {
				$rest     = '/' . basename( $existing ) . $rest;
				$existing = dirname( $existing );
			}
			$real = realpath( $existing );
			if ( false === $real ) {
				return new WP_Error( 'nomadsa_path', 'Path not found: ' . $rel );
			}
			$real .= $rest;
		}
		$real = wp_normalize_path( $real );
		if ( 0 !== strpos( $real . '/', $root . '/' ) ) {
			return new WP_Error( 'nomadsa_path', 'Path is outside the WordPress folder.' );
		}
		if ( self::is_sensitive_path( $real ) ) {
			return new WP_Error( 'nomadsa_path', 'This file is protected and cannot be accessed through the connector.' );
		}
		return $real;
	}

	public static function relative( $abs ) {
		$root = wp_normalize_path( untrailingslashit( realpath( ABSPATH ) ) );
		return ltrim( substr( wp_normalize_path( $abs ), strlen( $root ) ), '/' );
	}

	/**
	 * True for harmless PHP files plugins put in uploads: empty "silence is golden" index files
	 * and data files that stop straight away (for example Sucuri's <?php exit(0); ?> logs).
	 */
	public static function is_guard_file( $path ) {
		$head = (string) @file_get_contents( $path, false, null, 0, 600 );
		$code = preg_replace( '#/\*.*?\*/|//[^\n]*|\#[^\n]*#s', '', $head );
		$code = trim( str_replace( array( '<?php', '?>' ), '', $code ) );
		if ( '' === $code && filesize( $path ) < 600 ) {
			return true; // Only comments.
		}
		return (bool) preg_match( '/^\s*<\?php\s*(?:\/\*.*?\*\/\s*|\/\/[^\n]*\n\s*)*(exit|die)\s*(\(\s*[\'"\d]*\s*\))?\s*;/is', $head );
	}

	/** Removes things that look like secrets from text before it leaves the site. */
	public static function redact( $text ) {
		$text = preg_replace( "/(define\s*\(\s*['\"][A-Z_]*(KEY|SALT|PASSWORD|PASS|SECRET|TOKEN)[A-Z_]*['\"]\s*,\s*)(['\"]).*?\\3/i", '$1$3[redacted]$3', (string) $text );
		$text = preg_replace( '/((?:api[_-]?key|secret|password|passwd|token|bearer)["\']?\s*[:=]>?\s*["\'])[^"\']{6,}(["\'])/i', '$1[redacted]$2', $text );
		$text = preg_replace( '/\b(sk|pk|rk)_(live|test)_[A-Za-z0-9]{10,}\b/', '[redacted key]', $text );
		$text = preg_replace( '/\bAKIA[0-9A-Z]{16}\b/', '[redacted key]', $text );
		return $text;
	}

	public static function int_arg( $args, $key, $default, $min, $max ) {
		$v = isset( $args[ $key ] ) ? (int) $args[ $key ] : $default;
		return max( $min, min( $max, $v ) );
	}

	public static function fetch( $url, $method = 'GET' ) {
		$start = microtime( true );
		$res   = wp_remote_request( $url, array(
			'method'      => $method,
			'timeout'     => 15,
			'redirection' => 3,
			'sslverify'   => apply_filters( 'https_local_ssl_verify', true ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			'user-agent'  => 'NomadSiteAudit/' . NOMADSA_VERSION . '; ' . home_url(),
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		) );
		$ms = (int) round( ( microtime( true ) - $start ) * 1000 );
		return array( $res, $ms );
	}
}
