<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings (how the connection behaves) and runtime fixes (what has been applied to the site).
 */
class NSA_Settings {

	public static function defaults() {
		return array(
			'enabled'          => true,
			'allow_fixes'      => false,
			'allowed_hosts'    => 'claude.ai, claude.com, localhost, 127.0.0.1',
			'wpscan_token'     => '',
			'fixes_until'      => 0,
			'connect_until'    => 0,
			'token_lifetime'   => 3600,
			'refresh_lifetime' => 30 * DAY_IN_SECONDS,
		);
	}

	public static function get( $key = null ) {
		$s = wp_parse_args( (array) get_option( 'nsa_settings', array() ), self::defaults() );
		return null === $key ? $s : ( $s[ $key ] ?? null );
	}

	public static function update( array $values ) {
		$s = array_merge( self::get(), $values );
		update_option( 'nsa_settings', $s, false );
	}

	public static function runtime_defaults() {
		return array(
			'security_headers'        => array(),
			'disable_xmlrpc'          => false,
			'disable_file_editor'     => false,
			'hide_wp_version'         => false,
			'block_user_enumeration'  => false,
			'remove_core_info_files'  => false,
			'custom_css'              => '',
		);
	}

	public static function runtime( $key = null ) {
		$r = wp_parse_args( (array) get_option( 'nsa_runtime', array() ), self::runtime_defaults() );
		return null === $key ? $r : ( $r[ $key ] ?? null );
	}

	public static function update_runtime( array $values ) {
		update_option( 'nsa_runtime', array_merge( self::runtime(), $values ) );
	}

	public static function allowed_hosts() {
		return array_filter( array_map( 'trim', explode( ',', strtolower( (string) self::get( 'allowed_hosts' ) ) ) ) );
	}

	/** Fixes are allowed only while switched on and within their 24 hour window. */
	public static function fixes_active() {
		return (bool) self::get( 'allow_fixes' ) && (int) self::get( 'fixes_until' ) > time();
	}

	/** New connections are accepted only for a short time after an administrator opens the window. */
	public static function connect_window_open() {
		return (int) self::get( 'connect_until' ) > time();
	}

	public static function mcp_url() {
		return home_url( '/nsa-mcp' );
	}
}
