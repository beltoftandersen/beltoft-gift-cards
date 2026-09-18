<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;
use Bgcw\GiftCard\TransactionNote;

function bgcw_rest( $method, $route, $params = [] ) {
	$req = new WP_REST_Request( $method, $route );
	foreach ( $params as $k => $v ) {
		$req->set_param( $k, $v );
	}
	return rest_do_request( $req );
}

$tag = 'TEST-REST-' . wp_rand( 1000, 9999 );
$id  = Repository::insert( [ 'code' => $tag . '-A', 'initial_amount' => 20, 'balance' => 15, 'source' => 'paid_offline', 'recipient_email' => 'rest@example.test', 'expires_at' => '2031-06-01 12:00:00' ] );
TransactionRepository::insert( [ 'gift_card_id' => $id, 'type' => 'credit', 'amount' => 20, 'balance_after' => 20, 'note_key' => TransactionNote::KEY_MANUAL_CREATED ] );

// Unauthenticated → 401.
wp_set_current_user( 0 );
$res = bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards' );
bgcw_assert_eq( 401, $res->get_status(), 'anonymous list is 401' );

wp_set_current_user( bgcw_test_admin_id() );

// List with search + source filter.
$res = bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards', [ 'search' => $tag, 'source' => 'paid_offline' ] );
bgcw_assert_eq( 200, $res->get_status(), 'admin list is 200' );
$data = $res->get_data();
bgcw_assert_eq( 1, count( $data ), 'list returns one filtered card' );
bgcw_assert_eq( '1', $res->get_headers()['X-WP-Total'] ?? null, 'X-WP-Total header' );
bgcw_assert_eq( $tag . '-A', $data[0]['code'], 'list item code' );
bgcw_assert_eq( true, $data[0]['is_paid'], 'is_paid computed' );
bgcw_assert_eq( '15.00', $data[0]['balance'], 'balance as 2dp string' );
bgcw_assert_eq( '2031-06-01T12:00:00Z', $data[0]['expires_at'], 'expires_at ISO 8601 in UTC' );

// Invalid source param → 400.
$res = bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards', [ 'source' => 'bogus' ] );
bgcw_assert_eq( 400, $res->get_status(), 'invalid source param is 400' );

// Single by id and by code.
bgcw_assert_eq( $id, bgcw_rest( 'GET', "/wc-bgcw/v1/gift-cards/{$id}" )->get_data()['id'], 'get by id' );
bgcw_assert_eq( $id, bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards/code/' . strtolower( $tag . '-A' ) )->get_data()['id'], 'get by code is case-insensitive' );
bgcw_assert_eq( 404, bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards/999999999' )->get_status(), 'unknown id is 404' );
bgcw_assert_eq( 404, bgcw_rest( 'GET', '/wc-bgcw/v1/gift-cards/code/NOPE-0000' )->get_status(), 'unknown code is 404' );

// Transactions.
$res = bgcw_rest( 'GET', "/wc-bgcw/v1/gift-cards/{$id}/transactions" );
bgcw_assert_eq( 200, $res->get_status(), 'transactions 200' );
$tx = $res->get_data();
bgcw_assert_eq( 1, count( $tx ), 'one transaction' );
bgcw_assert_eq( 'credit', $tx[0]['type'], 'transaction type' );
bgcw_assert( '' !== $tx[0]['note'], 'transaction note rendered' );

TransactionRepository::delete_by_gift_card( $id );
Repository::delete( $id );
