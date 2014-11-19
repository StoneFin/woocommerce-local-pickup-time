<?php
/**
 * Local Pickup Time
 *
 * @package   Local_Pickup_Time
 * @author    Matt Banks <mjbanks@gmail.com>
 * @license   GPL-2.0+
 * @link      http://mattbanks.me
 * @copyright 2014 Matt Banks
 */

/**
 * Local_Pickup_Time class.
 * Defines public-facing functionality
 *
 * @package Local_Pickup_Time
 * @author  Your Name <mjbanks@gmail.com>
 */
class Local_Pickup_Time {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.2.0';

	/**
	 * Unique identifier for plugin.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'woocommerce-local-plugin-time';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Add the local pickup time field to the checkout page
		$public_hooked_location = apply_filters( 'local_pickup_time_select_location', 'woocommerce_after_order_notes' );
		add_action( $public_hooked_location, array( $this, 'time_select' ) );

		// Process the checkout
		add_action( 'woocommerce_checkout_process', array( $this, 'field_process' ) );

		// Update the order meta with local pickup time field value
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );

		// Add local pickup time field to order emails
		add_filter('woocommerce_email_order_meta_keys', array( $this, 'update_order_email' ) );

	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Local_Pickup_Time slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// No activation functionality needed... yet
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// No deactivation functionality needed... yet
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Create an array of times starting with an hour past the current time
	 * returns array($availableOptions[],$closedDateStrings[])
	 * @since    1.0.0
	 */
	public function create_hour_options() {
		// Make sure we have a time zone set
		$offset = get_option( 'gmt_offset' );
		$timezone_setting = get_option( 'timezone_string' );

		if ( $timezone_setting ) {
			date_default_timezone_set( get_option( 'timezone_string', 'America/New_York' ) );
		}
		else {
			$timezone = timezone_name_from_abbr( null, $offset * 3600, true );
			if( $timezone === false ) $timezone = timezone_name_from_abbr( null, $offset * 3600, false );
			date_default_timezone_set( $timezone );
		}

		// Get days closed textarea from settings, explode into an array
		$closing_days_raw = trim( get_option( 'local_pickup_hours_closings' ) );
		$closing_days = explode( "\n", $closing_days_raw );
		$closing_days = array_filter( $closing_days, 'trim' );

		// Get delay, interval, and number of days ahead settings
		$delay_minutes = get_option( 'local_pickup_delay_minutes', 60 );
		$interval = get_option( 'local_pickup_hours_interval', 30 );
		$delay_days = get_option( 'local_pickup_delay_days', 0 );
		$num_days_allowed = get_option( 'local_pickup_days_ahead', 1 );

		//sanity check
		$delay_days = $delay_days >= $num_days_allowed ? 0 : $delay_days;
		//i can foresee a request to support "business days" instead of just $delay_minutes = $delay_days*24*60+$delay_minutes;
		//If you're changing this make sure you finish implementing the rest of the business day logic kthx :)
		$delay_days_business_days = false;



		// Create an empty array for our dates
		$pickup_options = array();
		$current_time = time();


		// Loop through all days ahead and add the pickup time options to the array
		//delay_days defaults to 0
		for ( $i = $delay_days; $i < $num_days_allowed; $i++ ) {

			$current_date_string = date( 'm/d/Y', strtotime("+$i days", $current_time));
			// Get the date of current iteration
			$current_day_name = date( 'l', strtotime( "+$i days" ) );
			$current_day_name_lower = strtolower( $current_day_name );

			// Get the day's opening and closing times
			$open_time = get_option( 'local_pickup_hours_' . $current_day_name_lower . '_start', '10:00' );
			$close_time = get_option( 'local_pickup_hours_' . $current_day_name_lower . '_end', '19:00' );
			$tEnd = strtotime( $close_time );

			//it's a holiday, same day orders are possible and we're currently closed, or they're just straight up closed today.
			if ( in_array( $current_date_string, $closing_days ) || ($i == 0 && $current_time >= $tEnd ) || ($open_time == $close_time && ($open_time == 0 || $open_time == ''))) {
				// Set drop down text to let user know store is closed
				if($i == 0) {
					$reason = 'Pickup unavailable today';
				} else if ($open_time == $close_time && ($open_time == 0 || $open_time == '')) {
					$reason = 'Pickup unavailable on ' . $current_day_name . 's';
				} else {
					$reason = 'Pickup unavailable on ' . $current_day_name;
				}
				$pickup_options[] = array(''=>__($reason, $this->plugin_slug ));
				continue;
			}

			//if $i isn't today, then we don't care about the next available opening time, just set it to opening time.
			$start_time = $open_time;

			// Setup start and end times for pickup options
			if($delay_days_business_days && $delay_days > 0) {
				//todo fancy rounding logic...not sure how this should be implemented. Do we do $tNow + $nextClosestStartTime + $delay_days?
				//I think we need one more option for this to be fleshed out - orders received after hours are treated as received at the open of next business day? Maybe have a customizable time per day?,
				//orders received during open hours are treated as if they were recieved at end of day? Or beginning of day? I think for this to work we'll need a better interface for setting up order hours.
				//especially in places where orders are processed at night..they'd probably want to accept orders for the next business day up to midnight, even though the storefront closes at 6pm.
				throw new Exception('Business Day Delays aren\'t implemented yet! How did you get here?');
				$start_time = $open_time;
			} else {
				//not business days
				if($i == 0) {
					$now_plus_delay_seconds = time() + $delay;
					$pickup_time_begin_seconds = ceil($now_plus_delay_seconds / ($interval * 60)) * ($interval * 60) + ($interval * 60);
					//start_time is either the opening time or the next available pickup time
					$start_time = ($pickup_time_begin_seconds < strtotime( $open_time ) ) ? $open_time : date('g:i',$pickup_time_begin_seconds);
				}
			}

			// Today
			$tNow = strtotime( $start_time );
			// Create array of time options to return to woocommerce_form_field
			while ( $tNow <= $tEnd ) {
				$day_name = ( $i === 0 ) ? 'Today' : $current_day_name;
				$option_key = $current_day_name . date( "_h_i e", $tNow );
				$option_value = $day_name . ' ' . date( "g:i", $tNow );
				$pickup_options[] = array($option_key=>$option_value);
				$tNow = strtotime( "+$interval minutes", $tNow );
			}


		} // end for loop

		//if they're closed for all of the options, disable
		$flattened_pickup_options = array();
		$allClosed = true;
		$closedDays = array();
		foreach($pickup_options as $opt) {
			list($key,$val) = each($opt);
			if($key != ''){
				$allClosed = false;
				$flattened_pickup_options[$key] = $val;
			} else {
				$closedDays[] = $val;
			}
		}
		if($allClosed) {
			$flattened_pickup_options[''] = "No available pickup times";
			// Hide Order Review so user doesn't order anything today
			remove_action( 'woocommerce_checkout_order_review', 'woocommerce_order_review', 10 );
		}
		return array($flattened_pickup_options,$closedDays);
	}

