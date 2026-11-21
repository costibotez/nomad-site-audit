<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registry for the connector's built-in read-only tools.
 */
class NomadSA_Tools {

	private static $tools = array();

	private static function all() {
		if ( empty( self::$tools ) ) {
			self::$tools = NomadSA_Audit_Tools::definitions();
		}
		return self::$tools;
	}

	public static function list_for_client() {
		$out = array();
		foreach ( self::all() as $name => $tool ) {
			$props = $tool['properties'] ?? array();
			$schema = array(
				'type'                 => 'object',
				'properties'           => $props ?: new stdClass(),
				'additionalProperties' => false,
			);
			if ( ! empty( $tool['required'] ) ) {
				$schema['required'] = $tool['required'];
			}
			$out[] = array(
				'name'        => $name,
				'title'       => $tool['title'],
				'description' => $tool['description'],
				'inputSchema' => $schema,
				'annotations' => array( 'readOnlyHint' => true ),
			);
		}
		return $out;
	}

	public static function call( $name, array $args ) {
		$tool = self::all()[ $name ] ?? null;
		if ( ! $tool ) {
			return self::error_result( 'Unknown tool.' );
		}
		foreach ( $tool['required'] ?? array() as $key ) {
			if ( ! array_key_exists( $key, $args ) ) {
				return self::error_result( 'Missing required argument: ' . $key );
			}
		}
		$clean = array();
		foreach ( $args as $key => $value ) {
			if ( ! isset( $tool['properties'][ $key ] ) ) {
				return self::error_result( 'Unexpected argument.' );
			}
			$schema = $tool['properties'][ $key ];
			$value = self::sanitize_argument( $key, $value, $schema );
			if ( is_wp_error( $value ) ) {
				return self::error_result( 'Invalid argument: ' . $key );
			}
			$clean[ $key ] = $value;
		}

		try {
			$result = call_user_func( $tool['callback'], $clean );
		} catch ( Throwable $error ) {
			NomadSA_DB::log( $name, 'audit', 'error', 'Tool execution failed.' );
			return self::error_result( 'The tool could not complete the audit.' );
		}

		if ( is_wp_error( $result ) ) {
			NomadSA_DB::log( $name, 'audit', 'error', $result->get_error_code() );
			return self::error_result( $result->get_error_message() );
		}

		NomadSA_DB::log( $name, 'audit', 'read', 'Audit completed.' );
		$text = is_string( $result ) ? $result : wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$out = array(
			'content' => array( array( 'type' => 'text', 'text' => (string) $text ) ),
			'isError' => false,
		);
		if ( is_array( $result ) ) {
			$out['structuredContent'] = $result;
		}
		return $out;
	}

	private static function sanitize_argument( $key, $value, array $schema ) {
		$invalid = new WP_Error( 'nomadsa_argument' );
		switch ( $schema['type'] ) {
			case 'boolean':
				return is_bool( $value ) ? $value : $invalid;
			case 'integer':
				return is_int( $value ) ? $value : $invalid;
			case 'array':
				if ( ! is_array( $value ) || count( $value ) > 50 ) {
					return $invalid;
				}
				$items = array();
				foreach ( $value as $item ) {
					$item = self::sanitize_argument( $key, $item, $schema['items'] );
					if ( is_wp_error( $item ) ) {
						return $invalid;
					}
					$items[] = $item;
				}
				return $items;
			case 'string':
				if ( ! is_string( $value ) || strlen( $value ) > 2048 || preg_match( '/[[:cntrl:]]/', $value ) ) {
					return $invalid;
				}
				if ( isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
					return $invalid;
				}
				// Paths and code search patterns are literal strings. Their consumers validate path
				// containment and regex syntax respectively, so preserve their punctuation.
				if ( 'path' === $key || 'pattern' === $key ) {
					return wp_check_invalid_utf8( $value ) === $value ? $value : $invalid;
				}
				if ( 'url' === $key ) {
					$url = esc_url_raw( $value, array( 'http', 'https' ) );
					return '' !== $value && '' === $url ? $invalid : $url;
				}
				if ( in_array( $key, array( 'plugin', 'post_type', 'post_types', 'status', 'role', 'extensions' ), true ) ) {
					return sanitize_key( $value );
				}
				return sanitize_text_field( $value );
		}
		return $invalid;
	}

	public static function error_result( $message ) {
		return array( 'content' => array( array( 'type' => 'text', 'text' => $message ) ), 'isError' => true );
	}

