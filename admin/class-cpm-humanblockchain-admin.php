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
	 * Register admin menu and Twilio settings.
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
	 * Register Twilio settings.
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
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_twilio_from', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'cpm_nwp_twilio', 'cpm_nwp_default_country', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_default_country' ),
		) );

		register_setting( 'cpm_hb_membership', 'cpm_hb_membership_api_endpoint', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_membership_api_endpoint' ),
		) );
		register_setting( 'cpm_hb_membership', 'smallstreet_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_membership_api_key' ),
		) );
		register_setting( 'cpm_hb_membership', 'cpm_hb_register_user_api_endpoint', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_register_user_api_endpoint' ),
		) );
		register_setting( 'cpm_hb_membership', 'cpm_hb_register_user_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_register_user_api_key' ),
		) );
		register_setting( 'cpm_hb_membership', 'cpm_hb_smallstreet_backorders_url', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_smallstreet_backorders_url' ),
		) );
		register_setting( 'cpm_hb_membership', 'cpm_hb_smallstreet_backorders_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_smallstreet_backorders_api_key' ),
		) );
	}

	/**
	 * Sanitize membership API URL (https). Empty = use default route on this site.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_membership_api_endpoint( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		$prev  = get_option( 'cpm_hb_membership_api_endpoint', '' );
		if ( $value === '' ) {
			return '';
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'cpm_hb_membership',
				'bad_endpoint',
				__( 'Membership API URL must be a valid http(s) URL.', 'cpm-humanblockchain' )
			);
			return is_string( $prev ) ? $prev : '';
		}
		$parsed = wp_parse_url( $value );
		if ( empty( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			add_settings_error(
				'cpm_hb_membership',
				'bad_endpoint_scheme',
				__( 'Membership API URL must start with http:// or https://.', 'cpm-humanblockchain' )
			);
			return is_string( $prev ) ? $prev : '';
		}
		return esc_url_raw( $value );
	}

	/**
	 * Sanitize API key; empty input keeps the previously saved key.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_membership_api_key( $value ) {
		if ( isset( $_POST['cpm_hb_membership_clear_key'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_membership_clear_key'] ) ) ) {
			return '';
		}
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			$prev = get_option( 'smallstreet_api_key', '' );
			return is_string( $prev ) ? $prev : '';
		}
		return $value;
	}

	/**
	 * Sanitize Register User API URL. Empty = use default route on this site.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_register_user_api_endpoint( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		$prev  = get_option( 'cpm_hb_register_user_api_endpoint', '' );
		if ( $value === '' ) {
			return '';
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'cpm_hb_membership',
				'bad_register_user_endpoint',
				__( 'Register User API URL must be a valid http(s) URL.', 'cpm-humanblockchain' )
			);
			return is_string( $prev ) ? $prev : '';
		}
		$parsed = wp_parse_url( $value );
		if ( empty( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), array( 'http', 'https' ), true ) ) {
			add_settings_error(
				'cpm_hb_membership',
				'bad_register_user_endpoint_scheme',
				__( 'Register User API URL must start with http:// or https://.', 'cpm-humanblockchain' )
			);
			return is_string( $prev ) ? $prev : '';
		}
		return esc_url_raw( $value );
	}

	/**
	 * Optional Register User Bearer key; empty keeps previous; clear checkbox empties option.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_register_user_api_key( $value ) {
		if ( isset( $_POST['cpm_hb_register_user_clear_key'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_register_user_clear_key'] ) ) ) {
			return '';
		}
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			$prev = get_option( 'cpm_hb_register_user_api_key', '' );
			return is_string( $prev ) ? $prev : '';
		}
		return $value;
	}

	/**
	 * Smallstreet backorders-by-mobile URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_smallstreet_backorders_url( $value ) {
		$default = 'https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/backorders-by-mobile';
		$value   = is_string( $value ) ? trim( $value ) : '';
		$prev    = get_option( 'cpm_hb_smallstreet_backorders_url', $default );
		if ( $value === '' ) {
			return is_string( $prev ) && $prev !== '' ? $prev : $default;
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			add_settings_error(
				'cpm_hb_membership',
				'bad_smallstreet_backorders_url',
				__( 'Smallstreet backorders URL must be a valid http(s) URL.', 'cpm-humanblockchain' )
			);
			return is_string( $prev ) ? $prev : $default;
		}
		return esc_url_raw( $value );
	}

	/**
	 * API key for backorders-by-mobile (X-Dongtrader-Backorders-Key); empty keeps previous; clear checkbox removes.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_smallstreet_backorders_api_key( $value ) {
		if ( isset( $_POST['cpm_hb_smallstreet_backorders_clear_key'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['cpm_hb_smallstreet_backorders_clear_key'] ) ) ) {
			return '';
		}
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			$prev = get_option( 'cpm_hb_smallstreet_backorders_api_key', '' );
			return is_string( $prev ) ? $prev : '';
		}
		return $value;
	}

	/**
	 * Sanitize default country option (NP or US).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_default_country( $value ) {
		$value = is_string( $value ) ? strtoupper( trim( $value ) ) : '';
		return in_array( $value, array( 'NP', 'US' ), true ) ? $value : 'NP';
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
		$sid      = get_option( 'cpm_nwp_twilio_sid', '' );
		$token    = get_option( 'cpm_nwp_twilio_token', '' );
		$from     = get_option( 'cpm_nwp_twilio_from', '' );
		$country  = get_option( 'cpm_nwp_default_country', 'NP' );

		$using_constants = defined( 'CPM_NWP_TWILIO_SID' ) && defined( 'CPM_NWP_TWILIO_TOKEN' ) && defined( 'CPM_NWP_TWILIO_FROM' );
		$twilio_ready    = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) && Cpm_Humanblockchain_Otp_Service::is_configured();

		$default_membership_url   = rest_url( 'myapi/v1/membership' );
		$default_register_user_url = rest_url( 'myapi/v1/register-user' );
		$membership_endpoint      = get_option( 'cpm_hb_membership_api_endpoint', '' );
		$register_user_endpoint   = get_option( 'cpm_hb_register_user_api_endpoint', '' );
		$membership_key_set       = (bool) strlen( (string) get_option( 'smallstreet_api_key', '' ) );
		$register_user_key_set    = (bool) strlen( (string) get_option( 'cpm_hb_register_user_api_key', '' ) );
		$smallstreet_backorders_url = get_option( 'cpm_hb_smallstreet_backorders_url', 'https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/backorders-by-mobile' );
		$smallstreet_bo_key_set     = class_exists( 'Cpm_Humanblockchain_Smallstreet_Backorders' ) && Cpm_Humanblockchain_Smallstreet_Backorders::is_configured();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NWP Gateway Settings', 'cpm-humanblockchain' ); ?></h1>
			<?php settings_errors(); ?>

			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Twilio status:', 'cpm-humanblockchain' ); ?></strong>
					<?php if ( $twilio_ready ) : ?>
						<span style="color:#00a32a;"><?php esc_html_e( 'Ready — OTP SMS can be sent.', 'cpm-humanblockchain' ); ?></span>
					<?php else : ?>
						<span style="color:#d63638;"><?php esc_html_e( 'Not configured — add Account SID, Auth Token, and From number below (or define constants in wp-config.php).', 'cpm-humanblockchain' ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( $using_constants ) : ?>
					<p><?php esc_html_e( 'Credentials are loaded from wp-config.php constants (CPM_NWP_TWILIO_*). Fields below may be ignored.', 'cpm-humanblockchain' ); ?></p>
				<?php endif; ?>
				<p>
					<a href="https://console.twilio.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Twilio Console', 'cpm-humanblockchain' ); ?></a>
					&mdash;
					<a href="https://www.twilio.com/docs/sms" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Twilio SMS docs', 'cpm-humanblockchain' ); ?></a>
				</p>
				<p class="description">
					<?php esc_html_e( 'Sending to Nepal (+977) or other countries: in Twilio, enable outbound SMS for that country under Messaging → Settings → SMS geographic permissions. Otherwise Twilio returns a “permission … region” error.', 'cpm-humanblockchain' ); ?>
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
								<option value="NP" <?php selected( $country, 'NP' ); ?>><?php esc_html_e( 'Nepal (+977) — mobile numbers starting with 97/98', 'cpm-humanblockchain' ); ?></option>
								<option value="US" <?php selected( $country, 'US' ); ?>><?php esc_html_e( 'United States / Canada (+1)', 'cpm-humanblockchain' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'If the user types 10 digits without a country code, this decides whether they are interpreted as Nepal or US. Prefer full international format (+977…) in forms to avoid ambiguity.', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_sid"><?php esc_html_e( 'Account SID', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_sid" name="cpm_nwp_twilio_sid" value="<?php echo esc_attr( $sid ); ?>" class="regular-text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_token"><?php esc_html_e( 'Auth Token', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="password" id="cpm_nwp_twilio_token" name="cpm_nwp_twilio_token" value="<?php echo esc_attr( $token ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th><label for="cpm_nwp_twilio_from"><?php esc_html_e( 'From (Twilio number)', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_from" name="cpm_nwp_twilio_from" value="<?php echo esc_attr( $from ); ?>" class="regular-text" placeholder="+15551234567">
						<p class="description"><?php esc_html_e( 'E.164 format, e.g. +15551234567', 'cpm-humanblockchain' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr style="margin: 32px 0;" />

			<h2><?php esc_html_e( 'Membership & Register User APIs', 'cpm-humanblockchain' ); ?></h2>
			<p>
				<?php esc_html_e( 'Bearer-authenticated myapi routes. Values are stored server-side only.', 'cpm-humanblockchain' ); ?>
			</p>
			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Status:', 'cpm-humanblockchain' ); ?></strong>
					<?php if ( $membership_key_set ) : ?>
						<span style="color:#00a32a;"><?php esc_html_e( 'API key is saved.', 'cpm-humanblockchain' ); ?></span>
					<?php else : ?>
						<span style="color:#d63638;"><?php esc_html_e( 'No API key — add the Bearer token below.', 'cpm-humanblockchain' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Default membership URL if empty:', 'cpm-humanblockchain' ); ?>
					<code><?php echo esc_html( $default_membership_url ); ?></code>
				</p>
				<p class="description">
					<?php esc_html_e( 'Default register-user URL if empty:', 'cpm-humanblockchain' ); ?>
					<code><?php echo esc_html( $default_register_user_url ); ?></code>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'cpm_hb_membership' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cpm_hb_membership_api_endpoint"><?php esc_html_e( 'API endpoint URL', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="cpm_hb_membership_api_endpoint"
								name="cpm_hb_membership_api_endpoint"
								value="<?php echo esc_attr( is_string( $membership_endpoint ) ? $membership_endpoint : '' ); ?>"
								class="large-text code"
								placeholder="<?php echo esc_attr( $default_membership_url ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Full URL for POST JSON (e.g. https://yoursite.com/wp-json/myapi/v1/membership). Leave empty to use this site’s default route.', 'cpm-humanblockchain' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="smallstreet_api_key"><?php esc_html_e( 'API key (Bearer)', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="smallstreet_api_key"
								name="smallstreet_api_key"
								value=""
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php if ( $membership_key_set ) : ?>
									<?php esc_html_e( 'Leave blank to keep the current key. Enter a new value to replace it.', 'cpm-humanblockchain' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Must match the WordPress option the REST route checks (same as get_option( \'smallstreet_api_key\' )).', 'cpm-humanblockchain' ); ?>
								<?php endif; ?>
							</p>
							<p style="margin-top:10px;">
								<label>
									<input type="checkbox" name="cpm_hb_membership_clear_key" value="1" />
									<?php esc_html_e( 'Clear saved API key', 'cpm-humanblockchain' ); ?>
								</label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cpm_hb_register_user_api_endpoint"><?php esc_html_e( 'Register User — API endpoint URL', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="cpm_hb_register_user_api_endpoint"
								name="cpm_hb_register_user_api_endpoint"
								value="<?php echo esc_attr( is_string( $register_user_endpoint ) ? $register_user_endpoint : '' ); ?>"
								class="large-text code"
								placeholder="<?php echo esc_attr( $default_register_user_url ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'POST JSON to this URL (e.g. https://yoursite.com/wp-json/myapi/v1/register-user). Leave empty to use this site’s default route.', 'cpm-humanblockchain' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cpm_hb_register_user_api_key"><?php esc_html_e( 'Register User — API key (optional)', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="cpm_hb_register_user_api_key"
								name="cpm_hb_register_user_api_key"
								value=""
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php if ( $register_user_key_set ) : ?>
									<?php esc_html_e( 'A dedicated Bearer token for register-user only. Leave blank to keep the current value.', 'cpm-humanblockchain' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Leave empty to use the same key as “API key (Bearer)” above (smallstreet_api_key). Set a value only if register-user must use a different secret.', 'cpm-humanblockchain' ); ?>
								<?php endif; ?>
							</p>
							<p style="margin-top:10px;">
								<label>
									<input type="checkbox" name="cpm_hb_register_user_clear_key" value="1" />
									<?php esc_html_e( 'Clear dedicated Register User key (then the shared Membership API key is used)', 'cpm-humanblockchain' ); ?>
								</label>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cpm_hb_smallstreet_backorders_url"><?php esc_html_e( 'Smallstreet — backorders by mobile (URL)', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="cpm_hb_smallstreet_backorders_url"
								name="cpm_hb_smallstreet_backorders_url"
								value="<?php echo esc_attr( is_string( $smallstreet_backorders_url ) ? $smallstreet_backorders_url : '' ); ?>"
								class="large-text code"
								placeholder="https://www.smallstreet.app/wp-json/cpm-dongtrader/v1/backorders-by-mobile"
							/>
							<p class="description">
								<?php esc_html_e( 'POST JSON { "mobile": "5551234567" } with header X-Dongtrader-Backorders-Key. Used for buyer + proof=scan PoD flow (local + Smallstreet check, then OTP).', 'cpm-humanblockchain' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cpm_hb_smallstreet_backorders_api_key"><?php esc_html_e( 'Smallstreet — backorders API key (X-Dongtrader-Backorders-Key)', 'cpm-humanblockchain' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="cpm_hb_smallstreet_backorders_api_key"
								name="cpm_hb_smallstreet_backorders_api_key"
								value=""
								class="regular-text"
								autocomplete="new-password"
							/>
							<p class="description">
								<?php if ( $smallstreet_bo_key_set ) : ?>
									<span style="color:#00a32a;"><?php esc_html_e( 'Key is set.', 'cpm-humanblockchain' ); ?></span>
									<?php esc_html_e( 'Leave blank to keep the current key.', 'cpm-humanblockchain' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'A default key may apply until you save a custom value.', 'cpm-humanblockchain' ); ?>
								<?php endif; ?>
							</p>
							<p style="margin-top:10px;">
								<label>
									<input type="checkbox" name="cpm_hb_smallstreet_backorders_clear_key" value="1" />
									<?php esc_html_e( 'Clear Smallstreet backorders API key (disables buyer PoD Smallstreet checks until a new key is saved)', 'cpm-humanblockchain' ); ?>
								</label>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save membership settings', 'cpm-humanblockchain' ) ); ?>
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
