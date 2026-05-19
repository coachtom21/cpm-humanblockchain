<?php
/**
 * Proof-of-delivery delivery ledger: rebates, trade credits, and order reserve snapshots.
 *
 * Replaces 7%/3% XP rows on the 2-scan flow. Balances remain in user meta (Pod_Wallet);
 * this table is the audit trail and powers the My Account history page.
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local ledger for PoD economics (not xp_ledger).
 */
class Cpm_Hb_Delivery_Ledger {

	const TABLE = 'hb_delivery_ledger';

	const TYPE_BUYER_REBATE         = 'buyer_rebate';
	const TYPE_SELLER_TRADE_CREDIT   = 'seller_trade_credit';
	const TYPE_ORDER_RESERVE         = 'order_reserve';

	/** Default $0.30 order reserve (informational; not added to spendable balance). */
	const DEFAULT_RESERVE_CENTS = 30;

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Whether PoD should still write seller_scan / buyer_scan XP rows.
	 *
	 * @return bool
	 */
	public static function pod_records_xp_ledger() {
		return (bool) apply_filters( 'cpm_hb_pod_record_xp_ledger', false );
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_woocommerce_account_endpoint' ), 5 );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ), 25 );
		add_action( 'woocommerce_account_delivery-wallet_endpoint', array( __CLASS__, 'render_account_endpoint' ) );
	}

	/**
	 * @return string Endpoint slug.
	 */
	public static function account_endpoint_slug() {
		return 'delivery-wallet';
	}

