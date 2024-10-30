<?php
/*
Plugin Name: Cairn
Plugin URI: http://braydon.com
Description: Art Exhibition Gallery & Shop
Version: 0.1.5
Author: Braydon Fuller
Author URI: http://braydon.com/
License: GPLv2 or later
*/

/*
Copyright (C) 2013 Braydon Fuller <http://braydon.com/>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

define('USE_EXT', 'BCMATH');

require('includes/class-cairn-admin.php');
require('includes/class-cairn-email.php');
include('includes/class-cairn-rewrite.php');
include('includes/class-cairn-copyright.php');
include('includes/class-cairn-fineart-post.php');
include('includes/class-cairn-portfolio-post.php');
include('includes/class-cairn-post.php');
require('includes/class-cairn-shipping.php');
require('includes/class-cairn-postset.php');

add_theme_support( 'post-thumbnails' );

// GPG
include('includes/gpg/GPG.php');
add_filter( 'wp_mail_from', 'Cairn_Email::mail_from' );
add_filter( 'wp_mail_from_name', 'Cairn_Email::mail_from_name' );

// Bitcoin
include('includes/phpecc/classes/util/bcmath_Utils.php');
include('includes/phpecc/classes/interface/CurveFpInterface.php');
include('includes/phpecc/classes/CurveFp.php');
include('includes/phpecc/classes/interface/PointInterface.php');
include('includes/phpecc/classes/Point.php');
include('includes/phpecc/classes/NumberTheory.php');
include('includes/phpqrcode/qrlib.php');
include('includes/class-cairn-bitcoin-wallet.php');

// Stripe
include('includes/stripe/lib/Stripe.php');
include('includes/class-cairn-stripe-payment.php');

add_action( 'init', 'Cairn_Shipping::init' );
add_action( 'init', 'Cairn::initialize' );
add_action( 'init', 'Cairn_Bitcoin_Wallet::initialize' );
add_action( 'init', 'Cairn_Stripe_Payment::initialize' );
add_action( 'init', 'Cairn_Fineart_Post::init' );
add_action( 'init', 'Cairn_Portfolio_Post::init' );
add_action( 'init', 'Cairn_Post::init' );
add_action( 'init', 'Cairn::flush_rules' );
add_action( 'init', 'Cairn::set_cookie' );

// Add our ajax
add_action( 'wp_ajax_nopriv_cairn_contact', 'Cairn_Email::ajax_contact' );
add_action( 'wp_ajax_cairn_contact', 'Cairn_Email::ajax_contact' );

add_action( 'wp_ajax_nopriv_posts_set_add_post', 'Cairn_Post_Set::ajax_posts_set_add_post' );
add_action( 'wp_ajax_nopriv_posts_set_remove_post', 'Cairn_Post_Set::ajax_posts_set_remove_post' );
add_action( 'wp_ajax_nopriv_posts_set_quantity', 'Cairn_Post_Set::ajax_posts_set_quantity' );
add_action( 'wp_ajax_nopriv_posts_set_calculate', 'Cairn_Post_Set::ajax_posts_set_calculate' );

add_action( 'wp_ajax_posts_set_add_post', 'Cairn_Post_Set::ajax_posts_set_add_post' );
add_action( 'wp_ajax_posts_set_remove_post', 'Cairn_Post_Set::ajax_posts_set_remove_post' );
add_action( 'wp_ajax_posts_set_quantity', 'Cairn_Post_Set::ajax_posts_set_quantity' );
add_action( 'wp_ajax_posts_set_calculate', 'Cairn_Post_Set::ajax_posts_set_calculate' );

// Payment Processing
add_action( 'wp_ajax_posts_set_bitcoin_process_invoice', 'Cairn_Bitcoin_Wallet::ajax_process_invoice' );
add_action( 'wp_ajax_nopriv_posts_set_bitcoin_process_invoice', 'Cairn_Bitcoin_Wallet::ajax_process_invoice' );

add_action( 'wp_ajax_posts_set_credit_process_invoice', 'Cairn_Stripe_Payment::ajax_process_invoice' );
add_action( 'wp_ajax_nopriv_posts_set_credit_process_invoice', 'Cairn_Stripe_Payment::ajax_process_invoice' );
add_action( 'wp_ajax_posts_set_credit_authorize_invoice', 'Cairn_Stripe_Payment::ajax_authorize_invoice' );
add_action( 'wp_ajax_nopriv_posts_set_credit_authorize_invoice', 'Cairn_Stripe_Payment::ajax_authorize_invoice' );

add_action( 'wp_ajax_posts_set_bitcoin_status_invoice', 'Cairn_Bitcoin_Wallet::ajax_status_invoice' );
add_action( 'wp_ajax_nopriv_posts_set_bitcoin_status_invoice', 'Cairn_Bitcoin_Wallet::ajax_status_invoice' );
add_action( 'wp_ajax_posts_set_credit_status_invoice', 'Cairn_Stripe_Payment::ajax_status_invoice' );
add_action( 'wp_ajax_nopriv_posts_set_credit_status_invoice', 'Cairn_Stripe_Payment::ajax_status_invoice' );

// Prevent WordPress from generating resized images on upload
add_filter('intermediate_image_sizes_advanced','Cairn::image_sizes_advanced', 1, 1);
add_filter('wp_generate_attachment_metadata','Cairn::generate_metadata', 1, 2);

// Installation
register_activation_hook( __FILE__, 'Cairn_Post_Set::db_install' );
register_activation_hook( __FILE__, 'Cairn_Bitcoin_Wallet::db_install' );
register_activation_hook( __FILE__, 'Cairn::activate_cron' );
register_deactivation_hook( __FILE__, 'Cairn::deactivate_cron' );

// Cron
add_filter( 'cron_schedules', 'Cairn::scheduled_intervals' );
add_action( 'cairn_bitcoin_rates_cron', 'Cairn::bitcoin_rates_cron' );
add_action( 'cairn_status_cron', 'Cairn::update_order_statuses' );
add_action( 'cairn_bitcoin_addresses_cron', 'Cairn_Bitcoin_Wallet::generate_addresses' );

/**
 * A fast and secure art gallery and Internet shop.
 *
 * @package cairn
 */
