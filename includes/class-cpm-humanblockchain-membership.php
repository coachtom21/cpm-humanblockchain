<?php
/**
 * Membership selection: local user meta + PMPro and/or WooCommerce checkout (no remote REST).
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membership modal + checkout flow.
 */
class Cpm_Humanblockchain_Membership {

	/**
	 * Option: full URL for POST membership (empty = default REST route on this site).
	 */
	const OPTION_ENDPOINT = 'cpm_hb_membership_api_endpoint';

	/**
	 * Bearer token for REST + outbound sync (preferred over legacy smallstreet_api_key).
	 */
	const OPTION_API_KEY = 'cpm_hb_membership_api_key';

	/**
	 * Resolved POST URL for the membership API.
	 *
	 * @return string
	 */
	public static function get_api_endpoint_url() {
		$custom = trim( (string) get_option( self::OPTION_ENDPOINT, '' ) );
		if ( $custom !== '' && filter_var( $custom, FILTER_VALIDATE_URL ) ) {
			if ( function_exists( 'cpm_hb_should_block_outbound_smallstreet_url' ) && cpm_hb_should_block_outbound_smallstreet_url( $custom ) ) {
				return rest_url( 'myapi/v1/membership' );
			}
			return esc_url_raw( $custom );
		}
		return rest_url( 'myapi/v1/membership' );
	}

	/**
	 * Bearer token: {@see OPTION_API_KEY} first, then legacy smallstreet_api_key.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		$key = trim( (string) get_option( self::OPTION_API_KEY, '' ) );
		if ( $key !== '' ) {
			return $key;
		}
		return trim( (string) get_option( 'smallstreet_api_key', '' ) );
	}

	/**
	 * WC order meta: PMPro orders row id created for this checkout (idempotency).
	 */
	const META_WC_PMPRO_MEMBERORDER_ID = '_cpm_hb_pmpro_memberorder_id';

	/**
	 * Insert a PMPro `pmpro_membership_orders` row (shows under Memberships → Orders).
	 *
	 * @param int      $user_id   WordPress user ID.
	 * @param stdClass $level_obj Object from pmpro_getLevel().
	 * @param WP_User  $user      User for billing name/phone.
	 * @param array    $args {
	 *     Optional. @type string     $payment_type              Shown as payment type.
	 *     @type string     $payment_transaction_id    Unique transaction id.
	 *     @type string     $notes                     Order notes.
	 *     @type float|null $amount_override           If set, subtotal/total/InitialPayment (before tax).
	 *     @type float|null $tax_override              If set with amount_override, tax amount.
	 * }
	 * @return int Order id or 0.
	 */
	public static function create_pmpro_member_order( $user_id, $level_obj, WP_User $user, array $args = array() ) {
		if ( ! class_exists( 'MemberOrder', false ) ) {
			return 0;
		}
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || empty( $level_obj->id ) ) {
			return 0;
		}

		$defaults = array(
			'payment_type'             => '',
			'payment_transaction_id'   => '',
			'notes'                    => '',
			'amount_override'          => null,
			'tax_override'             => null,
		);
		$args = wp_parse_args( $args, $defaults );

		$initial = isset( $level_obj->initial_payment ) ? (float) $level_obj->initial_payment : 0.0;
		if ( null !== $args['amount_override'] && is_numeric( $args['amount_override'] ) ) {
			$initial = (float) $args['amount_override'];
		}
		$tax = 0.0;
		if ( null !== $args['tax_override'] && is_numeric( $args['tax_override'] ) ) {
			$tax = (float) $args['tax_override'];
		}

		$morder = new MemberOrder();
		$morder->user_id          = $user_id;
		$morder->membership_id    = (int) $level_obj->id;
		$morder->membership_level = $level_obj;

		$morder->InitialPayment = $initial;
		$morder->subtotal       = $initial;
		$morder->tax            = $tax;
		$morder->total          = $initial + $tax;

		$morder->status = 'success';

		$gw = get_option( 'pmpro_gateway', '' );
		if ( ! is_string( $gw ) || $gw === '' ) {
			$gw = 'check';
		}
		$morder->gateway               = $gw;
		$morder->gateway_environment  = (string) get_option( 'pmpro_gateway_environment', 'sandbox' );
		$morder->payment_type         = is_string( $args['payment_type'] ) ? $args['payment_type'] : '';
		$morder->payment_transaction_id = is_string( $args['payment_transaction_id'] ) ? $args['payment_transaction_id'] : '';
		$morder->subscription_transaction_id = '';
		$morder->notes                = is_string( $args['notes'] ) ? $args['notes'] : '';

