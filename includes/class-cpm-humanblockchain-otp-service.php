<?php

/**
 * OTP Service - Twilio SMS integration for device activation.
 *
 * Supports two modes (same Twilio Account SID + Auth Token):
 * 1) Twilio Verify API — optional Verify Service SID (VA…) via CPM_TWILIO_VERIFY_SERVICE_SID or
 *    cpm_nwp_twilio_verify_service_sid (matches Smallstreet / cpm-twilio). No "From" number required.
 * 2) Classic Messages API — wp_options cpm_nwp_twilio_sid, cpm_nwp_twilio_token, cpm_nwp_twilio_from
 *    (or CPM_NWP_TWILIO_* constants) plus local OTP in transients.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * OTP Service class.
 */
class Cpm_Humanblockchain_Otp_Service {

	const OTP_LENGTH       = 6;
	const OTP_EXPIRY_MIN   = 10;
	const TRANSIENT_PREFIX = 'cpm_nwp_otp_';

	/**
	 * Default country when user enters 10 digits without country code: NP or US.
	 *
	 * @return string 'NP' or 'US'
	 */
	public static function get_default_country() {
		$country = get_option( 'cpm_nwp_default_country', 'NP' );
		return in_array( $country, array( 'NP', 'US' ), true ) ? $country : 'NP';
	}

	/**
	 * Normalize phone to E.164 for Twilio (Nepal +977, US +1, or explicit +...).
	 *
	 * @param string $phone Raw phone input (any format).
	 * @return string|null E.164 format or null if invalid.
	 */
	public static function normalize_phone_e164( $phone ) {
		$phone = trim( $phone );
		// Explicit international E.164
		if ( preg_match( '/^\+[1-9]\d{6,14}$/', $phone ) ) {
			return $phone;
		}

		$digits = preg_replace( '/\D/', '', $phone );

		// Nepal: 977 + 10 digits (e.g. 9779849158973)
		if ( strlen( $digits ) === 13 && substr( $digits, 0, 3 ) === '977' ) {
			return '+' . $digits;
		}

		// Nepal mobile: 10 digits starting with 98 or 97 (when default country NP)
		if ( self::get_default_country() === 'NP' && strlen( $digits ) === 10 && preg_match( '/^9[78]/', $digits ) ) {
			return '+977' . $digits;
		}

		// US / Canada NANP: 10 digits
		if ( strlen( $digits ) === 10 ) {
			return '+1' . $digits;
		}

		// US with leading 1
		if ( strlen( $digits ) === 11 && substr( $digits, 0, 1 ) === '1' ) {
			return '+' . $digits;
		}

		return null;
	}

	/**
	 * Human-readable reason normalization failed (for AJAX errors).
	 *
	 * @param string $mobile_raw Raw input.
	 * @return string|null Message or null if input is valid.
	 */
	public static function normalize_phone_failure_message( $mobile_raw ) {
		if ( self::normalize_phone_e164( $mobile_raw ) ) {
			return null;
		}
		$digits = preg_replace( '/\D/', '', (string) $mobile_raw );
		if ( self::get_default_country() === 'NP' && strlen( $digits ) === 11 && preg_match( '/^9[78]/', $digits ) ) {
			return __( 'Nepal numbers must be exactly 10 digits without +977 (you entered 11 digits). Remove an extra digit, or use international format e.g. +9779849158973.', 'cpm-humanblockchain' );
		}
		return __( 'Please enter a valid mobile number (e.g. 9849158973 or +9779849158973).', 'cpm-humanblockchain' );
	}

	/**
	 * Get 10-digit national number for legacy US-centric DB checks (fallback).
	 *
	 * @param string $phone Raw phone input.
	 * @return string Digits (variable length for international).
	 */
	public static function get_phone_digits( $phone ) {
		$digits = preg_replace( '/\D/', '', $phone );
		if ( strlen( $digits ) === 11 && substr( $digits, 0, 1 ) === '1' ) {
			return substr( $digits, 1 );
		}
		if ( strlen( $digits ) === 10 ) {
			return $digits;
		}
		return $digits;
	}

