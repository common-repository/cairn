<?php
/**
 * The Cairn Post Set Class
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
 * Functions for working with post sets for carts, holds and orders stored for guests based on a cookie.
 *
 * @package cairn
 */
class Cairn_Post_Set {

	/**
	* AJAX callback to calculate the total cost for the post set including shipping costs.
	*
	* Requires the following paramaters:
	*
	* - zip _string_ The destination zip code.
	* - country _string_ The destination country.
	* - method _string_ The method of payment: 'btc' (BTC) or 'credit' (USD)

	* JSON Object is printed which includes the following attributes:
	*
	* - success _boolean_ If the calculation was successful.
	* - message _string_ The text that should be displayed to the user.
	* - prices _array_ The list of individual prices for items.
	*   - id _integer_ The ID of the item/post
	*   - total _array_ The total price for the item. 
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - printing _array_ The total for printing
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - framing _array_ The total for framing
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - production _array_ The total for production
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - artist _array_ The total for artist fees
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	* - calculated _array_ The breakdown of how much is spent on each service.
	*   - printing _array_ The price of all printing
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - framing _array_ The price of all framing
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - production _array_ The price of all production
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - artist _array_ The price of all artist fees
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - shipping _array_ The price of all shipping
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	*   - fees _array_ The price of all fees
	*      - BTC _float_ In Bitcoins
	*      - USD _float_ In United States Dollars
	* - method _string_ The method of payment: 'btc' or 'credit'
	* - total_btc _float_ The grand total for Bitcoins.
	* - total_usd _float_ The grand total for United States Dollars.
	* 
	*/	
	static public function ajax_posts_set_calculate() {

		global $wpdb;

		$zip = $_REQUEST['zip'];
		$country = $_REQUEST['country'];
		$usa = preg_match("/United States/i", $country );

		$method = $_REQUEST['method'];

		Cairn::hash();
		$row = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', Cairn::$hash ) );

		if ( $row  ) {

			if ( $row[0]->status != 'cart' ) {
				print json_encode(array(
					'success' => false,
					'message' => 'This cart can nolonger me modified.'
				));
				exit(0);
			}

			$id = $row[0]->id;
			$products = self::products( $row[0]->id );

			$weight = 0;
			$volume = 0;
			$max = 0;

			$prices = array();

            foreach( $products as $k => $p ) {

				// get the weight and dimensions
			    $options = get_post_meta( $p->post_id, 'purchase_options', true );
				$option = $options[ $p->post_option ];
				$w = $option['width'];
				$h = $option['height'];
				$l = $option['length'];

				// find the largest dimension
				if ( $w > $max ) $max = $w;
				if ( $h > $max ) $max = $h;
				if ( $l > $max ) $max = $l;

				// calculate volume
				$v = $w * $h * $l;

				// add to the totals
				$volume += $v * (int) $p->post_quantity;
				$weight += (int) $option['weight'] * (int) $p->post_quantity;

				$bitcoin_exchange_rates = get_option( 'bitcoin_exchange_rates', true );

				// calculate costs
				$printing_cost_btc = round(((float) $option['printing-cost'] / (float) $bitcoin_exchange_rates->USD->{'7d'}) * (float) $p->post_quantity, 4);
				$printing_cost_usd = round((float) $option['printing-cost'] * (float) $p->post_quantity, 2);


				$framing_cost_btc = round(((float) $option['framing-cost'] / (float) $bitcoin_exchange_rates->USD->{'7d'}) * (float) $p->post_quantity, 4);
				$framing_cost_usd = round((float) $option['framing-cost'] * (float) $p->post_quantity, 2);

				$production_cost_btc = round((float) $option['production-cost'] * (float) $p->post_quantity, 4);
				$production_cost_usd = round(((float) $option['production-cost'] * (float) $bitcoin_exchange_rates->USD->{'7d'}) * (float) $p->post_quantity, 2);
			
				$artist_fee_btc = round((float) $option['artist-fee'] * (float) $p->post_quantity, 4);
				$artist_fee_usd = round(((float) $option['artist-fee'] * (float) $bitcoin_exchange_rates->USD->{'7d'}) * (float) $p->post_quantity, 2);

				$prices[] = array(
					'id' => $p->post_id,
					'total' => array(
						'BTC' => $printing_cost_btc + $framing_cost_btc + $production_cost_btc + $artist_fee_btc,
						'USD' => $printing_cost_usd + $framing_cost_usd + $production_cost_usd + $artist_fee_usd
					),
					'printing' => array(
						'BTC' => $printing_cost_btc,
						'USD' => $printing_cost_usd
					),
					'framing' => array(
						'BTC' => $framing_cost_btc,
						'USD' => $framing_cost_usd
					),
					'production' => array(
						'BTC' => $production_cost_btc,
						'USD' => $production_cost_usd
					),
					'artist' => array(
						'BTC' => $artist_fee_btc,
						'USD' => $artist_fee_usd
					)
				);

            }

			// we know the box can not be smaller than the largest dimension
			$width = $max; 

			// the other two sides roughly
			$height = $length = round( pow( $volume/$width, 1/2 ) );

			if ( $weight >= 16 ) {
				$pounds = $weight / 16;
				$ounces = $weight % 16;
			} else {
				$pounds = 0;
				$ounces = $weight;
			}

			$bitcoin_exchange_rates = get_option( 'bitcoin_exchange_rates', true );

			// calculate totals
			$subtotal_btc = 0;
			$subtotal_usd = 0;

			$total_printing_usd = 0;
			$total_framing_usd = 0;
			$total_production_usd = 0;
			$total_artist_usd = 0;

			$total_printing_btc= 0;
			$total_framing_btc = 0;
			$total_production_btc = 0;
			$total_artist_btc = 0;

			foreach( $prices as $pp ) {
				$subtotal_btc += $pp['total']['BTC'];
				$subtotal_usd += $pp['total']['USD'];
				$total_printing_usd += $pp['printing']['USD'];
				$total_printing_btc += $pp['printing']['BTC'];
				$total_framing_usd += $pp['framing']['USD'];
				$total_framing_btc += $pp['framing']['BTC'];
				$total_production_usd += $pp['production']['USD'];
				$total_production_btc += $pp['production']['BTC'];
				$total_artist_usd += $pp['artist']['USD'];
				$total_artist_btc += $pp['artist']['BTC'];
			}

			if ( $usa ) {

				$services = array(
					array(
						'name' => 'Priority',
						'title' => 'USPS Priority'
					)
				);

				$estimates = Cairn_Shipping::usps_ratev4( $services, $pounds, $ounces, $zip, $width, $height, $length );

			} else {

				$services = array(15, 2); // first class and priority

				$estimates = Cairn_Shipping::usps_intlratev2( $services, $pounds, $ounces, $subtotal_usd, $country, $width, $height, $length );

			}

			if ( $estimates ) {

				// use the most economical estimate
				$shipping_usd = false;
				foreach ( $estimates as $estimate ) {
					$service_shipping_usd = round( (float) $estimate['rate'], 2 );
					if ( $service_shipping_usd < $shipping_usd || !$shipping_usd ) {
						$shipping_usd = $service_shipping_usd;
					}
				}

				// round to bitcents with two decimal places
				$shipping_btc = round( $shipping_usd / (float) $bitcoin_exchange_rates->USD->{'7d'}, 4 );

				// stripe, paypal and braintree all charge 2.9% plus 30 cents
				$fees = round( ( 0.029 * $subtotal_usd ) + 0.30, 2 );

				$grand_total_usd = round( $fees + $subtotal_usd + $shipping_usd, 2 );
				$grand_total_btc = round( $subtotal_btc + $shipping_btc, 8 );

				$calculated = array(
					'printing' => array(
						'USD' => $total_printing_usd,
						'BTC' => $total_printing_btc
					),
					'framing' => array(
						'USD' => $total_framing_usd,
						'BTC' => $total_framing_btc
					),
					'production' => array(
						'USD' => $total_production_usd,
						'BTC' => $total_production_btc
					),
					'artist' => array(
						'USD' => $total_artist_usd,
						'BTC' => $total_artist_btc
					),
					'shipping' => array( 
						'USD' => $shipping_usd, 
						'BTC' => $shipping_btc 
					), 
					'fees' => array(
						'USD' => $fees,
						'BTC' => 0
					)
				);

				// testing values
//				$grand_total_btc = (float) 0.0001;
//				$grand_total_usd = (float) 0.50;

				if ( !($grand_total_btc > 0) || !($grand_total_usd > 0) ) {
					print json_encode(array(
						'success' => false,
						'message' => 'Totals can not be zero.'
					));
					exit(0);
				}

				$sql = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET datetime=%s, method=%s, total_btc=%s, total_usd=%s, calculated=%s WHERE p.id=%s;", current_time('mysql'), $method, $grand_total_btc, $grand_total_usd, serialize($calculated), $id );

				$dbresult = $wpdb->query( $sql );

				if ( $dbresult == false ) {

					// there was a database error bail out

					print json_encode(array(
						'success' => false,
						'message' => 'There was a database error when saving your information.'
					));
					exit(0);

				} else {

					// success

					print json_encode( array(
						'success' => true,
						'prices' => $prices,
						'message' => 'Successfully saved totals to database',
						'calculated' => $calculated,
						'method' => $method,
						'total_btc' => $grand_total_btc,
						'total_usd' => $grand_total_usd
					));
					exit(0);

				}

			} else {

				print json_encode( array(
					'success' => false,
				));
				exit(0);

			}

		}

		exit(0);

	}


