<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;
use Bgcw\Checkout\OrderProcessor;

$tag  = 'TEST-ORD-' . wp_rand( 1000, 9999 );
$paid = Repository::insert( [ 'code' => $tag . '-PAID', 'initial_amount' => 50, 'balance' => 50, 'source' => 'paid_offline' ] );
$free = Repository::insert( [ 'code' => $tag . '-FREE', 'initial_amount' => 50, 'balance' => 50, 'source' => 'compensation' ] );

$order = wc_create_order();
foreach ( [ [ $tag . '-PAID', 10.00 ], [ $tag . '-FREE', 4.50 ] ] as $pair ) {
	$c = new WC_Order_Item_Coupon();
	$c->set_code( $pair[0] );
	$c->set_discount( $pair[1] );
	$c->set_discount_tax( 0 );
	$order->add_item( $c );
}
$order->save();

OrderProcessor::save_pending_deductions( $order );
$order = wc_get_order( $order->get_id() );

$by_code = [];
foreach ( $order->get_items( 'coupon' ) as $ci ) {
	$by_code[ strtoupper( $ci->get_code() ) ] = $ci;
}
bgcw_assert_eq( (string) $paid, (string) $by_code[ $tag . '-PAID' ]->get_meta( 'bgcw_gift_card_id' ), 'coupon line has gift card id' );
bgcw_assert_eq( 'paid_offline', $by_code[ $tag . '-PAID' ]->get_meta( 'bgcw_source' ), 'coupon line has source' );
bgcw_assert_eq( 'yes', $by_code[ $tag . '-PAID' ]->get_meta( 'bgcw_is_paid' ), 'paid card flagged yes' );
bgcw_assert_eq( 'no', $by_code[ $tag . '-FREE' ]->get_meta( 'bgcw_is_paid' ), 'free card flagged no' );
bgcw_assert_eq( '0', (string) $by_code[ $tag . '-FREE' ]->get_meta( 'bgcw_source_order_id' ), 'no source order id for manual card' );

OrderProcessor::deduct_gift_card_balances( $order->get_id() );
$order = wc_get_order( $order->get_id() );
bgcw_assert_eq( '1', $order->get_meta( '_bgcw_deducted' ), 'deduction completed' );
bgcw_assert_eq( '10.00', $order->get_meta( '_bgcw_paid_redeemed_total' ), 'paid total on order' );
bgcw_assert_eq( '4.50', $order->get_meta( '_bgcw_free_redeemed_total' ), 'free total on order' );
bgcw_assert_eq( 40.0, (float) Repository::find( $paid )->balance, 'paid card balance deducted' );

// Cleanup.
$order->delete( true );
foreach ( [ $paid, $free ] as $id ) { TransactionRepository::delete_by_gift_card( $id ); Repository::delete( $id ); }

// Both classic and block (Store API) checkout must reach the same handlers.
bgcw_assert( has_action( 'woocommerce_checkout_order_created', [ 'Bgcw\\Checkout\\OrderProcessor', 'save_pending_deductions' ] ) !== false, 'classic checkout hook registered' );
bgcw_assert( has_action( 'woocommerce_store_api_checkout_order_processed', [ 'Bgcw\\Checkout\\OrderProcessor', 'save_pending_deductions' ] ) !== false, 'store api hook registered' );

$paid2 = Repository::insert( [ 'code' => $tag . '-PAID2', 'initial_amount' => 50, 'balance' => 50, 'source' => 'paid_offline' ] );

$order2 = wc_create_order();
$c2 = new WC_Order_Item_Coupon();
$c2->set_code( $tag . '-PAID2' );
$c2->set_discount( 5.00 );
$c2->set_discount_tax( 0 );
$order2->add_item( $c2 );
$order2->save();

// Simulate block checkout, which fires this action instead of
// woocommerce_checkout_order_created.
do_action( 'woocommerce_store_api_checkout_order_processed', $order2 );
$order2 = wc_get_order( $order2->get_id() );

$by_code2 = [];
foreach ( $order2->get_items( 'coupon' ) as $ci ) {
	$by_code2[ strtoupper( $ci->get_code() ) ] = $ci;
}
bgcw_assert_eq( '5', (string) $order2->get_meta( '_bgcw_pending_deductions' )[ $tag . '-PAID2' ], 'block checkout stamps pending deductions' );
bgcw_assert_eq( 'paid_offline', $by_code2[ $tag . '-PAID2' ]->get_meta( 'bgcw_source' ), 'block checkout stamps coupon source' );

$order2->delete( true );
TransactionRepository::delete_by_gift_card( $paid2 );
Repository::delete( $paid2 );
