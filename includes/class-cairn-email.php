<?php
/**
 * The Cryptogallery Email Class
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
 * Email helper functions for Cairn payment processing
 *
 * @package cairn
 */
class Cairn_Email {

	/**
	 * Gets the contact information from the options database.
	* @return array 
	* - email _string_ The shipping contact email address
	* - email_public_key _string_ The email GPG public key
	* - email_public_key_id _string_ The email GPG public key ID
	* - address1 _string_ The origin address line 1
	* - address2 _string_ The origin address line 2
	* - city _string_ The origin city
	* - state _string_ The origin state/province
	* - zip _string_ The origin zip code
	* - country _string_ The country where the items ship
	* - sip_phone _string_ Session Initiated Protocal address
	* - pots_phone _string_ Plain old telephone system number
	* - name _string_ Name of the shipping contact
	 */
	static public function contact_info(){
		$contact = array(
			'email' => get_option('cairn_email', false),
			'email_public_key' => get_option('cairn_email_public_key', false),
			'email_public_key_id' => get_option('cairn_email_public_key_id', false),
			'address1' => get_option('cairn_mailing_address1', false),
			'address2' => get_option('cairn_mailing_address2', false),
			'city' => get_option('cairn_mailing_city', false),
			'state' => get_option('cairn_mailing_state', false),
			'zip' => get_option('cairn_mailing_zip', false),
			'country' => get_option('cairn_mailing_country', false),
			'sip_phone' => get_option('cairn_mailing_sip_phone', false),
			'bitmessage' => get_option('cairn_mailing_bitmessage', false),
			'pots_phone' => get_option('cairn_mailing_pots_phone', false),
			'name' => get_option('cairn_mailing_name', false),
			'company_name' => get_option('cairn_mailing_company_name', false)
		); 
		return $contact;
	}


	/**
	* Sends an email to the shipping contact
	*
	* @param string $subject The subject of the email.
	* @param string $message The body and message of the email.
	*
	* @return boolean If the email was processed without any errors
	 */
	static public function email( $subject, $message ) {

		$email = get_option('cairn_email', '');

		$success = wp_mail( $email, $subject, $message );

		return $success;
	}


	/**
	* Sends an encrypted email to the shipping contact
	*
	* @param string $subject The subject of the email.
	* @param string $message The body and message of the email.
	*
	* @return boolean If the email was processed without any errors
	 */
	static public function secure_email( $subject, $message ) {

		$cairn_email = get_option('cairn_email', '');
		$cairn_email_public_key = get_option('cairn_email_public_key', '');

		if ( $cairn_email && $cairn_email_public_key ) {

			return self::secure_email_to( $cairn_email, $cairn_email_public_key, $subject, $message );

		} else {

			return false;

		}
		
	}

	/**
	* Sends an encrypted email to an email address.
	*
	* @param string $email The recipient email address.
	* @param string $publickey The recipient email public keiy.
	* @param string $subject The subject of the email.
	* @param string $message The body and message of the email.
	*
	* @return boolean If the email was processed without any errors
	 */
	static public function secure_email_to( $email, $publickey, $subject, $message ) {

		$gpg = new GPG();

		$pk = new GPG_Public_Key( $publickey );

		$encrypted_message = $gpg->encrypt( $pk, $message );

		$success = wp_mail( $email, $subject, $encrypted_message );

		return $success;

	}


	/**
	* AJAX callback to send an email to the shipping contact. Prints JSON object with the status of the email with the following attributes:
	*
	* - success _boolean_ If the email was processed without errors.
	* - message _string_ The message to display to the user.
	*
	 */
	static public function ajax_contact() {

		// lets make sure that this request hasnt been submitted twice
		$nonce = $_REQUEST['nonce'];
		if ( ! wp_verify_nonce( $nonce, 'cairn_nonce' ) ) {

			print json_encode( array(
				'success' => false,
				'message' => 'Invalid nonce: '.$nonce
			));
		
			exit(0);

		}

		$name = $_REQUEST['cname'];
		$email = $_REQUEST['cemail'];
		$sip = $_REQUEST['csip'];
		$publickey = $_REQUEST['cpublickey'];
		$message = $_REQUEST['cmessage'];

		$body = 'Name: '.$name.PHP_EOL;
		$body .= 'Email: '.$email.PHP_EOL;
		$body .= 'Sip: '.$sip.PHP_EOL;
		$body .= 'Public Key: '.$publickey.PHP_EOL;
		$body .= 'Message: '.$message.PHP_EOL;

		$success = self::secure_email( '[Message]', $body );

		if ( $success ) {
			print json_encode( array(
				'success' => true,
				'message' => 'Message was processed with no errors.'
			));
		}

		exit(0);

	}

	/**
	* Callback to set the from email address header of outgoing email.
	* @param string $email_address The existing email address.
	 */
	static public function mail_from( $email_address ){
		return get_option( 'cairn_email', false );
	}

