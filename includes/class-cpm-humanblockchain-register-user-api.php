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
	 * At least 8 digits (same rule as membership API).
	 *
	 * @param string $phone Raw phone.
	 * @return bool
	 */
	private static function phone_has_enough_digits( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		return strlen( $digits ) >= 8;
	}

	/**
	 * Find a user ID by `phone` user meta (E.164 when possible).
	 *
	 * @param string $mobile_raw Raw mobile input.
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
		if ( ! self::phone_has_enough_digits( $mobile ) ) {
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

		$url  = self::get_endpoint_url();
		$resp = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $resp ) ) {
			return new WP_Error(
				'register_user_http',
				sprintf(
					/* translators: %s: error message */
					__( 'Register User request failed: %s', 'cpm-humanblockchain' ),
					$resp->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $resp );
		$raw  = wp_remote_retrieve_body( $resp );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'register_user_bad_json',
				__( 'Register User API returned an unexpected response.', 'cpm-humanblockchain' )
			);
		}

		$ok = ( $code >= 200 && $code < 300 ) && ! empty( $data['success'] ) && ! empty( $data['user_id'] );
		if ( $ok ) {
			$created = isset( $data['action'] ) && 'created' === $data['action'];
			if ( ! $created && isset( $data['password_generated'] ) && $data['password_generated'] ) {
				$created = true;
			}
			return array(
				'user_id'     => (int) $data['user_id'],
				'created_new' => $created,
			);
		}

		if ( 409 === (int) $code && isset( $data['code'] ) && 'user_already_exists' === $data['code'] ) {
			$matched = isset( $data['matched_by'] ) ? (string) $data['matched_by'] : '';
			if ( 'email' === $matched ) {
				$uid = email_exists( $email );
				if ( $uid ) {
					return array(
						'user_id'     => (int) $uid,
						'created_new' => false,
					);
				}
			}
			if ( 'phone' === $matched ) {
				$uid = self::find_user_id_by_phone_meta( $mobile );
				if ( $uid > 0 ) {
					return array(
						'user_id'     => $uid,
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
