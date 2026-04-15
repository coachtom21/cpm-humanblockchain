<?php
/**
 * Smallstreet — backorders-by-mobile REST (buyer PoD scan flow).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/backorders-by-mobile
 */
class Cpm_Humanblockchain_Smallstreet_Backorders {

	const OPTION_URL = 'cpm_hb_smallstreet_backorders_url';

	const OPTION_KEY = 'cpm_hb_smallstreet_backorders_api_key';

	/**
	 * Endpoint URL.
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		$default = 'https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/backorders-by-mobile';
		$u       = trim( (string) get_option( self::OPTION_URL, $default ) );
		if ( $u === '' ) {
			$u = $default;
		}
		return esc_url_raw( $u );
	}

	/**
	 * Default Bearer key (override or clear in Settings → NWP Gateway).
	 *
	 * @return string
	 */
	private static function default_api_key() {
		return '7JTQhndCeIw4VFNMKPep7eNAOfPPDGMidfIeuLVWvj3QqwMc';
	}

	/**
	 * Bearer token (stored in options; not exposed to frontend).
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return trim( (string) get_option( self::OPTION_KEY, self::default_api_key() ) );
	}

	/**
	 * Whether the integration can call Smallstreet.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return self::get_api_key() !== '';
	}

	/**
	 * Digits-only mobile for JSON body (example: 5551234567).
	 *
	 * @param string $mobile_raw Raw input.
	 * @return string
	 */
	private static function mobile_digits_for_json( $mobile_raw ) {
		return preg_replace( '/\D/', '', (string) $mobile_raw );
	}

	/**
	 * POST JSON to backorders-by-mobile.
	 *
	 * @param string $mobile_raw Phone as entered or E.164.
	 * @return array{ code: int, data: array|null, body: string }|WP_Error
	 */
	public static function request_backorders_by_mobile( $mobile_raw ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'smallstreet_not_configured',
				__( 'Smallstreet backorders API is not configured.', 'cpm-humanblockchain' )
			);
		}

		$url  = self::get_endpoint_url();
		$key  = self::get_api_key();
		$body = wp_json_encode(
			array(
				'mobile' => self::mobile_digits_for_json( $mobile_raw ),
			)
		);

		$args = apply_filters(
			'cpm_hb_smallstreet_backorders_request_args',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
				'body'    => $body,
				'sslverify' => true,
			),
			$url,
			$mobile_raw
		);

		$resp = wp_remote_post( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : null,
			'body' => $raw,
		);
	}

	/**
	 * True if Smallstreet recognizes this mobile for backorders (HTTP 2xx).
	 *
	 * @param string $mobile_raw Raw phone.
	 * @return bool
	 */
	public static function mobile_recognized_for_backorders( $mobile_raw ) {
		$res = self::request_backorders_by_mobile( $mobile_raw );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		return $res['code'] >= 200 && $res['code'] < 300;
	}
}
