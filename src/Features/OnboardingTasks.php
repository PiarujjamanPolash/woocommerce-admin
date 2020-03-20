<?php
/**
 * WooCommerce Onboarding Tasks
 * NOTE: DO NOT edit this file in WooCommerce core, this is generated from woocommerce-admin.
 *
 * @package Woocommerce Admin
 */

namespace Automattic\WooCommerce\Admin\Features;

use \Automattic\WooCommerce\Admin\Loader;
use Automattic\WooCommerce\Admin\API\Reports\Taxes\Stats\DataStore;
use \Automattic\WooCommerce\Admin\Notes\WC_Admin_Notes_Onboarding;

/**
 * Contains the logic for completing onboarding tasks.
 */
class OnboardingTasks {
	/**
	 * Class instance.
	 *
	 * @var OnboardingTasks instance
	 */
	protected static $instance = null;

	/**
	 * Name of the active task transient.
	 *
	 * @var string
	 */
	const ACTIVE_TASK_TRANSIENT = 'wc_onboarding_active_task';

	/**
	 * Get class instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// This hook needs to run when options are updated via REST.
		add_action( 'add_option_woocommerce_task_list_complete', array( $this, 'add_completion_note' ), 10, 2 );

		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'add_media_scripts' ) );
		// Old settings injection.
		// Run after Onboarding.
		add_filter( 'woocommerce_components_settings', array( $this, 'component_settings' ), 30 );
		// New settings injection.
		add_filter( 'woocommerce_shared_settings', array( $this, 'component_settings' ), 30 );

		add_action( 'admin_init', array( $this, 'set_active_task' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_onboarding_product_notice_admin_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_onboarding_homepage_notice_admin_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_onboarding_tax_notice_admin_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_onboarding_product_import_notice_admin_script' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_onboarding_menu_experience_script' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function add_media_scripts() {
		wp_enqueue_media();
	}

	/**
	 * Add task items to component settings.
	 *
	 * @param array $settings Component settings.
	 * @return array
	 */
	public function component_settings( $settings ) {
		// Bail early if not on a wc-admin powered page, or task list shouldn't be shown.
		if (
			! \Automattic\WooCommerce\Admin\Loader::is_admin_page() ||
			! \Automattic\WooCommerce\Admin\Features\Onboarding::should_show_tasks()
		) {
			return $settings;
		}

		// @todo We may want to consider caching some of these and use to check against
		// task completion along with cache busting for active tasks.
		$settings['onboarding']['automatedTaxSupportedCountries'] = self::get_automated_tax_supported_countries();
		$settings['onboarding']['hasHomepage']                    = self::check_task_completion( 'homepage' ) || 'classic' === get_option( 'classic-editor-replace' );
		$settings['onboarding']['hasPhysicalProducts']            = count(
			wc_get_products(
				array(
					'virtual' => false,
					'limit'   => 1,
				)
			)
		) > 0;
		$settings['onboarding']['hasProducts']                    = self::check_task_completion( 'products' );
		$settings['onboarding']['isAppearanceComplete']           = get_option( 'woocommerce_task_list_appearance_complete' );
		$settings['onboarding']['isTaxComplete']                  = self::check_task_completion( 'tax' );
		$settings['onboarding']['shippingZonesCount']             = count( \WC_Shipping_Zones::get_zones() );
		$settings['onboarding']['stylesheet']                     = get_option( 'stylesheet' );
		$settings['onboarding']['taxJarActivated']                = class_exists( 'WC_Taxjar' );
		$settings['onboarding']['themeMods']                      = get_theme_mods();

		return $settings;
	}

	/**
	 * Temporarily store the active task to persist across page loads when neccessary (such as publishing a product). Most tasks do not need to do this.
	 */
	public static function set_active_task() {
		if ( isset( $_GET[ self::ACTIVE_TASK_TRANSIENT ] ) ) { // phpcs:ignore csrf ok.
			$task = sanitize_title_with_dashes( wp_unslash( $_GET[ self::ACTIVE_TASK_TRANSIENT ] ) ); // phpcs:ignore csrf ok.

			if ( self::check_task_completion( $task ) ) {
				return;
			}

			set_transient(
				self::ACTIVE_TASK_TRANSIENT,
				$task,
				DAY_IN_SECONDS
			);
		}
	}

	/**
	 * Get the name of the active task.
	 *
	 * @return string
	 */
	public static function get_active_task() {
		return get_transient( self::ACTIVE_TASK_TRANSIENT );
	}

	/**
	 * Check for active task completion, and clears the transient.
	 *
	 * @return bool
	 */
	public static function is_active_task_complete() {
		$active_task = self::get_active_task();

		if ( ! $active_task ) {
			return false;
		}

		if ( self::check_task_completion( $active_task ) ) {
			delete_transient( self::ACTIVE_TASK_TRANSIENT );
			return true;
		}

		return false;
	}

