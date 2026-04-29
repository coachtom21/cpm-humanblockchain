<?php
/**
 * Smallstreet hub REST: opt-in only (default off for standalone HumanBlockchain).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True when the URL host is the Smallstreet hub (smallstreet.app and subdomains).
 *
 * @param string $url Full URL.
 * @return bool
 */
function cpm_hb_is_smallstreet_host( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || $host === '' ) {
		return false;
	}
	$host = strtolower( $host );
	// e.g. www.smallstreet.app, smallstreet.app, api.smallstreet.app
	return ( $host === 'smallstreet.app' || false !== strpos( $host, 'smallstreet.app' ) );
}

/**
 * When true, outbound HTTP to the Smallstreet hub REST APIs is allowed. Default false.
 *
 * Add `add_filter( 'cpm_hb_enable_smallstreet_rest', '__return_true' );` in a custom plugin
 * to re-enable membership/register-user/XP sync/backorders against smallstreet.app.
 *
 * @return bool
 */
function cpm_hb_smallstreet_rest_enabled() {
	return (bool) apply_filters( 'cpm_hb_enable_smallstreet_rest', false );
}

/**
 * Block outbound request to a Smallstreet URL when the integration is disabled.
 *
 * @param string $url Full URL.
 * @return bool
 */
function cpm_hb_should_block_outbound_smallstreet_url( $url ) {
	if ( cpm_hb_smallstreet_rest_enabled() ) {
		return false;
	}
	return cpm_hb_is_smallstreet_host( $url );
}
