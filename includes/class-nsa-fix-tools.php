<?php
defined( 'ABSPATH' ) || exit;

/**
 * Tools that change the site. Every callback receives ( $args, $confirm ).
 * Without confirm it returns a preview and changes nothing.
 */
class NSA_Fix_Tools {

	const SITE_SETTINGS = array(
		'blogname'               => 'string',
		'blogdescription'        => 'string',
		'blog_public'            => 'bool',
		'users_can_register'     => 'bool',
		'default_role'           => 'role',
		'default_comment_status' => 'open_closed',
		'default_ping_status'    => 'open_closed',
		'comment_registration'   => 'bool',
		'comment_moderation'     => 'bool',
		'close_comments_for_old_posts' => 'bool',
	);

	const HARDENING = array(
		'disable_xmlrpc'         => 'Switch off XML-RPC, an old remote access method used in password guessing attacks.',
		'disable_file_editor'    => 'Switch off the built in plugin and theme code editor in the dashboard.',
		'hide_wp_version'        => 'Remove the WordPress version from the page source and feeds.',
		'block_user_enumeration' => 'Stop strangers listing usernames through the REST API and ?author= links.',
		'remove_core_info_files' => 'Delete readme.html and license.txt (and again after each WordPress update).',
	);

	public static function register() {
		$c = __CLASS__;

		NSA_Tools::register( 'update_plugins', array(
			'kind'        => 'fix',
			'title'       => 'Update plugins',
			'description' => 'Updates plugins to their latest versions. Afterwards the homepage is loaded to confirm the site still works.',
			'properties'  => array(
				'plugins' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Plugin files or folder slugs, for example contact-form-7. Use ["all"] for every plugin with an update.' ),
			),
			'required'    => array( 'plugins' ),
			'callback'    => array( $c, 'update_plugins' ),
		) );

		NSA_Tools::register( 'update_themes', array(
			'kind'        => 'fix',
			'title'       => 'Update themes',
			'description' => 'Updates themes to their latest versions. Changes made directly to a theme (not a child theme) are lost.',
			'properties'  => array(
				'themes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Theme folder names, or ["all"].' ),
			),
			'required'    => array( 'themes' ),
			'callback'    => array( $c, 'update_themes' ),
		) );

		NSA_Tools::register( 'update_core', array(
			'kind'        => 'fix',
			'title'       => 'Update WordPress',
			'description' => 'Updates WordPress itself to the latest offered version.',
			'destructive' => true,
			'callback'    => array( $c, 'update_core' ),
		) );

		NSA_Tools::register( 'set_plugin_status', array(
			'kind'        => 'fix',
			'title'       => 'Activate or deactivate a plugin',
			'description' => 'Turns a plugin on or off. Settings are kept.',
			'properties'  => array(
				'plugin' => array( 'type' => 'string', 'description' => 'Plugin file or folder slug.' ),
				'active' => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'plugin', 'active' ),
			'callback'    => array( $c, 'set_plugin_status' ),
		) );

		NSA_Tools::register( 'delete_plugin', array(
			'kind'        => 'fix',
			'title'       => 'Delete an inactive plugin',
			'description' => 'Permanently deletes an inactive plugin and its files. The plugin can be reinstalled later but this cannot be undone through the connector.',
			'destructive' => true,
			'properties'  => array( 'plugin' => array( 'type' => 'string', 'description' => 'Plugin file or folder slug.' ) ),
			'required'    => array( 'plugin' ),
			'callback'    => array( $c, 'delete_plugin' ),
		) );

		NSA_Tools::register( 'delete_theme', array(
			'kind'        => 'fix',
			'title'       => 'Delete an unused theme',
			'description' => 'Permanently deletes an installed theme that is not active and is not the parent of the active theme.',
			'destructive' => true,
			'properties'  => array( 'theme' => array( 'type' => 'string', 'description' => 'Theme folder name, for example astra.' ) ),
			'required'    => array( 'theme' ),
			'callback'    => array( $c, 'delete_theme' ),
		) );

		NSA_Tools::register( 'delete_uploads_path', array(
			'kind'        => 'fix',
			'title'       => 'Delete a file or folder in uploads',
			'description' => 'Permanently deletes a file or folder inside wp-content/uploads, for example data left behind by a removed plugin. The preview lists what would be removed. Media files are flagged so they are not removed by mistake. Cannot be undone through the connector.',
			'destructive' => true,
			'properties'  => array( 'path' => array( 'type' => 'string', 'description' => 'Relative to the WordPress root, for example wp-content/uploads/sucuri.' ) ),
			'required'    => array( 'path' ),
			'callback'    => array( $c, 'delete_uploads_path' ),
		) );

		NSA_Tools::register( 'apply_hardening', array(
			'kind'        => 'fix',
			'title'       => 'Apply security hardening',
			'description' => 'Switches security hardening options on or off: ' . implode( ' ', array_map( function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( self::HARDENING ), self::HARDENING ) ),
			'properties'  => array(
				'settings' => array(
					'type'       => 'object',
					'properties' => array_map( function () {
						return array( 'type' => 'boolean' );
					}, self::HARDENING ),
					'description' => 'Options to change, each true or false.',
				),
			),
			'required'    => array( 'settings' ),
			'callback'    => array( $c, 'apply_hardening' ),
		) );

		NSA_Tools::register( 'set_security_headers', array(
			'kind'        => 'fix',
			'title'       => 'Set browser security headers',
			'description' => 'Sends security headers with every page. preset recommended sets safe defaults (HSTS, nosniff, SAMEORIGIN framing, referrer policy, permissions policy). Use custom with headers to set exact values, or off to remove them.',
			'properties'  => array(
				'preset'  => array( 'type' => 'string', 'enum' => array( 'recommended', 'custom', 'off' ) ),
				'headers' => array( 'type' => 'object', 'additionalProperties' => array( 'type' => 'string' ), 'description' => 'For custom: header name to value. An empty value removes that header.' ),
			),
			'required'    => array( 'preset' ),
			'callback'    => array( $c, 'set_security_headers' ),
		) );

