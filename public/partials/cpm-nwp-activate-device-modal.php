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
			<div class="cpm-nwp-form-field cpm-nwp-activate-field cpm-nwp-form-field--phone">
				<label for="cpm-nwp-activate-phone-country"><?php esc_html_e( 'Mobile number', 'cpm-humanblockchain' ); ?></label>
				<div class="cpm-nwp-phone-combo" role="group" aria-label="<?php esc_attr_e( 'Mobile number', 'cpm-humanblockchain' ); ?>">
					<?php
					require __DIR__ . '/cpm-nwp-register-phone-country-options.php';
					$act_def_iso = 'US';
					$act_def_dc  = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ? Cpm_Humanblockchain_Otp_Service::get_default_country() : 'US';
					if ( 'NP' === $act_def_dc ) {
						$act_def_iso = 'NP';
					}
					$act_def_iso = (string) apply_filters( 'cpm_nwp_register_default_phone_country', $act_def_iso );
					?>
					<select id="cpm-nwp-activate-phone-country" name="phone_country" class="cpm-nwp-phone-country" autocomplete="country" required>
						<?php
						foreach ( $cpm_nwp_phone_countries as $iso => $info ) {
							$sel = ( $act_def_iso === $iso ) ? ' selected="selected"' : '';
							printf(
								'<option value="%1$s" data-dial="%2$s"%4$s>%3$s (+%2$s)</option>',
								esc_attr( $iso ),
								esc_attr( $info['dial'] ),
								esc_html( $info['label'] ),
								$sel // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $sel is literal.
							);
						}
						?>
					</select>
					<input type="tel" id="cpm-nwp-activate-mobile-national" class="cpm-nwp-mobile-national" inputmode="numeric" autocomplete="tel-national" placeholder="<?php esc_attr_e( 'Local number, digits only', 'cpm-humanblockchain' ); ?>" required aria-describedby="cpm-nwp-activate-mobile-hint cpm-nwp-activate-lookup-hint">
					<input type="hidden" name="mobile" id="cpm-nwp-activate-mobile-e164" value="">
				</div>
				<p id="cpm-nwp-activate-mobile-hint" class="cpm-nwp-field-note cpm-nwp-activate-field-hint"><?php esc_html_e( 'Choose your country, then your number without the country code. Must match a device you already registered.', 'cpm-humanblockchain' ); ?></p>
				<p id="cpm-nwp-activate-lookup-hint" class="cpm-nwp-field-note cpm-nwp-activate-lookup-hint" aria-live="polite"></p>
			</div>
			<div class="cpm-nwp-activate-actions">
				<button type="submit" class="cpm-nwp-btn cpm-nwp-btn--otp"><?php esc_html_e( 'Send OTP', 'cpm-humanblockchain' ); ?></button>
			</div>
		</form>
	</div>
</div>
