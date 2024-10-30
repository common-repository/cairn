<?php
/**
 * The Cairn Post Class
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
 * Modifications to the Post to include copyright licensing options.
 *
 * @package cairn
 */
class Cairn_Post {

	/**
	 * Gets the formatted body for a post including licensing information.
	 *
	 * @param object $item The item object as return by Cairn::loop
	 * @param boolean $title Weither or not to include the title.

	 * @return string The formated body HTML.
	 */
	static public function body( &$item, $title = true ) {

        ob_start();
        Cairn::display('post.php', array( 'item' => $item, 'title' => $title ) );
        $body = preg_replace('/\s+/', ' ', ob_get_contents());
        ob_end_clean();

		return $body;

	}

	/**
	 * Callback to add licensing box
	 */
	static public function init(){
		add_action( 'save_post', 'Cairn_Post::save_postdata' );
		add_action( 'add_meta_boxes', 'Cairn_Post::add_custom_box' );
		add_action( 'admin_print_styles', 'Cairn_Post::header', 10, 1 );
	}


	/**
	 * Callback to save licensing information
	 */
	static public function save_postdata( $post_id ) {

		// verify if this is an auto save routine. 
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;

		Cairn_Copyright::update( $post_id, $_POST );

	}

	/**
	 * Callback to add styles for the licensing box
	 */
	static public function header( $page ) {
		wp_enqueue_style( 'cairn-options-css', plugin_dir_url( __FILE__ ).'../static/options.css');
		wp_enqueue_script( 'cairn-options-js', plugin_dir_url( __FILE__ ).'../static/options.js');

		wp_enqueue_style( 'cairn-admin-css', plugin_dir_url( __FILE__ ).'../static/shop-admin.css');
		wp_enqueue_script( 'cairn-admin-js', plugin_dir_url( __FILE__ ).'../static/shop-admin.js');

	}

	/**
	 * Callback to add HTML for the licensing box
	 */
	static public function add_custom_box( ) {

        add_meta_box(
			'post_license', 
			'Copyright & Licensing', 
			'Cairn_Copyright::license_box', 
			'post', 
			'normal', 
			'high'
		);

	}

}

?>
