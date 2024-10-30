<?php
/**
 * The Cairn Shop Post Class
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
 * Adds the fineart post type with several additional meta boxes.
 *
 * @package cairn
 */
class Cairn_Fineart_Post {

	/**
	 * Gets the formatted body for a post including licensing information.
	 *
	 * @param object $item The item object as return by Cairn::loop
	 * @param boolean $title Weither or not to include the title.

	 * @return string The formated body HTML.
	 */
	static public function body( &$item, $title = true ) {

        ob_start();
        Cairn::display('fineart.php', array( 'item' => $item, 'title' => $title ) );
        $body = preg_replace('/\s+/', ' ', ob_get_contents());
        ob_end_clean();

		return $body;

	}

	/**
	 * Callback to initialize the fineart post type.
	 */
	static public function init(){

		$labels = array(
			'name' => __('Shop'),
		    'singular_label' => __('Shop'),
		    'add_new' => __('Add Shop'),
		    'add_new_item' => __('Add Shop'),
		    'edit_item' => __('Edit Shop'),
		    'new_item' => __('New Shop'),
		    'view_item' => __('View Shop'),
		    'search_items' => __('Search Shop'),
		    'not_found' => __('No fine art found'),
		    'not_found_in_trash' => ('No fine art found in Trash'),
		    'parent_item_colon' => ''
		);

		// Register custom post types
		register_post_type('fineart', array(
			'labels' => $labels,
			'public' => true,
			'show_ui' => true, // UI in admin panel
			'_builtin' => false, // It's a custom post type, not built in
			'_edit_link' => 'post.php?post=%d',
			'capability_type' => 'fineart',
			'capabilities' => array(
				'publish_posts' => 'publish_finearts',
				'edit_posts' => 'edit_finearts',
				'edit_others_posts' => 'edit_others_finearts',
				'delete_posts' => 'delete_finearts',
				'delete_others_posts' => 'delete_others_finearts',
				'read_private_posts' => 'read_private_finearts',
				'edit_post' => 'edit_fineart',
				'delete_post' => 'delete_fineart',
				'read_post' => 'read_fineart'
			),
			'hierarchical' => false,
			'rewrite' => array("slug" => "fineart"), // Permalinks
			'supports' => array('title','author', 'thumbnail' /*,'custom-fields'*/) // Let's use custom fields for debugging purposes only
		));

		$roles = array( 'editor', 'administrator' );

		foreach ( $roles as $role ) {
			$role = get_role( $role );
			$role->add_cap( 'publish_finearts' );
			$role->add_cap( 'edit_finearts' );
			$role->add_cap( 'edit_others_finearts' );
			$role->add_cap( 'delete_finearts' );
			$role->add_cap( 'delete_others_finearts' );
			$role->add_cap( 'read_private_finearts' );
			$role->add_cap( 'edit_fineart' );
			$role->add_cap( 'delete_fineart' );
			$role->add_cap( 'read_fineart' );
			$role->add_cap( 'manage_categories' );
		}
		
		add_filter( 'map_meta_cap', 'Cairn_Fineart_Post::map_meta_cap', 10, 4 );
		add_filter( 'post_updated_messages', 'Cairn_Fineart_Post::updated_messages' );
		add_action( 'save_post', 'Cairn_Fineart_Post::save_postdata' );
		add_action( 'add_meta_boxes', 'Cairn_Fineart_Post::add_custom_box' );
		add_action( 'admin_print_styles', 'Cairn_Fineart_Post::header', 10, 1 );
	}

