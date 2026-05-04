<?php
/**
 * WooCommerce orders for the Human Blockchain backorders page (shop orders on this site).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build display rows from WooCommerce for logged-in customers.
 */
class Cpm_Humanblockchain_Woo_Backorders {

	/**
	 * Register PoD / backorders integration hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'cpm_hb_buyer_confirmed_delivery', array( __CLASS__, 'maybe_mark_woo_orders_after_pod_confirm' ), 10, 4 );
		add_action( 'cpm_hb_buyer_confirmed_delivery', array( __CLASS__, 'maybe_note_trade_credit_and_action' ), 15, 4 );

		add_filter( 'is_protected_meta', array( __CLASS__, 'unhide_nwp_daily_cap_meta_in_order_ui' ), 10, 3 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_nwp_two_scan_meta_box' ), 20, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_order_nwp_two_scan_cap_field' ), 30, 2 );
	}

	/**
	 * WooCommerce hides underscore meta keys in the order "Custom fields" box; this key must stay visible to confirm it saved.
	 *
	 * @param bool   $protected Whether the key is protected (hidden).
	 * @param string $meta_key  Meta key.
	 * @param string $meta_type Object type (e.g. order).
	 * @return bool
	 */
	public static function unhide_nwp_daily_cap_meta_in_order_ui( $protected, $meta_key, $meta_type ) {
		if ( '_cpm_hb_nwp_daily_max_usd' !== $meta_key ) {
			return $protected;
		}
		if ( in_array( (string) $meta_type, array( 'order', 'post', '' ), true ) ) {
			return false;
		}
		return $protected;
	}

