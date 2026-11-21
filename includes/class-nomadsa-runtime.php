<?php
defined( 'ABSPATH' ) || exit;

/**
 * Preserves supported hardening preferences from earlier versions.
 */
class NomadSA_Runtime {

	public static function init() {
		$r = NomadSA_Settings::runtime();

		if ( ! empty( $r['security_headers'] ) ) {
			add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );
			add_action( 'admin_init', array( __CLASS__, 'send_security_headers' ) );
			add_action( 'login_init', array( __CLASS__, 'send_security_headers' ) );
		}

		if ( $r['disable_xmlrpc'] ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'wp_headers', function ( $h ) {
				unset( $h['X-Pingback'] );
				return $h;
			} );
			add_filter( 'pings_open', '__return_false' );
			if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
				status_header( 403 );
				exit( 'XML-RPC is disabled on this site.' );
			}
		}

		if ( $r['disable_file_editor'] && ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- the core constant that switches off the code editor.
		}

		if ( $r['hide_wp_version'] ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( $r['block_user_enumeration'] ) {
			add_filter( 'rest_endpoints', function ( $endpoints ) {
				if ( ! is_user_logged_in() ) {
					unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
				}
				return $endpoints;
			} );
			add_action( 'template_redirect', function () {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether an author enumeration request was made.
				if ( ! is_user_logged_in() && isset( $_GET['author'] ) && is_numeric( $_GET['author'] ) ) {
					wp_safe_redirect( home_url( '/' ), 301 );
					exit;
				}
			}, 1 );
			add_filter( 'oembed_response_data', function ( $data ) {
				unset( $data['author_name'], $data['author_url'] );
				return $data;
			} );
			add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
				return 'users' === $name ? false : $provider;
			}, 10, 2 );
		}

	}

	public static function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}
		foreach ( (array) NomadSA_Settings::runtime( 'security_headers' ) as $name => $value ) {
			if ( ! in_array( $name, array( 'Strict-Transport-Security', 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy', 'Content-Security-Policy' ), true )
				|| ! is_string( $value ) || preg_match( '/[[:cntrl:]]/', $value )
				|| ( 'Strict-Transport-Security' === $name && ! is_ssl() ) ) {
				continue;
			}
			header( $name . ': ' . $value );
		}
	}

}