	/**
	 * Callback to save additional information for the fineart post type.
	 * @param integer $post_id The ID for the post.
	 */
	static public function save_postdata( $post_id ) {
		// verify if this is an auto save routine. 
		// If it is our form has not been submitted, so we dont want to do anything
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;

		// verify this came from the our screen and with proper authorization,
		// because save_post can be triggered at other times

		// Check permissions
		if ( isset( $_POST['post_type'] ) && $_POST['post_type'] == 'fineart' ) {
			if ( !current_user_can( 'edit_fineart', $post_id ) )
				return;
		} else {
			if ( !current_user_can( 'edit_fineart', $post_id ) )
				return;
		}


		if ( isset( $_POST['sales'] ) ){
			update_post_meta( $post_id, 'sales', $_POST['sales'] );
		}

		if ( isset( $_POST['details'] ) ){
			update_post_meta( $post_id, 'details', $_POST['details'] );
		}

		// OK, we're authenticated: we need to find and save the data

		if (isset($_POST['purchase_option'])){
			$purchase_options = array();
			foreach($_POST['purchase_option'] as $option){
				if ( $option['value'] ) {
					$option['value'] = esc_attr($option['value']);
				}
	            if($option['name'] != ''){
					array_push($purchase_options, $option);
				}
			}
			update_post_meta( $post_id, 'purchase_options', $purchase_options );
		}

		if ( isset( $_POST['downloads'] ) ){
			$download_options = array();
			foreach ( $_POST['downloads'] as $option ) {
				$option['size'] = (int) $option['size'];
				if( $option['name'] != '' ){
					array_push( $download_options, $option );
				}
			}
			update_post_meta( $post_id, 'downloads', $download_options );
		}

		if ( isset( $_POST['attributions'] ) ){
			$attribution_options = array();
			foreach ( $_POST['attributions'] as $option ) {
				if( $option['html'] != '' ){
					array_push( $attribution_options, $option );
				}
			}
			update_post_meta( $post_id, 'attributions', $attribution_options );
		}

		Cairn_Copyright::update( $post_id, $_POST );

	}

	/**
	 * Callback to add styles and scripts for the fineart post type.
	 */
	static public function header() {
		wp_enqueue_style( 'cairn-options-css', plugin_dir_url( __FILE__ ).'../static/admin/options.css');
		wp_enqueue_script( 'cairn-options-js', plugin_dir_url( __FILE__ ).'../static/admin/options.js');

		wp_enqueue_style( 'cairn-admin-css', plugin_dir_url( __FILE__ ).'../static/admin/shop-admin.css');
		wp_enqueue_script( 'cairn-admin-js', plugin_dir_url( __FILE__ ).'../static/admin/shop-admin.js');

	}

	/**
	 * Callback to add meta boxes for the fineart post type.
	 */
	static public function add_custom_box( ) {

        add_meta_box(
			'cairn_fineart_license', 
			'Copyright & Licensing', 
			'Cairn_Copyright::license_box', 
			'fineart', 
			'normal', 
			'high'
		);

	    add_meta_box( 
	        'cairn_fineart_details',
	        __( 'Details', 'cairn' ),
	        'Cairn_Fineart_Post::details_box',
	        'fineart',
			'side',
			'core'
	    );
	    add_meta_box(
			'cairn_fineart_purchase', 
			__('Purchase Options', 'cairn'), 
			'Cairn_Fineart_Post::purchase_box', 
			'fineart', 
			'normal', 
			'high'
		);
	    add_meta_box(
	        'cairn_fineart_downloads',
	        __( 'Downloads', 'cairn' ), 
	        'Cairn_Fineart_Post::downloads_box',
	        'fineart',
			'normal',
			'high'
	    );
	    add_meta_box(
	        'cairn_fineart_attributions',
	        __( 'References & Attributions', 'cairn' ), 
	        'Cairn_Fineart_Post::attributions_box',
	        'fineart',
			'normal',
			'high'
	    );
	}

