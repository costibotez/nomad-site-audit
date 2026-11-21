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

		$msg = NomadSA_HTTP::json_body();
		if ( null === $msg ) {
			NomadSA_HTTP::json( self::error( null, -32700, 'Parse error.' ), 400 );
		}

		// Batches are no longer part of the protocol, but answering them costs nothing.
		if ( is_array( $msg ) ) {
			if ( empty( $msg ) || count( $msg ) > 20 ) {
				NomadSA_HTTP::json( self::error( null, -32600, 'Invalid request batch.' ), 400 );
			}
			$out = array();
			foreach ( $msg as $m ) {
				$r = self::dispatch( $m );
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

	private static function dispatch( $msg ) {
		if ( ! $msg instanceof stdClass || ( $msg->jsonrpc ?? '' ) !== '2.0'
			|| ! is_string( $msg->method ?? null ) || strlen( $msg->method ) > 100 ) {
			return self::error( null, -32600, 'Invalid JSON-RPC request.' );
		}
		$id = $msg->id ?? null;
		if ( null !== $id && ! is_int( $id ) && ! is_float( $id )
			&& ( ! is_string( $id ) || strlen( $id ) > 200 ) ) {
			return self::error( null, -32600, 'Invalid request ID.' );
		}
		$method = $msg->method;
		$params = $msg->params ?? new stdClass();
		if ( ! $params instanceof stdClass ) {
			return self::error( $id, -32602, 'Parameters must be an object.' );
		}
		$is_notification = ! property_exists( $msg, 'id' );

		if ( $is_notification ) {
			return null; // notifications/initialized, notifications/cancelled and similar need no answer.
		}

		switch ( $method ) {
			case 'initialize':
				$asked   = $params->protocolVersion ?? '';
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
				$name = $params->name ?? '';
				$args = $params->arguments ?? new stdClass();
				if ( ! is_string( $name ) || ! preg_match( '/^[a-z][a-z0-9_]{0,99}$/D', $name ) || ! $args instanceof stdClass ) {
					return self::error( $id, -32602, 'A tool name and an arguments object are required.' );
				}
				return self::result( $id, NomadSA_Tools::call( $name, get_object_vars( $args ) ) );

			case 'resources/list':
				return self::result( $id, array( 'resources' => array() ) );

			case 'prompts/list':
				return self::result( $id, array( 'prompts' => array() ) );
		}

		return self::error( $id, -32601, 'Method not found.' );
	}

	private static function instructions() {
		return 'This read-only connector audits the WordPress site ' . home_url() . '. Start with site_overview, then security_audit and seo_audit. '
			. 'Explain findings in plain language and point the site owner to the relevant WordPress dashboard or hosting settings for changes.';
	}

	private static function result( $id, $result ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}

	private static function error( $id, $code, $message ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) );
	}
}
