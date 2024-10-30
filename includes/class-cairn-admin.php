<?php
/**
 * The Cairn Admin Class
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
 * Administration pages for settings, carts, holds, orders and etcetera.
 *
 * @package cairn
 */
class Cairn_Admin {

	/**
	* Callback to add pages to the menu.
	*/
	static public function add_admin_menus(){
		add_submenu_page( 'edit.php?post_type=fineart', 'Orders & Carts', 'Orders & Carts', 'edit_finearts', 'product-orders', 'Cairn_Admin::orders_page' );

		$settings_hook = add_submenu_page( 'edit.php?post_type=fineart', 'Settings', 'Settings', 'edit_finearts', 'cairn-settings', 'Cairn_Admin::settings_page' );

		$portfolio_settings_hook = add_submenu_page( 'edit.php?post_type=portfolio', 'Settings', 'Settings', 'edit_portfolios', 'cairn-portfolio-settings', 'Cairn_Admin::portfolio_settings_page' );

		add_action('admin_print_styles-' . $portfolio_settings_hook, 'Cairn_Admin::portfolio_styles');
		add_action('admin_print_scripts-' . $portfolio_settings_hook, 'Cairn_Admin::portfolio_scripts');

		add_action('admin_print_styles-' . $settings_hook, 'Cairn_Admin::portfolio_styles');
		add_action('admin_print_scripts-' . $settings_hook, 'Cairn_Admin::portfolio_scripts');

		// add page without menu
		$hookname = get_plugin_page_hookname( 'product-order', 'edit.php?post_type=fineart' );
		add_filter( $hookname, 'Cairn_Admin::order_page' );
		$GLOBALS['_registered_pages'][$hookname] = true;
	}

	/**
	* Callback to remove pages to the menu.
	*/
	static function remove_admin_menus() {

		// Removes the Appearance tab from the administration interface.

		global $menu;
		$restricted = array(__('Appearance'));

		end($menu);
		while (prev($menu)) {
			$value = explode(' ',$menu[key($menu)][0]);
			if ( in_array( $value[0] != NULL?$value[0]:"" , $restricted ) ) {
				unset($menu[key($menu)]);
			}
		}

		// Removes the Permalinks sub menu from the Settings panel.
		global $submenu;

		foreach ( $submenu as $menu_key => $menu_item ) {  
			if ( $menu_key == 'options-general.php') {  
				foreach ( $menu_item as $submenu_key => $submenu_items ) { 
					if ( $submenu_items[2] == 'options-permalink.php') {  
						unset( $submenu[$menu_key][$submenu_key] );  
						break;
					}  
				}  
			}  
		}
	} 

	/**
	* Callback to disable themes.
	*/
	static function disable_themes() {
		// Silence is golden.
	    return;
	}