class Cairn {

	public static $version = '0.1.4';
	public static $hold_expires_in_secs = 7200; // 120 minutes
	public static $image_sizes;
	public static $views_path;
	public static $hash = false;

	/**
	 * Callback to output a server response hooked into template_redirect. For any URL add "?type=json" to return a JSON object instead of standard HTML.
	 */
	static function response() {

	    global $wp_query;
	    global $posts;

		$cart = false;
		$items = false;

		self::hash();

		// preparation

		if ( !isset( $wp_query->query_vars['posts_set'] ) ) {

			// for all responses except cart

			if ( !Cairn_Post_Set::check_cart_status( self::$hash ) ) {

				// make sure that the cart is status cart					

				self::reset_cookie();

				$id = sha1( rand() );
				$success = Cairn_Post_Set::save( $id, self::$hash, array() );

			}

			$items = Cairn::loop();

	        unset($posts);

		} else {
	
			// for the cart and checkout

			$posts_set_status = @$wp_query->query_vars['posts_set_status'];
			$cart = Cairn_Post_Set::get( self::$hash );

			if ( !$cart ) {

				// there isn't a cart available

				$id = sha1( rand() );
				$success = Cairn_Post_Set::save( $id, self::$hash, array() );
				$cart = Cairn_Post_Set::get( self::$hash );

			}

			if ( $posts_set_status == 'cart' && $cart['status'] != 'cart' ) {

				// cart is not a cart

				self::reset_cookie();

				$id = sha1( rand() );
				$success = Cairn_Post_Set::save( $id, self::$hash, array() );
				$cart = Cairn_Post_Set::get( self::$hash );

			}
		}

		$contact = Cairn_Email::contact_info();

		// rendering output

		if ( isset( $wp_query->query_vars['view'] ) && 
			$wp_query->query_vars['view'] == 'feed' ) {

			// feeds xml

			$items = Cairn::loop();

			if ( isset( $wp_query->query_vars['feed'] ) ) {

				$format = $wp_query->query_vars['feed'];

			} else {

				$format = 'rss2';

			}

			$feedspace = array( 'items' => $items, 'mediaurl' => self::static_url('/'), 'format' => $format );

			$post_type = $wp_query->query_vars['post_type'];

	        ob_start();
			if ( !$format || $format == 'rss2' ) {
				self::display( 'feeds/'.$post_type.'/rss2.php', $feedspace );
			} else if ( $format == 'atom' ) {
				self::display( 'feeds/'.$post_type.'/atom.php', $feedspace );
			} else if ( $format == 'rdf' ) {
				self::display( 'feeds/'.$post_type.'/rdf.php', $feedspace );
			} else if ( $format == 'rss' ) {
				self::display( 'feeds/'.$post_type.'/rss.php', $feedspace );
			}
	        $body = ob_get_contents();
	        ob_end_clean();

	    } else if ( isset( $_REQUEST['type'] ) && $_REQUEST['type'] == 'json' ) {

			// json

			$wp_response = array( 
				'items' => $items,
				'contact' => $contact
			);
			
			if ( isset( $_REQUEST['request'] ) ) {
				$wp_response['request'] = $_REQUEST['request'];
			}
			

			if ( isset( $cart ) ) {
				$wp_response['cart'] = $cart;
			}
			
			$body = json_encode( $wp_response );

		} else {
			
			// html

	        ob_start();
	        self::display('main.php', array( 'contact' => $contact, 'cart' => $cart, 'items' => $items, 'mediaurl' => self::static_url(''), 'request' => $_REQUEST ) );
	        $body = ob_get_contents();
	        ob_end_clean();

		}

		print $body;

		exit(0);

	}


