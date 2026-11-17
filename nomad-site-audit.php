<?php
/**
 * Plugin Name:       Nomad Site Audit
 * Plugin URI:        https://nomad-developer.co.uk/
 * Description:       Lets Claude connect to this site as a custom connector to run security, search and health audits and, when allowed, apply fixes. Every change is previewed first, backed up where possible and logged.
 * Version:           1.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Nomad Developer
 * Author URI:        https://nomad-developer.co.uk/
 * License:           GPL-2.0-or-later
 * Text Domain:       nomad-site-audit
 */

defined( 'ABSPATH' ) || exit;

define( 'NSA_VERSION', '1.0.2' );
define( 'NSA_FILE', __FILE__ );
define( 'NSA_DIR', plugin_dir_path( __FILE__ ) );

require_once NSA_DIR . 'includes/class-nsa-db.php';
require_once NSA_DIR . 'includes/class-nsa-settings.php';
require_once NSA_DIR . 'includes/class-nsa-http.php';
require_once NSA_DIR . 'includes/class-nsa-oauth.php';
require_once NSA_DIR . 'includes/class-nsa-wellknown.php';
require_once NSA_DIR . 'includes/class-nsa-tools.php';
require_once NSA_DIR . 'includes/class-nsa-audit-tools.php';
require_once NSA_DIR . 'includes/class-nsa-fix-tools.php';
require_once NSA_DIR . 'includes/class-nsa-mcp.php';
require_once NSA_DIR . 'includes/class-nsa-runtime.php';
require_once NSA_DIR . 'includes/class-nsa-admin.php';

register_activation_hook( __FILE__, array( 'NSA_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'NSA_WellKnown', 'remove' ) );

add_action( 'plugins_loaded', function () {
	NSA_DB::maybe_upgrade();
	add_action( 'init', array( 'NSA_WellKnown', 'maybe_write' ), 20 );
	NSA_Runtime::init();
	NSA_Admin::init();
	// Requests for our own endpoints are answered before WordPress builds a page.
	add_action( 'init', array( 'NSA_HTTP', 'route' ), 1 );
} );