	/**
	 * Digit variants to match phone column (different stored formats).
	 *
	 * @param string $mobile_raw Raw input.
	 * @return string[] Unique digit-only strings.
	 */
	public static function get_phone_match_variants( $mobile_raw ) {
		$e164 = self::normalize_phone_e164( $mobile_raw );
		$all  = preg_replace( '/\D/', '', $mobile_raw );
		$variants = array();

		if ( $e164 ) {
			$variants[] = preg_replace( '/\D/', '', $e164 );
		}
		if ( $all ) {
			$variants[] = $all;
		}
		$gd = self::get_phone_digits( $mobile_raw );
		if ( $gd ) {
			$variants[] = $gd;
		}
		if ( $e164 && preg_match( '/^\+977/', $e164 ) ) {
			$d = preg_replace( '/\D/', '', $e164 );
			if ( strlen( $d ) === 13 && substr( $d, 0, 3 ) === '977' ) {
				$variants[] = substr( $d, 3 );
			}
		}
		// Stored as US mask +1 (984) 915-8973 → 19849158973 (legacy)
		if ( strlen( $all ) === 10 && preg_match( '/^9[78]/', $all ) ) {
			$variants[] = '1' . $all;
		}

		return array_values( array_unique( array_filter( $variants ) ) );
	}

	/**
	 * Last 10 digits of national number (for matching DB rows stored in different formats).
	 *
	 * @param string $mobile_raw Raw input.
	 * @return string|null 10 digits or null.
	 */
	public static function get_phone_last_national_digits_for_match( $mobile_raw ) {
		$e164 = self::normalize_phone_e164( $mobile_raw );
		if ( $e164 ) {
			$d = preg_replace( '/\D/', '', $e164 );
			if ( strlen( $d ) >= 10 ) {
				return substr( $d, -10 );
			}
		}
		$all_raw = preg_replace( '/\D/', '', $mobile_raw );
		if ( strlen( $all_raw ) >= 10 ) {
			return substr( $all_raw, -10 );
		}
		return null;
	}

	/**
	 * Generate random OTP.
	 *
	 * @return string 6-digit OTP.
	 */
	public static function generate_otp() {
		$min = (int) str_pad( '1', self::OTP_LENGTH, '0' );
		$max = (int) str_pad( '9', self::OTP_LENGTH, '9' );
		return (string) random_int( $min, $max );
	}

	/**
	 * Store OTP in transient.
	 *
	 * @param string $phone_e164 E.164 phone number.
	 * @param string $otp        OTP code.
	 */
	public static function store_otp( $phone_e164, $otp ) {
		$key   = self::TRANSIENT_PREFIX . md5( $phone_e164 );
		$value = array( 'otp' => $otp, 'created' => time() );
		set_transient( $key, $value, self::OTP_EXPIRY_MIN * MINUTE_IN_SECONDS );
	}

	/**
	 * Transient key for a phone’s OTP.
	 *
	 * @param string $phone_e164 E.164 phone.
	 * @return string
	 */
	public static function get_otp_transient_key( $phone_e164 ) {
		return self::TRANSIENT_PREFIX . md5( $phone_e164 );
	}