	/**
	* AJAX callback to calculate the total cost for the post set including shipping costs.
	*
	* Requires the following paramaters:
	*
	* - selected_post _array_ An associative array on which item to change.
	*   - id _integer_ The existing item/post ID
	*   - option _integer_ The existing option number
	*   - quantity _integer_ The new quantity 

	* JSON Object is printed which includes the following attributes:
	*
	* - success _boolean_ If the calculation was successful.
	* - message _string_ The text that should be displayed to the user.
	* 
	*/
	static public function ajax_posts_set_quantity() {

		global $wpdb;

		$selected = $_REQUEST['selected_post'];

		Cairn::hash();

		$row = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', Cairn::$hash ) );

		if ( $row ) {

			if ( $row[0]->status != 'cart' ) {

				print json_encode( array(
					'success' => false,
					'message' => 'This cart can nolonger me modified.'
				));
				die();

			}

			$id = $row[0]->id;
			$products = self::products( $row[0]->id );

            foreach( $products as $k => $p ) {

                if ( $selected['id'] == $p->post_id &&
                     $selected['option'] == $p->post_option ) {

					$available = self::available( $selected['quantity'], $p->post_id, $p->post_option );

					if ( $available ) {

						$products[$k]->post_quantity = $selected['quantity'];

					} else {

						print json_encode( array(
							'success' => false,
							'message' => 'Requested quantity is not currently available, please try again.'
						));
						die();

					}

                }
            }

			$success = self::update( $id, Cairn::$hash, $products );

			if ( !$success ) {

				print json_encode( array(
					'success' => false,
					'message' => 'There was a database error.'
				));
				die();				
			}

		} else {

			print json_encode( array(
				'success' => false,
				'message' => 'That cart does not exist.'
			));
			die();

		}

		print json_encode( array(
			'success' => true
		));

		die();
	}

