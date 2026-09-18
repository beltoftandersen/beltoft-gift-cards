<?php

namespace Bgcw\GiftCard;

defined( 'ABSPATH' ) || exit;

/**
 * How a gift card came into existence. Determines whether redemption
 * settles a paid liability or is a free discount.
 */
class Source {

	const ORDER        = 'order';
	const PAID_OFFLINE = 'paid_offline';
	const PROMOTION    = 'promotion';
	const COMPENSATION = 'compensation';

	/**
	 * @return string[]
	 */
	public static function all() {
		return [ self::ORDER, self::PAID_OFFLINE, self::PROMOTION, self::COMPENSATION ];
	}

	/**
	 * Sources an admin may pick when creating a card by hand.
	 *
	 * @return string[]
	 */
	public static function manual_sources() {
		return [ self::PAID_OFFLINE, self::PROMOTION, self::COMPENSATION ];
	}

	/**
	 * @param string $source Source value.
	 * @return bool
	 */
	public static function is_valid( $source ) {
		return in_array( $source, self::all(), true );
	}

	/**
	 * Whether money was received for this card.
	 *
	 * @param string $source Source value.
	 * @return bool
	 */
	public static function is_paid( $source ) {
		return in_array( $source, [ self::ORDER, self::PAID_OFFLINE ], true );
	}

	/**
	 * @return array<string,string>
	 */
	public static function labels() {
		return [
			self::ORDER        => __( 'Shop order', 'beltoft-gift-cards' ),
			self::PAID_OFFLINE => __( 'Paid offline', 'beltoft-gift-cards' ),
			self::PROMOTION    => __( 'Promotion (free)', 'beltoft-gift-cards' ),
			self::COMPENSATION => __( 'Compensation (free)', 'beltoft-gift-cards' ),
		];
	}

	/**
	 * @param string $source Source value.
	 * @return string
	 */
	public static function label( $source ) {
		$labels = self::labels();
		return $labels[ $source ] ?? (string) $source;
	}
}