	/**
	 * Check for task completion of a given task.
	 *
	 * @param string $task Name of task.
	 * @return bool
	 */
	public static function check_task_completion( $task ) {
		switch ( $task ) {
			case 'products':
				$products = wp_count_posts( 'product' );
				return (int) $products->publish > 0 || (int) $products->draft > 0;
			case 'homepage':
				$homepage_id = get_option( 'woocommerce_onboarding_homepage_post_id', false );
				if ( ! $homepage_id ) {
					return false;
				}
				$post      = get_post( $homepage_id );
				$completed = $post && 'publish' === $post->post_status;
				return $completed;
			case 'tax':
				return 'yes' === get_option( 'wc_connect_taxes_enabled' ) || count( DataStore::get_taxes( array() ) ) > 0;
		}
		return false;
	}

	/**
	 * Hooks into the product page to add a notice to return to the task list if a product was added.
	 *
	 * @param string $hook Page hook.
	 */
	public static function add_onboarding_product_notice_admin_script( $hook ) {
		global $post;
		if (
			'post.php' !== $hook ||
			'product' !== $post->post_type ||
			'products' !== self::get_active_task() ||
			! self::is_active_task_complete()
		) {
			return;
		}

		wp_enqueue_script( 'onboarding-product-notice', Loader::get_url( 'wp-admin-scripts/onboarding-product-notice.js' ), array( 'wc-navigation', 'wp-i18n', 'wp-data' ), WC_ADMIN_VERSION_NUMBER, true );
	}

	/**
	 * Hooks into the post page to display a different success notice and sets the active page as the site's home page if visted from onboarding.
	 *
	 * @param string $hook Page hook.
	 */
	public static function add_onboarding_homepage_notice_admin_script( $hook ) {
		global $post;
		if ( 'post.php' === $hook && 'page' === $post->post_type && isset( $_GET[ self::ACTIVE_TASK_TRANSIENT ] ) && 'homepage' === $_GET[ self::ACTIVE_TASK_TRANSIENT ] ) { // phpcs:ignore csrf ok.
			wp_enqueue_script( 'onboarding-homepage-notice', Loader::get_url( 'wp-admin-scripts/onboarding-homepage-notice.js' ), array( 'wc-navigation', 'wp-i18n', 'wp-data' ), WC_ADMIN_VERSION_NUMBER, true );
		}
	}

	/**
	 * Adds a notice to return to the task list when the save button is clicked on tax settings pages.
	 */
	public static function add_onboarding_tax_notice_admin_script() {
		$page = isset( $_GET['page'] ) ? $_GET['page'] : ''; // phpcs:ignore csrf ok, sanitization ok.
		$tab  = isset( $_GET['tab'] ) ? $_GET['tab'] : ''; // phpcs:ignore csrf ok, sanitization ok.

		if (
			'wc-settings' === $page &&
			'tax' === $tab &&
			'tax' === self::get_active_task() &&
			! self::is_active_task_complete()
		) {
			wp_enqueue_script( 'onboarding-tax-notice', Loader::get_url( 'wp-admin-scripts/onboarding-tax-notice.js' ), array( 'wc-navigation', 'wp-i18n', 'wp-data' ), WC_ADMIN_VERSION_NUMBER, true );
		}
	}

	/**
	 * Adds a notice to return to the task list when the product importeris done running.
	 *
	 * @param string $hook Page hook.
	 */
	public function add_onboarding_product_import_notice_admin_script( $hook ) {
		$step = isset( $_GET['step'] ) ? $_GET['step'] : ''; // phpcs:ignore csrf ok, sanitization ok.
		if ( 'product_page_product_importer' === $hook && 'done' === $step && 'product-import' === self::get_active_task() ) {
			delete_transient( self::ACTIVE_TASK_TRANSIENT );
			wp_enqueue_script( 'onboarding-product-import-notice', Loader::get_url( 'wp-admin-scripts/onboarding-product-import-notice.js' ), array( 'wc-navigation', 'wp-i18n', 'wp-data' ), WC_ADMIN_VERSION_NUMBER, true );
		}
	}

	/**
	 * Add a script that does some fine tuning around the WooCommerce menu experience during onboarding
	 * (hiding menu items while the task list is showing and the rest of the WooCommerce dashboard is
	 * not shown).
	 */
	public function add_onboarding_menu_experience_script() {
		if ( ! Loader::is_onboarding_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'onboarding-menu-experience',
			Loader::get_url( 'wp-admin-scripts/onboarding-menu-experience.js' ),
			array( WC_ADMIN_APP ),
			Loader::get_file_version( 'wp-admin-scripts/onboarding-menu-experience.js' ),
			true
		);
	}

	/**
	 * Get an array of countries that support automated tax.
	 *
	 * @return array
	 */
	public static function get_automated_tax_supported_countries() {
		// https://developers.taxjar.com/api/reference/#countries .
		$tax_supported_countries = array_merge(
			array( 'US', 'CA', 'AU' ),
			WC()->countries->get_european_union_countries()
		);

		return $tax_supported_countries;
	}

	/**
	 * Add the task list completion note after completing all tasks.
	 *
	 * @param mixed $old_value Old value.
	 * @param mixed $new_value New value.
	 */
	public static function add_completion_note( $old_value, $new_value ) {
		if ( $new_value ) {
			WC_Admin_Notes_Onboarding::add_task_list_complete_note();
		}
	}
}
