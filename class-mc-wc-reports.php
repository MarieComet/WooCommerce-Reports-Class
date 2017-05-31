<?php

/**
 * Reports class
 *
 * @author  Marie Comet
 * @package Get WooCommerce (REALLY) Best Sales
 * @version 1.0.0
 * See example of use case a the end of the file
 */

if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

require_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );

if ( !class_exists( 'MC_WC_Reports' ) ) {

    /**
    * Extend the WC_Admin_Report WooCommerce class (used in admin)
    * See the original class if needed 
    */

    class MC_WC_Reports extends WC_Admin_Report {
        
        /**
         * Constructor
         *
         * @access public
         * @since  1.0.0
         */
        public function __construct() {

        }

        public function get_best_sellers( $range = 'ever', $args = array() ) {
            $filter_range = false;
            if ( $range != 'ever' ) {
                $range_args = isset( $args[ 'range_args' ] ) ? $args[ 'range_args' ] : array();
                $this->calculate_current_range( $range, $range_args );
            }

            $limit = isset( $args[ 'limit' ] ) ? $args[ 'limit' ] : 100;

            $order_report_data_array = array(
                'data'         => array(
                    '_product_id' => array(
                        'type'            => 'order_item_meta',
                        'order_item_type' => 'line_item',
                        'function'        => '',
                        'name'            => 'product_id'
                    ),
                    '_qty'        => array(
                        'type'            => 'order_item_meta',
                        'order_item_type' => 'line_item',
                        'function'        => 'SUM',
                        'name'            => 'order_item_qty'
                    )
                ),
                'order_by'     => 'order_item_qty DESC',
                'group_by'     => 'product_id',
                'limit'        => 15,
                'query_type'   => 'get_results',
                'filter_range' => true,
                'order_types'  => wc_get_order_types( 'order-count' ),

            );

            if ( $limit == -1 )
                unset( $order_report_data_array[ 'limit' ] );

            // create the unique transient name for this request
            $transient_name = strtolower( get_class( $this ) );
            $arguments      = $range;
            if ( !empty( $args ) ) {
                foreach ( $args as $key => $value ) {
                    $arguments .= strtolower( $key ) . $value;
                }
            }

            $transient_name = $transient_name . md5( $arguments );

            $best_sellers = get_transient( $transient_name );

            if ( !$best_sellers ) {
                // delete transient
                delete_transient( strtolower( get_class( $this ) ) );
                // get data
                $best_sellers = $this->get_order_report_data( $order_report_data_array );
                // set the transient, with expiration one hour
                set_transient( $transient_name, $best_sellers, 3600 );
            }

            return $best_sellers;
        }


        /**
         * Get the current range and calculate the start and end dates
         *
         * @param  string $current_range
         */
        public function calculate_current_range( $current_range, $args = array() ) {

            switch ( $current_range ) {

                case 'year' :
                    $this->start_date    = strtotime( date( 'Y-01-01', current_time( 'timestamp' ) ) );
                    $this->end_date      = strtotime( 'midnight', current_time( 'timestamp' ) );
                    $this->chart_groupby = 'month';
                    break;

                case 'last_month' :
                    $first_day_current_month = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
                    $this->start_date        = strtotime( date( 'Y-m-01', strtotime( '-1 DAY', $first_day_current_month ) ) );
                    $this->end_date          = strtotime( date( 'Y-m-t', strtotime( '-1 DAY', $first_day_current_month ) ) );
                    $this->chart_groupby     = 'day';
                    break;

                case 'month' :
                    $this->start_date    = strtotime( date( 'Y-m-01', current_time( 'timestamp' ) ) );
                    $this->end_date      = strtotime( 'midnight', current_time( 'timestamp' ) );
                    $this->chart_groupby = 'day';
                    break;

                case 'yesterday':
                    $this->start_date    = strtotime( '-1 DAY midnight', current_time( 'timestamp' ) );
                    $this->end_date      = strtotime( 'midnight', current_time( 'timestamp' ) );
                    $this->chart_groupby = 'day';
                    break;

                case 'today':
                    $this->start_date    = strtotime( 'midnight', current_time( 'timestamp' ) );
                    $this->end_date      = strtotime( '+1 DAY midnight', current_time( 'timestamp' ) );
                    $this->chart_groupby = 'day';
                    break;

                case '7day' :
                    $this->start_date    = strtotime( '-6 days', current_time( 'timestamp' ) );
                    $this->end_date      = strtotime( 'midnight', current_time( 'timestamp' ) );
                    $this->chart_groupby = 'day';
                    break;
            }

            // Group by
            switch ( $this->chart_groupby ) {

                case 'day' :
                    $this->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date), DAY(posts.post_date)';
                    $this->chart_interval = ceil( max( 0, ( $this->end_date - $this->start_date ) / ( 60 * 60 * 24 ) ) );
                    $this->barwidth       = 60 * 60 * 24 * 1000;
                    break;

                case 'month' :
                    $this->group_by_query = 'YEAR(posts.post_date), MONTH(posts.post_date)';
                    $this->chart_interval = 0;
                    $min_date             = $this->start_date;

                    while ( ( $min_date = strtotime( "+1 MONTH", $min_date ) ) <= $this->end_date ) {
                        $this->chart_interval++;
                    }

                    $this->barwidth = 60 * 60 * 24 * 7 * 4 * 1000;
                    break;
            }

        }
    }
}

/**
*   Exemple of use :
*
*   $reports  = new MC_WC_Reports();
*   $best_sellers = $reports->get_best_sellers( 'year', array( 'limit' => 10 );
*
*   $best_sellers_products_id = array();
*   // Put best seller product id in an array
*   foreach ( $best_sellers as $product ) {
*       $best_sellers_products_id[] = absint( $product->product_id );
*   }
*   
*   You can use this array in a classic WP_Query, don't forget to use args 'orderby' => 'post__in' to keep IDs order !
*
*/