	/**
	 * Verify OTP for an E.164 number. Optionally clear transient only after caller persists state.
	 *
	 * @param string $phone_e164 Normalized E.164 phone.
	 * @param string $code         User-entered code (digits only).
	 * @param bool   $consume      If true, delete transient when code matches (default).
	 * @return array{ success: bool, message: string }
	 */
	public static function verify_otp( $phone_e164, $code, $consume = true ) {
		$code = preg_replace( '/\D/', '', (string) $code );
		if ( strlen( $code ) !== self::OTP_LENGTH ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter the 6-digit code from your SMS.', 'cpm-humanblockchain' ),
			);
		}
		if ( self::uses_twilio_verify() ) {
			return self::verify_otp_via_twilio_verify( $phone_e164, $code );
		}
		$key    = self::get_otp_transient_key( $phone_e164 );
		$stored = get_transient( $key );
		if ( ! is_array( $stored ) || empty( $stored['otp'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'That code has expired or was not sent. Request a new code.', 'cpm-humanblockchain' ),
			);
		}
		if ( ! hash_equals( (string) $stored['otp'], $code ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid verification code. Try again.', 'cpm-humanblockchain' ),
			);
		}
		if ( $consume ) {
			delete_transient( $key );
		}
		return array(
			'success' => true,
			'message' => __( 'Your device is verified.', 'cpm-humanblockchain' ),
		);
	}

	/**
	 * Remove OTP transient after successful server-side follow-up (e.g. DB update).
	 *
	 * @param string $phone_e164 E.164 phone.
	 */
	public static function clear_otp_transient( $phone_e164 ) {
		delete_transient( self::get_otp_transient_key( $phone_e164 ) );
	}

	/**
	 * Get Twilio credentials (from constants or options).
	 *
	 * @return array{ sid: string, token: string, from: string }
	 */
	private static function get_credentials() {
		$sid   = defined( 'CPM_NWP_TWILIO_SID' ) ? CPM_NWP_TWILIO_SID : get_option( 'cpm_nwp_twilio_sid', '' );
		$token = defined( 'CPM_NWP_TWILIO_TOKEN' ) ? CPM_NWP_TWILIO_TOKEN : get_option( 'cpm_nwp_twilio_token', '' );
		$from  = defined( 'CPM_NWP_TWILIO_FROM' ) ? CPM_NWP_TWILIO_FROM : get_option( 'cpm_nwp_twilio_from', '' );
		return array( 'sid' => $sid, 'token' => $token, 'from' => $from );
	}

	/**
	 * Twilio Verify Service SID (VA…). Same account as Account SID / Auth Token.
	 *
	 * @return string
	 */
	private static function get_verify_service_sid() {
		if ( defined( 'CPM_TWILIO_VERIFY_SERVICE_SID' ) && is_string( CPM_TWILIO_VERIFY_SERVICE_SID ) && CPM_TWILIO_VERIFY_SERVICE_SID !== '' ) {
			return trim( CPM_TWILIO_VERIFY_SERVICE_SID );
		}
		if ( defined( 'CPM_NWP_TWILIO_VERIFY_SERVICE_SID' ) && is_string( CPM_NWP_TWILIO_VERIFY_SERVICE_SID ) && CPM_NWP_TWILIO_VERIFY_SERVICE_SID !== '' ) {
			return trim( CPM_NWP_TWILIO_VERIFY_SERVICE_SID );
		}
		$opt = get_option( 'cpm_nwp_twilio_verify_service_sid', '' );
		return is_string( $opt ) ? trim( $opt ) : '';
	}

	/**
	 * Whether OTP uses Twilio Verify (not Messages API + local transient OTP).
	 *
	 * @return bool
	 */
	public static function uses_twilio_verify() {
		return self::get_verify_service_sid() !== '';
	}

	/**
	 * Check if Twilio is configured.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$creds = self::get_credentials();
		if ( empty( $creds['sid'] ) || empty( $creds['token'] ) ) {
			return false;
		}
		if ( self::uses_twilio_verify() ) {
			return true;
		}
		return ! empty( $creds['from'] );
	}

	/**
	 * Append Twilio Console hint when outbound SMS is blocked by geographic permissions.
	 *
	 * @param string               $message       Twilio error message.
	 * @param array<string, mixed> $body_response Decoded JSON body.
	 * @return string
	 */
	private static function maybe_append_geo_permission_help( $message, $body_response ) {
		$code = isset( $body_response['code'] ) ? (int) $body_response['code'] : 0;
		$msg  = strtolower( (string) $message );
		// 21408 is the common REST code for "permission to send an SMS has not been enabled for the region".
		$is_geo = ( 21408 === $code )
			|| ( false !== strpos( $msg, 'permission' ) && false !== strpos( $msg, 'region' ) )
			|| ( false !== strpos( $msg, 'geographic' ) && false !== strpos( $msg, 'permission' ) );
		if ( ! $is_geo ) {
			return $message;
		}
		return trim( $message ) . ' ' . __( 'In Twilio Console open Messaging → Settings → SMS geographic permissions (or search “Geo permissions”) and enable outbound SMS for the destination country.', 'cpm-humanblockchain' );
	}

	/**
	 * Send SMS via Twilio REST API.
	 *
	 * @param string $to   E.164 number (e.g. +15551234567).
	 * @param string $body Message body.
	 * @return array{ 'success' => bool, 'message' => string, 'error' => string|null }
	 */
	public static function send_sms( $to, $body ) {
		$creds = self::get_credentials();
		$sid   = $creds['sid'];
		$token = $creds['token'];
		$from  = $creds['from'];

		if ( empty( $sid ) || empty( $token ) || empty( $from ) ) {
			return array(
				'success' => false,
				'message' => __( 'SMS service is not configured. Contact the administrator.', 'cpm-humanblockchain' ),
				'error'   => 'twilio_not_configured',
			);
		}

		$url  = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';
		$auth = base64_encode( $sid . ':' . $token );

		$post_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'To'   => $to,
				'From' => $from,
				'Body' => $body,
			),
			'timeout'   => 15,
			'sslverify' => true,
		);
		$post_args = apply_filters( 'cpm_nwp_twilio_http_request_args', $post_args, $to, $body );

		$response = wp_remote_post( $url, $post_args );

		if ( is_wp_error( $response ) ) {
			$err = $response->get_error_message();
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: underlying error from WordPress HTTP API (e.g. SSL or connection). */
					__( 'Failed to send SMS: %s', 'cpm-humanblockchain' ),
					$err
				),
				'error'   => $err,
			);
		}

		$code          = wp_remote_retrieve_response_code( $response );
		$raw_body      = wp_remote_retrieve_body( $response );
		$body_response = json_decode( $raw_body, true );

		if ( $code >= 200 && $code < 300 ) {
			// Twilio returns 201 with status queued|sent|failed; treat non-delivered as failure when status is present.
			$twilio_status = is_array( $body_response ) && isset( $body_response['status'] ) ? $body_response['status'] : '';
			if ( in_array( $twilio_status, array( 'failed', 'undelivered', 'canceled' ), true ) ) {
				$err_detail = isset( $body_response['error_message'] ) ? $body_response['error_message'] : $twilio_status;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'cpm_nwp twilio message status=' . $twilio_status . ' to=' . $to . ' body=' . $raw_body );
				}
				$fail_msg = $err_detail ? $err_detail : __( 'SMS could not be delivered.', 'cpm-humanblockchain' );
				return array(
					'success' => false,
					'message' => self::maybe_append_geo_permission_help( $fail_msg, $body_response ),
					'error'   => $twilio_status,
				);
			}
			return array(
				'success' => true,
				'message' => __( 'SMS sent successfully.', 'cpm-humanblockchain' ),
				'error'   => null,
			);
		}

		$err_msg = isset( $body_response['message'] ) ? $body_response['message'] : __( 'SMS delivery failed.', 'cpm-humanblockchain' );
		$err_msg = self::maybe_append_geo_permission_help( $err_msg, is_array( $body_response ) ? $body_response : array() );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'cpm_nwp twilio HTTP ' . (string) $code . ' to=' . $to . ' response=' . $raw_body );
		}
		return array(
			'success' => false,
			'message' => $err_msg,
			'error'   => isset( $body_response['code'] ) ? $body_response['code'] : (string) $code,
		);
	}

	/**
	 * POST to Twilio Verify API (same Basic auth as Messages API).
	 *
	 * @param string               $path Relative to https://verify.twilio.com/v2/ (e.g. Services/VA…/Verifications).
	 * @param array<string, string> $form Form body.
	 * @return array{ response: array|WP_Error, http_code: int, data: array|null, raw: string }
	 */
	private static function twilio_verify_post( $path, array $form ) {
		$creds = self::get_credentials();
		$sid   = $creds['sid'];
		$token = $creds['token'];
		$url   = 'https://verify.twilio.com/v2/' . ltrim( $path, '/' );
		$auth  = base64_encode( $sid . ':' . $token );
		$post_args = array(
			'headers' => array(
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'      => $form,
			'timeout'   => 15,
			'sslverify' => true,
		);
		$to = isset( $form['To'] ) ? (string) $form['To'] : '';
		$post_args = apply_filters( 'cpm_nwp_twilio_http_request_args', $post_args, $to, 'twilio_verify' );

		$response = wp_remote_post( $url, $post_args );
		$http_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$raw       = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
		$data      = json_decode( $raw, true );

		return array(
			'response'  => $response,
			'http_code' => $http_code,
			'data'      => is_array( $data ) ? $data : null,
			'raw'       => $raw,
		);
	}

	/**
	 * Start SMS verification via Twilio Verify (same flow as Smallstreet Verify Service).
	 *
	 * @param string $phone_e164 E.164.
	 * @return array{ success: bool, message: string, error?: string|null }
	 */
	private static function send_otp_via_twilio_verify( $phone_e164 ) {
		$service_sid = self::get_verify_service_sid();
		$path        = 'Services/' . rawurlencode( $service_sid ) . '/Verifications';
		$parsed      = self::twilio_verify_post(
			$path,
			array(
				'To'      => $phone_e164,
				'Channel' => 'sms',
			)
		);

		if ( is_wp_error( $parsed['response'] ) ) {
			$err = $parsed['response']->get_error_message();
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: WordPress HTTP error. */
					__( 'Failed to start SMS verification: %s', 'cpm-humanblockchain' ),
					$err
				),
				'error'   => $err,
			);
		}

		if ( $parsed['http_code'] >= 200 && $parsed['http_code'] < 300 ) {
			$st = isset( $parsed['data']['status'] ) ? (string) $parsed['data']['status'] : '';
			if ( in_array( $st, array( 'pending', 'queued' ), true ) || $st === '' ) {
				return array(
					'success' => true,
					'message' => __( 'SMS sent successfully.', 'cpm-humanblockchain' ),
					'error'   => null,
				);
			}
		}

		$err_msg = isset( $parsed['data']['message'] ) ? (string) $parsed['data']['message'] : __( 'SMS delivery failed.', 'cpm-humanblockchain' );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'cpm_nwp twilio verify start HTTP ' . (string) $parsed['http_code'] . ' to=' . $phone_e164 . ' response=' . $parsed['raw'] );
		}
		return array(
			'success' => false,
			'message' => self::maybe_append_geo_permission_help( $err_msg, is_array( $parsed['data'] ) ? $parsed['data'] : array() ),
			'error'   => isset( $parsed['data']['code'] ) ? (string) $parsed['data']['code'] : (string) $parsed['http_code'],
		);
	}

	/**
	 * Check code via Twilio Verify API.
	 *
	 * @param string $phone_e164 E.164.
	 * @param string $code       Digits only.
	 * @return array{ success: bool, message: string }
	 */
	private static function verify_otp_via_twilio_verify( $phone_e164, $code ) {
		$service_sid = self::get_verify_service_sid();
		$path        = 'Services/' . rawurlencode( $service_sid ) . '/VerificationCheck';
		$parsed      = self::twilio_verify_post(
			$path,
			array(
				'To'   => $phone_e164,
				'Code' => $code,
			)
		);

		if ( is_wp_error( $parsed['response'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: WordPress HTTP error. */
					__( 'Verification request failed: %s', 'cpm-humanblockchain' ),
					$parsed['response']->get_error_message()
				),
			);
		}

		if ( $parsed['http_code'] >= 200 && $parsed['http_code'] < 300 && is_array( $parsed['data'] ) ) {
			$st = isset( $parsed['data']['status'] ) ? (string) $parsed['data']['status'] : '';
			if ( 'approved' === $st ) {
				return array(
					'success' => true,
					'message' => __( 'Your device is verified.', 'cpm-humanblockchain' ),
				);
			}
			$msg = isset( $parsed['data']['message'] ) ? (string) $parsed['data']['message'] : __( 'Invalid verification code. Try again.', 'cpm-humanblockchain' );
			return array(
				'success' => false,
				'message' => $msg,
			);
		}

		$err_msg = isset( $parsed['data']['message'] ) ? (string) $parsed['data']['message'] : __( 'Verification failed.', 'cpm-humanblockchain' );
		return array(
			'success' => false,
			'message' => $err_msg,
		);
	}

	/**
	 * Send OTP SMS and store for verification.
	 *
	 * @param string $phone_e164 E.164 phone number.
	 * @return array{ 'success' => bool, 'message' => string }
	 */
	public static function send_otp_sms( $phone_e164 ) {
		if ( self::uses_twilio_verify() ) {
			return self::send_otp_via_twilio_verify( $phone_e164 );
		}

		$otp = self::generate_otp();
		self::store_otp( $phone_e164, $otp );

		$body = sprintf(
			/* translators: %s: OTP code */
			__( 'Your NWP verification code is: %s', 'cpm-humanblockchain' ),
			$otp
		);

		$result = self::send_sms( $phone_e164, $body );
		return $result;
	}
}
