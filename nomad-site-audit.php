<?php
/**
 * Plugin Name:       Nomad Site Audit
 * Plugin URI:        https://github.com/costibotez/nomad-site-audit
 * Description:       Audit WordPress, track findings and performance, and connect to Codex or Claude for read-only audits.
 * Version:           1.2.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Costin Botez
 * Author URI:        https://www.nomad-developer.co.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nomad-site-audit
 */

defined( 'ABSPATH' ) || exit;

define( 'NOMADSA_VERSION', '1.2.0' );
define( 'NOMADSA_FILE', __FILE__ );
define( 'NOMADSA_DIR', plugin_dir_path( __FILE__ ) );

require_once NOMADSA_DIR . 'includes/class-nomadsa-db.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-settings.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-http.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-oauth.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-wellknown.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-tools.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-audit-tools.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-mcp.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-runtime.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-admin.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-audit-history.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-performance.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-audit-schedule.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-audit-admin.php';

register_activation_hook( __FILE__, function () {
	NomadSA_DB::install();
	NomadSA_Audit_Schedule::sync();
} );
register_deactivation_hook( __FILE__, array( 'NomadSA_Audit_Schedule', 'deactivate' ) );

add_action( 'plugins_loaded', function () {
	NomadSA_DB::maybe_upgrade();
	NomadSA_Runtime::init();
	NomadSA_Admin::init();
	NomadSA_Audit_Admin::init();
	NomadSA_Audit_Schedule::init();
	// Requests for our own endpoints are answered before WordPress builds a page.
	add_action( 'init', array( 'NomadSA_HTTP', 'route' ), 1 );
} );
