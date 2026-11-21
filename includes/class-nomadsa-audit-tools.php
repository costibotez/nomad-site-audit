<?php
defined( 'ABSPATH' ) || exit;

// Queries here use the plugin's own tables or read only counts, which WordPress has no API or cache for.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Read only tools: security, search, health and code inspection.
 */
class NomadSA_Audit_Tools {

	public static function definitions() {
		$c = __CLASS__;
		$tools = array();

		$tools[ 'site_overview' ] = array(
			'title'       => 'Site overview',
			'description' => 'Summary of the site: WordPress and PHP versions, server, theme, plugins, pending updates, users, key settings and which security, search, cache and backup plugins are in use. Start here.',
			'callback'    => array( $c, 'site_overview' ),
		);

		$tools[ 'list_plugins' ] = array(
			'title'       => 'List plugins',
			'description' => 'All installed plugins with version, active state, available update, WordPress.org status (last updated, closed or abandoned) and known vulnerabilities when a WPScan token is set in the plugin settings.',
			'properties'  => array(
				'check_directory' => array( 'type' => 'boolean', 'description' => 'Look up each plugin on WordPress.org. Default true.' ),
			),
			'callback'    => array( $c, 'list_plugins' ),
		);

		$tools[ 'list_themes' ] = array(
			'title'       => 'List themes',
			'description' => 'Installed themes with version, active state, parent theme and available update.',
			'callback'    => array( $c, 'list_themes' ),
		);

		$tools[ 'security_audit' ] = array(
			'title'       => 'Security audit',
			'description' => 'Runs about 30 security checks (updates, debug settings, exposed files, user enumeration, XML-RPC, security headers, SSL certificate expiry, file permissions, PHP files in uploads, admin accounts and more). Each check returns the result and practical next steps.',
			'callback'    => array( $c, 'security_audit' ),
		);

		$tools[ 'scan_for_malware' ] = array(
			'title'       => 'Scan for malware',
			'description' => 'Looks for common signs of hacked code: PHP files in uploads, obfuscated code (eval with base64, gzinflate and similar), web shells, very long encoded strings and recently changed PHP files. Findings are leads to review, not proof.',
			'properties'  => array(
				'path'          => array( 'type' => 'string', 'description' => 'Path relative to WordPress, or a configured root such as @content or @uploads. Default @content.' ),
				'modified_days' => array( 'type' => 'integer', 'description' => 'Also list PHP files changed in the last N days. Default 14.' ),
			),
			'callback'    => array( $c, 'scan_for_malware' ),
		);

		$tools[ 'verify_core_files' ] = array(
			'title'       => 'Verify WordPress core files',
			'description' => 'Compares every WordPress core file against the official checksums from WordPress.org to find modified, missing or unexpected files.',
			'callback'    => array( $c, 'verify_core_files' ),
		);

		$tools[ 'verify_plugin_files' ] = array(
			'title'       => 'Verify plugin files',
			'description' => 'Compares plugin files against official WordPress.org checksums to find modified or added files. Only works for plugins hosted on WordPress.org.',
			'properties'  => array(
				'plugin' => array( 'type' => 'string', 'description' => 'Plugin folder slug, for example contact-form-7. Leave out to check all active plugins.' ),
			),
			'callback'    => array( $c, 'verify_plugin_files' ),
		);

		$tools[ 'seo_audit' ] = array(
			'title'       => 'Search optimisation audit',
			'description' => 'Site level search checks (indexing, sitemap, robots.txt, permalinks, images without alt text) and a per page review of published content: search title and description, noindex, word count, headings, alt text and duplicates. Works with Yoast SEO and Rank Math.',
			'properties'  => array(
				'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Default page and post.' ),
				'limit'      => array( 'type' => 'integer', 'description' => 'Maximum items to review. Default 100, maximum 300.' ),
				'offset'     => array( 'type' => 'integer', 'description' => 'For paging. Default 0.' ),
			),
			'callback'    => array( $c, 'seo_audit' ),
		);

		$tools[ 'check_page' ] = array(
			'title'       => 'Check a page',
			'description' => 'Loads a page of this site like a visitor and reports response time, size, title, meta description, canonical, robots, headings, images, structured data, render blocking scripts, loading screens (preloaders), mixed content and response headers.',
			'properties'  => array(
				'url' => array( 'type' => 'string', 'description' => 'Full URL on this site or a path such as /about/. Default the homepage.' ),
			),
			'callback'    => array( $c, 'check_page' ),
		);

		$tools[ 'find_broken_links' ] = array(
			'title'       => 'Find broken links',
			'description' => 'Collects links and images from published content and checks which ones fail. Internal links by default.',
			'properties'  => array(
				'include_external' => array( 'type' => 'boolean', 'description' => 'Also check links to other websites. Default false.' ),
				'max_urls'         => array( 'type' => 'integer', 'description' => 'Maximum unique URLs to check. Default 80, maximum 250.' ),
			),
			'callback'    => array( $c, 'find_broken_links' ),
		);

		$tools[ 'database_health' ] = array(
			'title'       => 'Database health',
			'description' => 'Database size, autoloaded options (a common cause of slow sites), revisions, drafts, trash, spam and expired temporary data.',
			'callback'    => array( $c, 'database_health' ),
		);

		$tools[ 'list_cron_events' ] = array(
			'title'       => 'Scheduled tasks',
			'description' => 'WordPress scheduled tasks with next run time and whether any are overdue.',
			'callback'    => array( $c, 'list_cron_events' ),
		);

		$tools[ 'read_error_log' ] = array(
			'title'       => 'Read error log',
			'description' => 'Last lines of the WordPress debug log or PHP error log, with secrets removed.',
			'properties'  => array(
				'lines'  => array( 'type' => 'integer', 'description' => 'Default 100, maximum 1000.' ),
				'filter' => array( 'type' => 'string', 'description' => 'Only lines containing this text.' ),
			),
			'callback'    => array( $c, 'read_error_log' ),
		);

		$tools[ 'list_users' ] = array(
			'title'       => 'List users',
			'description' => 'Users with their roles, registration date and post count. Email addresses are partly hidden. Flags risky usernames.',
			'properties'  => array(
				'role' => array( 'type' => 'string', 'description' => 'Filter by role, for example administrator. Default all roles.' ),
			),
			'callback'    => array( $c, 'list_users' ),
		);

		$tools[ 'list_content' ] = array(
			'title'       => 'List content',
			'description' => 'Find posts, pages, media or other content and their IDs, for audit follow-up.',
			'properties'  => array(
				'post_type' => array( 'type' => 'string', 'description' => 'Default any. Use attachment for media.' ),
				'search'    => array( 'type' => 'string' ),
				'status'    => array( 'type' => 'string', 'description' => 'Default publish.' ),
				'limit'     => array( 'type' => 'integer', 'description' => 'Default 50, maximum 200.' ),
			),
			'callback'    => array( $c, 'list_content' ),
		);

		$tools[ 'list_files' ] = array(
			'title'       => 'List files',
			'description' => 'Lists a folder inside the WordPress install with sizes, dates and permissions. Protected files such as wp-config.php are hidden.',
			'properties'  => array(
				'path'  => array( 'type' => 'string', 'description' => 'Relative to WordPress or a configured root such as @theme, @plugins, or @uploads. Default the WordPress root.' ),
				'depth' => array( 'type' => 'integer', 'description' => 'Folder depth, 1 to 4. Default 1.' ),
			),
			'callback'    => array( $c, 'list_files' ),
		);

		$tools[ 'read_file' ] = array(
			'title'       => 'Read file',
			'description' => 'Reads a text file inside the WordPress install for code review. wp-config.php and other secret files are blocked and secrets are removed from the output.',
			'properties'  => array(
				'path'       => array( 'type' => 'string' ),
				'start_line' => array( 'type' => 'integer', 'description' => 'Default 1.' ),
				'max_lines'  => array( 'type' => 'integer', 'description' => 'Default 400, maximum 2000.' ),
			),
			'required'    => array( 'path' ),
			'callback'    => array( $c, 'read_file' ),
		);

		$tools[ 'search_code' ] = array(
			'title'       => 'Search code',
			'description' => 'Searches files for text or a regular expression, for code review or to locate where something is set.',
			'properties'  => array(
				'pattern'     => array( 'type' => 'string', 'description' => 'Plain text, or a regular expression when regex is true.' ),
				'regex'       => array( 'type' => 'boolean' ),
				'path'        => array( 'type' => 'string', 'description' => 'Default @content.' ),
				'extensions'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Default php, js, css, html, twig, json, txt.' ),
				'max_results' => array( 'type' => 'integer', 'description' => 'Default 100, maximum 500.' ),
			),
			'required'    => array( 'pattern' ),
			'callback'    => array( $c, 'search_code' ),
		);

