<?php
/**
 * Register User REST route helpers (POST /wp-json/myapi/v1/register-user).
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * URL and Bearer key resolution for the register-user API (used by server-side proxies).
 */
class Cpm_Humanblockchain_Register_User_Api {

	const OPTION_ENDPOINT = 'cpm_hb_register_user_api_endpoint';

	const OPTION_KEY = 'cpm_hb_register_user_api_key';

	/**
	 * Full POST URL (custom option or default REST route on this site).
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		$custom = trim( (string) get_option( self::OPTION_ENDPOINT, '' ) );
		if ( $custom !== '' && filter_var( $custom, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $custom );
		}
		return rest_url( 'myapi/v1/register-user' );
	}

	/**
	 * Bearer token: optional dedicated key, else same as Membership API (smallstreet_api_key).
	 *
	 * @return string
	 */
	public static function get_api_key() {
		$dedicated = trim( (string) get_option( self::OPTION_KEY, '' ) );
		if ( $dedicated !== '' ) {
			return $dedicated;
		}
		if ( class_exists( 'Cpm_Humanblockchain_Membership' ) ) {
			return Cpm_Humanblockchain_Membership::get_api_key();
		}
		return trim( (string) get_option( 'smallstreet_api_key', '' ) );
	}

	/**
	 * Whether guest device registration should use the register-user REST API (key configured).
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return self::get_api_key() !== '';
	}

	/**
	 * At least 8 digits — required before calling the remote register-user API.
	 *
	 * @param string $phone Raw phone.
	 * @return bool
	 */
	public static function phone_has_enough_digits_for_api( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		return strlen( $digits ) >= 8;
	}

