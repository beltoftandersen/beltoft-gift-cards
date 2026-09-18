<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Source;
use Bgcw\GiftCard\Repository;

global $wpdb;

bgcw_assert( class_exists( Source::class ), 'Source class exists' );
bgcw_assert_eq( [ 'order', 'paid_offline', 'promotion', 'compensation' ], Source::all(), 'all sources' );
bgcw_assert_eq( [ 'paid_offline', 'promotion', 'compensation' ], Source::manual_sources(), 'manual sources' );
bgcw_assert( Source::is_paid( 'order' ) && Source::is_paid( 'paid_offline' ), 'order + paid_offline are paid' );
bgcw_assert( ! Source::is_paid( 'promotion' ) && ! Source::is_paid( 'compensation' ), 'promotion + compensation are free' );
bgcw_assert( Source::is_valid( 'promotion' ) && ! Source::is_valid( 'bogus' ), 'is_valid' );
bgcw_assert( '' !== Source::label( 'compensation' ), 'label is non-empty' );

// Column exists.
$col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$wpdb->prefix}bgcw_gift_cards LIKE %s", 'source' ) );
bgcw_assert_eq( 'source', $col, 'source column exists' );
bgcw_assert_eq( '1.3', get_option( 'bgcw_db_version' ), 'db version is 1.3' );

// Insert stores source; default is promotion.
$id1 = Repository::insert( [ 'code' => 'TEST-SRC-' . wp_rand( 1000, 9999 ), 'initial_amount' => 5, 'balance' => 5, 'source' => 'paid_offline' ] );
$id2 = Repository::insert( [ 'code' => 'TEST-SRC-' . wp_rand( 1000, 9999 ), 'initial_amount' => 5, 'balance' => 5 ] );
bgcw_assert_eq( 'paid_offline', Repository::find( $id1 )->source, 'insert stores explicit source' );
bgcw_assert_eq( 'promotion', Repository::find( $id2 )->source, 'insert defaults source to promotion' );

// Cleanup any leftover legacy rows from prior test runs.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bgcw_gift_cards WHERE code LIKE %s", 'TEST-LEG-%' ) );

// Activation-path regression: legacy rows from pre-migration must be backfilled.
update_option( 'bgcw_db_version', '1.2' );
$inserted = $wpdb->insert(
	$wpdb->prefix . 'bgcw_gift_cards',
	[
		'code'            => 'TEST-LEG-' . wp_rand( 1000, 9999 ),
		'initial_amount'  => 1,
		'balance'         => 1,
		'currency'        => 'EUR',
		'status'          => 'active',
		'source'          => '',
	],
	[ '%s', '%f', '%f', '%s', '%s', '%s' ]
);
$legacy_id = (int) $wpdb->insert_id;
bgcw_assert( false !== $inserted && $legacy_id > 0, 'legacy row inserted' );
Bgcw\Support\Installer::activate();
bgcw_assert_eq( '1.3', get_option( 'bgcw_db_version' ), 'activation updates db version to 1.3' );
$legacy_row = $wpdb->get_row( $wpdb->prepare( "SELECT source FROM {$wpdb->prefix}bgcw_gift_cards WHERE id = %d", $legacy_id ) );
bgcw_assert_eq( 'promotion', $legacy_row->source, 'legacy row backfilled to promotion' );
Repository::delete( $legacy_id );

// No legacy rows left un-backfilled.
$empty = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bgcw_gift_cards WHERE source = ''" );
bgcw_assert_eq( 0, $empty, 'no rows with empty source after activation migration' );

Repository::delete( $id1 );
Repository::delete( $id2 );