	/**
	* AJAX callback to add a new item/post to the post_set that must be a status of cart.
	*
	* Requires the following paramaters:
	*
	* - selected_post _array_ An associative array on which item to change.
	*   - id _integer_ The item/post ID
	*   - option _integer_ The option number
	*   - quantity _integer_ The quantity 

	* JSON Object is printed which includes the following attributes:
	*
	* - success _boolean_ If the calculation was successful.
	* - message _string_ The text that should be displayed to the user.
	* 
	*/
	static public function ajax_posts_set_add_post() {
		global $wpdb;
		$selected = $_REQUEST['selected_post'];
		Cairn::hash();
		$row = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', Cairn::$hash ) );

		if ( !$row ) {
			$id = sha1( rand() );

			$quantity = $selected['quantity'];

			$available = self::available( $quantity, $selected['id'], $selected['option'] );

			if ( !$available ) {
				print json_encode(array(
					'success' => false,
					'message' => 'Quantity not available at the requested amount.'
				));
				exit(0);
			}

			$p = new stdClass();
			$p->post_id = $selected['id'];
			$p->post_option = $selected['option'];
			$p->post_quantity = $quantity;

			$success = self::save( $id, Cairn::$hash, array( $p ) );

		} else {

			if ( $row[0]->status != 'cart' ) {
				print json_encode( array(
					'success' => false,
					'message' => 'This cart can nolonger me modified.'
				));
				exit(0);
			}

			$id = $row[0]->id;
			$products = self::products( $id );

            $exists = false;
            foreach( $products as $k => $p ) {
                if ( $selected['id'] == $p->post_id &&
                     $selected['option'] == $p->post_option ) {
                    $exists = true;
					$quantity = $p->post_quantity + $selected['quantity'];
					$products[$k]->post_quantity = $quantity;
                }
            }

            if ( !$exists ) {

				$quantity = $selected['quantity'];

				$p = new stdClass();
				$p->post_id = $selected['id'];
				$p->post_option = $selected['option'];
				$p->post_quantity = $quantity;
                $products[] = $p;
            }

			$available = self::available( $quantity, $selected['id'], $selected['option'] );

			if ( !$available ) {
				print json_encode(array(
					'success' => false,
					'message' => 'Quantity not available at the requested amount.'
				));
				exit(0);
			}

			$success = self::update( $id, Cairn::$hash, $products );
			if ( $success ) {
				print json_encode( array(
					'success' => true,
					'message' => 'Artwork successfully added to cart.'
				));
			} else {
				print json_encode( array(
					'success' => false,
					'message' => 'Unable to add to cart.'
				));
			}
		}

		exit(0);
	}