		$name = trim( $user->first_name . ' ' . $user->last_name );
		if ( $name === '' ) {
			$name = $user->display_name ? $user->display_name : $user->user_login;
		}
		$morder->billing = new stdClass();
		$morder->billing->name     = $name;
		$morder->billing->street   = '';
		$morder->billing->city     = '';
		$morder->billing->state    = '';
		$morder->billing->zip      = '';
		$morder->billing->country  = '';
		$phone_meta = get_user_meta( $user_id, 'billing_phone', true );
		if ( ! is_string( $phone_meta ) || $phone_meta === '' ) {
			$phone_meta = get_user_meta( $user_id, 'mega-mobile', true );
		}
		$morder->billing->phone = is_string( $phone_meta ) ? $phone_meta : '';

		$saved = $morder->saveOrder();
		if ( false === $saved ) {
			return 0;
		}
		return (int) $morder->id;
	}

	/**
	 * When a WooCommerce order from Get started has `_cpm_hb_membership_tier`, grant PMPro level
	 * ({@see pmpro_changeMembershipLevel()} → `pmpro_memberships_users`) and add a PMPro order row
	 * (`pmpro_membership_orders`). MemberOrder alone does not activate membership.
	 *
	 * @param int $order_id WooCommerce order id.
	 */
	public static function wc_maybe_create_pmpro_order_for_hb_membership( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'MemberOrder', false ) || ! function_exists( 'pmpro_getLevel' ) ) {
			return;
		}
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( (int) $order->get_meta( self::META_WC_PMPRO_MEMBERORDER_ID ) > 0 ) {
			return;
		}
		if ( ! apply_filters( 'cpm_hb_sync_pmpro_order_from_wc_membership_order', true, $order ) ) {
			return;
		}

		$statuses = apply_filters(
			'cpm_hb_wc_order_statuses_for_pmpro_member_order_sync',
			array( 'processing', 'completed', 'on-hold' )
		);
		if ( ! is_array( $statuses ) || ! $order->has_status( $statuses ) ) {
			return;
		}

		$tier = $order->get_meta( '_cpm_hb_membership_tier' );
		$tier = is_string( $tier ) ? sanitize_key( $tier ) : '';
		if ( $tier === '' || ! in_array( $tier, self::valid_tier_slugs(), true ) ) {
			return;
		}

		$uid = (int) $order->get_customer_id();
		if ( $uid <= 0 ) {
			$email = $order->get_billing_email();
			if ( is_email( $email ) ) {
				$u = get_user_by( 'email', $email );
				if ( $u ) {
					$uid = (int) $u->ID;
				}
			}
		}
		if ( $uid <= 0 ) {
			return;
		}

		$level_id = self::get_pmpro_level_id_for_tier( $tier );
		if ( $level_id <= 0 ) {
			return;
		}
		$level_obj = pmpro_getLevel( $level_id );
		if ( empty( $level_obj ) ) {
			return;
		}

		$user = get_userdata( $uid );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$branch = $order->get_meta( '_cpm_hb_membership_branch' );
		$branch = is_string( $branch ) ? sanitize_key( $branch ) : '';
		$notes  = sprintf(
			/* translators: 1: WooCommerce order number, 2: tier slug, 3: branch slug or dash */
			__( 'HumanBlockchain Get started → WooCommerce order #%1$s. Tier: %2$s. Branch: %3$s.', 'cpm-humanblockchain' ),
			$order->get_order_number(),
			$tier,
			$branch !== '' ? $branch : '—'
		);

		$subtotal = (float) $order->get_subtotal();
		$tax      = (float) $order->get_total_tax();
		$total    = (float) $order->get_total();
		$use_wc   = ( $total > 0 || $subtotal > 0 || $tax > 0 );
		$args     = array(
			'payment_type'           => __( 'WooCommerce (Get started)', 'cpm-humanblockchain' ),
			'payment_transaction_id' => 'wc-' . $order_id . '-' . substr( md5( (string) $order->get_order_key() ), 0, 10 ),
			'notes'                  => $notes,
		);
		if ( $use_wc ) {
			$args['amount_override'] = $subtotal;
			$args['tax_override']    = $tax;
			if ( $args['amount_override'] + $args['tax_override'] <= 0 && $total > 0 ) {
				$args['amount_override'] = $total;
				$args['tax_override']    = 0.0;
			}
		}

		if ( apply_filters( 'cpm_hb_wc_grant_pmpro_level_from_membership_order', true, $order, $uid, $level_id, $level_obj ) && function_exists( 'pmpro_changeMembershipLevel' ) ) {
			global $pmpro_error;
			$pmpro_error = '';
			$granted     = pmpro_changeMembershipLevel( $level_id, $uid );
			if ( false === $granted ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						'cpm_hb wc_maybe_create_pmpro_order_for_hb_membership: pmpro_changeMembershipLevel failed for user ' . $uid . ' level ' . $level_id . ' — ' . ( $pmpro_error ? $pmpro_error : 'unknown' )
					);
				}
				return;
			}
		}

		$oid = self::create_pmpro_member_order( $uid, $level_obj, $user, $args );
		if ( $oid > 0 ) {
			$order->update_meta_data( self::META_WC_PMPRO_MEMBERORDER_ID, $oid );
			$order->save();
		}
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'cpm_hb_pmpro_level_id_for_tier', array( __CLASS__, 'filter_default_pmpro_level_id_for_tier' ), 99, 2 );

		add_action( 'wp_ajax_cpm_hb_membership_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_cpm_hb_membership_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'template_redirect', array( __CLASS__, 'checkout_capture_tier_from_query' ), 5 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'order_save_membership_tier' ), 10, 1 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'checkout_tier_notice' ), 5 );
		add_action( 'pmpro_checkout_before_form', array( __CLASS__, 'checkout_tier_notice' ), 5 );

		add_filter( 'pmpro_registration_checks', array( __CLASS__, 'pmpro_registration_checks_active_membership' ), 99 );

		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'wc_maybe_create_pmpro_order_for_hb_membership' ), 50, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'wc_maybe_create_pmpro_order_for_hb_membership' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'wc_maybe_create_pmpro_order_for_hb_membership' ), 20, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'wc_maybe_create_pmpro_order_for_hb_membership' ), 20, 1 );
	}

	/**
	 * Valid tier slugs from the Get started / membership modal.
	 *
	 * @return string[]
	 */
	public static function valid_tier_slugs() {
		return array( 'yamer', 'megavoter', 'patron' );
	}

	/**
	 * Map a PMPro level ID back to a HumanBlockchain tier slug (yamer|megavoter|patron), if known.
	 *
	 * @param int $level_id PMPro level id.
	 * @return string|null Tier slug or null if not mapped.
	 */
	public static function tier_slug_for_pmpro_level_id( $level_id ) {
		$level_id = (int) $level_id;
		if ( $level_id <= 0 ) {
			return null;
		}
		foreach ( self::valid_tier_slugs() as $tier ) {
			if ( self::get_pmpro_level_id_for_tier( $tier ) === $level_id ) {
				return $tier;
			}
		}
		return null;
	}

	/**
	 * Whether an active PMPro level should be treated like YAMer (free) for checkout rules.
	 *
	 * @param int $active_level_id Active membership level id.
	 * @return bool
	 */
	private static function active_pmpro_level_is_yamer_or_unknown_free( $active_level_id ) {
		$active_level_id = (int) $active_level_id;
		$tier            = self::tier_slug_for_pmpro_level_id( $active_level_id );
		if ( 'yamer' === $tier ) {
			return true;
		}
		if ( null !== $tier ) {
			return false;
		}
		if ( function_exists( 'pmpro_getLevel' ) && function_exists( 'pmpro_isLevelFree' ) ) {
			$L = pmpro_getLevel( $active_level_id );
			if ( $L && pmpro_isLevelFree( $L ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Block PMPro paid checkout when the member already has a different paid plan (same group).
	 * YAMer (free) may upgrade to Pioneer or Patron; same-level checkout is allowed (renewal).
	 *
	 * @param int      $user_id      WordPress user ID.
	 * @param stdClass $target_level Level object at checkout.
	 * @return bool True = block checkout.
	 */
	public static function pmpro_should_block_checkout_due_to_active_membership( $user_id, $target_level ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || empty( $target_level->id ) || ! function_exists( 'pmpro_getMembershipLevelsForUser' ) ) {
			return false;
		}
		if ( function_exists( 'pmpro_isLevelFree' ) && pmpro_isLevelFree( $target_level ) ) {
			return false;
		}
		$target_id = (int) $target_level->id;
		$actives   = pmpro_getMembershipLevelsForUser( $user_id );
		if ( empty( $actives ) || ! is_array( $actives ) ) {
			return false;
		}
		foreach ( $actives as $row ) {
			if ( (int) $row->id === $target_id ) {
				return false;
			}
		}
		foreach ( $actives as $row ) {
			$aid = (int) $row->id;
			if ( self::active_pmpro_level_is_yamer_or_unknown_free( $aid ) ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * PMPro: prevent duplicate paid subscriptions while a non–YAMer plan is active.
	 *
	 * @param bool $okay Continue checkout.
	 * @return bool
	 */
	public static function pmpro_registration_checks_active_membership( $okay ) {
		if ( ! $okay || ! function_exists( 'pmpro_setMessage' ) ) {
			return $okay;
		}
		global $current_user, $pmpro_level;
		if ( empty( $current_user->ID ) || empty( $pmpro_level ) || empty( $pmpro_level->id ) ) {
			return $okay;
		}
		$block = self::pmpro_should_block_checkout_due_to_active_membership( (int) $current_user->ID, $pmpro_level );
		$block = (bool) apply_filters( 'cpm_hb_pmpro_block_checkout_due_to_active_membership', $block, (int) $current_user->ID, $pmpro_level );
		if ( $block ) {
			pmpro_setMessage(
				__( 'You already have an active paid membership. Cancel it or wait until it expires before subscribing to a different plan. YAMer (free) members can upgrade to Pioneer or Patron at any time.', 'cpm-humanblockchain' ),
				'pmpro_error'
			);
			return false;
		}
		return $okay;
	}

	/**
	 * Organisational “branch” labels (Budget, Media, etc.) for the Get started modal dropdown.
	 * Filter: `cpm_hb_membership_branch_options` — keep keys as stable slugs.
	 *
	 * @return array<string,string> branch slug => label
	 */
	public static function get_branch_options() {
		$options = array(
			'budget'       => __( 'Budget', 'cpm-humanblockchain' ),
			'distribution' => __( 'Distribution', 'cpm-humanblockchain' ),
			'media'        => __( 'Media', 'cpm-humanblockchain' ),
			'membership'   => __( 'Membership', 'cpm-humanblockchain' ),
			'planning'     => __( 'Planning', 'cpm-humanblockchain' ),
		);
		$options = apply_filters( 'cpm_hb_membership_branch_options', $options );
		return is_array( $options ) ? $options : array();
	}

	/**
	 * @return string[]
	 */
	public static function valid_branch_slugs() {
		return array_keys( self::get_branch_options() );
	}

	/**
	 * Persist selection in `_membership_level` (local only; no HTTP).
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $tier       yamer|megavoter|patron.
	 * @param string $level_name PMPro-style name, e.g. YAMer.
	 * @param string $branch     Organisational branch slug (see {@see get_branch_options()}).
	 */
	public static function save_local_membership_to_user( $user_id, $tier, $level_name, $branch = '' ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$branch = is_string( $branch ) ? sanitize_key( $branch ) : '';
		$opts   = self::get_branch_options();
		$branch_label = ( $branch !== '' && isset( $opts[ $branch ] ) ) ? (string) $opts[ $branch ] : '';
		$payload = array(
			'success'    => true,
			'tier'       => $tier,
			'level_name' => (string) $level_name,
			'branch'     => $branch,
			'branch_label' => $branch_label,
			'source'     => 'local',
			'saved_at'   => current_time( 'mysql' ),
		);
		$payload = apply_filters( 'cpm_hb_local_membership_payload', $payload, $user_id, $tier, $branch );
		if ( is_array( $payload ) && ! empty( $payload ) ) {
			update_user_meta( $user_id, '_membership_level', wp_json_encode( $payload ) );
		}
	}

	/**
	 * If PMPro or Woo checkout is opened with ?cpm_hb_tier= — store in WC session when available.
	 */
	public static function checkout_capture_tier_from_query() {
		$on_pmpro = function_exists( 'pmpro_is_checkout' ) && pmpro_is_checkout();
		$on_woo   = function_exists( 'is_checkout' ) && is_checkout() && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() );
		if ( ! $on_pmpro && ! $on_woo ) {
			return;
		}
		if ( empty( $_GET['cpm_hb_tier'] ) || ! is_string( $_GET['cpm_hb_tier'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$tier = sanitize_key( wp_unslash( $_GET['cpm_hb_tier'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tier, self::valid_tier_slugs(), true ) ) {
			return;
		}
		if ( function_exists( 'WC' ) && WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( 'cpm_hb_tier', $tier );
		}
		if ( ! empty( $_GET['cpm_hb_branch'] ) && is_string( $_GET['cpm_hb_branch'] ) ) { // phpcs:ignore WordPress.Security
			$b = sanitize_key( wp_unslash( $_GET['cpm_hb_branch'] ) );
			if ( in_array( $b, self::valid_branch_slugs(), true ) && function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'cpm_hb_branch', $b );
			}
		}
	}

	/**
	 * Copy tier to order from session or from logged-in user meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function order_save_membership_tier( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return;
		}
		$tier = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$t = WC()->session->get( 'cpm_hb_tier' );
			if ( is_string( $t ) && $t !== '' ) {
				$tier = sanitize_key( $t );
			}
		}
		if ( $tier === '' && is_user_logged_in() ) {
			$raw = get_user_meta( get_current_user_id(), '_membership_level', true );
			$dec = is_string( $raw ) ? json_decode( $raw, true ) : null;
			if ( is_array( $dec ) && ! empty( $dec['tier'] ) ) {
				$tier = sanitize_key( (string) $dec['tier'] );
			}
		}
		if ( $tier !== '' && in_array( $tier, self::valid_tier_slugs(), true ) ) {
			update_post_meta( $order_id, '_cpm_hb_membership_tier', $tier );
		}
		$ob = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$br = WC()->session->get( 'cpm_hb_branch' );
			if ( is_string( $br ) && $br !== '' ) {
				$ob = sanitize_key( $br );
			}
		}
		if ( $ob === '' && is_user_logged_in() ) {
			$rawb = get_user_meta( get_current_user_id(), '_membership_level', true );
			$decb = is_string( $rawb ) ? json_decode( $rawb, true ) : null;
			if ( is_array( $decb ) && ! empty( $decb['branch'] ) ) {
				$ob = sanitize_key( (string) $decb['branch'] );
			}
		}
		if ( $ob !== '' && in_array( $ob, self::valid_branch_slugs(), true ) ) {
			update_post_meta( $order_id, '_cpm_hb_membership_branch', $ob );
		}
	}

	/**
	 * Show selected branch on the checkout form.
	 */
	public static function checkout_tier_notice() {
		$tier = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$t = WC()->session->get( 'cpm_hb_tier' );
			if ( is_string( $t ) && $t !== '' ) {
				$tier = sanitize_key( $t );
			}
		}
		if ( $tier === '' && ! empty( $_GET['cpm_hb_tier'] ) && is_string( $_GET['cpm_hb_tier'] ) ) { // phpcs:ignore WordPress.Security
			$tier = sanitize_key( wp_unslash( $_GET['cpm_hb_tier'] ) );
		}
		$ob = '';
		if ( function_exists( 'WC' ) && WC()->session ) {
			$b = WC()->session->get( 'cpm_hb_branch' );
			if ( is_string( $b ) && $b !== '' ) {
				$ob = sanitize_key( $b );
			}
		}
		if ( $ob === '' && ! empty( $_GET['cpm_hb_branch'] ) && is_string( $_GET['cpm_hb_branch'] ) ) { // phpcs:ignore WordPress.Security
			$ob = sanitize_key( wp_unslash( $_GET['cpm_hb_branch'] ) );
		}
		if ( $tier === '' || ! in_array( $tier, self::valid_tier_slugs(), true ) ) {
			return;
		}
		$level  = self::get_level_fields_for_tier( $tier );
		$label  = (string) $level['level_name'];
		$format = apply_filters( 'cpm_hb_checkout_tier_notice_format', 'column', $tier );
		// translators: %s: membership level label (e.g. YAMer, Patron).
		$text = sprintf( __( 'Your selected membership: %s', 'cpm-humanblockchain' ), $label );
		$opts   = self::get_branch_options();
		if ( $ob !== '' && in_array( $ob, self::valid_branch_slugs(), true ) && isset( $opts[ $ob ] ) ) {
			$text .= ' · ' . sprintf(
				/* translators: %s: branch name, e.g. Budget */
				__( 'Branch: %s', 'cpm-humanblockchain' ),
				$opts[ $ob ]
			);
		}
		$p_class = ( function_exists( 'pmpro_is_checkout' ) && pmpro_is_checkout() ) ? 'pmpro_message pmpro_alert' : 'woocommerce-info';
		if ( 'column' === $format ) {
			echo '<div class="cpm-hb-checkout-tier-notice cpm-hb-tier-' . esc_attr( $tier ) . ( $ob ? ' cpm-hb-br-' . esc_attr( $ob ) : '' ) . '"><p class="' . esc_attr( $p_class ) . '" style="margin-bottom:1.25em;">' . esc_html( $text ) . '</p></div>';
		} else {
			echo '<p class="' . esc_attr( $p_class ) . ' cpm-hb-checkout-tier-notice" style="margin-bottom:1.25em;">' . esc_html( $text ) . '</p>';
		}
	}

	/**
	 * Map UI tier (yamer|megavoter|patron) to a Paid Memberships Pro level ID.
	 *
	 * In your theme or a small plugin, connect PMPro “Memberships → Membership Levels” IDs, e.g.:
	 * add_filter( 'cpm_hb_pmpro_level_id_for_tier', function( $id, $tier ) {
	 *   $map = array( 'yamer' => 1, 'megavoter' => 2, 'patron' => 3 );
	 *   return isset( $map[ $tier ] ) ? (int) $map[ $tier ] : $id;
	 * }, 10, 2 );
	 *
	 * @param string $tier Tier slug.
	 * @return int Level ID, or 0 to skip PMPro pre-selected checkout and use Woo/fallback.
	 */
	public static function get_pmpro_level_id_for_tier( $tier ) {
		$tier = sanitize_key( (string) $tier );
		$id   = 0;
		$id   = (int) apply_filters( 'cpm_hb_pmpro_level_id_for_tier', $id, $tier );
		if ( $id < 0 ) {
			$id = 0;
		}
		return $id;
	}

	/**
	 * Last-chance tier → PMPro level ID when no theme or other code set `cpm_hb_pmpro_level_id_for_tier`.
	 * Without this, Get started falls through to WooCommerce checkout with no `cpm_hb_membership_product_id`,
	 * which yields an empty cart and no PMPro member/order rows.
	 *
	 * @param int    $id   Level ID from earlier filters (0 if unset).
	 * @param string $tier yamer|megavoter|patron.
	 * @return int
	 */
	public static function filter_default_pmpro_level_id_for_tier( $id, $tier ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			return $id;
		}
		$tier = sanitize_key( (string) $tier );
		$defaults = array(
			'yamer'     => 1,
			'megavoter' => 2,
			'patron'    => 3,
		);
		$labels = array(
			'yamer'     => 'YAMer',
			'megavoter' => 'Pioneer',
			'patron'    => 'Patron',
		);
		if ( ! isset( $defaults[ $tier ] ) ) {
			return 0;
		}
		if ( function_exists( 'pmpro_getAllLevels' ) ) {
			$by_lower = array();
			foreach ( pmpro_getAllLevels( true ) as $lvl ) {
				$n = strtolower( trim( (string) $lvl->name ) );
				if ( $n !== '' ) {
					$by_lower[ $n ] = (int) $lvl->id;
				}
			}
			$want = strtolower( $labels[ $tier ] );
			if ( isset( $by_lower[ $want ] ) ) {
				return (int) $by_lower[ $want ];
			}
			if ( 'megavoter' === $tier && isset( $by_lower['megavoter'] ) ) {
				return (int) $by_lower['megavoter'];
			}
		}
		return (int) $defaults[ $tier ];
	}

	/**
	 * Add configured Woo product to cart, or send to PMPro “Membership Checkout”, or pass tier on the URL.
	 * Filter `cpm_hb_pmpro_level_id_for_tier` to map each UI tier to a PMPro level ID.
	 * Filter `cpm_hb_membership_product_id` to map yamer|megavoter|patron → product ID.
	 *
	 * @param string      $tier   Tier slug.
	 * @param string|null $email  Optional guest email (for cookies/filters, not required).
	 * @param string      $branch Organisational branch slug; appended as `cpm_hb_branch` when set.
	 * @return string Checkout URL.
	 */
	private static function get_membership_checkout_url( $tier, $email = null, $branch = '' ) {
		$tier   = sanitize_key( (string) $tier );
		$email  = is_string( $email ) ? $email : null;
		$branch = is_string( $branch ) ? sanitize_key( $branch ) : '';
		$b_ok   = $branch !== '' && in_array( $branch, self::valid_branch_slugs(), true );
		$add_q  = function ( $url ) use ( $tier, $branch, $b_ok ) {
			$url = add_query_arg( 'cpm_hb_tier', rawurlencode( $tier ), $url );
			if ( $b_ok ) {
				$url = add_query_arg( 'cpm_hb_branch', rawurlencode( $branch ), $url );
			}
			return $url;
		};

		// Paid Memberships Pro: same “Membership Checkout” page as in Memberships → Settings → Page Settings.
		$pmpro_id = self::get_pmpro_level_id_for_tier( $tier );
		if ( $pmpro_id > 0 && function_exists( 'pmpro_url' ) && function_exists( 'pmpro_getLevel' ) ) {
			$pl = pmpro_getLevel( $pmpro_id );
			if ( $pl && ! empty( $pl->id ) ) {
				$url = pmpro_url( 'checkout', 'level=' . (int) $pl->id );
				$url = (string) apply_filters( 'cpm_hb_membership_pmpro_checkout_url', $add_q( $url ), $tier, $pmpro_id, $pl, $branch );
				return $url;
			}
		}

		$base = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		if ( function_exists( 'pmpro_url' ) && ! function_exists( 'wc_get_checkout_url' ) ) {
			$pmpro_checkout = pmpro_url( 'checkout' );
			if ( is_string( $pmpro_checkout ) && $pmpro_checkout !== '' ) {
				$base = $pmpro_checkout;
			}
		}
		$base = (string) apply_filters( 'cpm_hb_membership_checkout_base_url', $base, $tier );

		try {
			if ( function_exists( 'WC' ) ) {
				$wc = WC();
				if ( $wc && $wc->session && ! $wc->session->has_session() ) {
					$wc->session->set_customer_session_cookie( true );
				}
			}
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart();
			}
			if ( function_exists( 'WC' ) ) {
				$wc = WC();
				if ( $wc && $wc->session ) {
					$wc->session->set( 'cpm_hb_tier', $tier );
					if ( $b_ok ) {
						$wc->session->set( 'cpm_hb_branch', $branch );
					}
				}
			}
			if ( $email && is_email( $email ) && function_exists( 'wc_setcookie' ) ) {
				wc_setcookie( 'cpm_hb_membership_email', $email, time() + 3600 );
			}
			$product_id = (int) apply_filters( 'cpm_hb_membership_product_id', 0, $tier, $email );
			if ( $product_id > 0 && function_exists( 'WC' ) && WC()->cart ) {
				$empty_first = (bool) apply_filters( 'cpm_hb_membership_empty_cart_before_add', false, $tier );
				if ( $empty_first ) {
					WC()->cart->empty_cart();
				}
				$added = WC()->cart->add_to_cart(
					$product_id,
					1,
					0,
					array(),
					array(
						'cpm_hb_tier' => $tier,
					)
				);
				if ( ! $added && function_exists( 'wc_get_checkout_url' ) ) {
					return $add_q( wc_get_checkout_url() );
				}
				if ( $added && function_exists( 'wc_get_checkout_url' ) ) {
					return $add_q( wc_get_checkout_url() );
				}
			}
			if ( function_exists( 'wc_get_checkout_url' ) ) {
				return $add_q( wc_get_checkout_url() );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'cpm_hb get_membership_checkout_url: ' . $e->getMessage() );
			}
		}

		$fallback = apply_filters( 'cpm_hb_membership_checkout_url_fallback', $base, $tier, $branch );
		return $add_q( $fallback );
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
		$url = self::get_api_endpoint_url();
		$endpoint_host = wp_parse_url( $url, PHP_URL_HOST );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $endpoint_host || ! $site_host ) {
			return false;
		}
		return strtolower( (string) $endpoint_host ) === strtolower( (string) $site_host );
	}

	/**
	 * AJAX: submit membership selection (local save + optional Woo cart; no remote REST).
	 */
	public static function handle_submit() {
		check_ajax_referer( 'cpm_hb_membership', 'nonce' );

		if ( ! is_user_logged_in() ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$key = 'cpm_hb_mem_submit_' . md5( $ip );
			$n   = (int) get_transient( $key );
			if ( $n >= 45 ) {
				wp_send_json_error(
					array(
						'code'    => 'rate_limited',
						'message' => __( 'Too many attempts. Please try again in a few minutes.', 'cpm-humanblockchain' ),
					),
					429
				);
			}
			set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
		}

		$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : '';
		if ( ! in_array( $tier, self::valid_tier_slugs(), true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_tier',
					'message' => __( 'Invalid membership selection.', 'cpm-humanblockchain' ),
				),
				400
			);
		}

		$level = self::get_level_fields_for_tier( $tier );
		if ( $level['level_name'] === '' ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_tier',
					'message' => __( 'Invalid membership selection.', 'cpm-humanblockchain' ),
				),
				400
			);
		}

		$branch = '';
		if ( ! empty( $_POST['branch'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$branch = sanitize_key( wp_unslash( $_POST['branch'] ) );
		} elseif ( ! empty( $_POST['cpm_hb_branch'] ) ) {
			$branch = sanitize_key( wp_unslash( $_POST['cpm_hb_branch'] ) );
		}
		if ( $branch === '' || ! in_array( $branch, self::valid_branch_slugs(), true ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_branch',
					'message' => __( 'Please select a branch (e.g. Budget, Media, Planning).', 'cpm-humanblockchain' ),
				),
				400
			);
		}

		$phone_from_post = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		if ( $phone_from_post === '' && isset( $_POST['mobile'] ) ) {
			$phone_from_post = sanitize_text_field( wp_unslash( $_POST['mobile'] ) );
		}

		if ( is_user_logged_in() ) {
			$uid  = get_current_user_id();
			$user = wp_get_current_user();
			/**
			 * When true, user must provide phone (or have one on file) before redirect — opens “Your details” modal.
			 * Default false: go straight to checkout (PMPro / Woo can collect contact).
			 */
			$need_phone = (bool) apply_filters( 'cpm_hb_membership_require_phone_for_logged_in', false, $uid, $tier );

			$phone = $phone_from_post;
			if ( $phone === '' && class_exists( 'Cpm_Humanblockchain_Device_Registry' ) ) {
				$phone = Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $uid );
			}

			if ( $need_phone && ( $phone === '' || ! self::phone_has_enough_digits( $phone ) ) ) {
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

			self::save_local_membership_to_user( $uid, $tier, $level['level_name'], $branch );
			$redirect_url = self::get_membership_checkout_url( $tier, null, $branch );
			$out          = array(
				'success'        => true,
				'message'        => __( 'Membership saved. Continue to checkout.', 'cpm-humanblockchain' ),
				'redirect_url'   => $redirect_url,
				'tier'           => $tier,
				'branch'         => $branch,
				'level_name'     => $level['level_name'],
				'email'          => $user->user_email,
			);
			$out = apply_filters( 'cpm_hb_membership_submit_response', $out, 'logged_in', $uid );
			wp_send_json_success( $out );
		}

		/**
		 * When true, guests skip the pre-checkout email/phone modal and go straight to checkout
		 * (PMPro / Woo collect details on the checkout page). Default: true.
		 */
		$skip_guest_contact = (bool) apply_filters( 'cpm_hb_skip_guest_contact_before_checkout', true );
		if ( $skip_guest_contact ) {
			$redirect_url = self::get_membership_checkout_url( $tier, null, $branch );
			$out          = array(
				'success'      => true,
				'message'      => __( 'Continue to checkout to complete your membership.', 'cpm-humanblockchain' ),
				'redirect_url' => $redirect_url,
				'tier'         => $tier,
				'branch'       => $branch,
				'level_name'   => $level['level_name'],
				'email'        => '',
			);
			$out = apply_filters( 'cpm_hb_membership_submit_response', $out, 'guest', 0 );
			wp_send_json_success( $out );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : $phone_from_post;
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

		$redirect_url = self::get_membership_checkout_url( $tier, $email, $branch );
		$out          = array(
			'success'        => true,
			'message'        => __( 'Continue to checkout to complete your membership.', 'cpm-humanblockchain' ),
			'redirect_url'   => $redirect_url,
			'tier'           => $tier,
			'branch'         => $branch,
			'level_name'     => $level['level_name'],
			'email'          => $email,
		);
		$out = apply_filters( 'cpm_hb_membership_submit_response', $out, 'guest', 0 );
		wp_send_json_success( $out );
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

		$cached  = get_user_meta( $user_id, '_membership_level', true );
		$decoded = is_string( $cached ) ? json_decode( $cached, true ) : null;
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$level_id   = isset( $decoded['level_id'] ) ? (int) $decoded['level_id'] : 0;
		$level_name = isset( $decoded['level_name'] ) ? sanitize_text_field( (string) $decoded['level_name'] ) : '';
		$tier_key   = isset( $decoded['tier'] ) ? sanitize_key( (string) $decoded['tier'] ) : '';
		if ( $level_name === '' && $tier_key !== '' && in_array( $tier_key, self::valid_tier_slugs(), true ) ) {
			$level_name = (string) self::get_level_fields_for_tier( $tier_key )['level_name'];
		}

		if ( $level_id <= 0 && $level_name === '' ) {
			return array(
				'ok'      => false,
				'skipped' => true,
				'message' => __( 'No saved membership to sync yet. Choose a membership level first.', 'cpm-humanblockchain' ),
			);
		}

		if ( $api_key === '' ) {
			return array(
				'ok'      => true,
				'skipped' => true,
				'message' => __( 'Using membership saved on this site (no remote API).', 'cpm-humanblockchain' ),
				'data'    => $decoded,
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