	/**
	 * Callback to add the purchase options meta box.
	 */
	static public function purchase_box(){
	    global $post;

	    $html = '<table class="purchase-options"><tbody>';
	    $html .= '<tr class="purchase-option"><th>Basic Information</th><th>Shipping Information</th><th>Meta Information</th><th>Action</th></tr>';
	    $purchase_options = get_post_meta($post->ID, 'purchase_options', true);

	    $c = 0;

        if (!$purchase_options) $purchase_options = array();

	    foreach($purchase_options as $option){

			$holds = Cairn_Post_Set::holds( $post->ID, $c );
			$solds = Cairn_Post_Set::solds( $post->ID, $c );

			$coacheck = '';
			if ( isset($option['coa']) && $option['coa'] ) $coacheck = ' checked';
			$signcheck = '';
			if ( isset($option['signed']) && $option['signed'] ) $signcheck = ' checked';
			$ondemand = '';

			if ( isset($option['ondemand']) && $option['ondemand'] ) { 
				$ondemand = ' checked';
				$available = '∞';
			} else {
				$available = $option['quantity'] - $holds - $solds;
			}

			$html .= '<tr class="purchase-option"><td><p><strong>Option Name:</strong> <input class="purchase-option-name" name="purchase_option['.$c.'][name]" type="text" size="20" value="'.esc_attr($option['name']).'"/></p><p><strong>Printing Cost</strong> <em>(USD)</em>:<input class="purchase-option-price" name="purchase_option['.$c.'][printing-cost]" type="text" value="'.$option['printing-cost'].'" size="3"/></p><p><strong>Framing Cost</strong> <em>(USD):</em><input class="purchase-option-price" name="purchase_option['.$c.'][framing-cost]" type="text" value="'.$option['framing-cost'].'" size="3"/></p><p><strong>Production Cost</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['.$c.'][production-cost]" type="text" value="'.$option['production-cost'].'" size="3"/></p><p><strong>Artist Fee</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['.$c.'][artist-fee]" type="text" value="'.$option['artist-fee'].'" size="3"/></p><p><strong>SKU</strong> <em>(Stock Keeping Unit Number)</em>: <input type="text" class="purchase-option-sku" name="purchase_option['.$c.'][sku]" value="'.$option['sku'].'" size="3"/></p></td><td><p><strong>Weight</strong> <em>(ounces)</em>:<input class="purchase-option-weight" name="purchase_option['.$c.'][weight]" type="text" value="'.$option['weight'].'" size="2"/></p><p><strong>Width</strong> <em>(inches)</em>:<br/><input class="purchase-option-width" name="purchase_option['.$c.'][width]" type="text" value="'.$option['width'].'" size="2"/></p><p><strong>Height</strong> <em>(inches)</em>:<br/><input class="purchase-option-height" name="purchase_option['.$c.'][height]" type="text" value="'.$option['height'].'" size="2"/></p><p><strong>Length</strong> <em>(inches)</em>:<br/><input class="purchase-option-length" name="purchase_option['.$c.'][length]" type="text" value="'.$option['length'].'" size="2"/></p></td><td><p><input class="ondemand-field" type="checkbox" name="purchase_option['.$c.'][ondemand]" '.$ondemand.'/> On-Demand</p><p class="quantity-field"><strong>Total Quantity:</strong> <br/><input class="purchase-option-quantity" name="purchase_option['.$c.'][quantity]" type="text" value="'.$option['quantity'].'" size="2"/></p><p>Quantity on Hold: '.$holds.'</p><p>Quantity Sold: '.$solds.'</p><p>Available: '.$available.'</p><p><input type="checkbox" name="purchase_option['.$c.'][coa]" '.$coacheck.'/> Certificate of Authenticity</p><p><input type="checkbox" name="purchase_option['.$c.'][signed]" '.$signcheck.'/> Signed</p></td><td></td></tr>';
			$c = $c + 1;
	    }

		$html .= '<tr class="purchase-option"><td><p><strong>Option Name:</strong> <input class="purchase-option-name" name="purchase_option['.$c.'][name]" type="text" size="20" value=""/></p><p><strong>Printing Cost</strong> <em>(USD)</em>:<input class="purchase-option-price" name="purchase_option['.$c.'][printing-cost]" type="text" value="" size="3"/></p><p><strong>Framing Cost</strong> <em>(USD):</em><input class="purchase-option-price" name="purchase_option['.$c.'][framing-cost]" type="text" value="" size="3"/></p><p><strong>Production Cost</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['.$c.'][production-cost]" type="text" value="" size="3"/></p><p><strong>Artist Fee</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['.$c.'][artist-fee]" type="text" value="" size="3"/></p><p><strong>SKU</strong> <em>(Stock Keeping Unit Number)</em>: <input type="text" class="purchase-option-sku" name="purchase_option['.$c.'][sku]" value="" size="3"/></p></td><td><p><strong>Weight</strong> <em>(ounces)</em>:<input class="purchase-option-weight" name="purchase_option['.$c.'][weight]" type="text" value="" size="2"/></p><p><strong>Width</strong> <em>(inches)</em>:<br/><input class="purchase-option-width" name="purchase_option['.$c.'][width]" type="text" value="" size="2"/></p><p><strong>Height</strong> <em>(inches)</em>:<br/><input class="purchase-option-height" name="purchase_option['.$c.'][height]" type="text" value="" size="2"/></p><p><strong>Length</strong> <em>(inches)</em>:<br/><input class="purchase-option-length" name="purchase_option['.$c.'][length]" type="text" value="" size="2"/></p></td><td><p class="quantity-field"><strong>Quantity:</strong> <br/><input class="purchase-option-quantity" name="purchase_option['.$c.'][quantity]" type="text" value="" size="2"/></p><p><input class="ondemand-field" type="checkbox" name="purchase_option['.$c.'][ondemand]"/> <strong>On-Demand</strong></p><p><input type="checkbox" name="purchase_option['.$c.'][coa]"/> Certificate of Authenticity</p><p><input type="checkbox" name="purchase_option['.$c.'][signed]"/> Signed</p></td><td></td></tr>';

	    $html .= '</tbody></table>';

	    echo $html;

	}