		NSA_Tools::register( 'update_seo_meta', array(
			'kind'        => 'fix',
			'title'       => 'Update search title and description',
			'description' => 'Sets the search title, meta description and noindex for a page or post through Yoast SEO or Rank Math. Previous values are backed up.',
			'properties'  => array(
				'post_id'     => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string', 'description' => 'Search title, about 50 to 60 characters.' ),
				'description' => array( 'type' => 'string', 'description' => 'Meta description, about 120 to 155 characters.' ),
				'noindex'     => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( $c, 'update_seo_meta' ),
		) );

		NSA_Tools::register( 'update_image_alt', array(
			'kind'        => 'fix',
			'title'       => 'Update image alt text',
			'description' => 'Sets alt text on media library images (used for accessibility and image search). Previous values are backed up.',
			'properties'  => array(
				'items' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array( 'attachment_id' => array( 'type' => 'integer' ), 'alt' => array( 'type' => 'string' ) ),
						'required'   => array( 'attachment_id', 'alt' ),
					),
				),
			),
			'required'    => array( 'items' ),
			'callback'    => array( $c, 'update_image_alt' ),
		) );

		NSA_Tools::register( 'replace_in_content', array(
			'kind'        => 'fix',
			'title'       => 'Replace text in a page',
			'description' => 'Finds and replaces exact text in a page or post, including Elementor content. Useful for fixing broken links or wording. The previous version is backed up.',
			'properties'  => array(
				'post_id' => array( 'type' => 'integer' ),
				'search'  => array( 'type' => 'string' ),
				'replace' => array( 'type' => 'string' ),
			),
			'required'    => array( 'post_id', 'search', 'replace' ),
			'callback'    => array( $c, 'replace_in_content' ),
		) );

		NSA_Tools::register( 'edit_file', array(
			'kind'        => 'fix',
			'title'       => 'Edit a theme or must-use plugin file',
			'description' => 'Edits a file in wp-content/themes or wp-content/mu-plugins, either by replacing exact text (search and replace) or by writing full content (also creates new files). PHP is syntax checked before saving and the original is backed up. Prefer a child theme over editing a parent theme, since updates overwrite parent theme files.',
			'destructive' => true,
			'properties'  => array(
				'path'    => array( 'type' => 'string', 'description' => 'Relative to the WordPress root.' ),
				'search'  => array( 'type' => 'string', 'description' => 'Exact text to find. Must occur exactly once.' ),
				'replace' => array( 'type' => 'string' ),
				'content' => array( 'type' => 'string', 'description' => 'Full new file content, instead of search and replace.' ),
			),
			'required'    => array( 'path' ),
			'callback'    => array( $c, 'edit_file' ),
		) );

		NSA_Tools::register( 'set_custom_css', array(
			'kind'        => 'fix',
			'title'       => 'Add custom CSS',
			'description' => 'Adds CSS to every page through the connector (separate from the theme). Useful for small visual or speed fixes, for example hiding a loading screen. mode replace or append.',
			'properties'  => array(
				'css'  => array( 'type' => 'string' ),
				'mode' => array( 'type' => 'string', 'enum' => array( 'replace', 'append' ) ),
			),
			'required'    => array( 'css' ),
			'callback'    => array( $c, 'set_custom_css' ),
		) );

		NSA_Tools::register( 'set_site_setting', array(
			'kind'        => 'fix',
			'title'       => 'Change a site setting',
			'description' => 'Changes one of these settings: ' . implode( ', ', array_keys( self::SITE_SETTINGS ) ) . '.',
			'properties'  => array(
				'name'  => array( 'type' => 'string', 'enum' => array_keys( self::SITE_SETTINGS ) ),
				'value' => array( 'type' => array( 'string', 'boolean', 'integer' ) ),
			),
			'required'    => array( 'name', 'value' ),
			'callback'    => array( $c, 'set_site_setting' ),
		) );

		NSA_Tools::register( 'clean_database', array(
			'kind'        => 'fix',
			'title'       => 'Clean the database',
			'description' => 'Removes old revisions (keeping the latest per page), auto drafts, trashed posts, spam and trashed comments and expired temporary data. Choose which with the options; all default to true. This cannot be undone through the connector.',
			'destructive' => true,
			'properties'  => array(
				'revisions'          => array( 'type' => 'boolean' ),
				'keep_revisions'     => array( 'type' => 'integer', 'description' => 'Revisions to keep per page. Default 3.' ),
				'auto_drafts'        => array( 'type' => 'boolean' ),
				'trashed_posts'      => array( 'type' => 'boolean' ),
				'spam_comments'      => array( 'type' => 'boolean' ),
				'expired_transients' => array( 'type' => 'boolean' ),
			),
			'callback'    => array( $c, 'clean_database' ),
		) );

		NSA_Tools::register( 'flush_caches', array(
			'kind'        => 'fix',
			'title'       => 'Clear caches',
			'description' => 'Clears the WordPress object cache and any page cache from SiteGround Optimizer, WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, Autoptimize and Elementor CSS. Safe to run after changes.',
			'callback'    => array( $c, 'flush_caches' ),
		) );

		NSA_Tools::register( 'restore_backup', array(
			'kind'        => 'fix',
			'title'       => 'Undo a change',
			'description' => 'Restores a backup made by the connector before a change (files, search titles, alt text, page content, settings, custom CSS). Find the ID with list_backups.',
			'properties'  => array( 'backup_id' => array( 'type' => 'integer' ) ),
			'required'    => array( 'backup_id' ),
			'callback'    => array( $c, 'restore_backup' ),
		) );
	}

	/* ================================================================ Helpers */

	private static function upgrader_includes() {
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
	}

	private static function filesystem_ready() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( 'direct' !== get_filesystem_method() ) {
			return new WP_Error( 'nsa_fs', 'This server needs FTP details to install updates, so updates cannot run through the connector. Run them from the dashboard instead.' );
		}
		WP_Filesystem();
		return true;
	}

	private static function plugin_file( $id ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		if ( isset( $plugins[ $id ] ) ) {
			return $id;
		}
		foreach ( array_keys( $plugins ) as $file ) {
			if ( dirname( $file ) === $id || basename( $file, '.php' ) === $id ) {
				return $file;
			}
		}
		return null;
	}

	private static function health_check() {
		list( $res, $ms ) = NSA_Tools::fetch( home_url( '/' ) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'detail' => $res->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$fatal = false !== stripos( $body, 'critical error on this website' ) || false !== stripos( $body, 'Fatal error' );
		return array( 'ok' => 200 === $code && ! $fatal, 'status' => $code, 'response_ms' => $ms, 'detail' => $fatal ? 'The homepage shows a PHP error.' : '' );
	}

	private static function editable_path( $relative ) {
		$abs = NSA_Tools::resolve_path( $relative, false );
		if ( is_wp_error( $abs ) ) {
			return $abs;
		}
		$allowed = array( wp_normalize_path( get_theme_root() ) . '/', wp_normalize_path( WPMU_PLUGIN_DIR ) . '/' );
		foreach ( $allowed as $root ) {
			if ( 0 === strpos( $abs, $root ) ) {
				if ( preg_match( '/\.(php\d?|phtml|phar)$/i', $abs ) && 0 === strpos( $abs, wp_normalize_path( wp_get_upload_dir()['basedir'] ) ) ) {
					break;
				}
				return $abs;
			}
		}
		return new WP_Error( 'nsa_path', 'Only files in wp-content/themes and wp-content/mu-plugins can be edited through the connector.' );
	}

	private static function php_syntax_error( $code ) {
		if ( ! defined( 'TOKEN_PARSE' ) ) {
			return '';
		}
		try {
			token_get_all( $code, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			return 'PHP syntax error on line ' . $e->getLine() . ': ' . $e->getMessage();
		}
		return '';
	}

	/* ================================================================ Updates */

	public static function update_plugins( $args, $confirm ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_update_plugins();
		$updates = get_site_transient( 'update_plugins' );
		$avail   = isset( $updates->response ) ? $updates->response : array();
		$all     = get_plugins();
		$wanted  = (array) $args['plugins'];
		$files   = array();
		if ( in_array( 'all', $wanted, true ) ) {
			$files = array_keys( $avail );
		} else {
			foreach ( $wanted as $w ) {
				$f = self::plugin_file( (string) $w );
				if ( ! $f ) {
					return new WP_Error( 'nsa_plugin', 'Plugin not found: ' . $w );
				}
				$files[] = $f;
			}
		}
		$plan = array();
		$skip = array();
		foreach ( $files as $f ) {
			if ( isset( $avail[ $f ] ) && ! empty( $avail[ $f ]->package ) ) {
				$plan[ $f ] = array( 'name' => $all[ $f ]['Name'], 'from' => $all[ $f ]['Version'], 'to' => $avail[ $f ]->new_version );
			} elseif ( isset( $avail[ $f ] ) ) {
				$skip[ $f ] = 'Update needs a licence or manual download';
			} else {
				$skip[ $f ] = 'Already up to date';
			}
		}
		if ( ! $confirm ) {
			return array( 'summary' => count( $plan ) . ' plugins to update', 'updates' => array_values( $plan ), 'skipped' => $skip, 'advice' => 'Make sure a recent full backup exists before updating.' );
		}
		if ( ! $plan ) {
			return array( 'summary' => 'Nothing to update', 'skipped' => $skip );
		}
		$fs = self::filesystem_ready();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		self::upgrader_includes();
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$results  = $upgrader->bulk_upgrade( array_keys( $plan ) );
		wp_clean_plugins_cache();
		$after   = get_plugins();
		$report  = array();
		foreach ( $plan as $f => $p ) {
			$ok       = ! empty( $results[ $f ] ) && ! is_wp_error( $results[ $f ] );
			$report[] = array(
				'name'    => $p['name'],
				'from'    => $p['from'],
				'to'      => $after[ $f ]['Version'] ?? '?',
				'result'  => $ok ? 'updated' : ( is_wp_error( $results[ $f ] ?? null ) ? $results[ $f ]->get_error_message() : 'failed' ),
			);
		}
		$health = self::health_check();
		return array(
			'summary' => count( array_filter( $report, function ( $r ) { return 'updated' === $r['result']; } ) ) . ' of ' . count( $report ) . ' plugins updated; homepage ' . ( $health['ok'] ? 'OK' : 'NEEDS ATTENTION' ),
			'results' => $report,
			'homepage_check' => $health,
			'next'    => 'Run flush_caches so visitors get the updated files.',
		);
	}

	public static function update_themes( $args, $confirm ) {
		wp_update_themes();
		$updates = get_site_transient( 'update_themes' );
		$avail   = isset( $updates->response ) ? $updates->response : array();
		$wanted  = (array) $args['themes'];
		$slugs   = in_array( 'all', $wanted, true ) ? array_keys( $avail ) : array_map( 'sanitize_key', $wanted );
		$plan    = array();
		$skip    = array();
		foreach ( $slugs as $s ) {
			$t = wp_get_theme( $s );
			if ( ! $t->exists() ) {
				return new WP_Error( 'nsa_theme', 'Theme not found: ' . $s );
			}
			if ( ! empty( $avail[ $s ]['package'] ) ) {
				$plan[ $s ] = array( 'name' => $t->get( 'Name' ), 'from' => $t->get( 'Version' ), 'to' => $avail[ $s ]['new_version'] );
			} else {
				$skip[ $s ] = isset( $avail[ $s ] ) ? 'Update needs a licence or manual download' : 'Already up to date';
			}
		}
		if ( ! $confirm ) {
			$warn = array();
			foreach ( array_keys( $plan ) as $s ) {
				if ( $s === get_stylesheet() && ! wp_get_theme()->parent() ) {
					$warn[] = 'The active theme is not a child theme. Any changes made directly to its files will be lost.';
				}
			}
			return array( 'summary' => count( $plan ) . ' themes to update', 'updates' => array_values( $plan ), 'skipped' => $skip, 'warnings' => $warn );
		}
		if ( ! $plan ) {
			return array( 'summary' => 'Nothing to update', 'skipped' => $skip );
		}
		$fs = self::filesystem_ready();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		self::upgrader_includes();
		$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$results  = $upgrader->bulk_upgrade( array_keys( $plan ) );
		$report   = array();
		foreach ( $plan as $s => $p ) {
			wp_clean_themes_cache();
			$report[] = array(
				'name'   => $p['name'],
				'from'   => $p['from'],
				'to'     => wp_get_theme( $s )->get( 'Version' ),
				'result' => ( ! empty( $results[ $s ] ) && ! is_wp_error( $results[ $s ] ) ) ? 'updated' : 'failed',
			);
		}
		$health = self::health_check();
		return array( 'summary' => count( $report ) . ' themes processed; homepage ' . ( $health['ok'] ? 'OK' : 'NEEDS ATTENTION' ), 'results' => $report, 'homepage_check' => $health );
	}

	public static function update_core( $args, $confirm ) {
		global $wp_version;
		require_once ABSPATH . 'wp-admin/includes/update.php';
		wp_version_check( array(), true );
		$updates = get_core_updates();
		$update  = null;
		foreach ( (array) $updates as $u ) {
			if ( 'upgrade' === $u->response ) {
				$update = $u;
				break;
			}
		}
		if ( ! $update ) {
			return array( 'summary' => 'WordPress ' . $wp_version . ' is already the latest version' );
		}
		if ( ! $confirm ) {
			return array( 'summary' => 'Update WordPress from ' . $wp_version . ' to ' . $update->current, 'from' => $wp_version, 'to' => $update->current, 'advice' => 'Make sure a recent full backup exists. Check that plugins are compatible with the new version.' );
		}
		$fs = self::filesystem_ready();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		self::upgrader_includes();
		$from     = $wp_version;
		$upgrader = new Core_Upgrader( new WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->upgrade( $update );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$health = self::health_check();
		return array( 'summary' => 'WordPress updated from ' . $from . ' to ' . $result . '; homepage ' . ( $health['ok'] ? 'OK' : 'NEEDS ATTENTION' ), 'homepage_check' => $health );
	}

	public static function set_plugin_status( $args, $confirm ) {
		$file = self::plugin_file( (string) $args['plugin'] );
		if ( ! $file ) {
			return new WP_Error( 'nsa_plugin', 'Plugin not found.' );
		}
		if ( plugin_basename( NSA_FILE ) === $file ) {
			return new WP_Error( 'nsa_plugin', 'The connector cannot switch itself off. Do that from the Plugins screen.' );
		}
		$want = (bool) $args['active'];
		$is   = is_plugin_active( $file );
		$name = get_plugins()[ $file ]['Name'];
		if ( $want === $is ) {
			return array( 'summary' => $name . ' is already ' . ( $is ? 'active' : 'inactive' ) );
		}
		if ( ! $confirm ) {
			return array( 'summary' => ( $want ? 'Activate ' : 'Deactivate ' ) . $name, 'plugin' => $file );
		}
		if ( $want ) {
			$r = activate_plugin( $file );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		} else {
			deactivate_plugins( $file );
		}
		$health = self::health_check();
		return array( 'summary' => $name . ( $want ? ' activated' : ' deactivated' ) . '; homepage ' . ( $health['ok'] ? 'OK' : 'NEEDS ATTENTION' ), 'homepage_check' => $health );
	}

	public static function delete_plugin( $args, $confirm ) {
		$file = self::plugin_file( (string) $args['plugin'] );
		if ( ! $file ) {
			return new WP_Error( 'nsa_plugin', 'Plugin not found.' );
		}
		if ( is_plugin_active( $file ) ) {
			return new WP_Error( 'nsa_plugin', 'Deactivate the plugin before deleting it.' );
		}
		$name = get_plugins()[ $file ]['Name'];
		if ( ! $confirm ) {
			return array( 'summary' => 'Permanently delete ' . $name, 'plugin' => $file, 'warning' => 'Its files are removed. Some plugins also delete their settings when removed.' );
		}
		$fs = self::filesystem_ready();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$r = delete_plugins( array( $file ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'summary' => $name . ' deleted' );
	}

	public static function delete_theme( $args, $confirm ) {
		$slug  = sanitize_key( (string) $args['theme'] );
		$theme = wp_get_theme( $slug );
		if ( ! $theme->exists() ) {
			return new WP_Error( 'nsa_theme', 'Theme not found: ' . $slug );
		}
		if ( get_stylesheet() === $slug || get_template() === $slug ) {
			return new WP_Error( 'nsa_theme', 'This theme is in use (active theme or its parent) and cannot be deleted.' );
		}
		if ( ! $confirm ) {
			$remaining = array_diff( array_keys( wp_get_themes() ), array( $slug, get_stylesheet(), get_template() ) );
			$notes     = array();
			if ( ! $remaining ) {
				$notes[] = 'This is the last spare theme. Keeping one default theme as a fallback is recommended.';
			}
			return array( 'summary' => 'Permanently delete the theme ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ), 'theme' => $slug, 'notes' => $notes );
		}
		$fs = self::filesystem_ready();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		$r = delete_theme( $slug );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return array( 'summary' => $theme->get( 'Name' ) . ' deleted' );
	}

	public static function delete_uploads_path( $args, $confirm ) {
		$path = NSA_Tools::resolve_path( (string) $args['path'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$uploads = wp_normalize_path( untrailingslashit( realpath( wp_get_upload_dir()['basedir'] ) ) );
		if ( 0 !== strpos( $path, $uploads . '/' ) ) {
			return new WP_Error( 'nsa_path', 'Only files and folders inside wp-content/uploads can be deleted, and never the uploads folder itself.' );
		}
		if ( preg_match( '#^' . preg_quote( $uploads, '#' ) . '/\d{4}(/\d{2})?$#', $path ) ) {
			return new WP_Error( 'nsa_path', 'Year and month media folders cannot be deleted as a whole. Delete media from the Media Library instead.' );
		}
		$files = is_dir( $path ) ? iterator_to_array( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ), false ) : array( new SplFileInfo( $path ) );
		$count = 0;
		$size  = 0;
		$media = 0;
		$list  = array();
		foreach ( $files as $f ) {
			if ( $f->isFile() ) {
				$count++;
				$size += $f->getSize();
				if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|pdf|mp4|mp3|docx?)$/i', $f->getFilename() ) ) {
					$media++;
				}
				if ( count( $list ) < 30 ) {
					$list[] = NSA_Tools::relative( $f->getPathname() );
				}
			}
		}
		if ( ! $confirm ) {
			$notes = array();
			if ( $media ) {
				$notes[] = $media . ' of these look like media or documents. Make sure none are used on the site.';
			}
			return array( 'summary' => 'Permanently delete ' . NSA_Tools::relative( $path ) . ' (' . $count . ' files, ' . size_format( $size ) . ')', 'files' => $list, 'more_files' => max( 0, $count - count( $list ) ), 'notes' => $notes );
		}
		$deleted = 0;
		foreach ( $files as $f ) {
			$p = $f->getPathname();
			if ( $f->isDir() && ! $f->isLink() ) {
				@rmdir( $p );
			} elseif ( @unlink( $p ) ) {
				$deleted++;
			}
		}
		if ( is_dir( $path ) ) {
			@rmdir( $path );
		}
		if ( file_exists( $path ) ) {
			return new WP_Error( 'nsa_delete', 'Deleted ' . $deleted . ' files, but some could not be removed (permissions).' );
		}
		return array( 'summary' => 'Deleted ' . NSA_Tools::relative( $path ) . ' (' . $deleted . ' files)' );
	}

	/* ================================================================ Security settings */

	public static function apply_hardening( $args, $confirm ) {
		$req = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
		$cur = NSA_Settings::runtime();
		$changes = array();
		foreach ( $req as $k => $v ) {
			if ( ! isset( self::HARDENING[ $k ] ) ) {
				return new WP_Error( 'nsa_setting', 'Unknown option: ' . $k );
			}
			if ( (bool) $cur[ $k ] !== (bool) $v ) {
				$changes[ $k ] = array( 'from' => (bool) $cur[ $k ], 'to' => (bool) $v, 'what' => self::HARDENING[ $k ] );
			}
		}
		if ( ! $changes ) {
			return array( 'summary' => 'No changes needed' );
		}
		if ( ! $confirm ) {
			$notes = array();
			if ( ! empty( $changes['disable_xmlrpc']['to'] ) && ( class_exists( 'Jetpack' ) ) ) {
				$notes[] = 'Jetpack is active and needs XML-RPC. Disabling it will break Jetpack.';
			}
			if ( ! empty( $changes['disable_file_editor']['to'] ) && defined( 'DISALLOW_FILE_EDIT' ) ) {
				$notes[] = 'DISALLOW_FILE_EDIT is already defined in wp-config.php, so this option has no extra effect.';
			}
			return array( 'summary' => count( $changes ) . ' hardening changes', 'changes' => $changes, 'notes' => $notes );
		}
		NSA_DB::backup( 'runtime', 'hardening', wp_json_encode( array_intersect_key( $cur, $changes ) ) );
		$new = array();
		foreach ( $changes as $k => $c ) {
			$new[ $k ] = $c['to'];
		}
		NSA_Settings::update_runtime( $new );
		$deleted = array();
		if ( ! empty( $new['remove_core_info_files'] ) ) {
			$deleted = NSA_Runtime::remove_core_info_files();
		}
		return array( 'summary' => 'Applied ' . count( $changes ) . ' hardening changes', 'applied' => $new, 'files_deleted' => $deleted, 'next' => 'Run flush_caches so the changes reach visitors.' );
	}

	public static function recommended_headers() {
		$h = array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
		);
		if ( is_ssl() || 0 === strpos( home_url(), 'https://' ) ) {
			$h['Strict-Transport-Security'] = 'max-age=31536000';
		}
		return $h;
	}

	public static function set_security_headers( $args, $confirm ) {
		$preset = (string) $args['preset'];
		$cur    = (array) NSA_Settings::runtime( 'security_headers' );
		if ( 'recommended' === $preset ) {
			$new = self::recommended_headers();
		} elseif ( 'off' === $preset ) {
			$new = array();
		} elseif ( 'custom' === $preset ) {
			$new = $cur;
			foreach ( (array) ( $args['headers'] ?? array() ) as $name => $value ) {
				$name = preg_replace( '/[^A-Za-z0-9-]/', '', (string) $name );
				$value = str_replace( array( "\r", "\n" ), '', (string) $value );
				if ( '' === $name ) {
					continue;
				}
				if ( '' === $value ) {
					unset( $new[ $name ] );
				} else {
					$new[ $name ] = $value;
				}
			}
		} else {
			return new WP_Error( 'nsa_preset', 'Unknown preset.' );
		}
		if ( ! $confirm ) {
			$notes = array();
			if ( isset( $new['Content-Security-Policy'] ) ) {
				$notes[] = 'A Content-Security-Policy can block scripts, fonts or embeds the site relies on. Test the site carefully after applying.';
			}
			if ( isset( $new['X-Frame-Options'] ) ) {
				$notes[] = 'SAMEORIGIN stops other sites showing these pages inside a frame. Page builders on the same site keep working.';
			}
			return array( 'summary' => count( $new ) . ' security headers', 'current' => $cur, 'new' => $new, 'notes' => $notes );
		}
		NSA_DB::backup( 'runtime', 'security_headers', wp_json_encode( array( 'security_headers' => $cur ) ) );
		NSA_Settings::update_runtime( array( 'security_headers' => $new ) );
		return array( 'summary' => count( $new ) . ' security headers now sent', 'headers' => $new, 'next' => 'Run flush_caches, then check_page to confirm the headers arrive. Some hosts add or strip headers at server level.' );
	}

	public static function set_site_setting( $args, $confirm ) {
		$name = (string) $args['name'];
		if ( ! isset( self::SITE_SETTINGS[ $name ] ) ) {
			return new WP_Error( 'nsa_setting', 'This setting cannot be changed through the connector.' );
		}
		$type  = self::SITE_SETTINGS[ $name ];
		$value = $args['value'];
		switch ( $type ) {
			case 'bool':
				$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
				break;
			case 'role':
				$value = sanitize_key( $value );
				if ( ! get_role( $value ) ) {
					return new WP_Error( 'nsa_setting', 'Unknown role.' );
				}
				if ( in_array( $value, array( 'administrator', 'editor' ), true ) ) {
					return new WP_Error( 'nsa_setting', 'For safety the default role for new users cannot be administrator or editor.' );
				}
				break;
			case 'open_closed':
				$value = in_array( $value, array( 'open', 'closed' ), true ) ? $value : ( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ? 'open' : 'closed' );
				break;
			default:
				$value = sanitize_text_field( (string) $value );
		}
		$cur = get_option( $name );
		if ( (string) $cur === (string) $value ) {
			return array( 'summary' => $name . ' is already ' . $value );
		}
		if ( ! $confirm ) {
			$note = 'blog_public' === $name && ! $value ? 'This hides the whole site from search engines.' : '';
			return array( 'summary' => 'Change ' . $name, 'from' => $cur, 'to' => $value, 'note' => $note );
		}
		NSA_DB::backup( 'option', $name, wp_json_encode( array( 'value' => $cur ) ) );
		update_option( $name, $value );
		return array( 'summary' => $name . ' changed from ' . $cur . ' to ' . $value );
	}

	/* ================================================================ Content */

	public static function update_seo_meta( $args, $confirm ) {
		$post = get_post( (int) $args['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'nsa_post', 'Post not found.' );
		}
		$plugin = defined( 'WPSEO_VERSION' ) ? 'yoast' : ( ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) ? 'rankmath' : '' );
		if ( ! $plugin ) {
			return new WP_Error( 'nsa_seo', 'No supported search optimisation plugin (Yoast SEO or Rank Math) is active.' );
		}
		$keys = 'yoast' === $plugin
			? array( 'title' => '_yoast_wpseo_title', 'description' => '_yoast_wpseo_metadesc', 'noindex' => '_yoast_wpseo_meta-robots-noindex' )
			: array( 'title' => 'rank_math_title', 'description' => 'rank_math_description', 'noindex' => 'rank_math_robots' );
		$current = NSA_Audit_Tools::seo_meta( $post->ID );
		$changes = array();
		foreach ( array( 'title', 'description', 'noindex' ) as $f ) {
			if ( array_key_exists( $f, $args ) ) {
				$val = 'noindex' === $f ? (bool) $args[ $f ] : sanitize_text_field( (string) $args[ $f ] );
				if ( $val !== $current[ $f ] ) {
					$changes[ $f ] = array( 'from' => $current[ $f ], 'to' => $val );
				}
			}
		}
		if ( ! $changes ) {
			return array( 'summary' => 'No changes needed' );
		}
		if ( ! $confirm ) {
			$notes = array();
			if ( isset( $changes['title'] ) && mb_strlen( $changes['title']['to'] ) > 60 ) {
				$notes[] = 'Title is ' . mb_strlen( $changes['title']['to'] ) . ' characters and may be cut off in Google.';
			}
			if ( isset( $changes['description'] ) && mb_strlen( $changes['description']['to'] ) > 160 ) {
				$notes[] = 'Description is ' . mb_strlen( $changes['description']['to'] ) . ' characters and may be cut off in Google.';
			}
			if ( ! empty( $changes['noindex']['to'] ) ) {
				$notes[] = 'noindex removes this page from Google.';
			}
			return array( 'summary' => 'Update search settings for "' . $post->post_title . '"', 'url' => get_permalink( $post ), 'changes' => $changes, 'notes' => $notes );
		}
		$backup = array();
		foreach ( $keys as $f => $k ) {
			$backup[ $k ] = get_post_meta( $post->ID, $k, true );
		}
		NSA_DB::backup( 'post_meta', 'post:' . $post->ID, wp_json_encode( $backup ) );
		foreach ( $changes as $f => $c ) {
			if ( 'noindex' === $f ) {
				if ( 'yoast' === $plugin ) {
					$c['to'] ? update_post_meta( $post->ID, $keys[ $f ], '1' ) : delete_post_meta( $post->ID, $keys[ $f ] );
				} else {
					$robots = array_diff( (array) get_post_meta( $post->ID, 'rank_math_robots', true ), array( 'index', 'noindex' ) );
					$robots[] = $c['to'] ? 'noindex' : 'index';
					update_post_meta( $post->ID, 'rank_math_robots', array_values( $robots ) );
				}
			} else {
				update_post_meta( $post->ID, $keys[ $f ], $c['to'] );
			}
		}
		if ( 'yoast' === $plugin ) {
			do_action( 'wpseo_save_postdata', $post->ID );
		}
		clean_post_cache( $post->ID );
		return array( 'summary' => 'Search settings updated for "' . $post->post_title . '"', 'changes' => $changes );
	}

	public static function update_image_alt( $args, $confirm ) {
		$items = (array) $args['items'];
		if ( count( $items ) > 200 ) {
			return new WP_Error( 'nsa_items', 'At most 200 images at a time.' );
		}
		$plan = array();
		foreach ( $items as $it ) {
			$id = (int) ( $it['attachment_id'] ?? 0 );
			$p  = get_post( $id );
			if ( ! $p || 'attachment' !== $p->post_type ) {
				return new WP_Error( 'nsa_attachment', 'Not a media item: ' . $id );
			}
			$plan[ $id ] = array( 'file' => basename( (string) get_attached_file( $id ) ), 'from' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ), 'to' => sanitize_text_field( (string) ( $it['alt'] ?? '' ) ) );
		}
		if ( ! $confirm ) {
			return array( 'summary' => 'Set alt text on ' . count( $plan ) . ' images', 'changes' => $plan, 'note' => 'Images placed in Elementor or older posts may keep their own alt text in the page itself; replace_in_content can update those.' );
		}
		$backup = array();
		foreach ( $plan as $id => $c ) {
			$backup[ $id ] = $c['from'];
		}
		NSA_DB::backup( 'image_alt', 'attachments', wp_json_encode( $backup ) );
		foreach ( $plan as $id => $c ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $c['to'] );
		}
		return array( 'summary' => 'Alt text updated on ' . count( $plan ) . ' images' );
	}

	private static function replace_recursive( &$data, $search, $replace, &$count ) {
		if ( is_array( $data ) ) {
			foreach ( $data as &$v ) {
				self::replace_recursive( $v, $search, $replace, $count );
			}
			unset( $v );
		} elseif ( is_string( $data ) && false !== strpos( $data, $search ) ) {
			$n     = 0;
			$data  = str_replace( $search, $replace, $data, $n );
			$count += $n;
		}
	}

	public static function replace_in_content( $args, $confirm ) {
		$post = get_post( (int) $args['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'nsa_post', 'Post not found.' );
		}
		$search  = (string) $args['search'];
		$replace = (string) $args['replace'];
		if ( '' === $search ) {
			return new WP_Error( 'nsa_search', 'Search text cannot be empty.' );
		}
		$in_content = substr_count( $post->post_content, $search );
		$el_raw     = get_post_meta( $post->ID, '_elementor_data', true );
		$el_data    = $el_raw ? json_decode( is_string( $el_raw ) ? $el_raw : wp_json_encode( $el_raw ), true ) : null;
		$in_el      = 0;
		if ( is_array( $el_data ) ) {
			$copy = $el_data;
			self::replace_recursive( $copy, $search, $replace, $in_el );
		}
		if ( 0 === $in_content + $in_el ) {
			return new WP_Error( 'nsa_search', 'The text was not found in this page.' );
		}
		if ( ! $confirm ) {
			$pos = strpos( $post->post_content, $search );
			return array(
				'summary'          => 'Replace ' . ( $in_content + $in_el ) . ' occurrences on "' . $post->post_title . '"',
				'in_editor'        => $in_content,
				'in_elementor'     => $in_el,
				'context'          => false !== $pos ? mb_substr( $post->post_content, max( 0, $pos - 80 ), strlen( $search ) + 160 ) : '',
			);
		}
		NSA_DB::backup( 'post', 'post:' . $post->ID, wp_json_encode( array( 'post_content' => $post->post_content, '_elementor_data' => $el_raw ) ) );
		if ( $in_content ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_content' => str_replace( $search, $replace, $post->post_content ) ) );
		}
		if ( $in_el ) {
			$n = 0;
			self::replace_recursive( $el_data, $search, $replace, $n );
			update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $el_data ) ) );
			delete_post_meta( $post->ID, '_elementor_css' );
			delete_post_meta( $post->ID, '_elementor_element_cache' );
		}
		clean_post_cache( $post->ID );
		return array( 'summary' => 'Replaced ' . ( $in_content + $in_el ) . ' occurrences on "' . $post->post_title . '"', 'next' => 'Run flush_caches so visitors see the change.' );
	}

	/* ================================================================ Files and CSS */

	public static function edit_file( $args, $confirm ) {
		$path = self::editable_path( (string) $args['path'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$exists = file_exists( $path );
		$old    = $exists ? (string) file_get_contents( $path ) : '';
		if ( isset( $args['content'] ) ) {
			$new = (string) $args['content'];
		} elseif ( isset( $args['search'] ) ) {
			if ( ! $exists ) {
				return new WP_Error( 'nsa_file', 'File does not exist.' );
			}
			$count = substr_count( $old, (string) $args['search'] );
			if ( 1 !== $count ) {
				return new WP_Error( 'nsa_file', 'The search text must occur exactly once, it occurs ' . $count . ' times. Include more surrounding lines.' );
			}
			$new = str_replace( (string) $args['search'], (string) ( $args['replace'] ?? '' ), $old );
		} else {
			return new WP_Error( 'nsa_file', 'Provide either content, or search and replace.' );
		}
		$is_php = (bool) preg_match( '/\.php$/i', $path );
		if ( $is_php ) {
			$err = self::php_syntax_error( $new );
			if ( $err ) {
				return new WP_Error( 'nsa_syntax', $err . '. Nothing was saved.' );
			}
		}
		$rel = NSA_Tools::relative( $path );
		if ( ! $confirm ) {
			$notes = array();
			$parent = wp_normalize_path( get_template_directory() );
			if ( get_stylesheet() !== get_template() && 0 === strpos( $path, $parent . '/' ) ) {
				$notes[] = 'This is the parent theme. Changes are lost when the theme updates. Use the child theme instead.';
			} elseif ( get_stylesheet() === get_template() && 0 === strpos( $path, $parent . '/' ) ) {
				$notes[] = 'The active theme has no child theme, so changes here are lost on the next theme update.';
			}
			return array(
				'summary' => ( $exists ? 'Edit ' : 'Create ' ) . $rel,
				'diff'    => self::simple_diff( $old, $new ),
				'notes'   => $notes,
			);
		}
		if ( ! $exists && ! is_dir( dirname( $path ) ) ) {
			wp_mkdir_p( dirname( $path ) );
		}
		if ( ! is_writable( $exists ? $path : dirname( $path ) ) ) {
			return new WP_Error( 'nsa_file', 'The file is not writable by WordPress.' );
		}
		$backup_id = NSA_DB::backup( 'file', $rel, $exists ? $old : '' );
		if ( false === file_put_contents( $path, $new ) ) {
			return new WP_Error( 'nsa_file', 'Could not write the file.' );
		}
		if ( function_exists( 'opcache_invalidate' ) ) {
			@opcache_invalidate( $path, true );
		}
		$health = $is_php ? self::health_check() : array( 'ok' => true );
		if ( $is_php && ! $health['ok'] ) {
			// Roll back straight away if the site broke.
			if ( $exists ) {
				file_put_contents( $path, $old );
			} else {
				@unlink( $path );
			}
			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $path, true );
			}
			return new WP_Error( 'nsa_rollback', 'The homepage stopped working after the change, so it was rolled back automatically. ' . ( $health['detail'] ?? '' ) );
		}
		return array( 'summary' => ( $exists ? 'Edited ' : 'Created ' ) . $rel, 'backup_id' => $backup_id, 'homepage_check' => $health );
	}

	private static function simple_diff( $old, $new ) {
		$a = preg_split( '/\r?\n/', $old );
		$b = preg_split( '/\r?\n/', $new );
		$start = 0;
		while ( $start < count( $a ) && $start < count( $b ) && $a[ $start ] === $b[ $start ] ) {
			$start++;
		}
		$ea = count( $a ) - 1;
		$eb = count( $b ) - 1;
		while ( $ea >= $start && $eb >= $start && $a[ $ea ] === $b[ $eb ] ) {
			$ea--;
			$eb--;
		}
		$out = array( '@@ line ' . ( $start + 1 ) . ' @@' );
		for ( $i = max( 0, $start - 3 ); $i < $start; $i++ ) {
			$out[] = '  ' . $a[ $i ];
		}
		for ( $i = $start; $i <= $ea; $i++ ) {
			$out[] = '- ' . $a[ $i ];
		}
		for ( $i = $start; $i <= $eb; $i++ ) {
			$out[] = '+ ' . $b[ $i ];
		}
		for ( $i = $ea + 1; $i < min( count( $a ), $ea + 4 ); $i++ ) {
			$out[] = '  ' . $a[ $i ];
		}
		$text = implode( "\n", $out );
		return mb_strlen( $text ) > 12000 ? mb_substr( $text, 0, 12000 ) . "\n... (diff truncated)" : NSA_Tools::redact( $text );
	}

	public static function set_custom_css( $args, $confirm ) {
		$css = wp_strip_all_tags( (string) $args['css'] );
		if ( false !== stripos( $css, '</style' ) ) {
			return new WP_Error( 'nsa_css', 'Invalid CSS.' );
		}
		$cur = (string) NSA_Settings::runtime( 'custom_css' );
		$new = ( isset( $args['mode'] ) && 'append' === $args['mode'] ) ? trim( $cur . "\n" . $css ) : trim( $css );
		if ( ! $confirm ) {
			return array( 'summary' => 'Custom CSS ' . ( strlen( $new ) ) . ' characters', 'current' => $cur, 'new' => $new );
		}
		NSA_DB::backup( 'runtime', 'custom_css', wp_json_encode( array( 'custom_css' => $cur ) ) );
		NSA_Settings::update_runtime( array( 'custom_css' => $new ) );
		return array( 'summary' => 'Custom CSS saved', 'next' => 'Run flush_caches, then check the page.' );
	}

	/* ================================================================ Maintenance */

	public static function clean_database( $args, $confirm ) {
		global $wpdb;
		$opt = function ( $k ) use ( $args ) {
			return ! isset( $args[ $k ] ) || (bool) $args[ $k ];
		};
		$keep = NSA_Tools::int_arg( $args, 'keep_revisions', 3, 0, 50 );

		$rev_ids = array();
		if ( $opt( 'revisions' ) ) {
			$parents = $wpdb->get_col( "SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'revision'" );
			foreach ( $parents as $parent ) {
				$ids     = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_parent = %d ORDER BY post_date DESC", $parent ) );
				$rev_ids = array_merge( $rev_ids, array_slice( $ids, $keep ) );
			}
		}
		$plan = array(
			'revisions'          => count( $rev_ids ),
			'auto_drafts'        => $opt( 'auto_drafts' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ) : 0,
			'trashed_posts'      => $opt( 'trashed_posts' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ) : 0,
			'spam_comments'      => $opt( 'spam_comments' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ) : 0,
			'expired_transients' => $opt( 'expired_transients' ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) ) : 0,
		);
		if ( ! $confirm ) {
			return array( 'summary' => array_sum( $plan ) . ' items to remove', 'remove' => $plan, 'keep_revisions_per_page' => $keep, 'warning' => 'Removed items cannot be restored through the connector. Trashed pages are deleted permanently.' );
		}
		foreach ( $rev_ids as $id ) {
			wp_delete_post_revision( (int) $id );
		}
		if ( $plan['auto_drafts'] ) {
			foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ) as $id ) {
				wp_delete_post( (int) $id, true );
			}
		}
		if ( $plan['trashed_posts'] ) {
			foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'trash'" ) as $id ) {
				wp_delete_post( (int) $id, true );
			}
		}
		if ( $plan['spam_comments'] ) {
			foreach ( $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved IN ('spam','trash')" ) as $id ) {
				wp_delete_comment( (int) $id, true );
			}
		}
		if ( $plan['expired_transients'] ) {
			delete_expired_transients( true );
		}
		return array( 'summary' => 'Removed ' . array_sum( $plan ) . ' items', 'removed' => $plan );
	}

	public static function flush_caches( $args, $confirm ) {
		$found = array( 'WordPress object cache' );
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			$found[] = 'SiteGround Optimizer';
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			$found[] = 'WP Rocket';
		}
		if ( defined( 'LSCWP_V' ) ) {
			$found[] = 'LiteSpeed Cache';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			$found[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			$found[] = 'WP Super Cache';
		}
		if ( class_exists( 'autoptimizeCache' ) ) {
			$found[] = 'Autoptimize';
		}
		if ( did_action( 'elementor/loaded' ) ) {
			$found[] = 'Elementor CSS files';
		}
		if ( ! $confirm ) {
			return array( 'summary' => 'Clear ' . implode( ', ', $found ), 'caches' => $found );
		}
		wp_cache_flush();
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}
		if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		return array( 'summary' => 'Cleared ' . implode( ', ', $found ) );
	}

	public static function restore_backup( $args, $confirm ) {
		$b = NSA_DB::get_backup( (int) $args['backup_id'] );
		if ( ! $b ) {
			return new WP_Error( 'nsa_backup', 'Backup not found.' );
		}
		if ( ! $confirm ) {
			return array( 'summary' => 'Restore ' . $b['kind'] . ' backup of ' . $b['target'] . ' from ' . $b['created_at'] . ' UTC', 'kind' => $b['kind'], 'target' => $b['target'] );
		}
		$data = json_decode( $b['content'], true );
		switch ( $b['kind'] ) {
			case 'file':
				$path = self::editable_path( $b['target'] );
				if ( is_wp_error( $path ) ) {
					return $path;
				}
				NSA_DB::backup( 'file', $b['target'], file_exists( $path ) ? file_get_contents( $path ) : '' );
				if ( '' === $b['content'] ) {
					@unlink( $path ); // The file did not exist before.
				} else {
					file_put_contents( $path, $b['content'] );
				}
				if ( function_exists( 'opcache_invalidate' ) ) {
					@opcache_invalidate( $path, true );
				}
				break;
			case 'post_meta':
				$id = (int) substr( $b['target'], 5 );
				foreach ( (array) $data as $k => $v ) {
					( '' === $v || null === $v ) ? delete_post_meta( $id, $k ) : update_post_meta( $id, $k, $v );
				}
				clean_post_cache( $id );
				break;
			case 'post':
				$id = (int) substr( $b['target'], 5 );
				wp_update_post( array( 'ID' => $id, 'post_content' => $data['post_content'] ) );
				if ( ! empty( $data['_elementor_data'] ) ) {
					update_post_meta( $id, '_elementor_data', wp_slash( is_string( $data['_elementor_data'] ) ? $data['_elementor_data'] : wp_json_encode( $data['_elementor_data'] ) ) );
					delete_post_meta( $id, '_elementor_css' );
				}
				clean_post_cache( $id );
				break;
			case 'image_alt':
				foreach ( (array) $data as $id => $alt ) {
					update_post_meta( (int) $id, '_wp_attachment_image_alt', $alt );
				}
				break;
			case 'option':
				if ( ! isset( self::SITE_SETTINGS[ $b['target'] ] ) ) {
					return new WP_Error( 'nsa_backup', 'This setting cannot be restored through the connector.' );
				}
				update_option( $b['target'], $data['value'] );
				break;
			case 'runtime':
				NSA_Settings::update_runtime( (array) $data );
				break;
			default:
				return new WP_Error( 'nsa_backup', 'Unknown backup type.' );
		}
		return array( 'summary' => 'Restored ' . $b['target'] . ' to the version from ' . $b['created_at'] . ' UTC', 'next' => 'Run flush_caches.' );
	}
}
