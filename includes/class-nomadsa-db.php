<?php
defined( 'ABSPATH' ) || exit;

// The plugin keeps its own tables (apps, tokens, activity log, backups), which WordPress has no API for.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Database tables: registered apps, tokens, activity log and file backups.
 */
class NomadSA_DB {

	const SCHEMA_VERSION = '1';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'nomadsa_' . $name;
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

		update_option( 'nomadsa_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'nomadsa_schema_version' ) !== self::SCHEMA_VERSION ) {
			self::install();
		}
		if ( false !== get_option( 'nsa_schema_version' ) ) {
			self::migrate_from_nsa();
		}
	}

	/** Versions before 1.1.0 used the nsa prefix. Copy their data across so connections keep working. */
	private static function migrate_from_nsa() {
		global $wpdb;
		foreach ( array( 'clients', 'tokens', 'log', 'backups' ) as $t ) {
			$old = $wpdb->prefix . 'nsa_' . $t;
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) ) {
				continue;
			}
			// The new tables have exactly the same columns as the old ones.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', self::table( $t ), $old ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- removes the plugin's own old table after copying.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $old ) );
		}
		foreach ( array( 'settings', 'runtime', 'wellknown' ) as $o ) {
			$value = get_option( 'nsa_' . $o );
			if ( false !== $value ) {
				update_option( 'nomadsa_' . $o, $value, 'settings' !== $o );
			}
			delete_option( 'nsa_' . $o );
		}
		delete_option( 'nsa_schema_version' );
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
			'client_id'  => NomadSA_OAuth::$current_client,
			'tool'       => $tool,
			'kind'       => $kind,
			'status'     => $status,
			'summary'    => mb_substr( (string) $summary, 0, 2000 ),
			'args'       => (string) $json,
		) );
		// Keep the log to a sensible size.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table( 'log' ) ) );
		if ( $count > 5000 ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i ORDER BY id ASC LIMIT %d', self::table( 'log' ), $count - 5000 ) );
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
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table( 'backups' ), $id ), ARRAY_A );
		if ( $row ) {
			$row['content'] = gzuncompress( base64_decode( $row['content'] ) );
		}
		return $row;
	}

	public static function uninstall() {
		global $wpdb;
		foreach ( array( 'clients', 'tokens', 'log', 'backups' ) as $t ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables on uninstall.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $t ) ) );
		}
		foreach ( array( 'nomadsa_schema_version', 'nomadsa_settings', 'nomadsa_runtime', 'nomadsa_wellknown' ) as $o ) {
			delete_option( $o );
		}
	}
}
