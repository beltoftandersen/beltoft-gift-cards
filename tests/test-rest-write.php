<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;

function bgcw_rest_w( $method, $route, $params = [] ) {
	$req = new WP_REST_Request( $method, $route );
	$req->set_body_params( $params );
	return rest_do_request( $req );
}

wp_set_current_user( bgcw_test_admin_id() );
$created_ids = [];
bgcw_test_register_cleanup( function () use ( &$created_ids ) {
	foreach ( $created_ids as $cid ) {
		TransactionRepository::delete_by_gift_card( $cid );
		Repository::delete( $cid );
	}
} );

// Create: validation.
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 10 ] )->get_status(), 'create without source is 400' );
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 10, 'source' => 'order' ] )->get_status(), 'create with source=order is 400' );
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 0, 'source' => 'promotion' ] )->get_status(), 'create with amount 0 is 400' );
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 5, 'source' => 'promotion', 'expires_at' => 'not-a-date' ] )->get_status(), 'create with bad expires_at is 400' );

// Create: success.
$res = bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [
	'amount'          => 25,
	'source'          => 'paid_offline',
	'recipient_name'  => 'Rest Recipient',
	'recipient_email' => 'rest-w@example.test',
	'expires_at'      => '2032-01-31T00:00:00',
	'send_email'      => false,
] );
bgcw_assert_eq( 201, $res->get_status(), 'create is 201' );
$card = $res->get_data();
$created_ids[] = $card['id'];
bgcw_assert_eq( '25.00', $card['balance'], 'created balance' );
bgcw_assert_eq( 'paid_offline', $card['source'], 'created source' );
bgcw_assert_eq( '2032-01-31T00:00:00Z', $card['expires_at'], 'created expires_at in UTC' );
bgcw_assert( preg_match( '/^[A-Z0-9\-]+$/', $card['code'] ) === 1, 'created code format' );

// Unauthenticated write attempts are rejected on every write route.
wp_set_current_user( 0 );
bgcw_assert_eq( 401, bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 5, 'source' => 'promotion' ] )->get_status(), 'anonymous create is 401' );
bgcw_assert_eq( 401, bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$card['id']}", [ 'status' => 'active' ] )->get_status(), 'anonymous patch is 401' );
$balance_before_anon_adjust = (string) Repository::find( $card['id'] )->balance;
bgcw_assert_eq( 401, bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$card['id']}/adjust", [ 'amount' => 1 ] )->get_status(), 'anonymous adjust is 401' );
bgcw_assert_eq( $balance_before_anon_adjust, (string) Repository::find( $card['id'] )->balance, 'balance unchanged after anonymous adjust' );
bgcw_assert_eq( 401, bgcw_rest_w( 'DELETE', "/wc-bgcw/v1/gift-cards/{$card['id']}", [ 'force' => true ] )->get_status(), 'anonymous delete is 401' );
wp_set_current_user( bgcw_test_admin_id() );

// Create with expires_at null → never expires.
$res = bgcw_rest_w( 'POST', '/wc-bgcw/v1/gift-cards', [ 'amount' => 1, 'source' => 'promotion', 'expires_at' => null, 'send_email' => false ] );
$created_ids[] = $res->get_data()['id'];
bgcw_assert_eq( null, $res->get_data()['expires_at'], 'expires_at null → no expiry' );

// Patch.
$id  = $card['id'];
$res = bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'status' => 'disabled', 'source' => 'compensation', 'recipient_name' => 'Patched' ] );
bgcw_assert_eq( 200, $res->get_status(), 'patch is 200' );
bgcw_assert_eq( 'disabled', $res->get_data()['status'], 'patched status' );
bgcw_assert_eq( 'compensation', $res->get_data()['source'], 'patched source' );
bgcw_assert_eq( false, $res->get_data()['is_paid'], 'is_paid follows source' );
bgcw_assert_eq( 400, bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'status' => 'redeemed' ] )->get_status(), 'patch to redeemed is 400' );
bgcw_assert_eq( 400, bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [] )->get_status(), 'empty patch is 400' );
bgcw_assert_eq( 404, bgcw_rest_w( 'PATCH', '/wc-bgcw/v1/gift-cards/999999999', [ 'status' => 'active' ] )->get_status(), 'patch unknown is 404' );
bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'status' => 'active' ] );