	/**
	 * WooCommerce My Account → Delivery wallet.
	 */
	public static function register_woocommerce_account_endpoint() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		add_rewrite_endpoint( self::account_endpoint_slug(), EP_ROOT | EP_PAGES );
	}

	/**
	 * @param array<string, string> $items Menu items.
	 * @return array<string, string>
	 */
	public static function account_menu_items( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::account_endpoint_slug() ] = __( 'Delivery wallet', 'cpm-humanblockchain' );
			}
		}
		if ( ! isset( $new[ self::account_endpoint_slug() ] ) ) {
			$new[ self::account_endpoint_slug() ] = __( 'Delivery wallet', 'cpm-humanblockchain' );
		}
		return $new;
	}

	/**
	 * My Account delivery wallet page.
	 */
	public static function render_account_endpoint() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$uid = (int) get_current_user_id();
		$reb = class_exists( 'Cpm_Humanblockchain_Pod_Wallet' ) ? Cpm_Humanblockchain_Pod_Wallet::get_rebate_balance_cents( $uid ) : 0;
		$tc  = class_exists( 'Cpm_Humanblockchain_Pod_Wallet' ) ? Cpm_Humanblockchain_Pod_Wallet::get_trade_credit_balance_cents( $uid ) : 0;
		$rows = self::get_entries_for_user( $uid, 100 );

		$fmt = static function ( $cents ) {
			$usd = max( 0, (int) $cents ) / 100.0;
			if ( function_exists( 'wc_price' ) ) {
				return wp_strip_all_tags( wc_price( $usd ) );
			}
			return '$' . number_format_i18n( $usd, 2 );
		};
		?>
		<div class="cpm-hb-delivery-wallet-account">
			<h2><?php esc_html_e( 'Delivery wallet', 'cpm-humanblockchain' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Rebates and trade credits accrue when a buyer confirms delivery (2-scan flow). Amounts on orders labeled as reserve (for example $0.30) are ring-fenced on the order—not spendable wallet balance.', 'cpm-humanblockchain' ); ?>
			</p>
			<div class="cpm-hb-delivery-wallet-account__balances">
				<p><strong><?php esc_html_e( 'Buyer rebate balance:', 'cpm-humanblockchain' ); ?></strong> <?php echo esc_html( $fmt( $reb ) ); ?></p>
				<p><strong><?php esc_html_e( 'Seller trade credit balance:', 'cpm-humanblockchain' ); ?></strong> <?php echo esc_html( $fmt( $tc ) ); ?></p>
			</div>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No delivery ledger entries yet.', 'cpm-humanblockchain' ); ?></p>
			<?php else : ?>
				<table class="shop_table shop_table_responsive woocommerce-orders-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'cpm-humanblockchain' ); ?></th>
							<th><?php esc_html_e( 'Type', 'cpm-humanblockchain' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'cpm-humanblockchain' ); ?></th>
							<th><?php esc_html_e( 'Order', 'cpm-humanblockchain' ); ?></th>
							<th><?php esc_html_e( 'Code', 'cpm-humanblockchain' ); ?></th>
							<th><?php esc_html_e( 'Note', 'cpm-humanblockchain' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-title="<?php esc_attr_e( 'Date', 'cpm-humanblockchain' ); ?>"><?php echo esc_html( isset( $row->created_at ) ? (string) $row->created_at : '' ); ?></td>
								<td data-title="<?php esc_attr_e( 'Type', 'cpm-humanblockchain' ); ?>"><?php echo esc_html( self::entry_type_label( isset( $row->entry_type ) ? (string) $row->entry_type : '' ) ); ?></td>
								<td data-title="<?php esc_attr_e( 'Amount', 'cpm-humanblockchain' ); ?>"><?php echo esc_html( $fmt( isset( $row->amount_cents ) ? (int) $row->amount_cents : 0 ) ); ?></td>
								<td data-title="<?php esc_attr_e( 'Order', 'cpm-humanblockchain' ); ?>">
									<?php
									$oid = isset( $row->order_id ) ? (int) $row->order_id : 0;
									if ( $oid > 0 ) {
										echo '#' . esc_html( (string) $oid );
									} else {
										echo '—';
									}
									?>
								</td>
								<td data-title="<?php esc_attr_e( 'Code', 'cpm-humanblockchain' ); ?>"><code><?php echo esc_html( isset( $row->transaction_code ) ? (string) $row->transaction_code : '' ); ?></code></td>
								<td data-title="<?php esc_attr_e( 'Note', 'cpm-humanblockchain' ); ?>"><?php echo esc_html( isset( $row->note ) ? (string) $row->note : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $type Entry type slug.
	 * @return string
	 */
	public static function entry_type_label( $type ) {
		$labels = array(
			self::TYPE_BUYER_REBATE       => __( 'Buyer rebate', 'cpm-humanblockchain' ),
			self::TYPE_SELLER_TRADE_CREDIT => __( 'Seller trade credit', 'cpm-humanblockchain' ),
			self::TYPE_ORDER_RESERVE      => __( 'Order reserve (info)', 'cpm-humanblockchain' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}

	/**
	 * Create table on activation / upgrade.
	 */
	public static function create_table() {
		global $wpdb;
		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			entry_type VARCHAR(32) NOT NULL,
			amount_cents INT NOT NULL DEFAULT 0,
			transaction_code VARCHAR(64) NOT NULL DEFAULT '',
			order_id BIGINT UNSIGNED DEFAULT NULL,
			order_ids_json LONGTEXT NULL,
			counterparty_wp_user_id BIGINT UNSIGNED DEFAULT NULL,
			reserve_pledge_covered TINYINT(1) NOT NULL DEFAULT 0,
			event_fingerprint VARCHAR(64) NOT NULL DEFAULT '',
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id),
			KEY entry_type (entry_type),
			KEY transaction_code (transaction_code),
			KEY order_id (order_id),
			UNIQUE KEY uniq_event (event_fingerprint)
		) {$charset_collate};";

		$wpdb->query( $sql );
	}

	/**
	 * @param int   $buyer_id  Buyer.
	 * @param int   $seller_id Seller.
	 * @param int[] $order_ids Order IDs.
	 * @param string $code     HB code.
	 * @return string
	 */
	public static function delivery_event_fingerprint( $buyer_id, $seller_id, array $order_ids, $code ) {
		$ids = array_values( array_unique( array_map( 'intval', array_filter( $order_ids ) ) ) );
		sort( $ids );
		$raw = (string) $code . '|' . (int) $buyer_id . '|' . (int) $seller_id . '|' . implode( ',', $ids );
		return hash( 'sha256', $raw );
	}

	/**
	 * @param string $base_fp Base fingerprint from delivery confirm.
	 * @param string $suffix  entry type + optional order id.
	 * @return string
	 */
	private static function row_fingerprint( $base_fp, $suffix ) {
		return hash( 'sha256', $base_fp . '|' . $suffix );
	}

	/**
	 * @param array<string, mixed> $args Row fields.
	 * @return int|false Insert ID or false.
	 */
	public static function insert_entry( array $args ) {
		global $wpdb;
		$table = self::table_name();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			self::create_table();
		}

		$fp = isset( $args['event_fingerprint'] ) ? (string) $args['event_fingerprint'] : '';
		if ( $fp === '' ) {
			return false;
		}

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE event_fingerprint = %s LIMIT 1",
				$fp
			)
		);
		if ( $exists ) {
			return (int) $exists;
		}

		$order_ids = isset( $args['order_ids'] ) && is_array( $args['order_ids'] ) ? $args['order_ids'] : array();
		$inserted  = $wpdb->insert(
			$table,
			array(
				'wp_user_id'               => (int) $args['wp_user_id'],
				'entry_type'               => sanitize_key( (string) $args['entry_type'] ),
				'amount_cents'             => (int) $args['amount_cents'],
				'transaction_code'         => isset( $args['transaction_code'] ) ? (string) $args['transaction_code'] : '',
				'order_id'                 => ! empty( $args['order_id'] ) ? (int) $args['order_id'] : null,
				'order_ids_json'           => wp_json_encode( array_values( array_map( 'intval', $order_ids ) ) ),
				'counterparty_wp_user_id'  => ! empty( $args['counterparty_wp_user_id'] ) ? (int) $args['counterparty_wp_user_id'] : null,
				'reserve_pledge_covered'   => ! empty( $args['reserve_pledge_covered'] ) ? 1 : 0,
				'event_fingerprint'        => $fp,
				'note'                     => isset( $args['note'] ) ? (string) $args['note'] : '',
				'created_at'               => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$row_id = (int) $wpdb->insert_id;
		do_action( 'cpm_hb_delivery_ledger_row_saved', $row_id, $args );
		return $row_id;
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $limit   Max rows.
	 * @return array<int, object>
	 */
	public static function get_entries_for_user( $user_id, $limit = 50 ) {
		global $wpdb;
		$user_id = (int) $user_id;
		$limit   = min( 500, max( 1, (int) $limit ) );
		$table   = self::table_name();
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE wp_user_id = %d ORDER BY id DESC LIMIT %d",
				$user_id,
				$limit
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Reserve cents for an order (filterable, default $0.30).
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	public static function order_reserve_cents( $order ) {
		$cents = (int) apply_filters( 'cpm_hb_order_reserve_amount_cents', self::DEFAULT_RESERVE_CENTS, $order );
		return max( 0, $cents );
	}

	/**
	 * Whether a $30-style pledge covers the order reserve.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function order_reserve_pledge_covered( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return false;
		}
		$meta = $order->get_meta( '_cpm_hb_reserve_pledge_covered', true );
		if ( '1' === (string) $meta || 'yes' === strtolower( (string) $meta ) ) {
			return true;
		}
		$covered = (bool) apply_filters( 'cpm_hb_order_reserve_pledge_covered', false, $order );
		if ( $covered ) {
			return true;
		}
		$total = (float) $order->get_total();
		return $total >= 29.99;
	}

	/**
	 * Record rebate, trade credit, and per-order reserve rows after successful PoD confirm.
	 *
	 * @param int    $buyer_id      Buyer.
	 * @param int    $seller_id     Seller.
	 * @param int[]  $order_ids     Orders.
	 * @param string $code          HB code.
	 * @param int    $rebate_cents  Buyer rebate credited.
	 * @param int    $credit_cents  Seller trade credit credited.
	 * @param string $base_fp       Shared event fingerprint.
	 */
	public static function record_confirm_entries( $buyer_id, $seller_id, array $order_ids, $code, $rebate_cents, $credit_cents, $base_fp ) {
		$buyer_id  = (int) $buyer_id;
		$seller_id = (int) $seller_id;
		$code      = (string) $code;
		$order_ids = array_values( array_unique( array_map( 'intval', array_filter( $order_ids ) ) ) );

		if ( $rebate_cents > 0 && $buyer_id > 0 ) {
			self::insert_entry(
				array(
					'wp_user_id'              => $buyer_id,
					'entry_type'              => self::TYPE_BUYER_REBATE,
					'amount_cents'            => $rebate_cents,
					'transaction_code'        => $code,
					'order_id'                => ! empty( $order_ids ) ? (int) $order_ids[0] : null,
					'order_ids'               => $order_ids,
					'counterparty_wp_user_id' => $seller_id,
					'event_fingerprint'       => self::row_fingerprint( $base_fp, self::TYPE_BUYER_REBATE ),
					'note'                    => __( 'Buyer delivery rebate (2-scan confirm).', 'cpm-humanblockchain' ),
				)
			);
		}

		if ( $credit_cents > 0 && $seller_id > 0 ) {
			self::insert_entry(
				array(
					'wp_user_id'              => $seller_id,
					'entry_type'              => self::TYPE_SELLER_TRADE_CREDIT,
					'amount_cents'            => $credit_cents,
					'transaction_code'        => $code,
					'order_id'                => ! empty( $order_ids ) ? (int) $order_ids[0] : null,
					'order_ids'               => $order_ids,
					'counterparty_wp_user_id' => $buyer_id,
					'event_fingerprint'       => self::row_fingerprint( $base_fp, self::TYPE_SELLER_TRADE_CREDIT ),
					'note'                    => __( 'Seller trade credit (2-scan confirm).', 'cpm-humanblockchain' ),
				)
			);
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
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
			$reserve_cents = self::order_reserve_cents( $order );
			if ( $reserve_cents <= 0 ) {
				continue;
			}
			$pledge_covered = self::order_reserve_pledge_covered( $order );
			$note           = $pledge_covered
				? __( 'Order reserve (ring-fence); covered by pledge— not wallet balance.', 'cpm-humanblockchain' )
				: __( 'Order reserve (ring-fence); not wallet balance.', 'cpm-humanblockchain' );

			foreach ( array( $buyer_id, $seller_id ) as $uid ) {
				if ( $uid <= 0 ) {
					continue;
				}
				self::insert_entry(
					array(
						'wp_user_id'              => $uid,
						'entry_type'              => self::TYPE_ORDER_RESERVE,
						'amount_cents'            => $reserve_cents,
						'transaction_code'        => $code,
						'order_id'                => $oid,
						'order_ids'               => $order_ids,
						'counterparty_wp_user_id' => ( $uid === $buyer_id ) ? $seller_id : $buyer_id,
						'reserve_pledge_covered'  => $pledge_covered,
						'event_fingerprint'       => self::row_fingerprint( $base_fp, self::TYPE_ORDER_RESERVE . '|' . $oid . '|' . $uid ),
						'note'                    => $note,
					)
				);
			}
		}
	}
}
