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

	/** User lookup by mobile (buyer PoD pre-check). */
	const OPTION_USER_BY_MOBILE_URL = 'cpm_hb_smallstreet_user_by_mobile_url';

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
	 * user-by-mobile REST route on Smallstreet.
	 *
	 * @return string
	 */
	public static function get_user_by_mobile_endpoint_url() {
		$default = 'https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/user-by-mobile';
		$u       = trim( (string) get_option( self::OPTION_USER_BY_MOBILE_URL, $default ) );
		if ( $u === '' ) {
			$u = $default;
		}
		return esc_url_raw( $u );
	}

	/**
	 * Default key (override or clear in Settings → NWP Gateway).
	 * Remote endpoint expects header X-Dongtrader-Backorders-Key (see backorders-by-mobile).
	 *
	 * @return string
	 */
	private static function default_api_key() {
		return '7JTQhndCeIw4VFNMKPep7eNAOfPPDGMidfIeuLVWvj3QqwMc';
	}

	/**
	 * API key (stored in options; not exposed to frontend).
	 * Optional: define CPM_HB_SMALLSTREET_BACKORDERS_API_KEY in wp-config (same key as Smallstreet).
	 * If the option was never saved, uses the built-in default. If explicitly cleared (empty string in DB), returns ''.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		if ( defined( 'CPM_HB_SMALLSTREET_BACKORDERS_API_KEY' ) && is_string( CPM_HB_SMALLSTREET_BACKORDERS_API_KEY ) && self::trim_key( CPM_HB_SMALLSTREET_BACKORDERS_API_KEY ) !== '' ) {
			return self::trim_key( CPM_HB_SMALLSTREET_BACKORDERS_API_KEY );
		}
		$stored = get_option( self::OPTION_KEY, null );
		if ( null === $stored ) {
			$key = self::default_api_key();
		} else {
			$key = self::trim_key( (string) $stored );
		}
		return apply_filters( 'cpm_hb_smallstreet_backorders_api_key', $key );
	}

	/**
	 * @param string $k Raw key.
	 * @return string
	 */
	private static function trim_key( $k ) {
		return trim( (string) $k );
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
	 * Auth headers shared by Dongtrader mobile routes.
	 *
	 * @param string $key API key.
	 * @return array<string, string>
	 */
	private static function mobile_route_headers( $key ) {
		return array(
			'Content-Type'                => 'application/json',
			'Accept'                      => 'application/json',
			'X-Dongtrader-Backorders-Key' => $key,
			'Authorization'               => 'Bearer ' . $key,
		);
	}

	/**
	 * POST (then GET with ?mobile=) to user-by-mobile.
	 *
	 * @param string $mobile_raw Phone as entered or E.164.
	 * @return array{ code: int, data: array|null, body: string, method: string }|WP_Error
	 */
	public static function request_user_by_mobile( $mobile_raw ) {
		if ( ! self::is_configured() ) {
			return new WP_Error(
				'smallstreet_not_configured',
				__( 'Smallstreet API is not configured.', 'cpm-humanblockchain' )
			);
		}

		$url    = self::get_user_by_mobile_endpoint_url();
		$key    = self::get_api_key();
		$digits = self::mobile_digits_for_json( $mobile_raw );
		$body   = wp_json_encode( array( 'mobile' => $digits ) );

		$post_args = apply_filters(
			'cpm_hb_smallstreet_user_by_mobile_request_args',
			array(
				'timeout'   => 20,
				'headers'   => self::mobile_route_headers( $key ),
				'body'      => $body,
				'sslverify' => true,
			),
			$url,
			$mobile_raw,
			'POST'
		);

		$resp = wp_remote_post( $url, $post_args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );

		$used_get = false;
		if ( $code === 404 || $code === 405 ) {
			$get_url = add_query_arg( 'mobile', $digits, $url );
			$headers = array(
				'Accept'                      => 'application/json',
				'X-Dongtrader-Backorders-Key' => $key,
				'Authorization'               => 'Bearer ' . $key,
			);
			$get_args = apply_filters(
				'cpm_hb_smallstreet_user_by_mobile_request_args',
				array(
					'timeout'   => 20,
					'headers'   => $headers,
					'sslverify' => true,
				),
				$get_url,
				$mobile_raw,
				'GET'
			);
			$resp = wp_remote_get( $get_url, $get_args );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$used_get = true;
			$code     = (int) wp_remote_retrieve_response_code( $resp );
			$raw      = wp_remote_retrieve_body( $resp );
			$data     = json_decode( $raw, true );
		}

		return array(
			'code'   => $code,
			'data'   => is_array( $data ) ? $data : null,
			'body'   => $raw,
			'method' => $used_get ? 'GET' : 'POST',
		);
	}

	/**
	 * Whether JSON body from user-by-mobile indicates a matched user.
	 *
	 * @param array<string, mixed> $data Decoded JSON.
	 * @return bool
	 */
	private static function parse_user_by_mobile_found( array $data ) {
		if ( isset( $data['success'] ) && ! $data['success'] ) {
			return false;
		}
		if ( ! empty( $data['user']['id'] ) ) {
			return true;
		}
		if ( isset( $data['id'] ) && is_numeric( $data['id'] ) && (int) $data['id'] > 0 ) {
			return true;
		}
		if ( isset( $data['user'] ) && is_array( $data['user'] ) && ! empty( $data['user'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * True when Smallstreet returns a user for this mobile (buyer PoD gate before OTP).
	 *
	 * @param string $mobile_raw Raw phone.
	 * @return bool
	 */
	public static function user_exists_by_mobile( $mobile_raw ) {
		$res = self::request_user_by_mobile( $mobile_raw );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			return false;
		}
		$data = $res['data'];
		if ( ! is_array( $data ) ) {
			return false;
		}
		return self::parse_user_by_mobile_found( $data );
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
				'timeout'   => 20,
				'headers'   => array(
					'Content-Type'                  => 'application/json',
					'X-Dongtrader-Backorders-Key'   => $key,
					// Legacy / alternate auth some stacks accept.
					'Authorization'                 => 'Bearer ' . $key,
				),
				'body'      => $body,
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
	 * Backorder rows for the Human Blockchain backorder page (after buyer OTP verify redirect).
	 *
	 * @param string $mobile_raw Raw mobile (same as verify_send).
	 * @return array<int, array<string, mixed>>|array{} List of order rows for table display.
	 */
	public static function get_backorders_for_display( $mobile_raw ) {
		$res = self::request_backorders_by_mobile( $mobile_raw );
		if ( is_wp_error( $res ) ) {
			return array();
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 || ! is_array( $res['data'] ) ) {
			return array();
		}
		$d = $res['data'];
		if ( isset( $d['success'] ) && ! $d['success'] ) {
			$rows = array();
		} elseif ( isset( $d['backorders'] ) && is_array( $d['backorders'] ) ) {
			$rows = $d['backorders'];
		} else {
			$rows = array();
		}
		/**
		 * Parsed rows for the backorder page table, or replace if the API uses a different JSON shape.
		 *
		 * @param array<int, array<string, mixed>> $rows Order rows.
		 * @param array<string, mixed>              $data Raw decoded JSON body.
		 * @param array{ code: int, data: array|null, body: string } $res Full HTTP result.
		 * @param string                             $mobile_raw Request input.
		 */
		return apply_filters( 'cpm_hb_smallstreet_backorders_display_rows', $rows, $d, $res, $mobile_raw );
	}

	/**
	 * True if Smallstreet recognizes this mobile for backorders (HTTP 2xx and JSON success when present).
	 *
	 * @param string $mobile_raw Raw phone.
	 * @return bool
	 */
	public static function mobile_recognized_for_backorders( $mobile_raw ) {
		$res = self::request_backorders_by_mobile( $mobile_raw );
		if ( is_wp_error( $res ) ) {
			return false;
		}
		if ( $res['code'] < 200 || $res['code'] >= 300 ) {
			return false;
		}
		$data = $res['data'];
		if ( is_array( $data ) && array_key_exists( 'success', $data ) ) {
			return (bool) $data['success'];
		}
		return true;
	}
}
