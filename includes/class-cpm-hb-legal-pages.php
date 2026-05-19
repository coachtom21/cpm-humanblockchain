<?php
/**
 * Privacy Policy and Terms pages (Twilio 10DLC / site compliance).
 *
 * @package Cpm_Humanblockchain
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and syncs legal pages from plugin templates.
 */
class Cpm_Hb_Legal_Pages {

	const CONTENT_VERSION = '1.0.0';

	const META_MANAGED = '_cpm_hb_legal_managed';

	const SLUG_PRIVACY = 'privacy-policy';

	const SLUG_TERMS = 'terms-and-conditions';

	const OPTION_PRIVACY_PAGE_ID = 'cpm_hb_privacy_page_id';

	const OPTION_TERMS_PAGE_ID = 'cpm_hb_terms_page_id';

	const OPTION_CONTENT_VERSION = 'cpm_hb_legal_content_version';

	const OPTION_EFFECTIVE_DATE = 'cpm_hb_legal_effective_date';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_sync' ), 25 );
		add_action( 'admin_post_cpm_hb_sync_legal_pages', array( __CLASS__, 'handle_admin_sync' ) );
	}

	/**
	 * Bump template version → refresh managed pages.
	 */
	public static function maybe_sync() {
		$stored = (string) get_option( self::OPTION_CONTENT_VERSION, '' );
		if ( self::CONTENT_VERSION === $stored ) {
			return;
		}
		self::ensure_pages( true );
		update_option( self::OPTION_CONTENT_VERSION, self::CONTENT_VERSION );
	}

	/**
	 * Create or update Privacy + Terms pages.
	 *
	 * @param bool $force Overwrite managed or empty pages.
	 * @return array{privacy_id:int,terms_id:int}
	 */
	public static function ensure_pages( $force = false ) {
		$effective = wp_date( 'F j, Y' );
		update_option( self::OPTION_EFFECTIVE_DATE, $effective );

		$privacy_id = self::upsert_page(
			self::SLUG_PRIVACY,
			__( 'Privacy Policy', 'cpm-humanblockchain' ),
			'privacy-policy',
			self::OPTION_PRIVACY_PAGE_ID,
			$force
		);

		$terms_id = self::upsert_page(
			self::SLUG_TERMS,
			__( 'Terms and Conditions', 'cpm-humanblockchain' ),
			'terms-and-conditions',
			self::OPTION_TERMS_PAGE_ID,
			$force
		);

		if ( $privacy_id > 0 ) {
			update_option( 'wp_page_for_privacy_policy', $privacy_id );
		}

		return array(
			'privacy_id' => (int) $privacy_id,
			'terms_id'   => (int) $terms_id,
		);
	}

	/**
	 * Admin: force sync from NWP Gateway.
	 */
	public static function handle_admin_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cpm-humanblockchain' ) );
		}
		check_admin_referer( 'cpm_hb_sync_legal_pages' );

		self::ensure_pages( true );
		update_option( self::OPTION_CONTENT_VERSION, self::CONTENT_VERSION );

		$redirect = add_query_arg(
			array(
				'page'              => 'cpm-nwp-settings',
				'tab'               => 'integration',
				'cpm_hb_legal_sync' => '1',
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Support email for legal copy (filterable).
	 *
	 * @return string
	 */
	public static function support_email() {
		$default = 'support@humanblockchain.info';
		$stored  = (string) get_option( 'cpm_hb_legal_support_email', $default );
		if ( '' === $stored ) {
			$stored = $default;
		}
		return (string) apply_filters( 'cpm_hb_legal_support_email', $stored );
	}

	/**
	 * @return string
	 */
	public static function site_name() {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * @return string
	 */
	public static function home_url() {
		return (string) home_url( '/' );
	}

	/**
	 * @return string
	 */
	public static function privacy_url() {
		$id = (int) get_option( self::OPTION_PRIVACY_PAGE_ID, 0 );
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return (string) $url;
			}
		}
		$page = get_page_by_path( self::SLUG_PRIVACY );
		if ( $page ) {
			return (string) get_permalink( $page );
		}
		return (string) home_url( '/' . self::SLUG_PRIVACY . '/' );
	}

	/**
	 * @return string
	 */
	public static function terms_url() {
		$id = (int) get_option( self::OPTION_TERMS_PAGE_ID, 0 );
		if ( $id > 0 ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return (string) $url;
			}
		}
		$page = get_page_by_path( self::SLUG_TERMS );
		if ( $page ) {
			return (string) get_permalink( $page );
		}
		return (string) home_url( '/' . self::SLUG_TERMS . '/' );
	}

	/**
	 * @return string
	 */
	public static function effective_date() {
		$stored = (string) get_option( self::OPTION_EFFECTIVE_DATE, '' );
		if ( '' !== $stored ) {
			return $stored;
		}
		return wp_date( 'F j, Y' );
	}

	/**
	 * Optional governing law state (filter or option).
	 *
	 * @return string
	 */
	public static function governing_state() {
		$state = (string) get_option( 'cpm_hb_legal_governing_state', '' );
		return (string) apply_filters( 'cpm_hb_legal_governing_state', $state );
	}

	/**
	 * Optional venue text (filter or option).
	 *
	 * @return string
	 */
	public static function governing_venue() {
		$venue = (string) get_option( 'cpm_hb_legal_governing_venue', '' );
		return (string) apply_filters( 'cpm_hb_legal_governing_venue', $venue );
	}

	/**
	 * Variables passed into legal partials.
	 *
	 * @return array<string, string>
	 */
	public static function template_vars() {
		return array(
			'site_name'       => self::site_name(),
			'home_url'        => self::home_url(),
			'privacy_url'     => self::privacy_url(),
			'terms_url'       => self::terms_url(),
			'support_email'   => self::support_email(),
			'effective_date'  => self::effective_date(),
			'governing_state' => self::governing_state(),
			'governing_venue' => self::governing_venue(),
		);
	}

	/**
	 * Render a legal template to HTML.
	 *
	 * @param string $template Basename without .php.
	 * @return string
	 */
	public static function render_template( $template ) {
		$path = plugin_dir_path( __FILE__ ) . 'legal/' . $template . '.php';
		if ( ! is_readable( $path ) ) {
			return '';
		}
		$hb_legal = self::template_vars();
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}

	/**
	 * @param string $slug Post slug.
	 * @param string $title Page title.
	 * @param string $template Template basename.
	 * @param string $option_id_option Option key for page ID.
	 * @param bool   $force Force update.
	 * @return int Post ID or 0.
	 */
	private static function upsert_page( $slug, $title, $template, $option_id_option, $force ) {
		$content = self::render_template( $template );
		if ( '' === $content ) {
			return 0;
		}

		$existing = get_page_by_path( $slug );
		$post_id  = 0;

		if ( $existing instanceof WP_Post ) {
			$post_id  = (int) $existing->ID;
			$managed  = (string) get_post_meta( $post_id, self::META_MANAGED, true );
			$is_empty = '' === trim( wp_strip_all_tags( $existing->post_content ) );

			if ( ! $force && '1' !== $managed && ! $is_empty ) {
				update_option( $option_id_option, $post_id );
				return $post_id;
			}

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_name'    => $slug,
				)
			);
		} else {
			$post_id = (int) wp_insert_post(
				array(
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_type'    => 'page',
				),
				true
			);
			if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
				return 0;
			}
		}

		update_post_meta( $post_id, self::META_MANAGED, '1' );
		update_option( $option_id_option, $post_id );

		return $post_id;
	}

	/**
	 * Twilio campaign opt-in blurb (matches on-site flow).
	 *
	 * @return string
	 */
	public static function twilio_opt_in_description() {
		return sprintf(
			/* translators: 1: privacy URL, 2: terms URL */
			__(
				'Users opt in on our website by entering their mobile phone number and clicking “Send OTP” to receive a one-time verification code. Consent is not required to purchase. Message frequency varies; typically one message per verification request. Message and data rates may apply. Reply STOP to opt out, HELP for help. Privacy: %1$s · Terms: %2$s',
				'cpm-humanblockchain'
			),
			self::privacy_url(),
			self::terms_url()
		);
	}
}
