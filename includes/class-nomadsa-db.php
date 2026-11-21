<?php
defined( 'ABSPATH' ) || exit;

// The plugin keeps its own tables (apps, tokens, activity log, backups, audit runs and checklist), which WordPress has no API for.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Database tables: registered apps, tokens, activity log, legacy backups, audit history and checklist.
 */
class NomadSA_DB {

	const SCHEMA_VERSION = '2';

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

		dbDelta( 'CREATE TABLE ' . self::table( 'audit_runs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(20) NOT NULL,
			created_at datetime NOT NULL,
			payload longtext NOT NULL,
			PRIMARY KEY  (id),
			KEY source_run (source,id)
		) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'audit_checklist' ) . " (
			finding_id varchar(191) NOT NULL,
			state varchar(20) NOT NULL,
			audit_id bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (finding_id),
			KEY updated_at (updated_at)
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
			if ( $old !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) ) {
				continue;
			}
			// The new tables have exactly the same columns as the old ones.
			if ( false === $wpdb->query( $wpdb->prepare( 'INSERT INTO %i SELECT * FROM %i', self::table( $t ), $old ) ) ) {
				return;
			}
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

	public static function log( $tool, $kind, $status, $summary ) {
		global $wpdb;
		$wpdb->insert( self::table( 'log' ), array(
			'created_at' => self::now(),
			'user_id'    => NomadSA_OAuth::$current_user_id ?: get_current_user_id(),
			'client_id'  => NomadSA_OAuth::$current_client,
			'tool'       => $tool,
			'kind'       => $kind,
			'status'     => $status,
			'summary'    => wp_html_excerpt( NomadSA_Tools::redact( $summary ), 2000 ),
			'args'       => '[]',
		) );
		// Keep the log to a sensible size.
		$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table( 'log' ) ) );
		if ( $count > 5000 ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i ORDER BY id ASC LIMIT %d', self::table( 'log' ), $count - 5000 ) );
		}
	}

	public static function uninstall() {
		global $wpdb;
		foreach ( array( 'clients', 'tokens', 'log', 'backups', 'audit_runs', 'audit_checklist' ) as $t ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- removing the plugin's own tables on uninstall.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table( $t ) ) );
		}
		foreach ( array( 'nomadsa_schema_version', 'nomadsa_settings', 'nomadsa_runtime', 'nomadsa_wellknown', 'nomadsa_connect_until', 'nomadsa_audit_config', 'nomadsa_audit_baseline', 'nomadsa_audit_delivery', 'nomadsa_job_lock_audit', 'nomadsa_job_lock_performance' ) as $o ) {
			delete_option( $o );
		}
	}
}
