<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://codepixelzmedia.com/
 * @since      1.0.0
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/admin
 * @author     Codepixelz Media <dev@codepixelzmedia.com.np>
 */
class Cpm_Humanblockchain_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register admin menu (NWP Gateway).
	 *
	 * @since 1.0.0
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'NWP / HumanBlockchain', 'cpm-humanblockchain' ),
			__( 'NWP Gateway', 'cpm-humanblockchain' ),
			'manage_options',
			'cpm-nwp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register NWP Gateway (Twilio OTP) settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_twilio_sid', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_twilio_token', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_twilio_token' ),
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_twilio_from', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_twilio_verify_service_sid', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_default_country', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_default_country' ),
		) );
	}

	/**
	 * Sanitize Twilio Auth Token; empty input keeps the previously saved key.
	 * Password fields often submit blank on “Save” even when the admin did not intend to change the token,
	 * which would otherwise wipe the option and break OTP on this site (while Twilio still works elsewhere).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_twilio_token( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			$prev = get_option( 'cpm_nwp_twilio_token', '' );
			return is_string( $prev ) ? $prev : '';
		}
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize default country option (NP or US).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_default_country( $value ) {
		$value = is_string( $value ) ? strtoupper( trim( $value ) ) : '';
		return in_array( $value, array( 'NP', 'US', 'AUTO' ), true ) ? $value : 'AUTO';
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$sid              = get_option( 'cpm_nwp_twilio_sid', '' );
		$token            = get_option( 'cpm_nwp_twilio_token', '' );
		$from             = get_option( 'cpm_nwp_twilio_from', '' );
		$verify_service   = get_option( 'cpm_nwp_twilio_verify_service_sid', '' );
		$country          = get_option( 'cpm_nwp_default_country', 'AUTO' );

		$using_constants = defined( 'CPM_NWP_TWILIO_SID' ) || defined( 'CPM_NWP_TWILIO_TOKEN' ) || defined( 'CPM_NWP_TWILIO_FROM' )
			|| defined( 'CPM_TWILIO_ACCOUNT_SID' ) || defined( 'CPM_TWILIO_AUTH_TOKEN' ) || defined( 'CPM_TWILIO_FROM' )
			|| defined( 'CPM_TWILIO_VERIFY_SERVICE_SID' ) || defined( 'CPM_NWP_TWILIO_VERIFY_SERVICE_SID' );
		$twilio_ready      = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) && Cpm_Humanblockchain_Otp_Service::is_configured();
		$uses_verify_api   = $twilio_ready && class_exists( 'Cpm_Humanblockchain_Otp_Service' ) && Cpm_Humanblockchain_Otp_Service::uses_twilio_verify();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NWP Gateway Settings', 'cpm-humanblockchain' ); ?></h1>
			<?php settings_errors(); ?>

			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Twilio status:', 'cpm-humanblockchain' ); ?></strong>
					<?php if ( $twilio_ready ) : ?>
						<span style="color:#00a32a;"><?php esc_html_e( 'Ready — OTP SMS can be sent.', 'cpm-humanblockchain' ); ?></span>
						<?php if ( $uses_verify_api ) : ?>
							<?php esc_html_e( '(Twilio Verify — no “From” number needed.)', 'cpm-humanblockchain' ); ?>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#d63638;"><?php esc_html_e( 'Not configured — add Account SID and Auth Token, then either a Twilio Verify Service SID (VA…) or a From number for the Messages API.', 'cpm-humanblockchain' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $using_constants ) : ?>
					<p><?php esc_html_e( 'Some values may be loaded from wp-config.php (e.g. CPM_NWP_TWILIO_* or CPM_TWILIO_VERIFY_SERVICE_SID). Matching fields below can be left blank.', 'cpm-humanblockchain' ); ?></p>
				<?php endif; ?>
				<p>
					<a href="https://console.twilio.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Twilio Console', 'cpm-humanblockchain' ); ?></a>
					&mdash;
					<a href="https://www.twilio.com/docs/sms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Twilio SMS docs', 'cpm-humanblockchain' ); ?></a>
				</p>
				<p class="description">
					<?php esc_html_e( 'Sending to Nepal (+977) or other countries: in Twilio, enable outbound SMS for that country under Messaging → Settings → SMS geographic permissions. Otherwise Twilio returns a “permission … region” error.', 'cpm-humanblockchain' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'If Twilio message logs show error 30006 (landline or unreachable carrier), the destination cannot receive SMS: use a real mobile number. Ten-digit numbers starting with 97/98 are sent as Nepal (+977) before +1. Prefer Automatic or Nepal in Default country, or type +977… explicitly.', 'cpm-humanblockchain' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'If you use Twilio Verify, set the Verify Service SID (VA…) in the field below or define CPM_TWILIO_VERIFY_SERVICE_SID in wp-config.php.', 'cpm-humanblockchain' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'cpm_nwp_twilio' ); ?>
				<h2><?php esc_html_e( 'Twilio SMS (for OTP)', 'cpm-humanblockchain' ); ?></h2>
				<p><?php esc_html_e( 'Used when a user opens Activate device → Send OTP. The phone must already exist in wp_nwp_devices.', 'cpm-humanblockchain' ); ?></p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="cpm_nwp_default_country"><?php esc_html_e( 'Default country (10-digit numbers)', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<select id="cpm_nwp_default_country" name="cpm_nwp_default_country">
								<option value="AUTO" <?php selected( $country, 'AUTO' ); ?>><?php esc_html_e( 'Automatic — 97/98… as Nepal (+977), other 10 digits as +1', 'cpm-humanblockchain' ); ?></option>
								<option value="NP" <?php selected( $country, 'NP' ); ?>><?php esc_html_e( 'Nepal (+977) — 10 digits as Nepali mobile', 'cpm-humanblockchain' ); ?></option>
								<option value="US" <?php selected( $country, 'US' ); ?>><?php esc_html_e( 'United States / Canada (+1) for generic 10-digit (97/98 still go to +977 first)', 'cpm-humanblockchain' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Server always maps 10-digit numbers starting with 97 or 98 to +977 (Nepal mobile) before applying +1, unless a developer disables this with the cpm_nwp_ten_digit_97_98_as_nepal filter. “Automatic” uses +1 only for other 10-digit numbers. For unambiguous input, use full E.164 (+977… or +1…).', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_sid"><?php esc_html_e( 'Account SID', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_sid" name="cpm_nwp_twilio_sid" value="<?php echo esc_attr( $sid ); ?>" class="regular-text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_token"><?php esc_html_e( 'Auth Token', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<input type="password" id="cpm_nwp_twilio_token" name="cpm_nwp_twilio_token" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo esc_attr( $token !== '' ? __( 'Leave blank to keep saved token', 'cpm-humanblockchain' ) : __( 'Paste Auth Token from Twilio Console', 'cpm-humanblockchain' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Must match the Auth Token in Twilio Console. Leave blank when saving other settings to keep the current token.', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_verify_service_sid"><?php esc_html_e( 'Verify Service SID (optional)', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<?php if ( defined( 'CPM_TWILIO_VERIFY_SERVICE_SID' ) && is_string( CPM_TWILIO_VERIFY_SERVICE_SID ) && CPM_TWILIO_VERIFY_SERVICE_SID !== '' ) : ?>
								<code><?php echo esc_html( CPM_TWILIO_VERIFY_SERVICE_SID ); ?></code>
								<p class="description"><?php esc_html_e( 'Loaded from wp-config.php (CPM_TWILIO_VERIFY_SERVICE_SID). Remove or override the constant to use the field below instead.', 'cpm-humanblockchain' ); ?></p>
							<?php else : ?>
								<input type="text" id="cpm_nwp_twilio_verify_service_sid" name="cpm_nwp_twilio_verify_service_sid" value="<?php echo esc_attr( $verify_service ); ?>" class="regular-text" placeholder="VAxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
								<p class="description"><?php esc_html_e( 'When set, OTP can use the Twilio Verify API and you do not need a “From” number. Leave empty to use the classic Messages API + From number instead.', 'cpm-humanblockchain' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_from"><?php esc_html_e( 'From (Twilio number)', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_from" name="cpm_nwp_twilio_from" value="<?php echo esc_attr( $from ); ?>" class="regular-text" placeholder="+15551234567">
						<p class="description"><?php esc_html_e( 'Required only for the Messages API (when Verify Service SID is not set). E.164 format, e.g. +15551234567', 'cpm-humanblockchain' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cpm_Humanblockchain_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cpm_Humanblockchain_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cpm-humanblockchain-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cpm_Humanblockchain_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cpm_Humanblockchain_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cpm-humanblockchain-admin.js', array( 'jquery' ), $this->version, false );

	}

}
