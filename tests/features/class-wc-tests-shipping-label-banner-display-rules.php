<?php
/**
 * Shipping Label Banner Display Rules tests.
 *
 * @package WooCommerce\Tests\Shipping-label-banner-display-rules
 */

use \Automattic\WooCommerce\Admin\Features\ShippingLabelBannerDisplayRules;

/**
 * Class WC_Tests_Shipping_Label_Banner_Display_Rules
 */
class WC_Tests_Shipping_Label_Banner_Display_Rules extends WC_Unit_Test_Case {

	/**
	 * Jetpack version to test the display manager.
	 *
	 * @var string
	 */
	private $valid_jetpack_version = '4.4';

	/**
	 * Stores the default WordPress options stored in teh database.
	 *
	 * @var array
	 */
	private static $modified_options = array(
		'woocommerce_default_country'              => null,
		'woocommerce_currency'                     => null,
		'woocommerce_shipping_prompt_ab'           => null,
		'woocommerce_shipping_dismissed_timestamp' => null,
	);

	/**
	 * Setup for every single test.
	 */
	public function setUp() {
		parent::setup();

		update_option( 'woocommerce_default_country', 'US' );
		update_option( 'woocommerce_currency', 'USD' );
	}

	/**
	 * Setup for the whole test class.
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		foreach ( self::$modified_options as $option_name => $option_value ) {
			self::$modified_options[ $option_name ] = $option_value;
		}
	}

	/**
	 * Cleans up test data once all test have run.
	 */
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();

		foreach ( self::$modified_options as $option_name => $option_value ) {
			update_option( $option_name, $option_value );
		}
	}

	/**
	 * Test if the banner is displayed when all conditions are satisfied:
	 *   - Banner NOT dismissed
	 *   - Jetpack >= 4.4 installed and active
	 *   - Jetpack Connected
	 *   - No incompatible extensions installed:
	 *       - Shipstation not installed
	 *       - UPS not Installed
	 *       - Fedex not installed
	 *       - ShippingEasy not installed
	 *   - Order contains physical products which need to be shipped (we should check that the order status is not set to complete)
	 *   - Store is located in US
	 *   - Store currency is set to USD
	 *   - WCS plugin not installed OR WCS is installed *AND* ToS have NOT been accepted *AND* WCS version is 1.22.5 or greater
	 *     (The 1.22.5 or greater requirement is so we can launch the shipping modal from the banner)
	 */
	public function test_display_banner_if_all_conditions_are_met() {
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), true );
			}
		);
	}

	/**
	 * Test if the banner is hidden when Jetpack is not active.
	 */
	public function test_if_banner_hidden_when_jetpack_disconnected() {
		$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( null, null, null, null, null );

		$this->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
	}

	/**
	 * Test if the banner is hidden when a dismiss banner option is checked.
	 */
	public function test_if_banner_hidden_when_dismiss_option_enabled() {
		update_option( 'woocommerce_shipping_dismissed_timestamp', -1 );
		$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

		$this->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
	}

	/**
	 * Test if the banner is hidden when a dismiss banner option is checked for 24 hours.
	 */
	public function test_if_banner_hidden_when_dismiss_after_24h_option_enabled() {
		$two_hours_from_now = time() + ( 2 * 60 * 60 );
		update_option( 'woocommerce_shipping_dismissed_timestamp', $two_hours_from_now );

		$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

		$this->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
	}

	/**
	 * Test if the banner is hidden when a dismiss banner option is checked for 24 hours.
	 */
	public function test_show_banner_if_dismiss_after_24h_option_enabled_has_expired() {
		$two_hours_from_now = time() - ( 2 * 60 * 60 );
		update_option( 'woocommerce_shipping_dismissed_timestamp', $two_hours_from_now );

		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), true );
			}
		);
	}

	/**
	 * Test if the banner is hidden when no shippable product available.
	 */
	public function test_if_banner_hidden_when_no_shippable_product() {
		$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

		$this->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
	}

	/**
	 * Test if the banner is displayed when the store is in the US.
	 */
	public function test_if_banner_hidden_when_store_is_not_in_us() {
		update_option( 'woocommerce_default_country', 'ES' );
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is displayed when the store's currency is USD.
	 */
	public function test_if_banner_hidden_when_currency_is_not_usd() {
		update_option( 'woocommerce_currency', 'EUR' );
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is hidden when an incompatible plugin is installed
	 */
	public function test_if_banner_hidden_when_incompatible_plugin_installed() {
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, true );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is hidden when Jetpack version is not at least 4.4.
	 */
	public function test_if_banner_hidden_when_jetpack_version_is_old() {
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.3', true, '1.22.5', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is hidden when the WooCommerce Services Terms of Service has been already accepted.
	 */
	public function test_if_banner_hidden_when_wcs_tos_accepted() {
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', true, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is hidden when WooCommerce Services is installed but not up to date.
	 */
	public function test_if_banner_hidden_when_wcs_not_installed() {
		$this->with_order(
			function( $that ) {
				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.4', false, false );

				$that->assertEquals( $shipping_label_banner_display_rules->should_allow_banner(), false );
			}
		);
	}

	/**
	 * Test if the banner is displayed when site is in 'b' group.
	 */
	public function test_display_banner_if_b_flag() {
		$this->with_order(
			function( $that ) {
				update_option( 'woocommerce_shipping_prompt_ab', 'b' );

				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );
				$that->assertEquals( $shipping_label_banner_display_rules->should_display_banner(), true );
			}
		);
	}

	/**
	 * Test if the banner is displayed when site is in 'a' group.
	 */
	public function test_no_display_banner_if_a_flag() {
		$this->with_order(
			function( $that ) {
				update_option( 'woocommerce_shipping_prompt_ab', 'a' );

				$shipping_label_banner_display_rules = new ShippingLabelBannerDisplayRules( '4.4', true, '1.22.5', false, false );
				$that->assertEquals( $shipping_label_banner_display_rules->should_display_banner(), false );
			}
		);
	}

	/**
	 * Creates a test order.
	 */
	private function create_order() {
		$product = WC_Helper_Product::create_simple_product();
		$product->set_props( array( 'virtual' => true ) );

		$order      = new WC_Order();
		$order_item = new WC_Order_Item_Product();
		$order_item->set_props( array( 'product' => $product ) );
		$order->add_item( $order_item );
		$order->save();

		global $post;

		// phpcs:disable 	WordPress.WP.GlobalVariablesOverride.Prohibited
		$post     = new \stdClass();
		$post->ID = $order->get_id();

		return $order;
	}

	/**
	 * Destroys the test order.
	 *
	 * @param object $order to destroy.
	 */
	private function destroy_order( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$product->delete( true );
			$item->delete( true );
		}

		$order->delete( true );
	}

	/**
	 * Wraps a function call within an order creation/deletion lifecycle.
	 *
	 * @param function $callback to wrap.
	 */
	private function with_order( $callback ) {
		$order = $this->create_order();

		$callback( $this );

		$this->destroy_order( $order );
	}

}