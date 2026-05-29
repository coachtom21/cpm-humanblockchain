<?php

/**
 * Fired during plugin activation
 *
 * @link       https://https://codepixelzmedia.com/
 * @since      1.0.0
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 * @author     Codepixelz Media <dev@codepixelzmedia.com.np>
 */
class Cpm_Humanblockchain_Activator {

	/**
	 * Create NWP devices table on plugin activation.
	 * Table is NOT removed on deactivation (data preserved).
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		self::create_nwp_devices_table();
		self::upgrade_nwp_devices_table();
		self::create_xp_ledger_table();
		self::upgrade_xp_ledger_columns();
		if ( class_exists( 'Cpm_Hb_Delivery_Ledger' ) ) {
			Cpm_Hb_Delivery_Ledger::create_table();
		} else {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'class-cpm-hb-delivery-ledger.php';
			if ( class_exists( 'Cpm_Hb_Delivery_Ledger' ) ) {
				Cpm_Hb_Delivery_Ledger::create_table();
			}
		}
		flush_rewrite_rules( false );
	}

	/**
	 * Create wp_nwp_devices table if it does not exist.
	 *
	 * @since    1.0.0
	 */
	private static function create_nwp_devices_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'nwp_devices';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			device_hash VARCHAR(64) NOT NULL,
			name VARCHAR(255) DEFAULT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(32) DEFAULT NULL,
			phone_country VARCHAR(8) DEFAULT NULL,
			geo_lat DECIMAL(10,8) DEFAULT NULL,
			geo_lng DECIMAL(11,8) DEFAULT NULL,
			registered_at DATETIME NOT NULL,
			registration_status VARCHAR(32) NOT NULL DEFAULT 'registered',
			referral_source_nwp_id BIGINT UNSIGNED DEFAULT NULL,
			qrtiger_vcard_link VARCHAR(512) DEFAULT NULL,
			msb_credentials VARCHAR(50) DEFAULT NULL,
			register_as VARCHAR(50) DEFAULT NULL,
			consent_logging TINYINT(1) NOT NULL DEFAULT 0,
			consent_discord TINYINT(1) NOT NULL DEFAULT 0,
			ip_address VARCHAR(45) DEFAULT NULL,
			user_agent VARCHAR(512) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY device_hash (device_hash),
			KEY email (email),
			KEY user_id (user_id),
			KEY referral_source_nwp_id (referral_source_nwp_id),
			KEY registered_at (registered_at)
		) $charset_collate;";

		$wpdb->query( $sql );
	}

	/**
	 * Run table upgrade on plugins_loaded (for existing installs that didn't reactivate).
	 *
	 * @since    1.0.0
	 */
	public static function maybe_upgrade_nwp_devices() {
		self::upgrade_nwp_devices_table();
	}

	/**
	 * Ensure xp_ledger exists on existing installs (no reactivation required).
	 *
	 * @since 1.0.0
	 */
	public static function maybe_upgrade_xp_ledger() {
		self::create_xp_ledger_table();
		self::upgrade_xp_ledger_columns();
	}

	/**
	 * Ensure delivery ledger table exists (existing installs).
	 *
	 * @since 1.0.0
	 */
	public static function maybe_upgrade_delivery_ledger() {
		if ( class_exists( 'Cpm_Hb_Delivery_Ledger' ) ) {
			Cpm_Hb_Delivery_Ledger::create_table();
		}
	}

	/**
	 * Add columns to wp_nwp_devices if they don't exist (for upgrades).
	 *
	 * @since    1.0.0
	 */
	private static function upgrade_nwp_devices_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nwp_devices';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table_name" );
		$updates = array();

		if ( ! in_array( 'name', $columns, true ) ) {
			$updates[] = "ADD COLUMN name VARCHAR(255) DEFAULT NULL";
		}
		if ( ! in_array( 'msb_credentials', $columns, true ) ) {
			$updates[] = "ADD COLUMN msb_credentials VARCHAR(50) DEFAULT NULL";
		}
		if ( ! in_array( 'register_as', $columns, true ) ) {
			$updates[] = "ADD COLUMN register_as VARCHAR(50) DEFAULT NULL";
		}
		if ( ! in_array( 'consent_logging', $columns, true ) ) {
			$updates[] = "ADD COLUMN consent_logging TINYINT(1) NOT NULL DEFAULT 0";
		}
		if ( ! in_array( 'consent_discord', $columns, true ) ) {
			$updates[] = "ADD COLUMN consent_discord TINYINT(1) NOT NULL DEFAULT 0";
		}
		if ( ! in_array( 'phone_country', $columns, true ) ) {
			$updates[] = "ADD COLUMN phone_country VARCHAR(8) DEFAULT NULL";
		}
		if ( ! in_array( 'peace_pentagon_branch', $columns, true ) ) {
			$updates[] = "ADD COLUMN peace_pentagon_branch VARCHAR(32) DEFAULT NULL";
		}
		if ( ! in_array( 'branch_source', $columns, true ) ) {
			$updates[] = "ADD COLUMN branch_source VARCHAR(16) DEFAULT NULL";
		}
		if ( ! in_array( 'branch_preference', $columns, true ) ) {
			$updates[] = "ADD COLUMN branch_preference VARCHAR(32) DEFAULT NULL";
		}
		if ( ! in_array( 'buyer_poc_id', $columns, true ) ) {
			$updates[] = "ADD COLUMN buyer_poc_id VARCHAR(128) DEFAULT NULL";
		}
		if ( ! in_array( 'seller_poc_id', $columns, true ) ) {
			$updates[] = "ADD COLUMN seller_poc_id VARCHAR(128) DEFAULT NULL";
		}
		if ( ! in_array( 'poc_status', $columns, true ) ) {
			$updates[] = "ADD COLUMN poc_status VARCHAR(16) NOT NULL DEFAULT 'pending'";
		}
		if ( ! in_array( 'membership_tier', $columns, true ) ) {
			$updates[] = "ADD COLUMN membership_tier VARCHAR(32) DEFAULT NULL";
		}
		if ( ! in_array( 'serendipity_assigned_at', $columns, true ) ) {
			$updates[] = "ADD COLUMN serendipity_assigned_at DATETIME DEFAULT NULL";
		}

		foreach ( $updates as $alter ) {
			$wpdb->query( "ALTER TABLE $table_name $alter" );
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table_name" );
		$indexes = $wpdb->get_results( "SHOW INDEX FROM $table_name", ARRAY_A );
		$index_names = array();
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				if ( isset( $idx['Key_name'] ) ) {
					$index_names[ $idx['Key_name'] ] = true;
				}
			}
		}
		if ( in_array( 'buyer_poc_id', $columns, true ) && empty( $index_names['buyer_poc_id'] ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD KEY buyer_poc_id (buyer_poc_id)" );
		}
		if ( in_array( 'seller_poc_id', $columns, true ) && empty( $index_names['seller_poc_id'] ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD KEY seller_poc_id (seller_poc_id)" );
		}
		if ( in_array( 'peace_pentagon_branch', $columns, true ) && empty( $index_names['peace_pentagon_branch'] ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD KEY peace_pentagon_branch (peace_pentagon_branch)" );
		}
	}

	/**
	 * Local mirror of seller/buyer scan payloads; optional Smallstreet sync metadata.
	 *
	 * @since 1.0.0
	 */
	private static function create_xp_ledger_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'xp_ledger';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			wp_user_id BIGINT UNSIGNED NOT NULL,
			scan_type VARCHAR(32) NOT NULL DEFAULT 'seller_scan',
			transaction_id VARCHAR(64) NOT NULL,
			order_id BIGINT UNSIGNED DEFAULT NULL,
			xp_units VARCHAR(64) NOT NULL DEFAULT '0',
			scan_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			entry_json LONGTEXT NULL,
			remote_ledger_id VARCHAR(64) DEFAULT NULL,
			remote_sync_status VARCHAR(32) NOT NULL DEFAULT 'pending',
			remote_last_error TEXT NULL,
			ledger_date DATETIME DEFAULT NULL,
			counterparty_wp_user_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id),
			KEY scan_type (scan_type),
			KEY transaction_id (transaction_id),
			KEY order_id (order_id),
			KEY remote_sync_status (remote_sync_status),
			KEY counterparty_wp_user_id (counterparty_wp_user_id)
		) $charset_collate;";

		$wpdb->query( $sql );
	}

	/**
	 * Migrate xp_units to VARCHAR (large XP strings) and align defaults for existing installs.
	 *
	 * @since 1.0.0
	 */
	private static function upgrade_xp_ledger_columns() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'xp_ledger';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return;
		}

		$row = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'xp_units'" );
		if ( $row && isset( $row->Type ) && false !== stripos( (string) $row->Type, 'int' ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY xp_units VARCHAR(64) NOT NULL DEFAULT '0'" );
		}

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`" );
		if ( ! in_array( 'order_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN order_id BIGINT UNSIGNED DEFAULT NULL, ADD KEY order_id (order_id)" );
		}
		if ( ! in_array( 'ledger_date', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN ledger_date DATETIME DEFAULT NULL" );
		}
		if ( ! in_array( 'counterparty_wp_user_id', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN counterparty_wp_user_id BIGINT UNSIGNED DEFAULT NULL, ADD KEY counterparty_wp_user_id (counterparty_wp_user_id)" );
		}
	}
}
