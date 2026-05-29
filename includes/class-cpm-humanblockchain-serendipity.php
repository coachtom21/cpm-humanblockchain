<?php
/**
 * Serendipity Protocol — Peace Pentagon branch + Buyer/Seller POC assignment.
 *
 * Runs at onboarding (after membership is saved). Intentionally separate from
 * {@see Cpm_Humanblockchain_Two_Scan_Validator} (proof-of-delivery scans).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assigns organisational branch and POC cluster IDs on wp_nwp_devices.
 */
class Cpm_Humanblockchain_Serendipity {

	/**
	 * @var string[]
	 */
	private static $branch_slugs = array(
		'planning',
		'budget',
		'media',
		'distribution',
		'membership',
	);

	/**
	 * Seller tiers that receive a global Seller POC cluster.
	 *
	 * @var string[]
	 */
	private static $seller_tiers = array( 'megavoter', 'patron' );

	/**
	 * Register hooks (membership + device registration only — not PoD / 2-scan).
	 */
	public static function init() {
		add_action( 'cpm_hb_after_membership_saved', array( __CLASS__, 'on_membership_saved' ), 20, 4 );
		add_action( 'cpm_hb_after_device_registered', array( __CLASS__, 'on_device_registered' ), 20, 3 );
		add_action( 'cpm_hb_wc_membership_order_synced', array( __CLASS__, 'on_wc_membership_synced' ), 20, 4 );
	}

	/**
	 * @return string[]
	 */
	public static function branch_slugs() {
		$slugs = apply_filters( 'cpm_hb_serendipity_branch_slugs', self::$branch_slugs );
		return is_array( $slugs ) ? array_values( $slugs ) : self::$branch_slugs;
	}

	/**
	 * @param int    $user_id WordPress user ID.
	 * @param string $tier    yamer|megavoter|patron.
	 * @param string $branch  User-selected branch slug (may be empty).
	 * @param string $level_name Unused; hook arity only.
	 */
	public static function on_membership_saved( $user_id, $tier, $branch, $level_name = '' ) {
		unset( $level_name );
		self::maybe_assign_for_user( (int) $user_id, (string) $tier, (string) $branch );
	}

