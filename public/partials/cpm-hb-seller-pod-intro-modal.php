<?php
/**
 * Shown after choosing Seller on PoD landing — before phone / OTP.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-hb-seller-pod-intro-modal" class="cpm-nwp-modal cpm-nwp-modal--hidden cpm-nwp-modal--activate" role="dialog" aria-labelledby="cpm-hb-seller-intro-title" aria-hidden="true">
	<div class="cpm-nwp-modal-overlay"></div>
	<div class="cpm-nwp-modal-container cpm-nwp-activate-container">
		<button type="button" class="cpm-nwp-activate-close" id="cpm-hb-seller-pod-intro-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-nwp-activate-header">
			<span class="cpm-nwp-activate-icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
			</span>
			<h2 id="cpm-hb-seller-intro-title" class="cpm-nwp-activate-title"><?php esc_html_e( 'Seller verification', 'cpm-humanblockchain' ); ?></h2>
			<p class="cpm-nwp-verify-subtitle"><?php esc_html_e( 'Next, verify your registered mobile number. After you confirm the code, you will receive a transaction code to share with the buyer so they can confirm delivery.', 'cpm-humanblockchain' ); ?></p>
		</div>
		<div class="cpm-nwp-activate-actions">
			<button type="button" class="cpm-nwp-btn cpm-nwp-btn--otp" id="cpm-hb-seller-pod-intro-continue"><?php esc_html_e( 'Continue', 'cpm-humanblockchain' ); ?></button>
		</div>
	</div>
</div>
