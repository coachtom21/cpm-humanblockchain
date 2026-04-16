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
	 * Buyer proof-scan flow: client sends cpm_hb_proof_scan=1 or a nonce minted when ?proof=scan was on the initial request.
	 *
	 * @return bool
	 */
	private static function request_has_proof_scan_intent() {
		if ( isset( $_POST['cpm_hb_proof_scan'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_proof_scan'] ) ) ) {
			return true;
		}
		$nonce = isset( $_POST['cpm_hb_proof_scan_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['cpm_hb_proof_scan_nonce'] ) ) : '';
		return $nonce !== '' && (bool) wp_verify_nonce( $nonce, 'cpm_hb_proof_scan_flow' );
	}

	/**
	 * Unique transaction code for seller PoD scan completion (share with buyer).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	private static function create_seller_pod_transaction_code( $user_id ) {
		$user_id = (int) $user_id;
		$code    = 'HB-' . strtoupper( substr( bin2hex( random_bytes( 8 ) ), 0, 16 ) );
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, 'cpm_hb_last_seller_pod_tx_code', $code );
			update_user_meta( $user_id, 'cpm_hb_last_seller_pod_tx_at', time() );
		}
		return $code;
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
		if ( is_string( $meta ) && trim( $meta ) !== '' ) {
			return $meta;
		}
		$billing = get_user_meta( $user_id, 'billing_phone', true );
		if ( is_string( $billing ) && trim( $billing ) !== '' ) {
			return $billing;
		}
		return '';
	}

	/**
	 * Page slugs that qualify as the public backorders landing page (first match wins for permalinks).
	 *
	 * @return string[]
	 */
	public static function get_backorder_page_slugs() {
		return apply_filters(
			'cpm_hb_backorder_page_slugs',
			array( 'backorder', 'backorders' )
		);
	}

	/**
	 * URL for the site “backorder” page (slug `backorder` or `backorders`, or /backorder/ fallback).
	 * Used after OTP verify from the landing PoD flow and for localized redirects.
	 *
	 * @return string
	 */
	public static function get_backorder_page_url() {
		foreach ( self::get_backorder_page_slugs() as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof WP_Post ) {
				return get_permalink( $page );
			}
		}
		return home_url( '/backorder/' );
	}

	/**
	 * Whether the current request is the backorder page.
	 *
	 * @return bool
	 */
	public static function is_backorder_page_view() {
		if ( ! is_singular() ) {
			return false;
		}
		$slugs = self::get_backorder_page_slugs();
		if ( function_exists( 'is_page' ) ) {
			foreach ( $slugs as $slug ) {
				if ( is_page( $slug ) ) {
					return true;
				}
			}
		}
		// wp_enqueue_scripts runs before the loop; global $post may be unset. Queried object is reliable.
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post && in_array( (string) $queried->post_name, $slugs, true ) ) {
			return true;
		}
		global $post;
		return $post instanceof WP_Post && in_array( (string) $post->post_name, $slugs, true );
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
		add_action( 'wp_ajax_cpm_hb_buyer_confirm_delivery', array( __CLASS__, 'handle_buyer_confirm_delivery' ) );
		// Remove NWP device rows when the WP user is removed (same user_id / email in wp_nwp_devices).
		add_action( 'delete_user', array( __CLASS__, 'delete_devices_on_user_delete' ), 10, 3 );
		add_action( 'wpmu_delete_user', array( __CLASS__, 'delete_devices_on_user_delete' ), 10, 1 );
		add_action( 'deleted_user', array( __CLASS__, 'delete_devices_after_user_deleted' ), 10, 3 );
	}

	/**
	 * Whether NWP device rows should be removed for this user (same filter for all deletion hooks).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function should_delete_nwp_devices_for_user( $user_id ) {
		return (bool) apply_filters( 'cpm_nwp_delete_devices_on_user_delete', true, (int) $user_id );
	}

	/**
	 * Whether the NWP devices table exists for the current blog prefix.
	 *
	 * @param string $table Full table name (e.g. wp_nwp_devices).
	 * @return bool
	 */
	private static function nwp_devices_table_exists( $table ) {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ( $found === $table );
	}

	/**
	 * Delete rows in wp_*_nwp_devices for this user everywhere the table exists (current site or all subsites).
	 *
	 * @param int         $user_id          WordPress user ID.
	 * @param string|null $email_normalized Lowercased trimmed email, or null to skip email-based delete.
	 */
	private static function purge_nwp_devices_for_user( $user_id, $email_normalized = null ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}

		$site_ids = array( (int) get_current_blog_id() );
		if ( is_multisite() && function_exists( 'get_sites' ) ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			if ( ! is_array( $site_ids ) ) {
				$site_ids = array( (int) get_current_blog_id() );
			}
		}

		foreach ( $site_ids as $blog_id ) {
			$switched = false;
			if ( is_multisite() ) {
				switch_to_blog( (int) $blog_id );
				$switched = true;
			}

			global $wpdb;
			$table = $wpdb->prefix . self::TABLE_NAME;
			if ( self::nwp_devices_table_exists( $table ) ) {
				$wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );
				if ( is_string( $email_normalized ) && $email_normalized !== '' ) {
					$wpdb->query(
						$wpdb->prepare(
							"DELETE FROM {$table} WHERE LOWER(TRIM(email)) = %s",
							$email_normalized
						)
					);
				}
			}

			if ( $switched ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Remove all NWP device rows when a WordPress user is deleted (clears stored device_hash so the browser can register again).
	 * Runs on {@see 'delete_user'} (before row removal from wp_users) and {@see 'wpmu_delete_user'} (multisite network delete).
	 * Deletes by user_id and by normalized email to cover orphan rows.
	 *
	 * @param int           $user_id  User ID being deleted.
	 * @param int|null      $reassign User ID to reassign content to, or null.
	 * @param \WP_User|null $user     User object (WP 5.5+); used for email when present.
	 */
	public static function delete_devices_on_user_delete( $user_id, $reassign = null, $user = null ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		if ( ! self::should_delete_nwp_devices_for_user( $user_id ) ) {
			return;
		}

		$email = '';
		if ( $user instanceof \WP_User ) {
			$email = $user->user_email;
		} elseif ( $user_id > 0 ) {
			$ud = get_userdata( $user_id );
			if ( $ud ) {
				$email = $ud->user_email;
			}
		}

		$email_norm = ( $email !== '' ) ? strtolower( trim( $email ) ) : null;
		self::purge_nwp_devices_for_user( $user_id, $email_norm );
	}

	/**
	 * Safety net: delete any remaining rows by user_id after wp_users row is gone ({@see 'deleted_user'}).
	 *
	 * @param int           $user_id  User ID that was deleted.
	 * @param int|null      $reassign Unused.
	 * @param \WP_User|null $user     Unused (user no longer in DB).
	 */
	public static function delete_devices_after_user_deleted( $user_id, $reassign = null, $user = null ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		if ( ! self::should_delete_nwp_devices_for_user( $user_id ) ) {
			return;
		}
		self::purge_nwp_devices_for_user( $user_id, null );
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
	 * Buyer + proof=scan: also requires Smallstreet user-by-mobile.
	 *
	 * @since 1.0.0
	 */
	public static function handle_send_otp() {
		if ( ! check_ajax_referer( 'cpm_nwp_send_otp', 'cpm_nwp_otp_nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your session expired or this page is outdated. Please reload the page and try Send OTP again.', 'cpm-humanblockchain' ),
				),
				403
			);
		}

		$mobile_raw = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		if ( empty( $mobile_raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your mobile number.', 'cpm-humanblockchain' ) ) );
		}

		$phone_e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile_raw );
		if ( ! $phone_e164 ) {
			wp_send_json_error( array( 'message' => Cpm_Humanblockchain_Otp_Service::normalize_phone_failure_message( $mobile_raw ) ) );
		}

		$buyer_proof_scan = isset( $_POST['cpm_hb_buyer_proof_scan'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_buyer_proof_scan'] ) );
		$landing_role     = isset( $_POST['cpm_hb_user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['cpm_hb_user_role'] ) ) : '';
		$local_device     = self::find_device_id_by_phone( $mobile_raw );

		if ( $buyer_proof_scan ) {
			if ( 'buyer' !== $landing_role ) {
				wp_send_json_error( array( 'message' => __( 'Buyer role is required for this verification path.', 'cpm-humanblockchain' ) ) );
			}
			$proof_scan_url = self::request_has_proof_scan_intent();
			if ( ! $proof_scan_url ) {
				wp_send_json_error( array( 'message' => __( 'Open this flow from a link that includes ?proof=scan in the URL.', 'cpm-humanblockchain' ) ) );
			}
			if ( ! $local_device ) {
				wp_send_json_error(
					array(
						'message' => __( 'This number is not registered in HumanBlockchain (wp_nwp_devices). Register your device first.', 'cpm-humanblockchain' ),
					)
				);
			}
			if ( ! class_exists( 'Cpm_Humanblockchain_Smallstreet_Backorders' ) || ! Cpm_Humanblockchain_Smallstreet_Backorders::is_configured() ) {
				wp_send_json_error(
					array(
						'message' => __( 'Smallstreet API is not configured for the buyer proof-of-delivery flow. Add the backorders API key under Settings → NWP Gateway (Membership & APIs), or use Register → Activate device for normal OTP without this check.', 'cpm-humanblockchain' ),
					)
				);
			}
			if ( ! Cpm_Humanblockchain_Smallstreet_Backorders::user_exists_by_mobile( $mobile_raw ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'This number was not found on Smallstreet for your account. Use the mobile linked to your Smallstreet / WooCommerce profile.', 'cpm-humanblockchain' ),
					)
				);
			}
		} elseif ( ! $local_device ) {
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
		if ( ! check_ajax_referer( 'cpm_nwp_verify_otp', 'cpm_nwp_verify_nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your session expired or this page is outdated. Please reload the page and try again.', 'cpm-humanblockchain' ),
				),
				403
			);
		}

		$mobile_raw = isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '';
		$otp_code   = isset( $_POST['otp'] ) ? sanitize_text_field( wp_unslash( $_POST['otp'] ) ) : '';

		if ( empty( $mobile_raw ) || $otp_code === '' ) {
			wp_send_json_error( array( 'message' => __( 'Mobile number and verification code are required.', 'cpm-humanblockchain' ) ) );
		}

		$phone_e164 = Cpm_Humanblockchain_Otp_Service::normalize_phone_e164( $mobile_raw );
		if ( ! $phone_e164 ) {
			wp_send_json_error( array( 'message' => Cpm_Humanblockchain_Otp_Service::normalize_phone_failure_message( $mobile_raw ) ) );
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
		$buyer_proof_scan  = isset( $_POST['cpm_hb_buyer_proof_scan'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_buyer_proof_scan'] ) );
		$proof_scan_url    = self::request_has_proof_scan_intent();
		$landing_role      = isset( $_POST['cpm_hb_user_role'] ) ? sanitize_text_field( wp_unslash( $_POST['cpm_hb_user_role'] ) ) : '';
		$redirect_backorders = $landing_backorder && $buyer_proof_scan && $proof_scan_url && 'buyer' === $landing_role;
		$seller_pod_complete = $landing_backorder && 'seller' === $landing_role && $proof_scan_url;

		$seller_transaction_code = '';
		if ( $seller_pod_complete ) {
			$seller_transaction_code = self::create_seller_pod_transaction_code( $wp_uid );
			if ( class_exists( 'Cpm_Humanblockchain_Xp_Ledger' ) ) {
				Cpm_Humanblockchain_Xp_Ledger::record_seller_scan_after_verification( $wp_uid, $seller_transaction_code );
			}
		}

		if ( $landing_backorder ) {
			// Backorders: buyer + ?proof=scan. Seller + proof-scan: transaction code modal (no redirect). Else home.
			if ( $redirect_backorders ) {
				$redirect = apply_filters( 'cpm_hb_wc_backorder_redirect_url', self::get_backorder_page_url() );
			} elseif ( $seller_pod_complete ) {
				$redirect = '';
			} else {
				$redirect = apply_filters( 'cpm_hb_landing_verify_home_url', home_url( '/' ) );
			}
			$show_discord = false;
		} else {
			// Default: no immediate redirect; show Discord invite modal. Set redirect URL via filter to skip modal.
			$redirect     = apply_filters( 'cpm_nwp_after_verify_redirect', '' );
			$show_discord = (bool) apply_filters( 'cpm_nwp_after_verify_show_discord_modal', true );
		}

		$smallstreet_backorders = array();
		if ( $redirect_backorders && class_exists( 'Cpm_Humanblockchain_Smallstreet_Backorders' ) && Cpm_Humanblockchain_Smallstreet_Backorders::is_configured() ) {
			$smallstreet_backorders = Cpm_Humanblockchain_Smallstreet_Backorders::get_backorders_for_display( $mobile_raw );
			if ( ! empty( $smallstreet_backorders ) && $wp_uid > 0 ) {
				Cpm_Humanblockchain_Smallstreet_Backorders::save_user_backorders_cache( $wp_uid, $smallstreet_backorders );
			}
		}

		$payload = array(
			'message'            => $check['message'],
			'redirect_url'       => $redirect ? esc_url_raw( $redirect ) : '',
			'show_discord_modal' => (bool) $show_discord,
		);
		if ( $redirect_backorders ) {
			$payload['smallstreet_backorders'] = $smallstreet_backorders;
		}
		if ( $seller_pod_complete ) {
			$payload['seller_scan_success']     = true;
			$payload['seller_transaction_code'] = $seller_transaction_code;
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Buyer confirms delivery on the backorders page using the seller’s PoD transaction code (HB-…).
	 *
	 * @since 1.0.0
	 */
	public static function handle_buyer_confirm_delivery() {
		if ( ! check_ajax_referer( 'cpm_hb_backorders_confirm', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Session expired. Reload the page and try again.', 'cpm-humanblockchain' ) ),
				403
			);
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'cpm-humanblockchain' ) ) );
		}

		$code = isset( $_POST['transaction_code'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_code'] ) ) : '';
		$code = strtoupper( trim( preg_replace( '/\s+/', '', $code ) ) );

		$order_ids_raw = isset( $_POST['order_ids'] ) ? wp_unslash( $_POST['order_ids'] ) : '[]';
		$order_ids     = json_decode( $order_ids_raw, true );
		if ( ! is_array( $order_ids ) ) {
			$order_ids = array();
		}
		$order_ids = array_values( array_unique( array_map( 'intval', array_filter( $order_ids ) ) ) );

		if ( $code === '' || empty( $order_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter the transaction code and select at least one order.', 'cpm-humanblockchain' ) ) );
		}
		if ( ! preg_match( '/^HB-[A-F0-9]{16}$/', $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid transaction code format.', 'cpm-humanblockchain' ) ) );
		}

		global $wpdb;
		$seller_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'cpm_hb_last_seller_pod_tx_code',
				$code
			)
		);
		$seller_id = (int) $seller_id;
		if ( $seller_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'That transaction code was not found. Ask the seller for the code shown after they verify their phone.', 'cpm-humanblockchain' ) ) );
		}

		$at      = (int) get_user_meta( $seller_id, 'cpm_hb_last_seller_pod_tx_at', true );
		$max_age = (int) apply_filters( 'cpm_hb_seller_pod_transaction_code_ttl', 7 * DAY_IN_SECONDS );
		if ( $at > 0 && $max_age > 0 && ( time() - $at ) > $max_age ) {
			wp_send_json_error( array( 'message' => __( 'This transaction code has expired. Ask the seller to complete the delivery verification flow again.', 'cpm-humanblockchain' ) ) );
		}

		$buyer_id = (int) get_current_user_id();

		if ( class_exists( 'Cpm_Humanblockchain_Xp_Ledger' ) ) {
			Cpm_Humanblockchain_Xp_Ledger::record_buyer_scan_after_confirm( $buyer_id, $seller_id, $code, $order_ids );
		}

		/**
		 * After buyer confirms with a valid seller transaction code (extend to call Smallstreet / Woo, etc.).
		 *
		 * @param int    $buyer_id  Current user.
		 * @param int    $seller_id Matched seller user ID.
		 * @param int[]  $order_ids Woo / Smallstreet order IDs from the table.
		 * @param string $code      Transaction code.
		 */
		do_action( 'cpm_hb_buyer_confirmed_delivery', $buyer_id, $seller_id, $order_ids, $code );

		update_user_meta( $buyer_id, 'cpm_hb_last_buyer_pod_confirm_at', time() );
		update_user_meta(
			$buyer_id,
			'cpm_hb_last_buyer_pod_confirm_payload',
			array(
				'order_ids' => $order_ids,
				'seller_id' => $seller_id,
				'code'      => $code,
			)
		);

		$redirect = esc_url_raw( apply_filters( 'cpm_hb_buyer_confirm_redirect_url', home_url( '/' ) ) );

		wp_send_json_success(
			array(
				'message'      => __( 'Delivery confirmation recorded.', 'cpm-humanblockchain' ),
				'redirect_url' => $redirect,
			)
		);
	}
}
