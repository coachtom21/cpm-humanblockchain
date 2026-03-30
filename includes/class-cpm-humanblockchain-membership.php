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
	 * Load email, phone, and profile fields from a WordPress user ID for membership API (same shape as guest payload).
	 *
	 * @param int $user_id User ID.
	 * @return array{ email: string, phone: string, username: string, first_name: string, last_name: string }
	 */
	private static function get_identity_from_user_for_membership( $user_id ) {
		$user_id = (int) $user_id;
		$out     = array(
			'email'      => '',
			'phone'      => '',
			'username'   => '',
			'first_name' => '',
			'last_name'  => '',
		);
		if ( $user_id <= 0 ) {
			return $out;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $out;
		}
		$out['email']    = (string) $user->user_email;
		$out['username'] = (string) $user->user_login;
		$out['first_name'] = (string) get_user_meta( $user_id, 'first_name', true );
		$out['last_name']  = (string) get_user_meta( $user_id, 'last_name', true );
		if ( class_exists( 'Cpm_Humanblockchain_Device_Registry' ) ) {
			$out['phone'] = (string) Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $user_id );
		}
		if ( $out['phone'] === '' ) {
			$out['phone'] = (string) get_user_meta( $user_id, 'phone', true );
		}
		return $out;
	}

	/**
	 * Append optional membership fields: POST overrides; for logged-in users $identity_defaults fills gaps.
	 *
	 * @param array<string,mixed> $body              Request body (modified).
	 * @param array<string,string>|null $identity_defaults Optional profile values (username, first_name, last_name).
	 */
	private static function append_optional_membership_fields( array &$body, $identity_defaults = null ) {
		$optional = array( 'password', 'username', 'first_name', 'last_name' );
		foreach ( $optional as $field ) {
			$raw = '';
			if ( ! empty( $_POST[ $field ] ) ) {
				$raw = wp_unslash( $_POST[ $field ] );
			} elseif ( is_array( $identity_defaults ) && isset( $identity_defaults[ $field ] ) && (string) $identity_defaults[ $field ] !== '' ) {
				$raw = (string) $identity_defaults[ $field ];
			}
			if ( $raw === '' ) {
				continue;
			}
			if ( 'password' === $field ) {
				$body['password'] = (string) $raw;
			} elseif ( 'username' === $field ) {
				$body['username'] = sanitize_user( (string) $raw, true );
			} else {
				$body[ $field ] = sanitize_text_field( (string) $raw );
			}
		}
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
			$user_id  = get_current_user_id();
			$identity = self::get_identity_from_user_for_membership( $user_id );

			$email = sanitize_email( $identity['email'] );
			if ( ! is_email( $email ) ) {
				wp_send_json_error(
					array(
						'code'    => 'invalid_email',
						'message' => __( 'Your account does not have a valid email address.', 'cpm-humanblockchain' ),
					),
					400
				);
			}

			$phone_from_post = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
			if ( $phone_from_post === '' && isset( $_POST['mobile'] ) ) {
				$phone_from_post = sanitize_text_field( wp_unslash( $_POST['mobile'] ) );
			}
			$phone = $phone_from_post !== '' ? $phone_from_post : $identity['phone'];

			if ( $phone === '' || ! self::phone_has_enough_digits( $phone ) ) {
				wp_send_json(
					array(
						'success'     => false,
						'needs_phone' => true,
						'message'     => __( 'Please enter a valid phone number (at least 8 digits).', 'cpm-humanblockchain' ),
						'data'        => array(
							'code' => 'needs_phone',
						),
					),
					200
				);
			}

			$body['email'] = $email;
			$body['phone'] = self::normalize_phone_for_api( $phone );

			$identity_optional = array(
				'username'   => $identity['username'],
				'first_name' => $identity['first_name'],
				'last_name'  => $identity['last_name'],
			);
			self::append_optional_membership_fields( $body, $identity_optional );
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

			self::append_optional_membership_fields( $body, null );
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
}
