<?php
/**
 * “What is your role?” step after landing “Enter Website” (matches theme hb-role-popup).
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-hb-role-modal" class="cpm-hb-role-overlay" role="dialog" aria-modal="true" aria-labelledby="cpm-hb-role-title" aria-hidden="true">
	<div class="cpm-hb-role-container">
		<button type="button" class="cpm-hb-role-close" id="cpm-hb-role-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-hb-role-content">
			<h2 id="cpm-hb-role-title"><?php esc_html_e( 'What is your role?', 'cpm-humanblockchain' ); ?></h2>
			<div class="cpm-hb-role-selection">
				<label class="cpm-hb-role-option" for="cpm-hb-role-seller">
					<input type="radio" name="cpm_hb_user_role" value="seller" id="cpm-hb-role-seller" checked>
					<span><?php esc_html_e( 'Seller', 'cpm-humanblockchain' ); ?></span>
				</label>
				<label class="cpm-hb-role-option" for="cpm-hb-role-buyer">
					<input type="radio" name="cpm_hb_user_role" value="buyer" id="cpm-hb-role-buyer">
					<span><?php esc_html_e( 'Buyer', 'cpm-humanblockchain' ); ?></span>
				</label>
			</div>
			<div class="cpm-hb-role-actions">
				<button type="button" class="cpm-hb-role-continue" id="cpm-hb-role-continue"><?php esc_html_e( 'Continue', 'cpm-humanblockchain' ); ?></button>
			</div>
		</div>
	</div>
</div>
