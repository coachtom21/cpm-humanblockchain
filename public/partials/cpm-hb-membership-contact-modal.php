<?php
/**
 * Collect email + phone for membership API when user is logged out or phone is missing on file.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div id="cpm-hb-membership-contact-modal" class="cpm-hb-membership-overlay" role="dialog" aria-modal="true" aria-labelledby="cpm-hb-membership-contact-title" aria-hidden="true">
	<div class="cpm-hb-membership-container cpm-hb-membership-contact-wrap">
		<button type="button" class="cpm-hb-membership-close" id="cpm-hb-membership-contact-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-hb-membership-inner">
			<h2 id="cpm-hb-membership-contact-title" class="cpm-hb-membership-title"><?php esc_html_e( 'Your details', 'cpm-humanblockchain' ); ?></h2>
			<p class="cpm-hb-membership-intro"><?php esc_html_e( 'We need your email and phone to set your membership. Optional fields help if we create a new account.', 'cpm-humanblockchain' ); ?></p>

			<form id="cpm-hb-membership-contact-form" class="cpm-hb-membership-contact-form" novalidate>
				<div class="cpm-hb-membership-contact-scroll">
					<p class="cpm-hb-membership-contact-error" id="cpm-hb-membership-contact-error" role="alert" hidden></p>
					<label class="cpm-hb-membership-field cpm-hb-membership-email-row">
						<span class="cpm-hb-membership-label"><?php esc_html_e( 'Email', 'cpm-humanblockchain' ); ?></span>
						<input type="email" name="email" id="cpm-hb-membership-field-email" class="cpm-hb-membership-input" autocomplete="email" required />
					</label>
					<label class="cpm-hb-membership-field">
						<span class="cpm-hb-membership-label"><?php esc_html_e( 'Phone', 'cpm-humanblockchain' ); ?></span>
						<input type="tel" name="phone" id="cpm-hb-membership-field-phone" class="cpm-hb-membership-input" autocomplete="tel" required />
					</label>

					<div class="cpm-hb-membership-contact-optional">
						<p class="cpm-hb-membership-optional-heading"><?php esc_html_e( 'Optional (new accounts)', 'cpm-humanblockchain' ); ?></p>
						<p class="cpm-hb-membership-optional-intro"><?php esc_html_e( 'Use these when a new account should be created for you on submit.', 'cpm-humanblockchain' ); ?></p>
						<div class="cpm-hb-membership-optional-grid">
							<label class="cpm-hb-membership-field">
								<span class="cpm-hb-membership-label"><?php esc_html_e( 'First name', 'cpm-humanblockchain' ); ?></span>
								<input type="text" name="first_name" id="cpm-hb-membership-field-first-name" class="cpm-hb-membership-input" autocomplete="given-name" />
							</label>
							<label class="cpm-hb-membership-field">
								<span class="cpm-hb-membership-label"><?php esc_html_e( 'Last name', 'cpm-humanblockchain' ); ?></span>
								<input type="text" name="last_name" id="cpm-hb-membership-field-last-name" class="cpm-hb-membership-input" autocomplete="family-name" />
							</label>
							<label class="cpm-hb-membership-field cpm-hb-membership-field-span-2">
								<span class="cpm-hb-membership-label"><?php esc_html_e( 'Username', 'cpm-humanblockchain' ); ?></span>
								<input type="text" name="username" id="cpm-hb-membership-field-username" class="cpm-hb-membership-input" autocomplete="username" />
							</label>
							<label class="cpm-hb-membership-field cpm-hb-membership-field-span-2">
								<span class="cpm-hb-membership-label"><?php esc_html_e( 'Password', 'cpm-humanblockchain' ); ?></span>
								<input type="password" name="password" id="cpm-hb-membership-field-password" class="cpm-hb-membership-input" autocomplete="new-password" minlength="6" />
							</label>
						</div>
					</div>
				</div>

				<div class="cpm-hb-membership-actions">
					<button type="submit" class="cpm-hb-membership-continue" id="cpm-hb-membership-contact-submit"><?php esc_html_e( 'Submit', 'cpm-humanblockchain' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
