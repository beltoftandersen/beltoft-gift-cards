<?php

namespace Bgcw\Rest;

use Bgcw\GiftCard\Repository;
use Bgcw\GiftCard\Source;
use Bgcw\GiftCard\TransactionNote;
use Bgcw\GiftCard\TransactionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints for managing gift cards from external systems.
 *
 * Namespace is prefixed "wc-" so WooCommerce consumer key/secret
 * authentication applies; application passwords also work.
 */
class GiftCardsController extends \WP_REST_Controller {

	const NAMESPACE_V1 = 'wc-bgcw/v1';

	public function __construct() {
		$this->namespace = self::NAMESPACE_V1;
		$this->rest_base = 'gift-cards';
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args' => [
					'id' => [ 'type' => 'integer', 'required' => true ],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/code/(?P<code>[A-Za-z0-9\-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item_by_code' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/transactions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_transactions' ],
					'permission_callback' => [ $this, 'permissions_check' ],
				],
			]
		);
	}

	/**
	 * Shop managers and above. Filterable for custom roles / keys.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		$allowed = current_user_can( 'manage_woocommerce' );

		/**
		 * Filter REST access to gift card endpoints.
		 *
		 * @param bool            $allowed Whether the current user may access the endpoint.
		 * @param WP_REST_Request $request Request.
		 */
		$allowed = (bool) apply_filters( 'bgcw_rest_permission', $allowed, $request );

		if ( $allowed ) {
			return true;
		}

		return new WP_Error(
			'bgcw_rest_forbidden',
			__( 'Sorry, you are not allowed to manage gift cards.', 'beltoft-gift-cards' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	public function get_collection_params() {
		return [
			'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
			'search'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'status'   => [ 'type' => 'string', 'enum' => Repository::VALID_STATUSES ],
			'source'   => [ 'type' => 'string', 'enum' => Source::all() ],
			'orderby'  => [ 'type' => 'string', 'default' => 'created_at', 'enum' => [ 'id', 'code', 'balance', 'initial_amount', 'status', 'source', 'created_at', 'expires_at' ] ],
			'order'    => [ 'type' => 'string', 'default' => 'desc', 'enum' => [ 'asc', 'desc' ] ],
		];
	}

	public function get_items( $request ) {
		$per_page = (int) $request['per_page'];
		$page     = (int) $request['page'];

		$args = [
			'per_page' => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
			'orderby'  => $request['orderby'],
			'order'    => $request['order'],
			'status'   => (string) $request['status'],
			'source'   => (string) $request['source'],
			'search'   => (string) $request['search'],
		];

		$rows  = Repository::get_all_paginated( $args );
		$total = Repository::count_all( $args );

		$items = array_map( [ $this, 'prepare_gift_card' ], $rows );

		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / max( 1, $per_page ) ) );

		return $response;
	}

	public function get_item( $request ) {
		$gc = Repository::find( (int) $request['id'] );
		if ( ! $gc ) {
			return $this->not_found();
		}
		return new WP_REST_Response( $this->prepare_gift_card( $gc ), 200 );
	}

	public function get_item_by_code( $request ) {
		$gc = Repository::find_by_code( strtoupper( (string) $request['code'] ), true );
		if ( ! $gc ) {
			return $this->not_found();
		}
		return new WP_REST_Response( $this->prepare_gift_card( $gc ), 200 );
	}

	public function get_transactions( $request ) {
		$gc = Repository::find( (int) $request['id'] );
		if ( ! $gc ) {
			return $this->not_found();
		}
		$rows = TransactionRepository::get_by_gift_card( $gc->id );
		return new WP_REST_Response( array_map( [ $this, 'prepare_transaction' ], $rows ), 200 );
	}

	/**
	 * @param object $gc Gift card row.
	 * @return array
	 */
	public function prepare_gift_card( $gc ) {
		$source = (string) ( $gc->source ?? '' );
		return [
			'id'              => (int) $gc->id,
			'code'            => (string) $gc->code,
			'initial_amount'  => $this->money( $gc->initial_amount ),
			'balance'         => $this->money( $gc->balance ),
			'currency'        => (string) $gc->currency,
			'status'          => (string) $gc->status,
			'source'          => $source,
			'is_paid'         => Source::is_paid( $source ),
			'sender_name'     => (string) $gc->sender_name,
			'sender_email'    => (string) $gc->sender_email,
			'recipient_name'  => (string) $gc->recipient_name,
			'recipient_email' => (string) $gc->recipient_email,
			'message'         => (string) $gc->message,
			'order_id'        => $gc->order_id ? (int) $gc->order_id : null,
			'customer_id'     => $gc->customer_id ? (int) $gc->customer_id : null,
			'expires_at'      => $this->format_datetime( $gc->expires_at ),
			'created_at'      => $this->format_datetime( $gc->created_at ),
		];
	}

	/**
	 * @param object $tx Transaction row.
	 * @return array
	 */
	public function prepare_transaction( $tx ) {
		return [
			'id'            => (int) $tx->id,
			'gift_card_id'  => (int) $tx->gift_card_id,
			'order_id'      => $tx->order_id ? (int) $tx->order_id : null,
			'type'          => (string) $tx->type,
			'amount'        => $this->money( $tx->amount ),
			'balance_after' => $this->money( $tx->balance_after ),
			'note'          => TransactionNote::format( $tx ),
			'created_at'    => $this->format_datetime( $tx->created_at ),
		];
	}

	/**
	 * MySQL datetime → ISO 8601 (no timezone suffix; stored values are site-local like WC).
	 *
	 * @param string|null $mysql Datetime.
	 * @return string|null
	 */
	public function format_datetime( $mysql ) {
		if ( empty( $mysql ) || '0000-00-00 00:00:00' === $mysql ) {
			return null;
		}
		return mysql2date( 'Y-m-d\TH:i:s', $mysql, false );
	}

	private function money( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}

	private function not_found() {
		return new WP_Error( 'bgcw_rest_not_found', __( 'Gift card not found.', 'beltoft-gift-cards' ), [ 'status' => 404 ] );
	}
}