	/**
	* Prepares items to have all available information retrieved and accessible for templating. Returns an array of associative arrays that is later available as a JSON object.
	*
	* @return array 
	* - id	_integer_ The post ID.
	* - title _string_ The title of the item.
	* - type _string_ The type of item such as 'post' or 'fineart'.
	* - permalink _string_ The URL to the item.
	* - body _string_ The body text of the item if available.
	* - date _string_ The datetime of the item in MySQL datetime format.
	* - attachments _array_ Available images for the item.
	* - pretty_date _string_ The date in human friendly format.
	* - pubdate _string_ The date for feeds. 
	* - author _string_ The name of the author.
	* - excerpt _string_ The excerpt for the item.
	* - guid _string_ The globally unique identifier for the item.
	* - permalink_next _string_ The permalink for the next item.
	* - permalink_previous _string_ The Permalink for the previous item.
	* - license _array_ The copyright license for the item as associative array. 
	*   - rights _array_ An array of the individual rights such as 'by', 'sa', 'nc', and 'nd'.
	*   - author _string_ The author's name.
	*   - author_url  _string_ The url of the author's website to attribute.
	*   - url _string_ The url of the license.
	*   - jurisdiction _string_ The jurisdiction of the license.
	*   - title _string_ The title of the license.
	* - downloads _array_ The downloads available for the item
	*   - 'name' 
	*   - 'format' 
	*   - 'size' 
	*   - 'url' 
	* - attributions _array_ The references and attributions for the item. 
	*   - 'html'
	* - details _array_ The dimentional details of the item. 
	*   - 'width'
	*   - 'height'
	*   - 'depth'
	*   - 'description'
	* - options _array_ The available options to purchase the item. 
	*   - 'name'
	*   - 'printing-cost'
	*   - 'framing-cost'
	*   - 'production-cost'
	*   - 'artist-fee'
	*   - 'weight'
	*   - 'width'
	*   - 'height'
	*   - 'length'
	*   - 'ondemand'
	*   - 'quantity'
	*   - 'available'
	*/
	static public function loop(){

		$items = array();
		
		if ( have_posts() ) {

			add_filter('excerpt_more', 'Cairn::ajax_excerpt_more');
			add_filter('excerpt_length', 'Cairn::ajax_excerpt_length');

			while ( have_posts() ) {

				global $post;

				the_post();

				$permalink = get_permalink( $post->ID );

				$next_post =  get_next_post();
				$previous_post =  get_previous_post();
				if ( $next_post ) {
					$permalink_next = get_permalink( $next_post->ID );
				} else {
					$permalink_next = false;
				}

				if ( $previous_post ) {
					$permalink_previous = get_permalink( $previous_post->ID );
				} else {
					$permalink_previous = false;
				}

				$image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), "full");

				$item = array(
					'id' => $post->ID,
					'title' => apply_filters( 'the_title', $post->post_title ),
					'type' => $post->post_type,
					'permalink' => $permalink,
					'body' => apply_filters( 'the_content', $post->post_content ),
					'date' => $post->post_date,
					'image' => $image,
					'pretty_date' => get_the_date(),
					'pubdate' => mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false),
					'author' => get_the_author(),
					'excerpt' => get_the_excerpt(),
					'guid' => get_the_guid(),
					'permalink_next' => $permalink_next,
					'permalink_previous' => $permalink_previous
				);