	/**
	 * Sidebar meta box on order edit (HPOS + legacy) so the NWP two-scan control is easy to find.
	 *
	 * @param string              $screen_or_post_type Screen id (HPOS) or post type (legacy).
	 * @param WC_Order|WP_Post    $post_or_order       Order object or shop_order post.
	 */
	public static function register_nwp_two_scan_meta_box( $screen_or_post_type, $post_or_order ) {
		$order = null;
		if ( $post_or_order instanceof WC_Order ) {
			$order = $post_or_order;
		} elseif ( $post_or_order instanceof WP_Post && in_array( $post_or_order->post_type, array( 'shop_order', 'shop_order_placehold' ), true ) ) {
			$order = wc_get_order( $post_or_order->ID );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$screen = is_string( $screen_or_post_type ) ? $screen_or_post_type : '';
		if ( $screen === '' ) {
			return;
		}
		add_meta_box(
			'cpm_hb_order_nwp_two_scan',
			__( 'Human Blockchain — NWP two-scan', 'cpm-humanblockchain' ),
			array( __CLASS__, 'render_order_nwp_two_scan_cap_field' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Meta box / order screen: checkbox to tag order for strict two-scan (stores _cpm_hb_nwp_daily_max_usd = 0.03).
	 *
	 * @param WC_Order|WP_Post $order_or_post Order or legacy post.
	 */
	public static function render_order_nwp_two_scan_cap_field( $order_or_post ) {
		if ( ! is_admin() || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = $order_or_post instanceof WC_Order ? $order_or_post : wc_get_order( $order_or_post );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$raw     = $order->get_meta( '_cpm_hb_nwp_daily_max_usd', true );
		$checked = false;
		if ( is_numeric( $raw ) && abs( (float) $raw - 0.03 ) < 0.0001 ) {
			$checked = true;
		} elseif ( is_string( $raw ) && preg_match( '/^\s*0?\.03\s*$/', $raw ) ) {
			$checked = true;
		}
		?>
		<div class="cpm-hb-order-nwp-cap" style="padding:0 2px;">
			<input type="hidden" name="cpm_hb_order_nwp_cap_fields" value="1" />
			<label style="display:block;margin-bottom:8px;">
				<input type="checkbox" name="cpm_hb_order_nwp_strict_two_scan" value="1" <?php checked( $checked ); ?> />
				<?php esc_html_e( 'Strict proof-of-delivery time/distance (NWP $0.03/day cap)', 'cpm-humanblockchain' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Check this, then click Update on the order. The value is stored as order meta _cpm_hb_nwp_daily_max_usd = 0.03. Use when “Cap-aware two-scan” is enabled under NWP Gateway settings.', 'cpm-humanblockchain' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Whether the current POST is a valid WooCommerce order save from admin (legacy or HPOS).
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private static function verify_order_admin_save_nonce( $order_id ) {
		$order_id = (int) $order_id;
		if ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
		if ( $order_id > 0 && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-order_' . $order_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return true;
		}
		return false;
	}

	/**
	 * Persist NWP cap meta when the order is saved in admin.
	 *
	 * @param int                $order_id Order ID.
	 * @param WC_Order|WP_Post|null $post_or_order Order object (HPOS) or post (legacy).
	 */
	public static function save_order_nwp_two_scan_cap_field( $order_id, $post_or_order = null ) {
		if ( ! self::verify_order_admin_save_nonce( (int) $order_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = null;
		if ( $post_or_order instanceof WC_Order ) {
			$order = $post_or_order;
		} elseif ( $post_or_order instanceof WP_Post ) {
			$order = wc_get_order( $post_or_order->ID );
		} else {
			$order = wc_get_order( (int) $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( empty( $_POST['cpm_hb_order_nwp_cap_fields'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( ! empty( $_POST['cpm_hb_order_nwp_strict_two_scan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$order->update_meta_data( '_cpm_hb_nwp_daily_max_usd', '0.03' );
		} else {
			$order->delete_meta_data( '_cpm_hb_nwp_daily_max_usd' );
		}
		$order->save();
	}

	/**
	 * After PoD confirm: optional Woo order note + action for seller trade credit (hub can listen).
	 *
	 * @param int    $buyer_id   Buyer WP user ID.
	 * @param int    $seller_id  Seller WP user ID.
	 * @param int[]  $order_ids  Order IDs.
	 * @param string $code       HB transaction code.
	 */
	public static function maybe_note_trade_credit_and_action( $buyer_id, $seller_id, $order_ids, $code ) {
		$buyer_id  = (int) $buyer_id;
		$seller_id = (int) $seller_id;
		$code      = is_string( $code ) ? $code : '';
		if ( ! is_array( $order_ids ) || empty( $order_ids ) ) {
			return;
		}
		$credit = (float) apply_filters( 'cpm_hb_seller_trade_credit_on_delivery_usd', 10.30, $buyer_id, $seller_id, $order_ids, $code );
		$credit = max( 0, $credit );
		if ( $credit <= 0 ) {
			return;
		}
		/**
		 * Seller-side trade credit after buyer confirms delivery (implement wallet/hub credit here).
		 *
		 * @param float  $credit    USD amount (filterable default 10.30).
		 * @param int    $seller_id Seller WP user ID.
		 * @param int    $buyer_id  Buyer WP user ID.
		 * @param int[]  $order_ids Order IDs from confirm UI.
		 * @param string $code      HB-… code.
		 */
		do_action( 'cpm_hb_seller_trade_credit_due', $credit, $seller_id, $buyer_id, $order_ids, $code );

		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$note = (string) apply_filters(
			'cpm_hb_seller_trade_credit_order_note',
			sprintf(
				/* translators: 1: formatted USD, 2: seller WP user ID, 3: HB transaction code */
				__( 'Proof-of-delivery: register %1$s trade credit for seller (WP user %2$d). Transaction code %3$s.', 'cpm-humanblockchain' ),
				wp_strip_all_tags( function_exists( 'wc_price' ) ? wc_price( $credit ) : ( '$' . number_format_i18n( $credit, 2 ) ) ),
				$seller_id,
				$code
			),
			$credit,
			$seller_id,
			$buyer_id,
			$order_ids,
			$code
		);
		if ( $note === '' ) {
			return;
		}
		foreach ( $order_ids as $oid ) {
			$oid = (int) $oid;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			if ( ! self::buyer_owns_wc_order( $order, $buyer_id ) ) {
				continue;
			}
			$order->add_order_note( $note, false, true );
			break;
		}
	}

	/**
	 * After buyer PoD confirm (transaction code + selected rows), set matching WooCommerce orders to the target status.
	 *
	 * Default status is `completed` (WooCommerce’s fulfilled state). Use filter `cpm_hb_pod_confirm_woo_order_status`
	 * to use a custom status such as `delivered` when it is registered for WooCommerce.
	 *
	 * @param int    $buyer_id   Buyer WP user ID.
	 * @param int    $seller_id  Seller WP user ID (from HB code lookup).
	 * @param int[]  $order_ids  Order IDs from the backorders table (Woo + hub merged; only WC orders are updated).
	 * @param string $code       HB transaction code.
	 */
	public static function maybe_mark_woo_orders_after_pod_confirm( $buyer_id, $seller_id, $order_ids, $code ) {
		$buyer_id  = (int) $buyer_id;
		$seller_id = (int) $seller_id;
		$code      = is_string( $code ) ? $code : '';

		if ( ! function_exists( 'wc_get_order' ) || $buyer_id <= 0 || ! is_array( $order_ids ) || empty( $order_ids ) ) {
			return;
		}

		if ( ! (bool) apply_filters( 'cpm_hb_pod_confirm_update_woo_order_status', true, $buyer_id, $seller_id, $order_ids, $code ) ) {
			return;
		}

		$allowed_from = apply_filters(
			'cpm_hb_pod_confirm_woo_allowed_from_statuses',
			self::get_order_statuses(),
			$buyer_id,
			$seller_id,
			$order_ids,
			$code
		);
		if ( ! is_array( $allowed_from ) ) {
			$allowed_from = self::get_order_statuses();
		}

		foreach ( $order_ids as $oid ) {
			$oid = (int) $oid;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			if ( ! self::buyer_owns_wc_order( $order, $buyer_id ) ) {
				continue;
			}
			$current = $order->get_status();
			if ( ! in_array( $current, $allowed_from, true ) ) {
				continue;
			}

			$new_status = apply_filters( 'cpm_hb_pod_confirm_woo_order_status', 'completed', $order, $buyer_id, $seller_id, $code );
			$new_status = is_string( $new_status ) ? sanitize_key( $new_status ) : 'completed';
			if ( $new_status === '' ) {
				$new_status = 'completed';
			}
			$status_check = ( 0 === strpos( $new_status, 'wc-' ) ) ? $new_status : 'wc-' . $new_status;
			if ( function_exists( 'wc_is_order_status' ) && ! wc_is_order_status( $status_check ) ) {
				$new_status = 'completed';
			}
			if ( $current === $new_status ) {
				continue;
			}

			$note = (string) apply_filters(
				'cpm_hb_pod_confirm_woo_order_note',
				__( 'Order marked after buyer proof-of-delivery confirmation (Human Blockchain).', 'cpm-humanblockchain' ),
				$order,
				$buyer_id,
				$seller_id,
				$code
			);

			if ( $note !== '' ) {
				$order->update_status( $new_status, $note );
			} else {
				$order->update_status( $new_status );
			}
		}
	}

	/**
	 * Whether the given user is allowed to complete this order via PoD (customer ID or billing email match).
	 *
	 * @param WC_Order $order    Order.
	 * @param int      $buyer_id WP user ID.
	 * @return bool
	 */
	private static function buyer_owns_wc_order( $order, $buyer_id ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		$buyer_id = (int) $buyer_id;
		if ( $buyer_id <= 0 ) {
			return false;
		}
		if ( (int) $order->get_customer_id() === $buyer_id ) {
			return true;
		}
		$user = get_userdata( $buyer_id );
		if ( $user && is_email( $user->user_email ) ) {
			$bill = $order->get_billing_email();
			if ( is_string( $bill ) && $bill !== '' && strtolower( $bill ) === strtolower( $user->user_email ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'cpm_hb_pod_confirm_buyer_owns_order', false, $order, $buyer_id );
	}

	/**
	 * Order statuses to include (open / fulfillment queue).
	 *
	 * @return string[]
	 */
	public static function get_order_statuses() {
		$base = array( 'pending', 'processing', 'on-hold', 'pre-ordered' );
		return array_values( array_unique( apply_filters( 'cpm_hb_woo_backorders_order_statuses', $base ) ) );
	}

	/**
	 * Whether any selected WooCommerce order should use strict two-scan time/distance (NWP $0.03/day cap).
	 * Mark orders with meta `_cpm_hb_nwp_daily_max_usd` = 0.03 (string or float), or use the filter per order.
	 *
	 * Hub-only numeric IDs (no WC order) are ignored here.
	 *
	 * @param int[] $order_ids Order IDs from the backorders confirm UI.
	 * @return bool
	 */
	public static function orders_require_geo_two_scan( array $order_ids ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return (bool) apply_filters( 'cpm_hb_orders_require_geo_two_scan_no_wc', false, $order_ids );
		}
		foreach ( $order_ids as $oid ) {
			$oid = (int) $oid;
			if ( $oid <= 0 ) {
				continue;
			}
			$order = wc_get_order( $oid );
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$raw = $order->get_meta( '_cpm_hb_nwp_daily_max_usd', true );
			$max = is_numeric( $raw ) ? (float) $raw : null;
			if ( null !== $max && abs( $max - 0.03 ) < 0.0001 ) {
				return true;
			}
			if ( is_string( $raw ) && preg_match( '/^\s*0?\.03\s*$/', $raw ) ) {
				return true;
			}
			if ( (bool) apply_filters( 'cpm_hb_wc_order_requires_nwp_two_scan_geo', false, $order ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetch WC_Customer_Order_Query-compatible orders for a user (by customer id and billing email).
	 *
	 * @param int $user_id WP user ID.
	 * @return WC_Order[]
	 */
	private static function query_orders_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$status = self::get_order_statuses();
		$limit  = (int) apply_filters( 'cpm_hb_woo_backorders_query_limit', 80 );
		$base   = array(
			'status'  => $status,
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		);

		$by_id = array();

		$q1 = wc_get_orders( array_merge( $base, array( 'customer_id' => $user_id ) ) );
		if ( is_array( $q1 ) ) {
			foreach ( $q1 as $order ) {
				if ( $order instanceof WC_Order ) {
					$by_id[ $order->get_id() ] = $order;
				}
			}
		}

		$user = get_userdata( $user_id );
		if ( $user && is_email( $user->user_email ) ) {
			$q2 = wc_get_orders(
				array_merge(
					$base,
					array(
						'billing_email' => $user->user_email,
					)
				)
			);
			if ( is_array( $q2 ) ) {
				foreach ( $q2 as $order ) {
					if ( $order instanceof WC_Order ) {
						$by_id[ $order->get_id() ] = $order;
					}
				}
			}
		}

		$orders = array_values( $by_id );
		usort(
			$orders,
			static function ( $a, $b ) {
				/** @var WC_Order $a */
				/** @var WC_Order $b */
				$ta = $a->get_date_created() ? $a->get_date_created()->getTimestamp() : 0;
				$tb = $b->get_date_created() ? $b->get_date_created()->getTimestamp() : 0;
				return $tb <=> $ta;
			}
		);

		return array_slice( $orders, 0, $limit );
	}

	/**
	 * Whether this order should appear on the backorders page.
	 * Default: open statuses and at least one line looks like preorder/backorder-capable or still queued.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function order_qualifies( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		// Default: list every open-status order from wc_get_orders(); use filter false + strict checks for hub-only lines.
		$include_all_open = (bool) apply_filters( 'cpm_hb_woo_backorders_include_all_open_orders', true, $order );
		if ( $include_all_open ) {
			return true;
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) && method_exists( 'WC_Pre_Orders_Order', 'order_contains_pre_order' ) ) {
			try {
				if ( WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
					return true;
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// ignore
			}
		}

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			if ( 'yes' === $product->get_meta( '_wc_pre_orders_enabled' ) ) {
				return true;
			}
			if ( $product->backorders_allowed() && 'no' !== $product->backorders_allowed() ) {
				return true;
			}
			if ( $product->is_on_backorder( $item->get_quantity() ) ) {
				return true;
			}
		}

		return (bool) apply_filters( 'cpm_hb_woo_backorders_order_qualifies', false, $order );
	}

	/**
	 * Short label for fulfillment type.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function resolve_type_label( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}
		$flags = array();

		if ( $order->get_meta( '_preorder_date' ) ) {
			$flags[] = __( 'Pre-order', 'cpm-humanblockchain' );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) && method_exists( 'WC_Pre_Orders_Order', 'order_contains_pre_order' ) ) {
			try {
				if ( WC_Pre_Orders_Order::order_contains_pre_order( $order ) ) {
					$flags[] = __( 'Pre-order', 'cpm-humanblockchain' );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		$has_bo = false;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			if ( 'yes' === $product->get_meta( '_wc_pre_orders_enabled' ) && ! in_array( __( 'Pre-order', 'cpm-humanblockchain' ), $flags, true ) ) {
				$flags[] = __( 'Pre-order', 'cpm-humanblockchain' );
			}
			if ( $product->backorders_allowed() && 'no' !== $product->backorders_allowed() ) {
				$has_bo = true;
			}
			if ( $product->is_on_backorder( $item->get_quantity() ) ) {
				$has_bo = true;
			}
		}
		if ( $has_bo ) {
			$flags[] = __( 'Backorder', 'cpm-humanblockchain' );
		}

		$flags = array_unique( array_filter( $flags ) );
		if ( array() !== $flags ) {
			return implode( ' · ', $flags );
		}

		return __( 'Open order', 'cpm-humanblockchain' );
	}

	/**
	 * One table row for JS (keys aligned with Smallstreet-style id / order_number).
	 *
	 * @param WC_Order $order Order.
	 * @return array<string, string>
	 */
	public static function order_to_display_row( $order ) {
		$items_desc = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$name = $item->get_name();
			$qty  = $item->get_quantity();
			if ( $name ) {
				$items_desc[] = sprintf( '%s × %s', $qty, $name );
			}
		}
		$summary = implode( '; ', array_slice( $items_desc, 0, 3 ) );
		if ( count( $items_desc ) > 3 ) {
			$summary .= ' …';
		}

		$created = $order->get_date_created();
		$date_s  = $created ? $created->date_i18n( wc_date_format() ) : '';

		return array(
			'id'            => (string) $order->get_id(),
			'order_number'  => (string) $order->get_order_number(),
			'source'        => 'WooCommerce',
			'date'          => $date_s,
			'status'        => wc_get_order_status_name( $order->get_status() ),
			'type'          => self::resolve_type_label( $order ),
			'total'         => wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ),
			'items'         => $summary,
		);
	}

	/**
	 * Rows for the current customer (this WordPress / WooCommerce site).
	 *
	 * @param int $user_id WP user ID.
	 * @return array<int, array<string, string>>
	 */
	public static function get_display_rows_for_customer( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = self::query_orders_for_user( $user_id );

		if ( ! is_array( $orders ) ) {
			return array();
		}

		$rows = array();
		foreach ( $orders as $order ) {
			if ( ! self::order_qualifies( $order ) ) {
				continue;
			}
			$rows[] = self::order_to_display_row( $order );
		}

		return apply_filters( 'cpm_hb_woo_backorders_display_rows', $rows, $user_id );
	}

	/**
	 * Merge Woo rows with hub rows; prefer Woo entry when same order id.
	 *
	 * @param array<int, array<string, mixed>> $woo_rows Woo rows.
	 * @param array<int, array<string, mixed>> $hub_rows Hub/API rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function merge_with_hub_rows( array $woo_rows, array $hub_rows ) {
		$by_id = array();
		foreach ( $hub_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( $id === '' && isset( $row['order_number'] ) ) {
				$id = (string) $row['order_number'];
			}
			if ( $id !== '' ) {
				$by_id[ $id ] = $row;
			}
		}
		foreach ( $woo_rows as $row ) {
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( $id === '' ) {
				continue;
			}
			if ( isset( $by_id[ $id ] ) ) {
				$by_id[ $id ] = array_merge( $row, $by_id[ $id ] );
			} else {
				$by_id[ $id ] = $row;
			}
		}
		return array_values( $by_id );
	}
}
