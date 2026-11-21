<?php
defined( 'ABSPATH' ) || exit;

// These queries operate on the plugin's own tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class NomadSA_Audit_History {

	const AUDIT_LIMIT = 52;
	const PERFORMANCE_LIMIT = 90;
	const SEO_LIMIT = 100;
	const SECTIONS = array( 'security', 'seo', 'database' );

	public static function run( $source ) {
		$snapshot = array( 'checks' => array(), 'seo_posts' => array(), 'unavailable' => array() );
		foreach ( self::SECTIONS as $section ) {
			$part = self::inspect( $section );
			if ( is_wp_error( $part ) ) {
				$snapshot['unavailable'][] = $section;
				continue;
			}
			$snapshot['checks'] += $part['checks'];
			$snapshot['seo_posts'] += $part['seo_posts'];
		}
		return self::save( $source, $snapshot );
	}

	public static function save( $source, array $payload ) {
		global $wpdb;
		$table = NomadSA_DB::table( 'audit_runs' );
		$json = wp_json_encode( $payload );
		if ( false === $json || false === $wpdb->insert( $table, array(
			'source' => $source,
			'created_at' => NomadSA_DB::now(),
			'payload' => $json,
		) ) ) {
			return new WP_Error( 'nomadsa_save_run', __( 'The results could not be saved.', 'nomad-site-audit' ) );
		}

		$id = (int) $wpdb->insert_id;
		if ( 'performance' === $source ) {
			$boundary = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source = %s ORDER BY id DESC LIMIT 1 OFFSET %d', $table, 'performance', self::PERFORMANCE_LIMIT - 1 ) );
			if ( $boundary ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE source = %s AND id < %d', $table, 'performance', $boundary ) );
			}
		} else {
			$boundary = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source IN (%s,%s) ORDER BY id DESC LIMIT 1 OFFSET %d', $table, 'manual', 'weekly', self::AUDIT_LIMIT - 1 ) );
			if ( $boundary ) {
				$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE source IN (%s,%s) AND id < %d AND id <> %d', $table, 'manual', 'weekly', $boundary, self::baseline() ) );
			}
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE updated_at < %s', NomadSA_DB::table( 'audit_checklist' ), gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) );
		}
		return $id;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', NomadSA_DB::table( 'audit_runs' ), $id ), ARRAY_A );
		if ( $row ) {
			$row['payload'] = json_decode( $row['payload'], true );
		}
		return isset( $row['payload'] ) && is_array( $row['payload'] ) ? $row : null;
	}

	public static function recent() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id, source, created_at FROM %i WHERE source IN (%s,%s) ORDER BY id DESC LIMIT %d', NomadSA_DB::table( 'audit_runs' ), 'manual', 'weekly', self::AUDIT_LIMIT + 1 ), ARRAY_A );
	}

	public static function previous( $id, $source = '' ) {
		global $wpdb;
		$table = NomadSA_DB::table( 'audit_runs' );
		$previous = $source
			? $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source = %s AND id < %d ORDER BY id DESC LIMIT 1', $table, $source, $id ) )
			: $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE source IN (%s,%s) AND id < %d ORDER BY id DESC LIMIT 1', $table, 'manual', 'weekly', $id ) );
		return $previous ? self::get( $previous ) : null;
	}

	public static function baseline() {
		return (int) get_option( 'nomadsa_audit_baseline', 0 );
	}

	public static function audit( $id ) {
		$run = self::get( $id );
		return $run && in_array( $run['source'], array( 'manual', 'weekly' ), true ) ? $run : null;
	}

	public static function findings( array $snapshot ) {
		$findings = array_filter( $snapshot['checks'], function ( $check ) {
			return in_array( $check['status'], array( 'fail', 'warning' ), true );
		} );
		uasort( $findings, function ( $a, $b ) {
			return strcmp( $a['status'], $b['status'] );
		} );
		return $findings;
	}

	public static function compare( array $current, array $before ) {
		$old = self::findings( $before );
		$new = self::findings( $current );
		$groups = array( 'new' => array(), 'worsened' => array(), 'recurring' => array(), 'resolved' => array(), 'unverified' => array() );
		foreach ( $new as $id => $finding ) {
			if ( ! isset( $old[ $id ] ) ) {
				$groups['new'][ $id ] = $finding;
			} elseif ( 'fail' === $finding['status'] && 'warning' === $old[ $id ]['status'] ) {
				$groups['worsened'][ $id ] = $finding;
			} else {
				$groups['recurring'][ $id ] = $finding;
			}
		}
		foreach ( array_diff_key( $old, $new ) as $id => $finding ) {
			$group = self::verified( $id, $finding, $current ) ? 'resolved' : 'unverified';
			$groups[ $group ][ $id ] = $finding;
		}
		return $groups;
	}

	private static function verified( $id, array $finding, array $snapshot ) {
		if ( isset( $snapshot['checks'][ $id ] ) ) {
			return in_array( $snapshot['checks'][ $id ]['status'], array( 'pass', 'info' ), true );
		}
		return 'seo' === $finding['section'] && isset( $snapshot['seo_posts'][ $finding['post_id'] ] );
	}

	public static function checklist() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT finding_id, state, audit_id, updated_at FROM %i', NomadSA_DB::table( 'audit_checklist' ) ), OBJECT_K );
		return $rows ?: array();
	}

	public static function set_state( $id, $state, $audit_id ) {
		global $wpdb;
		return false !== $wpdb->replace( NomadSA_DB::table( 'audit_checklist' ), array(
			'finding_id' => $id,
			'state' => $state,
			'audit_id' => $audit_id,
			'updated_at' => NomadSA_DB::now(),
		) );
	}

	public static function recheck( $id, array $finding ) {
		$snapshot = self::inspect( $finding['section'] );
		if ( is_wp_error( $snapshot ) ) {
			return 'unverified';
		}
		if ( isset( self::findings( $snapshot )[ $id ] ) ) {
			return 'present';
		}
		return self::verified( $id, $finding, $snapshot ) ? 'verified' : 'unverified';
	}

	private static function inspect( $section ) {
		try {
			switch ( $section ) {
				case 'security':
					return self::security();
				case 'seo':
					return self::seo();
				case 'database':
					return self::database();
			}
		} catch ( Throwable $e ) {
			// An unavailable section leaves its previous findings unverified.
		}
		return new WP_Error( 'nomadsa_audit_section' );
	}

	private static function security() {
		$results = NomadSA_Audit_Tools::security_audit( array(), 2 );
		$checks = array();
		foreach ( $results['checks'] as $row ) {
			$key = $row['id'];
			$status = $row['status'];
			if ( 'info' === $status && in_array( $key, array( 'core_updates', 'plugin_updates', 'theme_updates' ), true ) ) {
				$status = 'unknown';
			}
			// Client reports and digests should never include account names or filesystem paths from network errors.
			$detail = in_array( $key, array( 'admin_username', 'admin_count', 'homepage' ), true ) ? '' : $row['detail'];
			$checks[ 'security:' . $key ] = self::check( 'security', $key, $status, $row['title'], $detail );
		}
		return array( 'checks' => $checks, 'seo_posts' => array() );
	}

	private static function seo() {
		$result = NomadSA_Audit_Tools::seo_audit( array( 'limit' => self::SEO_LIMIT ), 2 );
		$site = $result['site'];
		$sitemap = $site['sitemap']['status'];
		$checks = array(
			'seo:indexing' => self::check( 'seo', 'indexing', $site['search_engines_allowed'] ? 'pass' : 'fail', __( 'Search engine visibility is disabled', 'nomad-site-audit' ) ),
			'seo:robots' => self::check( 'seo', 'robots', ! $site['robots_txt']['available'] ? 'unknown' : ( $site['robots_txt']['blocks_whole_site'] ? 'warning' : 'pass' ), __( 'robots.txt contains a rule blocking the whole site', 'nomad-site-audit' ) ),
			'seo:sitemap' => self::check( 'seo', 'sitemap', ! is_int( $sitemap ) || $sitemap >= 500 ? 'unknown' : ( 200 === $sitemap ? 'pass' : 'warning' ), __( 'The sitemap could not be loaded', 'nomad-site-audit' ), is_int( $sitemap ) ? 'HTTP ' . $sitemap : '' ),
			'seo:permalinks' => self::check( 'seo', 'permalinks', 'plain' === $site['permalinks'] ? 'warning' : 'pass', __( 'Plain permalinks are in use', 'nomad-site-audit' ) ),
			'seo:media' => self::check( 'seo', 'media', $site['media_without_alt'] ? 'warning' : 'pass', __( 'Media library images are missing alt text', 'nomad-site-audit' ), (string) $site['media_without_alt'] ),
		);
		$posts = array();
		foreach ( $result['items'] as $item ) {
			$id = (int) $item['id'];
			$posts[ $id ] = true;
			foreach ( $item['issues'] as $issue ) {
				// A changed count in a warning is still the same issue on this post.
				$key = 'seo:' . $id . ':' . sha1( preg_replace( '/[0-9]+/', '#', $issue ) );
				$checks[ $key ] = self::check( 'seo', 'post', 'warning', $issue, $item['title'], $id );
			}
		}
		return array( 'checks' => $checks, 'seo_posts' => $posts );
	}

	private static function database() {
		$result = NomadSA_Audit_Tools::database_health();
		$checks = array(
			'database:autoload' => self::check( 'database', 'autoload', $result['autoload_kb'] > 800 ? 'warning' : 'pass', __( 'Autoloaded database options exceed 800 KB', 'nomad-site-audit' ), $result['autoload_kb'] . ' KB' ),
			'database:revisions' => self::check( 'database', 'revisions', $result['revisions'] > 500 ? 'warning' : 'pass', __( 'The database contains more than 500 revisions', 'nomad-site-audit' ), (string) $result['revisions'] ),
		);
		return array( 'checks' => $checks, 'seo_posts' => array() );
	}

	private static function check( $section, $code, $status, $title, $evidence = '', $post_id = 0 ) {
		return array(
			'section' => $section,
			'code' => $code,
			'status' => $status,
			'title' => sanitize_text_field( $title ),
			'evidence' => wp_html_excerpt( NomadSA_Tools::redact( $evidence ), 400 ),
			'post_id' => $post_id,
		);
	}

	/** The WordPress screen and next action for a finding. */
	public static function guidance( array $finding ) {
		if ( $finding['post_id'] ) {
			return array(
				get_edit_post_link( $finding['post_id'], 'raw' ) ?: admin_url( 'edit.php' ),
				__( 'Open the item in the editor or its SEO settings and address the issue for this page.', 'nomad-site-audit' ),
			);
		}
		$actions = array(
			'core_updates' => array( 'update-core.php', __( 'Review WordPress updates and install a supported release after backing up the site.', 'nomad-site-audit' ) ),
			'plugin_updates' => array( 'update-core.php', __( 'Review plugin updates and apply them after backing up the site.', 'nomad-site-audit' ) ),
			'theme_updates' => array( 'update-core.php', __( 'Review theme updates and apply them after backing up the site.', 'nomad-site-audit' ) ),
			'inactive_plugins' => array( 'plugins.php', __( 'Review inactive plugins and remove those you no longer need.', 'nomad-site-audit' ) ),
			'inactive_themes' => array( 'themes.php', __( 'Review inactive themes and keep a fallback theme.', 'nomad-site-audit' ) ),
			'admin_username' => array( 'users.php', __( 'Review easily guessed usernames and require strong sign-in protection.', 'nomad-site-audit' ) ),
			'admin_count' => array( 'users.php', __( 'Review which accounts need administrator access.', 'nomad-site-audit' ) ),
			'registration' => array( 'options-general.php', __( 'Check public registration and the default user role.', 'nomad-site-audit' ) ),
			'php_version' => array( 'site-health.php?tab=debug', __( 'Check the installed PHP version and ask your host to switch to a supported release.', 'nomad-site-audit' ) ),
			'https' => array( 'site-health.php', __( 'Enable HTTPS with your host and verify both site URLs after updating the configuration.', 'nomad-site-audit' ) ),
			'ssl_expiry' => array( 'site-health.php', __( 'Ask your host to renew the site certificate and confirm automatic renewal is enabled.', 'nomad-site-audit' ) ),
			'debug_display' => array( 'site-health.php?tab=debug', __( 'Ask your developer to disable public error display in the WordPress configuration.', 'nomad-site-audit' ) ),
			'debug_log_public' => array( 'site-health.php?tab=debug', __( 'Ask your host to block public access to the log or place it outside the web directory.', 'nomad-site-audit' ) ),
			'file_editor' => array( 'site-health.php', __( 'Ask your developer to disable the dashboard file editor in the WordPress configuration.', 'nomad-site-audit' ) ),
			'wp_config_perms' => array( 'site-health.php?tab=debug', __( 'Ask your host to restrict access to the WordPress configuration file.', 'nomad-site-audit' ) ),
			'php_in_uploads' => array( 'upload.php', __( 'Ask your host or security provider to review executable files in uploads before removing anything.', 'nomad-site-audit' ) ),
			'directory_listing' => array( 'site-health.php', __( 'Ask your host to disable directory listings for uploads.', 'nomad-site-audit' ) ),
			'user_enumeration' => array( 'users.php', __( 'Review which public author information is needed and configure access with your security plugin or host.', 'nomad-site-audit' ) ),
			'xmlrpc' => array( 'plugins.php', __( 'Check whether a plugin or integration needs XML-RPC before asking your host to restrict it.', 'nomad-site-audit' ) ),
			'security_headers' => array( 'site-health.php', __( 'Ask your host to review missing security headers and test the site after changes.', 'nomad-site-audit' ) ),
			'security_plugin' => array( 'plugins.php', __( 'Review your security plugins or confirm the protection provided by your host.', 'nomad-site-audit' ) ),
			'backup_plugin' => array( 'plugins.php', __( 'Confirm the backup schedule and test a restore with your host or backup provider.', 'nomad-site-audit' ) ),
			'homepage' => array( 'site-health.php', __( 'Open the homepage in a browser and check Site Health or hosting logs for errors.', 'nomad-site-audit' ) ),
			'indexing' => array( 'options-reading.php', __( 'Review the search visibility setting under Reading.', 'nomad-site-audit' ) ),
			'permalinks' => array( 'options-permalink.php', __( 'Review your permalink structure and test redirects before changing public URLs.', 'nomad-site-audit' ) ),
			'media' => array( 'upload.php', __( 'Add descriptive alt text to informative images in the Media Library.', 'nomad-site-audit' ) ),
			'robots' => array( 'options-reading.php', __( 'Review search visibility and check your robots.txt rules with your SEO plugin or host.', 'nomad-site-audit' ) ),
			'sitemap' => array( 'options-permalink.php', __( 'Check the sitemap URL, permalink routing and the sitemap setting in your SEO plugin.', 'nomad-site-audit' ) ),
			'autoload' => array( 'site-health.php', __( 'Review Site Health and ask your host or developer to identify oversized autoloaded options.', 'nomad-site-audit' ) ),
			'revisions' => array( 'site-health.php', __( 'Review retention with your developer and back up the database before maintenance.', 'nomad-site-audit' ) ),
		);
		$action = $actions[ $finding['code'] ] ?? array( 'site-health.php', __( 'Review the finding in Site Health and share the evidence with your developer or host if needed.', 'nomad-site-audit' ) );
		return array( admin_url( $action[0] ), $action[1] );
	}
}
