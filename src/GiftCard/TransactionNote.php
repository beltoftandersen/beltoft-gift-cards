<?php

namespace Bgcw\GiftCard;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a localized transaction note from a stable key + args.
 *
 * Notes are stored as `note_key` (machine identifier) and `note_args` (JSON)
 * so they can be translated at display time, regardless of the locale active
 * when the row was written.
 */
class TransactionNote {

	/**
	 * Stable note keys.
	 */
	const KEY_MANUAL_CREATED       = 'manual_created';
	const KEY_ORDER_CREATED        = 'order_created';
	const KEY_ORDER_USED           = 'order_used';
	const KEY_ORDER_REFUNDED       = 'order_refunded';
	const KEY_ORDER_PARTIAL_REFUND = 'order_partial_refund';

	/**
	 * Render a localized note for a transaction row.
	 *
	 * @param object $tx Transaction row from the DB.
	 * @return string
	 */
	public static function format( $tx ) {
		$key = isset( $tx->note_key ) ? (string) $tx->note_key : '';

		if ( '' === $key ) {
			// Legacy row written before this column existed.
			return isset( $tx->note ) ? (string) $tx->note : '';
		}

		$args = [];
		if ( ! empty( $tx->note_args ) ) {
			$decoded = json_decode( (string) $tx->note_args, true );
			if ( is_array( $decoded ) ) {
				$args = $decoded;
			}
		}

		$order_number = isset( $args['order_number'] ) ? (string) $args['order_number'] : '';

		switch ( $key ) {
			case self::KEY_MANUAL_CREATED:
				return __( 'Manually created by admin', 'beltoft-gift-cards' );

			case self::KEY_ORDER_CREATED:
				return sprintf(
					/* translators: %s: order number */
					__( 'Gift card created from order #%s', 'beltoft-gift-cards' ),
					$order_number
				);

			case self::KEY_ORDER_USED:
				return sprintf(
					/* translators: %s: order number */
					__( 'Used on order #%s', 'beltoft-gift-cards' ),
					$order_number
				);

			case self::KEY_ORDER_REFUNDED:
				return sprintf(
					/* translators: %s: order number */
					__( 'Refunded from order #%s', 'beltoft-gift-cards' ),
					$order_number
				);

			case self::KEY_ORDER_PARTIAL_REFUND:
				return sprintf(
					/* translators: %s: order number */
					__( 'Partial refund from order #%s', 'beltoft-gift-cards' ),
					$order_number
				);
		}

		// Unknown key — fall back to legacy `note` if any.
		return isset( $tx->note ) ? (string) $tx->note : '';
	}
}
