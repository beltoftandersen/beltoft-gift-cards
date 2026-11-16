<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;

add_filter( 'bgcw_should_send_email_now', '__return_false' );

/**
 * A paid order that made one card of $amount.
 */
function bgcw_test_topup_order( $amount ) {
	$order = wc_create_order();
	$order->set_total( $amount );
	$order->save();
	$item = new WC_Order_Item_Product();
	$item->set_name( 'Top-up' );
	$item->set_quantity( 1 );
	$item->add_meta_data( '_bgcw_amount', $amount, true );
	$order->add_item( $item );
	$order->save();
	$ref = new ReflectionMethod( GiftCardCreator::class, 'create_single' );
	$ref->setAccessible( true );
	$ref->invoke( null, $order, $item, (float) $amount );
	$order->update_meta_data( '_bgcw_cards_created', '1' );
	$order->save();
	$cards = Repository::get_by_order( $order->get_id() );

	return array( $order, $cards[0] );
}

$alerts = 0;
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) use ( &$alerts ) {
		if ( false !== strpos( (string) $atts['subject'], 'Gift card' ) ) {
			++$alerts;
		}
		return true;
	},
	1,
	2
);

// Unspent: a full refund voids the card.
list( $order, $card ) = bgcw_test_topup_order( 50 );
$order->update_status( 'refunded' );
$gc = Repository::find( $card->id );
bgcw_assert_eq( 0.0, (float) $gc->balance, 'a refunded top-up leaves no balance' );
bgcw_assert_eq( 'disabled', $gc->status, 'and the card is disabled' );
bgcw_assert_eq( 0, $alerts, 'nothing was spent: no alert' );

// Idempotent on repeated events.
do_action( 'woocommerce_order_status_refunded', $order->get_id() );
do_action( 'woocommerce_order_refunded', $order->get_id(), 0 );
bgcw_assert_eq( 0.0, (float) Repository::find( $card->id )->balance, 'repeated refund events change nothing' );
bgcw_assert_eq( 2, count( TransactionRepository::get_by_gift_card( $card->id ) ), 'one void transaction only' );

// Partly spent: the unspent part is voided, the admin is told about the spent part.
list( $order2, $card2 ) = bgcw_test_topup_order( 50 );
Repository::deduct_balance( $card2->id, 30 );
$order2->update_status( 'cancelled' );
$gc2 = Repository::find( $card2->id );
bgcw_assert_eq( 0.0, (float) $gc2->balance, 'the unspent 20 is voided, never below zero' );
bgcw_assert_eq( 1, $alerts, 'the admin is told that 30 was already spent' );
do_action( 'woocommerce_order_status_cancelled', $order2->get_id() );
bgcw_assert_eq( 1, $alerts, 'and only once' );

// Partial refund: reduced pro rata.
list( $order3, $card3 ) = bgcw_test_topup_order( 100 );
wc_create_refund( array( 'order_id' => $order3->get_id(), 'amount' => 40, 'reason' => 'test' ) );
bgcw_assert_eq( 60.0, (float) Repository::find( $card3->id )->balance, 'a partial refund of 40 reduces the credit by 40' );
bgcw_assert_eq( 'active', Repository::find( $card3->id )->status, 'the card stays active' );
