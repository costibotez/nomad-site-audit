<?php
defined( 'ABSPATH' ) || exit;

/**
 * Some hosts (SiteGround, many nginx setups) answer /.well-known/ themselves and never pass it to WordPress.
 * Writing the discovery documents as real files makes the connector work there too.
 */
class NSA_WellKnown {

	const FILES = array( 'oauth-authorization-server', 'oauth-protected-resource' );

	public static function dir() {
		return trailingslashit( ABSPATH ) . '.well-known/';
	}

	private static function documents() {
		return array(
			'oauth-authorization-server' => NSA_OAuth::authorization_server_metadata(),
			'oauth-protected-resource'   => NSA_OAuth::protected_resource_metadata(),
		);
	}

	/** Writes the files when missing or out of date. Cheap to call: compares a stored fingerprint first. */
	public static function maybe_write() {
		$docs        = self::documents();
		$fingerprint = md5( wp_json_encode( $docs ) . NSA_VERSION );
		if ( get_option( 'nsa_wellknown' ) === $fingerprint && file_exists( self::dir() . self::FILES[0] ) ) {
			return;
		}
		if ( self::write( $docs ) ) {
			update_option( 'nsa_wellknown', $fingerprint, true );
		}
	}

	public static function write( $docs = null ) {
		$docs = $docs ?: self::documents();
		$dir  = self::dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$ok = true;
		foreach ( $docs as $name => $data ) {
			$path = $dir . $name;
			if ( is_dir( $path ) ) {
				continue; // Never replace a folder someone else created.
			}
			$ok = ( false !== file_put_contents( $path, wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) && $ok;
		}
		return $ok;
	}

	/** Removes only files this plugin wrote. */
	public static function remove() {
		foreach ( self::FILES as $name ) {
			$path = self::dir() . $name;
			if ( is_file( $path ) ) {
				$data = json_decode( (string) file_get_contents( $path ), true );
				$ours = is_array( $data ) && ( isset( $data['registration_endpoint'] ) && false !== strpos( $data['registration_endpoint'], '/nsa-oauth/' )
					|| isset( $data['resource'] ) && false !== strpos( $data['resource'], '/nsa-mcp' ) );
				if ( $ours ) {
					@unlink( $path );
				}
			}
		}
		delete_option( 'nsa_wellknown' );
	}

	/** Loads the discovery address like Claude would. Returns array( ok, detail ). */
	public static function self_test() {
		$url = home_url( '/.well-known/oauth-authorization-server' );
		$res = wp_remote_get( $url, array( 'timeout' => 10, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		if ( is_wp_error( $res ) ) {
			return array( null, 'Could not test from the server: ' . $res->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 === wp_remote_retrieve_response_code( $res ) && is_array( $data ) && ( $data['issuer'] ?? '' ) === NSA_OAuth::issuer() ) {
			return array( true, $url . ' responds correctly.' );
		}
		return array( false, $url . ' returned HTTP ' . wp_remote_retrieve_response_code( $res ) . '. Clear the host cache (SiteGround: Speed, Caching, Flush) and test again. If it still fails, ask the host to serve the files in the .well-known folder.' );
	}
}