	/**
	 * @param int    $device_id Device row ID.
	 * @param int    $user_id   WordPress user ID.
	 * @param string $email     Email.
	 */
	public static function on_device_registered( $device_id, $user_id, $email ) {
		unset( $device_id, $email );
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return;
		}
		$mem = self::membership_from_user_meta( $user_id );
		if ( empty( $mem['tier'] ) ) {
			return;
		}
		self::maybe_assign_for_user( $user_id, (string) $mem['tier'], (string) ( $mem['branch'] ?? '' ) );
	}

	/**
	 * @param int    $user_id  WordPress user ID.
	 * @param string $tier     Tier slug.
	 * @param string $branch   Branch slug from order.
	 * @param int    $order_id WooCommerce order ID.
	 */
	public static function on_wc_membership_synced( $user_id, $tier, $branch, $order_id ) {
		unset( $order_id );
		self::maybe_assign_for_user( (int) $user_id, (string) $tier, (string) $branch );
	}

	/**
	 * Assign Serendipity outputs when a device row exists for the user.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $tier    yamer|megavoter|patron.
	 * @param string $branch_preference User branch from membership modal (optional).
	 * @return array<string,mixed>|WP_Error|null Assignment payload or null when skipped.
	 */
	public static function maybe_assign_for_user( $user_id, $tier, $branch_preference = '' ) {
		$user_id = (int) $user_id;
		$tier    = sanitize_key( (string) $tier );
		if ( $user_id <= 0 || $tier === '' ) {
			return null;
		}
		if ( ! apply_filters( 'cpm_hb_serendipity_assign_enabled', true, $user_id, $tier, $branch_preference ) ) {
			return null;
		}
		if ( class_exists( 'Cpm_Humanblockchain_Membership' )
			&& ! in_array( $tier, Cpm_Humanblockchain_Membership::valid_tier_slugs(), true ) ) {
			return null;
		}

		$device = self::get_primary_device_for_user( $user_id );
		if ( ! $device ) {
			return null;
		}

		return self::assign_for_device_row( $device, $tier, $branch_preference );
	}

	/**
	 * @param int $user_id WordPress user ID.
	 * @return object|null
	 */
	public static function get_primary_device_for_user( $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		$table = $wpdb->prefix . Cpm_Humanblockchain_Device_Registry::TABLE_NAME;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY registered_at DESC, id DESC LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * @param object|array<string,mixed> $device Row from wp_nwp_devices.
	 * @param string                     $tier   Membership tier.
	 * @param string                     $branch_preference User branch selection.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function assign_for_device_row( $device, $tier, $branch_preference = '' ) {
		global $wpdb;

		if ( is_array( $device ) ) {
			$device = (object) $device;
		}
		if ( empty( $device->id ) ) {
			return new WP_Error( 'no_device', __( 'Device record not found for Serendipity assignment.', 'cpm-humanblockchain' ) );
		}

		$device_id   = (int) $device->id;
		$tier        = sanitize_key( (string) $tier );
		$table       = $wpdb->prefix . Cpm_Humanblockchain_Device_Registry::TABLE_NAME;
		$device_hash = isset( $device->device_hash ) ? (string) $device->device_hash : '';
		$timestamp   = ! empty( $device->registered_at ) ? (string) $device->registered_at : current_time( 'mysql' );

		$existing_branch     = ! empty( $device->peace_pentagon_branch ) ? sanitize_key( (string) $device->peace_pentagon_branch ) : '';
		$existing_buyer_poc  = ! empty( $device->buyer_poc_id ) ? (string) $device->buyer_poc_id : '';
		$existing_seller_poc = ! empty( $device->seller_poc_id ) ? (string) $device->seller_poc_id : '';

		$branch_pref = is_string( $branch_preference ) ? sanitize_key( $branch_preference ) : '';
		if ( $branch_pref !== '' && ! self::is_valid_branch( $branch_pref ) ) {
			$branch_pref = '';
		}

		$branch_source = 'serendipity';
		if ( $branch_pref !== '' ) {
			$branch        = $branch_pref;
			$branch_source = 'user';
		} elseif ( $existing_branch !== '' && self::is_valid_branch( $existing_branch ) ) {
			$branch        = $existing_branch;
			$branch_source = ! empty( $device->branch_source ) ? sanitize_key( (string) $device->branch_source ) : 'serendipity';
		} else {
			$branch = self::assign_branch_from_hash( $device_hash );
		}

		$branch = apply_filters( 'cpm_hb_serendipity_branch', $branch, $device_id, $tier, $branch_pref, $device );

		$lat = isset( $device->geo_lat ) && $device->geo_lat !== null && $device->geo_lat !== '' ? (float) $device->geo_lat : null;
		$lng = isset( $device->geo_lng ) && $device->geo_lng !== null && $device->geo_lng !== '' ? (float) $device->geo_lng : null;

		$buyer_poc_id = $existing_buyer_poc;
		if ( $buyer_poc_id === '' ) {
			$buyer_poc_id = self::assign_buyer_poc_id( $lat, $lng, $timestamp, $device_hash );
			$buyer_poc_id = (string) apply_filters( 'cpm_hb_serendipity_buyer_poc_id', $buyer_poc_id, $device_id, $tier, $device );
		}

		$seller_poc_id = $existing_seller_poc;
		$needs_seller  = in_array( $tier, self::$seller_tiers, true );
		if ( $needs_seller && $seller_poc_id === '' ) {
			$seller_poc_id = self::assign_seller_poc_id( $device_hash, $timestamp );
			$seller_poc_id = (string) apply_filters( 'cpm_hb_serendipity_seller_poc_id', $seller_poc_id, $device_id, $tier, $branch, $device );
		}

		$poc_status = self::resolve_poc_status( $buyer_poc_id, $needs_seller, $seller_poc_id !== '' );
		$assigned_at = current_time( 'mysql' );

		$update = array(
			'peace_pentagon_branch' => $branch,
			'branch_source'         => $branch_source,
			'branch_preference'     => $branch_pref !== '' ? $branch_pref : null,
			'buyer_poc_id'          => $buyer_poc_id,
			'seller_poc_id'         => $needs_seller ? $seller_poc_id : null,
			'poc_status'            => $poc_status,
			'membership_tier'       => $tier,
			'serendipity_assigned_at' => $assigned_at,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$wpdb->update(
			$table,
			$update,
			array( 'id' => $device_id ),
			$formats,
			array( '%d' )
		);

		$payload = array(
			'device_id'             => $device_id,
			'peace_pentagon_branch' => $branch,
			'branch_source'         => $branch_source,
			'branch_preference'     => $branch_pref,
			'buyer_poc_id'          => $buyer_poc_id,
			'seller_poc_id'         => $needs_seller ? $seller_poc_id : '',
			'poc_status'            => $poc_status,
			'membership_tier'       => $tier,
			'assigned_at'           => $assigned_at,
			'welcome_copy'          => self::get_welcome_copy( $branch, $buyer_poc_id, $needs_seller ? $seller_poc_id : '' ),
		);

		$user_id = ! empty( $device->user_id ) ? (int) $device->user_id : 0;
		if ( $user_id > 0 ) {
			update_user_meta( $user_id, '_cpm_hb_serendipity', wp_json_encode( $payload ) );
		}

		$payload = apply_filters( 'cpm_hb_serendipity_assignment_payload', $payload, $device_id, $user_id, $tier );
		do_action( 'cpm_hb_serendipity_assigned', $payload, $device_id, $user_id, $tier );

		return $payload;
	}

	/**
	 * @param string $branch_slug Branch slug.
	 * @return bool
	 */
	public static function is_valid_branch( $branch_slug ) {
		$branch_slug = sanitize_key( (string) $branch_slug );
		return $branch_slug !== '' && in_array( $branch_slug, self::branch_slugs(), true );
	}

	/**
	 * Deterministic Peace Pentagon branch from device hash.
	 *
	 * @param string $device_hash Device hash.
	 * @return string Branch slug.
	 */
	public static function assign_branch_from_hash( $device_hash ) {
		$slugs = self::branch_slugs();
		$count = count( $slugs );
		if ( $count === 0 ) {
			return 'planning';
		}
		$hash_int = hexdec( substr( preg_replace( '/[^a-fA-F0-9]/', '', (string) $device_hash ), 0, 8 ) );
		if ( ! is_int( $hash_int ) ) {
			$hash_int = 0;
		}

		return $slugs[ $hash_int % $count ];
	}

	/**
	 * Local Buyer POC cluster: geo grid (0.1°) + day window, or hash fallback without geo.
	 *
	 * @param float|null $lat           Latitude.
	 * @param float|null $lng           Longitude.
	 * @param string     $timestamp     Registration timestamp (mysql).
	 * @param string     $device_hash   Device hash fallback.
	 * @return string Cluster ID.
	 */
	public static function assign_buyer_poc_id( $lat, $lng, $timestamp, $device_hash = '' ) {
		$day_window = gmdate( 'Y-m-d', strtotime( $timestamp ) );

		if ( null !== $lat && null !== $lng && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 ) {
			$grid_lat = round( $lat * 10 ) / 10;
			$grid_lng = round( $lng * 10 ) / 10;

			return sprintf( 'buyer_%s_%s_%s', $grid_lat, $grid_lng, $day_window );
		}

		$prefix = substr( preg_replace( '/[^a-fA-F0-9]/', '', (string) $device_hash ), 0, 8 );
		if ( $prefix === '' ) {
			$prefix = substr( md5( (string) $timestamp ), 0, 8 );
		}

		return sprintf( 'buyer_nogeo_%s_%s', $prefix, $day_window );
	}

	/**
	 * Global Seller POC pool from device hash + timestamp.
	 *
	 * @param string $device_hash Device hash.
	 * @param string $timestamp   Registration timestamp.
	 * @return string Seller POC cluster ID.
	 */
	public static function assign_seller_poc_id( $device_hash, $timestamp ) {
		$num_pools = (int) apply_filters( 'cpm_hb_serendipity_seller_pool_count', 100 );
		$num_pools = max( 5, min( 1000, $num_pools ) );

		$pool_hash  = hash( 'sha256', (string) $device_hash . (string) $timestamp );
		$pool_index = hexdec( substr( $pool_hash, 0, 8 ) ) % $num_pools;
		$set_number = (int) floor( $pool_index / 5 );

		return sprintf( 'seller_pool_%d_set_%d', $pool_index, $set_number );
	}

	/**
	 * @param string $buyer_poc_id   Buyer POC cluster ID.
	 * @param bool   $needs_seller   Whether tier expects seller POC.
	 * @param bool   $has_seller_poc Whether seller POC was assigned.
	 * @return string pending|active
	 */
	public static function resolve_poc_status( $buyer_poc_id, $needs_seller, $has_seller_poc ) {
		$threshold = (int) apply_filters( 'cpm_hb_serendipity_buyer_poc_active_threshold', 25 );
		$threshold = max( 1, $threshold );

		$buyer_count = self::count_devices_in_buyer_poc( $buyer_poc_id );

		if ( $buyer_count >= $threshold ) {
			return 'active';
		}
		if ( $needs_seller && $has_seller_poc ) {
			return 'pending';
		}

		return 'pending';
	}

	/**
	 * @param string $buyer_poc_id Buyer POC cluster ID.
	 * @return int
	 */
	public static function count_devices_in_buyer_poc( $buyer_poc_id ) {
		global $wpdb;
		$buyer_poc_id = (string) $buyer_poc_id;
		if ( $buyer_poc_id === '' ) {
			return 0;
		}
		$table = $wpdb->prefix . Cpm_Humanblockchain_Device_Registry::TABLE_NAME;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE buyer_poc_id = %s",
				$buyer_poc_id
			)
		);
	}

	/**
	 * @param int $user_id WordPress user ID.
	 * @return array{tier?:string,branch?:string}
	 */
	private static function membership_from_user_meta( $user_id ) {
		$raw = get_user_meta( (int) $user_id, '_membership_level', true );
		if ( ! is_string( $raw ) || $raw === '' ) {
			return array();
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}
		$out = array();
		if ( ! empty( $data['tier'] ) ) {
			$out['tier'] = sanitize_key( (string) $data['tier'] );
		}
		if ( ! empty( $data['branch'] ) ) {
			$out['branch'] = sanitize_key( (string) $data['branch'] );
		}
		return $out;
	}

	/**
	 * @param int $user_id WordPress user ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_assignment_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		$raw = get_user_meta( $user_id, '_cpm_hb_serendipity', true );
		if ( is_string( $raw ) && $raw !== '' ) {
			$data = json_decode( $raw, true );
			if ( is_array( $data ) ) {
				return $data;
			}
		}
		$device = self::get_primary_device_for_user( $user_id );
		if ( ! $device || empty( $device->serendipity_assigned_at ) ) {
			return null;
		}

		return array(
			'device_id'             => (int) $device->id,
			'peace_pentagon_branch' => (string) $device->peace_pentagon_branch,
			'branch_source'         => (string) $device->branch_source,
			'buyer_poc_id'          => (string) $device->buyer_poc_id,
			'seller_poc_id'         => (string) ( $device->seller_poc_id ?? '' ),
			'poc_status'            => (string) $device->poc_status,
			'membership_tier'       => (string) ( $device->membership_tier ?? '' ),
			'assigned_at'           => (string) $device->serendipity_assigned_at,
		);
	}

	/**
	 * @param string $branch        Branch slug.
	 * @param string $buyer_poc_id  Buyer POC ID.
	 * @param string $seller_poc_id Seller POC ID (may be empty).
	 * @return string
	 */
	public static function get_welcome_copy( $branch, $buyer_poc_id, $seller_poc_id = '' ) {
		$branch_name = ucfirst( sanitize_key( (string) $branch ) );
		if ( $seller_poc_id !== '' ) {
			return sprintf(
				/* translators: 1: branch name, 2: buyer POC id, 3: seller POC id */
				__( 'Welcome to the HumanBlockchain network. You have been assigned to the %1$s branch. Your local Buyer POC cluster is %2$s, and your global Seller POC group is %3$s.', 'cpm-humanblockchain' ),
				$branch_name,
				$buyer_poc_id,
				$seller_poc_id
			);
		}

		return sprintf(
			/* translators: 1: branch name, 2: buyer POC id */
			__( 'Welcome to the HumanBlockchain network. You have been assigned to the %1$s branch. Your local Buyer POC cluster is %2$s.', 'cpm-humanblockchain' ),
			$branch_name,
			$buyer_poc_id
		);
	}
}
