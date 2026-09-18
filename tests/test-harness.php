<?php
require_once __DIR__ . '/bootstrap.php';

bgcw_assert( class_exists( 'Bgcw\\Plugin' ), 'plugin is loaded' );
bgcw_assert( class_exists( 'WooCommerce' ), 'WooCommerce is loaded' );
bgcw_assert( bgcw_test_admin_id() > 0, 'an administrator exists for REST tests' );