	/**
	 * Find a user ID by `phone` user meta (E.164 when possible).
	 *
	 * @param string $mobile_raw Raw phone input.
	 * @return int
	 */
	private static function find_user_id_by_phone_meta( $mobile_raw ) {
		if ( class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ) {
			$e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile_raw );
			if ( $e164 ) {
				$users = get_users(
					array(
						'meta_key'   => 'phone',
						'meta_value' => $e164,
						'number'     => 1,
						'fields'     => 'ID',
					)
				);
				if ( ! empty( $users ) ) {
					return (int) $users[0];
				}
			}
		}
		return 0;
	}

	/**
	 * Same hostname as this site (internal REST — avoids wp_remote_post loopback failures).
	 *
	 * @param string $url Endpoint URL.
	 * @return bool
	 */
	private static function endpoint_is_same_site( $url ) {
		$h1 = wp_parse_url( $url, PHP_URL_HOST );
		$h2 = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $h1 ) || $h1 === '' || ! is_string( $h2 ) || $h2 === '' ) {
			return false;
		}
		return strtolower( $h1 ) === strtolower( $h2 );
	}

	/**
	 * REST route path (e.g. /myapi/v1/register-user) from full endpoint URL.
	 *
	 * @param string $url Full URL.
	 * @return string
	 */
	private static function rest_route_from_endpoint_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '/myapi/v1/register-user';
		}
		$prefix = '/' . rest_get_url_prefix();
		$pos    = strpos( $path, $prefix );
		if ( false !== $pos ) {
			$route = substr( $path, $pos + strlen( $prefix ) );
			return '' !== $route ? $route : '/myapi/v1/register-user';
		}
		return '/myapi/v1/register-user';
	}

	/**
	 * Extract user_id from various REST JSON shapes.
	 *
	 * @param array $data Decoded JSON.
	 * @return int
	 */
	private static function extract_user_id_from_payload( array $data ) {
		if ( ! empty( $data['user_id'] ) ) {
			return (int) $data['user_id'];
		}
		if ( ! empty( $data['data']['user_id'] ) ) {
			return (int) $data['data']['user_id'];
		}
		return 0;
	}

	/**
	 * Run register-user via internal REST (same site) or HTTP (remote).
	 *
	 * @param string $url     Endpoint URL.
	 * @param string $api_key Bearer key.
	 * @param array  $body    JSON body array.
	 * @return array{ code: int, data: array|null, raw: string }|WP_Error
	 */
	private static function execute_request( $url, $api_key, array $body ) {
		$json = wp_json_encode( $body );

		$use_internal = apply_filters( 'cpm_hb_register_user_use_internal_rest', true, $url );
		if ( $use_internal && self::endpoint_is_same_site( $url ) && function_exists( 'rest_do_request' ) ) {
			$route   = self::rest_route_from_endpoint_url( $url );
			$request = new WP_REST_Request( 'POST', $route );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_header( 'Authorization', 'Bearer ' . $api_key );
			$request->set_body( $json );

			$response = rest_do_request( $request );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( $response instanceof WP_REST_Response ) {
				$status = $response->get_status();
				$data   = $response->get_data();
				return array(
					'code' => $status,
					'data' => is_array( $data ) ? $data : array(),
					'raw'  => wp_json_encode( $data ),
				);
			}
			return new WP_Error( 'register_user_bad_rest', __( 'Unexpected REST response.', 'cpm-humanblockchain' ) );
		}

		$default_ssl = true;
		if ( function_exists( 'wp_get_environment_type' ) ) {
			$default_ssl = ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
		}

		$req_args = apply_filters(
			'cpm_hb_register_user_remote_post_args',
			array(
				'timeout'   => 30,
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'      => $json,
				'sslverify' => apply_filters( 'cpm_hb_register_user_sslverify', $default_ssl, $url ),
			),
			$url,
			$body
		);

		$resp = wp_remote_post( $url, $req_args );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : array(),
			'raw'  => $raw,
		);
	}

	/**
	 * POST /myapi/v1/register-user for guest device registration.
	 *
	 * @param array $args Keys: email, mobile, geo_lat, geo_lng, device_hash, referral (int), qrtiger (string URL).
	 * @return array|\WP_Error {
	 *   @type int  $user_id     WordPress user ID.
	 *   @type bool $created_new True if the API reported a newly created user (for rollback hints).
	 * }
	 */
	public static function register_user_for_device( array $args ) {
		$email = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'cpm-humanblockchain' ) );
		}

		$mobile = isset( $args['mobile'] ) ? sanitize_text_field( $args['mobile'] ) : '';
		if ( ! self::phone_has_enough_digits_for_api( $mobile ) ) {
			return new WP_Error(
				'phone_required',
				__( 'Mobile number is required (at least 8 digits) when Register User API is enabled.', 'cpm-humanblockchain' )
			);
		}

		$api_key = self::get_api_key();
		if ( $api_key === '' ) {
			return new WP_Error( 'no_api_key', __( 'Register User API is not configured.', 'cpm-humanblockchain' ) );
		}

		$phone_out = $mobile;
		if ( class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ) {
			$e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile );
			if ( $e164 ) {
				$phone_out = $e164;
			}
		}

		$body = array(
			'email' => $email,
			'phone' => $phone_out,
		);

		$geo_lat = isset( $args['geo_lat'] ) ? $args['geo_lat'] : null;
		$geo_lng = isset( $args['geo_lng'] ) ? $args['geo_lng'] : null;
		if ( null !== $geo_lat && '' !== $geo_lat && is_numeric( $geo_lat ) ) {
			$body['geo_lat'] = (float) $geo_lat;
		}
		if ( null !== $geo_lng && '' !== $geo_lng && is_numeric( $geo_lng ) ) {
			$body['geo_lng'] = (float) $geo_lng;
		}

		$device_hash = isset( $args['device_hash'] ) ? sanitize_text_field( $args['device_hash'] ) : '';
		if ( $device_hash !== '' ) {
			$body['device_hash'] = $device_hash;
		}

		$referral = isset( $args['referral'] ) ? absint( $args['referral'] ) : 0;
		if ( $referral > 0 ) {
			$body['referral_source_nwp_id'] = (string) $referral;
		}

		$qrtiger = isset( $args['qrtiger'] ) ? esc_url_raw( $args['qrtiger'] ) : '';
		if ( $qrtiger !== '' ) {
			$body['qrtiger_vcard_link'] = $qrtiger;
		}

		$url = self::get_endpoint_url();
		$res = self::execute_request( $url, $api_key, $body );

		if ( is_wp_error( $res ) ) {
			return new WP_Error(
				'register_user_http',
				sprintf(
					/* translators: %s: error message */
					__( 'Register User request failed: %s', 'cpm-humanblockchain' ),
					$res->get_error_message()
				)
			);
		}

		$code = (int) $res['code'];
		$data = $res['data'];

		$uid = self::extract_user_id_from_payload( $data );
		$ok  = ( $code >= 200 && $code < 300 ) && ! empty( $data['success'] ) && $uid > 0;

		if ( $ok ) {
			$created = isset( $data['action'] ) && 'created' === $data['action'];
			if ( ! $created && ! empty( $data['password_generated'] ) ) {
				$created = true;
			}
			return array(
				'user_id'     => $uid,
				'created_new' => $created,
			);
		}

		if ( 409 === $code && isset( $data['code'] ) && 'user_already_exists' === $data['code'] ) {
			$matched = isset( $data['matched_by'] ) ? (string) $data['matched_by'] : '';
			if ( 'email' === $matched ) {
				$exists = email_exists( $email );
				if ( $exists ) {
					return array(
						'user_id'     => (int) $exists,
						'created_new' => false,
					);
				}
			}
			if ( 'phone' === $matched ) {
				$puid = self::find_user_id_by_phone_meta( $mobile );
				if ( $puid > 0 ) {
					return array(
						'user_id'     => $puid,
						'created_new' => false,
					);
				}
			}
			return new WP_Error(
				'user_conflict',
				isset( $data['message'] ) ? (string) $data['message'] : __( 'User already exists.', 'cpm-humanblockchain' )
			);
		}

		$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'Register User API refused the request.', 'cpm-humanblockchain' );
		return new WP_Error(
			'register_user_failed',
			$msg
		);
	}
}