		$tools[ 'get_activity_log' ] = array(
			'title'       => 'Connector activity log',
			'description' => 'Recent audit activity and historical connector events.',
			'properties'  => array(
				'limit' => array( 'type' => 'integer', 'description' => 'Default 50, maximum 500.' ),
				'kind'  => array( 'type' => 'string', 'enum' => array( 'audit', 'fix' ) ),
			),
			'callback'    => array( $c, 'get_activity_log' ),
		);
		return $tools;
	}

	/* ================================================================ Helpers */

	private static function detect_stack() {
		$active = (array) get_option( 'active_plugins', array() );
		$map    = array(
			'seo'      => array( 'wordpress-seo' => 'Yoast SEO', 'seo-by-rank-math' => 'Rank Math', 'all-in-one-seo-pack' => 'All in One SEO', 'autodescription' => 'The SEO Framework', 'wp-seopress' => 'SEOPress' ),
			'security' => array( 'wordfence' => 'Wordfence', 'better-wp-security' => 'Solid Security', 'sucuri-scanner' => 'Sucuri', 'all-in-one-wp-security-and-firewall' => 'All In One WP Security', 'sg-security' => 'SiteGround Security', 'wps-hide-login' => 'WPS Hide Login', 'limit-login-attempts-reloaded' => 'Limit Login Attempts Reloaded' ),
			'cache'    => array( 'sg-cachepress' => 'SiteGround Optimizer', 'wp-rocket' => 'WP Rocket', 'litespeed-cache' => 'LiteSpeed Cache', 'w3-total-cache' => 'W3 Total Cache', 'wp-super-cache' => 'WP Super Cache', 'autoptimize' => 'Autoptimize', 'nitropack' => 'NitroPack', 'wp-fastest-cache' => 'WP Fastest Cache' ),
			'backup'   => array( 'updraftplus' => 'UpdraftPlus', 'backwpup' => 'BackWPup', 'duplicator' => 'Duplicator', 'all-in-one-wp-migration' => 'All-in-One WP Migration', 'jetpack' => 'Jetpack', 'blogvault-real-time-backup' => 'BlogVault' ),
			'builder'  => array( 'elementor' => 'Elementor', 'beaver-builder-lite-version' => 'Beaver Builder', 'js_composer' => 'WPBakery', 'divi-builder' => 'Divi', 'oxygen' => 'Oxygen', 'bricks' => 'Bricks' ),
			'forms'    => array( 'contact-form-7' => 'Contact Form 7', 'wpforms-lite' => 'WPForms Lite', 'wpforms' => 'WPForms', 'gravityforms' => 'Gravity Forms', 'fluentform' => 'Fluent Forms', 'formidable' => 'Formidable' ),
		);
		$found = array();
		foreach ( $map as $group => $plugins ) {
			$found[ $group ] = array();
			foreach ( $active as $file ) {
				$slug = dirname( $file );
				if ( isset( $plugins[ $slug ] ) ) {
					$found[ $group ][] = $plugins[ $slug ];
				}
			}
		}
		$theme = wp_get_theme();
		if ( 'Divi' === $theme->get_template() || 'bricks' === $theme->get_template() ) {
			$found['builder'][] = ucfirst( $theme->get_template() );
		}
		return $found;
	}

	private static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		return '';
	}

	public static function seo_meta( $post_id ) {
		$p = self::seo_plugin();
		if ( 'yoast' === $p ) {
			return array(
				'title'       => (string) get_post_meta( $post_id, '_yoast_wpseo_title', true ),
				'description' => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
				'noindex'     => '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ),
			);
		}
		if ( 'rankmath' === $p ) {
			$robots = (array) get_post_meta( $post_id, 'rank_math_robots', true );
			return array(
				'title'       => (string) get_post_meta( $post_id, 'rank_math_title', true ),
				'description' => (string) get_post_meta( $post_id, 'rank_math_description', true ),
				'noindex'     => in_array( 'noindex', $robots, true ),
			);
		}
		return array( 'title' => '', 'description' => '', 'noindex' => false );
	}

	private static function check( $id, $status, $title, $detail ) {
		return array( 'id' => $id, 'status' => $status, 'title' => $title, 'detail' => $detail );
	}

	private static function debug_log_path() {
		if ( defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG
			&& ! in_array( strtolower( WP_DEBUG_LOG ), array( 'true', '1' ), true ) ) {
			return WP_DEBUG_LOG;
		}
		return trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
	}

	private static function public_log_url( $path ) {
		$path = realpath( $path );
		if ( false === $path ) {
			return '';
		}
		$uploads = wp_get_upload_dir();
		foreach ( array( array( $uploads['basedir'], $uploads['baseurl'] ), array( WP_CONTENT_DIR, content_url() ), array( ABSPATH, site_url() ) ) as list( $root, $url ) ) {
			$root = realpath( $root );
			if ( false !== $root && NomadSA_Tools::within( $path, $root ) ) {
				$relative = ltrim( substr( wp_normalize_path( $path ), strlen( wp_normalize_path( $root ) ) ), '/' );
				return trailingslashit( $url ) . implode( '/', array_map( 'rawurlencode', explode( '/', $relative ) ) );
			}
		}
		return '';
	}

	private static function header_value( $res, $name ) {
		$v = wp_remote_retrieve_header( $res, $name );
		return is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}

	/** Content of a published post including page builder text, for analysis. */
	private static function rendered_content( $post ) {
		$content = (string) $post->post_content;
		$el      = get_post_meta( $post->ID, '_elementor_data', true );
		if ( $el && is_string( $el ) ) {
			$data = json_decode( $el, true );
			if ( is_array( $data ) ) {
				$parts = array();
				array_walk_recursive( $data, function ( $v, $k ) use ( &$parts ) {
					if ( is_string( $v ) && in_array( $k, array( 'editor', 'title', 'text', 'description_text', 'title_text', 'html', 'testimonial_content' ), true ) ) {
						$parts[] = $v;
					}
				} );
				$content .= "\n" . implode( "\n", $parts );
			}
		}
		return $content;
	}

	/* ================================================================ Overview */

	public static function site_overview() {
		global $wpdb, $wp_version;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$plugins     = get_plugins();
		$active      = (array) get_option( 'active_plugins', array() );
		$plugin_upd  = get_site_transient( 'update_plugins' );
		$theme_upd   = get_site_transient( 'update_themes' );
		$core        = get_core_updates();
		$core_latest = ( ! empty( $core[0] ) && 'upgrade' === $core[0]->response ) ? $core[0]->current : null;
		$theme       = wp_get_theme();
		$users       = count_users();
		$counts      = array();
		foreach ( array( 'page', 'post', 'attachment' ) as $pt ) {
			$c             = wp_count_posts( $pt );
			$counts[ $pt ] = 'attachment' === $pt ? (int) ( $c->inherit ?? 0 ) : (int) ( $c->publish ?? 0 );
		}

		return array(
			'summary'   => get_bloginfo( 'name' ) . ' on WordPress ' . $wp_version . ', PHP ' . PHP_VERSION,
			'site'      => array(
				'name'          => get_bloginfo( 'name' ),
				'tagline'       => get_bloginfo( 'description' ),
				'home_url'      => home_url(),
				'site_url'      => site_url(),
				'https'         => 0 === strpos( home_url(), 'https://' ),
				'multisite'     => is_multisite(),
				'language'      => get_locale(),
				'timezone'      => wp_timezone_string(),
				'permalinks'    => get_option( 'permalink_structure' ) ?: 'plain (not search friendly)',
				'search_engines' => get_option( 'blog_public' ) ? 'allowed' : 'discouraged (site hidden from search engines)',
				'front_page'    => 'page' === get_option( 'show_on_front' ) ? get_the_title( (int) get_option( 'page_on_front' ) ) : 'latest posts',
				'content'       => $counts,
			),
			'software'  => array(
				'wordpress'        => $wp_version,
				'wordpress_latest' => $core_latest ?: $wp_version,
				'php'              => PHP_VERSION,
				'database'         => $wpdb->db_server_info(),
				'server'           => NomadSA_HTTP::server( 'SERVER_SOFTWARE', 'unknown' ),
				'memory_limit'     => WP_MEMORY_LIMIT . ' (PHP ' . ini_get( 'memory_limit' ) . ')',
				'max_upload'       => size_format( wp_max_upload_size() ),
				'object_cache'     => wp_using_ext_object_cache() ? 'persistent' : 'none',
				'debug'            => array(
					'WP_DEBUG'         => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) ? (bool) WP_DEBUG_DISPLAY : true,
					'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false,
				),
			),
			'theme'     => array(
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'child_theme'      => $theme->parent() ? true : false,
				'parent'           => $theme->parent() ? $theme->parent()->get( 'Name' ) . ' ' . $theme->parent()->get( 'Version' ) : null,
				'installed_themes' => count( wp_get_themes() ),
			),
			'plugins'   => array(
				'installed'         => count( $plugins ),
				'active'            => count( $active ),
				'inactive'          => count( $plugins ) - count( array_intersect( array_keys( $plugins ), $active ) ),
				'updates_available' => isset( $plugin_upd->response ) ? count( $plugin_upd->response ) : 0,
			),
			'theme_updates_available' => isset( $theme_upd->response ) ? count( $theme_upd->response ) : 0,
			'users'     => $users['avail_roles'],
			'detected'  => self::detect_stack(),
			'connector' => array(
				'version'       => NOMADSA_VERSION,
				'read_only'     => true,
			),
		);
	}

	public static function list_plugins( $args ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$check   = ! isset( $args['check_directory'] ) || $args['check_directory'];
		$plugins = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$updates = get_site_transient( 'update_plugins' );
		$token   = (string) NomadSA_Settings::get( 'wpscan_token' );
		$out     = array();

		foreach ( $plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug ) {
				$slug = basename( $file, '.php' );
			}
			$row = array(
				'plugin'      => $file,
				'name'        => $data['Name'],
				'version'     => $data['Version'],
				'active'      => in_array( $file, $active, true ),
				'auto_update' => in_array( $file, (array) get_site_option( 'auto_update_plugins', array() ), true ),
			);
			if ( isset( $updates->response[ $file ] ) ) {
				$row['update_to'] = $updates->response[ $file ]->new_version;
			}
			if ( $check ) {
				$row['directory'] = self::directory_info( $slug );
			}
			if ( $token ) {
				$row['vulnerabilities'] = self::wpscan( 'plugins', $slug, $data['Version'], $token );
			}
			$out[] = $row;
		}
		$outdated = count( array_filter( $out, function ( $r ) {
			return isset( $r['update_to'] );
		} ) );
		return array(
			'summary'  => count( $out ) . ' plugins, ' . $outdated . ' with updates available',
			'plugins'  => $out,
			'note'     => $token ? 'Vulnerability data from WPScan.' : 'No WPScan token set, so known vulnerabilities were not checked. Updates available often include security fixes.',
		);
	}

	private static function directory_info( $slug ) {
		$key    = 'nomadsa_dir_' . md5( $slug );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$res = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . rawurlencode( $slug ) . '&request[fields][sections]=0&request[fields][versions]=0', array( 'timeout' => 8 ) );
		if ( is_wp_error( $res ) ) {
			return array( 'status' => 'lookup_failed' );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $data ) || isset( $data['error'] ) ) {
			$closed = is_array( $data ) && isset( $data['error'] ) && false !== stripos( (string) $data['error'], 'closed' );
			$info   = array( 'status' => $closed ? 'closed (removed from WordPress.org, often for security reasons)' : 'not on WordPress.org (premium or custom)' );
		} else {
			$updated = isset( $data['last_updated'] ) ? strtotime( $data['last_updated'] ) : 0;
			$info    = array(
				'status'       => 'listed',
				'last_updated' => $updated ? gmdate( 'Y-m-d', $updated ) : null,
				'abandoned'    => $updated && $updated < time() - 2 * YEAR_IN_SECONDS,
				'tested_up_to' => $data['tested'] ?? null,
				'active_installs' => $data['active_installs'] ?? null,
			);
		}
		set_transient( $key, $info, 12 * HOUR_IN_SECONDS );
		return $info;
	}

	private static function wpscan( $type, $slug, $version, $token ) {
		$key    = 'nomadsa_wps_' . md5( $type . $slug . $version );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}
		$res = wp_remote_get( 'https://wpscan.com/api/v3/' . $type . '/' . rawurlencode( $slug ), array(
			'timeout' => 10,
			'headers' => array( 'Authorization' => 'Token token=' . $token ),
		) );
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			return array( 'status' => 'lookup_failed or not in database' );
		}
		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$vulns = array();
		foreach ( (array) ( $data[ $slug ]['vulnerabilities'] ?? array() ) as $v ) {
			$fixed = $v['fixed_in'] ?? null;
			if ( null === $fixed || version_compare( $version, $fixed, '<' ) ) {
				$vulns[] = array( 'title' => $v['title'] ?? '', 'fixed_in' => $fixed, 'type' => $v['vuln_type'] ?? '' );
			}
		}
		set_transient( $key, $vulns, 12 * HOUR_IN_SECONDS );
		return $vulns;
	}

	public static function list_themes() {
		$updates = get_site_transient( 'update_themes' );
		$active  = get_stylesheet();
		$out     = array();
		foreach ( wp_get_themes() as $slug => $t ) {
			$out[] = array(
				'theme'     => $slug,
				'name'      => $t->get( 'Name' ),
				'version'   => $t->get( 'Version' ),
				'active'    => $slug === $active || $slug === get_template(),
				'parent'    => $t->parent() ? $t->get_template() : null,
				'update_to' => $updates->response[ $slug ]['new_version'] ?? null,
			);
		}
		return array( 'summary' => count( $out ) . ' themes installed', 'themes' => $out );
	}

	/* ================================================================ Security */

	public static function security_audit( $args = array(), $timeout = 15 ) {
		global $wpdb, $wp_version;
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		$checks = array();
		$home   = home_url( '/' );

		// Updates.
		$core = get_core_updates();
		if ( ! empty( $core[0] ) && 'upgrade' === $core[0]->response ) {
			$checks[] = self::check( 'core_updates', 'fail', 'WordPress is out of date', 'Running ' . $wp_version . ', latest is ' . $core[0]->current . '.' );
		} elseif ( ! empty( $core ) ) {
			$checks[] = self::check( 'core_updates', 'pass', 'WordPress is up to date', 'Running ' . $wp_version . '.' );
		} else {
			$checks[] = self::check( 'core_updates', 'info', 'WordPress update status unavailable', 'Open Dashboard → Updates to refresh WordPress update information.' );
		}
		$pu = get_site_transient( 'update_plugins' );
		$n  = isset( $pu->response ) ? count( $pu->response ) : 0;
		$checks[] = $n
			? self::check( 'plugin_updates', 'warning', $n . ' plugin updates available', implode( ', ', array_map( function ( $f ) { return dirname( $f ); }, array_keys( $pu->response ) ) ) )
			: ( $pu
				? self::check( 'plugin_updates', 'pass', 'All plugins are up to date', '' )
				: self::check( 'plugin_updates', 'info', 'Plugin update status unavailable', 'Open Dashboard → Updates to refresh plugin update information.' ) );
		$tu = get_site_transient( 'update_themes' );
		$n  = isset( $tu->response ) ? count( $tu->response ) : 0;
		$checks[] = $n
			? self::check( 'theme_updates', 'warning', $n . ' theme updates available', implode( ', ', array_keys( $tu->response ) ) )
			: ( $tu
				? self::check( 'theme_updates', 'pass', 'All themes are up to date', '' )
				: self::check( 'theme_updates', 'info', 'Theme update status unavailable', 'Open Dashboard → Updates to refresh theme update information.' ) );

		$plugins  = get_plugins();
		$inactive = array_diff( array_keys( $plugins ), (array) get_option( 'active_plugins', array() ) );
		$checks[] = $inactive
			? self::check( 'inactive_plugins', 'warning', count( $inactive ) . ' inactive plugins installed', 'Inactive plugins can still be attacked. Delete those not needed: ' . implode( ', ', $inactive ) )
			: self::check( 'inactive_plugins', 'pass', 'No inactive plugins', '' );

		$themes   = wp_get_themes();
		$extra    = array_diff( array_keys( $themes ), array( get_stylesheet(), get_template() ) );
		$checks[] = count( $extra ) > 1
			? self::check( 'inactive_themes', 'warning', count( $extra ) . ' unused themes', 'Keep one default theme as a fallback and remove the rest: ' . implode( ', ', $extra ) )
			: self::check( 'inactive_themes', 'pass', 'No unnecessary themes', '' );

		// Server.
		$checks[] = version_compare( PHP_VERSION, '8.1', '<' )
			? self::check( 'php_version', 'fail', 'PHP ' . PHP_VERSION . ' no longer receives security fixes', 'Ask the host to switch to PHP 8.2 or newer.' )
			: ( version_compare( PHP_VERSION, '8.2', '<' ) ? self::check( 'php_version', 'warning', 'PHP ' . PHP_VERSION . ' is close to end of life', 'Plan a move to PHP 8.3.' ) : self::check( 'php_version', 'pass', 'PHP ' . PHP_VERSION . ' is supported', '' ) );

		$https    = 0 === strpos( home_url(), 'https://' ) && 0 === strpos( site_url(), 'https://' );
		$checks[] = $https ? self::check( 'https', 'pass', 'Site uses HTTPS', '' ) : self::check( 'https', 'fail', 'Site is not fully on HTTPS', 'Home and site address should both start with https://.' );

		if ( $https ) {
			$checks[] = self::ssl_check( wp_parse_url( home_url(), PHP_URL_HOST ), min( $timeout, 8 ) );
		}

		// Debug.
		$debug   = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$display = $debug && ( ! defined( 'WP_DEBUG_DISPLAY' ) || WP_DEBUG_DISPLAY ) && ini_get( 'display_errors' );
		if ( $display ) {
			$checks[] = self::check( 'debug_display', 'fail', 'Errors are shown to visitors', 'WP_DEBUG and WP_DEBUG_DISPLAY are on. Errors can reveal file paths. Turn WP_DEBUG_DISPLAY off in wp-config.php (this file is protected, so this needs doing by hand or by the host).' );
		} elseif ( $debug ) {
			$checks[] = self::check( 'debug_display', 'warning', 'Debug mode is on', 'Errors are not shown to visitors, but debug mode should normally be off on a live site.' );
		} else {
			$checks[] = self::check( 'debug_display', 'pass', 'Debug mode is off', '' );
		}

		$url = self::public_log_url( self::debug_log_path() );
		if ( '' !== $url ) {
			list( $res ) = NomadSA_Tools::fetch( $url, 'HEAD', $timeout );
			if ( is_wp_error( $res ) ) {
				$checks[] = self::check( 'debug_log_public', 'unknown', 'Could not test access to the error log', 'Check the log URL from outside the server or ask your host to verify access.' );
			} else {
				$checks[] = 200 === wp_remote_retrieve_response_code( $res )
					? self::check( 'debug_log_public', 'fail', 'Error log can be downloaded by anyone', $url . ' is public and can leak paths and data. Block it or move the log outside the web folder.' )
					: self::check( 'debug_log_public', 'pass', 'Error log URL did not return the log', '' );
			}
		} else {
			$checks[] = self::check( 'debug_log_public', 'pass', 'No WordPress debug log mapped to a public directory', '' );
		}

		// Files.
		$checks[] = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			? self::check( 'file_editor', 'pass', 'Built in code editor is disabled', '' )
			: self::check( 'file_editor', 'warning', 'Built in code editor is enabled', 'Anyone who gets into an admin account can edit plugin and theme code from the dashboard.' );

		$cfg = file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $cfg ) ) {
			$perms    = fileperms( $cfg ) & 0777;
			$checks[] = ( $perms & 0002 )
				? self::check( 'wp_config_perms', 'fail', 'wp-config.php can be changed by any user on the server', 'Permissions are ' . decoct( $perms ) . '. Should be 640 or 600.' )
				: self::check( 'wp_config_perms', ( $perms & 0004 ) ? 'info' : 'pass', 'wp-config.php permissions are ' . decoct( $perms ), ( $perms & 0004 ) ? 'Readable by other server users. 640 or 600 is stricter if the host allows.' : '' );
		}

		$uploads = wp_get_upload_dir();
		$php_in_uploads = self::find_files( $uploads['basedir'], '/\.(php\d?|phtml|phar)$/i', 200 );
		$guards         = array_filter( $php_in_uploads, array( 'NomadSA_Tools', 'is_guard_file' ) );
		$suspicious     = array_diff( $php_in_uploads, $guards );
		if ( $suspicious ) {
			$checks[] = self::check( 'php_in_uploads', 'fail', count( $suspicious ) . ' PHP files with code in the uploads folder', 'Uploads should only contain media. Review these, they may be malicious: ' . implode( ', ', array_map( array( 'NomadSA_Tools', 'relative' ), array_slice( $suspicious, 0, 20 ) ) ) );
		} elseif ( $guards ) {
			$folders  = array_unique( array_map( function ( $f ) { return NomadSA_Tools::relative( dirname( $f ) ); }, $guards ) );
			$checks[] = self::check( 'php_in_uploads', 'info', count( $guards ) . ' harmless PHP placeholder or data files in uploads', 'Blank index files or plugin data files that stop immediately, in: ' . implode( ', ', array_slice( $folders, 0, 10 ) ) . '. Leftovers from removed plugins can be cleaned up.' );
		} else {
			$checks[] = self::check( 'php_in_uploads', 'pass', 'No PHP files in uploads', '' );
		}

		list( $res ) = NomadSA_Tools::fetch( $uploads['baseurl'] . '/', 'GET', $timeout );
		$listing  = ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) && false !== stripos( wp_remote_retrieve_body( $res ), 'Index of' );
		$checks[] = is_wp_error( $res )
			? self::check( 'directory_listing', 'unknown', 'Could not test folder browsing', '' )
			: ( $listing
			? self::check( 'directory_listing', 'fail', 'Folder contents can be browsed', $uploads['baseurl'] . '/ lists every file. Ask the host to disable directory listing.' )
			: self::check( 'directory_listing', 'pass', 'Folder browsing is blocked', '' ) );

		$info_files = array_filter( array( 'readme.html', 'license.txt' ), function ( $f ) {
			return file_exists( ABSPATH . $f );
		} );
		$checks[] = $info_files
			? self::check( 'info_files', 'info', 'WordPress information files are present', implode( ', ', $info_files ) . ' reveal that the site runs WordPress and sometimes the version. Low risk.' )
			: self::check( 'info_files', 'pass', 'WordPress information files are absent', '' );

		// Users.
		$risky = get_users( array( 'login__in' => array( 'admin', 'administrator', 'root', 'test', 'demo', 'user' ), 'fields' => array( 'user_login' ) ) );
		$checks[] = $risky
			? self::check( 'admin_username', 'warning', 'Easy to guess usernames exist', implode( ', ', wp_list_pluck( $risky, 'user_login' ) ) . '. These are the first names attackers try.' )
			: self::check( 'admin_username', 'pass', 'No easy to guess usernames', '' );

		$admins   = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_login' ) ) );
		$checks[] = count( $admins ) > 3
			? self::check( 'admin_count', 'warning', count( $admins ) . ' administrator accounts', 'Every admin account is a way in. Review: ' . implode( ', ', wp_list_pluck( $admins, 'user_login' ) ) )
			: self::check( 'admin_count', 'pass', count( $admins ) . ' administrator accounts', implode( ', ', wp_list_pluck( $admins, 'user_login' ) ) );

		if ( get_option( 'users_can_register' ) ) {
			$role     = get_option( 'default_role' );
			$checks[] = in_array( $role, array( 'administrator', 'editor', 'shop_manager' ), true )
				? self::check( 'registration', 'fail', 'Anyone can register as ' . $role, 'This gives strangers powerful access. Switch registration off or set the default role to subscriber.' )
				: self::check( 'registration', 'info', 'Anyone can register (as ' . $role . ')', 'Fine if intended, otherwise switch it off.' );
		} else {
			$checks[] = self::check( 'registration', 'pass', 'Public registration is off', '' );
		}

		$enum = false;
		list( $res ) = NomadSA_Tools::fetch( rest_url( 'wp/v2/users' ), 'GET', $timeout );
		if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
			$list = json_decode( wp_remote_retrieve_body( $res ), true );
			$enum = is_array( $list ) && ! empty( $list );
		}
		$checks[] = is_wp_error( $res )
			? self::check( 'user_enumeration', 'unknown', 'Could not test public author access', '' )
			: ( $enum
			? self::check( 'user_enumeration', 'warning', 'Usernames can be listed by anyone', rest_url( 'wp/v2/users' ) . ' shows author accounts, which helps password guessing attacks.' )
			: self::check( 'user_enumeration', 'pass', 'Usernames are not publicly listed', '' ) );

		$xmlrpc_checked = true;
		$xmlrpc_on = apply_filters( 'xmlrpc_enabled', true ) // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			 && ! NomadSA_Settings::runtime( 'disable_xmlrpc' );
		if ( $xmlrpc_on ) {
			list( $res ) = NomadSA_Tools::fetch( site_url( '/xmlrpc.php' ), 'GET', $timeout );
			$xmlrpc_checked = ! is_wp_error( $res );
			$xmlrpc_on = $xmlrpc_checked && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 405 ), true ) && false !== stripos( wp_remote_retrieve_body( $res ), 'XML-RPC' );
		}
		$checks[] = ! $xmlrpc_checked
			? self::check( 'xmlrpc', 'unknown', 'Could not test XML-RPC', '' )
			: ( $xmlrpc_on
			? self::check( 'xmlrpc', 'warning', 'XML-RPC is enabled', 'An old remote access method often used for password guessing attacks. Safe to disable unless the Jetpack plugin or the WordPress mobile app is used.' )
			: self::check( 'xmlrpc', 'pass', 'XML-RPC is disabled or blocked', '' ) );

		// Homepage headers.
		list( $res ) = NomadSA_Tools::fetch( $home, 'GET', $timeout );
		$home_status = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
		if ( $home_status >= 200 && $home_status < 400 ) {
			$checks[] = self::check( 'homepage', 'pass', 'The homepage responded', 'HTTP ' . $home_status );
			$want    = array( 'strict-transport-security', 'x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy', 'content-security-policy' );
			$missing = array();
			foreach ( $want as $h ) {
				if ( '' === self::header_value( $res, $h ) ) {
					if ( 'x-frame-options' === $h && false !== stripos( self::header_value( $res, 'content-security-policy' ), 'frame-ancestors' ) ) {
						continue;
					}
					$missing[] = $h;
				}
			}
			$missing_core = array_diff( $missing, array( 'content-security-policy' ) );
			$checks[]     = $missing_core
				? self::check( 'security_headers', 'warning', count( $missing ) . ' browser security headers missing', 'Missing: ' . implode( ', ', $missing ) . '. These tell browsers to block common attacks such as clickjacking.' )
				: self::check( 'security_headers', 'pass', 'Browser security headers are set', $missing ? 'Content-Security-Policy is not set, which is common and optional.' : '' );

			$leaks = array();
			foreach ( array( 'x-powered-by', 'server' ) as $h ) {
				$v = self::header_value( $res, $h );
				if ( preg_match( '/\d+\.\d+/', $v ) ) {
					$leaks[] = $h . ': ' . $v;
				}
			}
			$body = wp_remote_retrieve_body( $res );
			if ( preg_match( '/<meta[^>]+name=["\']generator["\'][^>]+content=["\']WordPress\s*([\d.]+)/i', $body, $m ) ) {
				$leaks[] = 'generator meta tag shows WordPress ' . $m[1];
			}
			$checks[] = $leaks
				? self::check( 'version_disclosure', 'info', 'Software versions are visible', implode( '; ', $leaks ) )
				: self::check( 'version_disclosure', 'pass', 'Software versions are hidden', '' );
		} else {
			$checks[] = self::check( 'homepage', 'fail', 'Could not load the homepage', $home_status ? 'HTTP ' . $home_status : $res->get_error_message() );
		}

		// Protection plugins.
		$stack    = self::detect_stack();
		$checks[] = $stack['security'] ? self::check( 'security_plugin', 'pass', 'Security plugin active', implode( ', ', $stack['security'] ) ) : self::check( 'security_plugin', 'warning', 'No security plugin found', 'A firewall and login protection plugin such as Wordfence is recommended, unless the host provides one.' );
		$checks[] = $stack['backup'] ? self::check( 'backup_plugin', 'pass', 'Backup plugin active', implode( ', ', $stack['backup'] ) ) : self::check( 'backup_plugin', 'warning', 'No backup plugin found', 'Make sure the host keeps daily backups stored off the server.' );

		$checks[] = 'wp_' === $wpdb->prefix
			? self::check( 'table_prefix', 'info', 'Default database table prefix', 'wp_ is the default. Changing it on a live site is not worth the risk; noted for completeness.' )
			: self::check( 'table_prefix', 'pass', 'Custom database table prefix', '' );

		$auto     = (array) get_site_option( 'auto_update_plugins', array() );
		$checks[] = self::check( 'auto_updates', 'info', count( $auto ) . ' plugins update automatically', 'Automatic updates close security holes faster; manual updates give more control. Either is fine with regular maintenance.' );

		$score = array( 'pass' => 0, 'warning' => 0, 'fail' => 0, 'info' => 0, 'unknown' => 0 );
		foreach ( $checks as $c ) {
			$score[ $c['status'] ]++;
		}
		return array(
			'summary' => $score['fail'] . ' failed, ' . $score['warning'] . ' warnings, ' . $score['pass'] . ' passed',
			'score'   => $score,
			'checks'  => $checks,
		);
	}

	private static function ssl_check( $host, $timeout ) {
		if ( ! function_exists( 'stream_socket_client' ) || ! function_exists( 'openssl_x509_parse' ) ) {
			return self::check( 'ssl_expiry', 'unknown', 'SSL certificate not checked', 'The server cannot inspect certificates.' );
		}
		$ctx = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $host ) ) );
		$fp  = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx );
		if ( ! $fp ) {
			return self::check( 'ssl_expiry', 'unknown', 'SSL certificate not checked', 'Could not connect: ' . $errstr );
		}
		$params = stream_context_get_params( $fp );
		fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes a network socket, not a file.
		$peer = $params['options']['ssl']['peer_certificate'] ?? null;
		$cert = $peer ? openssl_x509_parse( $peer ) : false;
		if ( ! $cert ) {
			return self::check( 'ssl_expiry', 'unknown', 'SSL certificate not checked', '' );
		}
		$days   = (int) floor( ( $cert['validTo_time_t'] - time() ) / DAY_IN_SECONDS );
		$issuer = $cert['issuer']['O'] ?? ( $cert['issuer']['CN'] ?? '' );
		if ( $days < 0 ) {
			return self::check( 'ssl_expiry', 'fail', 'SSL certificate has expired', 'Expired ' . abs( $days ) . ' days ago. Visitors see a security warning.' );
		}
		return self::check( 'ssl_expiry', $days < 14 ? 'warning' : 'pass', 'SSL certificate valid for ' . $days . ' more days', 'Issued by ' . $issuer . '. Free certificates renew automatically about 30 days before expiry.' );
	}

	private static function find_files( $dir, $regex, $limit = 200, $max_scan = 50000 ) {
		$found = array();
		if ( ! is_dir( $dir ) ) {
			return $found;
		}
		$scanned = 0;
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::LEAVES_ONLY );
			foreach ( $it as $file ) {
				if ( ++$scanned > $max_scan ) {
					break;
				}
				if ( ! $file->isLink() && $file->isFile() && ! NomadSA_Tools::is_sensitive_path( wp_normalize_path( $file->getPathname() ) ) && preg_match( $regex, $file->getFilename() ) ) {
					$found[] = $file->getPathname();
					if ( count( $found ) >= $limit ) {
						break;
					}
				}
			}
		} catch ( \Exception $e ) {
			// Unreadable folder, return what we have.
		}
		return $found;
	}

	public static function scan_for_malware( $args ) {
		$rel  = isset( $args['path'] ) ? (string) $args['path'] : '@content';
		$root = NomadSA_Tools::resolve_path( $rel );
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		if ( ! is_dir( $root ) ) {
			return new WP_Error( 'nomadsa_path', 'Not a folder.' );
		}
		$days     = NomadSA_Tools::int_arg( $args, 'modified_days', 14, 0, 365 );
		$patterns = array(
			'eval_encoded'   => '/\b(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|strrev|hex2bin)\s*\(/i',
			'eval_request'   => '/\b(eval|assert|system|exec|shell_exec|passthru|popen|proc_open)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)/i',
			'preg_replace_e' => '/preg_replace\s*\(\s*[\'"]([^\w\s]).*\1[a-z]*e[a-z]*[\'"]/i',
			'create_function' => '/\bcreate_function\s*\(/i',
			'webshell_names' => '/(FilesMan|WSO\s?shell|c99shell|r57shell|b374k|IndoXploit|alfa\s?shell)/i',
			'encoded_blob'   => '/[\'"][A-Za-z0-9+\/=]{1500,}[\'"]/',
			'hex_obfuscation' => '/(\\\\x[0-9a-f]{2}){30,}/i',
			'variable_function' => '/\$\w+\s*=\s*[\'"](base64_decode|gzinflate|str_rot13)[\'"]\s*;/i',
			'remote_include' => '/\b(include|require)(_once)?\s*\(?\s*[\'"]https?:\/\//i',
		);
		$findings = array();
		$recent   = array();
		$uploads  = realpath( wp_get_upload_dir()['basedir'] );
		$self_dir = wp_normalize_path( NOMADSA_DIR );
		$scanned  = 0;
		$cutoff   = time() - $days * DAY_IN_SECONDS;

		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			$path = wp_normalize_path( $file->getPathname() );
			if ( $file->isLink() || ! $file->isFile() || ! preg_match( '/\.(php\d?|phtml|phar|inc|ico|js)$/i', $path ) || 0 === strpos( $path, $self_dir ) || NomadSA_Tools::is_sensitive_path( $path ) ) {
				continue;
			}
			if ( ++$scanned > 40000 ) {
				break;
			}
			$is_php = (bool) preg_match( '/\.(php\d?|phtml|phar|inc)$/i', $path );
			if ( $is_php && $days && $file->getMTime() > $cutoff && count( $recent ) < 100 ) {
				$recent[] = array( 'file' => NomadSA_Tools::relative( $path ), 'modified' => gmdate( 'Y-m-d H:i', $file->getMTime() ) );
			}
			if ( $is_php && false !== $uploads && NomadSA_Tools::within( $path, $uploads ) && ! NomadSA_Tools::is_guard_file( $path ) ) {
				$findings[] = array( 'file' => NomadSA_Tools::relative( $path ), 'rule' => 'php_in_uploads', 'line' => null, 'snippet' => '' );
			}
			if ( $file->getSize() > 3 * MB_IN_BYTES ) {
				continue;
			}
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( 'ico' === $ext ) {
				$head = (string) file_get_contents( $path, false, null, 0, 200 );
				if ( false !== stripos( $head, '<?php' ) ) {
					$findings[] = array( 'file' => NomadSA_Tools::relative( $path ), 'rule' => 'php_in_image', 'line' => 1, 'snippet' => '' );
				}
				continue;
			}
			if ( 'js' === $ext ) {
				continue; // Minified JavaScript produces too many false alarms.
			}
			$lines = @file( $path );
			if ( ! $lines ) {
				continue;
			}
			foreach ( $lines as $i => $line ) {
				foreach ( $patterns as $rule => $re ) {
					if ( preg_match( $re, $line ) ) {
						$findings[] = array( 'file' => NomadSA_Tools::relative( $path ), 'rule' => $rule, 'line' => $i + 1, 'snippet' => mb_substr( trim( NomadSA_Tools::redact( $line ) ), 0, 200 ) );
						break 2;
					}
				}
			}
			if ( count( $findings ) >= 200 ) {
				break;
			}
		}
		usort( $recent, function ( $a, $b ) {
			return strcmp( $b['modified'], $a['modified'] );
		} );
		return array(
			'summary'         => count( $findings ) . ' suspicious patterns in ' . $scanned . ' files',
			'findings'        => $findings,
			'recently_changed_php' => $recent,
			'note'            => 'Some legitimate plugins use encoded strings or create_function. Review each finding with read_file and compare with verify_plugin_files before acting. Recent changes are normal right after updates.',
		);
	}

	public static function verify_core_files() {
		global $wp_version;
		require_once ABSPATH . 'wp-admin/includes/update.php';
		$locale = get_locale();
		$sums   = get_core_checksums( $wp_version, $locale );
		if ( ! $sums && 'en_US' !== $locale ) {
			$sums = get_core_checksums( $wp_version, 'en_US' );
		}
		if ( ! is_array( $sums ) ) {
			$dev = (bool) preg_match( '/-(alpha|beta|RC|src)/i', $wp_version );
			return new WP_Error( 'nomadsa_checksums', $dev
				? 'WordPress ' . $wp_version . ' is a test version, so no official checksums exist.'
				: 'Could not download official checksums from WordPress.org. The server may block outgoing requests.' );
		}
		$modified = array();
		$missing  = array();
		foreach ( $sums as $file => $md5 ) {
			if ( 0 === strpos( $file, 'wp-content/' ) ) {
				continue; // Default themes and plugins may be removed or updated separately.
			}
			$path = ABSPATH . $file;
			if ( ! file_exists( $path ) ) {
				if ( ! in_array( $file, array( 'readme.html', 'license.txt', 'wp-config-sample.php' ), true ) ) {
					$missing[] = $file;
				}
				continue;
			}
			if ( md5_file( $path ) !== $md5 ) {
				$modified[] = $file;
			}
		}
		$unexpected = array();
		foreach ( array( 'wp-admin', 'wp-includes' ) as $dir ) {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( ABSPATH . $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				$rel = ltrim( substr( wp_normalize_path( $f->getPathname() ), strlen( wp_normalize_path( ABSPATH ) ) ), '/' );
				if ( ! isset( $sums[ $rel ] ) && preg_match( '/\.(php\d?|phtml|js)$/i', $rel ) ) {
					$unexpected[] = $rel;
					if ( count( $unexpected ) >= 100 ) {
						break 2;
					}
				}
			}
		}
		foreach ( glob( ABSPATH . '*.php' ) as $f ) {
			$rel = basename( $f );
			if ( ! isset( $sums[ $rel ] ) && 'wp-config.php' !== $rel ) {
				$unexpected[] = $rel;
			}
		}
		$ok = ! $modified && ! $missing && ! $unexpected;
		return array(
			'summary'          => $ok ? 'All WordPress core files match the official versions' : count( $modified ) . ' modified, ' . count( $missing ) . ' missing, ' . count( $unexpected ) . ' unexpected files',
			'version'          => $wp_version,
			'modified'         => $modified,
			'missing'          => $missing,
			'unexpected_files' => $unexpected,
			'note'             => $ok ? '' : 'Modified or unexpected core files are a strong sign of a hack. Unexpected PHP files in the root can be legitimate (host files, verification files); review them with read_file.',
		);
	}

	public static function verify_plugin_files( $args ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$only    = isset( $args['plugin'] ) ? sanitize_key( $args['plugin'] ) : '';
		$report  = array();
		foreach ( $plugins as $file => $data ) {
			$slug = dirname( $file );
			if ( '.' === $slug || ( $only && $slug !== $only ) || ( ! $only && ! in_array( $file, $active, true ) ) ) {
				continue;
			}
			$res = wp_remote_get( 'https://downloads.wordpress.org/plugin-checksums/' . $slug . '/' . rawurlencode( $data['Version'] ) . '.json', array( 'timeout' => 15 ) );
			if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
				$report[] = array( 'plugin' => $slug, 'version' => $data['Version'], 'status' => 'no official checksums (premium, custom or unknown version)' );
				continue;
			}
			$sums = json_decode( wp_remote_retrieve_body( $res ), true );
			$sums = $sums['files'] ?? array();
			$dir  = WP_PLUGIN_DIR . '/' . $slug . '/';
			$modified = array();
			$added    = array();
			foreach ( $sums as $rel => $hash ) {
				$path = $dir . $rel;
				if ( file_exists( $path ) ) {
					$md5s = (array) ( $hash['md5'] ?? array() );
					if ( ! in_array( md5_file( $path ), $md5s, true ) ) {
						$modified[] = $rel;
					}
				}
			}
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				$rel = ltrim( substr( wp_normalize_path( $f->getPathname() ), strlen( wp_normalize_path( $dir ) ) ), '/' );
				if ( ! isset( $sums[ $rel ] ) && preg_match( '/\.(php\d?|phtml)$/i', $rel ) ) {
					$added[] = $rel;
					if ( count( $added ) >= 50 ) {
						break;
					}
				}
			}
			$report[] = array(
				'plugin'      => $slug,
				'version'     => $data['Version'],
				'status'      => ( $modified || $added ) ? 'differences found' : 'matches official files',
				'modified'    => array_slice( $modified, 0, 50 ),
				'added_php'   => $added,
			);
		}
		$issues = array_filter( $report, function ( $r ) {
			return 'differences found' === $r['status'];
		} );
		return array(
			'summary' => count( $report ) . ' plugins checked, ' . count( $issues ) . ' with differences',
			'plugins' => $report,
			'note'    => 'Added PHP files are sometimes created by the plugin itself (caches, generated files). Modified files in a plugin that does not do this are suspicious.',
		);
	}

	/* ================================================================ Search */

	public static function seo_audit( $args, $timeout = 15 ) {
		$types  = ! empty( $args['post_types'] ) && is_array( $args['post_types'] ) ? array_map( 'sanitize_key', $args['post_types'] ) : array( 'page', 'post' );
		$limit  = NomadSA_Tools::int_arg( $args, 'limit', 100, 1, 300 );
		$offset = NomadSA_Tools::int_arg( $args, 'offset', 0, 0, 100000 );
		$plugin = self::seo_plugin();

		$site = array();
		$site['search_engines_allowed'] = (bool) get_option( 'blog_public' );
		$site['seo_plugin']             = $plugin ?: 'none detected';
		$site['permalinks']             = get_option( 'permalink_structure' ) ?: 'plain';
		$site['site_title']             = get_bloginfo( 'name' );
		$site['tagline']                = get_bloginfo( 'description' );

		$sitemap = 'yoast' === $plugin || 'rankmath' === $plugin ? home_url( '/sitemap_index.xml' ) : get_sitemap_url( 'index' );
		list( $res ) = NomadSA_Tools::fetch( $sitemap, 'GET', $timeout );
		$site['sitemap'] = array(
			'url'    => $sitemap,
			'status' => is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ),
		);
		list( $res ) = NomadSA_Tools::fetch( home_url( '/robots.txt' ), 'GET', $timeout );
		$robots      = is_wp_error( $res ) ? '' : wp_remote_retrieve_body( $res );
		$site['robots_txt'] = array(
			'content'          => mb_substr( $robots, 0, 2000 ),
			'available'         => ! is_wp_error( $res ) && in_array( wp_remote_retrieve_response_code( $res ), array( 200, 404, 410 ), true ),
			'blocks_whole_site' => (bool) preg_match( '/^\s*Disallow:\s*\/\s*$/mi', $robots ),
			'lists_sitemap'    => false !== stripos( $robots, 'sitemap:' ),
		);

		global $wpdb;
		$site['media_without_alt'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
			 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' AND ( m.meta_value IS NULL OR m.meta_value = '' )"
		);

		$posts = get_posts( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'has_password'   => false,
		) );

		$items    = array();
		$titles   = array();
		$descs    = array();
		$counts   = array();
		foreach ( $posts as $post ) {
			$meta    = self::seo_meta( $post->ID );
			$content = self::rendered_content( $post );
			$text    = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
			$words   = str_word_count( $text );
			preg_match_all( '/<img\b[^>]*>/i', $content, $imgs );
			$no_alt = 0;
			foreach ( $imgs[0] as $img ) {
				if ( ! preg_match( '/\balt\s*=\s*["\'][^"\']+["\']/i', $img ) ) {
					$no_alt++;
				}
			}
			$h1 = preg_match_all( '/<h1\b/i', $content ) + ( false !== strpos( $content, '"header_size":"h1"' ) ? 1 : 0 );
			$internal = preg_match_all( '/href=["\'](' . preg_quote( home_url(), '/' ) . '|\/)[^"\']*/i', $content );

			$issues = array();
			if ( $meta['noindex'] ) {
				$issues[] = 'Hidden from search engines (noindex)';
			}
			if ( $plugin && '' === $meta['description'] ) {
				$issues[] = 'No meta description, Google will pick text itself';
			} elseif ( $meta['description'] && mb_strlen( $meta['description'] ) > 160 ) {
				$issues[] = 'Meta description longer than 160 characters';
			} elseif ( $meta['description'] && mb_strlen( $meta['description'] ) < 70 ) {
				$issues[] = 'Meta description shorter than 70 characters';
			}
			$shown_title = $meta['title'] ?: $post->post_title;
			if ( false === strpos( $shown_title, '%%' ) && mb_strlen( $shown_title ) > 65 ) {
				$issues[] = 'Search title longer than about 60 characters may be cut off';
			}
			if ( 'post' === $post->post_type && $words < 300 ) {
				$issues[] = 'Thin content (' . $words . ' words)';
			}
			if ( $no_alt ) {
				$issues[] = $no_alt . ' images without alt text';
			}
			if ( $h1 > 1 ) {
				$issues[] = 'More than one main heading (H1) in the content';
			}
			if ( 0 === $internal && 'post' === $post->post_type ) {
				$issues[] = 'No links to other pages on the site';
			}

			$key            = mb_strtolower( $shown_title );
			$titles[ $key ] = ( $titles[ $key ] ?? 0 ) + 1;
			if ( $meta['description'] ) {
				$dk           = mb_strtolower( $meta['description'] );
				$descs[ $dk ] = ( $descs[ $dk ] ?? 0 ) + 1;
			}
			$items[] = array(
				'id'           => $post->ID,
				'type'         => $post->post_type,
				'title'        => $post->post_title,
				'url'          => get_permalink( $post ),
				'search_title' => $meta['title'] ?: '(default template)',
				'meta_description' => $meta['description'],
				'words'        => $words,
				'issues'       => $issues,
				'_t'           => $key,
				'_d'           => mb_strtolower( $meta['description'] ),
			);
		}
		foreach ( $items as &$it ) {
			if ( ( $titles[ $it['_t'] ] ?? 0 ) > 1 ) {
				$it['issues'][] = 'Same search title as another page';
			}
			if ( $it['_d'] && ( $descs[ $it['_d'] ] ?? 0 ) > 1 ) {
				$it['issues'][] = 'Same meta description as another page';
			}
			unset( $it['_t'], $it['_d'] );
			foreach ( $it['issues'] as $i ) {
				$counts[ $i ] = ( $counts[ $i ] ?? 0 ) + 1;
			}
		}
		unset( $it );

		$site_issues = array();
		if ( ! $site['search_engines_allowed'] ) {
			$site_issues[] = 'The whole site is set to discourage search engines (Settings, Reading).';
		}
		if ( $site['robots_txt']['blocks_whole_site'] ) {
			$site_issues[] = 'robots.txt blocks the whole site.';
		}
		if ( 200 !== $site['sitemap']['status'] ) {
			$site_issues[] = 'Sitemap not found at ' . $sitemap . '.';
		}
		if ( 'plain' === $site['permalinks'] ) {
			$site_issues[] = 'Plain permalinks (?p=123) are not search friendly.';
		}
		if ( ! $plugin ) {
			$site_issues[] = 'No search optimisation plugin, so titles and descriptions cannot be set per page.';
		}
		if ( $site['media_without_alt'] ) {
			$site_issues[] = $site['media_without_alt'] . ' images in the media library have no alt text.';
		}

		return array(
			'summary'      => count( $items ) . ' items reviewed, ' . count( array_filter( $items, function ( $i ) { return ! empty( $i['issues'] ); } ) ) . ' with issues',
			'site'         => $site,
			'site_issues'  => $site_issues,
			'issue_counts' => $counts,
			'items'        => $items,
			'paging'       => array( 'offset' => $offset, 'limit' => $limit, 'returned' => count( $items ) ),
			'tip'          => 'Use check_page for a rendered view. Edit search titles and descriptions in the WordPress editor or your SEO plugin.',
		);
	}

	public static function check_page( $args ) {
		$url = isset( $args['url'] ) && '' !== $args['url'] ? (string) $args['url'] : home_url( '/' );
		if ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		}
		$target = wp_parse_url( $url );
		$home = wp_parse_url( home_url() );
		if ( empty( $target['host'] ) || strtolower( $target['host'] ) !== strtolower( $home['host'] )
			|| ( $target['scheme'] ?? '' ) !== $home['scheme']
			|| ( $target['port'] ?? ( 'https' === $home['scheme'] ? 443 : 80 ) ) !== ( $home['port'] ?? ( 'https' === $home['scheme'] ? 443 : 80 ) )
			|| isset( $target['user'] ) || isset( $target['pass'] ) ) {
			return new WP_Error( 'nomadsa_url', 'Only pages on this site can be checked.' );
		}
		list( $res, $ms ) = NomadSA_Tools::fetch( $url );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$html  = wp_remote_retrieve_body( $res );
		$code  = wp_remote_retrieve_response_code( $res );
		$out   = array(
			'url'           => $url,
			'status'        => $code,
			'response_ms'   => $ms,
			'html_size_kb'  => round( strlen( $html ) / 1024, 1 ),
			'headers'       => array(),
		);
		foreach ( array( 'cache-control', 'content-encoding', 'x-cache', 'x-proxy-cache', 'cf-cache-status', 'server', 'x-powered-by', 'strict-transport-security', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'permissions-policy', 'content-security-policy', 'x-robots-tag' ) as $h ) {
			$v = self::header_value( $res, $h );
			if ( '' !== $v ) {
				$out['headers'][ $h ] = $v;
			}
		}
		if ( '' === $html ) {
			return $out;
		}

		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			$out['note'] = 'Install the PHP DOM extension to analyse the page HTML.';
			return $out;
		}
		$previous = libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xp = new DOMXPath( $doc );

		$first = function ( $q, $attr = null ) use ( $xp ) {
			$n = $xp->query( $q )->item( 0 );
			if ( ! $n ) {
				return null;
			}
			return $attr ? $n->getAttribute( $attr ) : trim( $n->textContent );
		};

		$title = $first( '//title' );
		$desc  = $first( '//meta[@name="description"]', 'content' );
		$out['seo'] = array(
			'title'              => $title,
			'title_length'       => $title ? mb_strlen( $title ) : 0,
			'meta_description'   => $desc,
			'description_length' => $desc ? mb_strlen( $desc ) : 0,
			'canonical'          => $first( '//link[@rel="canonical"]', 'href' ),
			'robots'             => $first( '//meta[@name="robots"]', 'content' ),
			'lang'               => $first( '//html', 'lang' ),
			'viewport'           => $first( '//meta[@name="viewport"]', 'content' ),
			'open_graph'         => $xp->query( '//meta[starts-with(@property,"og:")]' )->length > 0,
			'structured_data'    => array(),
			'h1'                 => array(),
			'h2_count'           => $xp->query( '//h2' )->length,
		);
		foreach ( $xp->query( '//h1' ) as $h ) {
			$out['seo']['h1'][] = mb_substr( trim( preg_replace( '/\s+/', ' ', $h->textContent ) ), 0, 120 );
		}
		foreach ( $xp->query( '//script[@type="application/ld+json"]' ) as $s ) {
			$j = json_decode( $s->textContent, true );
			if ( isset( $j['@graph'] ) ) {
				foreach ( $j['@graph'] as $g ) {
					$out['seo']['structured_data'][] = is_array( $g['@type'] ?? '' ) ? implode( '/', $g['@type'] ) : ( $g['@type'] ?? '?' );
				}
			} elseif ( isset( $j['@type'] ) ) {
				$out['seo']['structured_data'][] = is_array( $j['@type'] ) ? implode( '/', $j['@type'] ) : $j['@type'];
			}
		}

		$imgs     = $xp->query( '//img' );
		$no_alt   = array();
		$no_size  = 0;
		$lazy     = 0;
		$first_img = null;
		foreach ( $imgs as $i => $img ) {
			$src = $img->getAttribute( 'src' ) ?: $img->getAttribute( 'data-src' );
			if ( 0 === $i ) {
				$first_img = array( 'src' => $src, 'loading' => $img->getAttribute( 'loading' ) ?: 'eager', 'fetchpriority' => $img->getAttribute( 'fetchpriority' ) );
			}
			if ( ! $img->hasAttribute( 'alt' ) ) {
				$no_alt[] = $src;
			}
			if ( ! $img->getAttribute( 'width' ) || ! $img->getAttribute( 'height' ) ) {
				$no_size++;
			}
			if ( 'lazy' === $img->getAttribute( 'loading' ) ) {
				$lazy++;
			}
		}
		$out['images'] = array(
			'total'              => $imgs->length,
			'missing_alt'        => count( $no_alt ),
			'missing_alt_examples' => array_slice( $no_alt, 0, 10 ),
			'without_dimensions' => $no_size,
			'lazy_loaded'        => $lazy,
			'first_image'        => $first_img,
		);

		$blocking = array();
		foreach ( $xp->query( '//head/script[@src]' ) as $s ) {
			if ( ! $s->hasAttribute( 'async' ) && ! $s->hasAttribute( 'defer' ) && 'module' !== $s->getAttribute( 'type' ) ) {
				$blocking[] = $s->getAttribute( 'src' );
			}
		}
		$css = array();
		foreach ( $xp->query( '//link[@rel="stylesheet"]' ) as $l ) {
			$css[] = $l->getAttribute( 'href' );
		}
		$out['performance'] = array(
			'scripts_total'           => $xp->query( '//script[@src]' )->length,
			'render_blocking_scripts' => $blocking,
			'stylesheets'             => count( $css ),
			'inline_style_kb'         => round( array_sum( array_map( function ( $n ) { return strlen( $n->textContent ); }, iterator_to_array( $xp->query( '//style' ) ) ) ) / 1024, 1 ),
			'preconnects'             => $xp->query( '//link[@rel="preconnect"]' )->length,
			'font_preloads'           => $xp->query( '//link[@rel="preload" and @as="font"]' )->length,
		);

		$loaders = array();
		foreach ( $xp->query( '//*[contains(translate(@class,"PRELOADR","preloadr"),"preloader") or contains(translate(@id,"PRELOADR","preloadr"),"preloader") or contains(@class,"page-loader") or contains(@class,"loading-screen") or contains(@class,"e-page-transition")]' ) as $n ) {
			$loaders[] = '<' . $n->nodeName . ( $n->getAttribute( 'id' ) ? ' id="' . $n->getAttribute( 'id' ) . '"' : '' ) . ' class="' . mb_substr( $n->getAttribute( 'class' ), 0, 120 ) . '">';
			if ( count( $loaders ) >= 5 ) {
				break;
			}
		}
		if ( $loaders ) {
			$out['performance']['loading_screen'] = array(
				'found'    => $loaders,
				'impact'   => 'A loading screen hides the page until it finishes, which delays when visitors and Google see the main content (Largest Contentful Paint).',
			);
		}

		$mixed = array();
		if ( 0 === strpos( $url, 'https://' ) && preg_match_all( '/(?:src|href)=["\'](http:\/\/[^"\']+\.(?:js|css|png|jpe?g|gif|webp|svg|woff2?))["\']/i', $html, $m ) ) {
			$mixed = array_slice( array_unique( $m[1] ), 0, 10 );
		}
		$out['mixed_content'] = $mixed;

		$internal = 0;
		$external = 0;
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $xp->query( '//a[@href]' ) as $a ) {
			$h = wp_parse_url( $a->getAttribute( 'href' ), PHP_URL_HOST );
			if ( ! $h || $h === $host ) {
				$internal++;
			} else {
				$external++;
			}
		}
		$out['links'] = array( 'internal' => $internal, 'external' => $external );

		if ( preg_match( '/<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)/i', $html, $m ) ) {
			$out['generator'] = $m[1];
		}
		$out['summary'] = 'HTTP ' . $code . ' in ' . $ms . ' ms, ' . $out['html_size_kb'] . ' KB, ' . count( $blocking ) . ' render blocking scripts' . ( $loaders ? ', loading screen found' : '' );
		return $out;
	}

	public static function find_broken_links( $args ) {
		$external = ! empty( $args['include_external'] );
		$max      = NomadSA_Tools::int_arg( $args, 'max_urls', 80, 1, 250 );
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$posts    = get_posts( array( 'post_type' => 'any', 'post_status' => 'publish', 'posts_per_page' => 500 ) );
		$urls     = array();
		foreach ( $posts as $post ) {
			$content = self::rendered_content( $post );
			$content = str_replace( '\\/', '/', $content );
			if ( ! preg_match_all( '/(?:href|src)=\\\\?["\']([^"\'#\\\\]+)/i', $content, $m ) ) {
				continue;
			}
			foreach ( array_unique( $m[1] ) as $u ) {
				if ( preg_match( '/^(mailto:|tel:|javascript:|data:|\{\{|\[)/i', $u ) ) {
					continue;
				}
				if ( 0 === strpos( $u, '/' ) && 0 !== strpos( $u, '//' ) ) {
					$u = home_url( $u );
				}
				$h = wp_parse_url( $u, PHP_URL_HOST );
				if ( ! $h ) {
					continue;
				}
				if ( $h !== $host && ! $external ) {
					continue;
				}
				$urls[ $u ][] = array( 'id' => $post->ID, 'title' => $post->post_title );
			}
		}
		$checked = 0;
		$broken  = array();
		foreach ( $urls as $u => $where ) {
			if ( $checked >= $max ) {
				break;
			}
			$checked++;
			list( $res ) = NomadSA_Tools::fetch( $u, 'HEAD' );
			$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
			if ( in_array( $code, array( 0, 403, 405, 501 ), true ) ) {
				list( $res ) = NomadSA_Tools::fetch( $u, 'GET' );
				$code = is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res );
			}
			if ( 0 === $code || $code >= 400 ) {
				$broken[] = array(
					'url'      => $u,
					'status'   => $code ?: ( is_wp_error( $res ) ? $res->get_error_message() : 'no response' ),
					'found_on' => array_slice( $where, 0, 10 ),
				);
			}
		}
		return array(
			'summary'       => count( $broken ) . ' broken out of ' . $checked . ' checked (' . count( $urls ) . ' unique links found)',
			'broken'        => $broken,
			'not_checked'   => max( 0, count( $urls ) - $checked ),
			'tip'           => 'Correct broken links in the WordPress editor on the pages listed.',
		);
	}

	/* ================================================================ Health */

	public static function database_health() {
		global $wpdb;
		$out = array();
		if ( 'sqlite' !== ( defined( 'DB_ENGINE' ) ? DB_ENGINE : '' ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT table_name AS t, ROUND((data_length + index_length) / 1048576, 2) AS mb, table_rows AS r FROM information_schema.TABLES WHERE table_schema = %s ORDER BY (data_length + index_length) DESC',
				DB_NAME
			), ARRAY_A );
			$out['total_mb']   = round( array_sum( wp_list_pluck( (array) $rows, 'mb' ) ), 2 );
			$out['largest_tables'] = array_slice( (array) $rows, 0, 10 );
		}
		$out['autoload_kb'] = round( (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ) / 1024, 1 );
		$out['largest_autoloaded_options'] = $wpdb->get_results( "SELECT option_name AS name, ROUND(LENGTH(option_value) / 1024, 1) AS kb FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto') ORDER BY LENGTH(option_value) DESC LIMIT 10", ARRAY_A );
		$out['revisions']          = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
		$out['auto_drafts']        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
		$out['trashed_posts']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
		$out['spam_comments']      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
		$out['trashed_comments']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
		$out['expired_transients'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
		$out['orphaned_postmeta']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.ID IS NULL" );

		$notes = array();
		if ( $out['autoload_kb'] > 800 ) {
			$notes[] = 'Autoloaded options are ' . $out['autoload_kb'] . ' KB. Above about 800 KB this slows every page. Check the largest options for leftovers from removed plugins.';
		}
		if ( $out['revisions'] > 500 ) {
			$notes[] = $out['revisions'] . ' old revisions can be cleaned up.';
		}
		$out['notes']   = $notes;
		$out['summary'] = 'Autoload ' . $out['autoload_kb'] . ' KB, ' . $out['revisions'] . ' revisions, ' . $out['expired_transients'] . ' expired transients';
		$out['tip']     = 'Review these counts before running database maintenance from WordPress or your host.';
		return $out;
	}

	public static function list_cron_events() {
		$crons  = _get_cron_array();
		$events = array();
		$now    = time();
		foreach ( (array) $crons as $ts => $hooks ) {
			foreach ( $hooks as $hook => $items ) {
				foreach ( $items as $item ) {
					$events[] = array(
						'hook'     => $hook,
						'next_run' => gmdate( 'Y-m-d H:i:s', $ts ) . ' UTC',
						'overdue'  => $ts < $now - 600,
						'schedule' => $item['schedule'] ?: 'once',
					);
				}
			}
		}
		$overdue = count( array_filter( $events, function ( $e ) {
			return $e['overdue'];
		} ) );
		return array(
			'summary'       => count( $events ) . ' scheduled tasks, ' . $overdue . ' overdue',
			'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'events'        => $events,
			'note'          => $overdue > 3 ? 'Several tasks are overdue. Scheduled tasks may not be running: the site may get little traffic or WP cron is disabled without a server cron job.' : '',
		);
	}

	public static function read_error_log( $args ) {
		$lines  = NomadSA_Tools::int_arg( $args, 'lines', 100, 1, 1000 );
		$filter = isset( $args['filter'] ) ? (string) $args['filter'] : '';
		$files  = array_filter( array( self::debug_log_path(), ini_get( 'error_log' ) ) );
		foreach ( array_unique( $files ) as $f ) {
			if ( ! stream_is_local( $f ) || ! is_file( $f ) || ! is_readable( $f ) ) {
				continue;
			}
			$size = filesize( $f );
			$read = min( $size, 2 * MB_IN_BYTES );
			// Only the end of the log matters, and logs can be very large.
			$chunk = $read ? (string) file_get_contents( $f, false, null, $size - $read, $read ) : '';
			$all = preg_split( '/\r?\n/', NomadSA_Tools::redact( $chunk ) );
			if ( $filter ) {
				$all = array_filter( $all, function ( $l ) use ( $filter ) {
					return false !== stripos( $l, $filter );
				} );
			}
			$tail = array_slice( array_values( array_filter( $all, 'strlen' ) ), -$lines );
			return array(
				'summary' => basename( $f ) . ', ' . size_format( $size ) . ', showing ' . count( $tail ) . ' lines',
				'file'    => basename( $f ),
				'size'    => size_format( $size ),
				'lines'   => $tail,
			);
		}
		return array( 'summary' => 'No readable error log found', 'note' => 'Logging may be off (WP_DEBUG_LOG) or the host keeps logs in its control panel.' );
	}

	public static function list_users( $args ) {
		$q = array( 'number' => 200, 'orderby' => 'registered', 'order' => 'DESC' );
		if ( ! empty( $args['role'] ) ) {
			$q['role'] = sanitize_key( $args['role'] );
		}
		$risky = array( 'admin', 'administrator', 'root', 'test', 'demo', 'user', 'webmaster' );
		$out   = array();
		foreach ( get_users( $q ) as $u ) {
			$email = $u->user_email;
			$at    = strpos( $email, '@' );
			$apps  = class_exists( 'WP_Application_Passwords' ) ? count( WP_Application_Passwords::get_user_application_passwords( $u->ID ) ) : 0;
			$out[] = array(
				'id'                    => $u->ID,
				'login'                 => $u->user_login,
				'display_name'          => $u->display_name,
				'roles'                 => $u->roles,
				'email'                 => $at ? substr( $email, 0, 1 ) . '***' . substr( $email, $at ) : '',
				'registered'            => $u->user_registered,
				'posts'                 => (int) count_user_posts( $u->ID ),
				'application_passwords' => $apps,
				'risky_username'        => in_array( strtolower( $u->user_login ), $risky, true ),
				'display_name_is_login' => $u->display_name === $u->user_login,
			);
		}
		return array( 'summary' => count( $out ) . ' users', 'users' => $out );
	}

	public static function list_content( $args ) {
		$limit = NomadSA_Tools::int_arg( $args, 'limit', 50, 1, 200 );
		$type  = ! empty( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'any';
		$q     = array(
			'post_type'      => $type,
			'post_status'    => ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : ( 'attachment' === $type ? 'inherit' : 'publish' ),
			'posts_per_page' => $limit,
			's'              => sanitize_text_field( $args['search'] ?? '' ),
		);
		$out = array();
		foreach ( get_posts( $q ) as $p ) {
			$row = array(
				'id'       => $p->ID,
				'type'     => $p->post_type,
				'title'    => $p->post_title,
				'url'      => 'attachment' === $p->post_type ? wp_get_attachment_url( $p->ID ) : get_permalink( $p ),
				'modified' => $p->post_modified,
			);
			if ( 'attachment' === $p->post_type ) {
				$row['alt'] = get_post_meta( $p->ID, '_wp_attachment_image_alt', true );
			} else {
				$row['builder'] = get_post_meta( $p->ID, '_elementor_edit_mode', true ) ? 'elementor' : 'editor';
			}
			$out[] = $row;
		}
		return array( 'summary' => count( $out ) . ' items', 'items' => $out );
	}

	/* ================================================================ Files */

	public static function list_files( $args ) {
		$dir = NomadSA_Tools::resolve_path( $args['path'] ?? '' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		if ( ! is_dir( $dir ) ) {
			return new WP_Error( 'nomadsa_path', 'Not a folder.' );
		}
		$depth = NomadSA_Tools::int_arg( $args, 'depth', 1, 1, 4 );
		$out   = array();
		$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		$it->setMaxDepth( $depth - 1 );
		foreach ( $it as $f ) {
			$path = wp_normalize_path( $f->getPathname() );
			if ( $f->isLink() || NomadSA_Tools::is_sensitive_path( $path ) ) {
				continue;
			}
			$out[] = array(
				'path'     => NomadSA_Tools::relative( $path ),
				'type'     => $f->isDir() ? 'folder' : 'file',
				'size'     => $f->isDir() ? null : $f->getSize(),
				'modified' => gmdate( 'Y-m-d H:i', $f->getMTime() ),
				'perms'    => substr( sprintf( '%o', $f->getPerms() ), -4 ),
			);
			if ( count( $out ) >= 800 ) {
				break;
			}
		}
		return array( 'summary' => count( $out ) . ' entries', 'entries' => $out, 'truncated' => count( $out ) >= 800 );
	}

	public static function read_file( $args ) {
		$path = NomadSA_Tools::resolve_path( $args['path'] ?? '' );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'nomadsa_path', 'Not a file.' );
		}
		if ( filesize( $path ) > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'nomadsa_size', 'File is larger than 5 MB.' );
		}
		$content = (string) file_get_contents( $path );
		if ( preg_match( '/[\x00-\x08\x0E-\x1F]/', substr( $content, 0, 4000 ) ) ) {
			return new WP_Error( 'nomadsa_binary', 'This looks like a binary file.' );
		}
		$lines = preg_split( '/\r?\n/', $content );
		$start = NomadSA_Tools::int_arg( $args, 'start_line', 1, 1, max( 1, count( $lines ) ) );
		$max   = NomadSA_Tools::int_arg( $args, 'max_lines', 400, 1, 2000 );
		$slice = array_slice( $lines, $start - 1, $max );
		$text  = '';
		foreach ( $slice as $i => $l ) {
			$text .= str_pad( (string) ( $start + $i ), 5, ' ', STR_PAD_LEFT ) . '  ' . $l . "\n";
		}
		$header = NomadSA_Tools::relative( $path ) . ' (' . count( $lines ) . ' lines, modified ' . gmdate( 'Y-m-d H:i', filemtime( $path ) ) . ' UTC, showing ' . $start . ' to ' . ( $start + count( $slice ) - 1 ) . ")\n\n";
		return $header . NomadSA_Tools::redact( $text );
	}

	public static function search_code( $args ) {
		$root = NomadSA_Tools::resolve_path( $args['path'] ?? '@content' );
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$pattern = $args['pattern'];
		if ( '' === $pattern ) {
			return new WP_Error( 'nomadsa_pattern', 'Search pattern cannot be empty.' );
		}
		$is_re   = ! empty( $args['regex'] );
		if ( $is_re ) {
			$re = '~(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=100)' . str_replace( '~', '\~', $pattern ) . '~i';
			if ( false === @preg_match( $re, '' ) ) {
				return new WP_Error( 'nomadsa_regex', 'Invalid regular expression.' );
			}
		}
		$exts = ! empty( $args['extensions'] ) && is_array( $args['extensions'] ) ? array_map( 'strtolower', $args['extensions'] ) : array( 'php', 'js', 'css', 'html', 'twig', 'json', 'txt' );
		$max  = NomadSA_Tools::int_arg( $args, 'max_results', 100, 1, 500 );
		$hits = array();
		$files = 0;
		$it   = is_dir( $root ) ? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) : array( new SplFileInfo( $root ) );
		foreach ( $it as $f ) {
			$path = wp_normalize_path( $f->getPathname() );
			if ( $f->isLink() || ! $f->isFile() || ! in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), $exts, true ) || NomadSA_Tools::is_sensitive_path( $path ) || $f->getSize() > MB_IN_BYTES ) {
				continue;
			}
			if ( ++$files > 20000 ) {
				break;
			}
			$lines = @file( $path );
			if ( ! $lines ) {
				continue;
			}
			foreach ( $lines as $i => $line ) {
				$match = $is_re ? @preg_match( $re, $line ) : ( false !== stripos( $line, $pattern ) );
				if ( $match ) {
					$hits[] = array( 'file' => NomadSA_Tools::relative( $path ), 'line' => $i + 1, 'text' => mb_substr( trim( NomadSA_Tools::redact( $line ) ), 0, 240 ) );
					if ( count( $hits ) >= $max ) {
						break 2;
					}
				}
			}
		}
		return array( 'summary' => count( $hits ) . ' matches in ' . $files . ' files searched', 'matches' => $hits );
	}

	public static function get_activity_log( $args ) {
		global $wpdb;
		$limit = NomadSA_Tools::int_arg( $args, 'limit', 50, 1, 500 );
		$kind  = ( ! empty( $args['kind'] ) && in_array( $args['kind'], array( 'audit', 'fix' ), true ) ) ? $args['kind'] : '';
		if ( $kind ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, created_at, user_id, tool, kind, status, summary FROM %i WHERE kind = %s ORDER BY id DESC LIMIT %d', NomadSA_DB::table( 'log' ), $kind, $limit ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, created_at, user_id, tool, kind, status, summary FROM %i ORDER BY id DESC LIMIT %d', NomadSA_DB::table( 'log' ), $limit ), ARRAY_A );
		}
		return array( 'summary' => count( $rows ) . ' entries (times in UTC)', 'entries' => $rows );
	}
}
