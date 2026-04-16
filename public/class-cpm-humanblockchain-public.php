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
	 * Register public shortcodes (backorders list mount point).
	 *
	 * @since 1.0.0
	 */
	public function register_shortcodes() {
		add_shortcode( 'cpm_hb_backorders', array( $this, 'shortcode_backorders' ) );
	}

	/**
	 * Tracks whether #cpm-hb-backorders-root was already printed (shortcode, the_content, or footer).
	 *
	 * @var bool
	 */
	private $backorders_mount_printed = false;

	/**
	 * Outputs a container the backorders script fills (sessionStorage + server initialRows).
	 * Place in the page editor as [cpm_hb_backorders] or in a template: echo do_shortcode( '[cpm_hb_backorders]' );
	 *
	 * @param array<string, string> $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function shortcode_backorders( $atts ) {
		$this->backorders_mount_printed = true;
		return '<div id="cpm-hb-backorders-root" class="cpm-hb-backorders-root"></div>';
	}

	/**
	 * Append the backorders mount to the Backorders page content if the theme outputs the_content() and nothing added yet.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_backorders_to_page_content( $content ) {
		if ( is_admin() || ! is_singular() ) {
			return $content;
		}
		if ( ! class_exists( 'Cpm_Humanblockchain_Device_Registry' ) || ! Cpm_Humanblockchain_Device_Registry::is_backorder_page_view() ) {
			return $content;
		}
		// Only augment the main post body. Sidebars/widgets often run `the_content` first with the same
		// queried page still "singular"; a global "append once" flag would attach the shortcode there and leave the main column empty.
		if ( ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( false !== strpos( $content, 'cpm-hb-backorders-root' ) ) {
			$this->backorders_mount_printed = true;
			return $content;
		}
		$this->backorders_mount_printed = true;
		return $content . "\n\n" . do_shortcode( '[cpm_hb_backorders]' );
	}

	/**
	 * If the theme never called the_content() (custom template), print the mount once before </body>.
	 */
	public function maybe_print_backorders_mount_footer() {
		if ( ! $this->should_enqueue_backorders_assets() ) {
			return;
		}
		if ( ! class_exists( 'Cpm_Humanblockchain_Device_Registry' ) || ! Cpm_Humanblockchain_Device_Registry::is_backorder_page_view() ) {
			return;
		}
		if ( $this->backorders_mount_printed ) {
			return;
		}
		$this->backorders_mount_printed = true;
		echo '<div id="cpm-hb-backorders-root" class="cpm-hb-backorders-root cpm-hb-backorders-root--footer"></div>';
	}

	/**
	 * Load backorders CSS/JS when viewing the backorder page, a page with the shortcode, or a backorder* page template.
	 *
	 * @return bool
	 */
	private function should_enqueue_backorders_assets() {
		if ( ! class_exists( 'Cpm_Humanblockchain_Device_Registry' ) ) {
			return false;
		}
		if ( Cpm_Humanblockchain_Device_Registry::is_backorder_page_view() ) {
			return true;
		}
		if ( ! is_singular() ) {
			return (bool) apply_filters( 'cpm_hb_enqueue_backorders_assets', false, null );
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return (bool) apply_filters( 'cpm_hb_enqueue_backorders_assets', false, null );
		}
		if ( has_shortcode( (string) $post->post_content, 'cpm_hb_backorders' ) ) {
			return true;
		}
		$tpl = get_page_template_slug( $post->ID );
		if ( is_string( $tpl ) && $tpl !== '' && false !== stripos( $tpl, 'backorder' ) ) {
			return true;
		}
		return (bool) apply_filters( 'cpm_hb_enqueue_backorders_assets', false, $post );
	}

	/**
	 * Whether the current request URL includes ?proof=scan (buyer PoD / backorders flow).
	 *
	 * @return bool
	 */
	private function request_has_proof_scan_param() {
		return isset( $_GET['proof'] ) && 'scan' === sanitize_text_field( wp_unslash( $_GET['proof'] ) );
	}

	/**
	 * Cookie set in JS when the user dismisses or completes the ?proof=scan landing gate (avoids showing it on every refresh).
	 *
	 * @return bool
	 */
	private function proof_scan_landing_already_acknowledged() {
		if ( ! isset( $_COOKIE['cpm_hb_proof_scan_landing_seen'] ) ) {
			return false;
		}
		return '1' === sanitize_text_field( wp_unslash( $_COOKIE['cpm_hb_proof_scan_landing_seen'] ) );
	}

	/**
	 * Whether to show the landing “Enter Website” gate.
	 *
	 * - Logged-out: default is front page only; also any URL with ?proof=scan.
	 * - Logged-in: only when ?proof=scan is present (same PoD prompts).
	 * - ?cpm_hb_skip_gate=1 hides the gate for guests unless ?proof=scan is present.
	 *
	 * @return bool
	 */
	public function should_show_landing_entry_modal() {
		$proof_scan = $this->request_has_proof_scan_param();

		// Once the user closes or completes the PoD gate, JS sets a cookie so ?proof=scan does not reopen it on every navigation/refresh.
		if ( $proof_scan && $this->proof_scan_landing_already_acknowledged() ) {
			return (bool) apply_filters( 'cpm_hb_show_landing_entry_modal', false );
		}

		if ( is_user_logged_in() ) {
			if ( ! $proof_scan ) {
				return false;
			}
			return (bool) apply_filters( 'cpm_hb_show_landing_entry_modal', true );
		}

		// After "Home" we navigate with ?cpm_hb_skip_gate=1 so the gate is not rendered on that load (avoids flash; works when navigation is reported as reload).
		if ( isset( $_GET['cpm_hb_skip_gate'] ) && '1' === (string) $_GET['cpm_hb_skip_gate'] ) {
			if ( ! $proof_scan ) {
				return false;
			}
		}
		if ( $proof_scan ) {
			return (bool) apply_filters( 'cpm_hb_show_landing_entry_modal', true );
		}
		$default = is_front_page();
		return (bool) apply_filters( 'cpm_hb_show_landing_entry_modal', $default );
	}

	/**
	 * Remove one-time skip query param so a full reload (F5) can show the gate again.
	 *
	 * @since 1.0.0
	 */
	public function strip_landing_skip_gate_query_param() {
		if ( is_user_logged_in() || ! is_front_page() ) {
			return;
		}
		if ( ! isset( $_GET['cpm_hb_skip_gate'] ) || '1' !== (string) $_GET['cpm_hb_skip_gate'] ) {
			return;
		}
		?>
		<script>
		(function() {
			try {
				var u = new URL( window.location.href );
				if ( u.searchParams.get( 'cpm_hb_skip_gate' ) === '1' ) {
					u.searchParams.delete( 'cpm_hb_skip_gate' );
					var q = u.searchParams.toString();
					u.search = q ? '?' + q : '';
					history.replaceState( {}, '', u.pathname + u.search + u.hash );
				}
			} catch ( e ) {}
		})();
		</script>
		<?php
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
	 * Output landing entry modal for guests (front page or ?proof=scan).
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
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-seller-pod-intro-modal.php';
		include plugin_dir_path( __FILE__ ) . 'partials/cpm-hb-seller-scan-success-modal.php';
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

		if ( $this->should_enqueue_backorders_assets() ) {
			wp_enqueue_style(
				$this->plugin_name . '-backorders-display',
				plugin_dir_url( __FILE__ ) . 'css/cpm-hb-backorders-display.css',
				array(),
				$this->version,
				'all'
			);
		}

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

		$uid = get_current_user_id();
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
				'strings'     => array(
					'submitting'        => __( 'Submitting…', 'cpm-humanblockchain' ),
					'continue'          => __( 'Continue', 'cpm-humanblockchain' ),
					'submit'            => __( 'Submit', 'cpm-humanblockchain' ),
					'genericErr'        => __( 'Something went wrong. Please try again.', 'cpm-humanblockchain' ),
					'membershipSuccess' => __( 'Your membership selection was saved.', 'cpm-humanblockchain' ),
					'successNext'       => __( 'Membership updated.', 'cpm-humanblockchain' ),
					'accountCreated'    => __( 'Your account was created. Save this password:', 'cpm-humanblockchain' ),
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

			$landing_home_url = apply_filters( 'cpm_hb_landing_home_url', home_url( '/' ) );

			wp_localize_script(
				$this->plugin_name . '-landing-entry',
				'cpmHbLanding',
				array(
					'homeUrl'             => esc_url_raw( $landing_home_url ),
					'proofOfDeliveryUrl'  => esc_url_raw( $proof_url ),
					'onboardingFunnelUrl' => esc_url_raw( $funnel_url ),
					'whatIsThisUrl'       => esc_url_raw( $what_default ),
					'howItWorksUrl'       => esc_url_raw( $how_default ),
					'answerBothPrompts'   => __( 'Please answer both prompts (Proof of Delivery and Final Destination).', 'cpm-humanblockchain' ),
					// Set from the initial HTTP request so ?proof=scan still counts if the address bar is cleaned before OTP (replaceState, etc.).
					'hasProofScan'        => $this->request_has_proof_scan_param(),
					'proofScanNonce'      => $this->request_has_proof_scan_param() ? wp_create_nonce( 'cpm_hb_proof_scan_flow' ) : '',
				)
			);
		}

		$discord_invite = apply_filters(
			'cpm_nwp_discord_invite_url',
			get_option( 'cpm_nwp_discord_invite_url', 'https://discord.com/invite/g5jreAPbra' )
		);

		$default_country = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ? Cpm_Humanblockchain_Otp_Service::get_default_country() : 'NP';

		wp_localize_script( $this->plugin_name, 'cpmNwp', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'action'          => 'cpm_nwp_register_device',
			'sendOtpAction'   => 'cpm_nwp_send_otp',
			'verifyOtpAction' => 'cpm_nwp_verify_otp',
			'homeUrl'         => home_url( '/' ),
			'discordInviteUrl'  => esc_url_raw( $discord_invite ),
			'defaultCountry'  => $default_country,
			'phoneErrors'     => array(
				'npElevenDigits' => __( 'Nepal numbers must be exactly 10 digits without +977 (you entered 11). Use e.g. 9849158973 or +9779849158973.', 'cpm-humanblockchain' ),
				'short'          => __( 'Please enter a valid mobile number (at least 10 digits, or full international +977…).', 'cpm-humanblockchain' ),
			),
		) );

		if ( $this->should_enqueue_backorders_assets() ) {
			wp_enqueue_script(
				$this->plugin_name . '-backorders-display',
				plugin_dir_url( __FILE__ ) . 'js/cpm-hb-backorders-display.js',
				array( 'jquery' ),
				$this->version,
				true
			);
			$api_ok = class_exists( 'Cpm_Humanblockchain_Smallstreet_Backorders' )
				&& Cpm_Humanblockchain_Smallstreet_Backorders::is_configured();

			$backorders_localize = array(
				'strings' => array(
					'title'                 => __( 'Your backorders', 'cpm-humanblockchain' ),
					'empty'                 => __( 'No backorder data returned.', 'cpm-humanblockchain' ),
					'noPhone'               => __( 'No phone number is on file for your account. Add one when you register your device or in your billing profile, then reload this page.', 'cpm-humanblockchain' ),
					'loginPrompt'           => __( 'Log in to load your backorders from Smallstreet (same phone as your shop account).', 'cpm-humanblockchain' ),
					'apiMissing'            => __( 'Backorders cannot load until the Smallstreet API key is saved under Settings → NWP Gateway in WordPress.', 'cpm-humanblockchain' ),
					'selectAll'             => __( 'Select all rows', 'cpm-humanblockchain' ),
					'selectRow'             => __( 'Select order %s', 'cpm-humanblockchain' ),
					'continueDelivery'      => __( 'Confirm delivery', 'cpm-humanblockchain' ),
					'continueDeliveryHint'    => __( 'Select one or more orders, then enter the transaction code from the seller.', 'cpm-humanblockchain' ),
					'modalTitle'              => __( 'Enter transaction code', 'cpm-humanblockchain' ),
					'modalBody'               => __( 'The seller received this code after they verified their phone. Enter it to confirm delivery for the orders you selected.', 'cpm-humanblockchain' ),
					'transactionPlaceholder'  => __( 'e.g. HB-XXXXXXXXXXXXXXXX', 'cpm-humanblockchain' ),
					'submitConfirm'           => __( 'Submit', 'cpm-humanblockchain' ),
					'cancel'                  => __( 'Cancel', 'cpm-humanblockchain' ),
					'submitting'              => __( 'Submitting…', 'cpm-humanblockchain' ),
					'enterCodeAndOrders'      => __( 'Select orders and enter the seller’s transaction code.', 'cpm-humanblockchain' ),
				),
				'loginUrl'             => wp_login_url( get_permalink() ),
				'isVisitor'            => ! is_user_logged_in(),
				'apiConfigured'        => $api_ok,
				'canConfirmDelivery'   => is_user_logged_in(),
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'confirmAction'        => 'cpm_hb_buyer_confirm_delivery',
				'confirmNonce'         => wp_create_nonce( 'cpm_hb_backorders_confirm' ),
				'homeUrl'              => esc_url_raw( home_url( '/' ) ),
				'linkedOrderIds'       => array(),
			);
			if ( is_user_logged_in() && class_exists( 'Cpm_Humanblockchain_Xp_Ledger' ) ) {
				$backorders_localize['linkedOrderIds'] = Cpm_Humanblockchain_Xp_Ledger::get_linked_order_ids_for_backorders_display();
			}
			// Logged-in + API: load rows by phone; persist successful responses in user meta so refresh still shows data if API is empty.
			if ( is_user_logged_in() && $api_ok ) {
				$uid   = (int) get_current_user_id();
				$phone = apply_filters(
					'cpm_hb_phone_for_backorders_lookup',
					Cpm_Humanblockchain_Device_Registry::get_phone_for_user( $uid ),
					$uid
				);
				$rows = array();
				if ( is_string( $phone ) && $phone !== '' ) {
					$rows = Cpm_Humanblockchain_Smallstreet_Backorders::get_backorders_for_display( $phone );
					if ( ! empty( $rows ) ) {
						Cpm_Humanblockchain_Smallstreet_Backorders::save_user_backorders_cache( $uid, $rows );
					} elseif ( (bool) apply_filters( 'cpm_hb_backorders_fallback_to_user_cache', true, $uid ) ) {
						$rows = Cpm_Humanblockchain_Smallstreet_Backorders::get_user_backorders_cache( $uid );
					}
				} elseif ( (bool) apply_filters( 'cpm_hb_backorders_fallback_to_user_cache', true, $uid ) ) {
					$rows = Cpm_Humanblockchain_Smallstreet_Backorders::get_user_backorders_cache( $uid );
				}
				$backorders_localize['initialRows'] = is_array( $rows ) ? $rows : array();
				if ( ( ! is_string( $phone ) || $phone === '' ) && empty( $backorders_localize['initialRows'] ) ) {
					$backorders_localize['showNoPhone'] = true;
				}
			}
			wp_localize_script(
				$this->plugin_name . '-backorders-display',
				'cpmHbBackorders',
				$backorders_localize
			);
		}
	}

}
