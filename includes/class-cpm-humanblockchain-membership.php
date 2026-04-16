<?php
/**
 * Membership REST proxy: POST /wp-json/myapi/v1/membership with Bearer smallstreet_api_key (server-side only).
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membership API AJAX handler.
 */
class Cpm_Humanblockchain_Membership {

	/**
	 * Option: full URL for POST membership (empty = default REST route on this site).
	 */
	const OPTION_ENDPOINT = 'cpm_hb_membership_api_endpoint';

	/**
	 * Resolved POST URL for the membership API.
	 *
	 * @return string
	 */
	public static function get_api_endpoint_url() {
		$custom = trim( (string) get_option( self::OPTION_ENDPOINT, '' ) );
		if ( $custom !== '' && filter_var( $custom, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $custom );
		}
		return rest_url( 'myapi/v1/membership' );
	}

	/**
	 * Bearer token from WordPress options (same as legacy smallstreet_api_key).
	 *
	 * @return string
	 */
	public static function get_api_key() {
		return trim( (string) get_option( 'smallstreet_api_key', '' ) );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_cpm_hb_membership_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_cpm_hb_membership_submit', array( __CLASS__, 'handle_submit' ) );
	}

	/**
	 * Default PMPro level_name per UI tier (filterable).
	 *
	 * @return array<string,string> tier slug => default name
	 */
	private static function default_level_names() {
		return array(
			'yamer'     => 'YAMer',
			'megavoter' => 'Pioneer',
			'patron'    => 'Patron',
		);
	}

	/**
	 * Map UI tier to PMPro level_name for the REST API.
	 *
	 * Use {@see 'cpm_hb_membership_level_name'} to change names per tier from a small custom plugin or theme.
	 *
	 * @param string $tier Tier slug (yamer|megavoter|patron).
	 * @return array{ level_id: int, level_name: string }
	 */
	public static function get_level_fields_for_tier( $tier ) {
		$tier = strtolower( (string) $tier );
		$defaults = self::default_level_names();
		if ( ! isset( $defaults[ $tier ] ) ) {
			return array(
				'level_id'   => 0,
				'level_name' => '',
			);
		}

		$level_name = (string) apply_filters( 'cpm_hb_membership_level_name', $defaults[ $tier ], $tier );
		return array(
			'level_id'   => 0,
			'level_name' => $level_name,
		);
	}

	/**
	 * Map UI tier slug to PMPro level_name only (legacy helper).
	 *
	 * @param string $tier Tier slug.
	 * @return string Empty if unknown or when only level_id is configured.
	 */
	public static function tier_to_level_name( $tier ) {
		$fields = self::get_level_fields_for_tier( $tier );
		return $fields['level_name'];
	}

	/**
	 * Whether a string has at least 8 digits (API invalid_phone rule).
	 *
	 * @param string $phone Raw phone.
	 * @return bool
	 */
	private static function phone_has_enough_digits( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		return strlen( $digits ) >= 8;
	}

