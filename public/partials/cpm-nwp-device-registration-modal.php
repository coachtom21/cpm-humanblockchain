<?php
/**
 * Device Registration Modal (Proof-first onboarding)
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-nwp-register-modal" class="cpm-nwp-modal cpm-nwp-modal--hidden" role="dialog" aria-labelledby="cpm-nwp-modal-title" aria-hidden="true">
	<div class="cpm-nwp-modal-overlay"></div>
	<div class="cpm-nwp-modal-container">
		<div class="cpm-nwp-modal-header">
			<div class="cpm-nwp-modal-title-wrap">
				<h2 id="cpm-nwp-modal-title" class="cpm-nwp-modal-title"><?php esc_html_e( 'Register your device', 'cpm-humanblockchain' ); ?></h2>
				<p class="cpm-nwp-modal-subtitle"><?php esc_html_e( 'Step 1 of 4 — Device registration. Capture device ID, email, mobile, and optional v-card link.', 'cpm-humanblockchain' ); ?></p>
			</div>
			<button type="button" class="cpm-nwp-modal-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>"><?php esc_html_e( 'Close', 'cpm-humanblockchain' ); ?></button>
		</div>

		<div id="cpm-nwp-register-feedback" class="cpm-nwp-inline-feedback cpm-nwp-inline-feedback--hidden" role="alert" aria-live="polite"></div>
		<form id="cpm-nwp-register-form" class="cpm-nwp-register-form" method="post" novalidate>
			<?php wp_nonce_field( 'cpm_nwp_device_register', 'cpm_nwp_register_nonce' ); ?>
			<input type="hidden" name="referral_source_nwp_id" id="cpm-nwp-referral-id" value="">
			<input type="hidden" name="device_hash" id="cpm-nwp-device-hash" value="">
			<input type="hidden" name="geo_lat" id="cpm-nwp-geo-lat" value="">
			<input type="hidden" name="geo_lng" id="cpm-nwp-geo-lng" value="">

			<div class="cpm-nwp-form-row">
				<div class="cpm-nwp-form-field">
					<label for="cpm-nwp-email"><?php esc_html_e( 'Email', 'cpm-humanblockchain' ); ?></label>
					<input type="email" id="cpm-nwp-email" name="email" placeholder="<?php esc_attr_e( 'you@email.com', 'cpm-humanblockchain' ); ?>" required>
				</div>
			</div>

			<div class="cpm-nwp-form-row">
				<div class="cpm-nwp-form-field cpm-nwp-form-field--phone">
					<label for="cpm-nwp-phone-country"><?php esc_html_e( 'Mobile number', 'cpm-humanblockchain' ); ?></label>
					<div class="cpm-nwp-phone-combo" role="group" aria-label="<?php esc_attr_e( 'Mobile number', 'cpm-humanblockchain' ); ?>">
						<?php
						require __DIR__ . '/cpm-nwp-register-phone-country-options.php';
						$def_iso = 'US';
						$def_dc  = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ? Cpm_Humanblockchain_Otp_Service::get_default_country() : 'US';
						if ( 'NP' === $def_dc ) {
							$def_iso = 'NP';
						}
						$def_iso = (string) apply_filters( 'cpm_nwp_register_default_phone_country', $def_iso );
						?>
						<select id="cpm-nwp-phone-country" name="phone_country" class="cpm-nwp-phone-country" autocomplete="country">
							<?php
							foreach ( $cpm_nwp_phone_countries as $iso => $info ) {
								$sel = ( $def_iso === $iso ) ? ' selected="selected"' : '';
								printf(
									'<option value="%1$s" data-dial="%2$s"%4$s>%3$s (+%2$s)</option>',
									esc_attr( $iso ),
									esc_attr( $info['dial'] ),
									esc_html( $info['label'] ),
									$sel // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe: $sel is literal.
								);
							}
							?>
						</select>
						<input type="tel" id="cpm-nwp-mobile-national" class="cpm-nwp-mobile-national" inputmode="numeric" autocomplete="tel-national" placeholder="<?php esc_attr_e( 'Local number, digits only', 'cpm-humanblockchain' ); ?>" aria-describedby="cpm-nwp-mobile-hint">
						<input type="hidden" name="mobile" id="cpm-nwp-mobile-e164" value="">
					</div>
					<p id="cpm-nwp-mobile-hint" class="cpm-nwp-field-note"><?php esc_html_e( 'Choose country, then enter your number without the country code. Required (8+ digits) when the Register User API key is saved under Settings → NWP Gateway. Your country is stored with the device record.', 'cpm-humanblockchain' ); ?></p>
					<p id="cpm-nwp-register-lookup-hint" class="cpm-nwp-field-note cpm-nwp-register-lookup-hint" aria-live="polite"></p>
				</div>
			</div>

			<div class="cpm-nwp-form-row">
				<div class="cpm-nwp-form-field">
					<label for="cpm-nwp-qrtiger"><?php esc_html_e( 'QRtiger v-card link (optional)', 'cpm-humanblockchain' ); ?></label>
					<input type="url" id="cpm-nwp-qrtiger" name="qrtiger_vcard_link" placeholder="<?php esc_attr_e( 'https://...', 'cpm-humanblockchain' ); ?>">
					<p class="cpm-nwp-field-note"><?php esc_html_e( 'If available. Not required for device registration.', 'cpm-humanblockchain' ); ?></p>
				</div>
			</div>

			<div class="cpm-nwp-form-actions">
				<button type="submit" class="cpm-nwp-btn cpm-nwp-btn--primary"><?php esc_html_e( 'Confirm Registration', 'cpm-humanblockchain' ); ?></button>
				<button type="button" class="cpm-nwp-btn cpm-nwp-btn--secondary cpm-nwp-open-activate-modal cpm-nwp-open-activate-modal--from-register"><?php esc_html_e( 'Activate device', 'cpm-humanblockchain' ); ?></button>
				<a href="<?php echo esc_url( home_url( '/nwp-gateway/membership-selection/' ) ); ?>" class="cpm-nwp-btn cpm-nwp-btn--secondary"><?php esc_html_e( 'Go to Membership', 'cpm-humanblockchain' ); ?></a>
			</div>
		</form>

		<div class="cpm-nwp-modal-footer">
			<p><strong><?php esc_html_e( 'Output created:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'device_registered = true — First trust point in the append-only ledger.', 'cpm-humanblockchain' ); ?></p>
			<p class="cpm-nwp-footer-note"><?php esc_html_e( 'Next steps: Join Discord Gracebook → Choose membership → Print / Issue NWP.', 'cpm-humanblockchain' ); ?></p>
		</div>
	</div>
</div>
