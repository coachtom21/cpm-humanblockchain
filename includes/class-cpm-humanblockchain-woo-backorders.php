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
	 * Order statuses to include (open / fulfillment queue).
	 *
	 * @return string[]
	 */
	public static function get_order_statuses() {
		$base = array( 'pending', 'processing', 'on-hold', 'pre-ordered' );
		return array_values( array_unique( apply_filters( 'cpm_hb_woo_backorders_order_statuses', $base ) ) );
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
