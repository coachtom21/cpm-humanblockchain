<?php
/**
 * Proof-of-delivery two-scan rules: elapsed time + Haversine distance (NWP Gateway settings).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores seller scan-1 anchor (per transaction code) and validates buyer scan-2 at OTP verify.
 */
class Cpm_Humanblockchain_Two_Scan_Validator {

	const TRANSIENT_PREFIX = 'cpm_hb_pod1_';

	/**
	 * Earth radius in meters (mean).
	 */
	const EARTH_RADIUS_M = 6371000.0;

	/**
	 * @param string $transaction_code HB-… code.
	 * @return string
	 */
	public static function transient_key( $transaction_code ) {
		$code = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );

		return self::TRANSIENT_PREFIX . md5( $code );
	}

	/**
	 * @param string $post_key $_POST key.
	 * @return float|null
	 */
	public static function parse_pod_geo_from_post( $post_key ) {
		if ( ! isset( $_POST[ $post_key ] ) ) {
			return null;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
		if ( $raw === '' || ! is_numeric( $raw ) ) {
			return null;
		}
		$f = (float) $raw;
		if ( 'cpm_hb_pod_geo_lat' === $post_key ) {
			if ( $f < -90 || $f > 90 ) {
				return null;
			}
		} elseif ( 'cpm_hb_pod_geo_lng' === $post_key ) {
			if ( $f < -180 || $f > 180 ) {
				return null;
			}
		}

		return $f;
	}

	/**
	 * Haversine distance in meters.
	 *
	 * @param float $lat1 Latitude degrees.
	 * @param float $lon1 Longitude degrees.
	 * @param float $lat2 Latitude degrees.
	 * @param float $lon2 Longitude degrees.
	 * @return float
	 */
	public static function haversine_m( $lat1, $lon1, $lat2, $lon2 ) {
		$lat1 = deg2rad( $lat1 );
		$lon1 = deg2rad( $lon1 );
		$lat2 = deg2rad( $lat2 );
		$lon2 = deg2rad( $lon2 );
		$dlat = $lat2 - $lat1;
		$dlon = $lon2 - $lon1;
		$h    = sin( $dlat / 2 ) * sin( $dlat / 2 ) + cos( $lat1 ) * cos( $lat2 ) * sin( $dlon / 2 ) * sin( $dlon / 2 );
		$h    = min( 1.0, max( 0.0, $h ) );
		$c    = 2 * atan2( sqrt( $h ), sqrt( 1 - $h ) );

		return self::EARTH_RADIUS_M * $c;
	}

	/**
	 * Persist seller scan-1 position and server time for later buyer checks.
	 *
	 * @param int         $seller_wp_user_id Seller WordPress user ID.
	 * @param string      $transaction_code  HB-… code.
	 * @param float|null  $lat               WGS84 latitude.
	 * @param float|null  $lng               WGS84 longitude.
	 */
	public static function record_seller_scan_anchor( $seller_wp_user_id, $transaction_code, $lat, $lng, $anchor_unix_ts = null ) {
		$seller_wp_user_id = (int) $seller_wp_user_id;
		$transaction_code  = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );
		if ( $seller_wp_user_id <= 0 || ! preg_match( '/^HB-[A-F0-9]{16}$/', $transaction_code ) ) {
			return;
		}

		$ts = null !== $anchor_unix_ts ? (int) $anchor_unix_ts : time();

		$data = array(
			'seller_id' => $seller_wp_user_id,
			'lat'       => null !== $lat ? (float) $lat : null,
			'lng'       => null !== $lng ? (float) $lng : null,
			'ts'        => $ts,
		);

		$ttl = (int) apply_filters( 'cpm_hb_pod_scan1_transient_ttl', 7 * DAY_IN_SECONDS );
		$ttl = min( 30 * DAY_IN_SECONDS, max( 300, $ttl ) );

		set_transient( self::transient_key( $transaction_code ), $data, $ttl );
	}

	/**
	 * Transient: buyer already passed two-scan at OTP verify for this HB code (grace window for backorders confirm).
	 *
	 * @param int    $buyer_wp_user_id Buyer user ID.
	 * @param string $transaction_code HB-… code.
	 * @return string
	 */
	private static function buyer_otp_two_scan_ok_transient_key( $buyer_wp_user_id, $transaction_code ) {
		$code = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );

		return 'cpm_hb_pod2_otp_' . md5( $code ) . '_' . (int) $buyer_wp_user_id;
	}

	/**
	 * After buyer OTP + two-scan succeeds, allow confirm-delivery without re-checking seller-anchor elapsed time
	 * (buyer may pick orders after the short two-scan window).
	 *
	 * @param int    $buyer_wp_user_id Buyer WordPress user ID.
	 * @param string $transaction_code HB-… code used at OTP.
	 */
	public static function remember_buyer_two_scan_passed_at_otp( $buyer_wp_user_id, $transaction_code ) {
		if ( ! apply_filters( 'cpm_hb_two_scan_validation_enabled', true ) ) {
			return;
		}
		$buyer_wp_user_id = (int) $buyer_wp_user_id;
		$code             = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );
		if ( $buyer_wp_user_id <= 0 || ! preg_match( '/^HB-[A-F0-9]{16}$/', $code ) ) {
			return;
		}
		$ttl = (int) apply_filters( 'cpm_hb_buyer_otp_two_scan_confirm_grace_ttl', 24 * HOUR_IN_SECONDS );
		$ttl = min( 7 * DAY_IN_SECONDS, max( 600, $ttl ) );
		set_transient( self::buyer_otp_two_scan_ok_transient_key( $buyer_wp_user_id, $code ), time(), $ttl );
	}

	/**
	 * Whether buyer already satisfied two-scan at OTP for this code within the grace TTL.
	 *
	 * @param int    $buyer_wp_user_id Buyer user ID.
	 * @param string $transaction_code HB-… code.
	 * @return bool
	 */
	public static function buyer_otp_two_scan_covers_confirm( $buyer_wp_user_id, $transaction_code ) {
		$buyer_wp_user_id = (int) $buyer_wp_user_id;
		$code             = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );
		if ( $buyer_wp_user_id <= 0 || ! preg_match( '/^HB-[A-F0-9]{16}$/', $code ) ) {
			return false;
		}
		$ts = get_transient( self::buyer_otp_two_scan_ok_transient_key( $buyer_wp_user_id, $code ) );

		return is_numeric( $ts );
	}

	/**
	 * Validate buyer scan-2 vs seller anchor: elapsed time + distance (and buyer geo when enabled).
	 *
	 * Used for OTP verify (guest/logged-out path) and for logged-in backorders confirm when OTP grace does not apply.
	 *
	 * @param string   $transaction_code Normalized or raw HB-… code.
	 * @param float|null $buyer_lat       Buyer WGS84 latitude or null if missing.
	 * @param float|null $buyer_lng       Buyer WGS84 longitude or null if missing.
	 * @param string     $geo_error_context Optional: 'otp' | 'confirm' for message wording.
	 * @param int[]|null $order_ids         When context is confirm, Woo order IDs selected by the buyer (optional).
	 * @return true|WP_Error
	 */
	public static function validate_buyer_two_scan( $transaction_code, $buyer_lat, $buyer_lng, $geo_error_context = 'otp', $order_ids = null ) {
		if ( ! apply_filters( 'cpm_hb_two_scan_validation_enabled', true ) ) {
			return true;
		}

		if ( ! class_exists( 'Cpm_Humanblockchain_Nwp_Gateway_Config' ) ) {
			return true;
		}

		$max_sec = Cpm_Humanblockchain_Nwp_Gateway_Config::get_two_scan_max_seconds();
		$max_m   = Cpm_Humanblockchain_Nwp_Gateway_Config::get_two_scan_max_distance_m();

		$code = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );

		if ( ! preg_match( '/^HB-[A-F0-9]{16}$/', $code ) ) {
			return new WP_Error(
				'cpm_hb_pod_tx',
				__( 'Enter the seller’s delivery code (HB-…) they received after verifying, then try again.', 'cpm-humanblockchain' )
			);
		}

		$anchor = get_transient( self::transient_key( $code ) );
		if ( ! is_array( $anchor ) || empty( $anchor['ts'] ) ) {
			return new WP_Error(
				'cpm_hb_pod_tx',
				__( 'That delivery code is unknown or has expired. Confirm the code with the seller and try again.', 'cpm-humanblockchain' )
			);
		}

		$order_ids = is_array( $order_ids ) ? array_values( array_map( 'intval', $order_ids ) ) : null;
		if ( ! self::should_enforce_time_distance( $code, $geo_error_context, $order_ids ) ) {
			return true;
		}

		if ( null === $buyer_lat || null === $buyer_lng ) {
			$geo_msg = 'confirm' === $geo_error_context
				? __( 'Location is required for delivery proof. Allow location access on this device, then confirm delivery again.', 'cpm-humanblockchain' )
				: __( 'Location is required for delivery proof. Allow location access on this device, then verify again.', 'cpm-humanblockchain' );

			return new WP_Error( 'cpm_hb_pod_geo', $geo_msg );
		}

		$elapsed = time() - (int) $anchor['ts'];
		if ( $elapsed > $max_sec ) {
			return new WP_Error(
				'cpm_hb_pod_time',
				sprintf(
					/* translators: %d: max allowed minutes (rounded up) */
					__( 'Too much time has passed since the seller verified this delivery (limit %d minutes). Ask the seller to verify again and share the new code.', 'cpm-humanblockchain' ),
					(int) max( 1, ceil( $max_sec / 60 ) )
				)
			);
		}

		$s_lat = isset( $anchor['lat'] ) && is_numeric( $anchor['lat'] ) ? (float) $anchor['lat'] : null;
		$s_lng = isset( $anchor['lng'] ) && is_numeric( $anchor['lng'] ) ? (float) $anchor['lng'] : null;
		if ( null === $s_lat || null === $s_lng ) {
			return new WP_Error(
				'cpm_hb_pod_anchor',
				__( 'The seller’s scan did not record a location. They must allow location when verifying, then share the new code.', 'cpm-humanblockchain' )
			);
		}

		$dist = self::haversine_m( $s_lat, $s_lng, $buyer_lat, $buyer_lng );
		if ( $dist > $max_m ) {
			return new WP_Error(
				'cpm_hb_pod_distance',
				sprintf(
					/* translators: %d: max distance in meters */
					__( 'This device is farther than allowed from the seller’s scan location (limit %d m). Complete the buyer scan near the handoff point.', 'cpm-humanblockchain' ),
					$max_m
				)
			);
		}

		return true;
	}

	/**
	 * When “capped NWP only” mode is on, skip elapsed time / distance / buyer GPS unless the flow qualifies.
	 *
	 * @param string     $transaction_code HB-… code (normalized).
	 * @param string     $geo_error_context  otp|confirm.
	 * @param int[]|null $order_ids          Selected Woo order IDs at confirm (may be empty).
	 * @return bool True = run full geo + time + distance checks.
	 */
	public static function should_enforce_time_distance( $transaction_code, $geo_error_context, $order_ids ) {
		unset( $transaction_code );
		if ( ! class_exists( 'Cpm_Humanblockchain_Nwp_Gateway_Config' )
			|| ! Cpm_Humanblockchain_Nwp_Gateway_Config::two_scan_geo_only_for_capped_nwp_orders() ) {
			return true;
		}
		if ( 'confirm' === $geo_error_context && class_exists( 'Cpm_Humanblockchain_Woo_Backorders' )
			&& is_array( $order_ids ) && array() !== $order_ids ) {
			return Cpm_Humanblockchain_Woo_Backorders::orders_require_geo_two_scan( $order_ids );
		}
		if ( 'otp' === $geo_error_context ) {
			return (bool) apply_filters( 'cpm_hb_two_scan_enforce_geo_at_otp_capped_only_mode', false );
		}
		return (bool) apply_filters(
			'cpm_hb_two_scan_enforce_geo_confirm_fallback',
			false,
			$order_ids,
			$geo_error_context
		);
	}

	/**
	 * Validate buyer ?proof=scan handoff: time + distance vs seller anchor (posted transaction code + geo).
	 * Called from verify-OTP handler before the SMS code is consumed so a failed check does not burn the OTP.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_buyer_after_otp() {
		$raw = isset( $_POST['cpm_hb_seller_transaction_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cpm_hb_seller_transaction_code'] ) ) : '';
		$code = strtoupper( trim( preg_replace( '/\s+/', '', $raw ) ) );

		$buyer_lat = self::parse_pod_geo_from_post( 'cpm_hb_pod_geo_lat' );
		$buyer_lng = self::parse_pod_geo_from_post( 'cpm_hb_pod_geo_lng' );

		return self::validate_buyer_two_scan( $code, $buyer_lat, $buyer_lng, 'otp' );
	}
}
