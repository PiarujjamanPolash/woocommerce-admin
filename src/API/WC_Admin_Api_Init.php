<?php
/**
 * REST API bootstrap.
 *
 * @package WooCommerce Admin/Classes
 */

namespace Automattic\WooCommerce\Admin\API;

defined( 'ABSPATH' ) || exit;

/**
 * WC_Admin_Api_Init class.
 */
class WC_Admin_Api_Init {

	/**
	 * Boostrap REST API.
	 */
	public function __construct() {
		// Hook in data stores.
		add_filter( 'woocommerce_data_stores', array( __CLASS__, 'add_data_stores' ) );
		// REST API extensions init.
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		add_filter( 'rest_endpoints', array( __CLASS__, 'filter_rest_endpoints' ), 10, 1 );

		// Add currency symbol to orders endpoint response.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( __CLASS__, 'add_currency_symbol_to_order_response' ) );
	}

	/**
	 * Init REST API.
	 */
	public function rest_api_init() {
		$controllers = array(
			'Automattic\WooCommerce\Admin\API\Notes\Controller',
			'Automattic\WooCommerce\Admin\API\Notes\Actions\Controller',
			'WC_Admin_REST_Coupons_Controller',
			'WC_Admin_REST_Customers_Controller',
			'WC_Admin_REST_Data_Controller',
			'WC_Admin_REST_Data_Countries_Controller',
			'WC_Admin_REST_Data_Download_Ips_Controller',
			'WC_Admin_REST_Leaderboards_Controller',
			'WC_Admin_REST_Orders_Controller',
			'WC_Admin_REST_Products_Controller',
			'WC_Admin_REST_Product_Categories_Controller',
			'WC_Admin_REST_Product_Variations_Controller',
			'WC_Admin_REST_Product_Reviews_Controller',
			'WC_Admin_REST_Product_Variations_Controller',
			'WC_Admin_REST_Reports_Controller',
			'WC_Admin_REST_Setting_Options_Controller',
			'WC_Admin_REST_Reports_Import_Controller',
			'WC_Admin_REST_Reports_Export_Controller',
			'WC_Admin_REST_Reports_Products_Controller',
			'WC_Admin_REST_Reports_Variations_Controller',
			'WC_Admin_REST_Reports_Products_Stats_Controller',
			'WC_Admin_REST_Reports_Revenue_Stats_Controller',
			'WC_Admin_REST_Reports_Orders_Controller',
			'WC_Admin_REST_Reports_Orders_Stats_Controller',
			'WC_Admin_REST_Reports_Categories_Controller',
			'WC_Admin_REST_Reports_Taxes_Controller',
			'WC_Admin_REST_Reports_Taxes_Stats_Controller',
			'WC_Admin_REST_Reports_Coupons_Controller',
			'WC_Admin_REST_Reports_Coupons_Stats_Controller',
			'WC_Admin_REST_Reports_Stock_Controller',
			'WC_Admin_REST_Reports_Stock_Stats_Controller',
			'WC_Admin_REST_Reports_Downloads_Controller',
			'WC_Admin_REST_Reports_Downloads_Stats_Controller',
			'WC_Admin_REST_Reports_Customers_Controller',
			'WC_Admin_REST_Reports_Customers_Stats_Controller',
			'WC_Admin_REST_Taxes_Controller',
			'WC_Admin_REST_Themes_Controller',
		);

		if ( \WC_Admin_Loader::is_feature_enabled( 'onboarding' ) ) {
			$controllers = array_merge(
				$controllers,
				array(
					'WC_Admin_REST_Onboarding_Levels_Controller',
					'WC_Admin_REST_Onboarding_Profile_Controller',
					'WC_Admin_REST_Onboarding_Plugins_Controller',
				)
			);
		}

		// The performance indicators controller must be registered last, after other /stats endpoints have been registered.
		$controllers[] = 'WC_Admin_REST_Reports_Performance_Indicators_Controller';

		$controllers = apply_filters( 'woocommerce_admin_rest_controllers', $controllers );

		foreach ( $controllers as $controller ) {
			$this->$controller = new $controller();
			$this->$controller->register_routes();
		}
	}

