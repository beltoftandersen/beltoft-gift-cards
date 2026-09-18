<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Repository;

$tag = 'TEST-UPD-' . wp_rand( 1000, 9999 );
$a = Repository::insert( [ 'code' => $tag . '-A', 'initial_amount' => 5, 'balance' => 5, 'source' => 'promotion', 'recipient_email' => 'upd-a@example.test' ] );
$b = Repository::insert( [ 'code' => $tag . '-B', 'initial_amount' => 5, 'balance' => 5, 'source' => 'paid_offline', 'recipient_email' => 'upd-b@example.test' ] );

bgcw_assert_eq( 1, Repository::count_all( [ 'search' => $tag, 'source' => 'promotion' ] ), 'source filter narrows count' );
$rows = Repository::get_all_paginated( [ 'search' => $tag, 'source' => 'paid_offline' ] );
bgcw_assert_eq( $tag . '-B', $rows[0]->code ?? null, 'source filter narrows list' );

bgcw_assert_eq( false, Repository::update( $a, [] ), 'update with no fields fails' );
bgcw_assert_eq( false, Repository::update( $a, [ 'status' => 'nope' ] ), 'update with invalid status fails' );
bgcw_assert_eq( false, Repository::update( $a, [ 'source' => 'nope' ] ), 'update with invalid source fails' );
bgcw_assert_eq( false, Repository::update( $a, [ 'balance' => 999 ] ), 'update ignores non-whitelisted field' );

bgcw_assert( Repository::update( $a, [ 'source' => 'compensation', 'recipient_name' => 'Renamed', 'expires_at' => null, 'status' => 'disabled' ] ), 'update succeeds' );
$fresh = Repository::find( $a );
bgcw_assert_eq( 'compensation', $fresh->source, 'source updated' );
bgcw_assert_eq( 'Renamed', $fresh->recipient_name, 'recipient_name updated' );
bgcw_assert_eq( null, $fresh->expires_at, 'expires_at set to null' );
bgcw_assert_eq( 'disabled', $fresh->status, 'status updated' );
bgcw_assert_eq( 'disabled', Repository::find_by_code( $tag . '-A' )->status, 'code cache invalidated after update' );

Repository::delete( $a );
Repository::delete( $b );
