<?php
defined( 'ABSPATH' ) || exit;

// The plugin uses atomic queries for the two job leases in the options table.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class NomadSA_Audit_Schedule {

	const WEEKLY = 'nomadsa_weekly_audit';
	const DAILY = 'nomadsa_daily_performance';

	public static function config() {
		return wp_parse_args( get_option( 'nomadsa_audit_config', array() ), array(
			'weekly' => false,
			'recipient' => '',
			'pages' => array(),
			'crux_key' => '',
			'report_name' => '',
			'accent' => '#17546b',
		) );
	}

	public static function init() {
		add_action( self::WEEKLY, array( __CLASS__, 'weekly' ) );
		add_action( self::DAILY, array( __CLASS__, 'daily' ) );
	}

	public static function sync() {
		$config = self::config();
		$jobs = array(
			self::WEEKLY => array( 'weekly', ! empty( $config['weekly'] ) ),
			self::DAILY => array( 'daily', ! empty( $config['pages'] ) ),
		);
		foreach ( $jobs as $hook => list( $interval, $enabled ) ) {
			if ( $enabled && ! wp_next_scheduled( $hook ) ) {
				$result = wp_schedule_event( time() + MINUTE_IN_SECONDS, $interval, $hook, array(), true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} elseif ( ! $enabled && wp_next_scheduled( $hook ) ) {
				$result = wp_clear_scheduled_hook( $hook, array(), true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		return true;
	}

	public static function deactivate( $network_wide = false ) {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( $site_id );
				self::deactivate();
				restore_current_blog();
			}
			return;
		}
		wp_clear_scheduled_hook( self::WEEKLY );
		wp_clear_scheduled_hook( self::DAILY );
	}

	public static function run_audit( $source ) {
		return self::with_lease( 'audit', function () use ( $source ) {
			return NomadSA_Audit_History::run( $source );
		} );
	}

	public static function run_performance() {
		$config = self::config();
		return self::with_lease( 'performance', function () use ( $config ) {
			return NomadSA_Performance::sample( $config['pages'], $config['crux_key'] );
		} );
	}

	public static function recheck( $id, $audit_id, array $finding ) {
		return self::with_lease( 'audit', function () use ( $id, $audit_id, $finding ) {
			$status = NomadSA_Audit_History::recheck( $id, $finding );
			if ( 'unverified' !== $status ) {
				$saved = NomadSA_Audit_History::set_state( $id, 'verified' === $status ? 'verified' : 'in_progress', $audit_id );
				if ( ! $saved ) {
					return new WP_Error( 'nomadsa_save_state', __( 'The checklist status could not be saved.', 'nomad-site-audit' ) );
				}
			}
			return $status;
		} );
	}

	/** One job per type, with an expiring lease so an interrupted PHP process can be retried. */
	private static function with_lease( $job, callable $work ) {
		global $wpdb;
		$key = 'nomadsa_job_lock_' . $job;
		$lease = gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS ) . ':' . wp_generate_uuid4();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value < %s', $wpdb->options, $key, NomadSA_DB::now() ) );
		// The unique option name makes acquisition atomic. A duplicate means another request holds the lease.
		$previous = $wpdb->suppress_errors();
		try {
			$result = $wpdb->insert( $wpdb->options, array( 'option_name' => $key, 'option_value' => $lease, 'autoload' => 'no' ) );
		} finally {
			$wpdb->suppress_errors( $previous );
		}
		if ( 1 !== $result ) {
			return new WP_Error( 'nomadsa_job_busy', __( 'This job is already running or could not start. Try again in a few minutes.', 'nomad-site-audit' ) );
		}
		try {
			return $work();
		} finally {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name = %s AND option_value = %s', $wpdb->options, $key, $lease ) );
		}
	}

	public static function weekly() {
		$config = self::config();
		if ( ! $config['weekly'] || ! is_email( $config['recipient'] ) ) {
			return;
		}
		$run_id = self::run_audit( 'weekly' );
		$run = is_wp_error( $run_id ) ? null : NomadSA_Audit_History::audit( $run_id );
		if ( ! $run ) {
			self::delivery( 'audit_failed' );
			return;
		}
		$previous = NomadSA_Audit_History::previous( $run_id, 'weekly' ) ?: NomadSA_Audit_History::previous( $run_id );
		$findings = NomadSA_Audit_History::findings( $run['payload'] );
		$alerts = array_filter( $findings, function ( $finding ) {
			return 'fail' === $finding['status'];
		} );
		if ( $previous ) {
			$changes = NomadSA_Audit_History::compare( $run['payload'], $previous['payload'] );
			$alerts += $changes['worsened'] + $changes['new'];
		}
		$lines = array();
		foreach ( array_slice( $alerts, 0, 12 ) as $finding ) {
			$priority = 'fail' === $finding['status'] ? __( 'High priority', 'nomad-site-audit' ) : __( 'Needs attention', 'nomad-site-audit' );
			$lines[] = '- ' . $priority . ' · ' . $finding['title'];
		}
		foreach ( NomadSA_Performance::weekly_regressions( $config['pages'] ) as $url => $flags ) {
			$lines[] = '- ' . $url . ' · ' . implode( ', ', $flags );
		}
		if ( ! $lines ) {
			self::delivery( 'quiet' );
			return;
		}

		$site = sanitize_text_field( wp_specialchars_decode( get_bloginfo( 'name' ) ) );
		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Weekly site audit', 'nomad-site-audit' ), $site );
		$message = __( 'The weekly site audit found items that need attention.', 'nomad-site-audit' ) . "\n\n" . implode( "\n", $lines ) . "\n\n"
			. __( 'View the complete audit and next steps in WordPress:', 'nomad-site-audit' ) . "\n"
			. add_query_arg( array( 'page' => 'nomadsa-audit', 'run' => $run_id ), admin_url( 'tools.php' ) );
		// Read consent again in case the administrator disabled delivery while the checks ran.
		$saved = self::config();
		if ( $saved['weekly'] && $config['recipient'] === $saved['recipient'] ) {
			self::delivery( wp_mail( $saved['recipient'], $subject, $message ) ? 'accepted' : 'mail_failed' );
		}
	}

	private static function delivery( $status ) {
		update_option( 'nomadsa_audit_delivery', array( 'status' => $status, 'date' => NomadSA_DB::now() ), false );
	}

	public static function daily() {
		if ( self::config()['pages'] ) {
			self::run_performance();
		}
	}
}
