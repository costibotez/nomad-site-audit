<?php
/**
 * Plugin Name:       Nomad Site Audit
 * Plugin URI:        https://github.com/costibotez/nomad-site-audit
 * Description:       Connect your site to Claude as a custom connector to run security, search and health audits and apply the fixes you approve. Every change is previewed first, backed up where possible and logged.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Costin Botez
 * Author URI:        https://www.nomad-developer.co.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nomad-site-audit
 */

defined( 'ABSPATH' ) || exit;

define( 'NOMADSA_VERSION', '1.1.0' );
define( 'NOMADSA_FILE', __FILE__ );
define( 'NOMADSA_DIR', plugin_dir_path( __FILE__ ) );

require_once NOMADSA_DIR . 'includes/class-nomadsa-db.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-settings.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-http.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-oauth.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-wellknown.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-tools.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-audit-tools.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-fix-tools.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-mcp.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-runtime.php';
require_once NOMADSA_DIR . 'includes/class-nomadsa-admin.php';

register_activation_hook( __FILE__, array( 'NomadSA_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'NomadSA_WellKnown', 'remove' ) );

add_action( 'plugins_loaded', function () {
	NomadSA_DB::maybe_upgrade();
	add_action( 'init', array( 'NomadSA_WellKnown', 'maybe_write' ), 20 );
	NomadSA_Runtime::init();
	NomadSA_Admin::init();
	// Requests for our own endpoints are answered before WordPress builds a page.
	add_action( 'init', array( 'NomadSA_HTTP', 'route' ), 1 );
} );
