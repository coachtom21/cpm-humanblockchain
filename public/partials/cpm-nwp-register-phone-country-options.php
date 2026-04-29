<?php
/**
 * Country / dial options for Register device mobile field (value = ISO 3166-1 alpha-2 for storage).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cpm_nwp_phone_countries = apply_filters(
	'cpm_nwp_register_phone_countries',
	array(
		'US' => array( 'dial' => '1', 'label' => __( 'United States', 'cpm-humanblockchain' ) ),
		'CA' => array( 'dial' => '1', 'label' => __( 'Canada', 'cpm-humanblockchain' ) ),
		'NP' => array( 'dial' => '977', 'label' => __( 'Nepal', 'cpm-humanblockchain' ) ),
		'GB' => array( 'dial' => '44', 'label' => __( 'United Kingdom', 'cpm-humanblockchain' ) ),
		'IN' => array( 'dial' => '91', 'label' => __( 'India', 'cpm-humanblockchain' ) ),
		'AU' => array( 'dial' => '61', 'label' => __( 'Australia', 'cpm-humanblockchain' ) ),
	)
);
