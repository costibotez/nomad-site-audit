<?php
defined( 'ABSPATH' ) || exit;

/**
 * Model Context Protocol endpoint (Streamable HTTP transport, JSON responses).
 */
class NomadSA_MCP {

	const SUPPORTED_VERSIONS = array( '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' );

	public static function handle() {
		$method = NomadSA_HTTP::method();
		if ( 'POST' !== $method ) {
			NomadSA_HTTP::json( array( 'error' => 'Use POST for MCP requests. This server does not offer a stream.' ), 405, array( 'Allow' => 'POST' ) );
		}

		if ( ! NomadSA_OAuth::authenticate_request() ) {
			// A normal path rather than /.well-known/, because some hosts never pass /.well-known/ to WordPress.
			$meta = home_url( '/nsa-oauth/resource' );
			NomadSA_HTTP::json(
				array( 'jsonrpc' => '2.0', 'id' => null, 'error' => array( 'code' => -32001, 'message' => 'Authentication required.' ) ),
				401,
				array( 'WWW-Authenticate' => 'Bearer realm="nomad-site-audit", resource_metadata="' . $meta . '"' )
			);
		}

		$raw = file_get_contents( 'php://input' );
		$msg = json_decode( (string) $raw, true );
		if ( ! is_array( $msg ) ) {
			NomadSA_HTTP::json( self::error( null, -32700, 'Parse error.' ), 400 );
		}

		// Batches are no longer part of the protocol, but answering them costs nothing.
		if ( array_keys( $msg ) === range( 0, count( $msg ) - 1 ) ) {
			$out = array();
			foreach ( $msg as $m ) {
				$r = self::dispatch( is_array( $m ) ? $m : array() );
				if ( null !== $r ) {
					$out[] = $r;
				}
			}
			if ( empty( $out ) ) {
				self::accepted();
			}
			NomadSA_HTTP::json( $out );
		}

		$response = self::dispatch( $msg );
		if ( null === $response ) {
			self::accepted();
		}
		NomadSA_HTTP::json( $response );
	}

	private static function accepted() {
		status_header( 202 );
		exit;
	}

	private static function dispatch( array $msg ) {
		$id     = $msg['id'] ?? null;
		$method = (string) ( $msg['method'] ?? '' );
		$params = isset( $msg['params'] ) && is_array( $msg['params'] ) ? $msg['params'] : array();
		$is_notification = ! array_key_exists( 'id', $msg );

		if ( $is_notification ) {
			return null; // notifications/initialized, notifications/cancelled and similar need no answer.
		}

		switch ( $method ) {
			case 'initialize':
				$asked   = (string) ( $params['protocolVersion'] ?? '' );
				$version = in_array( $asked, self::SUPPORTED_VERSIONS, true ) ? $asked : self::SUPPORTED_VERSIONS[0];
				return self::result( $id, array(
					'protocolVersion' => $version,
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array(
						'name'    => 'nomad-site-audit',
						'title'   => 'Nomad Site Audit: ' . get_bloginfo( 'name' ),
						'version' => NOMADSA_VERSION,
					),
					'instructions'    => self::instructions(),
				) );

			case 'ping':
				return self::result( $id, new stdClass() );

			case 'tools/list':
				return self::result( $id, array( 'tools' => NomadSA_Tools::list_for_client() ) );

			case 'tools/call':
				$name = (string) ( $params['name'] ?? '' );
				$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
				return self::result( $id, NomadSA_Tools::call( $name, $args ) );

			case 'resources/list':
				return self::result( $id, array( 'resources' => array() ) );

			case 'prompts/list':
				return self::result( $id, array( 'prompts' => array() ) );
		}

		return self::error( $id, -32601, 'Method not found: ' . $method );
	}

	private static function instructions() {
		$text = 'This server audits the WordPress site ' . home_url() . '. Start with site_overview, then security_audit and seo_audit. '
			. 'Explain findings to the site owner in plain language.';
		if ( NomadSA_OAuth::can_fix() ) {
			$text .= ' Fix tools change the live site. Always call a fix tool first without confirm to get a preview, show the preview to the user, '
				. 'and only call it again with confirm set to true after the user clearly approves that specific change. '
				. 'Suggest a full site backup before updates.';
		} else {
			$text .= ' This connection can only audit; fixes are not allowed.';
		}
		return $text;
	}

	private static function result( $id, $result ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}

	private static function error( $id, $code, $message ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) );
	}
}
