<?php
/**
 * The Cairn Stripe Payment Class
 *
 * Copyright (C) 2013 Braydon Fuller <http://braydon.com/>

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
 * Stripe payment processing in Cairn
 *
 * @package cairn
 */
class Cairn_Stripe_Payment {

	/**
	* Callback to initialize settings.
	*/
	static public function initialize(){

		global $wpdb;

		Stripe::setApiKey( get_option('cairn_stripe_api_key') );

	}


	/**
	* AJAX wrapper to authorize credit card transactions with authorize_invoice.
	*/
	static public function ajax_authorize_invoice() {

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

		// get the values
		$request = $_REQUEST;

		$result = self::authorize_invoice( $request );

		print json_encode( $result );

		if ( !$result['success'] ) {

			// send emails to report this error
			$message = "Someone is trying to authorize a credit card and experienced an error:\n\n".$result['message'];
			Cairn_Email::secure_email( '['.get_bloginfo('name').' Credit Card]', $message );	

		}

		exit(0);

	}

	/**
	* Authorizes a post_set from submitted credit card information. 
	*
	* @param array $request The posted request variables.

	* @return array 
	* - id _string_ The post_set id.
	* - success _boolean_ If the status check was successful.
	* - message _string_ The text that should be displayed to the user.
	* - request _array_ Returning the request variables back which has customer information with the addition of these:
	*	- received_usd _float_ The amount that was approved and received.
	*	- approved _boolean_ If the credit card was approved.
	*	- confirmation _string_ The confirmation hash for the order.
	*	- cc_lastfour _string_ The last four numbers of the credit card.
	*   - date _string_ The date in MySQL format.
	*
	*/
	static public function authorize_invoice( $request ) {

		global $wpdb;

		$hash = Cairn::hash();

		$cart = Cairn_Post_Set::get( $hash );

		if ( !$cart) {

			return array(
				'id' => false,
				'success' => false,
				'message' => 'That cart does not exist.'
			);

		} else {

			try {

				$charge = Stripe_Charge::create( array(
					'amount' => $cart['total_usd'] * 100, //in cents
					'currency' => 'usd', 
					'card' => array(
						'number' => $request['cc_number'],
						'exp_month' => $request['cc_exp_month'],
						'exp_year' => $request['cc_exp_year'],
						'cvc' => $request['cc_cvc'],
					),
					'capture' => false,
					'description' => $cart['id']
				));

			} catch ( Stripe_CardError $e ) {

				$body = $e->getJsonBody(); 

				$err = $body['error'];

				return array(
					'id' => $cart['id'],
					'success' => false,
					'message' => $err['message']
				);
			} catch ( Stripe_Error $e ) {

				print_r( $e );

				return array(
					'id' => $cart['id'],
					'success' => false,
					'message' => 'There was an error.'
				);

			}
		
			if ( $charge['card']['cvc_check'] != 'pass' ) {
				return array(
					'id' => $cart['id'],
					'success' => false,
					'message' => 'Credit Card CVC failed verification.'
				);
			}

			$transaction = $charge['id'];

			$confirmation_code = sha1( $transaction . $cart['id'] );

			// prepare our sql statement to update the status to hold
			$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET confirmation=%s, transaction=%s, status='received', received_datetime=%s, received_usd=%s WHERE p.id=%s;", $confirmation_code, $transaction, current_time('mysql'), $cart['total_usd'], $cart['id'] );

			$received_status = $wpdb->query( $sql_status );

			// lets check that our database has been updated
			if ( $received_status == false ) {
				// there was a database error bail out
				return array(
					'id' => $cart['id'],
					'success' => false,
					'message' => 'There was a database error. Please try again later.'
				);
			}			

			$lastfour = substr($request['cc_number'], -4);
			unset( $request['cc_number'] );
			unset( $request['cc_cvc'] );
			unset( $request['cc_exp_month'] );
			unset( $request['cc_exp_year'] );

			$request['received_usd'] = $cart['total_usd'];				
			$request['approved'] = true;
			$request['confirmation'] = $confirmation_code;
			$request['cc_lastfour'] = $lastfour;
			$request['date'] = current_time('mysql');

			return array(
				'id' => $cart['id'],
				'success' => true,
				'message' => 'Credit Card has been approved.',
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

			// send emails to report this error
			$message = "Someone is trying to checkout and experienced an error:\n\n".$result['message'];
			Cairn_Email::secure_email( '['.get_bloginfo('name').' Credit Card]', $message );	

		}

		exit(0);

	}

	/**
	* Processes a post_set with the status of a cart and changes it to a status of hold and saves information from $request to the database.
	*
	*
	* @param array $request Request variables posted from the order form.

	* @return array 
	* - id _boolean_ If the status check was successful.
	* - success _boolean_ If the status check was successful.
	* - message _string_ The text that should be displayed to the user.
	* - request _array_ Returning the request variables back which has customer information.
	*/
	static public function process_invoice( $request ) {

		global $wpdb;

		$hash = Cairn::hash();

		$cart = Cairn_Post_Set::get( $hash );

		if ( !$cart ) {

			return array(
				'id' => false,
				'success' => false,
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
						'request' => $request,
						'message' => 'Some items are no longer available at requested quantity.'
					);
				}

			};

			$publickey = get_option('cairn_email_public_key', false);

			if ( !$publickey ) {
				return array(
					'id' => $id,
					'success' => false,
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
					'message' => 'There was a database error when saving your information.'
				);

			} else {

				// prepare our sql statement to update the status to hold
				$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET status='hold', hold_datetime=%s WHERE p.id=%s;", current_time('mysql', true), $id );
		
				$hold_status = $wpdb->query( $sql_status );
		
				// lets check that our database has been updated
				if ( $hold_status == false ) {
					// there was a database error bail out
					return array(
						'id' => $id,
						'success' => false,
						'message' => 'There was a database error. Please try again later.'
					);
				}		
		
				// send email to customer 
				if ( $request['email'] ) {
					Cairn_Email::process_invoice_email( $cart, $request );
				}

				// return status
				return array(
					'id' => $id,
					'success' => true,
					'message' => 'Successfully created invoice for credit card transaction.',
					'request' => $request
				);

			}

		}

	}


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

		$sql = $wpdb->prepare( 'SELECT status, hold_datetime FROM '.$wpdb->prefix.'posts_set p WHERE p.hash=%s', $hash );

		$order = $wpdb->get_row( $sql );

		$result = self::status_invoice( $order, $request = false );

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
	* 
	*/
	static public function status_invoice( $order, $request = false ) {

		global $wpdb;

		$return = array();

		if ( $order ) {

			if ( $order->status != 'hold' && $order->status != 'expired' ) {
				return array(
					'success' => false,
					'end' => true,
					'message' => 'This order is not on hold.'
				);
			}

			$expired = Cairn_Post_Set::hold_expired( $order );

			if ( $order->status == 'expired' || $expired === true ) {
				return array(
					'success' => true,
					'end' => true,
					'expired' => true
				);
			} else {

				return array(
					'success' => true,
					'end' => false,
					'expired' => false,
					'seconds_remaining' => $expired
				);

			}
		}
	}

	/**
	* Captures an authorized post_set for payment.
	*
	* @param string $id The post_set id.

	* @return array 
	* - id _string_ The post_set id.
	* - success _boolean_ If the status check was successful.
	* - message _string_ The text that should be displayed to the user.
	*/
	static public function capture_invoice( $id ) {

		global $wpdb;

		$row = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.id="' . $id .'"' );

		if ( !$row ) {

			return array(
				'id' => false,
				'success' => false,
				'message' => 'That cart does not exist.'
			);

		} else {
	
			$transaction = $row[0]->transaction;

			if ( !$transaction ) {

				return array(
					'id' => $row[0]->id,
					'success' => false,
					'message' => 'That cart does not have an authorized transaction id.'
				);

			} else {

				$charge = Stripe_Charge::retrieve( $transaction );

				try {

					$charge->capture();

					$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET status='confirmed', paid_datetime=%s, paid_usd=%s WHERE p.id=%s AND p.transaction=%s;", current_time('mysql', true), $charge->amount/100, $id, $transaction );

					$confirmed_status = $wpdb->query( $sql_status );

					return array(
						'id' => $row[0]->id,
						'success' => true,
						'message' => 'Credit Card was successfully captured.'
					);



				} catch ( Stripe_Error $e ) {

					return array(
						'id' => $row[0]->id,
						'success' => false,
						'message' => 'There was a Stripe Erorr: '.$e
					);

				}
			}
		}
	}
}

?>