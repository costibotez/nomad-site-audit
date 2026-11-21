<?php
defined( 'ABSPATH' ) || exit;

/**
 * The connector's discovery paths, scoped to its resource and issuer.
 */
class NomadSA_WellKnown {

	public static function path( $name, $url ) {
		return '/.well-known/' . $name . untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	public static function authorization_path() {
		return self::path( 'oauth-authorization-server', NomadSA_OAuth::issuer() );
	}

	public static function origin() {
		$home = untrailingslashit( home_url( '/' ) );
		$path = (string) wp_parse_url( $home, PHP_URL_PATH );
		return '' === $path ? $home : substr( $home, 0, -strlen( $path ) );
	}

	/** Loads the discovery address like a connector would, when requested by an administrator. */
	public static function self_test() {
		$url = self::origin() . self::authorization_path();
		$res = wp_safe_remote_get( $url, array( 'timeout' => 10, 'redirection' => 0 ) );
		if ( is_wp_error( $res ) ) {
			return array( false, 'Could not test ' . $url . ': ' . $res->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		$ok   = 200 === wp_remote_retrieve_response_code( $res )
			&& ( $data['issuer'] ?? '' ) === NomadSA_OAuth::issuer()
			&& ( $data['token_endpoint'] ?? '' ) === home_url( '/nsa-oauth/token' );

		return $ok
			? array( true, $url . ' responds correctly.' )
			: array( false, 'Ask your host to route ' . $url . ' to this WordPress site, then test again.' );
	}
}
