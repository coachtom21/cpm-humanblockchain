<?php

/**
 * OTP Service - Twilio SMS integration for device activation.
 *
 * Supports two modes (same Twilio Account SID + Auth Token):
 * 1) Twilio Verify API — optional Verify Service SID (VA…) via CPM_TWILIO_VERIFY_SERVICE_SID or
 *    cpm_nwp_twilio_verify_service_sid (matches Smallstreet / cpm-twilio). No "From" number required.
 * 2) Classic Messages API — wp_options cpm_nwp_twilio_sid, cpm_nwp_twilio_token, cpm_nwp_twilio_from
 *    (or CPM_NWP_TWILIO_* / CPM_TWILIO_ACCOUNT_SID + CPM_TWILIO_AUTH_TOKEN aliases) plus local OTP in transients.
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
	 * Default country when user enters 10 digits without country code: NP, US, or AUTO.
	 *
	 * - NP: 10-digit 97/98… → +977; other 10-digit → +1 (legacy quirk; prefer typing +977).
	 * - US: 10-digit → +1 **except** 9[78]… is treated as Nepal first (see normalize_phone_e164) so 984… is not sent as +1.
	 * - AUTO: same routing as US for remaining digits; Nepal 9[78] always +977 when filter allows.
	 *
	 * @return string 'NP'|'US'|'AUTO'
	 */
	public static function get_default_country() {
		$country = get_option( 'cpm_nwp_default_country', 'AUTO' );
		return in_array( $country, array( 'NP', 'US', 'AUTO' ), true ) ? $country : 'AUTO';
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

		// Nepal mobile 10-digit (e.g. 9849158973): always resolve to +977 *before* NANP +1 so a mis-set
		// "US" default in NWP Gateway does not send SMS to +1 984… (Twilio 30006 on wrong country).
		// Disable with: add_filter( 'cpm_nwp_ten_digit_97_98_as_nepal', '__return_false' );
		if ( strlen( $digits ) === 10 && preg_match( '/^9[78]\d{8}$/', $digits ) && (bool) apply_filters( 'cpm_nwp_ten_digit_97_98_as_nepal', true ) ) {
			return '+977' . $digits;
		}

		// US / Canada NANP: remaining 10-digit numbers
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
		$def = self::get_default_country();
		if ( in_array( $def, array( 'NP', 'AUTO' ), true ) && strlen( $digits ) === 11 && preg_match( '/^9[78]/', $digits ) ) {
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
	 * Trim secrets copied from Twilio Console (newlines / BOM break Basic auth and yield "invalid username").
	 *
	 * @param string $value Raw SID, token, or From.
	 * @return string
	 */
	private static function trim_twilio_secret( $value ) {
		$value = trim( (string) $value );
		if ( $value !== '' && preg_match( '/^\x{FEFF}/u', $value ) ) {
			$value = preg_replace( '/^\x{FEFF}/u', '', $value );
		}
		return $value;
	}

	/**
	 * First non-empty constant wins, else wp_options. Empty defines in wp-config no longer override the database.
	 * Aliases match common Smallstreet / cpm-twilio naming (CPM_TWILIO_ACCOUNT_SID, CPM_TWILIO_AUTH_TOKEN).
	 *
	 * @param string   $option_name Option key.
	 * @param string[] $constant_names In priority order.
	 * @return string Raw value (trimmed in get_credentials).
	 */
	private static function pick_twilio_constant_or_option( $option_name, array $constant_names ) {
		foreach ( $constant_names as $name ) {
			if ( ! defined( $name ) ) {
				continue;
			}
			$raw = constant( $name );
			if ( ! is_string( $raw ) && ! is_numeric( $raw ) ) {
				continue;
			}
			if ( self::trim_twilio_secret( (string) $raw ) !== '' ) {
				return (string) $raw;
			}
		}
		$opt = get_option( $option_name, '' );
		return is_string( $opt ) ? $opt : '';
	}

	/**
	 * Get Twilio credentials (from constants or options).
	 *
	 * @return array{ sid: string, token: string, from: string }
	 */
	private static function get_credentials() {
		$sid = self::pick_twilio_constant_or_option(
			'cpm_nwp_twilio_sid',
			array( 'CPM_NWP_TWILIO_SID', 'CPM_TWILIO_ACCOUNT_SID' )
		);
		$token = self::pick_twilio_constant_or_option(
			'cpm_nwp_twilio_token',
			array( 'CPM_NWP_TWILIO_TOKEN', 'CPM_TWILIO_AUTH_TOKEN' )
		);
		$from = self::pick_twilio_constant_or_option(
			'cpm_nwp_twilio_from',
			array( 'CPM_NWP_TWILIO_FROM', 'CPM_TWILIO_FROM' )
		);

		$creds = array(
			'sid'   => self::trim_twilio_secret( $sid ),
			'token' => self::trim_twilio_secret( $token ),
			'from'  => self::trim_twilio_secret( $from ),
		);

		/**
		 * Twilio Account SID + Auth Token (+ optional From). Fix mismatched env or map subaccounts.
		 *
		 * @param array{ sid: string, token: string, from: string } $creds Credentials after trim.
		 */
		return apply_filters( 'cpm_nwp_twilio_credentials', $creds );
	}

	/**
	 * Extra help when Twilio returns 401-style auth errors.
	 *
	 * @param string $message Twilio API message.
	 * @return string
	 */
	private static function maybe_append_twilio_auth_help( $message ) {
		$m = strtolower( (string) $message );
		if ( false === strpos( $m, 'invalid username' )
			&& false === strpos( $m, 'invalid password' )
			&& false === strpos( $m, 'authenticate' )
			&& false === strpos( $m, 'authentication failed' ) ) {
			return $message;
		}
		return trim( (string) $message ) . ' '
			. __( 'Use the Twilio Console “Account SID” (starts with AC) and “Auth Token” from the same project as your Verify Service (VA…). Do not use an API Key (SK…) as the username unless you also use its secret as the password. Re-save credentials in Settings → NWP Gateway or wp-config.', 'cpm-humanblockchain' );
	}

	/**
	 * Twilio Verify Service SID (VA…). Same account as Account SID / Auth Token.
	 *
	 * @return string
	 */
	private static function get_verify_service_sid() {
		if ( defined( 'CPM_TWILIO_VERIFY_SERVICE_SID' ) && is_string( CPM_TWILIO_VERIFY_SERVICE_SID ) && CPM_TWILIO_VERIFY_SERVICE_SID !== '' ) {
			return self::trim_twilio_secret( CPM_TWILIO_VERIFY_SERVICE_SID );
		}
		if ( defined( 'CPM_NWP_TWILIO_VERIFY_SERVICE_SID' ) && is_string( CPM_NWP_TWILIO_VERIFY_SERVICE_SID ) && CPM_NWP_TWILIO_VERIFY_SERVICE_SID !== '' ) {
			return self::trim_twilio_secret( CPM_NWP_TWILIO_VERIFY_SERVICE_SID );
		}
		$opt = get_option( 'cpm_nwp_twilio_verify_service_sid', '' );
		return self::trim_twilio_secret( is_string( $opt ) ? $opt : '' );
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
	 * Extra help for Twilio delivery error 30006 (landline / carrier cannot deliver SMS).
	 *
	 * @param string   $message    Base message.
	 * @param int      $error_code From Message resource (error_code) or API body.
	 * @return string
	 */
	private static function maybe_append_landline_30006_help( $message, $error_code ) {
		if ( 30006 !== (int) $error_code ) {
			return (string) $message;
		}
		return trim( (string) $message ) . ' '
			. __( 'This number likely cannot receive SMS (landline, non-SMS line, or blocked route). Use a real mobile in full international format (e.g. Nepal: +977984000000; US: +1…). In Twilio, confirm SMS geo permissions and that the device can receive test SMS. See https://www.twilio.com/docs/api/errors/30006', 'cpm-humanblockchain' );
	}

	/**
	 * GET a Message instance from the Twilio REST API.
	 *
	 * @param string               $message_sid Message SID (SM…).
	 * @param array{ sid: string, token: string, from: string } $creds Account credentials.
	 * @return array<string, mixed>|null Decoded JSON or null on failure.
	 */
	private static function twilio_get_message( $message_sid, array $creds ) {
		$sid   = $creds['sid'];
		$token = $creds['token'];
		if ( $sid === '' || $token === '' || $message_sid === '' ) {
			return null;
		}
		$url  = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages/' . rawurlencode( $message_sid ) . '.json';
		$auth = base64_encode( $sid . ':' . $token );
		$args = array(
			'headers'   => array( 'Authorization' => 'Basic ' . $auth ),
			'timeout'   => 10,
			'sslverify' => true,
		);
		$args  = apply_filters( 'cpm_nwp_twilio_get_message_request_args', $args, $message_sid );
		$resp  = wp_remote_get( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return is_array( $body ) ? $body : null;
	}

	/**
	 * Poll a Message until it leaves “queued” / “sending” so 30006 appears on the resource (not only in Console later).
	 *
	 * @param array<string, mixed> $body_response First POST /Messages response.
	 * @param array{ sid: string, token: string, from: string } $creds Credentials.
	 * @param string               $to            E.164 to (for debug log).
	 * @return array<string, mixed> Latest message data.
	 */
	private static function twilio_message_after_send_poll( $body_response, array $creds, $to ) {
		if ( ! is_array( $body_response ) || empty( $body_response['sid'] ) ) {
			return is_array( $body_response ) ? $body_response : array();
		}
		$in_progress = array( 'queued', 'sending', 'accepted', 'scheduled' );
		$data        = $body_response;
		for ( $i = 0; $i < 12; $i++ ) {
			$st = isset( $data['status'] ) ? (string) $data['status'] : '';
			if ( $st === '' || ! in_array( $st, $in_progress, true ) ) {
				break;
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
				error_log( 'cpm_nwp twilio poll message to=' . $to . ' status=' . $st . ' i=' . $i );
			}
			usleep( 300000 );
			$next = self::twilio_get_message( (string) $data['sid'], $creds );
			if ( is_array( $next ) ) {
				$data = $next;
			} else {
				break;
			}
		}
		return is_array( $data ) ? $data : $body_response;
	}

	/**
	 * User-facing line for a failed/undelivered message including Twilio 30006 context.
	 *
	 * @param array<string, mixed> $data Message resource JSON.
	 * @return string
	 */
	private static function message_failed_user_text( array $data ) {
		$msg     = '';
		$err_msg = $data['error_message'] ?? $data['ErrorMessage'] ?? null;
		if ( is_string( $err_msg ) && $err_msg !== '' ) {
			$msg = $err_msg;
		}
		$err_code = 0;
		if ( isset( $data['error_code'] ) && is_numeric( $data['error_code'] ) ) {
			$err_code = (int) $data['error_code'];
		} elseif ( isset( $data['ErrorCode'] ) && is_numeric( $data['ErrorCode'] ) ) {
			$err_code = (int) $data['ErrorCode'];
		}
		if ( $msg === '' && $err_code > 0 ) {
			$msg = sprintf(
				/* translators: %d: Twilio error code, e.g. 30006 */
				__( 'SMS could not be delivered (Twilio error %d).', 'cpm-humanblockchain' ),
				$err_code
			);
		} elseif ( $msg === '' ) {
			$msg = __( 'SMS could not be delivered.', 'cpm-humanblockchain' );
		}
		$msg = self::maybe_append_landline_30006_help( $msg, $err_code );
		return self::maybe_append_geo_permission_help( $msg, array( 'code' => $err_code, 'message' => $msg ) );
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
			// Re-fetch while queued so async failures (e.g. 30006 landline) are visible before we tell the user “success”.
			if ( is_array( $body_response ) && ! empty( $body_response['sid'] ) ) {
				$body_response = self::twilio_message_after_send_poll( $body_response, $creds, $to );
			}
			$raw_body = wp_json_encode( $body_response );

			$twilio_status = is_array( $body_response ) && isset( $body_response['status'] ) ? $body_response['status'] : '';
			if ( in_array( $twilio_status, array( 'failed', 'undelivered', 'canceled' ), true ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'cpm_nwp twilio message status=' . $twilio_status . ' to=' . $to . ' body=' . ( is_string( $raw_body ) ? $raw_body : '' ) );
				}
				$fail_msg = self::message_failed_user_text( is_array( $body_response ) ? $body_response : array() );
				return array(
					'success' => false,
					'message' => $fail_msg,
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
		$err_msg = self::maybe_append_twilio_auth_help(
			self::maybe_append_geo_permission_help( $err_msg, is_array( $body_response ) ? $body_response : array() )
		);
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
		$err_msg = self::maybe_append_twilio_auth_help( self::maybe_append_geo_permission_help( $err_msg, is_array( $parsed['data'] ) ? $parsed['data'] : array() ) );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'cpm_nwp twilio verify start HTTP ' . (string) $parsed['http_code'] . ' to=' . $phone_e164 . ' response=' . $parsed['raw'] );
		}
		return array(
			'success' => false,
			'message' => $err_msg,
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
				'message' => self::maybe_append_twilio_auth_help( $msg ),
			);
		}

		$err_msg = isset( $parsed['data']['message'] ) ? (string) $parsed['data']['message'] : __( 'Verification failed.', 'cpm-humanblockchain' );
		return array(
			'success' => false,
			'message' => self::maybe_append_twilio_auth_help( $err_msg ),
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
		if ( ! $result['success'] ) {
			// Do not keep a “phantom” OTP the user can never have received.
			self::clear_otp_transient( $phone_e164 );
		}
		return $result;
	}
}
