<?php
/**
 * Activate Device / Login Modal (mobile + OTP)
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-nwp-activate-modal" class="cpm-nwp-modal cpm-nwp-modal--hidden cpm-nwp-modal--activate" role="dialog" aria-labelledby="cpm-nwp-activate-title" aria-hidden="true">
	<div class="cpm-nwp-modal-overlay"></div>
	<div class="cpm-nwp-modal-container cpm-nwp-activate-container">
		<button type="button" class="cpm-nwp-activate-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-nwp-activate-header">
			<span class="cpm-nwp-activate-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
			<h2 id="cpm-nwp-activate-title" class="cpm-nwp-activate-title"><?php esc_html_e( 'Your Phone Number', 'cpm-humanblockchain' ); ?></h2>
		</div>
		<div id="cpm-nwp-activate-feedback" class="cpm-nwp-inline-feedback cpm-nwp-inline-feedback--hidden" role="alert" aria-live="polite"></div>
		<form id="cpm-nwp-activate-form" class="cpm-nwp-activate-form">
			<?php wp_nonce_field( 'cpm_nwp_send_otp', 'cpm_nwp_otp_nonce' ); ?>
			<div class="cpm-nwp-form-field cpm-nwp-activate-field">
				<label for="cpm-nwp-activate-mobile" class="screen-reader-text"><?php esc_html_e( 'Mobile number', 'cpm-humanblockchain' ); ?></label>
				<input type="tel" id="cpm-nwp-activate-mobile" name="mobile" autocomplete="tel" required>
			</div>
			<div class="cpm-nwp-activate-actions">
				<button type="submit" class="cpm-nwp-btn cpm-nwp-btn--otp"><?php esc_html_e( 'Send OTP', 'cpm-humanblockchain' ); ?></button>
			</div>
		</form>
	</div>
</div>
