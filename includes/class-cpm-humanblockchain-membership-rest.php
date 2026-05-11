<?php
/**
 * REST API: POST myapi/v1/membership — grant/cancel PMPro (same contract as Smallstreet theme).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paid Memberships Pro membership grant/cancel via Bearer token.
 */
class Cpm_Humanblockchain_Membership_Rest {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'myapi/v1',
			'/membership',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_assign' ),
				'permission_callback' => array( __CLASS__, 'permission_bearer' ),
			)
		);
	}

	/**
	 * Bearer token must match {@see Cpm_Humanblockchain_Membership::get_api_key()}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function permission_bearer( WP_REST_Request $request ) {
		$stored = Cpm_Humanblockchain_Membership::get_api_key();
		if ( $stored === '' ) {
			return new WP_Error(
				'membership_api_disabled',
				__( 'Membership API key is not configured.', 'cpm-humanblockchain' ),
				array( 'status' => 503 )
			);
		}
		$auth_header = $request->get_header( 'Authorization' );
		$auth_header = is_string( $auth_header ) ? $auth_header : '';
		$api_key     = preg_replace( '/^\s*Bearer\s+/i', '', trim( $auth_header ) );
		if ( ! is_string( $api_key ) || ! hash_equals( $stored, $api_key ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Invalid or missing Bearer token.', 'cpm-humanblockchain' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * @param string $phone Raw phone.
	 * @return string Digits only.
	 */
	private static function normalize_phone( $phone ) {
		return preg_replace( '/\D+/', '', (string) $phone );
	}

	/**
	 * @param int    $user_id    User ID.
	 * @param string $phone_norm Digits-only.
	 * @return bool
	 */
	private static function user_phone_matches( $user_id, $phone_norm ) {
		if ( $phone_norm === '' ) {
			return false;
		}
		$billing = self::normalize_phone( get_user_meta( $user_id, 'billing_phone', true ) );
		$mega    = self::normalize_phone( get_user_meta( $user_id, 'mega-mobile', true ) );
		if ( $billing === '' && $mega === '' ) {
			return true;
		}
		return ( $billing !== '' && $billing === $phone_norm ) || ( $mega !== '' && $mega === $phone_norm );
	}

	/**
	 * @param string $phone_norm Digits-only.
	 * @param string $email      Lowercase compare.
	 * @return bool
	 */
	private static function phone_registered_to_other_email( $phone_norm, $email ) {
		global $wpdb;
		if ( $phone_norm === '' ) {
			return false;
		}
		$email_lower = strtolower( $email );
		$rows        = $wpdb->get_results(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ('billing_phone','mega-mobile') AND meta_value != ''",
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return false;
		}
		foreach ( $rows as $row ) {
			if ( self::normalize_phone( $row['meta_value'] ) !== $phone_norm ) {
				continue;
			}
			$u = get_userdata( (int) $row['user_id'] );
			if ( $u && strtolower( $u->user_email ) !== $email_lower ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array  $params    Request body.
	 * @param string $email     Sanitized email.
	 * @param string $phone_raw Phone as sent.
	 * @return array|WP_Error
	 */
	private static function create_wp_user( array $params, $email, $phone_raw ) {
		$pass_in = isset( $params['password'] ) ? (string) wp_unslash( $params['password'] ) : '';
		if ( $pass_in !== '' && strlen( $pass_in ) < 6 ) {
			return new WP_Error(
				'weak_password',
				__( 'If password is provided it must be at least 6 characters.', 'cpm-humanblockchain' ),
				array( 'status' => 400 )
			);
		}

		$password           = $pass_in;
		$password_generated = false;
		if ( $password === '' ) {
			$password           = wp_generate_password( 24, true, true );
			$password_generated = true;
		}

		$username_in = isset( $params['username'] ) ? sanitize_user( wp_unslash( $params['username'] ), true ) : '';
		$wc_args       = array();
		if ( ! empty( $params['first_name'] ) ) {
			$wc_args['first_name'] = sanitize_text_field( wp_unslash( $params['first_name'] ) );
		}
		if ( ! empty( $params['last_name'] ) ) {
			$wc_args['last_name'] = sanitize_text_field( wp_unslash( $params['last_name'] ) );
		}

		if ( function_exists( 'wc_create_new_customer' ) ) {
			$new_id = wc_create_new_customer( $email, $username_in, $password, $wc_args );
			if ( is_wp_error( $new_id ) ) {
				$code   = $new_id->get_error_code();
				$status = ( strpos( $code, 'email-exists' ) !== false || strpos( $code, 'username-exists' ) !== false ) ? 409 : 400;
				return new WP_Error( $code, $new_id->get_error_message(), array( 'status' => $status ) );
			}
			$new_id = (int) $new_id;
			update_user_meta( $new_id, 'billing_phone', $phone_raw );
			update_user_meta( $new_id, 'mega-mobile', $phone_raw );
			return array(
				'user_id'            => $new_id,
				'user_created'       => true,
				'password_generated' => $password_generated,
				'password'           => $password_generated ? $password : null,
			);
		}

		$base = sanitize_user( sanitize_title( current( explode( '@', $email ) ) ), true );
		if ( $base === '' ) {
			$base = 'user';
		}
		$login = $base;
		$i     = 0;
		while ( username_exists( $login ) ) {
			++$i;
			$login = $base . $i;
		}

		$uid = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => $password,
				'first_name' => isset( $wc_args['first_name'] ) ? $wc_args['first_name'] : '',
				'last_name'  => isset( $wc_args['last_name'] ) ? $wc_args['last_name'] : '',
				'role'       => 'subscriber',
			)
		);

		if ( is_wp_error( $uid ) ) {
			return new WP_Error(
				'user_create_failed',
				$uid->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$uid = (int) $uid;
		update_user_meta( $uid, 'billing_phone', $phone_raw );
		update_user_meta( $uid, 'mega-mobile', $phone_raw );

		return array(
			'user_id'            => $uid,
			'user_created'       => true,
			'password_generated' => $password_generated,
			'password'           => $password_generated ? $password : null,
		);
	}

	private static function save_api_response_to_user_meta( $user_id, array $api_data ) {
		if ( class_exists( 'Cpm_Humanblockchain_Membership' ) ) {
			Cpm_Humanblockchain_Membership::save_membership_response_to_user_meta( $user_id, $api_data );
		}
	}

	/**
	 * POST handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_assign( WP_REST_Request $request ) {
		if ( ! function_exists( 'pmpro_changeMembershipLevel' ) || ! function_exists( 'pmpro_getLevel' ) ) {
			return new WP_Error(
				'pmpro_missing',
				__( 'Paid Memberships Pro is not active.', 'cpm-humanblockchain' ),
				array( 'status' => 503 )
			);
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$level_id   = isset( $params['level_id'] ) ? absint( $params['level_id'] ) : 0;
		$level_name = isset( $params['level_name'] ) ? sanitize_text_field( wp_unslash( $params['level_name'] ) ) : '';

		if ( ! $level_id && $level_name && function_exists( 'pmpro_getAllLevels' ) ) {
			foreach ( pmpro_getAllLevels( true ) as $lvl ) {
				if ( strtolower( (string) $lvl->name ) === strtolower( $level_name ) ) {
					$level_id = (int) $lvl->id;
					break;
				}
			}
		}

		if ( ! $level_id ) {
			return new WP_Error(
				'missing_level',
				__( 'Provide a valid level_id or level_name that exists in PMPro.', 'cpm-humanblockchain' ),
				array( 'status' => 400 )
			);
		}

		$level_obj = pmpro_getLevel( $level_id );
		if ( empty( $level_obj ) ) {
			return new WP_Error(
				'invalid_level',
				__( 'Membership level does not exist.', 'cpm-humanblockchain' ),
				array( 'status' => 404 )
			);
		}

		$email     = isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '';
		$phone_raw = isset( $params['phone'] ) ? trim( wp_unslash( (string) $params['phone'] ) ) : '';
		if ( $phone_raw === '' && isset( $params['mobile'] ) ) {
			$phone_raw = trim( wp_unslash( (string) $params['mobile'] ) );
		}

		if ( ! is_email( $email ) || $phone_raw === '' ) {
			return new WP_Error(
				'missing_fields',
				__( 'Valid email and phone (or mobile) are required.', 'cpm-humanblockchain' ),
				array( 'status' => 400 )
			);
		}

		$phone_norm = self::normalize_phone( $phone_raw );
		if ( strlen( $phone_norm ) < 8 ) {
			return new WP_Error(
				'invalid_phone',
				__( 'Phone must contain at least 8 digits.', 'cpm-humanblockchain' ),
				array( 'status' => 400 )
			);
		}

		$do_cancel       = ! empty( $params['cancel'] );
		$user_id_param   = isset( $params['user_id'] ) ? absint( $params['user_id'] ) : 0;
		$user            = null;
		$user_id         = 0;
		$user_created    = false;
		$password_generated = false;
		$plain_password  = null;

		if ( $user_id_param ) {
			$user = get_userdata( $user_id_param );
			if ( ! $user ) {
				return new WP_Error( 'user_not_found', __( 'User not found.', 'cpm-humanblockchain' ), array( 'status' => 404 ) );
			}
			if ( strtolower( (string) $user->user_email ) !== strtolower( $email ) ) {
				return new WP_Error(
					'email_mismatch',
					__( 'email does not match user_id.', 'cpm-humanblockchain' ),
					array( 'status' => 400 )
				);
			}
			if ( ! self::user_phone_matches( $user_id_param, $phone_norm ) ) {
				return new WP_Error(
					'phone_mismatch',
					__( 'Phone does not match this account.', 'cpm-humanblockchain' ),
					array( 'status' => 403 )
				);
			}
			$user_id = $user_id_param;
		} else {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				if ( ! self::user_phone_matches( (int) $user->ID, $phone_norm ) ) {
					return new WP_Error(
						'phone_mismatch',
						__( 'Phone does not match this email on file.', 'cpm-humanblockchain' ),
						array( 'status' => 403 )
					);
				}
				$user_id = (int) $user->ID;
			} elseif ( $do_cancel ) {
				return new WP_Error( 'user_not_found', __( 'User not found.', 'cpm-humanblockchain' ), array( 'status' => 404 ) );
			} elseif ( self::phone_registered_to_other_email( $phone_norm, $email ) ) {
				return new WP_Error(
					'phone_in_use',
					__( 'This phone is already registered to a different email address.', 'cpm-humanblockchain' ),
					array( 'status' => 409 )
				);
			} else {
				$create = self::create_wp_user( $params, $email, $phone_raw );
				if ( is_wp_error( $create ) ) {
					return $create;
				}
				$user_id            = (int) $create['user_id'];
				$user_created       = ! empty( $create['user_created'] );
				$password_generated = ! empty( $create['password_generated'] );
				$plain_password       = isset( $create['password'] ) ? $create['password'] : null;
				$user               = get_userdata( $user_id );
				if ( ! $user ) {
					return new WP_Error(
						'user_create_failed',
						__( 'Account could not be loaded after creation.', 'cpm-humanblockchain' ),
						array( 'status' => 500 )
					);
				}
			}
		}

		if ( ! $user_created && $user_id ) {
			$b = trim( (string) get_user_meta( $user_id, 'billing_phone', true ) );
			$m = trim( (string) get_user_meta( $user_id, 'mega-mobile', true ) );
			if ( $b === '' && $m === '' ) {
				update_user_meta( $user_id, 'billing_phone', $phone_raw );
				update_user_meta( $user_id, 'mega-mobile', $phone_raw );
			}
		}

		if ( ! $do_cancel && ! $user_created && function_exists( 'pmpro_hasMembershipLevel' ) && pmpro_hasMembershipLevel( $level_id, $user_id ) ) {
			$resp = array(
				'success'    => true,
				'message'    => __( 'User already has this membership.', 'cpm-humanblockchain' ),
				'action'     => 'already_member',
				'user_id'    => $user_id,
				'email'      => $email,
				'level_id'   => $level_id,
				'level_name' => $level_obj->name,
			);
			return new WP_REST_Response( $resp, 200 );
		}

		if ( $do_cancel ) {
			if ( ! function_exists( 'pmpro_cancelMembershipLevel' ) ) {
				return new WP_Error( 'pmpro_missing', __( 'Cancel is not available.', 'cpm-humanblockchain' ), array( 'status' => 503 ) );
			}
			$ok = pmpro_cancelMembershipLevel( $level_id, $user_id );
			return new WP_REST_Response(
				array(
					'success'    => (bool) $ok,
					'user_id'    => $user_id,
					'level_id'   => $level_id,
					'level_name' => $level_obj->name,
					'action'     => 'cancelled',
				),
				$ok ? 200 : 500
			);
		}

		global $pmpro_error;
		$pmpro_error = '';
		$result      = pmpro_changeMembershipLevel( $level_id, $user_id );

		if ( false === $result ) {
			$err = $pmpro_error ? $pmpro_error : __( 'Membership could not be updated.', 'cpm-humanblockchain' );
			return new WP_Error( 'membership_failed', $err, array( 'status' => 500 ) );
		}

		$response = array(
			'success'        => true,
			'unchanged'      => ( null === $result ),
			'user_id'        => $user_id,
			'level_id'       => $level_id,
			'level_name'     => $level_obj->name,
			'action'         => 'granted',
			'user_created'   => $user_created,
		);

		if ( $user_created ) {
			$response['password_generated'] = $password_generated;
			if ( $password_generated && null !== $plain_password ) {
				$response['password'] = $plain_password;
			}
		}

		/**
		 * When true (default), create a PMPro order row so **Memberships → Orders** shows this grant.
		 *
		 * @param bool     $create   Whether to create the order.
		 * @param int      $user_id  User ID.
		 * @param int      $level_id Level ID.
		 * @param stdClass $level_obj Level object.
		 */
		if ( true === $result && apply_filters( 'cpm_hb_membership_rest_create_pmpro_order', true, $user_id, $level_id, $level_obj ) ) {
			$order_user = get_userdata( $user_id );
			if ( $order_user instanceof WP_User && class_exists( 'Cpm_Humanblockchain_Membership' ) ) {
				$oid = Cpm_Humanblockchain_Membership::create_pmpro_member_order(
					$user_id,
					$level_obj,
					$order_user,
					array(
						'payment_type'           => __( 'HumanBlockchain REST API', 'cpm-humanblockchain' ),
						'payment_transaction_id' => 'hb-api-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 8, false, false ),
						'notes'                  => __( 'Membership granted via POST /wp-json/myapi/v1/membership.', 'cpm-humanblockchain' ),
					)
				);
				if ( $oid > 0 ) {
					$response['pmpro_order_id'] = $oid;
				}
			}
		}

		self::save_api_response_to_user_meta( $user_id, $response );

		return new WP_REST_Response( $response, 200 );
	}
}
