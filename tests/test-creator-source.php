<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\TransactionRepository;

// Missing source is rejected.
$bad   = [];
$bad[] = GiftCardCreator::create_manual( [ 'amount' => 10 ] );
bgcw_assert_eq( false, $bad[0], 'manual create without source fails' );
// Non-manual source is rejected.
$bad[] = GiftCardCreator::create_manual( [ 'amount' => 10, 'source' => 'order' ] );
bgcw_assert_eq( false, $bad[1], 'manual create with source=order fails' );
$bad[] = GiftCardCreator::create_manual( [ 'amount' => 10, 'source' => 'bogus' ] );
bgcw_assert_eq( false, $bad[2], 'manual create with unknown source fails' );

// Valid manual create stores source, sender, expiry.
$id = GiftCardCreator::create_manual( [
	'amount'       => 12.5,
	'source'       => 'compensation',
	'sender_name'  => 'Test Sender',
	'sender_email' => 'sender@example.test',
	'expires_at'   => '2031-01-01 00:00:00',
	'send_email'   => false,
] );
bgcw_assert( $id > 0, 'manual create with valid source succeeds' );
$gc = Repository::find( $id );
bgcw_assert_eq( 'compensation', $gc->source, 'source stored' );
bgcw_assert_eq( 'Test Sender', $gc->sender_name, 'sender_name stored' );
bgcw_assert_eq( 'sender@example.test', $gc->sender_email, 'sender_email stored' );
bgcw_assert_eq( '2031-01-01 00:00:00', $gc->expires_at, 'explicit expires_at stored' );

// expires_at => null means no expiry.
$id2 = GiftCardCreator::create_manual( [ 'amount' => 1, 'source' => 'promotion', 'expires_at' => null, 'send_email' => false ] );
bgcw_assert_eq( null, Repository::find( $id2 )->expires_at, 'expires_at null stores no expiry' );

// Order-created cards get source=order via the filter path.
add_filter( 'bgcw_should_send_email_now', '__return_false' ); // no delivery email during tests
$order = wc_create_order();
$item  = new WC_Order_Item_Product();
$item->set_name( 'Fake gift card item' );
$item->set_quantity( 1 );
$item->add_meta_data( '_bgcw_amount', 7, true );
$order->add_item( $item );
$order->save();
$captured = [];
add_filter( 'bgcw_gift_card_creation_args', function ( $args ) use ( &$captured ) { $captured = $args; return $args; } );
// Call the private create path through the public status handler would need a gift-card product; instead
// assert the filter receives source=order by invoking the creator on a minimal fake product type.
$ref = new ReflectionMethod( GiftCardCreator::class, 'create_single' );
$ref->setAccessible( true );
$ok = $ref->invoke( null, $order, $item, 7.0 );
bgcw_assert( $ok, 'create_single succeeds' );
bgcw_assert_eq( 'order', $captured['source'] ?? null, 'order-created card has source=order' );
$created = Repository::get_by_order( $order->get_id() );
bgcw_assert_eq( 'order', $created[0]->source ?? null, 'stored order card source=order' );

// Cleanup.
foreach ( $created as $c ) { TransactionRepository::delete_by_gift_card( $c->id ); Repository::delete( $c->id ); }
TransactionRepository::delete_by_gift_card( $id ); Repository::delete( $id );
TransactionRepository::delete_by_gift_card( $id2 ); Repository::delete( $id2 );
foreach ( $bad as $b ) { if ( $b ) { TransactionRepository::delete_by_gift_card( $b ); Repository::delete( $b ); } }
$order->delete( true );
