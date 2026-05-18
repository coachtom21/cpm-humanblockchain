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
	 * HumanBlockchain wp_xp_ledger uses wp_user_id (not Smallstreet user_id/key/value).
	 *
	 * @return bool
	 */
	public static function uses_hb_xp_ledger_schema() {
		global $wpdb;
		$table = $wpdb->prefix . 'xp_ledger';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$col = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` LIKE 'wp_user_id'" );
		return is_array( $col ) && count( $col ) > 0;
	}

	/**
	 * @param int $order_id WooCommerce order ID.
	 * @return array<int, object>
	 */
	public static function get_hb_xp_rows_for_order( $order_id ) {
		global $wpdb;
		$order_id = absint( $order_id );
		if ( $order_id <= 0 || ! self::uses_hb_xp_ledger_schema() ) {
			return array();
		}
		$table = $wpdb->prefix . 'xp_ledger';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE order_id = %d ORDER BY id ASC",
				$order_id
			)
		);
		$rows = is_array( $rows ) ? $rows : array();
		$like = '%' . $wpdb->esc_like( (string) $order_id ) . '%';
		$json_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE ( order_id IS NULL OR order_id != %d ) AND entry_json LIKE %s ORDER BY id ASC",
				$order_id,
				$like
			)
		);
		if ( is_array( $json_rows ) ) {
			foreach ( $json_rows as $jr ) {
				if ( ! is_object( $jr ) || empty( $jr->entry_json ) ) {
					continue;
				}
				$decoded = json_decode( (string) $jr->entry_json, true );
				if ( ! is_array( $decoded ) ) {
					continue;
				}
				$oids = array();
				if ( ! empty( $decoded['order_id'] ) ) {
					$oids[] = (int) $decoded['order_id'];
				}
				if ( ! empty( $decoded['order_ids'] ) && is_array( $decoded['order_ids'] ) ) {
					foreach ( $decoded['order_ids'] as $oid ) {
						$oids[] = (int) $oid;
					}
				}
				if ( in_array( $order_id, $oids, true ) ) {
					$rows[] = $jr;
				}
			}
		}
		$by_id = array();
		foreach ( $rows as $row ) {
			if ( is_object( $row ) && isset( $row->id ) ) {
				$by_id[ (int) $row->id ] = $row;
			}
		}
		return array_values( $by_id );
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_all_hb_xp_rows( $limit = 500 ) {
		global $wpdb;
		$limit = min( 2000, max( 1, (int) $limit ) );
		if ( ! self::uses_hb_xp_ledger_schema() ) {
			return array();
		}
		$table = $wpdb->prefix . 'xp_ledger';
		$rows  = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT {$limit}" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Push one HumanBlockchain xp_ledger row to ledger/xp/event-*.json
	 *
	 * @param object $row DB row object.
	 * @return true|WP_Error
	 */
	public static function sync_hb_xp_row_to_github( $row ) {
		if ( ! self::is_enabled() || ! is_object( $row ) ) {
			return new WP_Error( 'cpm_hb_ledger', __( 'GitHub ledger is not available.', 'cpm-humanblockchain' ) );
		}
		$entry = array();
		if ( ! empty( $row->entry_json ) ) {
			$decoded = json_decode( (string) $row->entry_json, true );
			if ( is_array( $decoded ) ) {
				$entry = $decoded;
			}
		}
		self::on_xp_ledger_row_saved(
			array(
				'row_id'                  => isset( $row->id ) ? (int) $row->id : 0,
				'wp_user_id'              => isset( $row->wp_user_id ) ? (int) $row->wp_user_id : 0,
				'scan_type'               => isset( $row->scan_type ) ? (string) $row->scan_type : '',
				'transaction_id'          => isset( $row->transaction_id ) ? (string) $row->transaction_id : '',
				'xp_units'                => isset( $row->xp_units ) ? (string) $row->xp_units : '',
				'scan_status'             => isset( $row->scan_status ) ? (string) $row->scan_status : '',
				'order_id'                => isset( $row->order_id ) ? (int) $row->order_id : null,
				'counterparty_wp_user_id' => isset( $row->counterparty_wp_user_id ) ? (int) $row->counterparty_wp_user_id : null,
				'ledger_date'             => isset( $row->ledger_date ) ? (string) $row->ledger_date : '',
				'entry_json'              => $entry,
				'event_kind'              => 'manual_sync',
			)
		);
		return true;
	}

	/**
	 * Sync WooCommerce order + related xp_ledger rows (+ optional bulk).
	 *
	 * @param int   $order_id           Primary order ID.
	 * @param array $options            sync_all_orders, sync_all_xp, orders_limit, xp_limit.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function sync_bundle_to_github( $order_id, array $options = array() ) {
		if ( ! self::is_enabled() || ! function_exists( 'ss_ledger_gh_sync_order_to_github' ) ) {
			return new WP_Error( 'cpm_hb_ledger', __( 'GitHub ledger is not configured.', 'cpm-humanblockchain' ) );
		}

		$order_id           = absint( $order_id );
		$sync_all_orders    = ! empty( $options['sync_all_orders'] );
		$sync_all_xp        = ! empty( $options['sync_all_xp'] );
		$orders_limit       = isset( $options['orders_limit'] ) ? (int) $options['orders_limit'] : 200;
		$xp_limit           = isset( $options['xp_limit'] ) ? (int) $options['xp_limit'] : 500;
		$delay              = max( 0, (int) apply_filters( 'cpm_hb_github_ledger_bulk_usleep', 150000 ) );

		$stats = array(
			'orders_ok'   => 0,
			'orders_fail' => 0,
			'xp_ok'       => 0,
			'xp_fail'     => 0,
			'order_ids'   => array(),
			'xp_row_ids'  => array(),
		);

		$order_ids = array();
		if ( $order_id > 0 ) {
			$order_ids[] = $order_id;
		}
		if ( $sync_all_orders && function_exists( 'wc_get_orders' ) ) {
			$recent = wc_get_orders(
				array(
					'limit'   => min( 500, max( 1, $orders_limit ) ),
					'orderby' => 'date',
					'order'   => 'DESC',
					'return'  => 'ids',
				)
			);
			if ( is_array( $recent ) ) {
				$order_ids = array_values( array_unique( array_merge( $order_ids, array_map( 'absint', $recent ) ) ) );
			}
		}

		foreach ( $order_ids as $oid ) {
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( $order ) {
				$order->delete_meta_data( '_ss_ledger_gh_last_sync' );
				$order->save();
			}
			$r = ss_ledger_gh_sync_order_to_github( $oid, 'manual', sprintf( 'Ledger: NWP manual sync order #%d', $oid ) );
			if ( is_wp_error( $r ) ) {
				++$stats['orders_fail'];
			} else {
				++$stats['orders_ok'];
				$stats['order_ids'][] = $oid;
			}
			if ( $delay > 0 ) {
				usleep( $delay );
			}
		}

		$xp_rows = array();
		if ( $sync_all_xp ) {
			$xp_rows = self::get_all_hb_xp_rows( $xp_limit );
		} elseif ( $order_id > 0 ) {
			$xp_rows = self::get_hb_xp_rows_for_order( $order_id );
		}

		$xp_seen = array();
		foreach ( $xp_rows as $row ) {
			if ( ! is_object( $row ) || empty( $row->id ) ) {
				continue;
			}
			$rid = (int) $row->id;
			if ( isset( $xp_seen[ $rid ] ) ) {
				continue;
			}
			$xp_seen[ $rid ] = true;
			$r               = self::sync_hb_xp_row_to_github( $row );
			if ( is_wp_error( $r ) ) {
				++$stats['xp_fail'];
			} else {
				++$stats['xp_ok'];
				$stats['xp_row_ids'][] = $rid;
			}
			if ( $delay > 0 ) {
				usleep( $delay );
			}
		}

		if ( $order_id <= 0 && ! $sync_all_orders && ! $sync_all_xp ) {
			return new WP_Error( 'cpm_hb_ledger', __( 'Enter an order ID or enable a bulk sync option.', 'cpm-humanblockchain' ) );
		}

		if ( $stats['orders_ok'] === 0 && $stats['xp_ok'] === 0 && ( $stats['orders_fail'] > 0 || $stats['xp_fail'] > 0 ) ) {
			return new WP_Error(
				'cpm_hb_ledger',
				__( 'No rows were synced successfully. Check Last error on this page or server logs.', 'cpm-humanblockchain' ),
				$stats
			);
		}

		return $stats;
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
