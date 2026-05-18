<?php
/**
 * Push HumanBlockchain transactions to the shared GitHub ledger repo (ss_ledger_gh_* MU-plugin).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges wp_xp_ledger rows and PoD wallet credits (cpm-humanblockchain) to GitHub.
 */
class Cpm_Hb_Github_Ledger {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'cpm_hb_xp_ledger_row_saved', array( __CLASS__, 'on_xp_ledger_row_saved' ), 10, 1 );
		add_action( 'cpm_hb_buyer_rebate_wallet_credited', array( __CLASS__, 'on_buyer_rebate_credited' ), 10, 5 );
		add_action( 'cpm_hb_seller_trade_credit_wallet_credited', array( __CLASS__, 'on_seller_trade_credit_credited' ), 10, 6 );
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return function_exists( 'ss_ledger_gh_config_ok' ) && ss_ledger_gh_config_ok();
	}

	/**
	 * @param string $scan_type Raw scan_type from wp_xp_ledger.
	 * @return string GitHub action slug.
	 */
	public static function scan_type_to_action( $scan_type ) {
		$map = array(
			'seller_scan'    => 'seller-scan',
			'buyer_scan'     => 'buyer-scan',
			'discord_verify' => 'discord-verify',
			'personal_scan'  => 'personal-scan',
		);
		$scan_type = sanitize_key( (string) $scan_type );
		if ( isset( $map[ $scan_type ] ) ) {
			return $map[ $scan_type ];
		}
		return $scan_type !== '' ? $scan_type : 'xp-event';
	}

	/**
	 * @param array<string, mixed> $row Event from xp-ledger emitter.
	 */
	public static function on_xp_ledger_row_saved( $row ) {
		if ( ! self::is_enabled() || ! is_array( $row ) ) {
			return;
		}

		$user_id = isset( $row['wp_user_id'] ) ? absint( $row['wp_user_id'] ) : 0;
		$scan_type = isset( $row['scan_type'] ) ? sanitize_key( (string) $row['scan_type'] ) : '';
		if ( ! $user_id || $scan_type === '' ) {
			return;
		}

		$row_id = isset( $row['row_id'] ) ? absint( $row['row_id'] ) : 0;
		$txn    = isset( $row['transaction_id'] ) ? (string) $row['transaction_id'] : '';
		$event  = isset( $row['event_kind'] ) ? sanitize_key( (string) $row['event_kind'] ) : 'insert';

		$action = self::scan_type_to_action( $scan_type );
		if ( 'seller_completed' === $event && 'seller_scan' === $scan_type ) {
			$action = 'seller-scan-completed';
		}

		$uniq_parts = array( 'hb' );
		if ( $row_id > 0 ) {
			$uniq_parts[] = (string) $row_id;
		}
		$uniq_parts[] = $scan_type;
		if ( 'seller_completed' === $event ) {
			$uniq_parts[] = 'completed';
		} elseif ( $txn !== '' ) {
			$uniq_parts[] = sanitize_key( preg_replace( '/[^a-zA-Z0-9_-]/', '', $txn ) );
		}

		$payload = array(
			'user_id' => $user_id,
			'action'  => $action,
			'date'    => isset( $row['ledger_date'] ) && $row['ledger_date'] !== ''
				? gmdate( 'c', strtotime( (string) $row['ledger_date'] ) )
				: gmdate( 'c' ),
			'uniq'    => implode( '-', array_filter( $uniq_parts ) ),
			'site'    => 'humanblockchain',
			'xp'      => array(
				'action'    => $scan_type,
				'xp_gained' => isset( $row['xp_units'] ) ? (string) $row['xp_units'] : '',
				'status'    => isset( $row['scan_status'] ) ? (string) $row['scan_status'] : '',
			),
		);

		if ( ! empty( $row['order_id'] ) ) {
			$payload['order_id'] = absint( $row['order_id'] );
		}
		if ( $txn !== '' ) {
			$payload['transaction_id'] = $txn;
		}
		if ( ! empty( $row['counterparty_wp_user_id'] ) ) {
			$payload['counterparty_user_id'] = absint( $row['counterparty_wp_user_id'] );
		}
		if ( ! empty( $row['entry_json'] ) && is_array( $row['entry_json'] ) ) {
			$payload['entry'] = $row['entry_json'];
		}

		$payload = apply_filters( 'cpm_hb_github_ledger_xp_payload', $payload, $row );
		do_action( 'ss_ledger_gh_record_xp', $payload );
	}

	/**
	 * @param int    $buyer_id     Buyer user ID.
	 * @param int    $rebate_cents Cents credited.
	 * @param string $fp           Fingerprint.
	 * @param int[]  $order_ids    Order IDs.
	 * @param string $code         HB transaction code.
	 */
	public static function on_buyer_rebate_credited( $buyer_id, $rebate_cents, $fp, $order_ids, $code ) {
		self::record_wallet_event(
			(int) $buyer_id,
			'pod-buyer-rebate',
			substr( sanitize_key( $fp ), 0, 48 ),
			array(
				'amount_cents'   => (int) $rebate_cents,
				'transaction_id' => (string) $code,
				'order_ids'      => array_values( array_map( 'intval', (array) $order_ids ) ),
			)
		);
	}

	/**
	 * @param int    $seller_id    Seller user ID.
	 * @param int    $credit_cents Cents credited.
	 * @param string $fp           Fingerprint.
	 * @param int    $buyer_id     Buyer user ID.
	 * @param int[]  $order_ids    Order IDs.
	 * @param string $code         HB transaction code.
	 */
	public static function on_seller_trade_credit_credited( $seller_id, $credit_cents, $fp, $buyer_id, $order_ids, $code ) {
		self::record_wallet_event(
			(int) $seller_id,
			'pod-seller-trade-credit',
			substr( sanitize_key( $fp ), 0, 48 ),
			array(
				'amount_cents'        => (int) $credit_cents,
				'transaction_id'      => (string) $code,
				'counterparty_user_id' => (int) $buyer_id,
				'order_ids'           => array_values( array_map( 'intval', (array) $order_ids ) ),
			)
		);
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param string               $action  Action slug.
	 * @param string               $uniq    Idempotency suffix.
	 * @param array<string, mixed> $extra   Extra fields.
	 */
	private static function record_wallet_event( $user_id, $action, $uniq, array $extra = array() ) {
		if ( ! self::is_enabled() || $user_id <= 0 ) {
			return;
		}

		$payload = array_merge(
			array(
				'user_id' => $user_id,
				'action'  => sanitize_key( $action ),
				'date'    => gmdate( 'c' ),
				'uniq'    => 'hb-wallet-' . $uniq,
				'site'    => 'humanblockchain',
			),
			$extra
		);

		$payload = apply_filters( 'cpm_hb_github_ledger_wallet_payload', $payload, $user_id, $action );
		do_action( 'ss_ledger_gh_record_xp', $payload );
	}
}

Cpm_Hb_Github_Ledger::init();
