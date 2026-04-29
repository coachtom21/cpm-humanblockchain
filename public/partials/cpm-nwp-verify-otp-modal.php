<?php
/**
 * Verify OTP modal (after SMS sent)
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-nwp-verify-otp-modal" class="cpm-nwp-modal cpm-nwp-modal--hidden cpm-nwp-modal--activate" role="dialog" aria-labelledby="cpm-nwp-verify-title" aria-hidden="true">
	<div class="cpm-nwp-modal-overlay"></div>
	<div class="cpm-nwp-modal-container cpm-nwp-activate-container">
		<button type="button" class="cpm-nwp-activate-close cpm-nwp-verify-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-nwp-activate-header">
			<span class="cpm-nwp-activate-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></span>
			<h2 id="cpm-nwp-verify-title" class="cpm-nwp-activate-title"><?php esc_html_e( 'Enter verification code', 'cpm-humanblockchain' ); ?></h2>
			<p class="cpm-nwp-verify-subtitle"><?php esc_html_e( 'We sent a 6-digit code to your phone. Enter it below.', 'cpm-humanblockchain' ); ?></p>
		</div>
		<div id="cpm-nwp-verify-feedback" class="cpm-nwp-inline-feedback cpm-nwp-inline-feedback--hidden" role="alert" aria-live="polite"></div>
		<form id="cpm-nwp-verify-otp-form" class="cpm-nwp-activate-form">
			<?php wp_nonce_field( 'cpm_nwp_verify_otp', 'cpm_nwp_verify_nonce' ); ?>
			<input type="hidden" name="mobile" id="cpm-nwp-verify-mobile" value="">
			<input type="hidden" name="phone_country" id="cpm-nwp-verify-phone-country" value="">
			<div class="cpm-nwp-form-field cpm-nwp-activate-field">
				<label for="cpm-nwp-verify-otp-input" class="screen-reader-text"><?php esc_html_e( '6-digit code', 'cpm-humanblockchain' ); ?></label>
				<input type="text" id="cpm-nwp-verify-otp-input" name="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="<?php esc_attr_e( '000000', 'cpm-humanblockchain' ); ?>" required>
			</div>
			<div class="cpm-nwp-activate-actions">
				<button type="submit" class="cpm-nwp-btn cpm-nwp-btn--otp"><?php esc_html_e( 'Verify & continue', 'cpm-humanblockchain' ); ?></button>
			</div>
		</form>
	</div>
</div>
