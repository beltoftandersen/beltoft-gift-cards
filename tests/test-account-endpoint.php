<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\Frontend\AccountEndpoint;

global $wp_rewrite;

$option   = AccountEndpoint::OPTION;
$original = get_option( $option, null );
bgcw_test_register_cleanup( function () use ( $option, $original ) {
	if ( null === $original ) {
		delete_option( $option );
	} else {
		update_option( $option, $original );
	}
	update_option( 'woocommerce_queue_flush_rewrite_rules', 'yes' );
} );

$account = wc_get_page_permalink( 'myaccount' );
$has_rule = function ( $slug ) use ( $wp_rewrite ) {
	foreach ( array_keys( (array) $wp_rewrite->wp_rewrite_rules() ) as $regex ) {
		if ( false !== strpos( $regex, '/' . $slug . '(/(.*))?/?$' ) ) {
			return true;
		}
	}
	return false;
};

// Default slug when nothing is set.
delete_option( $option );
bgcw_assert_eq( 'gift-cards', AccountEndpoint::slug(), 'default slug is gift-cards' );
bgcw_assert_eq( 'gift-cards', WC()->query->get_query_vars()['gift-cards'] ?? null, 'gift-cards key registered in WooCommerce query vars' );
bgcw_assert_eq( trailingslashit( $account ) . 'gift-cards/', wc_get_account_endpoint_url( 'gift-cards' ), 'default account URL' );

// Configured slug: URL and query var follow it; the rewrite flush is queued for the next request.
update_option( 'woocommerce_queue_flush_rewrite_rules', 'no' );
update_option( $option, 'cartoes-presente' );
bgcw_assert_eq( trailingslashit( $account ) . 'cartoes-presente/', wc_get_account_endpoint_url( 'gift-cards' ), 'account URL uses the configured slug' );
bgcw_assert_eq( trailingslashit( $account ) . 'cartoes-presente/2/', wc_get_endpoint_url( 'gift-cards', '2', $account ), 'endpoint URL with a value uses the configured slug' );
bgcw_assert_eq( 'yes', get_option( 'woocommerce_queue_flush_rewrite_rules' ), 'changing the slug queues a rewrite flush' );

// What the next request does: WooCommerce registers its endpoints, then runs the queued flush.
WC()->query->add_endpoints();
flush_rewrite_rules( false );
bgcw_assert( $has_rule( 'cartoes-presente' ), 'rebuilt rewrite rules contain the configured slug' );

// Back to the default via an empty value or by deleting the option.
update_option( 'woocommerce_queue_flush_rewrite_rules', 'no' );
update_option( $option, '' );
bgcw_assert_eq( 'gift-cards', AccountEndpoint::slug(), 'empty option falls back to the default slug' );
bgcw_assert_eq( 'yes', get_option( 'woocommerce_queue_flush_rewrite_rules' ), 'clearing the slug queues a rewrite flush' );
update_option( $option, 'cartoes-presente' );
update_option( 'woocommerce_queue_flush_rewrite_rules', 'no' );
delete_option( $option );
bgcw_assert_eq( trailingslashit( $account ) . 'gift-cards/', wc_get_account_endpoint_url( 'gift-cards' ), 'deleting the option restores the default URL' );
bgcw_assert_eq( 'yes', get_option( 'woocommerce_queue_flush_rewrite_rules' ), 'deleting the option queues a rewrite flush' );

// Settings field sits inside WooCommerce's Account endpoints section.
$settings = apply_filters(
	'woocommerce_get_settings_advanced',
	[
		[ 'id' => 'woocommerce_logout_endpoint', 'type' => 'text' ],
		[ 'id' => 'account_endpoint_options', 'type' => 'sectionend' ],
	],
	''
);
$ids = wp_list_pluck( $settings, 'id' );
$pos = array_search( $option, $ids, true );
bgcw_assert( false !== $pos && $pos > array_search( 'woocommerce_logout_endpoint', $ids, true ) && $pos < array_search( 'account_endpoint_options', $ids, true ), 'settings field is added after WooCommerce endpoints, before the section end' );
bgcw_assert_eq( 'gift-cards', $settings[ $pos ]['default'] ?? null, 'settings field defaults to gift-cards' );
$other = apply_filters( 'woocommerce_get_settings_advanced', [ [ 'id' => 'account_endpoint_options', 'type' => 'sectionend' ] ], 'features' );
bgcw_assert( ! in_array( $option, wp_list_pluck( $other, 'id' ), true ), 'field is not added to other Advanced sections' );

// Saving through WooCommerce settings sanitizes the slug.
WC_Admin_Settings::save_fields( [ $settings[ $pos ] ], [ $option => ' Cartões Presente ' ] );
bgcw_assert_eq( 'cartoes-presente', get_option( $option ), 'settings save stores a sanitized slug' );
WC_Admin_Settings::save_fields( [ $settings[ $pos ] ], [ $option => '' ] );
bgcw_assert_eq( 'gift-cards', get_option( $option ), 'settings save of an empty value stores the default' );
