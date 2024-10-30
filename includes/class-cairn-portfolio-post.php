<?php
/**
 * The Cairn Portfolio Post Class
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
 * Adds the portfolio post type with several additional meta boxes.
 *
 * @package cairn
 */
class Cairn_Portfolio_Post {

	/**
	 * Gets the formatted body for a post including licensing information.
	 *
	 * @param object $item The item object as return by Cairn::loop
	 * @param boolean $title Weither or not to include the title.

	 * @return string The formated body HTML.
	 */
	static public function body( &$item, $title = true ) {

        ob_start();
        Cairn::display('portfolio.php', array( 'item' => $item, 'title' => $title ) );
        $body = preg_replace('/\s+/', ' ', ob_get_contents());
        ob_end_clean();

		return $body;

	}

	/**
	 * Callback to initialize the fineart post type.
	 */
	static public function init(){

		$labels = array(
			'name' => __('Portfolio'),
		    'singular_label' => __('Portfolio'),
		    'add_new' => __('Add Portfolio Item'),
		    'add_new_item' => __('Add Portfolio Item'),
		    'edit_item' => __('Edit Portfolio Item'),
		    'new_item' => __('New Portfolio Item'),
		    'view_item' => __('View Portfolio Item'),
		    'search_items' => __('Search Portfolio Items'),
		    'not_found' => __('No portfolio items found'),
		    'not_found_in_trash' => ('No portfolio items found in Trash'),
		    'parent_item_colon' => ''
		);

		// Register custom post types
		register_post_type('portfolio', array(
			'labels' => $labels,
			'public' => true,
			'show_ui' => true, // UI in admin panel
			'_builtin' => false, // It's a custom post type, not built in
			'_edit_link' => 'post.php?post=%d',
			'capability_type' => 'portfolio',
			'capabilities' => array(
				'publish_posts' => 'publish_portfolios',
				'edit_posts' => 'edit_portfolios',
				'edit_others_posts' => 'edit_others_portfolios',
				'delete_posts' => 'delete_portfolios',
				'delete_others_posts' => 'delete_others_portfolios',
				'read_private_posts' => 'read_private_portfolios',
				'edit_post' => 'edit_portfolio',
				'delete_post' => 'delete_portfolio',
				'read_post' => 'read_portfolio'
			),
			'hierarchical' => false,
			'rewrite' => array("slug" => "portfolio"), // Permalinks
			'supports' => array('title','author', 'thumbnail' /*,'custom-fields'*/) // Let's use custom fields for debugging purposes only
		));

		$roles = array( 'editor', 'administrator' );

		foreach ( $roles as $role ) {
			$role = get_role( $role );
			$role->add_cap( 'publish_portfolios' );
			$role->add_cap( 'edit_portfolios' );
			$role->add_cap( 'edit_others_portfolios' );
			$role->add_cap( 'delete_portfolios' );
			$role->add_cap( 'delete_others_portfolios' );
			$role->add_cap( 'read_private_portfolios' );
			$role->add_cap( 'edit_portfolio' );
			$role->add_cap( 'delete_portfolio' );
			$role->add_cap( 'read_portfolio' );
			$role->add_cap( 'manage_categories' );
		}

		add_filter( 'media_send_to_editor', 'Cairn_Portfolio_Post::media_send_to_editor', 20, 3 );
		add_filter( 'map_meta_cap', 'Cairn_Portfolio_Post::map_meta_cap', 10, 4 );
		add_filter( 'post_updated_messages', 'Cairn_Portfolio_Post::updated_messages' );
		add_action( 'save_post', 'Cairn_Portfolio_Post::save_postdata' );
		add_action( 'add_meta_boxes', 'Cairn_Portfolio_Post::add_custom_box' );
		add_action( 'admin_print_styles', 'Cairn_Portfolio_Post::header', 10, 1 );
	}

	static public function media_send_to_editor( $html, $id ){

		$attachment = get_post( $id );

		$out = "";
		if ( preg_match( "/video/", $attachment->post_mime_type ) ){
		    $html = '<video controls="controls"><source data-id="'.$attachment->ID.'" src="'.wp_get_attachment_url($attachment->ID).'" type="'.$attachment->post_mime_type.'"></source></video>';
		} else if ( preg_match( "/image/", $attachment->post_mime_type ) ){
			$html = preg_replace("/<img/", '<img data-id="'.$attachment->ID.'"', $html);
		}
		return $html;
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
		if ( isset( $_POST['post_type'] ) && $_POST['post_type'] == 'portfolio' ) {
			if ( !current_user_can( 'edit_portfolio', $post_id ) )
				return;
		} else {
			if ( !current_user_can( 'edit_portfolio', $post_id ) )
				return;
		}

		// OK, we're authenticated: we need to find and save the data

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
			update_post_meta( $post_id, 'videos', $video_sources );
		}

