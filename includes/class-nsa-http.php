<?php
defined( 'ABSPATH' ) || exit;

/**
 * Routes requests for the connector endpoints and provides small response helpers.
 */
class NSA_HTTP {

	/** Path of the current request relative to the site home, without trailing slash. */
	public static function request_path() {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home = rtrim( $home, '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		$path = '/' . trim( $path, '/' );
		return $path;
	}

	public static function route() {
		$path = self::request_path();

		if ( 0 === strpos( $path, '/.well-known/oauth-protected-resource' ) ) {
			self::cors();
			self::json( NSA_OAuth::protected_resource_metadata() );
		}
		if ( 0 === strpos( $path, '/.well-known/oauth-authorization-server' ) || 0 === strpos( $path, '/.well-known/openid-configuration' ) ) {
			self::cors();
			self::json( NSA_OAuth::authorization_server_metadata() );
		}

		switch ( $path ) {
			case '/nsa-oauth/resource':
				self::cors();
				self::json( NSA_OAuth::protected_resource_metadata() );
				exit;
			case '/nsa-oauth/authorize':
				NSA_OAuth::authorize();
				exit;
			case '/nsa-oauth/token':
				self::cors();
				NSA_OAuth::token();
				exit;
			case '/nsa-oauth/register':
				self::cors();
				NSA_OAuth::register();
				exit;
			case '/nsa-mcp':
				self::cors();
				NSA_MCP::handle();
				exit;
		}
	}

	public static function cors() {
		if ( ! headers_sent() ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, Mcp-Protocol-Version, Mcp-Session-Id' );
			header( 'Access-Control-Expose-Headers: WWW-Authenticate, Mcp-Session-Id' );
		}
		if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
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
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	public static function oauth_error( $error, $description, $status = 400 ) {
		self::json( array( 'error' => $error, 'error_description' => $description ), $status );
	}

	/** Reads the Bearer token, including from hosts that move the Authorization header. */
	public static function bearer_token() {
		$candidates = array(
			$_SERVER['HTTP_AUTHORIZATION'] ?? '',
			$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
		);
		if ( function_exists( 'getallheaders' ) ) {
			foreach ( (array) getallheaders() as $k => $v ) {
				if ( 'authorization' === strtolower( $k ) ) {
					$candidates[] = $v;
				}
			}
		}
		foreach ( $candidates as $h ) {
			if ( preg_match( '/^Bearer\s+(\S+)$/i', trim( (string) $h ), $m ) ) {
				return $m[1];
			}
		}
		return '';
	}

	public static function body_params() {
		$type = strtolower( $_SERVER['CONTENT_TYPE'] ?? '' );
		$raw  = file_get_contents( 'php://input' );
		if ( false !== strpos( $type, 'application/json' ) ) {
			$data = json_decode( (string) $raw, true );
			return is_array( $data ) ? $data : array();
		}
		if ( ! empty( $_POST ) ) {
			return wp_unslash( $_POST );
		}
		parse_str( (string) $raw, $data );
		return $data;
	}
}
