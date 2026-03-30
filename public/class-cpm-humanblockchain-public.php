<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://https://codepixelzmedia.com/
 * @since      1.0.0
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/public
 * @author     Codepixelz Media <dev@codepixelzmedia.com.np>
 */
class Cpm_Humanblockchain_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Whether to show the landing “Enter Website” gate (logged-out visitors, front page only).
	 *
	 * @return bool
	 */
	public function should_show_landing_entry_modal() {
		if ( is_user_logged_in() ) {
			return false;
		}
		$default = is_front_page();
		return (bool) apply_filters( 'cpm_hb_show_landing_entry_modal', $default );
	}

	/**
	 * Body class when the landing entry modal is present.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class_landing_entry( $classes ) {
		if ( $this->should_show_landing_entry_modal() ) {
			$classes[] = 'cpm-hb-landing-entry-active';
		}
		return $classes;
	}

	/**
	 * Output landing entry modal on the front page for guests only.
	 *
	 * @since 1.0.0
	 */
	public function render_landing_entry_modal() {
		if ( ! $this->should_show_landing_entry_modal() ) {
			return;
		}
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-landing-entry-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-role-modal.php';
	}

	/**
	 * Add "Register device NWP" button to header navigation menu.
	 *
	 * @since    1.0.0
	 * @param    string   $items  The HTML list content for the menu items.
	 * @param    stdClass $args   An object containing wp_nav_menu() arguments.
	 * @return   string          Modified menu items with NWP button appended.
	 */
	public function add_register_device_button_to_menu( $items, $args ) {
		$get_started = sprintf(
			'<li class="cpm-hb-get-started-wrap menu-item"><a href="#" class="cpm-hb-get-started-btn cpm-hb-open-membership-modal btn ghost">%1$s</a></li>',
			esc_html__( 'Get started', 'cpm-humanblockchain' )
		);

		if ( class_exists( 'Cpm_Humanblockchain_Device_Registry' ) && Cpm_Humanblockchain_Device_Registry::current_user_has_activated_device() ) {
			$label = apply_filters(
				'cpm_nwp_active_badge_text',
				__( 'You are active', 'cpm-humanblockchain' )
			);
			$button = sprintf(
				'<li class="cpm-nwp-register-btn-wrap menu-item cpm-nwp-active-item"><span class="cpm-nwp-active-badge" role="status"><span class="cpm-nwp-active-dot" aria-hidden="true"></span><span class="cpm-nwp-active-badge-text">%1$s</span></span></li>',
				esc_html( $label )
			);
		} else {
			$button = sprintf(
				'<li class="cpm-nwp-register-btn-wrap menu-item"><a href="#" class="cpm-nwp-register-btn cpm-nwp-open-modal" data-cpm-modal="cpm-nwp-register-modal">%1$s</a></li>',
				esc_html__( 'Register device NWP', 'cpm-humanblockchain' )
			);
		}
		return $items . $button . $get_started;
	}

	/**
	 * Output the device registration modal in footer.
	 * Renders only once even if wp_footer runs multiple times.
	 *
	 * @since    1.0.0
	 */
	public function render_device_registration_modal() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-nwp-device-registration-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-nwp-activate-device-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-nwp-verify-otp-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-nwp-discord-invite-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-membership-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-membership-contact-modal.php';
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		if ( $this->should_show_landing_entry_modal() ) {
			wp_enqueue_style(
				$this->plugin_name . '-landing-entry',
				plugin_dir_url( __FILE__ ) . 'css/cpm-hb-landing-entry.css',
				array(),
				$this->version,
				'all'
			);
			wp_enqueue_style(
				$this->plugin_name . '-role-modal',
				plugin_dir_url( __FILE__ ) . 'css/cpm-hb-role-modal.css',
				array( $this->plugin_name . '-landing-entry' ),
				$this->version,
				'all'
			);
		}

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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cpm-humanblockchain-public.css', array(), $this->version, 'all' );
		wp_enqueue_style(
			$this->plugin_name . '-membership-modal',
			plugin_dir_url( __FILE__ ) . 'css/cpm-hb-membership-modal.css',
			array(),
			$this->version,
			'all'
		);

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cpm-humanblockchain-public.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script(
			$this->plugin_name . '-membership-modal',
			plugin_dir_url( __FILE__ ) . 'js/cpm-hb-membership-modal.js',
			array( 'jquery', $this->plugin_name ),
			$this->version,
			true
		);

		$membership_continue = apply_filters( 'cpm_hb_membership_continue_url', home_url( '/nwp-gateway/' ) );
		$uid                 = get_current_user_id();
		$user_phone          = ( $uid && class_exists( 'Cpm_Humanblockchain_Device_Registry' ) )
			? Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $uid )
			: '';
		$user_email          = '';
		if ( $uid ) {
			$u = wp_get_current_user();
			if ( $u && $u->ID ) {
				$user_email = $u->user_email;
			}
		}
		wp_localize_script(
			$this->plugin_name . '-membership-modal',
			'cpmHbMembership',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'action'      => 'cpm_hb_membership_submit',
				'nonce'       => wp_create_nonce( 'cpm_hb_membership' ),
				'isLoggedIn'  => (bool) $uid,
				'userEmail'   => $user_email,
				'userPhone'   => $user_phone,
				'continueUrl' => esc_url_raw( $membership_continue ),
				'strings'     => array(
					'submitting'     => __( 'Submitting…', 'cpm-humanblockchain' ),
					'continue'       => __( 'Continue', 'cpm-humanblockchain' ),
					'submit'         => __( 'Submit', 'cpm-humanblockchain' ),
					'genericErr'     => __( 'Something went wrong. Please try again.', 'cpm-humanblockchain' ),
					'successNext'    => __( 'Membership updated. Continuing…', 'cpm-humanblockchain' ),
					'accountCreated' => __( 'Your account was created. Save this password:', 'cpm-humanblockchain' ),
				),
			)
		);

		if ( $this->should_show_landing_entry_modal() ) {
			wp_enqueue_script(
				$this->plugin_name . '-landing-entry',
				plugin_dir_url( __FILE__ ) . 'js/cpm-hb-landing-entry.js',
				array( 'jquery', $this->plugin_name ),
				$this->version,
				true
			);

			$proof_default = class_exists( 'Cpm_Humanblockchain_Device_Registry' )
				? Cpm_Humanblockchain_Device_Registry::get_backorder_page_url()
				: home_url( '/backorder/' );
			$proof_url     = apply_filters( 'cpm_hb_proof_of_delivery_url', $proof_default );
			$funnel_url = apply_filters( 'cpm_hb_onboarding_funnel_url', home_url( '/nwp-gateway/' ) );

			$how_default = apply_filters( 'cpm_hb_how_it_works_url', home_url( '/two-qrcode/' ) );
			$two_qr_page = get_page_by_path( 'two-qrcode' );
			if ( $two_qr_page instanceof WP_Post ) {
				$how_default = get_permalink( $two_qr_page );
			}

			$what_default = home_url( '/how-it-works/' );
			$hiw_page     = get_page_by_path( 'how-it-works' );
			if ( $hiw_page instanceof WP_Post ) {
				$what_default = get_permalink( $hiw_page );
			}
			$what_default = apply_filters( 'cpm_hb_what_is_this_url', $what_default );

			wp_localize_script(
				$this->plugin_name . '-landing-entry',
				'cpmHbLanding',
				array(
					'homeUrl'             => home_url( '/' ),
					'proofOfDeliveryUrl'  => esc_url_raw( $proof_url ),
					'onboardingFunnelUrl' => esc_url_raw( $funnel_url ),
					'whatIsThisUrl'       => esc_url_raw( $what_default ),
					'howItWorksUrl'       => esc_url_raw( $how_default ),
					'answerBothPrompts'   => __( 'Please answer both prompts (Proof of Delivery and Final Destination).', 'cpm-humanblockchain' ),
				)
			);
		}

		$discord_invite = apply_filters(
			'cpm_nwp_discord_invite_url',
			get_option( 'cpm_nwp_discord_invite_url', 'https://discord.com/invite/g5jreAPbra' )
		);

		wp_localize_script( $this->plugin_name, 'cpmNwp', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'action'        => 'cpm_nwp_register_device',
			'sendOtpAction' => 'cpm_nwp_send_otp',
			'verifyOtpAction' => 'cpm_nwp_verify_otp',
			'homeUrl'       => home_url( '/' ),
			'discordInviteUrl' => esc_url_raw( $discord_invite ),
		) );
	}

}