	/**
	 * Filter REST API endpoints.
	 *
	 * @param array $endpoints List of endpoints.
	 * @return array
	 */
	public static function filter_rest_endpoints( $endpoints ) {
		// Override GET /wc/v4/system_status/tools.
		if ( isset( $endpoints['/wc/v4/system_status/tools'] )
			&& isset( $endpoints['/wc/v4/system_status/tools'][1] )
			&& isset( $endpoints['/wc/v4/system_status/tools'][0] )
			&& $endpoints['/wc/v4/system_status/tools'][1]['callback'][0] instanceof WC_Admin_REST_System_Status_Tools_Controller
		) {
			$endpoints['/wc/v4/system_status/tools'][0] = $endpoints['/wc/v4/system_status/tools'][1];
		}
		// // Override GET & PUT for /wc/v4/system_status/tools.
		if ( isset( $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'] )
			&& isset( $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][3] )
			&& isset( $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][2] )
			&& $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][2]['callback'][0] instanceof WC_Admin_REST_System_Status_Tools_Controller
			&& $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][3]['callback'][0] instanceof WC_Admin_REST_System_Status_Tools_Controller
		) {
			$endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][0] = $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][2];
			$endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][1] = $endpoints['/wc/v4/system_status/tools/(?P<id>[\w-]+)'][3];
		}

		// Override GET /wc/v4/reports.
		if ( isset( $endpoints['/wc/v4/reports'] )
			&& isset( $endpoints['/wc/v4/reports'][1] )
			&& isset( $endpoints['/wc/v4/reports'][0] )
			&& $endpoints['/wc/v4/reports'][1]['callback'][0] instanceof WC_Admin_REST_Reports_Controller
		) {
			$endpoints['/wc/v4/reports'][0] = $endpoints['/wc/v4/reports'][1];
		}

		// Override /wc/v4/coupons.
		if ( isset( $endpoints['/wc/v4/coupons'] )
			&& isset( $endpoints['/wc/v4/coupons'][3] )
			&& isset( $endpoints['/wc/v4/coupons'][2] )
			&& $endpoints['/wc/v4/coupons'][2]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
			&& $endpoints['/wc/v4/coupons'][3]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
		) {
			$endpoints['/wc/v4/coupons'][0] = $endpoints['/wc/v4/coupons'][2];
			$endpoints['/wc/v4/coupons'][1] = $endpoints['/wc/v4/coupons'][3];
		}

		// Override /wc/v4/customers.
		if ( isset( $endpoints['/wc/v4/customers'] )
			&& isset( $endpoints['/wc/v4/customers'][3] )
			&& isset( $endpoints['/wc/v4/customers'][2] )
			&& $endpoints['/wc/v4/customers'][2]['callback'][0] instanceof WC_Admin_REST_Customers_Controller
			&& $endpoints['/wc/v4/customers'][3]['callback'][0] instanceof WC_Admin_REST_Customers_Controller
		) {
			$endpoints['/wc/v4/customers'][0] = $endpoints['/wc/v4/customers'][2];
			$endpoints['/wc/v4/customers'][1] = $endpoints['/wc/v4/customers'][3];
		}

		// Override /wc/v4/orders/$id.
		if ( isset( $endpoints['/wc/v4/orders/(?P<id>[\d]+)'] )
			&& isset( $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][5] )
			&& isset( $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][4] )
			&& isset( $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][3] )
			&& $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][3]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
			&& $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][4]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
			&& $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][5]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
		) {
			$endpoints['/wc/v4/orders/(?P<id>[\d]+)'][0] = $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][3];
			$endpoints['/wc/v4/orders/(?P<id>[\d]+)'][1] = $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][4];
			$endpoints['/wc/v4/orders/(?P<id>[\d]+)'][2] = $endpoints['/wc/v4/orders/(?P<id>[\d]+)'][5];
		}

		// Override /wc/v4/orders.
		if ( isset( $endpoints['/wc/v4/orders'] )
			&& isset( $endpoints['/wc/v4/orders'][3] )
			&& isset( $endpoints['/wc/v4/orders'][2] )
			&& $endpoints['/wc/v4/orders'][2]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
			&& $endpoints['/wc/v4/orders'][3]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
		) {
			$endpoints['/wc/v4/orders'][0] = $endpoints['/wc/v4/orders'][2];
			$endpoints['/wc/v4/orders'][1] = $endpoints['/wc/v4/orders'][3];
		}

		// Override /wc/v4/data.
		if ( isset( $endpoints['/wc/v4/data'] )
			&& isset( $endpoints['/wc/v4/data'][1] )
			&& $endpoints['/wc/v4/data'][1]['callback'][0] instanceof WC_Admin_REST_Data_Controller
		) {
			$endpoints['/wc/v4/data'][0] = $endpoints['/wc/v4/data'][1];
		}

		// Override /wc/v4/products.
		if ( isset( $endpoints['/wc/v4/products'] )
			&& isset( $endpoints['/wc/v4/products'][3] )
			&& isset( $endpoints['/wc/v4/products'][2] )
			&& $endpoints['/wc/v4/products'][2]['callback'][0] instanceof WC_Admin_REST_Products_Controller
			&& $endpoints['/wc/v4/products'][3]['callback'][0] instanceof WC_Admin_REST_Products_Controller
		) {
			$endpoints['/wc/v4/products'][0] = $endpoints['/wc/v4/products'][2];
			$endpoints['/wc/v4/products'][1] = $endpoints['/wc/v4/products'][3];
		}

		// Override /wc/v4/products/$id.
		if ( isset( $endpoints['/wc/v4/products/(?P<id>[\d]+)'] )
			&& isset( $endpoints['/wc/v4/products/(?P<id>[\d]+)'][5] )
			&& isset( $endpoints['/wc/v4/products/(?P<id>[\d]+)'][4] )
			&& isset( $endpoints['/wc/v4/products/(?P<id>[\d]+)'][3] )
			&& $endpoints['/wc/v4/products/(?P<id>[\d]+)'][3]['callback'][0] instanceof WC_Admin_REST_Products_Controller
			&& $endpoints['/wc/v4/products/(?P<id>[\d]+)'][4]['callback'][0] instanceof WC_Admin_REST_Products_Controller
			&& $endpoints['/wc/v4/products/(?P<id>[\d]+)'][5]['callback'][0] instanceof WC_Admin_REST_Products_Controller
		) {
			$endpoints['/wc/v4/products/(?P<id>[\d]+)'][0] = $endpoints['/wc/v4/products/(?P<id>[\d]+)'][3];
			$endpoints['/wc/v4/products/(?P<id>[\d]+)'][1] = $endpoints['/wc/v4/products/(?P<id>[\d]+)'][4];
			$endpoints['/wc/v4/products/(?P<id>[\d]+)'][2] = $endpoints['/wc/v4/products/(?P<id>[\d]+)'][5];
		}

		// Override /wc/v4/products/categories.
		if ( isset( $endpoints['/wc/v4/products/categories'] )
			&& isset( $endpoints['/wc/v4/products/categories'][3] )
			&& isset( $endpoints['/wc/v4/products/categories'][2] )
			&& $endpoints['/wc/v4/products/categories'][2]['callback'][0] instanceof WC_Admin_REST_Product_categories_Controller
			&& $endpoints['/wc/v4/products/categories'][3]['callback'][0] instanceof WC_Admin_REST_Product_categories_Controller
		) {
			$endpoints['/wc/v4/products/categories'][0] = $endpoints['/wc/v4/products/categories'][2];
			$endpoints['/wc/v4/products/categories'][1] = $endpoints['/wc/v4/products/categories'][3];
		}

		// Override /wc/v4/products/reviews.
		if ( isset( $endpoints['/wc/v4/products/reviews'] )
			&& isset( $endpoints['/wc/v4/products/reviews'][3] )
			&& isset( $endpoints['/wc/v4/products/reviews'][2] )
			&& $endpoints['/wc/v4/products/reviews'][2]['callback'][0] instanceof WC_Admin_REST_Product_Reviews_Controller
			&& $endpoints['/wc/v4/products/reviews'][3]['callback'][0] instanceof WC_Admin_REST_Product_Reviews_Controller
		) {
			$endpoints['/wc/v4/products/reviews'][0] = $endpoints['/wc/v4/products/reviews'][2];
			$endpoints['/wc/v4/products/reviews'][1] = $endpoints['/wc/v4/products/reviews'][3];
		}

		// Override /wc/v4/products/$product_id/variations.
		if ( isset( $endpoints['products/(?P<product_id>[\d]+)/variations'] )
			&& isset( $endpoints['products/(?P<product_id>[\d]+)/variations'][3] )
			&& isset( $endpoints['products/(?P<product_id>[\d]+)/variations'][2] )
			&& $endpoints['products/(?P<product_id>[\d]+)/variations'][2]['callback'][0] instanceof WC_Admin_REST_Product_Variations_Controller
			&& $endpoints['products/(?P<product_id>[\d]+)/variations'][3]['callback'][0] instanceof WC_Admin_REST_Product_Variations_Controller
		) {
			$endpoints['products/(?P<product_id>[\d]+)/variations'][0] = $endpoints['products/(?P<product_id>[\d]+)/variations'][2];
			$endpoints['products/(?P<product_id>[\d]+)/variations'][1] = $endpoints['products/(?P<product_id>[\d]+)/variations'][3];
		}

		// Override /wc/v4/taxes.
		if ( isset( $endpoints['/wc/v4/taxes'] )
			&& isset( $endpoints['/wc/v4/taxes'][3] )
			&& isset( $endpoints['/wc/v4/taxes'][2] )
			&& $endpoints['/wc/v4/taxes'][2]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
			&& $endpoints['/wc/v4/taxes'][3]['callback'][0] instanceof WC_Admin_REST_Orders_Controller
		) {
			$endpoints['/wc/v4/taxes'][0] = $endpoints['/wc/v4/taxes'][2];
			$endpoints['/wc/v4/taxes'][1] = $endpoints['/wc/v4/taxes'][3];
		}

		// Override /wc/v4/settings/$group_id.
		if ( isset( $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'] )
			&& isset( $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][5] )
			&& isset( $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][4] )
			&& isset( $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][3] )
			&& $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][3]['callback'][0] instanceof WC_Admin_REST_Setting_Options_Controller
			&& $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][4]['callback'][0] instanceof WC_Admin_REST_Setting_Options_Controller
			&& $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][5]['callback'][0] instanceof WC_Admin_REST_Setting_Options_Controller
		) {
			$endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][0] = $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][3];
			$endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][1] = $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][4];
			$endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][2] = $endpoints['/wc/v4/settings/(?P<group_id>[\w-]+)'][5];
		}

		return $endpoints;
	}

	/**
	 * Adds data stores.
	 *
	 * @param array $data_stores List of data stores.
	 * @return array
	 */
	public static function add_data_stores( $data_stores ) {
		return array_merge(
			$data_stores,
			array(
				'report-revenue-stats'   => 'WC_Admin_Reports_Orders_Stats_Data_Store',
				'report-orders'          => 'WC_Admin_Reports_Orders_Data_Store',
				'report-orders-stats'    => 'WC_Admin_Reports_Orders_Stats_Data_Store',
				'report-products'        => 'WC_Admin_Reports_Products_Data_Store',
				'report-variations'      => 'WC_Admin_Reports_Variations_Data_Store',
				'report-products-stats'  => 'WC_Admin_Reports_Products_Stats_Data_Store',
				'report-categories'      => 'WC_Admin_Reports_Categories_Data_Store',
				'report-taxes'           => 'WC_Admin_Reports_Taxes_Data_Store',
				'report-taxes-stats'     => 'WC_Admin_Reports_Taxes_Stats_Data_Store',
				'report-coupons'         => 'WC_Admin_Reports_Coupons_Data_Store',
				'report-coupons-stats'   => 'WC_Admin_Reports_Coupons_Stats_Data_Store',
				'report-downloads'       => 'WC_Admin_Reports_Downloads_Data_Store',
				'report-downloads-stats' => 'WC_Admin_Reports_Downloads_Stats_Data_Store',
				'admin-note'             => 'WC_Admin_Notes_Data_Store',
				'report-customers'       => 'WC_Admin_Reports_Customers_Data_Store',
				'report-customers-stats' => 'WC_Admin_Reports_Customers_Stats_Data_Store',
				'report-stock-stats'     => 'WC_Admin_Reports_Stock_Stats_Data_Store',
			)
		);
	}

	/**
	 * Add the currency symbol (in addition to currency code) to each Order
	 * object in REST API responses. For use in formatCurrency().
	 *
	 * @param {WP_REST_Response} $response REST response object.
	 * @returns {WP_REST_Response}
	 */
	public static function add_currency_symbol_to_order_response( $response ) {
		$response_data                    = $response->get_data();
		$currency_code                    = $response_data['currency'];
		$currency_symbol                  = get_woocommerce_currency_symbol( $currency_code );
		$response_data['currency_symbol'] = html_entity_decode( $currency_symbol );
		$response->set_data( $response_data );

		return $response;
	}
}