<?php

/**
 * Device Registry - handles device registration and wp_nwp_devices table.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Device Registry class.
 */
class Cpm_Humanblockchain_Device_Registry {

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'nwp_devices';

	/**
	 * SQL expression: phone column with formatting characters stripped.
	 *
	 * @return string
	 */
	private static function phone_digits_sql_expr() {
		return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', '')";
	}

	/**
	 * Find device row id by mobile (handles Nepal/US and legacy stored formats).
	 *
	 * @param string $mobile_raw Raw phone input.
	 * @return string|null Row id or null.
	 */
	private static function find_device_id_by_phone( $mobile_raw ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . self::TABLE_NAME;
		$digits_expr  = self::phone_digits_sql_expr();
		$variants     = Cpm_Humanblockchain_Otp_Service::get_phone_match_variants( $mobile_raw );
		$last10       = Cpm_Humanblockchain_Otp_Service::get_phone_last_national_digits_for_match( $mobile_raw );
		$placeholders = array();
		$args         = array();

		if ( ! empty( $variants ) ) {
			$in = implode( ', ', array_fill( 0, count( $variants ), '%s' ) );
			$placeholders[] = "$digits_expr IN ($in)";
			$args           = array_merge( $args, $variants );
		}
		if ( $last10 ) {
			$placeholders[] = "RIGHT($digits_expr, 10) = %s";
			$args[]         = $last10;
		}
		if ( empty( $placeholders ) ) {
			return null;
		}

		$sql = "SELECT id FROM $table_name WHERE phone IS NOT NULL AND phone != '' AND (" . implode( ' OR ', $placeholders ) . ') LIMIT 1';
		// $wpdb->prepare() requires one argument per placeholder — not a single array.
		return $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );
	}

	/**
	 * Whether a WordPress user has at least one NWP device with status "activated".
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool
	 */
	public static function user_has_activated_device( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE user_id = %d AND registration_status = %s LIMIT 1",
				$user_id,
				'activated'
			)
		);
		return (bool) $found;
	}

	/**
	 * Whether the current logged-in user has an activated NWP device.
	 *
	 * @return bool
	 */
	public static function current_user_has_activated_device() {
		$uid = get_current_user_id();
		return $uid ? self::user_has_activated_device( $uid ) : false;
	}

	/**
	 * Best-effort phone for a WordPress user: latest NWP device row, then user meta `phone`.
	 *
	 * @param int $user_id User ID.
	 * @return string Phone string or empty.
	 */
	public static function get_phone_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return '';
		}
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$phone = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT phone FROM {$table} WHERE user_id = %d AND phone IS NOT NULL AND phone != '' ORDER BY updated_at DESC, id DESC LIMIT 1",
				$user_id
			)
		);
		if ( is_string( $phone ) && $phone !== '' ) {
			return $phone;
		}
		$meta = get_user_meta( $user_id, 'phone', true );
		return is_string( $meta ) ? $meta : '';
	}

	/**
	 * URL for the site “backorder” page (slug `backorder`, or /backorder/ fallback).
	 * Used after OTP verify from the landing PoD flow and for localized redirects.
	 *
	 * @return string
	 */
	public static function get_backorder_page_url() {
		$page = get_page_by_path( 'backorder' );
		if ( $page instanceof WP_Post ) {
			return get_permalink( $page );
		}
		return home_url( '/backorder/' );
	}

	/**
	 * Build a unique username from an email local-part.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private static function generate_unique_username_from_email( $email ) {
		$at = strpos( $email, '@' );
		$local = false !== $at ? substr( $email, 0, $at ) : $email;
		$base  = sanitize_user( $local, true );
		if ( '' === $base ) {
			$base = 'nwp_user';
		}
		$base = substr( $base, 0, 60 );
		$username = $base;
		$n        = 0;
		while ( username_exists( $username ) ) {
			$n++;
			$suffix   = (string) $n;
			$username = substr( $base, 0, max( 1, 60 - strlen( $suffix ) ) ) . $suffix;
		}
		return $username;
	}

	/**
	 * Resolve WordPress user ID for a new device: logged-in user, existing email, or newly created account.
	 *
	 * @param string   $email        Sanitized registration email.
	 * @param bool     $created_new  Set true if a new user was created (for rollback on DB failure).
	 * @return int|\WP_Error
	 */
	private static function get_or_create_wp_user_for_device( $email, &$created_new ) {
		$created_new = false;

		$current_id = get_current_user_id();
		if ( $current_id > 0 ) {
			return (int) $current_id;
		}

		$existing = email_exists( $email );
		if ( $existing ) {
			return (int) $existing;
		}

		$username = self::generate_unique_username_from_email( $email );
		$password = wp_generate_password( 24, true, true );
		$role     = apply_filters( 'cpm_nwp_new_user_role', 'subscriber', $email );

		$user_id = wp_insert_user(
			array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => $role,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$created_new = true;
		return (int) $user_id;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'wp_ajax_cpm_nwp_register_device', array( __CLASS__, 'handle_register_device' ) );
		add_action( 'wp_ajax_nopriv_cpm_nwp_register_device', array( __CLASS__, 'handle_register_device' ) );
		add_action( 'wp_ajax_cpm_nwp_send_otp', array( __CLASS__, 'handle_send_otp' ) );
		add_action( 'wp_ajax_nopriv_cpm_nwp_send_otp', array( __CLASS__, 'handle_send_otp' ) );
		add_action( 'wp_ajax_cpm_nwp_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );
		add_action( 'wp_ajax_nopriv_cpm_nwp_verify_otp', array( __CLASS__, 'handle_verify_otp' ) );
		add_action( 'deleted_user', array( __CLASS__, 'delete_devices_for_deleted_user' ), 10, 1 );
	}

	/**
	 * Remove all NWP device rows when a WordPress user is deleted.
	 *
	 * @param int $user_id Deleted user ID.
	 */
	public static function delete_devices_for_deleted_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		if ( ! apply_filters( 'cpm_nwp_delete_devices_on_user_delete', true, $user_id ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
	}

	/**
	 * Handle device registration AJAX request.
	 *
	 * @since 1.0.0
	 */
	public static function handle_register_device() {
		check_ajax_referer( 'cpm_nwp_device_register', 'cpm_nwp_register_nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$mobile      = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		$qrtiger     = isset( $_POST['qrtiger_vcard_link'] ) ? esc_url_raw( wp_unslash( $_POST['qrtiger_vcard_link'] ) ) : '';
		$device_hash = isset( $_POST['device_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['device_hash'] ) ) : '';
		$referral    = isset( $_POST['referral_source_nwp_id'] ) ? absint( $_POST['referral_source_nwp_id'] ) : 0;
		$geo_lat     = isset( $_POST['geo_lat'] ) ? floatval( $_POST['geo_lat'] ) : null;
		$geo_lng     = isset( $_POST['geo_lng'] ) ? floatval( $_POST['geo_lng'] ) : null;

		// Required: email and device_hash (per doc)
		if ( empty( $email ) || empty( $device_hash ) ) {
			wp_send_json_error( array( 'message' => __( 'Email and device identification are required.', 'cpm-humanblockchain' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'cpm-humanblockchain' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// Repeat scan: same device_hash — only short-circuit if the linked WP user still exists.
		// Otherwise remove orphan rows (user deleted, failed run, or stale live data) and continue registration.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id FROM $table_name WHERE device_hash = %s LIMIT 1",
				$device_hash
			)
		);

		if ( $existing ) {
			$linked_uid = isset( $existing->user_id ) ? (int) $existing->user_id : 0;
			if ( $linked_uid > 0 && get_userdata( $linked_uid ) ) {
				wp_send_json_success(
					array(
						'message'   => __( 'This device is already registered. You can proceed to the next steps.', 'cpm-humanblockchain' ),
						'repeated'  => true,
						'device_id' => (int) $existing->id,
					)
				);
			}
			$wpdb->delete( $table_name, array( 'device_hash' => $device_hash ), array( '%s' ) );
		}

		// Email already registered (another device or prior registration)
		$email_normalized = strtolower( trim( $email ) );
		$email_exists     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_name WHERE LOWER(TRIM(email)) = %s LIMIT 1",
				$email_normalized
			)
		);
		if ( $email_exists ) {
			wp_send_json_error( array( 'message' => __( 'This email address is already registered.', 'cpm-humanblockchain' ) ) );
		}

		// Mobile already registered (when provided)
		if ( ! empty( $mobile ) ) {
			$phone_exists = self::find_device_id_by_phone( $mobile );
			if ( $phone_exists ) {
				wp_send_json_error( array( 'message' => __( 'This mobile number is already registered.', 'cpm-humanblockchain' ) ) );
			}
		}

		$created_new_wp_user = false;
		$wp_user_id          = 0;

		// 1) Resolve WordPress user: logged-in → existing by email → create new.
		$current_uid = get_current_user_id();
		if ( $current_uid > 0 ) {
			$wp_user_id = (int) $current_uid;
		} else {
			$existing_uid = email_exists( $email );
			if ( $existing_uid ) {
				$wp_user_id = (int) $existing_uid;
			} else {
				$wp_user_id = self::get_or_create_wp_user_for_device( $email, $created_new_wp_user );
				if ( is_wp_error( $wp_user_id ) ) {
					wp_send_json_error(
						array(
							'message' => sprintf(
								/* translators: %s: WordPress error message */
								__( 'Could not create your account: %s', 'cpm-humanblockchain' ),
								$wp_user_id->get_error_message()
							),
						)
					);
				}
			}
		}

		if ( ! empty( $mobile ) ) {
			update_user_meta( $wp_user_id, 'phone', $mobile );
		}

		$ip_address    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null;
		$user_agent    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null;
		$registered_at = current_time( 'mysql' );

		// Insert only doc-specified fields: device_hash, timestamp, geo, email, mobile, qrtiger (if available), referral
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'                => $wp_user_id,
				'device_hash'            => $device_hash,
				'email'                  => $email,
				'phone'                  => $mobile ?: null,
				'geo_lat'                => $geo_lat ?: null,
				'geo_lng'                => $geo_lng ?: null,
				'registered_at'          => $registered_at,
				'registration_status'    => 'registered',
				'referral_source_nwp_id' => $referral ?: null,
				'qrtiger_vcard_link'     => $qrtiger ?: null,
				'ip_address'             => $ip_address,
				'user_agent'             => substr( $user_agent, 0, 512 ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			if ( $created_new_wp_user ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $wp_user_id );
			}
			wp_send_json_error( array( 'message' => __( 'Registration failed. Please try again.', 'cpm-humanblockchain' ) ) );
		}

		$device_id = (int) $wpdb->insert_id;

		// 3) Sync to register-user REST API (Smallstreet / remote) after local row exists — failure does not roll back device registration.
		$register_user_sync = null;
		if ( apply_filters( 'cpm_hb_register_user_sync_after_device', true, $device_id, $wp_user_id, $email )
			&& class_exists( 'Cpm_Humanblockchain_Register_User_Api' )
			&& Cpm_Humanblockchain_Register_User_Api::is_configured()
			&& Cpm_Humanblockchain_Register_User_Api::phone_has_enough_digits_for_api( $mobile ) ) {
			$sync_result = Cpm_Humanblockchain_Register_User_Api::register_user_for_device(
				array(
					'email'       => $email,
					'mobile'      => $mobile,
					'geo_lat'     => $geo_lat,
					'geo_lng'     => $geo_lng,
					'device_hash' => $device_hash,
					'referral'    => $referral,
					'qrtiger'     => $qrtiger,
				)
			);
			if ( is_wp_error( $sync_result ) ) {
				$register_user_sync = array(
					'ok'      => false,
					'message' => $sync_result->get_error_message(),
				);
			} else {
				$register_user_sync = array(
					'ok' => true,
				);
			}
		}

		$response = array(
			'message'   => __( 'Device registered successfully. You are ready for the next steps.', 'cpm-humanblockchain' ),
			'device_id' => $device_id,
			'repeated'  => false,
		);
		if ( null !== $register_user_sync ) {
			$response['register_user_sync'] = $register_user_sync;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Handle Send OTP AJAX request.
	 * Checks wp_nwp_devices for phone; only sends OTP if found.
	 *
	 * @since 1.0.0
	 */
	public static function handle_send_otp() {
		check_ajax_referer( 'cpm_nwp_send_otp', 'cpm_nwp_otp_nonce' );

		$mobile_raw = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		if ( empty( $mobile_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your mobile number.', 'cpm-humanblockchain' ) ) );
		}

		$phone_e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile_raw );
		if ( ! $phone_e164 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid mobile number (e.g. 9849158973 or +9779849158973).', 'cpm-humanblockchain' ) ) );
		}

		$exists = self::find_device_id_by_phone( $mobile_raw );

		if ( ! $exists ) {
			wp_send_json_error( array( 'message' => __( 'This phone number is not registered. Please register your device first.', 'cpm-humanblockchain' ) ) );
		}

		if ( ! Cpm_Humanblockchain_Otp_Service::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'SMS service is not configured. Contact the administrator.', 'cpm-humanblockchain' ) ) );
		}

		$result = Cpm_Humanblockchain_Otp_Service::send_otp_sms( $phone_e164 );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => __( 'Verification code sent to your phone. Check your messages.', 'cpm-humanblockchain' ) ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Handle Verify OTP AJAX: check code, mark device activated, log user in, then client shows Discord step (or redirect if filtered).
	 *
	 * @since 1.0.0
	 */
	public static function handle_verify_otp() {
		check_ajax_referer( 'cpm_nwp_verify_otp', 'cpm_nwp_verify_nonce' );

		$mobile_raw = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		$otp_code   = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';

		if ( empty( $mobile_raw ) || $otp_code === '' ) {
			wp_send_json_error( array( 'message' => __( 'Mobile number and verification code are required.', 'cpm-humanblockchain' ) ) );
		}

		$phone_e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile_raw );
		if ( ! $phone_e164 ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid mobile number.', 'cpm-humanblockchain' ) ) );
		}

		$device_id = self::find_device_id_by_phone( $mobile_raw );
		if ( ! $device_id ) {
			wp_send_json_error( array( 'message' => __( 'This phone number is not registered.', 'cpm-humanblockchain' ) ) );
		}

		$check = Cpm_Humanblockchain_Otp_Service::verify_otp( $phone_e164, $otp_code, false );
		if ( ! $check['success'] ) {
			wp_send_json_error( array( 'message' => $check['message'] ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$device_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, email FROM {$table_name} WHERE id = %d",
				(int) $device_id
			)
		);

		$wp_uid = $device_row ? (int) $device_row->user_id : 0;
		if ( $wp_uid <= 0 && $device_row && ! empty( $device_row->email ) ) {
			$by_email = get_user_by( 'email', $device_row->email );
			if ( $by_email ) {
				$wp_uid = (int) $by_email->ID;
			}
		}

		if ( $wp_uid <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'No WordPress account is linked to this device. Contact support.', 'cpm-humanblockchain' ) ) );
		}

		$user = get_userdata( $wp_uid );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Could not log you in. Contact support.', 'cpm-humanblockchain' ) ) );
		}

		$updated = $wpdb->update(
			$table_name,
			array(
				'registration_status' => 'activated',
				'updated_at'          => current_time( 'mysql' ),
			),
			array( 'id' => (int) $device_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not save verification. Please try again.', 'cpm-humanblockchain' ) ) );
		}

		Cpm_Humanblockchain_Otp_Service::clear_otp_transient( $phone_e164 );

		wp_set_current_user( $wp_uid );
		wp_set_auth_cookie( $wp_uid, true, is_ssl() );
		/** This action is documented in wp-includes/user.php. */
		do_action( 'wp_login', $user->user_login, $user );

		$landing_backorder = isset( $_POST['cpm_hb_verify_redirect'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_verify_redirect'] ) );

		if ( $landing_backorder ) {
			$redirect     = apply_filters( 'cpm_hb_wc_backorder_redirect_url', self::get_backorder_page_url() );
			$show_discord = false;
		} else {
			// Default: no immediate redirect; show Discord invite modal. Set redirect URL via filter to skip modal.
			$redirect     = apply_filters( 'cpm_nwp_after_verify_redirect', '' );
			$show_discord = (bool) apply_filters( 'cpm_nwp_after_verify_show_discord_modal', true );
		}

		wp_send_json_success(
			array(
				'message'            => $check['message'],
				'redirect_url'       => $redirect ? esc_url_raw( $redirect ) : '',
				'show_discord_modal' => (bool) $show_discord,
			)
		);
	}
}
