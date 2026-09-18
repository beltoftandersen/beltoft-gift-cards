<?php

namespace Bgcw\Rest;

use Bgcw\GiftCard\GiftCardCreator;
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
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_write_args( true ),
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
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => $this->get_write_args( false ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'force' => [ 'type' => 'boolean', 'default' => false ],
					],
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/adjust',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'adjust_balance' ],
					'permission_callback' => [ $this, 'permissions_check' ],
					'args'                => [
						'amount' => [ 'type' => 'number', 'required' => true, 'minimum' => -1000000, 'maximum' => 1000000 ],
						'note'   => [ 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
					],
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
	 * Argument schema shared by create (POST) and update (PATCH).
	 *
	 * @param bool $create Whether this is the create route.
	 * @return array
	 */
	private function get_write_args( $create ) {
		$args = [
			'source'          => [ 'type' => 'string', 'enum' => $create ? Source::manual_sources() : Source::all() ],
			'sender_name'     => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'sender_email'    => [ 'type' => 'string', 'format' => 'email' ],
			'recipient_name'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'recipient_email' => [ 'type' => 'string', 'format' => 'email' ],
			'message'         => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
			'expires_at'      => [
				'type'              => [ 'string', 'null' ],
				'validate_callback' => [ $this, 'validate_expires_at' ],
			],
		];

		if ( $create ) {
			$args['amount']     = [ 'type' => 'number', 'required' => true, 'minimum' => 0, 'exclusiveMinimum' => true ];
			$args['source']['required'] = true;
			$args['send_email'] = [ 'type' => 'boolean', 'default' => true ];
		} else {
			$args['status'] = [ 'type' => 'string', 'enum' => [ 'active', 'disabled' ] ];
		}

		return $args;
	}

	/**
	 * Accept null or an ISO 8601 / MySQL datetime string.
	 *
	 * @param mixed $value Value to validate.
	 * @return true|WP_Error
	 */
	public function validate_expires_at( $value ) {
		if ( null === $value ) {
			return true;
		}
		if ( is_string( $value ) && false !== strtotime( $value ) ) {
			return true;
		}
		return new WP_Error( 'rest_invalid_param', __( 'expires_at must be an ISO 8601 date or null.', 'beltoft-gift-cards' ), [ 'status' => 400 ] );
	}

	/**
	 * Normalise request datetime → MySQL datetime (or null). Assumes the value
	 * is already validated. Missing key → default handled by caller.
	 */
	private function to_mysql_datetime( $value ) {
		if ( null === $value ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', strtotime( (string) $value ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$data = [
			'amount'          => (float) $request['amount'],
			'source'          => (string) $request['source'],
			'sender_name'     => (string) $request->get_param( 'sender_name' ),
			'sender_email'    => (string) $request->get_param( 'sender_email' ),
			'recipient_name'  => (string) $request->get_param( 'recipient_name' ),
			'recipient_email' => (string) $request->get_param( 'recipient_email' ),
			'message'         => (string) $request->get_param( 'message' ),
			'send_email'      => (bool) $request['send_email'],
		];

		if ( $request->has_param( 'expires_at' ) ) {
			$data['expires_at'] = $this->to_mysql_datetime( $request['expires_at'] );
		}

		$id = GiftCardCreator::create_manual( $data );
		if ( ! $id ) {
			return new WP_Error( 'bgcw_rest_create_failed', __( 'Gift card could not be created.', 'beltoft-gift-cards' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $this->prepare_gift_card( Repository::find( $id ) ), 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$gc = Repository::find( (int) $request['id'] );
		if ( ! $gc ) {
			return $this->not_found();
		}

		$fields = [];
		foreach ( [ 'status', 'source', 'sender_name', 'sender_email', 'recipient_name', 'recipient_email', 'message' ] as $key ) {
			if ( $request->has_param( $key ) ) {
				$fields[ $key ] = (string) $request[ $key ];
			}
		}
		if ( $request->has_param( 'expires_at' ) ) {
			$fields['expires_at'] = $this->to_mysql_datetime( $request['expires_at'] );
		}

		if ( empty( $fields ) ) {
			return new WP_Error( 'bgcw_rest_nothing_to_update', __( 'No updatable fields were provided.', 'beltoft-gift-cards' ), [ 'status' => 400 ] );
		}

		if ( ! Repository::update( $gc->id, $fields ) ) {
			return new WP_Error( 'bgcw_rest_update_failed', __( 'Gift card could not be updated.', 'beltoft-gift-cards' ), [ 'status' => 500 ] );
		}

		return new WP_REST_Response( $this->prepare_gift_card( Repository::find( $gc->id ) ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function adjust_balance( $request ) {
		$gc = Repository::find( (int) $request['id'] );
		if ( ! $gc ) {
			return $this->not_found();
		}

		$amount = round( (float) $request['amount'], 2 );
		if ( 0.0 === $amount ) {
			return new WP_Error( 'bgcw_rest_invalid_amount', __( 'Amount must be non-zero.', 'beltoft-gift-cards' ), [ 'status' => 400 ] );
		}

		if ( $amount < 0 ) {
			if ( ! Repository::deduct_balance( $gc->id, abs( $amount ) ) ) {
				return new WP_Error( 'bgcw_rest_insufficient_balance', __( 'Insufficient balance for this debit.', 'beltoft-gift-cards' ), [ 'status' => 400 ] );
			}
			$type = 'debit';
		} else {
			if ( ! Repository::add_balance( $gc->id, $amount ) ) {
				return new WP_Error( 'bgcw_rest_update_failed', __( 'Gift card could not be updated.', 'beltoft-gift-cards' ), [ 'status' => 500 ] );
			}
			$type = 'credit';
		}

		$updated = Repository::find( $gc->id );

		$tx_id = TransactionRepository::insert( [
			'gift_card_id'  => $gc->id,
			'type'          => $type,
			'amount'        => abs( $amount ),
			'balance_after' => (float) $updated->balance,
			'note_key'      => TransactionNote::KEY_ADJUSTMENT,
			'note_args'     => [ 'note' => (string) $request['note'] ],
		] );

		if ( ! $tx_id ) {
			return new WP_Error( 'bgcw_rest_ledger_failed', __( 'Balance was changed but the transaction could not be recorded.', 'beltoft-gift-cards' ), [ 'status' => 500 ] );
		}

		if ( (float) $updated->balance <= 0 && 'active' === $updated->status ) {
			Repository::update_status( $gc->id, 'redeemed' );
		} elseif ( (float) $updated->balance > 0 && 'redeemed' === $updated->status ) {
			Repository::update_status( $gc->id, 'active' );
		}

		return new WP_REST_Response( $this->prepare_gift_card( Repository::find( $gc->id ) ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$gc = Repository::find( (int) $request['id'] );
		if ( ! $gc ) {
			return $this->not_found();
		}

		if ( ! $request['force'] ) {
			return new WP_Error( 'bgcw_rest_force_required', __( 'Gift cards cannot be trashed. Pass force=true to delete permanently.', 'beltoft-gift-cards' ), [ 'status' => 400 ] );
		}

		$previous = $this->prepare_gift_card( $gc );

		if ( ! Repository::delete( $gc->id ) ) {
			return new WP_Error( 'bgcw_rest_delete_failed', __( 'Gift card could not be deleted.', 'beltoft-gift-cards' ), [ 'status' => 500 ] );
		}

		TransactionRepository::delete_by_gift_card( $gc->id );
		Repository::invalidate_code_cache( $gc->code );

		return new WP_REST_Response( [ 'deleted' => true, 'previous' => $previous ], 200 );
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
