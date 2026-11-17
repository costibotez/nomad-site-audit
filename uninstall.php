<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-nsa-db.php';
require_once __DIR__ . '/includes/class-nsa-wellknown.php';
NSA_WellKnown::remove();
NSA_DB::uninstall();