	/**
	 * Callback to add the download options meta box.
	 */
	static public function downloads_box( $post ) {
	    global $post;
	    $html = '<table class="download-options"><tbody>';
	    $html .= '<tr class="download-option"><th>Title</th><th>Format/MIME Type</th><th>Size/Bytes</th><th>URL</th><th></th></tr>';
	    $download_options = get_post_meta($post->ID, 'downloads', true);

	    $c = 0;

        if (!$download_options) $download_options = array();

	    foreach($download_options as $option){
			$html .= '<tr class="download-option"><td><input class="download-option-name" name="downloads['.$c.'][name]" type="text" size="8" value="'.esc_attr($option['name']).'"/></td><td><input class="download-option-format" name="downloads['.$c.'][format]" type="text" value="'.$option['format'].'" size="3"/></td><td><input type="text" class="download-option-size" name="downloads['.$c.'][size]" value="'.$option['size'].'" size="3"/></td><td><input class="download-option-url" name="downloads['.$c.'][url]" type="text" value="'.$option['url'].'" size="20"/></td><td></td></tr>';
			$c = $c + 1;
	    }

	    $html .= '<tr class="download-option"><td><input class="download-option-name" name="downloads['.$c.'][name]" type="text" size="8" value=""/></td><td><input class="download-option-format" name="downloads['.$c.'][format]" type="text" value="" size="3"/></td><td><input type="text" class="download-option-size" name="downloads['.$c.'][size]" value="" size="3"/></td><td><input class="download-option-url" name="downloads['.$c.'][url]" type="text" value="" size="20"/></td><td></td></tr>';
	    $html .= '</tbody></table>';

	    echo $html;
	}

	/**
	 * Callback to add the attributions and references options meta box.
	 */
	static public function attributions_box( $post ) {
	    global $post;
	    $html = '<table class="attribution-options"><tbody>';
	    $html .= '<tr class="attribution-option"><th>Link</th><th></th></tr>';
	    $attribution_options = get_post_meta($post->ID, 'attributions', true);

	    $c = 0;

        if (!$attribution_options) $attribution_options = array();

	    foreach($attribution_options as $option){
			$html .= '<tr class="attribution-option"><td><input class="attribution-option-html" name="attributions['.$c.'][html]" type="text" size="8" value="'.esc_attr($option['html']).'"/></td><td></td></tr>';
			$c = $c + 1;
	    }

	    $html .= '<tr class="attribution-option"><td><input class="attribution-option-html" name="attributions['.$c.'][html]" type="text" size="8" value=""/></td><td></td></tr>';
	    $html .= '</tbody></table>';

	    echo $html;
	}

	/**
	 * Callback to add the details meta box.
	 */
	static public function details_box( $post ) {

	    $post_details = get_post_meta($post->ID, 'details', true );
		if ( !$post_details ) {
			$post_details = array('width' => '', 'height' => '', 'depth' => '', 'description' => '');
		}

		echo '<table class="fineart-dimensions">';
		echo '<tr><th>Width</th><th>Height</th><th>Depth</th></tr>';
		echo '<tr>';
		// The actual fields for data entry
		echo '<td>';
		echo '<input type="text" id="details-width" name="details[width]" value="'.$post_details['width'].'" size="5" /></td>';

		// The actual fields for data entry
		echo '<td>';
		echo '<input type="text" id="details-height" name="details[height]" value="'.$post_details['height'].'" size="5" /></td>';

		// The actual fields for data entry
		echo '<td>';
		echo '<input type="text" id="details-depth" name="details[depth]" value="'.$post_details['depth'].'" size="5" /></td>';
		echo '</tr>';

		echo '</table>';

		echo '<table class="fineart-dimensions fineart-description">';

		echo '<tr><th>Description</th></tr>';

		// The actual fields for data entry
		echo '<tr><td><textarea type="text" id="details-description" name="details[description]" value="" size="30">'.$post_details['description'].'</textarea></td></tr>';

		echo '</table>';
	}