				$videos = get_post_meta( $post->ID, 'videos', true);
				$videos_feed = get_post_meta( $post->ID, 'videos_feed', true);
				$c = 0;

				if ( is_array( $videos ) ) {
					foreach ( $videos as &$video ) {
						if ( $videos_feed == $c ) {
							$video['feed'] = true;
						} else {
							$video['feed'] = false;
						}
						$video['url'] = wp_get_attachment_url( $video['id'] );
						$video['meta'] = wp_get_attachment_metadata( $video['id'] );
						$video['type'] = $video['type'];//$video['meta']['mime_type'];
						$c++;
					}
					$item['videos'] = $videos;

				}

				$license = get_post_meta( $post->ID, 'license', true);
				if ( $license ) {
					$item['license'] = $license;
				}

				$downloads = get_post_meta( $post->ID, 'downloads', true);
				if ( $downloads ) {
					$item['downloads'] = $downloads;
				}

				$attributions = get_post_meta( $post->ID, 'attributions', true);
				if ( $attributions ) {
					$item['attributions'] = $attributions;
				}

				$details = get_post_meta( $post->ID, 'details', true);
				if ( $details ) {
					if ( isset( $details['description'] ) ) { 
						$details['description'] = apply_filters('the_content', $details['description'] );
					}
					$item['details'] = $details;
					
				}

				$sales = get_post_meta( $post->ID, 'purchase_options', true);

				if ( $sales ) {

					foreach( $sales as $index => $option ) {

						if ( isset( $option['ondemand'] ) && $option['ondemand'] ) {

							$sales[$index]['available'] = true;

						} else {

							$holds = Cairn_Post_Set::holds( $post->ID, $index );

							$solds = Cairn_Post_Set::solds( $post->ID, $index );

							$sales[$index]['holds'] = $holds;

							$available = $option['quantity'] - $holds - $solds;

							if ( $available > 0 ) {

								$sales[$index]['available'] = $available;

							} else {

								$sales[$index]['available'] = false;

							}
						}
					}
					$item['options'] = $sales;
				}

