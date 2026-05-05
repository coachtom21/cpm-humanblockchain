<?php
/**
 * Landing page “Enter Website” gate — matches hello-theme-child entry-overlay design.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name = get_bloginfo( 'name' );
$how_url     = apply_filters( 'cpm_hb_how_it_works_url', home_url( '/two-qrcode/' ) );
$two_qr_page = get_page_by_path( 'two-qrcode' );
if ( $two_qr_page instanceof WP_Post ) {
	$how_url = get_permalink( $two_qr_page );
}
$what_default = home_url( '/how-it-works/' );
$hiw_page     = get_page_by_path( 'how-it-works' );
if ( $hiw_page instanceof WP_Post ) {
	$what_default = get_permalink( $hiw_page );
}
$what_url = apply_filters( 'cpm_hb_what_is_this_url', $what_default );

$landing_home_url = apply_filters( 'cpm_hb_landing_home_url', home_url( '/' ) );

$logo_inner   = '';
$logo_has_img = false;

if ( function_exists( 'hb_get_site_logo' ) ) {
	$candidate = hb_get_site_logo(
		'medium',
		array(
			'class'       => 'cpm-hb-entry-logo-img',
			'aria-hidden' => 'true',
		)
	);
	if ( is_string( $candidate ) && false !== strpos( $candidate, '<img' ) ) {
		$logo_inner   = $candidate;
		$logo_has_img = true;
	}
}

if ( ! $logo_has_img ) {
	$custom_logo_id = get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$img = wp_get_attachment_image(
			(int) $custom_logo_id,
			'medium',
			false,
			array(
				'class'   => 'cpm-hb-entry-logo-img',
				'alt'     => esc_attr( $site_name ),
				'loading' => 'eager',
			)
		);
		if ( $img ) {
			$logo_inner   = $img;
			$logo_has_img = true;
		}
	}
}

if ( ! $logo_has_img ) {
	$site_icon_id = (int) get_option( 'site_icon' );
	if ( $site_icon_id ) {
		$img = wp_get_attachment_image(
			$site_icon_id,
			'medium',
			false,
			array(
				'class'   => 'cpm-hb-entry-logo-img',
				'alt'     => esc_attr( $site_name ),
				'loading' => 'eager',
			)
		);
		if ( $img ) {
			$logo_inner   = $img;
			$logo_has_img = true;
		}
	}
}

if ( ! $logo_has_img ) {
	$abbr = ( function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) && mb_strlen( $site_name ) > 0 )
		? mb_strtoupper( mb_substr( $site_name, 0, 2 ) )
		: strtoupper( substr( $site_name, 0, min( 2, max( 0, strlen( $site_name ) ) ) ) );
	if ( '' === $abbr ) {
		$abbr = 'HB';
	}
	$logo_inner = '<span class="cpm-hb-entry-logo-fallback">' . esc_html( $abbr ) . '</span>';
}

$logo_inner = apply_filters( 'cpm_hb_landing_entry_logo_inner_html', $logo_inner, $logo_has_img, $site_name );
?>

<div id="cpm-hb-landing-entry-modal" class="cpm-hb-entry-overlay active" role="dialog" aria-modal="true" aria-labelledby="cpm-hb-entry-title">
	<div class="cpm-hb-entry-overlay-shell">
		<div class="cpm-hb-entry-modal">
			<button type="button" class="cpm-hb-entry-close" id="cpm-hb-landing-dismiss" aria-label="<?php esc_attr_e( 'Close dialog', 'cpm-humanblockchain' ); ?>">
				<span class="cpm-hb-entry-close-icon" aria-hidden="true">&times;</span>
			</button>
			<div class="cpm-hb-entry-top">
				<div class="cpm-hb-entry-brand">
					<div class="cpm-hb-entry-logo<?php echo $logo_has_img ? ' cpm-hb-entry-logo--has-image' : ''; ?>" aria-hidden="true">
						<?php echo wp_kses_post( $logo_inner ); ?>
					</div>
					<div>
						<h2 id="cpm-hb-entry-title" class="cpm-hb-entry-h1"><?php echo esc_html( $site_name ); ?> • <?php esc_html_e( 'Enter Website', 'cpm-humanblockchain' ); ?></h2>
						<div class="cpm-hb-entry-sub"><?php esc_html_e( 'Three quick prompts (Y/Y/Y). Then you choose the path.', 'cpm-humanblockchain' ); ?></div>
					</div>
				</div>
				<div class="cpm-hb-entry-header-cta">
					<a class="cpm-hb-entry-btn" id="cpm-hb-landing-home" href="<?php echo esc_url( $landing_home_url ); ?>"><?php esc_html_e( 'Home', 'cpm-humanblockchain' ); ?></a>
					<a class="cpm-hb-entry-btn" id="cpm-hb-landing-what" href="<?php echo esc_url( $what_url ); ?>"><?php esc_html_e( 'What is this?', 'cpm-humanblockchain' ); ?></a>
				</div>
			</div>

			<div class="cpm-hb-entry-q" id="cpm-hb-q1">
				<h2><?php esc_html_e( 'Prompt 1 — Is this Proof of Delivery?', 'cpm-humanblockchain' ); ?></h2>
				<p><?php esc_html_e( 'Choose Yes only if you’re confirming a delivery event (voucher attached / proof recorded).', 'cpm-humanblockchain' ); ?></p>
				<div class="cpm-hb-entry-pillRow">
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-pod-yes" data-prompt="proof" data-value="yes" aria-pressed="false"><?php esc_html_e( 'Yes', 'cpm-humanblockchain' ); ?></button>
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-pod-no" data-prompt="proof" data-value="no" aria-pressed="false"><?php esc_html_e( 'No', 'cpm-humanblockchain' ); ?></button>
				</div>
			</div>

			<div class="cpm-hb-entry-q" id="cpm-hb-q2">
				<h2><?php esc_html_e( 'Prompt 2 — Is this the Final Destination?', 'cpm-humanblockchain' ); ?></h2>
				<p><?php esc_html_e( 'Choose Yes only if the package arrived at its intended final destination.', 'cpm-humanblockchain' ); ?></p>
				<div class="cpm-hb-entry-pillRow">
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-fd-yes" data-prompt="final" data-value="yes" aria-pressed="false"><?php esc_html_e( 'Yes', 'cpm-humanblockchain' ); ?></button>
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-fd-no" data-prompt="final" data-value="no" aria-pressed="false"><?php esc_html_e( 'No', 'cpm-humanblockchain' ); ?></button>
				</div>
			</div>

			<div class="cpm-hb-entry-q" id="cpm-hb-q3">
				<h2><?php esc_html_e( 'Prompt 3 — NWP acceptance (issuer path)', 'cpm-humanblockchain' ); ?></h2>
				<p><?php esc_html_e( 'Choose Yes only if you accept the New World Penny (NWP) presented for this encounter. If Yes, specify whether it is issued on an individual, Patron Organizing Community (POC) / five-seller group, or guild path—as shown on your scan or voucher.', 'cpm-humanblockchain' ); ?></p>
				<div class="cpm-hb-entry-pillRow">
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-nwp-yes" data-prompt="nwp" data-value="yes" aria-pressed="false"><?php esc_html_e( 'Yes', 'cpm-humanblockchain' ); ?></button>
					<button type="button" class="cpm-hb-entry-pill" id="cpm-hb-nwp-no" data-prompt="nwp" data-value="no" aria-pressed="false"><?php esc_html_e( 'No', 'cpm-humanblockchain' ); ?></button>
				</div>
				<div class="cpm-hb-entry-issuer-panel" id="cpm-hb-q3-issuer" hidden>
					<p class="cpm-hb-entry-issuer-lead"><?php esc_html_e( 'Which issuer path applies to this NWP?', 'cpm-humanblockchain' ); ?></p>
					<div class="cpm-hb-entry-pillRow cpm-hb-entry-pillRow--issuer" role="group" aria-label="<?php esc_attr_e( 'NWP issuer type', 'cpm-humanblockchain' ); ?>">
						<button type="button" class="cpm-hb-entry-pill cpm-hb-entry-issuer-pill" data-nwp-issuer="individual" aria-pressed="false"><?php esc_html_e( 'Individual', 'cpm-humanblockchain' ); ?></button>
						<button type="button" class="cpm-hb-entry-pill cpm-hb-entry-issuer-pill" data-nwp-issuer="poc" aria-pressed="false"><?php esc_html_e( 'POC / five-seller', 'cpm-humanblockchain' ); ?></button>
						<button type="button" class="cpm-hb-entry-pill cpm-hb-entry-issuer-pill" data-nwp-issuer="guild" aria-pressed="false"><?php esc_html_e( 'Guild', 'cpm-humanblockchain' ); ?></button>
					</div>
				</div>
			</div>

			<div class="cpm-hb-entry-divider" role="separator"></div>

			<div class="cpm-hb-entry-cta-row">
				<button type="button" class="cpm-hb-entry-btn cpm-hb-entry-btn--primary" id="cpm-hb-enter-website"><?php esc_html_e( 'Enter Website', 'cpm-humanblockchain' ); ?></button>
				<a class="cpm-hb-entry-btn cpm-hb-entry-btn--ghost" id="cpm-hb-how-it-works" href="<?php echo esc_url( $what_url ); ?>"><?php esc_html_e( 'How it works', 'cpm-humanblockchain' ); ?></a>
			</div>

			<p class="cpm-hb-entry-fine">
				<?php esc_html_e( 'Your responses are recorded for reputation outcomes (“Kalshi Mirror” style metrics) at individual / group / guild levels. Demo storage key:', 'cpm-humanblockchain' ); ?>
				<code>hb_last_scan</code>.
			</p>
			<p class="cpm-hb-entry-note">
				<?php esc_html_e( 'All three prompts (Proof of Delivery, Final Destination, NWP acceptance) are recorded together. If NWP = Yes, also pick Individual, POC/five-seller, or Guild. If Proof of Delivery = Yes (whether or not Final Destination), Continue can open buyer/seller, phone verification, then delivery / backorder. If Proof of Delivery = No, answers are saved and you enter the site.', 'cpm-humanblockchain' ); ?>
			</p>
		</div>
	</div>
</div>
