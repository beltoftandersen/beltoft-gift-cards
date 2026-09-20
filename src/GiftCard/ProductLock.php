<?php

namespace Bgcw\GiftCard;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for gift cards locked to a specific product.
 */
class ProductLock {

	/** @var array<int,string> Product names primed for the current request (admin lists). */
	private static $names = [];

	/**
	 * Batch-load product names so per-row lookups do not hit the database.
	 *
	 * @param int[] $product_ids Product IDs.
	 */
	public static function prime_names( array $product_ids ): void {
		$product_ids = array_values( array_unique( array_filter( array_map( 'intval', $product_ids ) ) ) );
		$product_ids = array_diff( $product_ids, array_keys( self::$names ) );
		if ( empty( $product_ids ) ) {
			return;
		}
		foreach ( wc_get_products( [ 'include' => $product_ids, 'limit' => -1, 'status' => 'any' ] ) as $product ) {
			self::$names[ $product->get_id() ] = $product->get_name();
		}
		foreach ( $product_ids as $id ) {
			if ( ! isset( self::$names[ $id ] ) ) {
				self::$names[ $id ] = '';
			}
		}
	}

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
		if ( self::is_locked( $gc ) && array_key_exists( (int) $gc->product_id, self::$names ) ) {
			$name = self::$names[ (int) $gc->product_id ];
			return '' !== $name ? $name : __( 'a product that is no longer available', 'beltoft-gift-cards' );
		}
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
	 * Whether the locked product (or one of its variations) is in the current cart.
	 *
	 * @param object $gc Gift card row.
	 */
	public static function product_in_cart( $gc ): bool {
		if ( ! self::is_locked( $gc ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		$locked = (int) $gc->product_id;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( (int) $item['product_id'] === $locked || (int) ( $item['variation_id'] ?? 0 ) === $locked ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove the product restriction so the card acts as ordinary store credit.
	 *
	 * @return bool False when the card does not exist or is not locked.
	 */
	public static function unlock( int $gift_card_id ): bool {
		$gc = Repository::find( $gift_card_id );
		if ( ! self::is_locked( $gc ) ) {
			return false;
		}

		return Repository::update( $gift_card_id, [ 'product_id' => null ] );
	}
}