				$items[] = $item;
			}

		}
		
		return $items;

	}


	/**
	 * Gets the permalink for a post.
	 *
	 * @param object $post The post object.
	 * @param boolean $leavename Weither or not leave the slug name.

	 * @return string The permalink for the post.
	 */
	static function post_uri($post, $leavename=false){

		if ( $post->post_name ) { 

			$post_name = $post->post_name;

		} else {

			$post_name = sanitize_title($post->post_title);

		}

		if ( in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft' ) ) ) {
			return home_url( '?p=' . $post->ID );
		}

		$uri = '';

	    switch( $post->post_type ){

	    case 'page':

	      $uri = Cairn_Rewrite::uri(array(
					       'post_type' => 'page', 
					       'pagename' => $post_name
					       ), 
					 'default', 
					 true, 
					 $leavename
				 );
	      break;

	    case 'post':

	      $year = mysql2date("Y", $post->post_date);
	      $month = mysql2date("n", $post->post_date);

		  $uri = Cairn_Rewrite::uri(array(
				       'post_type' => 'post', 
				       'year' => $year, 
				       'monthnum' => $month, 
				       'name' => $post_name
				       ), 
				 'default', 
				 true, 
				 $leavename
				 );
	      break;

	    case 'attachment':

	      $uri = Cairn_Rewrite::uri(array(
				       'post_type' => 'attachment', 
				       'name' => $post_name
				       ), 
				 'default', 
				 true, 
				 $leavename
				 );
		  break;

	    case 'fineart':

	      $uri = Cairn_Rewrite::uri(array(
				       'post_type' => 'fineart', 
				       'name' => $post_name
				       ), 
				 'default', 
				 true, 
				 $leavename
				 );
		  break;

	    case 'portfolio':

	      $uri = Cairn_Rewrite::uri(array(
				       'post_type' => 'portfolio', 
				       'name' => $post_name
				       ), 
				 'default', 
				 true, 
				 $leavename
				 );
		  break;
		}

		$permalink = trailingslashit(get_bloginfo('url')).$uri;

		$permalink = apply_filters( 'post_uri', $permalink, $post, $leavename );

		return $permalink;

	}


	/**
	 * Includes template from template directory with variables extracted.
	 *
	 * @param string $path Relative path from templates directory.
	 * @param array $namespace An associative array of variables for the template.

	 * @return
	 */
	public static function display( $path, $namespace ) {

		extract($namespace);

		$view = Cairn::$views_path . $path;

		if ( file_exists( $view ) ) {
			include( $view );
		} else {
			trigger_error( 'No template at "'.$path.'" found.', E_USER_WARNING );
		}

	}

	/**
	 * Returns the full URL to the static directory.
	 *
	 * @param string $url Relative URL to static resource.

	 * @return string 
	 */
	static public function static_url( $url ) {
		if ( $url ) {
			return plugins_url('cairn/static') . $url . '?v=' . self::$version;
		} else {
			return plugins_url('cairn/static') . $url;
		};

	}

	/**


	/**
	 * Callback to get the value of the hash from the posts set cookie.
	 */
	public static function hash() {
		if ( isset( $_COOKIE['cairn_selector_hash'] ) ) {
			self::$hash = $_COOKIE['cairn_selector_hash']; 
			return self::$hash;
		} else if ( isset( $HTTP_COOKIE_VARS['cairn_selector_hash'] ) ) {
			self::$hash = $HTTP_COOKIE_VARS['cairn_selector_hash'];
			return self::$hash;
		} else {
			return false;
		}
	}

	/**
	 * Callback to reset the cookie for the posts sets.
	 */
	public static function reset_cookie() {
        if ( isset( $_COOKIE['cairn_selector_hash'] ) ) {
			$domain = str_replace( 'http://', '', get_bloginfo('url') );
			$domain = str_replace( 'https://', '', $domain );
			$domain = str_replace( 'www.', '', $domain );
			self::$hash = sha1( rand() );
			if ( $domain == 'localhost' || preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $domain ) ) {
				$domain = FALSE;
			} else {
				$domain = '.'.$domain;
			}
			setcookie( 'cairn_selector_hash', self::$hash, 0, '/', $domain, false, false );
		}
	}

	/**
	 * Callback to set the cookie for the posts sets.
	 */
	public static function set_cookie() {
        if ( !isset( $_COOKIE['cairn_selector_hash'] ) ) {
			$domain = str_replace( 'http://', '', get_bloginfo('url') );
			$domain = str_replace( 'https://', '', $domain );
			$domain = str_replace( 'www.', '', $domain );
			self::$hash = sha1( rand() );
			if ( $domain == 'localhost' || preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $domain ) ) {
				$domain = FALSE;
			} else {
				$domain = '.'.$domain;
			}
			//$expire = time() + ( 86400 * 7 );
			setcookie( 'cairn_selector_hash', self::$hash, 0, '/', $domain, false, false );
		}
	}


	/**
	 * Adds cron intervals to available schedules.
	 *
	 * @param array $schedules Existing schedules array

	 * @return array
	 */
	static public function scheduled_intervals( $schedules ){

		$schedules['seconds_30'] = array(
			'interval' => 30,     
			'display' => __('Once every 30 seconds')
		);
		$schedules['minutes_2.5'] = array(
			'interval' => 2.5 * 60, 
			'display' => __('Once every 2.5 minutes')
		);
		$schedules['minutes_5'] = array(
			'interval' => 5 * 60,   
			'display' => __('Once every 5 minutes')
		);

		return $schedules;
	}


	/**
	 * Callback to activate cron schedules
	 */
	static public function activate_cron() {
		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'cairn_bitcoin_rates_cron' );
		wp_schedule_event( current_time( 'timestamp' ), 'minutes_2.5', 'cairn_status_cron' );
		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'cairn_bitcoin_addresses_cron' );
	}

	/**
	 * Callback to deactivate cron schedules
	 */
	static public function deactivate_cron() {
		wp_clear_scheduled_hook( 'cairn_bitcoin_rates_cron' );
		wp_clear_scheduled_hook( 'cairn_status_cron' );
		wp_clear_scheduled_hook( 'cairn_bitcoin_addresses_cron' );
	}

	/**
	 * Callback to update the status of all post sets on hold.
	 */
	static public function update_order_statuses(){

		global $wpdb;

		$query = "SELECT * FROM " . $wpdb->prefix . "posts_set p WHERE p.status='hold' OR p.status='received'";

		$orders = $wpdb->get_results( $query );

		$count = 0;

		if ( is_array( $orders ) ) {
	
			foreach ( $orders as $order ) {

				$count++;

				if ( $order->method == 'btc' ) {					

					$status = Cairn_Bitcoin_Wallet::status_invoice( $order );

				} else if ( $order->method == 'credit' ) {

					$status = Cairn_Stripe_Payment::status_invoice( $order );

				}

			}

			return $count;
	
		}

	}

	/**
	 * Callback to update bitcoin exchange rates
	 */
	static public function bitcoin_rates_cron(){

		global $wpdb;

		$bitcoin_exchange_rates = get_option( 'bitcoin_exchange_rates', true );
		$response = wp_remote_get( 'http://api.bitcoincharts.com/v1/weighted_prices.json' );
		if ( is_array($response) && $response['response']['code'] == 200 ) {
			$bitcoin_exchange_rates = json_decode( $response['body'] );
			if ( $bitcoin_exchange_rates ) {
				update_option( 'bitcoin_exchange_rates', $bitcoin_exchange_rates );
			}
		}

	}

	/**
	 * Callback to generate imaginary metadata for images since they will be generate on-the-fly.
	 */
	static function generate_metadata($meta, $id) {

		$attachment = get_post( $id );

		if ( preg_match('!^image/!', get_post_mime_type( $attachment ) ) ) {							

			foreach (self::$image_sizes as $sizename => $size) {

				// figure out what size WP would make this:
				$newsize = image_resize_dimensions($meta['width'], $meta['height'], $size['width'], $size['height'], $size['crop']);

				if ($newsize) {
					$info = pathinfo($meta['file']);
					$ext = $info['extension'];
					$name = wp_basename($meta['file'], ".$ext");

					$suffix = '?w='.$newsize[4].'&h='.$newsize[5];

					$resized = array(
						'file' => $name.'.'.$ext.$suffix,
						'width' => $newsize[4],
						'height' => $newsize[5],
					);

					$meta['sizes'][$sizename] = $resized;
				}
			}

		}

		return $meta;
	}

	/**
	 * Callback to store intermediate image sizes.
	 */
	static function image_sizes_advanced($sizes) {

		self::$image_sizes = $sizes;
		return array();
	}

	/**
	 * Callback to change excerpt more text.
	 */
	static function ajax_excerpt_more(){
		return '...';
	}

	/**
	 * Callback to change excerpt more length.
	 */
	static function ajax_excerpt_length(){
		return 40;
	}


	/**
	 * Callback to initialize settings and add callbacks into hooks
	 */
	static function initialize() {

		global $wpdb;

		$path = dirname( __FILE__ );

		$cairn = json_decode( file_get_contents( $path.'/static/urls.json' ), true );

		Cairn_Rewrite::set_rules( $cairn['uri'] );

	    // Add our new rewrite rules based on our configuration.
	    update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );
	    add_action( 'generate_rewrite_rules', 'Cairn::generate_rewrite_rules' );

		// Avoid confusion of features by turning off a few administration tabs.
	    add_filter( 'setup_theme', 'Cairn_Admin::disable_themes' );
	    add_action( 'admin_menu', 'Cairn_Admin::remove_admin_menus' );

		// Add carts and orders page
		add_action( 'admin_menu', 'Cairn_Admin::add_admin_menus' );

		// Variables
		add_filter( 'query_vars', 'Cairn::add_query_vars' );

	    // Parse the request
	    add_action( 'parse_request', 'Cairn::parse_request' );

	    // Parse the query
	    add_action( 'parse_query', 'Cairn::parse_query' );

	    // Generate our http response
	    add_action( 'template_redirect', 'Cairn::response' );

	    // Update hooks into our URI rewriting structure
	    add_filter( 'post_type_link', 'Cairn::post_uri_filter', 1, 3 );
	    add_filter( 'post_link', 'Cairn::post_uri_filter', 1, 3 );
	    add_filter( 'page_link', 'Cairn::page_uri_filter', 1, 3 );

		self::$views_path = dirname(__FILE__).'/views/';

	}

	/**
	 * Callback to flush the rewrite rules.
	 */
	static function flush_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	/**
	 * Callback to parse the request hooked into parse_request
	 */
	static function parse_request(&$request) {

		if ( ( $request->matched_rule === NULL || 
			$request->matched_rule == '') && 
			strpos($request->request, 'wp-admin') !== 0 ) {
			header("Status: 404 Not Found");
			self::display( '404.php', array('mediaurl' => self::static_url('') ) );
			exit(0);
		}
		return $request;
	}

	/**
	 * Callback to parse the query hooked into parse_query
	 */
	static function parse_query(&$query) {

		if (isset($query->query_vars['numberposts'])){
			$query->set('posts_per_page', $query->query_vars['numberposts']);
		};

		return $query;
	}

	/**
	 * Callback to add query variables hooked into query_vars
	 */
	static function add_query_vars($query_vars) {
		// Add our template query variable for WP_Query
		$query_vars[] = 'template';
		$query_vars[] = 'view';
		$query_vars[] = 'robots';
		$query_vars[] = 'feed_type';
		$query_vars[] = 'numberposts';
		$query_vars[] = 'can_be_empty';
		$query_vars[] = 'posts_set';
		$query_vars[] = 'posts_set_status';
		return $query_vars;
	}


	/**
	 * Callback to generate rewrite rules hooked into generate_rewrite_rules
	 */
	static function generate_rewrite_rules() {

	    Cairn_Rewrite::generate_rules();

	    global $wp_rewrite;

	    $wp_rewrite->extra_rules_top = array();
	    $wp_rewrite->extra_permastructs = array();
	    $wp_rewrite->rules = &Cairn_Rewrite::$rules;

	}  

	/**
	 * Callback to return the uri for pages hooked into page_link
	 *
	 * @param string $permalink The existing permalink.
	 * @param object $post The post object.
	 * @param object $leavename Weither or not to leave the slug name.

	 * @return string Permalink for the page.
	 */
	static function page_uri_filter( $permalink, $post, $leavename ) {
		$post = get_post($post);
		return self::post_uri($post, $leavename);
	}

	/**
	 * Callback to return the uri for posts hooked into post_link and post_type_link
	 *
	 * @param string $permalink The existing permalink.
	 * @param object $post The post object.
	 * @param object $leavename Weither or not to leave the slug name.

	 * @return string Permalink for the post.
	 */
	static function post_uri_filter($permalink, $post, $leavename) {
		return self::post_uri($post, $leavename);
	}
}

?>