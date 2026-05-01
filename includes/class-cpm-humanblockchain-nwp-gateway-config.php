<?php
/**
 * NWP Gateway stored settings (shared getters for admin + public / REST).
 *
 * @package Cpm_Humanblockchain
 */

/**
 * Class Cpm_Humanblockchain_Nwp_Gateway_Config
 */
class Cpm_Humanblockchain_Nwp_Gateway_Config {

	const DEFAULT_TWO_SCAN_MAX_SECONDS     = 180;
	const DEFAULT_TWO_SCAN_MAX_DISTANCE_M  = 50;
	const OPTION_TWO_SCAN_MAX_SECONDS      = 'cpm_nwp_two_scan_max_seconds';
	const OPTION_TWO_SCAN_MAX_DISTANCE_M   = 'cpm_nwp_two_scan_max_distance_m';

	/**
	 * Maximum allowed seconds between scan 1 and scan 2 (PoD / two-scan flow).
	 *
	 * @return int Positive seconds.
	 */
	public static function get_two_scan_max_seconds() {
		$v = (int) get_option( self::OPTION_TWO_SCAN_MAX_SECONDS, self::DEFAULT_TWO_SCAN_MAX_SECONDS );
		if ( $v < 1 ) {
			$v = self::DEFAULT_TWO_SCAN_MAX_SECONDS;
		}
		return (int) apply_filters( 'cpm_nwp_two_scan_max_seconds', $v );
	}

	/**
	 * Maximum allowed distance in meters between scan 1 and scan 2 locations.
	 *
	 * @return int Positive meters.
	 */
	public static function get_two_scan_max_distance_m() {
		$v = (int) get_option( self::OPTION_TWO_SCAN_MAX_DISTANCE_M, self::DEFAULT_TWO_SCAN_MAX_DISTANCE_M );
		if ( $v < 1 ) {
			$v = self::DEFAULT_TWO_SCAN_MAX_DISTANCE_M;
		}
		return (int) apply_filters( 'cpm_nwp_two_scan_max_distance_m', $v );
	}
}
