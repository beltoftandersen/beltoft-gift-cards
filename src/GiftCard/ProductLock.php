<?php

namespace Bgcw\GiftCard;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for gift cards locked to a specific product.
 */
class ProductLock {

	/**
	 * Whether the card is locked to a product.
	 *
	 * @param object|null $gc Gift card row.
	 */
	public static function is_locked( $gc ): bool {
		return $gc && ! empty( $gc->product_id );
	}

	/**
	 * The locked product, when it still exists.
	 *
	 * @param object|null $gc Gift card row.
	 * @return \WC_Product|null
	 */
	public static function product( $gc ) {
		if ( ! self::is_locked( $gc ) ) {
			return null;
		}
		$product = wc_get_product( (int) $gc->product_id );

		return $product ? $product : null;
	}

	/**
	 * Product name for display, or an empty string when unlocked.
	 *
	 * @param object|null $gc Gift card row.
	 */
	public static function product_name( $gc ): string {
		$product = self::product( $gc );
		if ( $product ) {
			return $product->get_name();
		}

		return self::is_locked( $gc ) ? __( 'a product that is no longer available', 'beltoft-gift-cards' ) : '';
	}

	/**
	 * URL the recipient should land on when redeeming.
	 *
	 * Simple purchasable products go to the cart (the product is added by the auto-apply
	 * handler); anything else goes to the product page so options can be chosen.
	 *
	 * @param object $gc Gift card row.
	 */
	public static function redeem_url( $gc ): string {
		$product = self::product( $gc );
		if ( ! $product || ! $product->is_purchasable() ) {
			return wc_get_page_permalink( 'shop' );
		}
		if ( self::can_add_directly( $product ) ) {
			return wc_get_cart_url();
		}

		return (string) $product->get_permalink();
	}

	/**
	 * Whether the product can be added to the cart without choosing options.
	 */
	public static function can_add_directly( \WC_Product $product ): bool {
		return 'simple' === $product->get_type() && $product->is_purchasable() && $product->is_in_stock();
	}

	/**
	 * Remove the product restriction so the card acts as ordinary store credit.
	 */
	public static function unlock( int $gift_card_id ): bool {
		return Repository::update( $gift_card_id, [ 'product_id' => null ] );
	}
}