	/**
	* Callback for the orders page.
	*/
	static public function orders_page() {

		// then lets render the page
		global $wpdb;
		$cuser = wp_get_current_user();

		$total_items = (int) $wpdb->get_var( 'SELECT COUNT(id) FROM ' .$wpdb->prefix . 'posts_set p');
		$posts_per_page = (int) get_option('posts_per_page');
		if ( $total_items > $posts_per_page ) {
			$total_pages = ceil( $total_items / $posts_per_page );
		} else {
			$total_pages = 1;
		}

		if ( isset( $_REQUEST['status'] ) ) {
			$where = $wpdb->prepare(' WHERE status=%s ', $_REQUEST['status']);
		} else {
			$where = '';
		}

		$sql = 'SELECT * FROM ' . $wpdb->prefix . 'posts_set p '.$where.'ORDER BY datetime DESC';
		if ( $posts_per_page > 0 ) {
			$sql .= ' LIMIT '.$posts_per_page;
		}

		if ( isset( $_REQUEST['paged'] ) ) {
			$offset = $posts_per_page * ( $_REQUEST['paged'] - 1 );
			$sql .= ' OFFSET '.$offset;
			$current = (int) $_REQUEST['paged'];
		} else {
			$current = 1;
		}

		$result = $wpdb->get_results( $sql );

		$cart_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="cart";');

		$hold_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="hold";');

		$received_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="received";');

		$expired_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="expired";');

		$confirmed_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="confirmed";');
		$shipped_count = $wpdb->get_var('SELECT COUNT(id) FROM '.$wpdb->prefix.'posts_set p WHERE status="shipped";');

		print '<div class="wrap">';
		print '<div class="icon32 icon32-posts-product" id="icon-edit"><br></div>';
		print '<h2>Orders & Carts</h2>';


//		print '<a href="'.admin_url('edit.php?post_type=fineart&page=product-orders&update_order_statuses=1').'">Update Status of Pending Orders</a>';

		// lets make sure that all the orders are up-to-date
//		if ( isset( $_REQUEST['update_order_statuses'] ) ) {

//			$count = self::update_order_statuses();

//			if ( $count > 0 ) {

//				print '<div id="message" class="updated below-h2"><p>Checked the status of '.$count.' orders that are currently processing.</p></div>';

//			}

//		}

		print '<div class="tablenav top">';
		print '<ul class="subsubsub">';
		print '<li class="all"><a class="current" href="edit.php?post_type=fineart&page=product-orders">All <span class="count">('.$total_items.')</span></a> | </li> ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=cart"> Cart <span class="count">('.$cart_count.')</span></a> | </li> ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=hold"> Hold <span class="count">('.$hold_count.')</span></a> | </li> ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=received"> Received <span class="count">('.$received_count.')</span></a> | </li> ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=expired"> Expired <span class="count">('.$expired_count.')</span></a> | </li> ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=confirmed"> Confirmed <span class="count">('.$confirmed_count.')</span></a></li> | ';
		print '<li class="checkout"> <a href="edit.php?post_type=fineart&page=product-orders&status=shipped"> Shipped <span class="count">('.$shipped_count.')</span></a></li> ';

		print '</ul>';

		
		print '<div class="tablenav-pages">'.Cairn_Fineart_Post::pagination( $total_items, $total_pages, $current ).'</div>';

		print '</div>';

		print '<table class="wp-list-table widefat fixed products" cellspacing="0">';
		print '<tr><th>Method</th><th>Status</th><th>Fine Art</th><th>Totals</th></tr>';

		foreach ( $result as $order ) {

			$products = Cairn_Post_Set::products( $order->id );

			$posts = array();
			$posts_html = '<ul>';
			foreach ( $products as $product ) {
				$post = get_post( $product->post_id );
				$posts_html .= '<li><a href="'.get_permalink($post->ID).'">'.$post->post_title.'</a><br/>';
				$posts_html .= 'Option: '.$product->post_option.'<br/>';
				$posts_html .= 'Quantity: '.$product->post_quantity.'<br/>';
				$posts_html .= '</li>';
			}
			$posts_html .= '</ul>';


			print '<tr>';


			print '<td>'.$order->method.'</td>';
			print '<td>'.$order->status; 
			print ' <a href="'.admin_url('edit.php?post_type=fineart&page=product-order&post_set_id='.$order->id).'"> (View)</a></td>';

			print '</td>';

 			print '<td>'.$posts_html.'</td>';
			print '<td>';
			if ( $order->paid_usd ) {
				print '<p><strong>Paid Total: '.$order->paid_usd.' USD</strong></p>';
			} 
			if ( $order->paid_btc ) {
				print '<p><strong>Paid Total: '.$order->paid_btc.' BTC</strong></p>';
			} 

			if ( $order->received_usd ) {
				print '<p>Received Total: '.$order->received_usd.' USD</p>';
			}
			if ( $order->received_btc ) {
				print '<p>Received Total: '.$order->received_btc.' BTC</p>';
			}

			if ( $order->total_usd ) {
				print '<p>Total: '.$order->total_usd.' USD</p>';
			} 
			if ( $order->total_btc ) {
				print '<p>Total: '.$order->total_btc.' BTC</p>';
			} 

			print '</td>';

			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}

	/**
	* Callback to for the single order page.
	*/
	static public function order_page() {

		global $wpdb;

		// make the page

		if ( isset ( $_GET['post_set_id'] ) ) {
			$id = $_GET['post_set_id'];
		} else {
			print 'Not Found';
		}

		$order_group = Cairn_Post_Set::get_set_by_id( $id );
		$order = $order_group['post_set'];


		// do our actions

		if ( isset( $_REQUEST['cairn_action'] ) ) {
			$action = $_REQUEST['cairn_action'];
			switch ( $action ) {
				case "send_shipping":
					Cairn_Email::email( '[Shipping Information]', $order->shipping );
					break;
				case "capture_and_confirm":
					$data = Cairn_Stripe_Payment::capture_invoice( $order->id );
					echo '<div id="message" class="updated fade">';
					echo '<p>'.$data['message'].'</p></div>';
					break;
				case "expire":
					$data = Cairn_Post_Set::expire( $order->id );
					echo '<div id="message" class="updated fade">';
					echo '<p>'.$data['message'].'</p></div>';
					break;
					break;
				case "shipped":
					$data = Cairn_Post_Set::shipped( $order->id );
					echo '<div id="message" class="updated fade">';
					echo '<p>'.$data['message'].'</p></div>';
					break;
				case "status":

					if ( $order->method == 'btc' ) {					
						$status = Cairn_Bitcoin_Wallet::status_invoice( $order );
					} else if ( $order->method == 'credit' ) {
						$status = Cairn_Stripe_Payment::status_invoice( $order );
					}
					if ( !$status['success'] ) {
						echo '<div id="message" class="updated fade">';
						echo '<p>'.$status['message'].'</p></div>';
					}
					break;
			}
		}

		// print the page

		print '<div class="wrap">';
		print '<div class="icon32 icon32-posts-product" id="icon-edit"><br></div>';
		print '<h2>Order</h2>';

		print '<div class="tablenav top">';
		print '</div>';

		print '<table class="wp-list-table widefat fixed products" cellspacing="0">';
		print '<tr><th>Confirmation</th><th>Date</th><th>Method</th><th>Status</th><th>Cart ID</th><th>Transaction ID</th><th>Bitcoin Address</th></tr>';


		print '<tr>';

		print '<td class="confirmation-id">'.$order->confirmation.'</td>';
		print '<td>'.$order->datetime.'</td>';
		print '<td>'.$order->method.'</td>';
		print '<td>'.$order->status.'</td>';
		print '<td>'.$order->id.'</td>';

		print '<td>'.$order->transaction.'</td>';

		print '<td>'.$order->btc_address.'</td></tr></table><br/>';
	
	
		print '<table class="wp-list-table widefat fixed products" cellspacing="0"><tr><th>Fine Art</th><th>Totals</th></tr><tr>';

		$products = Cairn_Post_Set::products( $order->id );

		$posts = array();
		$posts_html = '<td><ul>';
		foreach ( $products as $product ) {
			$post = get_post( $product->post_id );
			$posts_html .= '<li><a href="'.get_permalink($post->ID).'">'.$post->post_title.'</a><br/>';
			$posts_html .= 'Option: '.$product->post_option.'<br/>';
			$posts_html .= 'Quantity: '.$product->post_quantity.'<br/>';
			$posts_html .= '</li>';
		}
		$posts_html .= '</ul></td>';

		print $posts_html;
		print '<td>';

		if ( $order->paid_usd ) {
			print '<p><strong>Paid Total: '.$order->paid_usd.' USD</strong></p>';
		}
		if ( $order->paid_btc ) {
			print '<p><strong>Paid Total: '.$order->paid_btc.' BTC</strong></p>';
		}


		if ( $order->received_usd ) {
			print '<p>Received Total: '.$order->received_usd.' USD</p>';
		}
		if ( $order->received_btc ) {
			print '<p>Received Total: '.$order->received_btc.' BTC</p>';
		}

		if ( $order->total_usd ) {
			print '<p>Total: '.$order->total_usd.' USD</p>';
		}
		if ( $order->total_btc ) {
			print '<p>Total: '.$order->total_btc.' BTC</p>';
		}


		print '</td></tr>';

		print '</table>';

		print '<div class="order-actions">';

		if ( $order->status == 'received' || $order->status == 'hold' || $order->status == 'expired' ) {
			print '<p><form method="POST"><input type="hidden" name="cairn_action" value="status"/><input type="submit" value="Update Status" id="action-completed" class="button button-primary button-large"></form></p>';
		}

		if ( $order->shipping ) {

			print '<p><form method="POST"><input type="hidden" name="cairn_action" value="send_shipping" /><input type="submit" value="Email Shipping Information" id="action-completed" class="button button-primary button-large"></form></p>';
		}

		if ( $order->status == 'received' && $order->method == 'credit' ) {
			print '<p><form method="POST"><input type="hidden" name="cairn_action" value="capture_and_confirm"/><input type="submit" value="Capture & Confirm Credit Card" id="action-completed" class="button button-primary button-large"></form></p>';
		}

		if ( $order->status == 'received' ) {
			print '<p><form method="POST"><input type="hidden" name="cairn_action" value="expire"/><input type="submit" value="Manual Expire Order" id="action-completed" class="button button-primary button-large"></form></p>';
		}

		if ( $order->status == 'confirmed' ) {
			print '<p><form method="POST"><input type="hidden" name="cairn_action" value="shipped"/><input type="submit" value="Change to Shipped" id="action-completed" class="button button-primary button-large"></form></p>';
		}

		print '</div>';

		print '</div>';
	}

	/**
	* Callback to add scripts to portfolio settings
	*/
	static public function portfolio_styles() {
		wp_enqueue_style('thickbox');
		wp_enqueue_style('media-views');
	}

	/**
	* Callback to add scripts to portfolio settings
	*/
	static public function portfolio_scripts() {
		wp_enqueue_script('thickbox');
		wp_enqueue_script('media-views', 'thickbox');
	}

	/**
	* Callback to for the settings page.
	*/
	static public function portfolio_settings_page() {

		if ( isset( $_POST['settings_update'] ) ) {

			update_option('cairn_display_text', (string)$_POST["cairn_display_text"]);
			update_option('cairn_button_text', (string)$_POST["cairn_button_text"]);
			update_option('cairn_button_link', (string)$_POST["cairn_button_link"]);
			update_option('cairn_video_poster', (array)$_POST["cairn_video_poster"]);

			if ( isset( $_POST['videos'] ) ){
				$video_sources = array();
				foreach($_POST['videos'] as $source){
					if ( $source['url'] && $source['url'] != '' ) {
						$source['url'] = esc_attr($source['url']);
					}
					if($source['type'] != ''){
						array_push($video_sources, $source);
					}
				}
				update_option( 'cairn_video_sources', $video_sources );
			}

		}

		$cairn_video_sources = get_option('cairn_video_sources', false);
		$cairn_video_poster = get_option('cairn_video_poster', false);
		$cairn_display_text = get_option('cairn_display_text', false);
		$cairn_button_text = get_option('cairn_button_text', false);
		$cairn_button_link = get_option('cairn_button_link', false);

	?>

		<div class="wrap">

		<div class="icon32 icon32-posts-product" id="icon-edit"><br></div>
		<h2>Cairn Portfolio Settings</h2>

		<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="settings_update" value="true"/>

		<h3 class="title">Introduction</h3>
		<p>These are the variables to change to configure the welcome page introduction video banner. The video sources should be large enough to be scaled full screen. The poster image will show up on mobile and will not autoplay, otherwise the video will autoplay and loop. It is recommended to not include sound for these videos. The text will overlay the video with a button below it as the main action when visiting the website. The button link should use absolute format such as '/portfolio/' with a leading slash.</p>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">Video Sources</th>
					<td>

					<table class="video-sources">
						<tbody>
							<tr class="video-source"><th class="video-header-id">ID</th><th class="video-header-type">Type</th><th class="video-header-url">URL</th><th class="video-header-max">Max-width (px)</th><th class="video-header-actions"></th></tr>
						<?php 
			
						$c = 0;
			
						$html = '';

						foreach( $cairn_video_sources as $source ) {
							$html .= '<tr class="video-source"><td><input class="video-source-id" name="videos['.$c.'][id]" type="text" value="'.$source['id'].'" size="3"/></td><td><input class="video-source-type" name="videos['.$c.'][type]" type="text" value="'.$source['type'].'" size="3"/></td><td><input class="video-source-url" name="videos['.$c.'][url]" type="text" value="'.$source['url'].'" size="20"/><a class="video-source-upload-button add-new-h2">Select Video</a></td><td><input class="video-source-max" name="videos['.$c.'][max]" type="text" value="'.$source['max'].'" size="3"/></td><td></td></tr>';
							$c = $c + 1;
						}
			
						$html .= '<tr class="video-source"><td><input class="video-source-id" name="videos['.$c.'][id]" type="text" value="" size="3"/></td><td><input class="video-source-type" name="videos['.$c.'][type]" type="text" value="" size="3"/></td><td><input class="video-source-url" name="videos['.$c.'][url]" type="text" value="" size="20"/><a class="video-source-upload-button add-new-h2">Select Video</a></td><td><input class="video-source-max" name="videos['.$c.'][max]" type="text" value="" size="3"/></td><td></td></tr>';
			
						print $html;
			
						?>						
							</tbody>
						</table>

					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Video Poster</th>
					<td>
						<table class="image-sources">
							<tbody>
								<tr class="image-source">
									<th class="image-header-id">ID</th>
									<th class="image-header-url">URL</th>
								</tr>
								<tr class="image-source">
									<td>
										<input class="image-source-id" name="cairn_video_poster[id]" type="text" value="<?php print $cairn_video_poster['id']; ?>" size="3"/>
									</td>
									<td>
										<input class="image-source-url" name="cairn_video_poster[url]" type="text" value="<?php print $cairn_video_poster['url']; ?>" size="20"/><a class="image-upload-button add-new-h2">Select Image</a>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Display Text</th>
					<td>
						<input type="text" name="cairn_display_text" value="<?php print $cairn_display_text; ?>" size="100" /><p class="description">This is the large introduction text.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Button Text</th>
					<td>
						<input type="text" name="cairn_button_text" value="<?php print $cairn_button_text; ?>" size="100" /><p class="description">This is the text for the introduction button.</p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Button Link</th>
					<td>
						<input type="text" name="cairn_button_link" value="<?php print $cairn_button_link; ?>" size="100" /><p class="description">This is the URL link value for the introduction button.</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<input type="submit" class="button-primary"  value="Save Changes"/>
		</p>	

		</form>
		</div>

	<?php

	}


	/**
	* Callback to for the settings page.
	*/
	static public function settings_page() {

		if ( isset( $_POST['settings_update'] ) ) {

			update_option('cairn_bitcoin_master_public_key', (string)$_POST["cairn_bitcoin_master_public_key"]);
			update_option('cairn_stripe_api_key', (string)$_POST["cairn_stripe_api_key"]);
			update_option('cairn_usps_api_key', (string)$_POST["cairn_usps_api_key"]);
			update_option('cairn_usps_api_zip_code', (string)$_POST["cairn_usps_api_zip_code"]);
			update_option('cairn_email', (string)$_POST["cairn_email"]);
			update_option('cairn_email_public_key', (string)$_POST["cairn_email_public_key"]);
			update_option('cairn_email_public_key_id', (string)$_POST["cairn_email_public_key_id"]);
			update_option('cairn_mailing_address1', (string)$_POST["cairn_mailing_address1"]);
			update_option('cairn_mailing_address2', (string)$_POST["cairn_mailing_address2"]);
			update_option('cairn_mailing_city', (string)$_POST["cairn_mailing_city"]);
			update_option('cairn_mailing_state', (string)$_POST["cairn_mailing_state"]);
			update_option('cairn_mailing_zip', (string)$_POST["cairn_mailing_zip"]);
			update_option('cairn_mailing_country', (string)$_POST["cairn_mailing_country"]);
			update_option('cairn_mailing_pots_phone', (string)$_POST["cairn_mailing_pots_phone"]);
			update_option('cairn_mailing_sip_phone', (string)$_POST["cairn_mailing_sip_phone"]);
			update_option('cairn_mailing_bitmessage', (string)$_POST["cairn_mailing_bitmessage"]);
			update_option('cairn_mailing_company_name', (string)$_POST["cairn_mailing_company_name"]);
			update_option('cairn_mailing_name', (string)$_POST["cairn_mailing_name"]);
			update_option('cairn_posts_copyright', (string)$_POST["cairn_posts_copyright"]);
			update_option('cairn_fineart_copyright', (string)$_POST["cairn_fineart_copyright"]);
			update_option('cairn_portfolio_copyright', (string)$_POST["cairn_portfolio_copyright"]);
			update_option('cairn_posts_copyright_url', (string)$_POST["cairn_posts_copyright_url"]);
			update_option('cairn_portfolio_copyright_url', (string)$_POST["cairn_portfolio_copyright_url"]);
			update_option('cairn_fineart_copyright_url', (string)$_POST["cairn_fineart_copyright_url"]);
			update_option('cairn_about_page', (string)$_POST["cairn_about_page"]);
			update_option('cairn_shipping_page', (string)$_POST["cairn_shipping_page"]);
			update_option('cairn_privacy_page', (string)$_POST["cairn_privacy_page"]);
			update_option('cairn_large_logo', (array)$_POST["cairn_large_logo"]);
			update_option('cairn_small_logo', (array)$_POST["cairn_small_logo"]);

			$overrides = array( 'test_form' => false);

			echo '<div id="message" class="updated fade">';
			echo '<p><strong>Options Updated</strong></p></div>';
		} 

		$cairn_large_logo = get_option('cairn_large_logo', false);
		$cairn_small_logo = get_option('cairn_small_logo', false);
		$cairn_bitcoin_master_public_key = get_option('cairn_bitcoin_master_public_key', false);
		$cairn_stripe_api_key = get_option('cairn_stripe_api_key', false);
		$cairn_usps_api_key = get_option('cairn_usps_api_key', false);
		$cairn_usps_api_zip_code = get_option('cairn_usps_api_zip_code', false);
		$cairn_email = get_option('cairn_email', false);
		$cairn_email_public_key = get_option('cairn_email_public_key', false);
		$cairn_email_public_key_id = get_option('cairn_email_public_key_id', false);
		$cairn_mailing_address1 = get_option('cairn_mailing_address1', false);
		$cairn_mailing_address2 = get_option('cairn_mailing_address2', false);
		$cairn_mailing_city = get_option('cairn_mailing_city', false);
		$cairn_mailing_state = get_option('cairn_mailing_state', false);
		$cairn_mailing_zip = get_option('cairn_mailing_zip', false);
		$cairn_mailing_country = get_option('cairn_mailing_country', false);
		$cairn_mailing_sip_phone = get_option('cairn_mailing_sip_phone', false);
		$cairn_mailing_bitmessage = get_option('cairn_mailing_bitmessage', false);
		$cairn_mailing_pots_phone = get_option('cairn_mailing_pots_phone', false);
		$cairn_mailing_company_name = get_option('cairn_mailing_company_name', false);
		$cairn_mailing_name = get_option('cairn_mailing_name', false);
		$cairn_fineart_copyright = get_option('cairn_fineart_copyright', false);
		$cairn_portfolio_copyright = get_option('cairn_portfolio_copyright', false);
		$cairn_posts_copyright = get_option('cairn_posts_copyright', false);
		$cairn_fineart_copyright_url = get_option('cairn_fineart_copyright_url', false);
		$cairn_portfolio_copyright_url = get_option('cairn_portfolio_copyright_url', false);
		$cairn_posts_copyright_url = get_option('cairn_posts_copyright_url', false);

		?>

		<div class="wrap">

		<div class="icon32 icon32-posts-product" id="icon-edit"><br></div>
		<h2>Cairn Shop Settings</h2>

		<p>These are the variables to configure recieving payment for items listed in your shop. When an order comes in it will be encrypted with your GPG public key and sent to your email, all customer information is encrypted. It is strongly advised to keep all private keys on storage that is disconnected from a computer, and to use them only when nessasary. Configuration of Bitcoin payments is to your Electrum Bitcoin wallet. Addresses are generated using your master public key that you can enter below.</p>

		<form method="post">
		<input type="hidden" name="settings_update" value="true"/>

		<h3 class="title">Trademarks</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">Large Logo (100px PNG)</th>
					<td>
						<table class="image-sources">
							<tbody>
								<tr class="image-source">
									<th class="image-header-id">ID</th>
									<th class="image-header-url">URL</th>
								</tr>
								<tr class="image-source">
									<td>
										<input class="image-source-id" name="cairn_large_logo[id]" type="text" value="<?php print $cairn_large_logo['id']; ?>" size="3"/>
									</td>
									<td>
										<input class="image-source-url" name="cairn_large_logo[url]" type="text" value="<?php print $cairn_large_logo['url']; ?>" size="20"/><a class="image-upload-button add-new-h2">Select Image</a>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Small Logo (40px PNG)</th>
					<td>
						<table class="image-sources">
							<tbody>
								<tr class="image-source">
									<th class="image-header-id">ID</th>
									<th class="image-header-url">URL</th>
								</tr>
								<tr class="image-source">
									<td>
										<input class="image-source-id" name="cairn_small_logo[id]" type="text" value="<?php print $cairn_small_logo['id']; ?>" size="3"/>
									</td>
									<td>
										<input class="image-source-url" name="cairn_small_logo[url]" type="text" value="<?php print $cairn_small_logo['url']; ?>" size="20"/><a class="image-upload-button add-new-h2">Select Image</a>
									</td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			</tbody>
		</table>
		<h3 class="title">Shipping Email</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="cairn_email">Email Address</label></th>
					<td><input type="text" name="cairn_email" value="<?php print $cairn_email; ?>" size="100" /><p class="description">This email will receive shipping information for orders and messages.</p></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="siteurl">Email Address GPG Public Key ID</label></th>
					<td><input type="text" name="cairn_email_public_key_id" size="100" value="<?php print $cairn_email_public_key_id ?>"/></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="blogdescription">Email Address GPG Public Key</label></th>
					<td><textarea name="cairn_email_public_key" cols="100" rows="5"><?php print $cairn_email_public_key ?></textarea>
					<p class="description">Make sure that you can decrypt messages from this public key before accepting sales. Information on generating keys is available on <a href="http://www.gnupg.org/gph/en/manual.html">GNU Privacy Handbook</a></p>
				</tr>
			</tbody>
		</table>
		<h3 class="title">Payments</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="cairn_bitcoin_master_public_key">Bitcoin Electrum Master Public Key</label></th>
					<td><textarea name="cairn_bitcoin_master_public_key" cols="100" rows="5"><?php print $cairn_bitcoin_master_public_key ?></textarea>
					<p class="description">Make sure you can spend coins with this wallet before accepting payments on it, and that you have a backup of the seed. This is used to generate a unique address to recieve bitcoins. It is accessible from <em><a href="http://electrum.org/">Electrum</a> -> Preferences -> Import/Export -> Master Public Key -> Show</em></p></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="cairn_stripe_api_key">Stripe API Key</label></th>
					<td><input type="text" name="cairn_stripe_api_key" value="<?php print $cairn_stripe_api_key; ?>" size="100" /><p class="description">This is used to connect to Stripe API to authorize and capture credit card transactions. It is accessible from <em><a href="http://stripe.com/">Stripe.com</a> -> Your Account -> API Keys -> Secret Key</em></p></td>
				</tr>
				<tr valign="top">
					<th scope="row">USPS Web Tools API</th>
					<td><input type="text" name="cairn_usps_api_key" value="<?php print $cairn_usps_api_key; ?>" size="100" /><p class="description">This is used to calculate shipping costs, you can obtain a key at <a href="https://www.usps.com/business/web-tools-apis/welcome.htm">USPS.com</a> </em></p></td>
				</tr>
				<tr valign="top">
					<th scope="row">Origin Zip Code</th>
					<td><input type="text" name="cairn_usps_api_zip_code" value="<?php print $cairn_usps_api_zip_code; ?>" size="100" /><p class="description">This is the origin zip code used to calculate shipping costs.</em></p></td>
				</tr>
			</tbody>
		</table>
		<h3 class="title">Contact</h3>
		<table class="form-table">
			<tbody>	
				<tr valign="top">
					<th scope="row">Company Name</th>
					<td><input type="text" name="cairn_mailing_company_name" value="<?php print $cairn_mailing_company_name; ?>" size="100" /></td>
				</tr>			
				<tr valign="top">
					<th scope="row">Full Name</th>
					<td><input type="text" name="cairn_mailing_name" value="<?php print $cairn_mailing_name; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Mailing Address Line 1</th>
					<td><input type="text" name="cairn_mailing_address1" value="<?php print $cairn_mailing_address1; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Mailing Address Line 2</th>
					<td><input type="text" name="cairn_mailing_address2" value="<?php print $cairn_mailing_address2; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">City</th>
					<td><input type="text" name="cairn_mailing_city" value="<?php print $cairn_mailing_city; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">State</th>
					<td><input type="text" name="cairn_mailing_state" value="<?php print $cairn_mailing_state; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Zip Code</th>
					<td><input type="text" name="cairn_mailing_zip" value="<?php print $cairn_mailing_zip; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Country</th>
					<td><input type="text" name="cairn_mailing_country" value="<?php print $cairn_mailing_country; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">SIP Phone</th>
					<td><input type="text" name="cairn_mailing_sip_phone" value="<?php print $cairn_mailing_sip_phone; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Bitmessage Address</th>
					<td><input type="text" name="cairn_mailing_bitmessage" value="<?php print $cairn_mailing_bitmessage; ?>" size="100" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">POTS Phone</th>
					<td><input type="text" name="cairn_mailing_pots_phone" value="<?php print $cairn_mailing_pots_phone; ?>" size="100" /></td>
				</tr>
			</tbody>
		</table>			
		<h3 class="title">Pages</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">About Page</th>
					<td><label for="page_on_front"><?php print wp_dropdown_pages( array( 'name' => 'cairn_about_page', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'cairn_about_page' ) ) ); ?></label></td>
				</tr>
				<tr valign="top">
					<th scope="row">Shipping and Returns Page</th>
					<td><label for="page_on_front"><?php print wp_dropdown_pages( array( 'name' => 'cairn_shipping_page', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'cairn_shipping_page' ) ) ); ?></label></td>
				</tr>
				<tr valign="top">
					<th scope="row">Privacy Page</th>
					<td><label for="page_on_front"><?php print wp_dropdown_pages( array( 'name' => 'cairn_privacy_page', 'echo' => 0, 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0', 'selected' => get_option( 'cairn_privacy_page' ) ) ); ?></label></td>
				</tr>
			</tbody>
		</table>
		<h3 class="title">Copyright Notices</h3>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">Fine Art Copyright</th>
					<td><input type="text" name="cairn_fineart_copyright" value="<?php print $cairn_fineart_copyright; ?>" size="100" placeholder="© Your Name. Some rights reserved."/><p class="description">This is listed in the feeds and HTML head.</p></td>
				</tr>
				<tr valign="top">
					<th scope="row">Fine Art Copyright URL</th>
					<td><input type="text" name="cairn_fineart_copyright_url" value="<?php print $cairn_fineart_copyright_url; ?>" size="100" placeholder="//creativecommons.org/licenses/by-sa/3.0/" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Portfolio Copyright</th>
					<td><input type="text" name="cairn_portfolio_copyright" value="<?php print $cairn_portfolio_copyright; ?>" size="100" placeholder="© Your Name. Some rights reserved."/><p class="description">This is listed in the feeds and HTML head.</p></td>
				</tr>
				<tr valign="top">
					<th scope="row">Portfolio Copyright URL</th>
					<td><input type="text" name="cairn_portfolio_copyright_url" value="<?php print $cairn_portfolio_copyright_url; ?>" size="100" placeholder="//creativecommons.org/licenses/by-sa/3.0/" /></td>
				</tr>
				<tr valign="top">
					<th scope="row">News Copyright</th>
					<td><input type="text" name="cairn_posts_copyright" value="<?php print $cairn_posts_copyright; ?>" size="100" placeholder="© Your Name. Some rights reserved." /><p class="description">This is listed in the feeds and HTML head.</p></td>
				</tr>
				<tr valign="top">
					<th scope="row">News Copyright URL</th>
					<td><input type="text" name="cairn_posts_copyright_url" value="<?php print $cairn_posts_copyright_url; ?>" size="100" placeholder="//creativecommons.org/licenses/by-sa/3.0/" /></td>
				</tr>

			</tbody>
		</table>


		<p class="submit">
			<input type="submit" class="button-primary"  value="Save Changes"/>
		</p>

		</form>
		</div>

	<?php
	}
}

?>