	/**
	 * Add the local pickup time field to the checkout page
	 *
	 * @since    1.0.0
	 */
	public function time_select( $checkout ) {
		echo '<div id="local-pickup-time-select"><h2>' . __( 'Pickup Time', $this->plugin_slug ) . '</h2>';
		$hourOptions = self::create_hour_options();
		woocommerce_form_field( 'local_pickup_time_select', array(
			'type'          => 'select',
			'class'         => array( 'local-pickup-time-select-field form-row-wide' ),
			'label'         => __( 'Pickup Time', $this->plugin_slug ),
			'options'		=>	$hourOptions[0]
		), $checkout->get_value( 'local_pickup_time_select' ));
		if(!empty($hourOptions[1])) {
			echo '<p>' . join('<br />',$hourOptions[1]) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Process the checkout
	 *
	 * @since    1.0.0
	 */
	public function field_process() {
		global $woocommerce;

		// Check if set, if its not set add an error.
		if ( !$_POST['local_pickup_time_select'] )
			 $woocommerce->add_error( __( 'Please select a pickup time.', $this->plugin_slug ) );
	}

	/**
	 * Update the order meta with local pickup time field value
	 *
	 * @since    1.0.0
	 */
	public function update_order_meta( $order_id ) {
		if ( $_POST['local_pickup_time_select'] ) update_post_meta( $order_id, '_local_pickup_time_select', esc_attr( $_POST['local_pickup_time_select']) );
	}

	/**
	 * Add local pickup time field to order emails
	 *
	 * @since    1.0.0
	 */
	public function update_order_email( $keys ) {
		$keys['Pickup time'] = '_local_pickup_time_select';
		return $keys;
	}

}