	/**
	 * Pagination for the orders pages.
	 */
	static public function pagination( $total_items, $total_pages, $current ) {

		$output = '<span class="displaying-num">' . sprintf( _n( '1 item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';

		$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		$current_url = remove_query_arg( array( 'hotkeys_highlight_last', 'hotkeys_highlight_first' ), $current_url );

		$page_links = array();

		$disable_first = $disable_last = '';

		if ( $current == 1 ) {
			$disable_first = ' disabled';
		}

		if ( $current == $total_pages ) {
			$disable_last = ' disabled';
		}

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'first-page' . $disable_first,
			esc_attr__( 'Go to the first page' ),
			esc_url( remove_query_arg( 'paged', $current_url ) ),
			'&laquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'prev-page' . $disable_first,
			esc_attr__( 'Go to the previous page' ),
			esc_url( add_query_arg( 'paged', max( 1, $current-1 ), $current_url ) ),
			'&lsaquo;'
		);

		$html_current_page = sprintf( "<input class='current-page' title='%s' type='text' name='paged' value='%s' size='%d' />",
			esc_attr__( 'Current page' ),
			$current,
			strlen( $total_pages )
		);

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = '<span class="paging-input">' . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . '</span>';

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'next-page' . $disable_last,
			esc_attr__( 'Go to the next page' ),
			esc_url( add_query_arg( 'paged', min( $total_pages, $current+1 ), $current_url ) ),
			'&rsaquo;'
		);

		$page_links[] = sprintf( "<a class='%s' title='%s' href='%s'>%s</a>",
			'last-page' . $disable_last,
			esc_attr__( 'Go to the last page' ),
			esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
			'&raquo;'
		);

		$pagination_links_class = 'pagination-links';

		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class = ' hide-if-js';
		}

		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}

		return "<div class='tablenav-pages{$page_class}'>$output</div>";
	}

	static public function map_meta_cap( $caps, $cap, $user_id, $args ) {

		if ( 'edit_fineart' == $cap || 'delete_fineart' == $cap || 'read_fineart' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );
			$caps = array();
		}

		if ( 'edit_fineart' == $cap ) {
			if ( $user_id == $post->post_author ) {
				$caps[] = $post_type->cap->edit_posts;
			} else {
				$caps[] = $post_type->cap->edit_others_posts;
			}
		} else if ( 'delete_fineart' == $cap ) {
			if ( $user_id == $post->post_author ) {
				$caps[] = $post_type->cap->delete_posts;
			} else {
				$caps[] = $post_type->cap->delete_others_posts;
			}
		} else if ( 'read_fineart' == $cap ) {
			if ( 'private' != $post->post_status ){
				$caps[] = 'read';
			} else if ( $user_id == $post->post_author ) {
				$caps[] = 'read';
			} else {
				$caps[] = $post_type->cap->read_private_posts;
			}
		}

		return $caps;
	}

	static public function updated_messages( $messages ) {

	    global $post, $post_ID;

	    $messages['fineart'] = array(
				    0 => '', 
				    1 => sprintf( __('Shop updated. <a href="%s">View Shop</a>'), esc_url( get_permalink($post_ID) ) ),
				    2 => __('Custom field updated.'),
				    3 => __('Custom field deleted.'),
				    4 => __('Shop updated.'),
				    5 => isset($_GET['revision']) ? sprintf( __('Shop restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				    6 => sprintf( __('Shop published. <a href="%s">View Shop</a>'), esc_url( get_permalink($post_ID) ) ),
				    7 => __('Shop saved.'),
				    8 => sprintf( __('Shop submitted. <a target="_blank" href="%s">Preview Shop</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				    9 => sprintf( __('Shop scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Shop</a>'),
						  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
				    10 => sprintf( __('Shop draft updated. <a target="_blank" href="%s">Preview Shop</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				    );

	    return $messages;
	}
	
}

?>
