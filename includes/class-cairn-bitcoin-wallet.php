<?php
/**
 * The Cairn Bitcoin Wallet Class
 *
 * Copyright (C) 2013 Braydon Fuller <http://braydon.com/> 

 * This is module is largely based on code from Bitcoin Payments for WooCommerce <http://www.bitcoinway.com/>

 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.

 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @license https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * @author Braydon Fuller <braydon.com>
 * @package cairn
 *
*/

/**
 * Electrum Bitcoin Wallet to accept payments in Cairn
 *
 * @package cairn
 */
class Cairn_Bitcoin_Wallet {

	static public $master_public_key;
	static public $table_name;
	static public $funds_received_value_expires_in_secs; 
	static public $assigned_address_expires_in_secs;
	static public $confirmations_required;
	static public $blockchain_api_timeout_secs;
	static public $blockchain_api_max_failures;
	static public $max_unusable_generated_addresses;
	static public $starting_index_for_new_btc_addresses;	
	static public $max_unused_addresses_buffer;

	/**
	* AJAX wrapper for status_invoice, will print JSON object of the returned status.
	*/
	static public function ajax_status_invoice() {

		global $wpdb;

		// lets make sure that this request hasnt been submitted twice
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'cairn_nonce' ) ) {

			print json_encode( array(
				'id' => false,
				'end' => true,
				'success' => false,
				'message' => 'Please refresh page and try again, invalid nonce.'
			));
		
			exit(0);

		}

		$request = $_REQUEST;
		$request['requested_by_ip'] = @$_SERVER['REMOTE_ADDR'];
		$request['datetime'] = date('Y-m-d H:i:s T');

		$hash = Cairn::hash();

		$sql = $wpdb->prepare( 'SELECT * FROM '.$wpdb->prefix.'posts_set p WHERE p.hash=%s', $hash );

		$order = $wpdb->get_row( $sql );

		$result = self::status_invoice( $order, $request );

		print json_encode( $result );

		exit(0);
					
	}

	/**
	* Updates the status of a post_set and returns an assocative array of the status of the posts set.
	*
	*
	* @param object $order The posts_set object.
	* @param array $request Request variables posted from the order form.

	* @return array 
	* - success _boolean_ If the status check was successful.
	* - end _boolean_ If this is the last check that is nessasary.
	* - expired _boolean_ If the post_set has expired.
	* - seconds_remaining _integer_ The number of seconds remaining until expired.
	* - message _string_ The text that should be displayed to the user.
	* - request _array_ Returning the request variables back which has customer information.
	* - confirmed _boolean_ If the post_set has been confirmed.
	* - confirmations _integer_ The number of confirmations.
	* - confirmations_required _integer_ The number of confirmations nessasary to be confirmed.
	* - confirmation _string_ The unque hash used as order identification.
	* - btc_address _string_ The bitcoin address that funds should be sent.
	* - received_btc _float_ The number of received bitcoins for received status.
	* - paid_btc _float_ The number of paid bitcoins for confirmed status. 
	* - date _string_ The datetime is MySQL format.
	* 
	*/
	static public function status_invoice( $order, $request = array() ) {

		global $wpdb;

		$return = array();

		if ( !$order ) {
			return array(
				'success' => false,
				'end' => true,
				'message' => 'Order information is nolonger available, session has likely been reset.',
				'request' => $request
			);
		}

		// confirmed by the cron, return confirmed status
		if ( $order->status == 'confirmed' ) {

			$request['confirmed'] = true;
			$request['confirmations'] = self::$confirmations_required;
			$request['confirmations_required'] = self::$confirmations_required;
			$request['confirmation'] = $order->confirmation;
			$request['btc_address'] = $order->btc_address;
			$request['paid_btc'] = $order->paid_btc;
			$request['date'] = current_time('mysql');

			return array(
				'success' => true,
				'end' => true,
				'expired' => false,
				'received' => true,
				'request' => $request
			);
		}

		if ( $order->status != 'hold' && $order->status != 'received' && $order->status != 'expired' ) {
			return array(
				'success' => false,
				'end' => true,
				'message' => 'The status can not be checked at this time.',
				'request' => $request
			);
		}

		$expired = Cairn_Post_Set::hold_expired( $order );

		if ( $order->status == 'expired' || $expired === true ) {

			return array(
				'success' => true,
				'end' => true,
				'expired' => true,
				'request' => $request
			);

		} else {

			// check if payment has been made in full 
			$confirmed_info = self::received_info( $order->btc_address, self::$confirmations_required, self::$blockchain_api_timeout_secs );

			if ( $confirmed_info['result'] == 'success' ) {

				// update database to the latest status of funds

				$current_time = time();

				$query = "UPDATE `".self::$table_name."` SET `total_received_funds` = '{".$confirmed_info['balance']."}', `received_funds_checked_at`='".$current_time."' WHERE `btc_address`='".$order->btc_address."';";

				$ret_code = $wpdb->query( $query );


				// check if payment has been made in full 

				if ( $confirmed_info['balance'] >= $order->total_btc ) {

					$confirmation_code = sha1( $order->btc_address . $order->id . $confirmed_info['balance'] );
					$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET confirmation=%s, status='confirmed', paid_datetime=%s, paid_btc=%s WHERE p.id=%s AND p.btc_address=%s;", $confirmation_code, current_time('mysql', true), $confirmed_info['balance'], $order->id, $order->btc_address );

					$confirmed_status = $wpdb->query( $sql_status );

					$current_time = time();

					$query = "UPDATE `".self::$table_name."` SET `status`='used' WHERE `btc_address`='".$order->btc_address."';";
					$ret_code = $wpdb->query( $query );

					$request['confirmed'] = true;
					$request['confirmations'] = self::$confirmations_required;
					$request['confirmations_required'] = self::$confirmations_required;
					$request['confirmation'] = $confirmation_code;
					$request['btc_address'] = $order->btc_address;
					$request['paid_btc'] = $confirmed_info['balance'];
					$request['date'] = current_time('mysql');

					return array(
						'success' => true,
						'end' => true,
						'expired' => false,
						'received' => true,
						'request' => $request
					);

				} 

			}

			// lets do a check to see if the transaction is received 
			$received_info = self::received_info( $order->btc_address, false );

			if ( $received_info['result'] == 'success' && $received_info['balance'] >= $order->total_btc ) {

				$confirmations = 0;

				$received_btc = $received_info['balance'];

				for ( $i = 1; $i < self::$confirmations_required; $i++ ) {

					$confirmations_info = self::received_info( $order->btc_address, $i );

					if ( $confirmations_info['result'] == 'success' && $confirmations_info['balance'] > 0 ) {
						$received_btc = $confirmations_info['balance'];

						$confirmations = $i;

					} else {

						break;
					}

				}

				$confirmation_code = sha1( $order->btc_address . $order->id . $received_info['balance'] );

				$received_sql = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET confirmation=%s, status='received', received_datetime=%s, received_btc=%s WHERE p.id=%s AND p.btc_address=%s;", $confirmation_code, current_time('mysql', true), $received_info['balance'], $order->id, $order->btc_address );

				$received_status = $wpdb->query( $received_sql );

				$request['confirmations'] = $confirmations;
				$request['confirmations_required'] = self::$confirmations_required;
				$request['confirmation'] = $confirmation_code;
				$request['btc_address'] = $order->btc_address;
				$request['received_btc'] = $received_btc;
				$request['date'] = current_time('mysql');

				return array(
					'success' => true,
					'end' => false,
					'expired' => false,
					'received' => true,
					'request' => $request
				);

			}

			$request['btc_address'] = $order->btc_address;
			$request['date'] = current_time('mysql');

			return array(
				'success' => true,
				'end' => false,
				'expired' => false,
				'received' => false,
				'seconds_remaining' => $expired,
				'request' => $request
			);

		}

	}

	/**
	* AJAX wrapper for process_invoice, will print JSON object from returned status.
	*/
	static public function ajax_process_invoice() {

		// lets make sure that this request hasnt been submitted twice
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'cairn_nonce' ) ) {

			print json_encode( array(
				'id' => false,
				'success' => false,
				'message' => 'Please refresh page and try again, invalid nonce.'
			));
		
			exit(0);

		}

		$request = $_REQUEST;
		$request['requested_by_ip'] = @$_SERVER['REMOTE_ADDR'];
		$request['datetime'] = date('Y-m-d H:i:s T');
		// basic checks?			
	  
		$result = self::process_invoice( $request );

		print json_encode( $result );

		if ( !$result['success'] ) {

			$message = "Someone is trying to checkout and experienced an error:\n\n".$result['message'];
			Cairn_Email::secure_email( '['.get_bloginfo('name').' Bitcoin]', $message );

		}

		exit(0);

	}
	
	/**
	* Processes a post_set with the status of a cart and changes it to a status of hold and saves information from $request to the database.
	*
	*
	* @param object $order The posts_set object.
	* @param array $request Request variables posted from the order form.

	* @return array 
	* - id _boolean_ If the status check was successful.
	* - success _boolean_ If the status check was successful.
	* - message _string_ The text that should be displayed to the user.
	* - request _array_ Returning the request variables back which has customer information.
	* - bitcoin_address _string_ The bitcoin address that funds should be sent.
	* - bitcoin_qrcode _string_ A base64 encoded image string of data for the qrcode.
	* - seconds_remaining _integer_ The number of seconds remaining until the hold will expire.
	*/
	static public function process_invoice( $request ) {

		global $wpdb;

		$hash = Cairn::hash();

		$cart = Cairn_Post_Set::get( $hash );

		if ( !$cart ) {

			return array(
				'id' => false,
				'success' => false,
				'bitcoin_address' => false,
				'request' => $request,
				'message' => 'That cart does not exist.'
			);

		} else {

			$id = $cart['id'];

			foreach ( $cart['products'] as $product ) {

				$available = Cairn_Post_Set::available( $product['quantity'], $product['id'], $product['option'] );

				if ( !$available ) {
					return array(
						'id' => $id,
						'success' => false,
						'bitcoin_address' => false,
						'request' => $request,
						'message' => 'Some items are no longer available at requested quantity.'
					);
				}

			};

			// lets save receipient information to the database and send email
			$publickey = get_option('cairn_email_public_key', false);

			if ( !$publickey ) {
				return array(
					'id' => $id,
					'success' => false,
					'bitcoin_address' => false,
					'request' => $request,
					'message' => 'Administrator has not completely configured the shop yet.'
				);
			}

			$shipping_encrypted = Cairn_Email::encrypt_shipping_info( $cart, $request, $publickey );

			// prepare our sql statement
			$sql = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET datetime=%s, shipping=%s WHERE p.id=%s;", current_time('mysql'), $shipping_encrypted, $id );

			$cairn_email = get_option('cairn_email', false);

			// send notify email with shipping information
			wp_mail( $cairn_email, '[Order on Hold]', $shipping_encrypted );

			// do the database transaction
			$dbresult = $wpdb->query( $sql );

			if ( $dbresult == false ) {

				// incase there was a database error bail out
				return array(
					'id' => $id,
					'success' => false,
					'bitcoin_address' => false,
					'request' => $request,
					'message' => 'There was a database error when saving your information.'
				);

			} else {

				$bitcoin_address = false;

				// generate bitcoin address for electrum wallet provider
				$payment_address = self::payment_address( self::$master_public_key, $request );

				$bitcoin_address = @$payment_address['generated_bitcoin_address'];

				if ( !$bitcoin_address ) {

					// return status as false
					return array(
						'id' => $id,
						'success' => false,
						'bitcoin_address' => false,
						'request' => $request,
						'message' => $payment_address['message']
					);
					//xxx don't display error in detail
				}
		
				// prepare our sql statement to update the status to hold
				$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET btc_address=%s, hold_datetime=%s, status='hold' WHERE p.id=%s;", $bitcoin_address, current_time('mysql', true), $id );
		
				$hold_status = $wpdb->query( $sql_status );
		
				// lets check that our database has been updated
				if ( $hold_status == false ) {
					// there was a database error bail out
					return array(
						'id' => $id,
						'success' => false,
						'bitcoin_address' => $bitcoin_address,
						'request' => $request,
						'message' => 'There was a database error. Please try again later.'
					);
				}		
		
				// send email to customer if can encrypt
				if ( $request['email'] ) {

					Cairn_Email::process_invoice_email( $cart, $request );

				}

				$frame = 'bitcoin:'.$bitcoin_address.'?amount='.$cart['total_btc'];

				// generate the qr code image
				ob_start();
				QRcode::png( $frame, null, QR_ECLEVEL_H, 5, 0 );
				$qrcode = base64_encode( ob_get_contents() );
				ob_end_clean();	

				$request['bitcoin_qrcode'] = $qrcode;
				$request['bitcoin_address'] = $bitcoin_address;
				$request['seconds_remaining'] = Cairn::$hold_expires_in_secs;

				// return status
				return array(
					'id' => $id,
					'success' => true,
					'message' => 'Successfully generated bitcoin address for order.',
					'request' => $request
				);

			}

		}

	}

	/**
	* Retreives data from a remote server.

	* @param string $url The url to request.
	* @param boolean $return_content_on_error If to return content on error.
	* @param integer $timeout The total seconds until the request will timeout.
	* @param string $user_agent The user agent to identify. 
	* @return string The data requested from the URL.
	*/
	static public function curl( $url, $return_content_on_error = false, $timeout = 60, $user_agent=FALSE ) {

		if ( !function_exists('curl_init') ) {
			return @file_get_contents( $url );
		}

		$options = array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER => false,    // don't return headers
			CURLOPT_ENCODING => "",       // handle compressed
			CURLOPT_USERAGENT => $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12"), // who am i
			CURLOPT_AUTOREFERER => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => $timeout,       // timeout on connect
			CURLOPT_TIMEOUT => $timeout,       // timeout on response in seconds.
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_MAXREDIRS => 10,       // stop after 10 redirects
		);

		$ch = curl_init();

		if ( function_exists( 'curl_setopt_array' ) ) {
			curl_setopt_array( $ch, $options );
		} else {
	
			// To accomodate older PHP 5.0.x systems
	
			curl_setopt ($ch, CURLOPT_URL            , $url);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER , true);     // return web page
			curl_setopt ($ch, CURLOPT_HEADER         , false);    // don't return headers
			curl_setopt ($ch, CURLOPT_ENCODING       , "");       // handle compressed
			curl_setopt ($ch, CURLOPT_USERAGENT      , $user_agent?$user_agent:urlencode("Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.576.0 Safari/534.12")); // who am i
			curl_setopt ($ch, CURLOPT_AUTOREFERER    , true);     // set referer on redirect
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT , $timeout);       // timeout on connect
			curl_setopt ($ch, CURLOPT_TIMEOUT        , $timeout);       // timeout on response in seconds.
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION , true);     // follow redirects
			curl_setopt ($ch, CURLOPT_MAXREDIRS      , 10);       // stop after 10 redirects
		}

		$content = curl_exec( $ch );
		$err = curl_errno( $ch );
		$header = curl_getinfo( $ch );
	
		curl_close($ch);

		if ( !$err && $header['http_code'] == 200 ) {
			return trim( $content );
		} else {
			if ( $return_content_on_error ) {
				return trim( $content );
			} else {
				return false;
			}
		}
	}

	/**
	* Discovers the received information for a particular bitcoin address. 

	* @param string $btc_address The bitcoin address in question.
	* @param integer $required_confirmations The total number of confirmations to check.
	* @param integer $api_timeout The total seconds until the request will timeout.

	* @return string The status of the bitcoin address.
	* - result _string_ 'success' or 'error'
	* - message _string_ The message of the status.
	* - host_reply_raw _string_ The raw reply from the status of the bitcoin address
	* - balance _float_ The number of bitcoins received
	* 
	*/
	static public function received_info( $btc_address, $required_confirmations = 0, $api_timeout = 10 ) {

		if ( $required_confirmations ) {
			$confirmations_url_part_c = "?confirmations=$required_confirmations";
			$confirmations_url_part_e = "/$required_confirmations";
		} else {
			$confirmations_url_part_c = "";
			$confirmations_url_part_e = "";
		}

		// try blockchain first
		$funds_received = self::curl('https://blockchain.info/q/getreceivedbyaddress/' . $btc_address . $confirmations_url_part_c, true, $api_timeout);

		if ( is_numeric( $funds_received ) ) {

			$funds_received = sprintf("%.8f", $funds_received / 100000000.0 );

		} else {

			$blockchain_info_failure_reply = $funds_received;
	
			// try blockexplorer second
			$funds_received = self::curl('https://blockexplorer.com/q/getreceivedbyaddress/' . $btc_address . $confirmations_url_part_e, true, $api_timeout);
	
			$blockexplorer_com_failure_reply = $funds_received;

		}

		if ( is_numeric( $funds_received ) ) {

			$ret_info_array = array(
				'result' => 'success',
				'message' => "",
				'host_reply_raw' => "",
				'balance' => $funds_received,
			);

		} else {

			$ret_info_array = array(
				'result' => 'error',
				'message' => "Blockchains API failure. Erratic replies:\n" . $blockexplorer_com_failure_reply . "\n" . $blockchain_info_failure_reply,
				'host_reply_raw' => $blockexplorer_com_failure_reply . "\n" . $blockchain_info_failure_reply,
				'balance' => false,
			);

		}
	
		return $ret_info_array;
	}

	/**
	* Gets a bitcoin address that can be used for payment.

	* @return string The newly minted bitcoin address.
	* - result _string_ 'success' or 'error'
	* - message _string_ The message of the status.
	* - host_reply_raw _string_ The raw reply from the status of the bitcoin address
	* - generated_bitcoin_address _string_ The bitcoin address available to be used.
	* 
	*/
	static public function payment_address( $request ) {

		global $wpdb;

		$origin_id = md5( self::$master_public_key );

		$clean_address = NULL;

		$current_time = time();

		$query = "SELECT `btc_address` FROM `".self::$table_name."` WHERE `origin_id`='".$origin_id."' AND `total_received_funds`='0' AND (('".$current_time."' - `received_funds_checked_at`) < '".self::$funds_received_value_expires_in_secs."') AND (`status`='unused' ) ORDER BY `index_in_wallet` ASC;"; 

		$clean_address = $wpdb->get_var( $query );

		if ( !$clean_address ) {
	
			// check databases for unused addresses
	
			$query = "SELECT * FROM `".self::$table_name."` WHERE `origin_id`='".$origin_id."' AND ( `status`='unused' OR `status`='unknown' ) ORDER BY `index_in_wallet` ASC;"; 

			$address_rows = $wpdb->get_results( $query, ARRAY_A );
	
			if ( is_array( $address_rows ) ) {

				$blockchains_api_failures = 0;

				foreach ( $address_rows as $address_row ) {

					 $btc_address = $address_row['btc_address'];

					 $btc_address_info = self::received_info( $btc_address, 0, self::$blockchain_api_timeout_secs );

					 if ( $btc_address_info['balance'] === false) {

						$blockchains_api_failures ++;

						if ( $blockchains_api_failures >= self::$blockchain_api_max_failures ) {

							return array (
								'result' => 'error',
								'message' => $btc_address_info['message'],
								'host_reply_raw' => $btc_address_info['host_reply_raw'],
								'generated_bitcoin_address' => $btc_address,
							);
						}

					} else {

						if ( $btc_address_info['balance'] == 0 ) {

							// balanace at this address is zero

							$clean_address = $btc_address;

							break;

						} else {

							// balance at this address is non-zero

							$orders = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM ' .$wpdb->prefix . 'posts_set p WHERE p.btc_address=%s ', $btc_address ) );

							if ( $orders > 0 ) {

								// there is an existing order
								$new_status = 'revalidate';

							} else { 

								// no orders were ever placed to this address, it is likely payment was 
								// sent to this address outside of this online store business
								$new_status = 'used';

							}

							$current_time = time();

							$update_sql = $wpdb->prepare( 'UPDATE `'.self::$table_name.'` SET `status`=%s, `total_received_funds`=%s, `received_funds_checked_at`=%s WHERE `btc_address`=%s', $new_status, $btc_address_info['balance'], $current_time, $btc_address );

							$ret_code = $wpdb->query( $update_sql );

						}
					}
				}
			}
		}

		if ( !$clean_address ) {

			$add_address = self::add_address();
	
			if ( $add_address['result'] == 'success' ) {

				$clean_address = $add_address['generated_bitcoin_address'];

			} else {

				return array(
					'result' => 'error',
					'message' => 'Failed to find/generate bitcoin address. ' . $add_address['message'],
					'host_reply_raw' => $add_address['host_reply_raw'],
					'generated_bitcoin_address' => false
				);

			}
		}

		$current_time = time();

		if ( isset( $request['requested_by_ip'] ) ) {
			$remote_addr = $request['requested_by_ip'];
		} else {
			$remote_addr = false;
		}

		$update_query = $wpdb->prepare('UPDATE '.self::$table_name.' SET total_received_funds="0", received_funds_checked_at=%s, status="assigned", assigned_at=%s, last_assigned_to_ip=%s WHERE btc_address=%s', $current_time, $current_time, $remote_addr, $clean_address );
		
		$ret_code = $wpdb->query( $update_query );

		return array(
			'result' => 'success',
			'message' => "",
			'host_reply_raw' => "",
			'generated_bitcoin_address' => $clean_address,
		);

	}

	/**
	* Adds a new bitcoin address to the database.

	* @return string The newly minted bitcoin address.
	* - result _string_ 'success' or 'error'
	* - message _string_ The message of the status.
	* - host_reply_raw _string_ The raw reply from the status of the bitcoin address
	* - generated_bitcoin_address _string_ The bitcoin address available to be used.
	* 
	*/
	static public function add_address() {

		global $wpdb;

		// checksum of the master public key
		$origin_id = md5( self::$master_public_key );

		$clean_address = false;

		// find next index to generate
		$next_key_index = $wpdb->get_var( "SELECT MAX(`index_in_wallet`) AS `max_index_in_wallet` FROM `".self::$table_name."` WHERE `origin_id`='".$origin_id."';" );
	
		if ( $next_key_index === NULL) {
			$next_key_index = self::$starting_index_for_new_btc_addresses;
		} else {
			$next_key_index = $next_key_index+1; 
		}

		$total_new_keys_generated = 0;
		$blockchains_api_failures = 0;
	
		do {

			$new_btc_address = self::address( self::$master_public_key, $next_key_index );
			$address_info = self::received_info( $new_btc_address, 0, self::$blockchain_api_timeout_secs );
			$total_new_keys_generated ++;
	
			if ( $address_info['balance'] === false ) {
				$status = 'unknown';
			} else if ($address_info['balance'] == 0) {
				// newly generated address with zero balance
				$status = 'unused'; 
			} else {
				// already used generated address
				$status = 'used';   
			}

			$funds_received = ( $address_info['balance'] === false ) ? -1 : $address_info['balance'];

			$received_funds_checked_at_time = ( $address_info['balance'] === false ) ? 0 : time();

			// insert newly generated address into database
	
			$query = $wpdb->prepare( "INSERT INTO `".self::$table_name."` (`btc_address`, `origin_id`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES (%s, %s, %s, %s, %s, %s)", $new_btc_address, $origin_id, $next_key_index, $funds_received, $received_funds_checked_at_time, $status ); 

			$ret_code = $wpdb->query( $query );
	
			$next_key_index++;
	
			if ( $address_info['balance'] === false ) {

				$blockchains_api_failures ++;

				if ( $blockchains_api_failures >= self::$blockchains_api_max_failures ) {

					$return_info = array(
						'result' => 'error',
						'message' => $address_info['message'],
						'host_reply_raw' => $address_info['host_reply_raw'],
						'generated_bitcoin_address' => false,
					);

					return $return_info;

				}

			} else {

				if ( $address_info['balance'] == 0 ) {
					$clean_address = $new_btc_address;
				}
			}
	
			if ( $clean_address ) {
				 break;
			}

			if ( $total_new_keys_generated >= self::$max_unusable_generated_addresses ) {
	
				$return_info = array(
					'result' => 'error',
					'message' => "Problem: Generated '$total_new_keys_generated' addresses and none were found to be unused. Possibly old merchant's wallet (with many used addresses) is used for new installation. If that is the case - 'starting_index_for_new_btc_addresses' needs to be proper set to high value",
					'host_reply_raw' => '',
					'generated_bitcoin_address' => false,
				);
	
				return $return_info;
			}
	
		} while ( true );

		// we have a clean address
		return array(
			'result' => 'success',
			'message' => '',
			'host_reply_raw' => '',
			'generated_bitcoin_address' => $clean_address,
		);
	
	}

	/**
	* Generates a new bitcoin address based upon a master public key from Electrum.

	* @param string $master_public_key The master public key from Electrum.
	* @param integer $key_index The index used for the key to syncronize with the offline wallet.

	* @return string The newly minted bitcoin address.
	* 
	*/
	static public function address( $master_public_key, $key_index ) {

		// create the ecc curve
		if ( USE_EXT == 'GMP') {		
			// GMP
			$_p = gmp_Utils::gmp_hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F');
			$_r = gmp_Utils::gmp_hexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
			$_b	= gmp_Utils::gmp_hexdec('0x0000000000000000000000000000000000000000000000000000000000000007');
			$_Gx = gmp_Utils::gmp_hexdec('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798');
			$_Gy = gmp_Utils::gmp_hexdec('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8');
		} else { 
			// BCMATH
			$_p	= bcmath_Utils::bchexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F');
			$_r	= bcmath_Utils::bchexdec('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
			$_b = bcmath_Utils::bchexdec('0x0000000000000000000000000000000000000000000000000000000000000007');
			$_Gx = bcmath_Utils::bchexdec('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798');
			$_Gy = bcmath_Utils::bchexdec('0x483ada7726a3c4655da4fbfc0e1108a8fd17b448a68554199c47d08ffb10d4b8');
		}
	
		$curve = new CurveFp($_p, 0, $_b);
		$gen = new Point( $curve, $_Gx, $_Gy, $_r );

		// prepare the input values
		if ( USE_EXT == 'GMP') {	
			// GMP
			$x = gmp_Utils::gmp_hexdec('0x'.substr($master_public_key, 0, 64));
			$y = gmp_Utils::gmp_hexdec('0x'.substr($master_public_key, 64, 64));
			$z = gmp_Utils::gmp_hexdec('0x'.hash('sha256', hash('sha256', $key_index.':0:'.pack('H*',$master_public_key), TRUE)));
		} else {
			// BCMATH
			$x = bcmath_Utils::bchexdec('0x'.substr($master_public_key, 0, 64));
			$y = bcmath_Utils::bchexdec('0x'.substr($master_public_key, 64, 64));
			$z = bcmath_Utils::bchexdec('0x'.hash('sha256', hash('sha256', $key_index.':0:'.pack('H*',$master_public_key), TRUE)));
		}

		// generate the new public key based off master and sequence points
		$pt = Point::add(new Point($curve, $x, $y), Point::mul($z, $gen) );

		if ( USE_EXT == 'GMP' ) {	
			// GMP
			$keystr = "\x04" . str_pad(gmp_Utils::gmp_dec2base($pt->getX(), 256), 32, "\x0", STR_PAD_LEFT) . str_pad(gmp_Utils::gmp_dec2base($pt->getY(), 256), 32, "\x0", STR_PAD_LEFT);
		} else {   
			// BCMATH
			$keystr = "\x04" . str_pad(bcmath_Utils::dec2base($pt->getX(), 256), 32, "\x0", STR_PAD_LEFT) . str_pad(bcmath_Utils::dec2base($pt->getY(), 256), 32, "\x0", STR_PAD_LEFT);
		}

		$vh160 =  "\x0".hash('ripemd160', hash('sha256', $keystr, TRUE), TRUE);
		$addr = $vh160.substr(hash('sha256', hash('sha256', $vh160, TRUE), TRUE), 0, 4);

		// base58 conversion
		$alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
		$encoded  = '';

		if ( USE_EXT == 'GMP' ) {	
			// GMP
			$num = gmp_Utils::gmp_base2dec($addr, 256);
		} else { 
			// BCMATH
			$num = bcmath_Utils::base2dec($addr, 256);
		}

		while ( intval( $num ) >= 58 ) {
			$div = bcdiv($num, '58');
			$mod = bcmod($num, '58');
			$encoded = $alphabet[intval($mod)] . $encoded;
			$num = $div;
		}
	
		$encoded = $alphabet[intval($num)] . $encoded;
		$pad = '';
		$n = 0;
		while ( $addr[$n++] == "\x0" ) {
			$pad .= '1';
		}

		return $pad.$encoded;
	}

	/**
	 * Callback to generate many bitcoin addresses in advance.
	*/
	static public function generate_addresses(){

		global $wpdb;

		if ( self::$master_public_key ) {

			$origin_id = md5( self::$master_public_key );

			do {

				$count_query = $wpdb->prepare( "SELECT COUNT(*) as `total_unused_addresses` FROM ".self::$table_name." b WHERE b.origin_id=%s AND b.status='unused'", $origin_id );

				$total_unused_addresses = $wpdb->get_var( $count_query );

				if ( $total_unused_addresses < self::$max_unused_addresses_buffer ) {
					self::add_address();
				} else {
					break;
				}

			} while ( true );
		}

	}

	/**
	 * Callback to initialize settings.
	*/
	static public function initialize(){

		global $wpdb;

		self::$blockchain_api_timeout_secs = 10;
		self::$blockchain_api_max_failures = 3;
		self::$max_unusable_generated_addresses = 20; 
		self::$starting_index_for_new_btc_addresses = 0; 
		self::$max_unused_addresses_buffer = 20;
		self::$funds_received_value_expires_in_secs = 60 * 60 * 3; // one hour
		self::$assigned_address_expires_in_secs = 60 * 60 * 3; // one hour
		self::$confirmations_required = 6; // six confirmations
		self::$table_name = $wpdb->prefix . 'posts_set_btc_addresses';
		self::$master_public_key = get_option('cairn_bitcoin_master_public_key', false );

	}

	/**
	 * Callback to install database scheme.
	*/
	static public function db_install() {

		global $wpdb;

		self::initialize(); // make sure varibales are set...			

		if ( $wpdb->get_var("SHOW TABLES LIKE '".self::$table_name."'") != self::$table_name ) {

			$sql = "CREATE TABLE IF NOT EXISTS `".self::$table_name."` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`btc_address` char(36) NOT NULL,
				`origin_id` char(64) NOT NULL DEFAULT '',
				`index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
				`status` char(16)  NOT NULL DEFAULT 'unknown',
				`last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
				`assigned_at` bigint(20) NOT NULL DEFAULT '0',
				`total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
				`received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
				PRIMARY KEY (`id`),
				UNIQUE KEY `btc_address` (`btc_address`),
				UNIQUE KEY `index_in_wallet` (`index_in_wallet`,`origin_id`)
			);";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

	}

}

?>