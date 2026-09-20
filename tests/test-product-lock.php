<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\Cart\CartHandler;
use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\ProductLock;
use Bgcw\GiftCard\Repository;

global $wpdb;
$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}bgcw_gift_cards", 0 );
bgcw_assert( in_array( 'product_id', $cols, true ), 'product_id column exists' );

// Temp simple product to lock to.
$workshop = new WC_Product_Simple();
$workshop->set_name( 'TMP Workshop' ); $workshop->set_regular_price( 80 ); $workshop->set_status( 'publish' );
$workshop_id = $workshop->save();
$other = new WC_Product_Simple();
$other->set_name( 'TMP Other' ); $other->set_regular_price( 20 ); $other->set_status( 'publish' );
$other_id = $other->save();
bgcw_test_register_cleanup( function () use ( $workshop_id, $other_id ) { wp_delete_post( $workshop_id, true ); wp_delete_post( $other_id, true ); } );

add_filter( 'bgcw_should_send_email_now', '__return_false' );

// Manual create with product_id.
$id = GiftCardCreator::create_manual( [ 'amount' => 80, 'source' => 'paid_offline', 'product_id' => $workshop_id, 'send_email' => false ] );
bgcw_test_register_cleanup( function () use ( $id ) { Repository::delete( $id ); } );
$gc = Repository::find( $id );
bgcw_assert_eq( $workshop_id, (int) $gc->product_id, 'manual create stores product_id' );
bgcw_assert_eq( true, ProductLock::is_locked( $gc ), 'is_locked true' );
bgcw_assert_eq( 'TMP Workshop', ProductLock::product_name( $gc ), 'product_name resolves' );
bgcw_assert_eq( true, ProductLock::can_add_directly( wc_get_product( $workshop_id ) ), 'simple product can be added directly' );
bgcw_assert_eq( wc_get_cart_url(), ProductLock::redeem_url( $gc ), 'redeem url is the cart for a simple product' );

// Virtual coupon carries the product restriction.
$coupon_data = CartHandler::virtual_coupon_data( false, $gc->code );
bgcw_assert_eq( [ $workshop_id ], $coupon_data['product_ids'] ?? null, 'virtual coupon restricted to product' );
$label = CartHandler::coupon_label( 'x', new WC_Coupon( $gc->code ) );
bgcw_assert( false !== strpos( $label, 'TMP Workshop' ), 'coupon label names the product (' . $label . ')' );

// Applied in a cart: discounts only the locked product.
wc_load_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart( $other_id, 1 );
WC()->cart->calculate_totals();
$other_only_total = (float) WC()->cart->get_total( 'edit' );
$applied = WC()->cart->apply_coupon( $gc->code );
wc_clear_notices();
bgcw_assert_eq( false, $applied, 'code rejected when locked product not in cart' );
WC()->cart->add_to_cart( $workshop_id, 1 );
WC()->cart->calculate_totals();
$both_total = (float) WC()->cart->get_total( 'edit' );
bgcw_assert( $both_total > $other_only_total, 'workshop adds to the total before the card' );
$applied = WC()->cart->apply_coupon( $gc->code );
WC()->cart->calculate_totals();
wc_clear_notices();
bgcw_assert_eq( true, $applied, 'code accepted with locked product in cart' );
$after_total = (float) WC()->cart->get_total( 'edit' );
bgcw_assert( abs( $after_total - $other_only_total ) < 0.02, sprintf( 'card covers the workshop only (other=%.2f both=%.2f after=%.2f)', $other_only_total, $both_total, $after_total ) );
WC()->cart->empty_cart();

// Unlock -> ordinary card.
bgcw_assert_eq( true, ProductLock::unlock( $id ), 'unlock succeeds' );
Repository::invalidate_code_cache();
$gc = Repository::find( $id );
bgcw_assert_eq( false, ProductLock::is_locked( $gc ), 'unlocked' );
bgcw_assert_eq( [], CartHandler::virtual_coupon_data( false, $gc->code )['product_ids'], 'no product restriction after unlock' );

// Order path: line item meta -> card product_id.
$order = wc_create_order();
$gcp   = wc_get_products( [ 'type' => 'gift-card', 'limit' => 1, 'status' => 'publish' ] );
bgcw_assert( ! empty( $gcp ), 'gift card product exists' );
$item = new WC_Order_Item_Product();
$item->set_product( $gcp[0] ); $item->set_quantity( 1 ); $item->set_total( 80 ); $item->set_subtotal( 80 );
$item->add_meta_data( '_bgcw_amount', 80 );
$item->add_meta_data( '_bgcw_recipient_email', 'lock@example.test' );
$item->add_meta_data( '_bgcw_product_id', $workshop_id );
$order->add_item( $item );
$order->set_billing_email( 'buyer@example.test' ); $order->set_billing_first_name( 'Buyer' );
$order->save();
bgcw_test_register_cleanup( function () use ( $order ) { foreach ( Repository::get_by_order( $order->get_id() ) as $c ) { Repository::delete( $c->id ); } $order->delete( true ); } );
GiftCardCreator::maybe_create_gift_cards( $order->get_id() );
$cards = Repository::get_by_order( $order->get_id() );
bgcw_assert_eq( 1, count( $cards ), 'one card created from order' );
bgcw_assert_eq( $workshop_id, (int) ( $cards[0]->product_id ?? 0 ), 'order card locked to product from item meta' );

// Amount-limits filter is consulted for non-predefined amounts.
$seen = null;
add_filter( 'bgcw_validate_amount_limits', function ( $check, $pid, $amount ) use ( &$seen ) { $seen = $amount; return false; }, 10, 3 );
$_POST = [ 'bgcw_amount' => '12345', 'bgcw_recipient_email' => 'a@example.test' ];
$passed = \Bgcw\Frontend\ProductPage::validate( true, $gcp[0]->get_id(), 1 );
wc_clear_notices();
$_POST = [];
bgcw_assert_eq( 12345.0, $seen, 'bgcw_validate_amount_limits receives the amount' );
bgcw_assert_eq( true, $passed, 'limits skipped when filter returns false' );

// REST exposes product_id.
$ctrl = new \Bgcw\Rest\GiftCardsController();
$prepared = $ctrl->prepare_gift_card( Repository::find( $cards[0]->id ) );
bgcw_assert_eq( $workshop_id, $prepared['product_id'], 'REST exposes product_id' );
bgcw_assert_eq( 'TMP Workshop', $prepared['product_name'], 'REST exposes product_name' );
