<?php
defined( 'ABSPATH' ) || exit;

/**
 * Connector settings and legacy hardening preferences.
 */
class NomadSA_Settings {

	public static function defaults() {
		return array(
			'enabled'          => true,
			'allowed_hosts'    => 'claude.ai, claude.com, localhost, 127.0.0.1',
			'wpscan_token'     => '',
			'token_lifetime'   => 3600,
			'refresh_lifetime' => 30 * DAY_IN_SECONDS,
		);
	}

	public static function get( $key = null ) {
		$s = wp_parse_args( (array) get_option( 'nomadsa_settings', array() ), self::defaults() );
		return null === $key ? $s : ( $s[ $key ] ?? null );
	}

	public static function update( array $values ) {
		$s = array_merge( self::get(), $values );
		update_option( 'nomadsa_settings', $s, false );
	}

	public static function runtime_defaults() {
		return array(
			'security_headers'        => array(),
			'disable_xmlrpc'          => false,
			'disable_file_editor'     => false,
			'hide_wp_version'         => false,
			'block_user_enumeration'  => false,
		);
	}

	public static function runtime( $key = null ) {
		$r = wp_parse_args( (array) get_option( 'nomadsa_runtime', array() ), self::runtime_defaults() );
		return null === $key ? $r : ( $r[ $key ] ?? null );
	}

	public static function allowed_hosts() {
		return array_filter( array_map( 'trim', explode( ',', strtolower( (string) self::get( 'allowed_hosts' ) ) ) ) );
	}

	public static function connection_deadline() {
		return (int) get_option( 'nomadsa_connect_until', 0 );
	}

	public static function open_connection_window() {
		update_option( 'nomadsa_connect_until', time() + 15 * MINUTE_IN_SECONDS, false );
	}

	public static function connect_window_open() {
		return self::connection_deadline() > time();
	}

	public static function consume_connection_window() {
		return self::connect_window_open() && delete_option( 'nomadsa_connect_until' );
	}

	public static function mcp_url() {
		return home_url( '/nsa-mcp' );
	}
}
