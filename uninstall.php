<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-nomadsa-db.php';
require_once __DIR__ . '/includes/class-nomadsa-wellknown.php';
NomadSA_WellKnown::remove();
NomadSA_DB::uninstall();