		if ( isset( $_POST['videos_feed'] ) ){
			update_post_meta( $post_id, 'videos_feed', $_POST['videos_feed'] );
		}

		

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
	        'cairn_portfolio_video',
	        __( 'Video Sources (Sizes)', 'cairn' ), 
	        'Cairn_Portfolio_Post::video_box',
	        'portfolio',
			'normal',
			'high'
	    );

        add_meta_box(
			'cairn_fineart_license', 
			'Copyright & Licensing', 
			'Cairn_Copyright::license_box', 
			'portfolio', 
			'normal', 
			'high'
		);

	    add_meta_box( 
	        'cairn_portfolio_details',
	        __( 'Details', 'cairn' ),
	        'Cairn_Portfolio_Post::details_box',
	        'portfolio',
			'side',
			'core'
	    );

	    add_meta_box(
	        'cairn_portfolio_attributions',
	        __( 'References & Attributions', 'cairn' ), 
	        'Cairn_Portfolio_Post::attributions_box',
	        'portfolio',
			'normal',
			'core'
	    );
	}


	/**
	 * Callback to add the download options meta box.
	 */
	static public function video_box( $post ) {
	    global $post;
	    $html = '<table class="video-sources"><tbody>';
	    $html .= '<tr class="video-source"><th>ID</th><th>Type</th><th>URL</th><th>Max-width (px)</th><th>Feed Primary</th><th></th></tr>';
	    $video_sources = get_post_meta($post->ID, 'videos', true);
	    $videos_feed = get_post_meta($post->ID, 'videos_feed', true);

	    $c = 0;

        if (!$video_sources) $video_sources = array();

	    foreach($video_sources as $source){
			if ( $videos_feed == $c ) {
				$feedchecked = ' checked';
			} else {
				$feedchecked = '';
			}
			$html .= '<tr class="video-source"><td><input class="video-source-id" name="videos['.$c.'][id]" type="text" value="'.$source['id'].'" size="3"/></td><td><input class="video-source-type" name="videos['.$c.'][type]" type="text" value="'.$source['type'].'" size="3"/></td><td><input class="video-source-url" name="videos['.$c.'][url]" type="text" value="'.$source['url'].'" size="20"/><a class="video-source-upload-button add-new-h2">Select Video</a></td><td><input class="video-source-max" name="videos['.$c.'][max]" type="text" value="'.$source['max'].'" size="3"/></td><td><input type="radio" value="'.$c.'" name="videos_feed" '.$feedchecked.'/></td><td></td></tr>';
			$c = $c + 1;
	    }

	    $html .= '<tr class="video-source"><td><input class="video-source-id" name="videos['.$c.'][id]" type="text" value="" size="3"/></td><td><input class="video-source-type" name="videos['.$c.'][type]" type="text" value="" size="3"/></td><td><input class="video-source-url" name="videos['.$c.'][url]" type="text" value="" size="20"/><a class="video-source-upload-button add-new-h2">Select Video</a></td><td><input class="video-source-max" name="videos['.$c.'][max]" type="text" value="" size="3"/></td><td><input type="radio" value="'.$c.'" name="videos_feed"/></td><td></td></tr>';
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

		echo '<table class="fineart-dimensions fineart-description">';

		echo '<tr><th>Description</th></tr>';

		// The actual fields for data entry
		echo '<tr><td><textarea type="text" id="details-description" name="details[description]" value="" size="30" rows="12">'.$post_details['description'].'</textarea></td></tr>';

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

		if ( 'edit_portfolio' == $cap || 'delete_portfolio' == $cap || 'read_portfolio' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );
			$caps = array();
		}

		if ( 'edit_portfolio' == $cap ) {
			if ( $user_id == $post->post_author ) {
				$caps[] = $post_type->cap->edit_posts;
			} else {
				$caps[] = $post_type->cap->edit_others_posts;
			}
		} else if ( 'delete_portfolio' == $cap ) {
			if ( $user_id == $post->post_author ) {
				$caps[] = $post_type->cap->delete_posts;
			} else {
				$caps[] = $post_type->cap->delete_others_posts;
			}
		} else if ( 'read_portfolio' == $cap ) {
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

	    $messages['portfolio'] = array(
				    0 => '', 
				    1 => sprintf( __('Portfolio Item updated. <a href="%s">View Portfolio Item</a>'), esc_url( get_permalink($post_ID) ) ),
				    2 => __('Custom field updated.'),
				    3 => __('Custom field deleted.'),
				    4 => __('Portfolio item updated.'),
				    5 => isset($_GET['revision']) ? sprintf( __('Portfolio item restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				    6 => sprintf( __('Portfolio item published. <a href="%s">View Item</a>'), esc_url( get_permalink($post_ID) ) ),
				    7 => __('Portfolio item saved.'),
				    8 => sprintf( __('Portfolio item submitted. <a target="_blank" href="%s">Preview Item</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				    9 => sprintf( __('Portfolio item scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Item</a>'),
						  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
				    10 => sprintf( __('Portfolio item draft updated. <a target="_blank" href="%s">Preview Item</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				    );

	    return $messages;
	}
	
}

?>