// Patch expires_at to null clears the expiry set at creation.
$res = bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'expires_at' => null ] );
bgcw_assert_eq( 200, $res->get_status(), 'patch expires_at null is 200' );
bgcw_assert_eq( null, $res->get_data()['expires_at'], 'patch expires_at null clears expiry' );

// Patch recipient_email to '' clears it (format=>email would have rejected this).
$res = bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'recipient_email' => '' ] );
bgcw_assert_eq( 200, $res->get_status(), 'patch recipient_email empty is 200' );
bgcw_assert_eq( '', $res->get_data()['recipient_email'], 'patch recipient_email empty clears it' );
bgcw_assert_eq( 400, bgcw_rest_w( 'PATCH', "/wc-bgcw/v1/gift-cards/{$id}", [ 'recipient_email' => 'not-an-email' ] )->get_status(), 'patch invalid recipient_email is 400' );

// Adjust.
$res = bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => -5, 'note' => 'Test debit' ] );
bgcw_assert_eq( 200, $res->get_status(), 'debit adjust is 200' );
bgcw_assert_eq( '20.00', $res->get_data()['balance'], 'balance after debit' );
$res = bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => 2.5, 'note' => 'Test credit' ] );
bgcw_assert_eq( '22.50', $res->get_data()['balance'], 'balance after credit' );
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => -100 ] )->get_status(), 'over-debit is 400' );
bgcw_assert_eq( 400, bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => 0 ] )->get_status(), 'zero adjust is 400' );
$tx = rest_do_request( new WP_REST_Request( 'GET', "/wc-bgcw/v1/gift-cards/{$id}/transactions" ) )->get_data();
bgcw_assert_eq( 3, count( $tx ), 'three transactions (create + 2 adjustments)' );
$notes = array_column( $tx, 'note' );
bgcw_assert( in_array( 'Test debit', $notes, true ) && in_array( 'Test credit', $notes, true ), 'adjustment notes rendered verbatim' );

// Debit to zero marks redeemed.
$res = bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => -22.5 ] );
bgcw_assert_eq( 'redeemed', $res->get_data()['status'], 'zero balance → redeemed' );

// Crediting a redeemed card reactivates it; debiting back to zero re-redeems it.
$res = bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => 1 ] );
bgcw_assert_eq( 'active', $res->get_data()['status'], 'credit on redeemed card reactivates' );
bgcw_assert_eq( '1.00', $res->get_data()['balance'], 'balance after reactivating credit' );
$res = bgcw_rest_w( 'POST', "/wc-bgcw/v1/gift-cards/{$id}/adjust", [ 'amount' => -1 ] );
bgcw_assert_eq( 'redeemed', $res->get_data()['status'], 'debit back to zero → redeemed again' );

// Delete.
bgcw_assert_eq( 400, bgcw_rest_w( 'DELETE', "/wc-bgcw/v1/gift-cards/{$id}" )->get_status(), 'delete without force is 400' );
$res = bgcw_rest_w( 'DELETE', "/wc-bgcw/v1/gift-cards/{$id}", [ 'force' => true ] );
bgcw_assert_eq( 200, $res->get_status(), 'forced delete is 200' );
bgcw_assert_eq( true, $res->get_data()['deleted'], 'deleted flag' );
bgcw_assert_eq( null, Repository::find( $id ), 'row gone' );
bgcw_assert_eq( [], TransactionRepository::get_by_gift_card( $id ), 'transactions gone' );
