<?php
defined( 'ABSPATH' ) || exit;

/**
 * Database tables: registered apps, tokens, activity log and file backups.
 */
class NSA_DB {

	const SCHEMA_VERSION = '1';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'nsa_' . $name;
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( 'CREATE TABLE ' . self::table( 'clients' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id varchar(191) NOT NULL,
			client_name varchar(255) NOT NULL DEFAULT '',
			redirect_uris longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY client_id (client_id)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'tokens' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token_hash char(64) NOT NULL,
			kind varchar(20) NOT NULL,
			client_id varchar(191) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			scope varchar(100) NOT NULL DEFAULT '',
			expires_at datetime NOT NULL,
			created_at datetime NOT NULL,
			last_used_at datetime NULL,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY client_id (client_id)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			client_id varchar(191) NOT NULL DEFAULT '',
			tool varchar(100) NOT NULL,
			kind varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			summary text NOT NULL,
			args longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'backups' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			kind varchar(30) NOT NULL,
			target varchar(500) NOT NULL,
			content longtext NOT NULL,
			PRIMARY KEY  (id)
		) $charset;" );

		update_option( 'nsa_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'nsa_schema_version' ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
	}

	public static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function log( $tool, $kind, $status, $summary, $args = array() ) {
		global $wpdb;
		$json = wp_json_encode( $args );
		if ( strlen( $json ) > 20000 ) {
			$json = substr( $json, 0, 20000 ) . '... (truncated)';
		}
		$wpdb->insert( self::table( 'log' ), array(
			'created_at' => self::now(),
			'user_id'    => get_current_user_id(),
			'client_id'  => NSA_OAuth::$current_client,
			'tool'       => $tool,
			'kind'       => $kind,
			'status'     => $status,
			'summary'    => mb_substr( (string) $summary, 0, 2000 ),
			'args'       => (string) $json,
		) );
		// Keep the log to a sensible size.
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table( 'log' ) );
		if ( $count > 5000 ) {
			$wpdb->query( 'DELETE FROM ' . self::table( 'log' ) . ' ORDER BY id ASC LIMIT ' . ( $count - 5000 ) );
		}
	}

	public static function backup( $kind, $target, $content ) {
		global $wpdb;
		$wpdb->insert( self::table( 'backups' ), array(
			'created_at' => self::now(),
			'kind'       => $kind,
			'target'     => $target,
			'content'    => base64_encode( gzcompress( (string) $content, 6 ) ),
		) );
		return (int) $wpdb->insert_id;
	}

	public static function get_backup( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'backups' ) . ' WHERE id = %d', $id ), ARRAY_A );
		if ( $row ) {
			$row['content'] = gzuncompress( base64_decode( $row['content'] ) );
		}
		return $row;
	}

	public static function uninstall() {
		global $wpdb;
		foreach ( array( 'clients', 'tokens', 'log', 'backups' ) as $t ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $t ) );
		}
		foreach ( array( 'nsa_schema_version', 'nsa_settings', 'nsa_runtime' ) as $o ) {
			delete_option( $o );
		}
	}
}
