<?php
/**
 * Privacy Policy body (included by Cpm_Hb_Legal_Pages).
 *
 * @var array<string, string> $hb_legal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name      = isset( $hb_legal['site_name'] ) ? $hb_legal['site_name'] : '';
$home_url       = isset( $hb_legal['home_url'] ) ? $hb_legal['home_url'] : '';
$terms_url      = isset( $hb_legal['terms_url'] ) ? $hb_legal['terms_url'] : '';
$support_email  = isset( $hb_legal['support_email'] ) ? $hb_legal['support_email'] : '';
$effective_date = isset( $hb_legal['effective_date'] ) ? $hb_legal['effective_date'] : '';
?>
<h1><?php esc_html_e( 'Privacy Policy', 'cpm-humanblockchain' ); ?></h1>

<p><strong><?php esc_html_e( 'Effective date:', 'cpm-humanblockchain' ); ?></strong> <?php echo esc_html( $effective_date ); ?></p>

<p>
	<?php
	printf(
		/* translators: 1: site name, 2: home URL */
		wp_kses(
			__( 'This Privacy Policy describes how <strong>%1$s</strong> (“we,” “us,” or “our”), operating at <a href="%2$s">%2$s</a> (the “Site”), collects, uses, and protects information when you use our website, marketplace, device registration, and proof-of-delivery services (collectively, the “Services”).', 'cpm-humanblockchain' ),
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

<p><?php esc_html_e( 'By using the Site or providing information to us, you agree to this Privacy Policy. If you do not agree, please do not use the Services.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '1. Who we are', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We provide community commerce tools, including the NWP Processing Center, WooCommerce marketplace features, device activation, and two-scan proof-of-delivery verification. For privacy-related questions, contact us at:', 'cpm-humanblockchain' ); ?></p>

<ul>
	<li><strong><?php esc_html_e( 'Email:', 'cpm-humanblockchain' ); ?></strong> <a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a></li>
	<li><strong><?php esc_html_e( 'Website:', 'cpm-humanblockchain' ); ?></strong> <a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( untrailingslashit( $home_url ) ); ?></a></li>
</ul>

<h2><?php esc_html_e( '2. Information we collect', 'cpm-humanblockchain' ); ?></h2>

<h3><?php esc_html_e( '2.1 Information you provide', 'cpm-humanblockchain' ); ?></h3>

<ul>
	<li><strong><?php esc_html_e( 'Account information:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'name, email address, username, password, and profile details when you register or update your account.', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'Phone number:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'mobile number when you request a one-time verification code (OTP) for device activation, seller/buyer scan verification, or related NWP flows.', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'Order and transaction data:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'billing/shipping details, order history, payment status, and delivery-related records processed through WooCommerce.', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'Device and proof-of-delivery data:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'device identifiers, scan timestamps, and—when you grant permission—approximate location data used to validate two-scan proof-of-delivery.', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'Communications:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'messages you send to support or through forms on the Site.', 'cpm-humanblockchain' ); ?></li>
</ul>

<h3><?php esc_html_e( '2.2 Information collected automatically', 'cpm-humanblockchain' ); ?></h3>

<ul>
	<li><strong><?php esc_html_e( 'Log and usage data:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'IP address, browser type, pages visited, referring URLs, and timestamps.', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'Cookies and similar technologies:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'session cookies, security cookies, and analytics cookies (see Section 8).', 'cpm-humanblockchain' ); ?></li>
</ul>

<h3><?php esc_html_e( '2.3 Information from third parties', 'cpm-humanblockchain' ); ?></h3>

<ul>
	<li><strong><?php esc_html_e( 'Payment processors:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'payment confirmation and limited billing details (we do not store full card numbers on our servers).', 'cpm-humanblockchain' ); ?></li>
	<li><strong><?php esc_html_e( 'SMS delivery providers:', 'cpm-humanblockchain' ); ?></strong> <?php esc_html_e( 'delivery status for verification messages sent to your phone number.', 'cpm-humanblockchain' ); ?></li>
</ul>

<h2><?php esc_html_e( '3. How we use your information', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We use personal information to:', 'cpm-humanblockchain' ); ?></p>

<ul>
	<li><?php esc_html_e( 'Create and manage your account and authenticate you.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Process orders, payments, refunds, and delivery-wallet credits where applicable.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Send one-time verification codes (OTP) by SMS when you request them on the Site.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Operate two-scan proof-of-delivery, device registry, and related ledger/audit features.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Prevent fraud, enforce our Terms, and protect the security of the Site.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Respond to support requests and send service-related notices (not marketing SMS unless you separately opt in).', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Improve the Site and comply with legal obligations.', 'cpm-humanblockchain' ); ?></li>
</ul>

<h2><?php esc_html_e( '4. SMS and phone verification (important)', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'When you enter your mobile phone number on our Site and click to send a verification code, you are requesting a transactional, one-time password (OTP) message. We use your number only for purposes related to that request, such as:', 'cpm-humanblockchain' ); ?></p>

<ul>
	<li><?php esc_html_e( 'Device activation and account verification', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Seller or buyer scan verification in the two-scan proof-of-delivery flow', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Other security or authentication steps clearly shown on the page where you submit your number', 'cpm-humanblockchain' ); ?></li>
</ul>

<p><strong><?php esc_html_e( 'We do not sell your phone number to third parties for their marketing.', 'cpm-humanblockchain' ); ?></strong></p>

<p>
	<?php
	printf(
		/* translators: %s: terms page URL */
		wp_kses(
			__( 'We do <strong>not</strong> use the SMS verification program described in our <a href="%s">Terms and Conditions</a> to send promotional or marketing text messages. Message frequency is limited to messages you trigger by requesting a code (typically one message per request). <strong>Message and data rates may apply.</strong> Carriers are not liable for delayed or undelivered messages.', 'cpm-humanblockchain' ),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		),
		esc_url( $terms_url )
	);
	?>
</p>

<p><?php esc_html_e( 'SMS messages may be delivered through our messaging provider (e.g., Twilio). That provider processes your phone number and message content only to deliver the message on our behalf.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '5. How we share information', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We may share information with:', 'cpm-humanblockchain' ); ?></p>

<ul>
	<li><?php esc_html_e( 'Service providers who help us operate the Site (hosting, email, SMS/OTP delivery, payment processing, security).', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Business partners only as needed to fulfill orders or services you use, under contractual confidentiality obligations.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Legal authorities when required by law or to protect rights, safety, and security.', 'cpm-humanblockchain' ); ?></li>
	<li><?php esc_html_e( 'Successors in connection with a merger, acquisition, or sale of assets, with notice where required by law.', 'cpm-humanblockchain' ); ?></li>
</ul>

<p><?php esc_html_e( 'We do not sell personal information for money. We do not share phone numbers for unrelated third-party advertising.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '6. Data retention', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We retain information for as long as needed to provide the Services, comply with legal obligations, resolve disputes, and enforce agreements. Verification logs and delivery-related records may be kept for audit and fraud-prevention purposes according to our internal retention schedule.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '7. Security', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We use reasonable administrative, technical, and organizational measures to protect personal information. No method of transmission or storage is 100% secure; we cannot guarantee absolute security.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '8. Cookies', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We use cookies and similar technologies for login sessions, security, preferences, and analytics. You can control cookies through your browser settings. Disabling cookies may limit some Site features.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '9. Your choices and rights', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'Depending on where you live, you may have the right to access, correct, or delete certain personal information; object to or restrict certain processing; or withdraw consent where processing is based on consent.', 'cpm-humanblockchain' ); ?></p>

<p>
	<?php
	printf(
		/* translators: %s: support email */
		wp_kses(
			__( 'For SMS verification messages, you may stop further messages by replying <strong>STOP</strong> to a message from our program, as described in our Terms. You can also contact <a href="mailto:%s">%s</a>.', 'cpm-humanblockchain' ),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		),
		esc_attr( $support_email ),
		esc_html( $support_email )
	);
	?>
</p>

<p><?php esc_html_e( 'California residents may have additional rights under the CCPA/CPRA. Contact us to submit a verifiable request. We will not discriminate against you for exercising privacy rights.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '10. Children', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'The Services are not directed to children under 13 (or 16 where applicable). We do not knowingly collect personal information from children. Contact us if you believe we have done so.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '11. International users', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'If you access the Site from outside the United States, your information may be processed in the United States or other countries where our service providers operate. By using the Site, you consent to that transfer where permitted by law.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '12. Third-party links', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'The Site may link to third-party websites or services. We are not responsible for their privacy practices. Review their policies before providing information.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '13. Changes to this policy', 'cpm-humanblockchain' ); ?></h2>

<p><?php esc_html_e( 'We may update this Privacy Policy from time to time. We will post the revised version on this page and update the effective date. Continued use of the Site after changes means you accept the updated policy.', 'cpm-humanblockchain' ); ?></p>

<h2><?php esc_html_e( '14. Contact us', 'cpm-humanblockchain' ); ?></h2>

<p>
	<strong><?php echo esc_html( $site_name ); ?></strong><br>
	<?php esc_html_e( 'Email:', 'cpm-humanblockchain' ); ?> <a href="mailto:<?php echo esc_attr( $support_email ); ?>"><?php echo esc_html( $support_email ); ?></a><br>
	<?php esc_html_e( 'Web:', 'cpm-humanblockchain' ); ?> <a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( untrailingslashit( $home_url ) ); ?></a>
</p>