	/**
	* AJAX callback to remove an item/post from the post_set that must be a status of cart.
	*
	* Requires the following paramaters:
	*
	* - selected_post _array_ An associative array on which item to change.
	*   - id _integer_ The existing item/post ID
	*   - option _integer_ The existing option number

	* JSON Object is printed which includes the following attributes:
	*
	* - success _boolean_ If the calculation was successful.
	* - message _string_ The text that should be displayed to the user.
	* 
	*/
	static public function ajax_posts_set_remove_post() {
		global $wpdb;
		$selected_post = $_REQUEST['selected_post'];
		Cairn::hash();
		$row = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', Cairn::$hash ) );
		if ( !$row ) {
			$success = false;
		} else {

			if ( $row[0]->status != 'cart' ) {
				return array(
					'success' => false,
					'message' => 'This cart can nolonger me modified.'
				);
			}

			$id = $row[0]->id;
			$products = self::products( $id );
			foreach ( $products as $key => $value ) {
				if ( $value->post_id == $selected_post['id'] &&
					$value->post_option == $selected_post['option'] ) {
					array_splice($products, $key, 1);
				}
			}
			$success = self::update( $id, Cairn::$hash, $products );
		}
		print json_encode( array( 'success' => $success ) );
		die();
	}

	/**
	* Returns a post_set from its ID
	*
	* @param string $id The posts_set ID

	* @return array|false
	* - post_set _object_ The direct database object result.
	* - posts _array_ An array of posts for the post set.
	* - products _array_ An array of the post IDs from the post set.
	*/
	static public function get_set_by_id( $id ) {
		global $wpdb;
		$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM '.$wpdb->prefix.'posts_set WHERE id=%s', $id ) );
		if ( $result ) {

			$products = self::products( $result[0]->id );

			$posts = array();
			$post_ids = array();
			foreach ( $products as $product ) {
				$posts[] = get_post( $product->post_id );
				$post_ids[] = $product->post_id;
			}
			//$result[0]->products;
			return array( 'post_set' => $result[0], 'posts' => $posts, 'products' => $post_ids );
		} else {
			return false;
		}
	}

	/**
	* Returns all the item/posts IDs for a post_set from the hash
	*
	* @param string $hash The hash for the post_set

	* @return array The post IDs in the set.
	*/
	static public function get_post_set_ids( $hash ) {
		global $wpdb;

		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', $hash ) );

		$post_ids = array();

		if ( $id ) {
			$products = self::products( $id );

			if ( is_array( $products) ) {
				foreach ( $products as $p ) {
					$post_ids[] = $p->post_id;
				}
			}
		}

		return $post_ids;
	}

