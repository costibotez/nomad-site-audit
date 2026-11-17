<?php
defined( 'ABSPATH' ) || exit;

/**
 * Applies the fixes stored by the connector on every request: security headers, hardening and custom CSS.
 */
class NSA_Runtime {

	public static function init() {
		$r = NSA_Settings::runtime();

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
			define( 'DISALLOW_FILE_EDIT', true );
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

		if ( $r['remove_core_info_files'] ) {
			add_action( '_core_updated_successfully', array( __CLASS__, 'remove_core_info_files' ) );
		}

		if ( '' !== trim( (string) $r['custom_css'] ) ) {
			add_action( 'wp_head', array( __CLASS__, 'print_custom_css' ), 999 );
		}
	}

	public static function send_security_headers() {
		if ( headers_sent() ) {
			return;
		}
		foreach ( (array) NSA_Settings::runtime( 'security_headers' ) as $name => $value ) {
			if ( 'Strict-Transport-Security' === $name && ! is_ssl() ) {
				continue;
			}
			header( $name . ': ' . $value );
		}
	}

	public static function print_custom_css() {
		echo "\n<style id=\"nsa-custom-css\">\n" . wp_strip_all_tags( (string) NSA_Settings::runtime( 'custom_css' ) ) . "\n</style>\n";
	}

	public static function remove_core_info_files() {
		$deleted = array();
		foreach ( array( 'readme.html', 'license.txt' ) as $f ) {
			if ( file_exists( ABSPATH . $f ) && @unlink( ABSPATH . $f ) ) {
				$deleted[] = $f;
			}
		}
		return $deleted;
	}
}
