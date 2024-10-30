<?php
/**
 * The Cairn Shipping Class
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
 * Functions for doing shipping calculations.
 *
 * @package cairn
 */
class Cairn_Shipping {

	static public $user_id;
	static public $zip_origin;

	/**
	 * Callback to initialize the settings for the USPS Web Tools API.
	 */
	static public function init(){
		self::$user_id = get_option( 'cairn_usps_api_key' );
		self::$zip_origin = get_option( 'cairn_usps_api_zip_code' );
	}

	/**
	 * Retreives the international shipping estimate from the USPS Web Tools API 
	 *
	 * @param array $services The services ids (integers) to get retreive prices.
	 * @param integer $pounds The pounds of the package.
	 * @param integer $ounces The ounces of the package.
	 * @param float $value The valued price of the package contents.
	 * @param string $country The destination country.
	 * @param integer $width The width of the package.
	 * @param integer $height The height of the package.
	 * @param integer $length The length of the package.

	 * @return array 
	 * - service_id _integer_ The id of the service.
	 * - service _string_ The name of the service.
	 * - rate _float_ The estimate of the shipping.
	 */
	static public function usps_intlratev2( $services, $pounds, $ounces, $value, $country, $width, $height, $length ) {

		$http = new WP_Http();

		$container = 'RECTANGULAR';
		$size = 'REGULAR';

		$estimates = array();

		$xml_send =  '<IntlRateV2Request USERID="'.self::$user_id.'">';
		$xml_send .= '<Revision>2</Revision>';
		$xml_send .= '<Package ID="1ST">';
		$xml_send .= '<Pounds>'.$pounds.'</Pounds>';
		$xml_send .= '<Ounces>'.$ounces.'</Ounces>';
		$xml_send .= '<Machinable>True</Machinable>';
		$xml_send .= '<MailType>Package</MailType>';
        $xml_send .= '<GXG><POBoxFlag>N</POBoxFlag><GiftFlag>N</GiftFlag></GXG>';
		$xml_send .= '<ValueOfContents>'.$value.'</ValueOfContents>';
		$xml_send .= '<Country>'.$country.'</Country>';
		$xml_send .= '<Container>'.$container.'</Container>';
		$xml_send .= '<Size>'.$size.'</Size>';
		$xml_send .= '<Width>'.$width.'</Width>';
		$xml_send .= '<Length>'.$length.'</Length>';
		$xml_send .= '<Height>'.$height.'</Height>';
		$xml_send .= '<Girth>'.$width.'</Girth>';
		$xml_send .= '<CommercialFlag>n</CommercialFlag>';
		$xml_send .= '</Package>';
		$xml_send .= '</IntlRateV2Request>';

		$result = $http->request('http://production.shippingapis.com/ShippingAPI.dll', array('method' => 'POST', 'timeout' => 20, 'body' => array('API' => 'IntlRateV2', 'XML' => $xml_send )));

		$xml = new SimpleXMLElement($result['body']);

		foreach ( $xml->Package->Service as $s ) {

			$service_id = (int) $s->attributes()->ID;

			if ( in_array( $service_id, $services ) ) {

				$rate = (string) $s->Postage;
				$name = (string) $s->SvcDescription;
				$estimates[] = array(
					'service_id' => $service_id,
					'service' => $name,
					'rate' => $rate
				);
			}

		}

		$estimates = array_reverse( $estimates );

		return $estimates;

	}

	/**
	 * Retreives the domestic shipping estimate from the USPS Web Tools API 
	 *
	 * @param array $services
	 * - name _string_ The name of the service, such as 'Priority'.
	 * - title _string_ The title to display the service, such as 'USPS Priority'.
	 * @param integer $pounds The pounds of the package.
	 * @param integer $ounces The ounces of the package.
	 * @param integer $zip The destination zip.
	 * @param integer $width The width of the package.
	 * @param integer $height The height of the package.
	 * @param integer $length The length of the package.

	 * @return array 
	 * - service _string_ The name of the service.
	 * - rate _float_ The estimate of the shipping.
	 */
	static public function usps_ratev4( $services, $pounds, $ounces, $zip, $width, $height, $length ) {

		$http = new WP_Http();

		if ( $width > 12 || $height > 12 || $length > 12 ) {
			$size = 'LARGE';
			$container = 'RECTANGULAR';
		} else {
			$size = 'REGULAR';
			$container = '';
		}

		$estimates = array();

		foreach ( $services as $service ) {

			$xml_send =  '<RateV4Request USERID="'.self::$user_id.'"><Revision/><Package ID="1ST"><Service>'.$service['name'].'</Service><ZipOrigination>'.self::$zip_origin.'</ZipOrigination><ZipDestination>'.$zip.'</ZipDestination><Pounds>'.$pounds.'</Pounds>';
			$xml_send .= '<Ounces>'.$ounces.'</Ounces>';
			$xml_send .= '<Container>'.$container.'</Container>';
			$xml_send .= '<Size>'.$size.'</Size>';
			$xml_send .= '<Width>'.$width.'</Width><Length>'.$length.'</Length><Height>'.$height.'</Height><Machinable>True</Machinable></Package></RateV4Request>';

			$result = $http->request('http://production.shippingapis.com/ShippingAPI.dll', array('method' => 'POST', 'timeout' => 20, 'body' => array('API' => 'RateV4', 'XML' => $xml_send )));

			// errors?

			$xml = new SimpleXMLElement($result['body']);

			foreach ( $xml->Package->Postage as $s ) {
				$rate = (string) $s->Rate;
				$estimates[] = array( 
					'service' => $service['title'],
					'rate' => $rate
				);
			}

		}

		$estimates = array_reverse( $estimates );

		return $estimates;			

	}

}

?>