	/**
	 * Normalize phone for API when possible.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	private static function normalize_phone_for_api( $phone ) {
		if ( class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ) {
			$e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $phone );
			if ( $e164 ) {
				return $e164;
			}
		}
		return trim( (string) $phone );
	}

	/**
	 * Store successful membership API fields on the WordPress user as JSON in `_membership_level`.
	 *
	 * @param int                  $user_id  Local WordPress user ID.
	 * @param array<string,mixed> $api_data Decoded JSON body from the membership API.
	 */
	public static function save_membership_response_to_user_meta( $user_id, array $api_data ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$payload = array(
			'success'      => ! empty( $api_data['success'] ),
			'unchanged'    => ! empty( $api_data['unchanged'] ),
			'user_id'      => isset( $api_data['user_id'] ) ? (int) $api_data['user_id'] : 0,
			'level_id'     => isset( $api_data['level_id'] ) ? (int) $api_data['level_id'] : 0,
			'level_name'   => isset( $api_data['level_name'] ) ? sanitize_text_field( (string) $api_data['level_name'] ) : '',
			'action'       => isset( $api_data['action'] ) ? sanitize_key( (string) $api_data['action'] ) : '',
			'user_created' => ! empty( $api_data['user_created'] ),
			'saved_at'     => current_time( 'mysql' ),
		);
		$payload = apply_filters( 'cpm_hb_membership_user_meta_payload', $payload, $user_id, $api_data );
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			return;
		}
		update_user_meta( $user_id, '_membership_level', wp_json_encode( $payload ) );
	}

	/**
	 * Whether the resolved membership POST URL is on this WordPress site (same host as home_url).
	 * External URLs must not receive local {@see wp_get_current_user()} IDs — the remote service returns user_not_found.
	 *
	 * @return bool
	 */
	private static function membership_endpoint_is_same_site() {
		$custom = trim( (string) get_option( self::OPTION_ENDPOINT, '' ) );
		if ( $custom === '' ) {
			return true;
		}
		$endpoint_host = wp_parse_url( $custom, PHP_URL_HOST );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $endpoint_host || ! $site_host ) {
			return false;
		}
		return strtolower( (string) $endpoint_host ) === strtolower( (string) $site_host );
	}

	/**
	 * AJAX: submit membership selection.
	 */
	public static function handle_submit() {
		check_ajax_referer( 'cpm_hb_membership', 'nonce' );

		$tier = isset( $_POST['tier'] ) ? sanitize_text_field( wp_unslash( $_POST['tier'] ) ) : '';
		$level  = self::get_level_fields_for_tier( $tier );
		$has_id = $level['level_id'] > 0;
		$has_nm = $level['level_name'] !== '';
		if ( ! $has_id && ! $has_nm ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_tier',
					'message' => __( 'Invalid membership selection.', 'cpm-humanblockchain' ),
				),
				400
			);
		}

		$api_key = self::get_api_key();
		if ( $api_key === '' ) {
			wp_send_json_error(
				array(
					'code'    => 'api_not_configured',
					'message' => __( 'Membership API is not configured on this site.', 'cpm-humanblockchain' ),
				),
				503
			);
		}

		$body = array();
		if ( $has_id ) {
			$body['level_id'] = (int) $level['level_id'];
		}
		if ( $has_nm ) {
			$body['level_name'] = $level['level_name'];
		}

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$body['email'] = $user->user_email;

			$include_uid = (bool) apply_filters(
				'cpm_hb_membership_include_user_id',
				self::membership_endpoint_is_same_site(),
				$user->ID,
				self::get_api_endpoint_url()
			);
			if ( $include_uid ) {
				$body['user_id'] = (int) $user->ID;
			}

			$phone_from_post = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			if ( $phone_from_post === '' && isset( $_POST['mobile'] ) ) {
				$phone_from_post = sanitize_text_field( wp_unslash( $_POST['mobile'] ) );
			}

			$phone = $phone_from_post !== '' ? $phone_from_post : Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $user->ID );

			if ( $phone === '' || ! self::phone_has_enough_digits( $phone ) ) {
				wp_send_json(
					array(
						'success'      => false,
						'needs_phone'  => true,
						'message'      => __( 'Please enter a valid phone number (at least 8 digits).', 'cpm-humanblockchain' ),
						'data'         => array(
							'code' => 'needs_phone',
						),
					),
					200
				);
			}

			$body['phone'] = self::normalize_phone_for_api( $phone );
		} else {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			if ( $phone === '' && isset( $_POST['mobile'] ) ) {
				$phone = sanitize_text_field( wp_unslash( $_POST['mobile'] ) );
			}

			if ( ! is_email( $email ) ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_email',
						'message' => __( 'Please enter a valid email address.', 'cpm-humanblockchain' ),
					),
					400
				);
			}

			if ( ! self::phone_has_enough_digits( $phone ) ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_phone',
						'message' => __( 'Please enter a valid phone number (at least 8 digits).', 'cpm-humanblockchain' ),
					),
					400
				);
			}

			$body['email'] = $email;
			$body['phone'] = self::normalize_phone_for_api( $phone );

			$optional = array( 'password', 'username', 'first_name', 'last_name' );
			foreach ( $optional as $field ) {
				if ( empty( $_POST[ $field ] ) ) {
					continue;
				}
				$val = wp_unslash( $_POST[ $field ] );
				if ( 'password' === $field ) {
					$body['password'] = (string) $val;
				} elseif ( 'username' === $field ) {
					$body['username'] = sanitize_user( (string) $val, true );
				} else {
					$body[ $field ] = sanitize_text_field( (string) $val );
				}
			}
		}

		$url  = self::get_api_endpoint_url();
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'code'    => 'http_error',
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error(
				array(
					'code'    => 'bad_response',
					'message' => __( 'Unexpected response from membership service.', 'cpm-humanblockchain' ),
				),
				500
			);
		}

		if ( $code >= 200 && $code < 300 && ! empty( $data['success'] ) ) {
			$wp_uid = get_current_user_id();
			if ( $wp_uid > 0 ) {
				self::save_membership_response_to_user_meta( $wp_uid, $data );
			}
			wp_send_json_success( $data );
		}

		$err_code = isset( $data['code'] ) ? (string) $data['code'] : 'membership_failed';
		$message  = isset( $data['message'] ) ? (string) $data['message'] : __( 'Membership could not be updated.', 'cpm-humanblockchain' );

		wp_send_json_error(
			array(
				'code'    => $err_code,
				'message' => $message,
				'raw'     => $data,
			),
			$code >= 400 && $code < 600 ? $code : 500
		);
	}

	/**
	 * Re-POST membership using the last saved level_id / level_name (same endpoint as the modal).
	 * Updates `_membership_level` on success so account pages can show fresh remote state.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{ ok: bool, skipped?: bool, message?: string, data?: array<int|string,mixed> }
	 */
	public static function refresh_membership_from_api_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'Invalid user.', 'cpm-humanblockchain' ),
			);
		}

		$api_key = self::get_api_key();
		if ( $api_key === '' ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'Membership API is not configured on this site.', 'cpm-humanblockchain' ),
			);
		}

		$cached  = get_user_meta( $user_id, '_membership_level', true );
		$decoded = is_string( $cached ) ? json_decode( $cached, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$level_id   = isset( $decoded['level_id'] ) ? (int) $decoded['level_id'] : 0;
		$level_name = isset( $decoded['level_name'] ) ? sanitize_text_field( (string) $decoded['level_name'] ) : '';

		if ( $level_id <= 0 && $level_name === '' ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'No saved membership to sync yet. Choose a membership level first.', 'cpm-humanblockchain' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'User email is missing.', 'cpm-humanblockchain' ),
			);
		}

		$phone = '';
		if ( class_exists( 'Cpm_Humanblockchain_Device_Registry' ) ) {
			$phone = Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $user_id );
		}
		if ( $phone === '' || ! self::phone_has_enough_digits( $phone ) ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'A valid phone number on your profile or device record is required to sync membership.', 'cpm-humanblockchain' ),
			);
		}

		$body = array(
			'email' => $user->user_email,
			'phone' => self::normalize_phone_for_api( $phone ),
		);
		if ( $level_id > 0 ) {
			$body['level_id'] = $level_id;
		}
		if ( $level_name !== '' ) {
			$body['level_name'] = $level_name;
		}

		$include_uid = (bool) apply_filters(
			'cpm_hb_membership_include_user_id',
			self::membership_endpoint_is_same_site(),
			$user_id,
			self::get_api_endpoint_url()
		);
		if ( $include_uid ) {
			$body['user_id'] = $user_id;
		}

		$url  = self::get_api_endpoint_url();
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Unexpected response from membership service.', 'cpm-humanblockchain' ),
			);
		}

		if ( $code >= 200 && $code < 300 && ! empty( $data['success'] ) ) {
			self::save_membership_response_to_user_meta( $user_id, $data );
			return array(
				'ok'   => true,
				'data' => $data,
			);
		}

		$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'Membership could not be updated.', 'cpm-humanblockchain' );
		return array(
			'ok'      => false,
			'message' => $message,
			'data'    => $data,
		);
	}
}