	/**
	* Callback to set the from name header of outgoing email.
	* @param string $from_name The existing email name.
	 */
	static public function mail_from_name( $from_name ){
		return get_bloginfo( 'name' );
	}

	/**
	* Encrypts the shipping information into an encrypted string.
	*
	* @param string $cart A cart array return by Cairn_Post_Set::cart
	* @param string $request The posted request variables sent from the order form.
	* @param string $publickey The shipping contact public key.
	*
	* @return string The shipping information encrypted.
	 */
	static public function encrypt_shipping_info( $cart, $request, $publickey ) {

			$shipping = 'This is the shipping and contact information for order: '.admin_url('edit.php?post_type=fineart&page=product-order&post_set_id='.$cart['id']).PHP_EOL.PHP_EOL;

			$shipping .= $request['name'].PHP_EOL;
			$shipping .= $request['address1'].PHP_EOL;
			if ( $request['address2'] ) {
				$shipping .= $request['address2'].PHP_EOL;
			}
			$shipping .= $request['city'].', '.$request['state'].' '.$request['zip'].PHP_EOL;
			$shipping .= $request['country'].PHP_EOL;

			if ( $request['sip'] ) {
				$shipping .= 'SIP Phone: '.$request['sip'].PHP_EOL;
			}
			if ( $request['email'] ) {
				$shipping .= 'Email: '.$request['email'].PHP_EOL;
			}
			if ( $request['publickey'] ) {
				$shipping .= PHP_EOL.PHP_EOL.$request['publickey'].PHP_EOL;
			}

			$gpg = new GPG();
			$pk = new GPG_Public_Key( $publickey );
			$shipping_encrypted = $gpg->encrypt( $pk, $shipping );

			return $shipping_encrypted;

	}

	/**
	* Sends an email to the customer to notify about the recent order on hold.
	*
	* @param string $cart A cart array return by Cairn_Post_Set::cart
	* @param string $request The posted request variables sent from the order form.
	*
	*/
	static public function process_invoice_email( $cart, $request ) {

		if ( $request['publickey'] ) {
			$message = 'Dear '.$request['name'].':'.PHP_EOL;
		} else {
			$message = 'Hello:'.PHP_EOL;
		}
		$message .= PHP_EOL;
		$message .= 'We have received your order and have put your artwork on hold for a limited time.'.PHP_EOL.PHP_EOL;
		$message .= 'Your order is not complete until we have received and confirmed payment. You should expect to receive a confirmation message when payment has been confirmed within two business days after payment, and then a message when your order is shipped. A full receipt will be included in your delivery.'.PHP_EOL;
		$message .= PHP_EOL;
		$message .= 'Please allow 2-3 weeks for delivery. For more information about our shipping policies visit: https://braydon.com/shipping/'.PHP_EOL.PHP_EOL.PHP_EOL;

		// if email is encrypted add personal information
		if ( $request['publickey'] ) {

			$message .= 'YOUR SELECTED ARTWORK:'.PHP_EOL.PHP_EOL;

			foreach ( $cart['products'] as $key => $product ) {

				$message .= $product['post']['title'].PHP_EOL;
				$message .= $product['post']['details']['width'].' x '.$product['post']['details']['height'].PHP_EOL;
				$option = $product['post']['options'][$product['option']];
				$message .= $option['name'].PHP_EOL;
				$message .= $product['quantity'].PHP_EOL;
				$message .= PHP_EOL;						

			};

			if ( $cart['method'] == 'btc' ) {

				$message .= 'Total: '.$cart['total_btc'].' BTC'.PHP_EOL.PHP_EOL.PHP_EOL;
	
			} else if ( $cart['method'] == 'credit' ) {

				$message .= 'Total: '.$cart['total_usd'].' USD'.PHP_EOL.PHP_EOL.PHP_EOL;
	
			}

			$message .= 'YOUR SHIPPING INFORMATION:'.PHP_EOL.PHP_EOL;
			$message .= $request['name'].PHP_EOL;
			$message .= $request['address1'].PHP_EOL;
			if ( $request['address2'] ) {
				$message .= $request['address2'].PHP_EOL;
			}
			$message .= $request['city'].', '.$request['state'].' '.$request['zip'].PHP_EOL;
			$message .= $request['country'].PHP_EOL;
			$message .= 'Email: '.$request['email'].PHP_EOL;
			if ( $request['sip'] ) {
				$message .= 'SIP Phone: '.$request['sip'].PHP_EOL;
			}
			$message .= PHP_EOL;

		}
		// end personal information	

		$message .= 'Sincerely,'.PHP_EOL;
		$message .= 'Braydon.com'.PHP_EOL.PHP_EOL.PHP_EOL;

		if ( $request['publickey'] ) {

			self::secure_email_to( $request['email'], $request['publickey'], '[Order on Hold]', $message ); 

		} else {

			wp_mail( $request['email'], '[Order on Hold]', $message );

		}

	}

	
}

?>