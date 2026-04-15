<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://codepixelzmedia.com/
 * @since      1.0.0
 *
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Cpm_Humanblockchain
 * @subpackage Cpm_Humanblockchain/includes
 * @author     Codepixelz Media <dev@codepixelzmedia.com.np>
 */
class Cpm_Humanblockchain {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Cpm_Humanblockchain_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'CPM_HUMANBLOCKCHAIN_VERSION' ) ) {
			$this->version = CPM_HUMANBLOCKCHAIN_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'cpm-humanblockchain';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Cpm_Humanblockchain_Loader. Orchestrates the hooks of the plugin.
	 * - Cpm_Humanblockchain_i18n. Defines internationalization functionality.
	 * - Cpm_Humanblockchain_Admin. Defines all hooks for the admin area.
	 * - Cpm_Humanblockchain_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-loader.php';

		/**
		 * The class responsible for plugin activation and table upgrades.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-activator.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-cpm-humanblockchain-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-cpm-humanblockchain-public.php';

		/**
		 * OTP Service - Twilio SMS for device activation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-otp-service.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-membership.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-register-user-api.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-smallstreet-backorders.php';

		/**
		 * Device Registry - handles device registration.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cpm-humanblockchain-device-registry.php';

		$this->loader = new Cpm_Humanblockchain_Loader();

		Cpm_Humanblockchain_Device_Registry::init();
		Cpm_Humanblockchain_Membership::init();
		$this->loader->add_action( 'plugins_loaded', 'Cpm_Humanblockchain_Activator', 'maybe_upgrade_nwp_devices', 5 );

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Cpm_Humanblockchain_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Cpm_Humanblockchain_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Cpm_Humanblockchain_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'register_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Cpm_Humanblockchain_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'register_shortcodes' );
		$this->loader->add_filter( 'the_content', $plugin_public, 'append_backorders_to_page_content', 20 );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'maybe_print_backorders_mount_footer', 5 );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_filter( 'body_class', $plugin_public, 'body_class_landing_entry', 10, 1 );
		$this->loader->add_filter( 'wp_nav_menu_items', $plugin_public, 'add_register_device_button_to_menu', 10, 2 );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'render_landing_entry_modal', 4 );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'strip_landing_skip_gate_query_param', 5 );
		$this->loader->add_action( 'wp_footer', $plugin_public, 'render_device_registration_modal', 6 );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Cpm_Humanblockchain_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
