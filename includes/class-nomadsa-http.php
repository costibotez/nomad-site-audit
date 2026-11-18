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

	/** Path of the current request relative to the site home, without trailing slash. */
	public static function request_path() {
		$path = (string) wp_parse_url( esc_url_raw( self::server( 'REQUEST_URI', '/' ) ), PHP_URL_PATH );
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
			self::json( NomadSA_OAuth::protected_resource_metadata() );
		}
		if ( 0 === strpos( $path, '/.well-known/oauth-authorization-server' ) || 0 === strpos( $path, '/.well-known/openid-configuration' ) ) {
			self::cors();
			self::json( NomadSA_OAuth::authorization_server_metadata() );
		}

		switch ( $path ) {
			case '/nsa-oauth/resource':
				self::cors();
				self::json( NomadSA_OAuth::protected_resource_metadata() );
				exit;
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
				self::cors();
				NomadSA_MCP::handle();
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
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
			if ( preg_match( '/^Bearer\s+(\S+)$/i', trim( (string) $h ), $m ) ) {
				return $m[1];
			}
		}
		return '';
	}

	public static function body_params() {
		$type = strtolower( self::server( 'CONTENT_TYPE' ) );
		$raw  = file_get_contents( 'php://input' );
		if ( false !== strpos( $type, 'application/json' ) ) {
			$data = json_decode( (string) $raw, true );
			return is_array( $data ) ? $data : array();
		}
		// OAuth token and registration requests come from Claude's servers, not from a WordPress form,
		// so there is no nonce; they are authenticated by the authorisation code, PKCE verifier or refresh token.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST ) ) {
			return map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		parse_str( (string) $raw, $data );
		return $data;
	}
}
