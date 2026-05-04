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
	 * Settings API groups: one per tabbed form. WordPress saves every option in a group on submit;
	 * fields not in the POST become null and would wipe the other tab if both shared one group.
	 */
	const NWP_SETTINGS_GROUP_GENERAL     = 'cpm_nwp_gateway_general';
	const NWP_SETTINGS_GROUP_INTEGRATION = 'cpm_nwp_gateway_integration';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		add_filter( 'wp_redirect', array( $this, 'filter_nwp_settings_redirect_tab' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_nwp_settings_wc_assets' ), 99 );

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
	 * Register NWP Gateway settings (two option groups for General vs Integration tabs).
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		$g = self::NWP_SETTINGS_GROUP_GENERAL;
		register_setting( $g, 'cpm_nwp_discord_invite_url', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_discord_invite_url' ),
		) );
		register_setting( $g, 'cpm_nwp_two_scan_max_seconds', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_two_scan_max_seconds' ),
		) );
		register_setting( $g, 'cpm_nwp_two_scan_max_distance_m', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_two_scan_max_distance_m' ),
		) );
		register_setting( $g, 'cpm_nwp_two_scan_geo_only_capped_nwp', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_two_scan_geo_only_capped_nwp' ),
		) );
		register_setting( $g, 'cpm_nwp_auto_cap_product_ids', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_auto_cap_product_ids' ),
		) );
		register_setting( $g, 'cpm_nwp_qr_url', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_nwp_qr_url' ),
		) );

		$i = self::NWP_SETTINGS_GROUP_INTEGRATION;
		register_setting( $i, 'cpm_nwp_twilio_sid', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( $i, 'cpm_nwp_twilio_token', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_twilio_token' ),
		) );
		register_setting( $i, 'cpm_nwp_twilio_from', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( $i, 'cpm_nwp_default_country', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_default_country' ),
		) );
		register_setting( $i, 'cpm_nwp_qrtiger_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_qrtiger_api_key' ),
		) );
		register_setting( $i, 'cpm_nwp_qrtiger_api_url', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_qrtiger_api_url' ),
		) );
	}

	/**
	 * After saving NWP options, keep the active tab (General vs Integration) in the redirect URL.
	 *
	 * @param string $location Redirect location.
	 * @param int    $status   Status code.
	 * @return string
	 */
	public function filter_nwp_settings_redirect_tab( $location, $status ) {
		if ( ! isset( $_POST['option_page'], $_POST['cpm_nwp_settings_tab'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $location;
		}
		$page = (string) wp_unslash( $_POST['option_page'] ); // phpcs:ignore WordPress.Security
		if ( ! in_array( $page, array( self::NWP_SETTINGS_GROUP_GENERAL, self::NWP_SETTINGS_GROUP_INTEGRATION ), true ) ) {
			return $location;
		}
		if ( false === strpos( (string) $location, 'cpm-nwp-settings' ) || false === strpos( (string) $location, 'settings-updated' ) ) {
			return $location;
		}
		$tab = sanitize_key( wp_unslash( $_POST['cpm_nwp_settings_tab'] ) ); // phpcs:ignore WordPress.Security
		if ( 'qr' === $tab ) {
			$tab = 'general';
		}
		if ( ! in_array( $tab, array( 'general', 'integration' ), true ) ) {
			return $location;
		}
		$location = remove_query_arg( 'tab', $location );
		return add_query_arg( 'tab', $tab, $location );
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
	 * Gracebook / Discord invite URL (after device verification).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_discord_invite_url( $value ) {
		$v = is_string( $value ) ? esc_url_raw( trim( $value ) ) : '';
		if ( $v === '' ) {
			$v = 'https://discord.com/invite/g5jreAPbra';
		}
		return $v;
	}

	/**
	 * Max seconds between first and second scan (two-scan / PoD validation).
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_two_scan_max_seconds( $value ) {
		$n = absint( $value );
		$min = 30;
		$max = 86400;
		if ( $n < 1 ) {
			$n = Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_SECONDS;
		}
		return max( $min, min( $max, $n ) );
	}

	/**
	 * Max distance in meters between scan locations.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_two_scan_max_distance_m( $value ) {
		$n = absint( $value );
		$min = 5;
		$max = 50000;
		if ( $n < 1 ) {
			$n = Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_DISTANCE_M;
		}
		return max( $min, min( $max, $n ) );
	}

	/**
	 * Checkbox: two-scan time/distance only for NWP $0.03/day-cap Woo orders.
	 *
	 * @param mixed $value Raw value (use hidden field 0 + checkbox 1 so unchecked saves 0).
	 * @return string '1' or '0'
	 */
	public function sanitize_two_scan_geo_only_capped_nwp( $value ) {
		return '1' === (string) $value ? '1' : '0';
	}

	/**
	 * Comma-separated product or variation IDs for auto NWP cap tagging at checkout.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_auto_cap_product_ids( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();
			foreach ( $value as $p ) {
				$n = absint( $p );
				if ( $n > 0 ) {
					$parts[] = (string) $n;
				}
			}
			$parts = array_unique( array_slice( $parts, 0, 500 ) );
			return implode( ',', $parts );
		}
		$s = is_string( $value ) ? trim( $value ) : '';
		if ( $s === '' ) {
			return '';
		}
		$parts = array();
		foreach ( preg_split( '/[\s,]+/', $s, -1, PREG_SPLIT_NO_EMPTY ) as $p ) {
			$n = absint( $p );
			if ( $n > 0 ) {
				$parts[] = (string) $n;
			}
		}
		$parts = array_unique( array_slice( $parts, 0, 500 ) );
		return implode( ',', $parts );
	}

	/**
	 * Target URL for the downloadable QR code image (optional; can be empty until generated).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_nwp_qr_url( $value ) {
		$v = is_string( $value ) ? trim( $value ) : '';
		if ( $v === '' ) {
			return '';
		}
		return esc_url_raw( $v );
	}

	/**
	 * AJAX: build PNG in Media Library, delete previous attachment if any.
	 */
	public function ajax_generate_nwp_qr() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'cpm-humanblockchain' ) ), 403 );
		}
		check_ajax_referer( 'cpm_nwp_generate_qr', 'cpm_nwp_qr_nonce' );
		$raw = isset( $_POST['qr_url'] ) ? wp_unslash( $_POST['qr_url'] ) : '';
		$url = is_string( $raw ) ? esc_url_raw( trim( $raw ) ) : '';
		if ( $url === '' ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid URL to encode (http or https).', 'cpm-humanblockchain' ) ) );
		}
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'The URL must start with http:// or https://', 'cpm-humanblockchain' ) ) );
		}

		$base    = apply_filters( 'cpm_nwp_qr_png_remote_url', 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=', $url );
		$req_url = $base . rawurlencode( $url );

		$resp = wp_remote_get(
			$req_url,
			array(
				'timeout'   => 25,
				'sslverify' => true,
			)
		);
		if ( is_wp_error( $resp ) ) {
			wp_send_json_error( array( 'message' => $resp->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = (string) wp_remote_retrieve_body( $resp );
		if ( $code < 200 || $code >= 300 || strlen( $body ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Could not fetch the QR code image. Check that outbound HTTPS is allowed, then try again.', 'cpm-humanblockchain' ) ) );
		}
		if ( strlen( $body ) > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Unexpected response from QR service.', 'cpm-humanblockchain' ) ) );
		}
		if ( "\x89PNG" !== substr( $body, 0, 4 ) ) {
			wp_send_json_error( array( 'message' => __( 'QR service did not return a valid PNG image.', 'cpm-humanblockchain' ) ) );
		}

		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$filename  = 'nwp-gateway-qr-' . wp_hash( $url . microtime( true ) ) . '.png';
		$upload    = wp_upload_bits( $filename, null, $body );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $upload['error'] ? $upload['error'] : __( 'Failed to write the image to uploads.', 'cpm-humanblockchain' ) ) ) );
		}
		$filetype = wp_check_filetype( $filename, null );
		$attach   = array(
			'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/png',
			'post_title'     => sanitize_text_field( 'NWP Gateway QR ' . substr( ( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ?: 'link', 0, 50 ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attach, $upload['file'] );
		if ( ! $attach_id ) {
			@unlink( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_send_json_error( array( 'message' => __( 'Failed to create the media attachment.', 'cpm-humanblockchain' ) ) );
		}
		$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( is_array( $meta ) && ! empty( $meta ) ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}

		$old = (int) get_option( 'cpm_nwp_qr_attachment_id', 0 );
		if ( $old > 0 && $old !== (int) $attach_id ) {
			wp_delete_attachment( $old, true );
		}

		update_option( 'cpm_nwp_qr_url', $url );
		update_option( 'cpm_nwp_qr_attachment_id', (int) $attach_id );
		$image_url = wp_get_attachment_url( (int) $attach_id );
		if ( ! is_string( $image_url ) || $image_url === '' ) {
			$image_url = '';
		}
		wp_send_json_success(
			array(
				'message'        => __( 'QR code image saved to the Media Library.', 'cpm-humanblockchain' ),
				'attachmentId'  => (int) $attach_id,
				'imageUrl'      => $image_url,
				'regenerate'    => true,
			)
		);
	}

	/**
	 * QRTiger API key; empty input keeps the previously saved key.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_qrtiger_api_key( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( $value === '' ) {
			$prev = get_option( 'cpm_nwp_qrtiger_api_key', '' );
			return is_string( $prev ) ? $prev : '';
		}
		return sanitize_text_field( $value );
	}

	/**
	 * QRTiger API base URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_qrtiger_api_url( $value ) {
		$v = is_string( $value ) ? trim( $value ) : '';
		if ( $v === '' ) {
			return '';
		}
		return esc_url_raw( $v );
	}

	/**
	 * Parse saved comma-separated product IDs into a unique int list.
	 *
	 * @param string $comma_ids Raw option value.
	 * @return int[]
	 */
	private function parse_auto_cap_saved_product_ids( $comma_ids ) {
		$out = array();
		foreach ( preg_split( '/[\s,]+/', (string) $comma_ids, -1, PREG_SPLIT_NO_EMPTY ) as $p ) {
			$n = absint( $p );
			if ( $n > 0 ) {
				$out[] = $n;
			}
		}
		return array_values( array_unique( array_slice( $out, 0, 500 ) ) );
	}

	/**
	 * Label for one row in the auto-cap product multiselect.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return string
	 */
	private function format_product_label_for_auto_cap_dropdown( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		$id    = (int) $product->get_id();
		$label = '#' . $id . ' — ' . wp_strip_all_tags( $product->get_name() );
		if ( $product->is_type( 'variation' ) && function_exists( 'wc_get_formatted_variation' ) ) {
			$attrs = wc_get_formatted_variation( $product, true, true, false );
			if ( is_string( $attrs ) && $attrs !== '' ) {
				$label .= ' (' . wp_strip_all_tags( $attrs ) . ')';
			}
		}
		return $label;
	}

	/**
	 * Build &lt;option&gt; list: all published products (up to a limit) plus any saved IDs not in that set.
	 *
	 * @param string $comma_ids Saved option (comma-separated IDs).
	 * @return string HTML (escaped).
	 */
	private function get_nwp_auto_cap_product_multiselect_options_html( $comma_ids ) {
		if ( ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product_Query' ) ) {
			return '';
		}
		$selected = $this->parse_auto_cap_saved_product_ids( $comma_ids );
		$labels   = array();
		$limit    = (int) apply_filters( 'cpm_hb_nwp_auto_cap_dropdown_max_products', 500 );
		$limit    = min( 2000, max( 1, $limit ) );
		$types    = apply_filters(
			'cpm_hb_nwp_auto_cap_dropdown_product_types',
			array( 'simple', 'variable', 'variation' )
		);
		if ( ! is_array( $types ) ) {
			$types = array( 'simple', 'variable', 'variation' );
		}
		$query = new WC_Product_Query(
			array(
				'status'  => 'publish',
				'limit'   => $limit,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'objects',
				'type'    => $types,
			)
		);
		$found = $query->get_products();
		if ( is_array( $found ) ) {
			foreach ( $found as $product ) {
				if ( ! $product instanceof WC_Product ) {
					continue;
				}
				$pid = (int) $product->get_id();
				if ( $pid <= 0 ) {
					continue;
				}
				$labels[ $pid ] = $this->format_product_label_for_auto_cap_dropdown( $product );
			}
		}
		foreach ( $selected as $sid ) {
			if ( isset( $labels[ $sid ] ) ) {
				continue;
			}
			$product = wc_get_product( $sid );
			$labels[ $sid ] = $product
				? $this->format_product_label_for_auto_cap_dropdown( $product )
				: '#' . $sid . ' — ' . __( '(missing product)', 'cpm-humanblockchain' );
		}
		natcasesort( $labels );
		$html = '';
		foreach ( $labels as $id => $label ) {
			$sel = in_array( (int) $id, $selected, true ) ? ' selected="selected"' : '';
			$html .= '<option value="' . esc_attr( (string) $id ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
		}
		return $html;
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
		$discord_invite   = get_option( 'cpm_nwp_discord_invite_url', 'https://discord.com/invite/g5jreAPbra' );
		$two_scan_secs    = (int) get_option(
			Cpm_Humanblockchain_Nwp_Gateway_Config::OPTION_TWO_SCAN_MAX_SECONDS,
			Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_SECONDS
		);
		$two_scan_m       = (int) get_option(
			Cpm_Humanblockchain_Nwp_Gateway_Config::OPTION_TWO_SCAN_MAX_DISTANCE_M,
			Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_DISTANCE_M
		);
		$two_scan_cap_only = '1' === (string) get_option( Cpm_Humanblockchain_Nwp_Gateway_Config::OPTION_TWO_SCAN_GEO_ONLY_CAPPED_NWP, '0' );
		$auto_cap_ids      = (string) get_option( Cpm_Humanblockchain_Nwp_Gateway_Config::OPTION_AUTO_CAP_PRODUCT_IDS, '' );
		$nwp_qr_url       = get_option( 'cpm_nwp_qr_url', '' );
		$nwp_qr_att       = (int) get_option( 'cpm_nwp_qr_attachment_id', 0 );
		$nwp_qr_image     = ( $nwp_qr_att > 0 ) ? wp_get_attachment_image_url( $nwp_qr_att, 'full' ) : '';
		if ( ! is_string( $nwp_qr_image ) || $nwp_qr_image === '' ) {
			$nwp_qr_image = '';
		}
		$sid              = get_option( 'cpm_nwp_twilio_sid', '' );
		$token            = get_option( 'cpm_nwp_twilio_token', '' );
		$from             = get_option( 'cpm_nwp_twilio_from', '' );
		$country          = get_option( 'cpm_nwp_default_country', 'AUTO' );
		$qrtiger_key      = get_option( 'cpm_nwp_qrtiger_api_key', '' );
		$qrtiger_url      = get_option( 'cpm_nwp_qrtiger_api_url', '' );
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'qr' === $active_tab ) {
			$active_tab = 'general';
		}
		if ( ! in_array( $active_tab, array( 'general', 'integration' ), true ) ) {
			$active_tab = 'general';
		}

		$using_constants = defined( 'CPM_NWP_TWILIO_SID' ) || defined( 'CPM_NWP_TWILIO_TOKEN' ) || defined( 'CPM_NWP_TWILIO_FROM' )
			|| defined( 'CPM_TWILIO_ACCOUNT_SID' ) || defined( 'CPM_TWILIO_AUTH_TOKEN' ) || defined( 'CPM_TWILIO_FROM' )
			|| defined( 'CPM_TWILIO_VERIFY_SERVICE_SID' ) || defined( 'CPM_NWP_TWILIO_VERIFY_SERVICE_SID' );
		$twilio_ready      = class_exists( 'Cpm_Humanblockchain_Otp_Service' ) && Cpm_Humanblockchain_Otp_Service::is_configured();
		$uses_verify_api   = $twilio_ready && class_exists( 'Cpm_Humanblockchain_Otp_Service' ) && Cpm_Humanblockchain_Otp_Service::uses_twilio_verify();

		$base_url  = admin_url( 'options-general.php?page=cpm-nwp-settings' );
		$tab_gen   = $base_url . '&tab=general';
		$tab_int   = $base_url . '&tab=integration';
		?>
		<div class="wrap cpm-nwp-admin-settings">
			<h1><?php esc_html_e( 'NWP Gateway Settings', 'cpm-humanblockchain' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'NWP settings sections', 'cpm-humanblockchain' ); ?>">
				<a href="<?php echo esc_url( $tab_gen ); ?>" class="nav-tab<?php echo 'general' === $active_tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'cpm-humanblockchain' ); ?></a>
				<a href="<?php echo esc_url( $tab_int ); ?>" class="nav-tab<?php echo 'integration' === $active_tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Integration', 'cpm-humanblockchain' ); ?></a>
			</nav>

			<div class="cpm-nwp-tab-panel" id="cpm-nwp-panel-general" style="<?php echo 'general' === $active_tab ? '' : 'display:none;'; ?>">
				<form method="post" action="options.php" class="cpm-nwp-settings-form-general">
					<?php settings_fields( self::NWP_SETTINGS_GROUP_GENERAL ); ?>
					<input type="hidden" name="cpm_nwp_settings_tab" value="general" />
					<h2 class="title" style="margin-top:1em;"><?php esc_html_e( 'QR & onboarding links', 'cpm-humanblockchain' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'These values power post-registration modals and printed QR flows. Device registration can still collect a QRtiger v-card link per user on the public form.', 'cpm-humanblockchain' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cpm_nwp_discord_invite_url"><?php esc_html_e( 'Gracebook Discord invite URL', 'cpm-humanblockchain' ); ?></label></th>
							<td>
								<input type="url" class="large-text" id="cpm_nwp_discord_invite_url" name="cpm_nwp_discord_invite_url" value="<?php echo esc_attr( $discord_invite ); ?>" placeholder="https://discord.com/invite/…">
								<p class="description">
									<?php esc_html_e( 'Used in the “Join Gracebook Discord” step after a user verifies their phone. Shown in the CTA from the NWP / HumanBlockchain modals on the site.', 'cpm-humanblockchain' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<h2 class="title" style="margin-top:2em;"><?php esc_html_e( '2-scan validation', 'cpm-humanblockchain' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Limits used when validating proof-of-delivery style flows (seller scan vs buyer scan): maximum elapsed time and maximum distance between the two reported locations.', 'cpm-humanblockchain' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cpm_nwp_two_scan_max_seconds"><?php esc_html_e( 'Maximum time between scans', 'cpm-humanblockchain' ); ?></label></th>
							<td>
								<input type="number" class="small-text" id="cpm_nwp_two_scan_max_seconds" name="cpm_nwp_two_scan_max_seconds" value="<?php echo esc_attr( (string) $two_scan_secs ); ?>" min="30" max="86400" step="1">
								<span class="description"><?php esc_html_e( 'seconds', 'cpm-humanblockchain' ); ?></span>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: default seconds (e.g. 180) */
											__( 'Default %d (3 minutes). Allowed range: 30–86400 seconds.', 'cpm-humanblockchain' ),
											Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_SECONDS
										)
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpm_nwp_two_scan_max_distance_m"><?php esc_html_e( 'Maximum distance between scans', 'cpm-humanblockchain' ); ?></label></th>
							<td>
								<input type="number" class="small-text" id="cpm_nwp_two_scan_max_distance_m" name="cpm_nwp_two_scan_max_distance_m" value="<?php echo esc_attr( (string) $two_scan_m ); ?>" min="5" max="50000" step="1">
								<span class="description"><?php esc_html_e( 'meters', 'cpm-humanblockchain' ); ?></span>
								<p class="description">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %d: default meters (e.g. 50) */
											__( 'Haversine distance between latitude/longitude fixes. Default %d m. Allowed range: 5–50000 m.', 'cpm-humanblockchain' ),
											Cpm_Humanblockchain_Nwp_Gateway_Config::DEFAULT_TWO_SCAN_MAX_DISTANCE_M
										)
									);
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Cap-aware two-scan', 'cpm-humanblockchain' ); ?></th>
							<td>
								<input type="hidden" name="cpm_nwp_two_scan_geo_only_capped_nwp" value="0" />
								<label>
									<input type="checkbox" name="cpm_nwp_two_scan_geo_only_capped_nwp" value="1" <?php checked( $two_scan_cap_only ); ?> />
									<?php esc_html_e( 'Apply time/distance rules only to WooCommerce orders tagged for NWP $0.03/day max (order meta _cpm_hb_nwp_daily_max_usd = 0.03, or filter).', 'cpm-humanblockchain' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'When enabled, buyer OTP normally skips GPS/time checks unless you enable the otp filter; delivery confirm enforces location and elapsed time only if at least one selected order qualifies.', 'cpm-humanblockchain' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="cpm_nwp_auto_cap_product_ids"><?php esc_html_e( 'Auto-tag: products', 'cpm-humanblockchain' ); ?></label></th>
							<td>
								<?php if ( class_exists( 'WooCommerce' ) ) : ?>
									<select
										class="wc-enhanced-select"
										multiple="multiple"
										style="width:100%;max-width:640px;"
										id="cpm_nwp_auto_cap_product_ids"
										name="cpm_nwp_auto_cap_product_ids[]"
										data-placeholder="<?php esc_attr_e( 'Select products…', 'cpm-humanblockchain' ); ?>"
										data-allow_clear="true"
									>
										<?php
										echo wp_kses(
											$this->get_nwp_auto_cap_product_multiselect_options_html( $auto_cap_ids ),
											array(
												'option' => array(
													'value'    => true,
													'selected' => true,
												),
											)
										);
										?>
									</select>
									<p class="description">
										<?php
										echo esc_html(
											sprintf(
												/* translators: %d: max products listed in the dropdown */
												__( 'Lists up to %d published products (and your saved picks if they are outside that list). Use the box to search within the list and select multiple rows. At checkout, matching orders get _cpm_hb_nwp_daily_max_usd = 0.03. Increase the limit with filter cpm_hb_nwp_auto_cap_dropdown_max_products if needed.', 'cpm-humanblockchain' ),
												(int) apply_filters( 'cpm_hb_nwp_auto_cap_dropdown_max_products', 500 )
											)
										);
										?>
									</p>
								<?php else : ?>
									<input type="text" class="large-text code" id="cpm_nwp_auto_cap_product_ids" name="cpm_nwp_auto_cap_product_ids" value="<?php echo esc_attr( $auto_cap_ids ); ?>" placeholder="<?php esc_attr_e( 'e.g. 123, 456', 'cpm-humanblockchain' ); ?>">
									<p class="description">
										<?php esc_html_e( 'WooCommerce is required for the product picker. Enter comma-separated product or variation IDs, or install WooCommerce and reload.', 'cpm-humanblockchain' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
					</table>

					<h2 class="title" style="margin-top:2em;"><?php esc_html_e( 'QR code', 'cpm-humanblockchain' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Set the link people should get when they scan the code, then generate a PNG. The file is stored in the Media Library so you can reuse or download it. Use Regenerate to replace the image after you change the URL or to refresh the file.', 'cpm-humanblockchain' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cpm_nwp_qr_url"><?php esc_html_e( 'QR URL', 'cpm-humanblockchain' ); ?></label></th>
							<td>
								<input type="url" class="large-text" id="cpm_nwp_qr_url" name="cpm_nwp_qr_url" value="<?php echo esc_attr( is_string( $nwp_qr_url ) ? $nwp_qr_url : '' ); ?>" placeholder="https://…">
								<p class="description"><?php esc_html_e( 'Full public URL to encode in the QR image. Save the form or generate — generating also saves this URL in the site options.', 'cpm-humanblockchain' ); ?></p>
								<p>
									<button type="button" class="button button-primary" id="cpm-nwp-qr-generate" data-cpm-regenerate="<?php echo ( $nwp_qr_att > 0 && $nwp_qr_image !== '' ) ? '1' : '0'; ?>"><?php echo ( $nwp_qr_att > 0 && $nwp_qr_image !== '' ) ? esc_html__( 'Regenerate QR', 'cpm-humanblockchain' ) : esc_html__( 'Generate QR', 'cpm-humanblockchain' ); ?></button>
									<span id="cpm-nwp-qr-gen-status" class="description" style="margin-left:0.5em;vertical-align:middle;"></span>
								</p>
								<?php
								$edit_link = ( $nwp_qr_att > 0 ) ? get_edit_post_link( $nwp_qr_att, 'raw' ) : '';
								$show_prv    = ( $nwp_qr_image !== '' );
								?>
								<div id="cpm-nwp-qr-preview" style="margin-top:0.75em;padding:12px;border:1px solid #c3c4c7;background:#fff;max-width:220px;border-radius:4px;<?php echo $show_prv ? '' : ' display:none;'; ?>" aria-hidden="<?php echo $show_prv ? 'false' : 'true'; ?>">
									<p style="margin:0 0 0.5em 0;" class="description"><strong><?php esc_html_e( 'Current QR', 'cpm-humanblockchain' ); ?></strong></p>
									<p style="margin:0 0 0.5em 0;">
										<img id="cpm-nwp-qr-preview-img" src="<?php echo $show_prv ? esc_url( $nwp_qr_image ) : ''; ?>" alt="" style="max-width:100%;height:auto;<?php echo $show_prv ? ' display:block' : ' display:none'; ?>">
									</p>
									<p id="cpm-nwp-qr-preview-edit" style="margin:0;" class="description">
										<?php
										if ( is_string( $edit_link ) && $edit_link !== '' ) {
											?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Open in Media Library', 'cpm-humanblockchain' ); ?></a>
										<?php
										}
										?>
									</p>
								</div>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			</div>

			<div class="cpm-nwp-tab-panel" id="cpm-nwp-panel-integration" style="<?php echo 'integration' === $active_tab ? '' : 'display:none;'; ?>">

			<div class="notice notice-info inline" style="margin: 12px 0;">
				<p>
					<strong><?php esc_html_e( 'Twilio status:', 'cpm-humanblockchain' ); ?></strong>
					<?php if ( $twilio_ready ) : ?>
						<span style="color:#00a32a;"><?php esc_html_e( 'Ready — OTP SMS can be sent.', 'cpm-humanblockchain' ); ?></span>
						<?php if ( $uses_verify_api ) : ?>
							<?php esc_html_e( '(Twilio Verify — no “From” number needed.)', 'cpm-humanblockchain' ); ?>
						<?php endif; ?>
					<?php else : ?>
						<span style="color:#d63638;"><?php esc_html_e( 'Not configured — add Account SID and Auth Token, then either define a Twilio Verify Service SID (VA…) in wp-config.php or add a From number for the Messages API.', 'cpm-humanblockchain' ); ?></span>
					<?php endif; ?>
				</p>
				<?php
				if ( ! $twilio_ready && class_exists( 'Cpm_Humanblockchain_Otp_Service' ) ) {
					$gaps = Cpm_Humanblockchain_Otp_Service::get_twilio_configuration_gaps();
					if ( array() !== $gaps ) {
						echo '<ul style="margin:0.5em 0 0 1.25em;list-style:disc;">';
						if ( in_array( 'account_sid', $gaps, true ) ) {
							echo '<li>' . esc_html__( 'Account SID is missing (or not in wp-config).', 'cpm-humanblockchain' ) . '</li>';
						}
						if ( in_array( 'auth_token', $gaps, true ) ) {
							echo '<li>' . esc_html__( 'Auth Token is missing — paste it from Twilio Console. “Leave blank to keep” only works if a token is already stored; re-paste after it was cleared.', 'cpm-humanblockchain' ) . '</li>';
						}
						if ( in_array( 'verify_or_from', $gaps, true ) ) {
							echo '<li>' . esc_html__( 'Define CPM_TWILIO_VERIFY_SERVICE_SID or CPM_NWP_TWILIO_VERIFY_SERVICE_SID (VA…) in wp-config.php for Verify, or set a From number for classic SMS — one of the two is required.', 'cpm-humanblockchain' ) . '</li>';
						}
						echo '</ul>';
					}
				}
				?>
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
					<?php esc_html_e( 'If you use Twilio Verify, define CPM_TWILIO_VERIFY_SERVICE_SID or CPM_NWP_TWILIO_VERIFY_SERVICE_SID in wp-config.php (VA… from Twilio Console → Verify → Services).', 'cpm-humanblockchain' ); ?>
				</p>
			</div>

			<form method="post" action="options.php" class="cpm-nwp-settings-form-integration">
				<?php settings_fields( self::NWP_SETTINGS_GROUP_INTEGRATION ); ?>
				<input type="hidden" name="cpm_nwp_settings_tab" value="integration" />
				<h2 class="title" style="margin-top:0;"><?php esc_html_e( 'Twilio SMS (for OTP)', 'cpm-humanblockchain' ); ?></h2>
				<p><?php esc_html_e( 'Used when a user opens Activate device → Send OTP. The phone must already exist in wp_nwp_devices.', 'cpm-humanblockchain' ); ?></p>
				<table class="form-table" role="presentation">
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
						<th scope="row"><label for="cpm_nwp_twilio_sid"><?php esc_html_e( 'Account SID', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_sid" name="cpm_nwp_twilio_sid" value="<?php echo esc_attr( $sid ); ?>" class="regular-text" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></td>
					</tr>
					<tr>
						<th scope="row"><label for="cpm_nwp_twilio_token"><?php esc_html_e( 'Auth Token', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<input type="password" id="cpm_nwp_twilio_token" name="cpm_nwp_twilio_token" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo esc_attr( $token !== '' ? __( 'Leave blank to keep saved token', 'cpm-humanblockchain' ) : __( 'Paste Auth Token from Twilio Console', 'cpm-humanblockchain' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Must match the Auth Token in Twilio Console. Leave blank when saving other settings to keep the current token.', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cpm_nwp_twilio_from"><?php esc_html_e( 'From (Twilio number)', 'cpm-humanblockchain' ); ?></label></th>
						<td><input type="text" id="cpm_nwp_twilio_from" name="cpm_nwp_twilio_from" value="<?php echo esc_attr( $from ); ?>" class="regular-text" placeholder="+15551234567">
						<p class="description"><?php esc_html_e( 'Required only for the Messages API (when Verify Service SID is not set). E.164 format, e.g. +15551234567', 'cpm-humanblockchain' ); ?></p></td>
					</tr>
				</table>

				<h2 class="title" style="margin-top:2em;"><?php esc_html_e( 'QRTiger integration', 'cpm-humanblockchain' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Optional. Use these credentials for server-side calls to the QRTiger API (e.g. v-cards, dynamic QR). The public register form can still store a v-card link per user without this.', 'cpm-humanblockchain' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="cpm_nwp_qrtiger_api_key"><?php esc_html_e( 'QRTiger API key', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<input type="password" id="cpm_nwp_qrtiger_api_key" name="cpm_nwp_qrtiger_api_key" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo esc_attr( is_string( $qrtiger_key ) && $qrtiger_key !== '' ? __( 'Leave blank to keep saved key', 'cpm-humanblockchain' ) : __( 'Paste your QRTiger API key', 'cpm-humanblockchain' ) ); ?>">
							<p class="description"><?php esc_html_e( 'From your QRTiger account. Leave blank when saving other fields to keep the current key.', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cpm_nwp_qrtiger_api_url"><?php esc_html_e( 'QRTiger API URL', 'cpm-humanblockchain' ); ?></label></th>
						<td>
							<input type="url" id="cpm_nwp_qrtiger_api_url" name="cpm_nwp_qrtiger_api_url" value="<?php echo esc_attr( $qrtiger_url ); ?>" class="large-text" placeholder="https://api.qrtiger.com/">
							<p class="description"><?php esc_html_e( 'Base URL for the QRTiger API (include protocol, no trailing path required if your client adds routes).', 'cpm-humanblockchain' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles( $hook = '' ) {

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
	public function enqueue_scripts( $hook = '' ) {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cpm-humanblockchain-admin.js', array( 'jquery' ), $this->version, true );
		if ( 'settings_page_cpm-nwp-settings' === $hook ) {
			wp_localize_script(
				$this->plugin_name,
				'cpmNwpAdmin',
				array(
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'cpm_nwp_generate_qr' ),
					'strGen'    => __( 'Generate QR', 'cpm-humanblockchain' ),
					'strRegen'  => __( 'Regenerate QR', 'cpm-humanblockchain' ),
					'strNoUrl'  => __( 'Enter a valid http(s) URL to encode, then try again.', 'cpm-humanblockchain' ),
					'strReqFail' => __( 'The request could not be completed. Try again.', 'cpm-humanblockchain' ),
					'strWorking' => __( 'Generating…', 'cpm-humanblockchain' ),
					'postEdit'   => admin_url( 'post.php' ),
					'mediaStr'  => __( 'Open in Media Library', 'cpm-humanblockchain' ),
				)
			);
		}
	}

	/**
	 * WooCommerce SelectWoo (product search) on NWP settings — runs at priority 20 so WC has registered handles.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue_nwp_settings_wc_assets( $hook_suffix ) {
		if ( 'settings_page_cpm-nwp-settings' !== $hook_suffix || ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		if ( wp_style_is( 'woocommerce_admin_styles', 'registered' ) ) {
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
		if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_add_inline_script(
				'wc-enhanced-select',
				'jQuery(function($){$(document.body).trigger("wc-enhanced-select-init");'
				. '$(document).on("submit",".cpm-nwp-settings-form-general",function(){'
				. 'var $s=$("#cpm_nwp_auto_cap_product_ids");if(!$s.length){return;}'
				. 'var v=$s.val();if(!v||!v.length){$(this).append("<input type=\"hidden\" name=\"cpm_nwp_auto_cap_product_ids[]\" value=\"\" />");}'
				. '});});',
				'after'
			);
		}
	}

}