	/**
	* Replaces the main database query_posts to only get the posts from a post set.
	*
	* @param string $hash The posts_set hash.
	* @return boolean If the database query was successful.
	*/
	static public function query_posts_set( $hash ) {

		$post_ids = self::get_post_set_ids( $hash );

		if ( $post_ids ) {

			query_posts( array( 'post__in' => $post_ids, 'post_type' => 'fineart', 'numberposts' => -1 ) );
			return true;

		} else {

			return false;
		}

	}

	/**
	* Get all information about a post_set
	*
	* @param string $hash The posts_set hash.
	* @return array 
	* - id _string_ The id of the post set
	* - products _array_ The items/posts/products in the post set:
	*   - id _integer_ The id of the item/post
	*   - option _integer_ The option number for the item
	*   - quantity _integer_ The quantity of the items
	*   - post _object_ The item as returned by Cairn::loop
	* - status _string_ The status of the cart.
	* - hash _string_ The hash for the cart.
	* - method _string The method of payment for the set, 'btc' or 'credit'.
	* - total_usd _float_ The total price for the set in United States Dollars.
	* - total_btc _float_ The total price for the set in Bitcoin.
	* - calculated _array_ The calculated totals for each service (see: ajax_posts_set_calculate)
	*
	*/
	static public function get( $hash ) {

		global $wpdb;

		// get the basic cart query

		$result = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', $hash ) );

		$products = false;
		$total_btc = false;
		$total_usd = false;
		$calculated = false;
		$method = false;

		if ( $result ) {

			$id = $result[0]->id;

			$products = self::products( $id );

			$status = $result[0]->status;				
			$total_btc = $result[0]->total_btc;	
			$total_usd = $result[0]->total_usd;
			$method = $result[0]->method;	
			if ( $result[0]->transaction ) {
				$transaction_id = $result[0]->transaction;
			} else {
				$transaction_id = false;
			}
			$hash = $result[0]->hash;				
			$calculated = unserialize( $result[0]->calculated );

		} else {

			// no cart exists
			
			return false;

		}

		// query the posts for the cart

		$queried = self::query_posts_set( $hash );

		$products_array = array();

		if ( $queried ) {			

			$items = Cairn::loop();

			if ( $items ) {

				foreach ( $products as $k => $c ) {

					foreach ( $items as $item ) {

						if ( $item['id'] == $c->post_id ) {
							$products_array[] = array(
								'id' => $c->post_id,
								'option' => $c->post_option,
								'quantity' => $c->post_quantity,
								'post' => $item
							);
						}
					}
				}

			} 

		}

		// return the cart with or without products						

		return array( 'id' => $id, 'products' => $products_array, 'status' => $status, 'hash' =>  $hash, 'method' => $method, 'total_usd' => $total_usd, 'total_btc' => $total_btc, 'calculated' => $calculated );


	}

	/**
	* Save a new post set 
	*
	* @param string $id The id for the set.
	* @param string $hash The hash for the set.
	* @param array $products
	* - id _integer_ The item/post ID
	* - option _integer_ The option number
    * - quantity _integer_ The quantity
	*
	* @return boolean If the save was successful.
	*/
	static public function save( $id, $hash, $products ) {
		global $wpdb;
		$result = $wpdb->insert( $wpdb->prefix . 'posts_set', array( 'datetime' => current_time('mysql'), 'id' => $id, 'hash' => $hash, 'status' => 'cart'  ) );

		// todo check status			
		self::update( $id, $hash, $products );

		if ( $result ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	* Update a post set in the database
	*
	* @param string $id The id for the set.
	* @param string $hash The hash for the set.
	* @param array $products
	* - id _integer_ The item/post ID
	* - option _integer_ The option number
    * - quantity _integer_ The quantity
	*
	* @return boolean If the save was successful.
	*/
	static public function update( $id, $hash, $products ) {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'posts_set_relationships', array( 'post_set_id' => $id ) );

		if ( is_array( $products ) && count( $products ) > 0 ) {

			$values = array();
			foreach ( $products as $p ) {
				$values[] = $wpdb->prepare( '( %s, %s, %s, %s )', $id, $p->post_id, $p->post_option, $p->post_quantity );
			}

			$insert_sql = 'INSERT INTO ' . $wpdb->prefix . 'posts_set_relationships ( post_set_id, post_id, post_option, post_quantity ) VALUES '.join( ',', $values).';';

			$result = $wpdb->query( $insert_sql );

			if ( $result ) {
				return true;
			}

		}

		return false;

	}

	/**
	* Returns the posts for a post set
	*
	* @param string $id The id for the set.
	* @return object
	* - id _string_ The id for the row
	* - post_set_id _string_ The id for the post set
	* - post_id _integer_ The item/post ID
	* - post_option _integer_ The option number
    * - post_quantity _integer_ The quantity
	*
	*/
	static public function products( $id ) {

		global $wpdb;

		$products = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $wpdb->prefix . 'posts_set_relationships p WHERE p.post_set_id=%s', $id ) );

