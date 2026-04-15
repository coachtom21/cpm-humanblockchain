<?php
/**
 * After seller OTP verify on PoD landing — transaction code for buyer.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-hb-seller-scan-success-modal" class="cpm-nwp-modal cpm-nwp-modal--hidden cpm-nwp-modal--activate" role="dialog" aria-labelledby="cpm-hb-seller-scan-success-title" aria-hidden="true">
	<div class="cpm-nwp-modal-overlay"></div>
	<div class="cpm-nwp-modal-container cpm-nwp-activate-container cpm-hb-seller-scan-success-inner">
		<button type="button" class="cpm-nwp-activate-close" id="cpm-hb-seller-scan-success-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-nwp-activate-header">
			<span class="cpm-nwp-activate-icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			</span>
			<h2 id="cpm-hb-seller-scan-success-title" class="cpm-nwp-activate-title"><?php esc_html_e( 'Scanned successfully', 'cpm-humanblockchain' ); ?></h2>
			<p class="cpm-hb-seller-scan-lead"><?php esc_html_e( 'Seller scan is successful. Here is your transaction code:', 'cpm-humanblockchain' ); ?></p>
		</div>
		<div class="cpm-hb-seller-tx-row">
			<code class="cpm-hb-seller-tx-code" id="cpm-hb-seller-tx-code-display" aria-live="polite"></code>
			<button type="button" class="cpm-nwp-btn cpm-nwp-btn--otp cpm-hb-seller-tx-copy" id="cpm-hb-seller-tx-copy"><?php esc_html_e( 'Copy', 'cpm-humanblockchain' ); ?></button>
		</div>
		<p class="cpm-hb-seller-scan-hint" id="cpm-hb-seller-tx-copy-feedback" aria-live="polite"></p>
		<p class="cpm-nwp-verify-subtitle cpm-hb-seller-scan-share"><?php esc_html_e( 'Share this transaction code with the buyer to confirm the delivery.', 'cpm-humanblockchain' ); ?></p>
		<div class="cpm-nwp-activate-actions">
			<button type="button" class="cpm-nwp-btn cpm-nwp-btn--otp cpm-hb-seller-scan-done" id="cpm-hb-seller-scan-done"><?php esc_html_e( 'Done', 'cpm-humanblockchain' ); ?></button>
		</div>
	</div>
</div>