	/* ---------------------------------------------------------------- Shared helpers */

	/** Paths protected from connector file inspection. */
	public static function is_sensitive_path( $path ) {
		$base = strtolower( basename( $path ) );
		$deny = array( 'wp-config.php', 'wp-config-sample.php', '.env', '.htpasswd', '.htaccess', '.user.ini', 'php.ini', 'auth.json', 'id_rsa', 'id_ed25519', '.my.cnf' );
		if ( in_array( $base, $deny, true ) || 0 === strpos( $base, '.env' ) || 0 === strpos( $base, 'wp-config.php.' ) ) {
			return true;
		}
		if ( preg_match( '/\.(sql|sql\.gz|key|pem|p12|pfx|crt|sqlite|db|bak|zip|tar|gz|7z|rar|log|log\.\d+)$/i', $base ) ) {
			return true;
		}
		if ( preg_match( '#/(\.git|\.ssh|node_modules|updraft|backups?|ai1wm-backups)(/|$)#i', $path ) ) {
			return true;
		}
		return false;
	}

	/** Configured filesystem locations, with aliases for roots outside WordPress. */
	private static function path_roots() {
		return array(
			'@content'      => WP_CONTENT_DIR,
			'@uploads'      => wp_get_upload_dir()['basedir'],
			'@plugins'      => WP_PLUGIN_DIR,
			'@mu-plugins'   => WPMU_PLUGIN_DIR,
			'@theme'        => get_stylesheet_directory(),
			'@parent-theme' => get_template_directory(),
			''              => ABSPATH,
		);
	}

	public static function within( $path, $root ) {
		$root = untrailingslashit( wp_normalize_path( $root ) );
		return 0 === strpos( untrailingslashit( wp_normalize_path( $path ) ) . '/', $root . '/' );
	}

	/** Resolves an existing read-only path and checks both its spelling and its real location. */
	public static function resolve_path( $relative ) {
		$rel = trim( wp_normalize_path( $relative ), '/' );
		if ( preg_match( '#(?:^|/)\.\.(?:/|$)|[[:cntrl:]:]#', $rel ) ) {
			return new WP_Error( 'nomadsa_path', 'Invalid relative path.' );
		}

		$roots = self::path_roots();
		$alias = explode( '/', $rel, 2 )[0];
		if ( '' !== $alias && isset( $roots[ $alias ] ) ) {
			$rel = ltrim( substr( $rel, strlen( $alias ) ), '/' );
		} else {
			$alias = '';
		}
		$root = realpath( $roots[ $alias ] );
		if ( false === $root ) {
			return new WP_Error( 'nomadsa_path', 'The configured directory does not exist.' );
		}

		$requested = trailingslashit( wp_normalize_path( $root ) ) . $rel;
		$real = realpath( $requested );
		if ( false === $real ) {
			return new WP_Error( 'nomadsa_path', 'Path not found.' );
		}
		if ( ! self::within( $real, $root ) || self::is_sensitive_path( $requested ) || self::is_sensitive_path( $real ) ) {
			return new WP_Error( 'nomadsa_path', 'This path is protected.' );
		}
		return wp_normalize_path( $real );
	}

	public static function relative( $abs ) {
		$path = wp_normalize_path( realpath( $abs ) ?: $abs );
		$roots = self::path_roots();
		// Preserve WordPress-relative paths where possible, otherwise pick the most specific alias.
		uksort( $roots, function ( $a, $b ) use ( $roots ) {
			if ( '' === $a || '' === $b ) {
				return (int) ( '' === $b ) - (int) ( '' === $a );
			}
			return strlen( $roots[ $b ] ) <=> strlen( $roots[ $a ] );
		} );
		foreach ( $roots as $alias => $dir ) {
			$root = realpath( $dir );
			if ( false !== $root && self::within( $path, $root ) ) {
				$rel = ltrim( substr( $path, strlen( wp_normalize_path( $root ) ) ), '/' );
				return trim( $alias . '/' . $rel, '/' );
			}
		}
		return basename( $path );
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

	public static function fetch( $url, $method = 'GET', $timeout = 15 ) {
		$start = microtime( true );
		$res   = wp_safe_remote_request( $url, array(
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 3,
			'user-agent'  => 'NomadSiteAudit/' . NOMADSA_VERSION . '; ' . home_url(),
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		) );
		$ms = (int) round( ( microtime( true ) - $start ) * 1000 );
		return array( $res, $ms );
	}
}
