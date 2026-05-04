<?php
/**
 * PoD delivery wallet: buyer rebate + seller trade credit balances (Option A alongside XP ledger).
 *
 * Credits run on {@see 'cpm_hb_buyer_confirmed_delivery'}. Amounts use the same filters as messaging:
 * `cpm_hb_buyer_delivery_confirmed_rebate_usd` and `cpm_hb_seller_trade_credit_on_delivery_usd`.
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User-meta wallet balances (integer cents) + idempotent event fingerprints.
 */
class Cpm_Humanblockchain_Pod_Wallet {

	const META_REBATE_CENTS        = 'cpm_hb_rebate_balance_cents';
	const META_TRADE_CREDIT_CENTS  = 'cpm_hb_trade_credit_balance_cents';
	const META_REBATE_FP          = '_cpm_hb_pod_rebate_fingerprints';
	const META_SELLER_CREDIT_FP   = '_cpm_hb_pod_seller_credit_fingerprints';
	const ORDER_META_REBATE_CENTS = '_cpm_hb_pod_rebate_credited_cents';
	const ORDER_META_CREDIT_CENTS = '_cpm_hb_pod_seller_credit_credited_cents';
	const FP_MAX_STORE            = 200;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'cpm_hb_buyer_confirmed_delivery', array( __CLASS__, 'on_buyer_confirmed_delivery' ), 20, 4 );
		add_filter( 'cpm_hb_buyer_confirm_delivery_success_message', array( __CLASS__, 'append_wallet_summary_to_buyer_message' ), 15, 5 );
		add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_account_balances' ), 15 );
	}

	/**
	 * @param int $user_id WP user ID.
	 * @return int Non-negative cents.
	 */
	public static function get_rebate_balance_cents( $user_id ) {
		$v = get_user_meta( (int) $user_id, self::META_REBATE_CENTS, true );
		$n = is_numeric( $v ) ? (int) $v : 0;
		return max( 0, $n );
	}

	/**
	 * @param int $user_id WP user ID.
	 * @return int Non-negative cents.
	 */
	public static function get_trade_credit_balance_cents( $user_id ) {
		$v = get_user_meta( (int) $user_id, self::META_TRADE_CREDIT_CENTS, true );
		$n = is_numeric( $v ) ? (int) $v : 0;
		return max( 0, $n );
	}

	/**
	 * @param int    $buyer_id  Buyer.
	 * @param int    $seller_id Seller.
	 * @param int[]  $order_ids Order IDs from confirm UI.
	 * @param string $code      HB-… code.
	 * @return string
	 */
	private static function event_fingerprint( $buyer_id, $seller_id, array $order_ids, $code ) {
		$ids = array_values( array_unique( array_map( 'intval', array_filter( $order_ids ) ) ) );
		sort( $ids );
		$raw = (string) $code . '|' . (int) $buyer_id . '|' . (int) $seller_id . '|' . implode( ',', $ids );
		return hash( 'sha256', $raw );
	}

	/**
	 * @param int    $user_id User to store fingerprints on.
	 * @param string $meta_key User meta key (rebate or seller credit list).
	 * @param string $fp       Fingerprint.
	 * @return bool True if already applied.
	 */
	private static function fingerprint_seen( $user_id, $meta_key, $fp ) {
		$list = get_user_meta( (int) $user_id, $meta_key, true );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		return in_array( $fp, $list, true );
	}

	/**
	 * @param int    $user_id User.
	 * @param string $meta_key Meta key.
	 * @param string $fp       Fingerprint.
	 */
	private static function fingerprint_store( $user_id, $meta_key, $fp ) {
		$list = get_user_meta( (int) $user_id, $meta_key, true );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = $fp;
		if ( count( $list ) > self::FP_MAX_STORE ) {
			$list = array_slice( $list, -self::FP_MAX_STORE );
		}
		update_user_meta( (int) $user_id, $meta_key, $list );
	}

	/**
	 * @param int $user_id User.
	 * @param string $balance_meta Rebates or trade credit meta key.
	 * @param int $delta_cents Amount to add (>= 0).
	 */
	private static function add_balance_cents( $user_id, $balance_meta, $delta_cents ) {
		$user_id      = (int) $user_id;
		$delta_cents  = max( 0, (int) $delta_cents );
		if ( $user_id <= 0 || $delta_cents <= 0 ) {
			return;
		}
		$cur = get_user_meta( $user_id, $balance_meta, true );
		$cur = is_numeric( $cur ) ? (int) $cur : 0;
		update_user_meta( $user_id, $balance_meta, $cur + $delta_cents );
	}

	/**
	 * Whether this confirm includes at least one WooCommerce order owned by the buyer.
	 *
	 * @param int   $buyer_id  Buyer user ID.
	 * @param int[] $order_ids Order IDs.
	 * @return bool
	 */
	private static function has_buyer_owned_wc_order( $buyer_id, array $order_ids ) {
		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'Cpm_Humanblockchain_Woo_Backorders' ) ) {
			return false;
		}
		foreach ( $order_ids as $oid ) {
			$oid = (int) $oid;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( $order instanceof WC_Order && Cpm_Humanblockchain_Woo_Backorders::buyer_owns_wc_order( $order, $buyer_id ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * First Woo order in the batch the buyer owns (for order notes).
	 *
	 * @param int   $buyer_id  Buyer.
	 * @param int[] $order_ids IDs.
	 * @return WC_Order|null
	 */
	private static function first_buyer_owned_wc_order( $buyer_id, array $order_ids ) {
		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'Cpm_Humanblockchain_Woo_Backorders' ) ) {
			return null;
		}
		foreach ( $order_ids as $oid ) {
			$oid = (int) $oid;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( $order instanceof WC_Order && Cpm_Humanblockchain_Woo_Backorders::buyer_owns_wc_order( $order, $buyer_id ) ) {
				return $order;
			}
		}
		return null;
	}

	/**
	 * @param int    $buyer_id  Buyer.
	 * @param int    $seller_id Seller.
	 * @param int[]  $order_ids Order IDs.
	 * @param string $code      Transaction code.
	 */
	public static function on_buyer_confirmed_delivery( $buyer_id, $seller_id, $order_ids, $code ) {
		$buyer_id  = (int) $buyer_id;
		$seller_id = (int) $seller_id;
		$code      = is_string( $code ) ? $code : '';
		if ( ! is_array( $order_ids ) ) {
			$order_ids = array();
		}

		if ( ! (bool) apply_filters( 'cpm_hb_pod_wallet_apply_balances', true, $buyer_id, $seller_id, $order_ids, $code ) ) {
			return;
		}

		$require_wc = (bool) apply_filters( 'cpm_hb_pod_wallet_require_wc_order', true, $buyer_id, $seller_id, $order_ids, $code );
		if ( $require_wc && ! self::has_buyer_owned_wc_order( $buyer_id, $order_ids ) ) {
			return;
		}

		$fp = self::event_fingerprint( $buyer_id, $seller_id, $order_ids, $code );

		$rebate_usd = (float) apply_filters( 'cpm_hb_buyer_delivery_confirmed_rebate_usd', 5.0, $buyer_id, $seller_id, $order_ids, $code );
		$rebate_usd = max( 0, $rebate_usd );
		$credit_usd = (float) apply_filters( 'cpm_hb_seller_trade_credit_on_delivery_usd', 10.30, $buyer_id, $seller_id, $order_ids, $code );
		$credit_usd = max( 0, $credit_usd );

		$rebate_cents = (int) round( $rebate_usd * 100 );
		$credit_cents = (int) round( $credit_usd * 100 );

		$primary = self::first_buyer_owned_wc_order( $buyer_id, $order_ids );

		if ( $buyer_id > 0 && $rebate_cents > 0 && ! self::fingerprint_seen( $buyer_id, self::META_REBATE_FP, $fp ) ) {
			self::add_balance_cents( $buyer_id, self::META_REBATE_CENTS, $rebate_cents );
			self::fingerprint_store( $buyer_id, self::META_REBATE_FP, $fp );
			if ( $primary instanceof WC_Order ) {
				$prev = (int) $primary->get_meta( self::ORDER_META_REBATE_CENTS, true );
				$primary->update_meta_data( self::ORDER_META_REBATE_CENTS, $prev + $rebate_cents );
				$primary->save();
				$note = (string) apply_filters(
					'cpm_hb_pod_wallet_buyer_rebate_order_note',
					sprintf(
						/* translators: %s: formatted money */
						__( 'Human Blockchain: buyer rebate wallet credited %s for this delivery confirmation.', 'cpm-humanblockchain' ),
						wp_strip_all_tags( function_exists( 'wc_price' ) ? wc_price( $rebate_usd ) : ( '$' . number_format_i18n( $rebate_usd, 2 ) ) )
					),
					$rebate_usd,
					$buyer_id,
					$seller_id,
					$order_ids,
					$code,
					$primary
				);
				if ( $note !== '' ) {
					$primary->add_order_note( $note, false, true );
				}
			}
			/**
			 * Buyer rebate cents credited to user-meta wallet after PoD confirm.
			 *
			 * @param int    $buyer_id     Buyer user ID.
			 * @param int    $rebate_cents Amount added (cents).
			 * @param string $fp           Idempotency fingerprint.
			 * @param int[]  $order_ids    Order IDs from confirm UI.
			 * @param string $code         HB transaction code.
			 */
			do_action( 'cpm_hb_buyer_rebate_wallet_credited', $buyer_id, $rebate_cents, $fp, $order_ids, $code );
		}

		if ( $seller_id > 0 && $credit_cents > 0 && ! self::fingerprint_seen( $seller_id, self::META_SELLER_CREDIT_FP, $fp ) ) {
			self::add_balance_cents( $seller_id, self::META_TRADE_CREDIT_CENTS, $credit_cents );
			self::fingerprint_store( $seller_id, self::META_SELLER_CREDIT_FP, $fp );
			if ( $primary instanceof WC_Order ) {
				$prev = (int) $primary->get_meta( self::ORDER_META_CREDIT_CENTS, true );
				$primary->update_meta_data( self::ORDER_META_CREDIT_CENTS, $prev + $credit_cents );
				$primary->save();
				$note = (string) apply_filters(
					'cpm_hb_pod_wallet_seller_credit_order_note',
					sprintf(
						/* translators: 1: formatted money, 2: buyer WP user ID */
						__( 'Human Blockchain: seller trade credit wallet credited %1$s (buyer WP user %2$d).', 'cpm-humanblockchain' ),
						wp_strip_all_tags( function_exists( 'wc_price' ) ? wc_price( $credit_usd ) : ( '$' . number_format_i18n( $credit_usd, 2 ) ) ),
						$buyer_id
					),
					$credit_usd,
					$seller_id,
					$buyer_id,
					$order_ids,
					$code,
					$primary
				);
				if ( $note !== '' ) {
					$primary->add_order_note( $note, false, true );
				}
			}
			/**
			 * Seller trade credit cents credited to user-meta wallet after PoD confirm.
			 *
			 * @param int    $seller_id    Seller user ID.
			 * @param int    $credit_cents Amount added (cents).
			 * @param string $fp           Idempotency fingerprint.
			 * @param int    $buyer_id     Buyer user ID.
			 * @param int[]  $order_ids    Order IDs.
			 * @param string $code         HB transaction code.
			 */
			do_action( 'cpm_hb_seller_trade_credit_wallet_credited', $seller_id, $credit_cents, $fp, $buyer_id, $order_ids, $code );
		}
	}

	/**
	 * @param string $msg       Existing success message.
	 * @param int    $buyer_id  Buyer.
	 * @param int    $seller_id Seller.
	 * @param int[]  $order_ids Order IDs.
	 * @param string $code      HB code.
	 * @return string
	 */
	public static function append_wallet_summary_to_buyer_message( $msg, $buyer_id, $seller_id, $order_ids, $code ) {
		$buyer_id = (int) $buyer_id;
		if ( $buyer_id <= 0 ) {
			return $msg;
		}
		$reb_c = self::get_rebate_balance_cents( $buyer_id );
		if ( $reb_c <= 0 ) {
			return $msg;
		}
		$usd = $reb_c / 100.0;
		if ( function_exists( 'wc_price' ) ) {
			$bal = wp_strip_all_tags( wc_price( $usd ) );
		} else {
			$bal = '$' . number_format_i18n( $usd, 2 );
		}
		$msg .= ' ' . sprintf(
			/* translators: %s: formatted wallet balance */
			__( 'Your rebate wallet balance is now %s.', 'cpm-humanblockchain' ),
			$bal
		);
		return $msg;
	}

	/**
	 * WooCommerce My Account dashboard snippet.
	 */
	public static function render_account_balances() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$uid = get_current_user_id();
		$reb = self::get_rebate_balance_cents( $uid );
		$tc  = self::get_trade_credit_balance_cents( $uid );
		if ( $reb <= 0 && $tc <= 0 ) {
			return;
		}
		$fmt = static function ( $cents ) {
			$usd = max( 0, (int) $cents ) / 100.0;
			if ( function_exists( 'wc_price' ) ) {
				return wp_strip_all_tags( wc_price( $usd ) );
			}
			return '$' . number_format_i18n( $usd, 2 );
		};
		echo '<section class="cpm-hb-pod-wallet-dashboard" aria-label="' . esc_attr__( 'Human Blockchain wallets', 'cpm-humanblockchain' ) . '">';
		echo '<p class="cpm-hb-pod-wallet-dashboard__title">' . esc_html__( 'Human Blockchain wallets', 'cpm-humanblockchain' ) . '</p>';
		if ( $reb > 0 ) {
			echo '<p class="cpm-hb-pod-wallet-dashboard__line">' . esc_html__( 'Buyer rebate balance:', 'cpm-humanblockchain' ) . ' <strong class="cpm-hb-pod-wallet-dashboard__amount">' . esc_html( $fmt( $reb ) ) . '</strong></p>';
		}
		if ( $tc > 0 ) {
			echo '<p class="cpm-hb-pod-wallet-dashboard__line">' . esc_html__( 'Seller trade credit balance:', 'cpm-humanblockchain' ) . ' <strong class="cpm-hb-pod-wallet-dashboard__amount">' . esc_html( $fmt( $tc ) ) . '</strong></p>';
		}
		echo '<p class="cpm-hb-pod-wallet-dashboard__desc">' . esc_html__( 'Balances accrue when a delivery is confirmed on the backorders flow; XP rewards are separate.', 'cpm-humanblockchain' ) . '</p>';
		echo '</section>';
	}
}
