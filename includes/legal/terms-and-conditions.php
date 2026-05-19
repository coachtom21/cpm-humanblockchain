<?php
/**
 * Terms and Conditions body (included by Cpm_Hb_Legal_Pages).
 *
 * @var array<string, string> $hb_legal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name        = isset( $hb_legal['site_name'] ) ? $hb_legal['site_name'] : '';
$home_url         = isset( $hb_legal['home_url'] ) ? $hb_legal['home_url'] : '';
$privacy_url      = isset( $hb_legal['privacy_url'] ) ? $hb_legal['privacy_url'] : '';
$terms_url        = isset( $hb_legal['terms_url'] ) ? $hb_legal['terms_url'] : '';
$support_email    = isset( $hb_legal['support_email'] ) ? $hb_legal['support_email'] : '';
$effective_date   = isset( $hb_legal['effective_date'] ) ? $hb_legal['effective_date'] : '';
$governing_state  = isset( $hb_legal['governing_state'] ) ? trim( $hb_legal['governing_state'] ) : '';
$governing_venue  = isset( $hb_legal['governing_venue'] ) ? trim( $hb_legal['governing_venue'] ) : '';
?>
<h1><?php esc_html_e( 'Terms and Conditions', 'cpm-humanblockchain' ); ?></h1>

<p><strong><?php esc_html_e( 'Effective date:', 'cpm-humanblockchain' ); ?></strong> <?php echo esc_html( $effective_date ); ?></p>

<p>
	<?php
	printf(
		wp_kses(
			__( 'Welcome to <strong>%1$s</strong> (“we,” “us,” or “our”), available at <a href="%2$s">%2$s</a> (the “Site”). These Terms and Conditions (“Terms”) govern your access to and use of the Site, including the NWP Processing Center, marketplace, device registration, SMS verification, and two-scan proof-of-delivery features (collectively, the “Services”).', 'cpm-humanblockchain' ),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		),
		esc_html( $site_name ),
		esc_url( $home_url )
	);
	?>
</p>

<p>
	<?php
	printf(
		wp_kses(
			__( 'By accessing or using the Services, you agree to these Terms and our <a href="%s">Privacy Policy</a>. If you do not agree, do not use the Services.', 'cpm-humanblockchain' ),
			array( 'a' => array( 'href' => array() ) )
		),
		esc_url( $privacy_url )
	);
	?>
</p>

<h2><?php esc_html_e( '1. Eligibility', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'You must be at least 18 years old (or the age of majority in your jurisdiction) and able to form a binding contract to use the Services. You are responsible for ensuring your use complies with applicable laws in your location.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '2. Accounts and security', 'cpm-humanblockchain' ); ?></h2>

<ul>
	<li><?php esc_html_e( 'You must provide accurate registration information and keep your credentials secure.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'You are responsible for activity under your account.', 'cpm-humanblockchain' ); ?></li>
	<li>
		<?php
		printf(
			wp_kses(
				/* translators: %s: support email */
				__( 'Notify us promptly at <a href="mailto:%s">%s</a> if you suspect unauthorized access.', 'cpm-humanblockchain' ),
				array( 'a' => array( 'href' => array() ) )
			),
			esc_attr( $support_email ),
			esc_html( $support_email )
		);
		?>
	</li>
	<li><?php esc_html_e( 'We may suspend or terminate accounts that violate these Terms or pose a security risk.', 'cpm-humanblockchain' ); ?></li>
</ul>

<h2><?php esc_html_e( '3. Marketplace and orders', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'Products and services offered through the Site may be sold by us or third-party sellers, as indicated on each listing. When you place an order:', 'cpm-humanblockchain' ); ?></p>

<ul>
	<li><?php esc_html_e( 'You agree to pay all charges, taxes, and shipping shown at checkout.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Order acceptance is subject to availability, payment authorization, and fraud review.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Delivery, returns, and refunds are governed by the policies stated at checkout and applicable law.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Participation in delivery rebates, trade credits, reserves, or wallet features is subject to program rules displayed on the Site and may change with notice.', 'cpm-humanblockchain' ); ?></li>
</ul>

<h2><?php esc_html_e( '4. Device registration and proof-of-delivery', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'Certain features require registering a device, completing identity or presence steps, and/or participating in a two-scan proof-of-delivery process (seller scan, buyer scan). You agree to use these features only for legitimate transactions you are party to; provide accurate information; grant location permissions only for verification purposes described on screen; and not attempt to circumvent verification, GPS checks, time windows, or ledger/audit controls.', 'cpm-humanblockchain' ); ?></p>

<p>
	<?php
	printf(
		wp_kses(
			__( 'We may record verification events (including timestamps and, where enabled, location data) for fraud prevention, dispute resolution, and audit trails as described in our <a href="%s">Privacy Policy</a>.', 'cpm-humanblockchain' ),
			array( 'a' => array( 'href' => array() ) )
		),
		esc_url( $privacy_url )
	);
	?>
</p>

<h2><?php esc_html_e( '5. SMS verification program (NWP / device & delivery OTP)', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'This section describes our SMS one-time verification program for carriers and users. It applies when you enter your mobile number on our Site and request a verification code.', 'cpm-humanblockchain' ); ?></p>

