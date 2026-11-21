<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-nomadsa-db.php';
require_once __DIR__ . '/includes/class-nomadsa-audit-schedule.php';
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $nomadsa_site_id ) {
		switch_to_blog( $nomadsa_site_id );
		NomadSA_Audit_Schedule::deactivate();
		NomadSA_DB::uninstall();
		restore_current_blog();
	}
} else {
	NomadSA_Audit_Schedule::deactivate();
	NomadSA_DB::uninstall();
}
