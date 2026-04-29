<?php
/**
 * Header “Get started” → Select membership modal.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cpm_hb_membership_branches = class_exists( 'Cpm_Humanblockchain_Membership' )
	? Cpm_Humanblockchain_Membership::get_branch_options()
	: array();
if ( ! is_array( $cpm_hb_membership_branches ) ) {
	$cpm_hb_membership_branches = array();
}
?>

<div id="cpm-hb-membership-modal" class="cpm-hb-membership-overlay" role="dialog" aria-modal="true" aria-labelledby="cpm-hb-membership-title" aria-hidden="true">
	<div class="cpm-hb-membership-container">
		<button type="button" class="cpm-hb-membership-close" id="cpm-hb-membership-close" aria-label="<?php esc_attr_e( 'Close', 'cpm-humanblockchain' ); ?>">&times;</button>
		<div class="cpm-hb-membership-inner">
			<h2 id="cpm-hb-membership-title" class="cpm-hb-membership-title"><?php esc_html_e( 'Select membership', 'cpm-humanblockchain' ); ?></h2>
			<p class="cpm-hb-membership-intro" id="cpm-hb-membership-intro-text"><?php esc_html_e( 'Choose the path that fits you. You can change details later in onboarding.', 'cpm-humanblockchain' ); ?></p>

			<div class="cpm-hb-membership-branch-row">
				<label for="cpm-hb-membership-branch" class="cpm-hb-membership-branch-label"><?php esc_html_e( 'Branch (select one)', 'cpm-humanblockchain' ); ?></label>
				<select id="cpm-hb-membership-branch" class="cpm-hb-membership-branch" name="cpm_hb_branch" aria-describedby="cpm-hb-membership-intro-text" autocomplete="off">
					<option value=""><?php esc_html_e( 'Branch (Select One)', 'cpm-humanblockchain' ); ?></option>
					<?php
					foreach ( $cpm_hb_membership_branches as $cpm_b_slug => $cpm_b_label ) {
						$cpm_b_slug = sanitize_key( (string) $cpm_b_slug );
						if ( $cpm_b_slug === '' ) {
							continue;
						}
						?>
					<option value="<?php echo esc_attr( $cpm_b_slug ); ?>"><?php echo esc_html( $cpm_b_label ); ?></option>
						<?php
					}
					?>
				</select>
			</div>

			<div id="cpm-hb-membership-success" class="cpm-hb-membership-success" hidden role="status" aria-live="polite" tabindex="-1">
				<p class="cpm-hb-membership-success-icon" aria-hidden="true">✓</p>
				<p class="cpm-hb-membership-success-msg" id="cpm-hb-membership-success-msg"></p>
			</div>

			<div class="cpm-hb-membership-grid" role="radiogroup" aria-labelledby="cpm-hb-membership-title">
				<button type="button" class="cpm-hb-membership-card" data-tier="yamer" role="radio" aria-checked="false" id="cpm-hb-tier-yamer">
					<span class="cpm-hb-membership-card-title"><?php esc_html_e( 'Buyer (YAM’er)', 'cpm-humanblockchain' ); ?></span>
					<span class="cpm-hb-membership-card-badge"><?php esc_html_e( 'Free • Buyer-first path', 'cpm-humanblockchain' ); ?></span>
					<ul class="cpm-hb-membership-card-list">
						<li><?php esc_html_e( 'Requires device registration + MSB credentials', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Discord Gracebook acceptance required', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( '$30 pledge verified as true → eligible for $5 XP reward', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Assigned Peace Pentagon role + Buyer POC (Pending → Active)', 'cpm-humanblockchain' ); ?></li>
					</ul>
				</button>
				<button type="button" class="cpm-hb-membership-card" data-tier="megavoter" role="radio" aria-checked="false" id="cpm-hb-tier-megavoter">
					<span class="cpm-hb-membership-card-title"><?php esc_html_e( 'Seller / Sponsor (Megavoter)', 'cpm-humanblockchain' ); ?></span>
					<span class="cpm-hb-membership-card-badge"><?php esc_html_e( '$12 annual pledge • Seller path', 'cpm-humanblockchain' ); ?></span>
					<ul class="cpm-hb-membership-card-list">
						<li><?php esc_html_e( 'Requires QRtiger v-card', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Individual-sponsored or group-sponsored', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Discord Gracebook acceptance required', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Assigned Peace Pentagon role + Seller POC (Pending → Active)', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Can issue NWP through Seller POC', 'cpm-humanblockchain' ); ?></li>
					</ul>
				</button>
				<button type="button" class="cpm-hb-membership-card" data-tier="patron" role="radio" aria-checked="false" id="cpm-hb-tier-patron">
					<span class="cpm-hb-membership-card-title"><?php esc_html_e( 'Patron / Stakeholder', 'cpm-humanblockchain' ); ?></span>
					<span class="cpm-hb-membership-card-badge"><?php esc_html_e( '$30 mo / leaderboard award', 'cpm-humanblockchain' ); ?></span>
					<ul class="cpm-hb-membership-card-list">
						<li><?php esc_html_e( 'Recognition for reliability and participation', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'Supports Detente 2030 build-out', 'cpm-humanblockchain' ); ?></li>
						<li><?php esc_html_e( 'May unlock deeper organizer responsibilities', 'cpm-humanblockchain' ); ?></li>
					</ul>
				</button>
			</div>

			<div class="cpm-hb-membership-actions">
				<button type="button" class="cpm-hb-membership-continue" id="cpm-hb-membership-continue" disabled><?php esc_html_e( 'Continue', 'cpm-humanblockchain' ); ?></button>
			</div>
		</div>
	</div>
</div>