<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;">
	<tbody>
		<tr>
			<td><strong><?php esc_html_e( 'Program name', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php echo esc_html( $site_name ); ?> / <?php esc_html_e( 'NWP SMS Verification', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Organization', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php echo esc_html( $site_name ); ?> (<?php echo esc_html( wp_parse_url( $home_url, PHP_URL_HOST ) ?: $home_url ); ?>)</td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Types of messages', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php esc_html_e( 'One-time passwords (OTP) and security codes only. No marketing or promotional SMS in this program.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'When messages are sent', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php esc_html_e( 'Only after you enter your phone number and click Send OTP (or equivalent) on our website—for example during device activation, seller scan verification, or buyer scan verification.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Message frequency', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php esc_html_e( 'Typically one message per verification request you initiate. Additional messages only if you request another code or complete another verification step.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Sample message', 'cpm-humanblockchain' ); ?></strong></td>
			<td><em><?php esc_html_e( 'Your NWP verification code is: 123456', 'cpm-humanblockchain' ); ?></em> <?php esc_html_e( '(actual codes vary and expire.)', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Cost', 'cpm-humanblockchain' ); ?></strong></td>
			<td><strong><?php esc_html_e( 'Message and data rates may apply.', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'Contact your wireless carrier for details.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Opt-in / consent', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php esc_html_e( 'By providing your mobile number and clicking to send a code on our Site, you consent to receive the verification SMS for that request. Consent is not a condition of purchasing goods unless a specific checkout step clearly states otherwise.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Opt-out', 'cpm-humanblockchain' ); ?></strong></td>
			<td><?php esc_html_e( 'Reply STOP to any message from this program to stop further SMS. You may still receive one final confirmation of your opt-out.', 'cpm-humanblockchain' ); ?></td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Help', 'cpm-humanblockchain' ); ?></strong></td>
			<td>
				<?php esc_html_e( 'Reply HELP for assistance, or email', 'cpm-humanblockchain' ); ?>
				<a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>.
			</td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Support contact', 'cpm-humanblockchain' ); ?></strong></td>
			<td>
				<a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a>
				&middot;
				<a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( untrailingslashit( $home_url ) ); ?></a>
			</td>
		</tr>
		<tr>
			<td><strong><?php esc_html_e( 'Privacy', 'cpm-humanblockchain' ); ?></strong></td>
			<td>
				<?php esc_html_e( 'See our', 'cpm-humanblockchain' ); ?>
				<a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'cpm-humanblockchain' ); ?></a>
				<?php esc_html_e( 'for how we handle phone numbers and related data.', 'cpm-humanblockchain' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<p><?php esc_html_e( 'Carriers are not liable for delayed or undelivered messages. Supported carriers vary by country; US delivery requires a valid US mobile number where US SMS is offered.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '6. Acceptable use', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'You agree not to violate any law or third-party rights; upload malware or interfere with security; impersonate others; abuse SMS verification; or manipulate proof-of-delivery, ledger, or rebate systems.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '7. Intellectual property', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'The Site, logos, text, graphics, software, and other content are owned by us or licensors and protected by intellectual property laws. You receive a limited, non-exclusive, non-transferable license to access and use the Services for personal or authorized business use.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '8. Disclaimers', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'THE SERVICES ARE PROVIDED “AS IS” AND “AS AVAILABLE.” TO THE FULLEST EXTENT PERMITTED BY LAW, WE DISCLAIM ALL WARRANTIES, EXPRESS OR IMPLIED, INCLUDING MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, AND NON-INFRINGEMENT.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '9. Limitation of liability', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'TO THE FULLEST EXTENT PERMITTED BY LAW, WE AND OUR OFFICERS, DIRECTORS, EMPLOYEES, AND SUPPLIERS WILL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, OR PUNITIVE DAMAGES ARISING FROM YOUR USE OF THE SERVICES. OUR TOTAL LIABILITY FOR ANY CLAIM WILL NOT EXCEED THE GREATER OF (A) THE AMOUNT YOU PAID US FOR THE TRANSACTION GIVING RISE TO THE CLAIM IN THE TWELVE (12) MONTHS BEFORE THE CLAIM, OR (B) ONE HUNDRED U.S. DOLLARS (US $100).', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '10. Indemnification', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'You agree to indemnify and hold harmless us from claims arising from your misuse of the Services, violation of these Terms, or violation of any law or third-party rights.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '11. Dispute resolution and governing law', 'cpm-humanblockchain' ); ?></h2>

<?php if ( '' !== $governing_state && '' !== $governing_venue ) : ?>
	<p>
		<?php
		printf(
			/* translators: 1: US state name, 2: court venue */
			esc_html__( 'These Terms are governed by the laws of the State of %1$s, United States, without regard to conflict-of-law rules, except where mandatory consumer protections in your country apply. Any dispute will be resolved in the state or federal courts located in %2$s, and you consent to personal jurisdiction there, unless applicable law requires otherwise.', 'cpm-humanblockchain' ),
			esc_html( $governing_state ),
			esc_html( $governing_venue )
		);
		?>
	</p>
<?php else : ?>
	<p><?php esc_html_e( 'These Terms are governed by the laws of the United States and the state in which our principal place of business is located, without regard to conflict-of-law rules, except where mandatory consumer protections in your country apply.', 'cpm-humanblockchain' ); ?></p>
<?php endif; ?>

<h2><?php esc_html_e( '12. Changes', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We may modify these Terms at any time by posting an updated version on this page. Continued use after the effective date constitutes acceptance of the revised Terms.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '13. Termination', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'You may stop using the Services at any time. We may suspend or terminate access for any reason, including violation of these Terms.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '14. Contact', 'cpm-humanblockchain' ); ?></h2>

<p>
	<strong><?php echo esc_html( $site_name ); ?></strong><br>
	<?php esc_html_e( 'Email:', 'cpm-humanblockchain' ); ?> <a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a><br>
	<?php esc_html_e( 'Web:', 'cpm-humanblockchain' ); ?> <a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( untrailingslashit( $home_url ) ); ?></a><br>
	<?php esc_html_e( 'Privacy:', 'cpm-humanblockchain' ); ?> <a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Privacy Policy', 'cpm-humanblockchain' ); ?></a>
</p>
