<?php
/**
 * SMS opt-in disclosure (10DLC) — below phone number on Send OTP flows.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$privacy_url = function_exists( 'hb_legal_privacy_url' ) ? hb_legal_privacy_url() : home_url( '/privacy-policy/' );
$terms_url   = function_exists( 'hb_legal_terms_url' ) ? hb_legal_terms_url() : home_url( '/terms-and-conditions/' );
?>
<p id="cpm-nwp-sms-disclosure" class="cpm-nwp-field-note cpm-nwp-sms-disclosure">
	<?php esc_html_e( 'By continuing, you agree to receive verification SMS messages from HumanBlockchain/NWP. Msg & data rates may apply. Reply STOP to opt out and HELP for help. View our', 'cpm-humanblockchain' ); ?>
	<a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Privacy Policy', 'cpm-humanblockchain' ); ?></a>
	<?php esc_html_e( 'and', 'cpm-humanblockchain' ); ?>
	<a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Terms & Conditions', 'cpm-humanblockchain' ); ?></a>.
</p>
