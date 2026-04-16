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

		foreach ( $updates as $alter ) {
			$wpdb->query( "ALTER TABLE $table_name $alter" );
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
			created_at DATETIME NOT NULL,
			updated_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY wp_user_id (wp_user_id),
			KEY scan_type (scan_type),
			KEY transaction_id (transaction_id),
			KEY order_id (order_id),
			KEY remote_sync_status (remote_sync_status)
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
	}
}
