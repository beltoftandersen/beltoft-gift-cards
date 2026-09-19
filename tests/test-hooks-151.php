<?php
require_once __DIR__ . '/bootstrap.php';

use Bgcw\GiftCard\GiftCardCreator;
use Bgcw\GiftCard\Repository;
use Bgcw\Frontend\MyAccount;

$admin_id = bgcw_test_admin_id();
bgcw_assert( $admin_id > 0, 'admin user available' );

// bgcw_gift_card_deleted fires with the id after a successful delete.
$id = GiftCardCreator::create_manual( [ 'amount' => 3, 'source' => 'compensation', 'send_email' => false ] );
bgcw_assert( $id > 0, 'card created for delete test' );
$seen = null;
add_action( 'bgcw_gift_card_deleted', function ( $deleted_id ) use ( &$seen ) { $seen = $deleted_id; } );
Repository::delete( $id );
bgcw_assert_eq( (int) $id, $seen, 'bgcw_gift_card_deleted fired with card id' );
$seen = null;
Repository::delete( 999999999 );
bgcw_assert_eq( null, $seen, 'bgcw_gift_card_deleted not fired when nothing deleted' );

// bgcw_my_account_card_actions fires per card row on the My Account page.
$id2 = GiftCardCreator::create_manual( [ 'amount' => 4, 'source' => 'compensation', 'send_email' => false ] );
bgcw_test_register_cleanup( function () use ( $id2 ) { Repository::delete( $id2 ); } );
global $wpdb;
$wpdb->update( Repository::table(), [ 'customer_id' => $admin_id ], [ 'id' => $id2 ] );
Repository::invalidate_code_cache();
wp_set_current_user( $admin_id );
add_action( 'bgcw_my_account_card_actions', function ( $gc ) use ( $id2 ) { if ( (int) $gc->id === (int) $id2 ) { echo '<!--hook-' . (int) $gc->id . '-->'; } } );
ob_start();
MyAccount::render();
$html = ob_get_clean();
bgcw_assert( false !== strpos( $html, '<!--hook-' . $id2 . '-->' ), 'bgcw_my_account_card_actions fired inside card row' );
