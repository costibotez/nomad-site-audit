<?php
defined( 'ABSPATH' ) || exit;

/**
 * Routes requests for the connector endpoints and provides small response helpers.
 */
class NomadSA_HTTP {

	/** A sanitised value from $_SERVER. */
	public static function server( $key, $default = '' ) {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : $default;
	}

	public static function method() {
		return strtoupper( self::server( 'REQUEST_METHOD', 'GET' ) );
	}

	public static function route() {
		$path = untrailingslashit( (string) wp_parse_url( esc_url_raw( self::server( 'REQUEST_URI', '/' ) ), PHP_URL_PATH ) );

		$resource = NomadSA_WellKnown::path( 'oauth-protected-resource', NomadSA_Settings::mcp_url() );
		if ( $path === $resource || $path === self::url_path( '/nsa-oauth/resource' ) ) {
			self::cors();
			self::metadata( NomadSA_OAuth::protected_resource_metadata() );
		}
		if ( $path === NomadSA_WellKnown::authorization_path() ) {
			self::cors();
			self::metadata( NomadSA_OAuth::authorization_server_metadata() );
		}

		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( '' !== $home ) {
			if ( 0 !== strpos( $path, $home . '/' ) ) {
				return;
			}
			$path = substr( $path, strlen( $home ) );
		}

		switch ( $path ) {
			case '/nsa-oauth/authorize':
				NomadSA_OAuth::authorize();
				exit;
			case '/nsa-oauth/token':
				self::cors();
				NomadSA_OAuth::token();
				exit;
			case '/nsa-oauth/register':
				self::cors();
				NomadSA_OAuth::register();
				exit;
			case '/nsa-mcp':
				self::validate_origin();
				self::cors();
				NomadSA_MCP::handle();
				exit;
		}
	}

	private static function validate_origin() {
		$origin = self::server( 'HTTP_ORIGIN' );
		if ( '' === $origin || $origin === NomadSA_WellKnown::origin() ) {
			return;
		}
		$parts = wp_parse_url( $origin );
		if ( ! $parts || isset( $parts['path'] ) || isset( $parts['query'] ) || ! NomadSA_OAuth::redirect_allowed( $origin ) ) {
			self::json( array( 'error' => 'This request origin is not allowed.' ), 403 );
		}
	}

	private static function url_path( $path ) {
		return (string) wp_parse_url( home_url( $path ), PHP_URL_PATH );
	}

	private static function metadata( array $document ) {
		if ( ! in_array( self::method(), array( 'GET', 'HEAD' ), true ) ) {
			self::json( array( 'error' => 'Use GET.' ), 405, array( 'Allow' => 'GET, HEAD, OPTIONS' ) );
		}
		self::json( $document );
	}

	public static function cors() {
		if ( ! headers_sent() ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, Mcp-Protocol-Version, Mcp-Session-Id' );
			header( 'Access-Control-Expose-Headers: WWW-Authenticate, Mcp-Session-Id' );
		}
		if ( 'OPTIONS' === self::method() ) {
			status_header( 204 );
			exit;
		}
	}

	public static function json( $data, $status = 200, $headers = array() ) {
		if ( ! headers_sent() ) {
			status_header( $status );
			nocache_headers();
			header( 'Content-Type: application/json; charset=utf-8' );
			foreach ( $headers as $name => $value ) {
				header( $name . ': ' . $value );
			}
		}
		if ( 'HEAD' !== self::method() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response, never rendered as HTML.
			echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		exit;
	}

	public static function oauth_error( $error, $description, $status = 400 ) {
		self::json( array( 'error' => $error, 'error_description' => $description ), $status );
	}

	/** Reads the Bearer token, including from hosts that move the Authorization header. */
	public static function bearer_token() {
		$candidates = array(
			self::server( 'HTTP_AUTHORIZATION' ),
			self::server( 'REDIRECT_HTTP_AUTHORIZATION' ),
		);
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) {
				if ( 'authorization' === strtolower( $k ) ) {
					$candidates[] = $v;
				}
			}
		}
		foreach ( $candidates as $h ) {
			if ( is_string( $h ) && preg_match( '/^Bearer[ \t]+([A-Za-z0-9_~-]{1,128})$/iD', trim( $h ), $m ) ) {
				return $m[1];
			}
		}
		return '';
	}

	/** Bounded JSON transport decoder. Callers validate their message fields and arguments. */
	public static function json_body() {
		$raw = self::read_body();
		return null === $raw ? null : json_decode( $raw, false, 32 );
	}

	/** OAuth parameters are validated separately for registration and token exchange. */
	public static function body_params() {
		$type = strtolower( trim( explode( ';', self::server( 'CONTENT_TYPE' ), 2 )[0] ) );
		if ( 'application/json' === $type ) {
			$data = self::json_body();
			return $data instanceof stdClass ? get_object_vars( $data ) : array();
		}
		if ( 'application/x-www-form-urlencoded' !== $type ) {
			return array();
		}
		$raw = self::read_body();
		if ( null === $raw || substr_count( $raw, '&' ) >= 50 ) {
			return array();
		}
		parse_str( $raw, $data );
		return $data;
	}

	private static function read_body() {
		$limit = 64 * KB_IN_BYTES;
		$raw   = file_get_contents( 'php://input', false, null, 0, $limit + 1 );
		return false !== $raw && strlen( $raw ) <= $limit ? $raw : null;
	}
}
