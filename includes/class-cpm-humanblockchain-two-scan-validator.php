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
	public static function record_seller_scan_anchor( $seller_wp_user_id, $transaction_code, $lat, $lng ) {
		$seller_wp_user_id = (int) $seller_wp_user_id;
		$transaction_code  = strtoupper( trim( preg_replace( '/\s+/', '', (string) $transaction_code ) ) );
		if ( $seller_wp_user_id <= 0 || ! preg_match( '/^HB-[A-F0-9]{16}$/', $transaction_code ) ) {
			return;
		}

		$data = array(
			'seller_id' => $seller_wp_user_id,
			'lat'       => null !== $lat ? (float) $lat : null,
			'lng'       => null !== $lng ? (float) $lng : null,
			'ts'        => time(),
		);

		$ttl = (int) apply_filters( 'cpm_hb_pod_scan1_transient_ttl', 7 * DAY_IN_SECONDS );
		$ttl = min( 30 * DAY_IN_SECONDS, max( 300, $ttl ) );

		set_transient( self::transient_key( $transaction_code ), $data, $ttl );
	}

	/**
	 * Validate buyer ?proof=scan handoff: time + distance vs seller anchor (posted transaction code + geo).
	 * Called from verify-OTP handler before the SMS code is consumed so a failed check does not burn the OTP.
	 *
	 * @return true|WP_Error
	 */
	public static function validate_buyer_after_otp() {
		if ( ! apply_filters( 'cpm_hb_two_scan_validation_enabled', true ) ) {
			return true;
		}

		if ( ! class_exists( 'Cpm_Humanblockchain_Nwp_Gateway_Config' ) ) {
			return true;
		}

		$max_sec = Cpm_Humanblockchain_Nwp_Gateway_Config::get_two_scan_max_seconds();
		$max_m   = Cpm_Humanblockchain_Nwp_Gateway_Config::get_two_scan_max_distance_m();

		$raw = isset( $_POST['cpm_hb_seller_transaction_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cpm_hb_seller_transaction_code'] ) ) : '';
		$code = strtoupper( trim( preg_replace( '/\s+/', '', $raw ) ) );

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

		$buyer_lat = self::parse_pod_geo_from_post( 'cpm_hb_pod_geo_lat' );
		$buyer_lng = self::parse_pod_geo_from_post( 'cpm_hb_pod_geo_lng' );
		if ( null === $buyer_lat || null === $buyer_lng ) {
			return new WP_Error(
				'cpm_hb_pod_geo',
				__( 'Location is required for delivery proof. Allow location access on this device, then verify again.', 'cpm-humanblockchain' )
			);
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
}