		if ( $products ) {
			return $products;
		} else {
			return array();
		}
	}


	/**
	* Returns the posts for a post set
	*
	* @param string $hash The hash for the set.
	* @return boolean If the post set is a cart.
	*/
	static public function check_cart_status( $hash ) {
		global $wpdb;

		$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . $wpdb->prefix . 'posts_set p WHERE p.hash=%s', $hash ) );

		if ( $status == 'cart' ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	* Change the status of a post set to expire.
	*
	* @param string $id The id for the set.
	* @return array
	* - success _boolean_ If the change was successful.
	* - message _string_ The message to display to the user.
	*/
	static public function expire( $id ){

		global $wpdb;

		$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET status='expired' WHERE p.id=%s AND p.status='received';", $id );

		$result = $wpdb->query( $sql_status );

		if ( $result ) {
			return array(
				'success' => true,
				'message' => 'Successfully changed status to expired.'
			);
		} else {
			return array(
				'success' => false,
				'message' => 'Did not change status to expired.'
			);
		}

	}

	/**
	* Change the status of a post set to shipped.
	*
	* @param string $id The id for the set.
	* @return array
	* - success _boolean_ If the change was successful.
	* - message _string_ The message to display to the user.
	*/
	static public function shipped( $id ){

		global $wpdb;

		$sql_status = $wpdb->prepare("UPDATE ".$wpdb->prefix."posts_set p SET status='shipped' WHERE p.id=%s AND p.status='confirmed';", $id );

		$result = $wpdb->query( $sql_status );

		if ( $result ) {
			return array(
				'success' => true,
				'message' => 'Successfully changed status to shipped.'
			);
		} else {
			return array(
				'success' => false,
				'message' => 'Did not change status to shipped.'
			);
		}

	}

	/**
	* Check, update and return the hold status of a post set.
	*
	* @param object $order The post_set object
	* @param string $format The format to return the time remaining.
	* @return boolean|string The time remaining if the item is not expired otherwise returns false.
	*/
	static public function hold_expired( $order, $format = false ) {

		global $wpdb;

		// format example: %a days, %h hours, %i minutes and %s seconds

		// only valid for orders that are on hold
		if ( $order->status != 'hold' ) {
			return false;
		}

		// there isn't a hold datetime to 
		if ( $order->hold_datetime == 0 ) {
			return false;
		}

		$difference = strtotime(current_time('mysql', true)) - strtotime($order->hold_datetime);

		if ( $difference < Cairn::$hold_expires_in_secs ) {

			$seconds = Cairn::$hold_expires_in_secs - $difference;
	
			if ( $format ) {
				$a = new DateTime('UTC');
			    $b = clone $a;
			    $b->modify("+$seconds seconds");
			    return $a->diff($b)->format( $format );
			}

			return $seconds;				

		} else {

			// update database 

			$sql_status = $wpdb->prepare( "UPDATE ".$wpdb->prefix . "posts_set p SET p.status='expired' WHERE p.id=%s;", $order->id );

			$confirmed_status = $wpdb->query( $sql_status );

			return true;

		}
	}

	/**
	* Calculates if the item is still available in the desired quantity.
	*
	* @param integer $quantity The desired quantity for an item/post
	* @param integer $post_id The post_id to check
	* @param integer $post_option The post purchase option to check
	* @return boolean|integer The number of items available or true/false.
	*/
	static public function available( $quantity, $post_id, $post_option ){

	    $options = get_post_meta( $post_id, 'purchase_options', true );

		if ( isset( $options[ $post_option ]['ondemand'] ) &&
			$options[ $post_option ]['ondemand'] ) {

			return true;

		} else {

			$total = $options[ $post_option ]['quantity'];

			$holds = self::holds( $post_id, $post_option );

			$solds = self::solds( $post_id, $post_option );

			$available = $total - $holds - $solds - $quantity;

			if ( $available >= 0 ) {

				return true;
			}

		}

		return false;
		
	}

	/**
	* Calculates the number of times the item has been sold.
	*
	* @param integer $post_id The post_id to check.
	* @param integer $post_option The post purchase option to check.
	* @return integer The number of items sold.
	*/
	static public function solds( $post_id, $post_option ){

		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT SUM(post_quantity) FROM ' . $wpdb->prefix . 'posts_set_relationships r LEFT JOIN '.$wpdb->prefix.'posts_set p ON p.id = r.post_set_id WHERE r.post_id=%s AND r.post_option=%s AND ( p.status="confirmed" OR p.status="shipped" );', $post_id, $post_option );

		$total = $wpdb->get_var( $sql );

		if ( !$total ) {
			$total = 0;
		}

		return $total;

	}

	/**
	* Calculates the number of times the item is on hold.
	*
	* @param integer $post_id The post_id to check.
	* @param integer $post_option The post purchase option to check.
	* @return integer The number of times the item is on hold.
	*/
	static public function holds( $post_id, $post_option ){

		global $wpdb;

		$expires_at = new DateTime('UTC');
	    $expires_at->modify('-'.Cairn::$hold_expires_in_secs.' seconds');
		$expires_at_str = $expires_at->format("Y-m-d H:i:s");

		$sql = $wpdb->prepare( 'SELECT SUM(post_quantity) FROM ' . $wpdb->prefix . 'posts_set_relationships r LEFT JOIN '.$wpdb->prefix.'posts_set p ON p.id = r.post_set_id WHERE r.post_id=%s AND r.post_option=%s AND ( ( p.status="hold" AND p.hold_datetime >= %s ) OR p.status="received" ) ;', $post_id, $post_option, $expires_at_str );

		$total = $wpdb->get_var( $sql );

		if ( !$total ) {
			$total = 0;
		}

		return $total;

	}


	/**
	 * Callback to install database scheme.
	*/
	public static function db_install() {
		global $wpdb;

		$table_name = $wpdb->prefix . "posts_set";

		if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

			$sql = "CREATE TABLE " . $table_name . "(
				id VARCHAR(40) NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'cart',
				hash VARCHAR(40) NOT NULL,
				method VARCHAR(32),
				btc_address VARCHAR(36),
				transaction VARCHAR(32),
				confirmation VARCHAR(40),
				calculated TEXT,
				total_btc DECIMAL(16,8),
				total_usd DECIMAL(10,2),
				received_btc DECIMAL(16,8),
				received_usd DECIMAL(10,2),
				paid_btc DECIMAL(16,8),
				paid_usd DECIMAL(10,2),
				refunded_btc DECIMAL(16,8),
				refunded_usd DECIMAL(10,2),
				datetime DATETIME NOT NULL,
				hold_datetime DATETIME NOT NULL,
				received_datetime DATETIME NOT NULL,
				paid_datetime DATETIME NOT NULL,
				refunded_datetime DATETIME NOT NULL,
				shipping BLOB,
				UNIQUE KEY id (id)
			);";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}

		// This table is so that we can search posts set by post_id to see if any 
		// post set have a post, we can then use this to see if an post is on hold
		// if the hold_datetime is still active.

		$table_name_rel = $wpdb->prefix . "posts_set_relationships";

		if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name_rel'") != $table_name_rel ) {

			$sql = "CREATE TABLE " . $table_name_rel . "(
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				post_set_id VARCHAR(40) NOT NULL,
				post_id BIGINT(20) NOT NULL,
				post_option BIGINT(20) NOT NULL,
				post_quantity BIGINT(20) NOT NULL,
				UNIQUE KEY id (id)
			);";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}
}

?>