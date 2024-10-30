<?php 
/**
 * The Cairn Copyright Class
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
 * Creative Commons licensing options for posts and fineart.
 *
 * @package cairn
 */
class Cairn_Copyright {

	/** List of countries */
	static public $countries = array(
		'ar' => 'Argentina',
		'au' => 'Australia',
		'at' => 'Austria',
		'be' => 'Belgium',
		'br' => 'Brazil',
		'bg' => 'Bulgaria',
		'ca' => 'Canada', 
		'cl' => 'Chile', 
		'cn' => 'China Mainland',
		'co' => 'Colombia',
		'cr' => 'Costa Rica', 
		'hr' => 'Croatia', 
		'cz' => 'Czech Republic',
		'dk' => 'Denmark',
		'ec' => 'Ecuador', 
		'ee' => 'Estonia', 
		'fi' => 'Finland', 
		'gr' => 'Greece',
		'gt' => 'Guatemala',
		'hk' => 'Hong Kong',
		'hu' => 'Hungary',
		'in' => 'India', 
		'ie' => 'Ireland',
		'il' => 'Israel',
		'it' => 'Italy', 
		'jp' => 'Japan',
		'lu' => 'Luxembourg',
		'mk' => 'Macedonia', 
		'my' => 'Malaysia',
		'mt' => 'Malta',
		'mx' => 'Mexico',
		'nl' => 'Netherlands', 
		'nz' => 'New Zealand',
		'no' => 'Norway', 
		'pe' => 'Peru', 
		'ph' => 'Philippines',
		'pl' => 'Poland',
		'pt' => 'Portugal',
		'pr' => 'Puerto Rico',
		'ro' => 'Romania', 
		'rs' => 'Serbia',
		'sg' => 'Singapore',
		'si' => 'Slovenia', 
		'za' => 'South Africa',
		'kr' => 'South Korea', 
		'es' => 'Spain', 
		'se' => 'Sweden', 
		'ch' => 'Switzerland',
		'tw' => 'Taiwan', 
		'th' => 'Thailand',
		'uk' => 'UK: England &amp; Wales',
		'scotland' => 'UK: Scotland',
		'us' => 'United States',
		'vn' => 'Vietnam'
	);

	/**
	* Returns HTML display of a license.
	*
	* @param array $license The license array for the post.
	* @return string The html output for the license.
	* 
	*/
	static public function html( $license ) {

		$html = '<span class="copyright">©';
		$html .= get_the_date( 'Y' );
		$html .= ' <a href="'.$license['author_url'].'" rel="license cc:author">'.$license['author'].'</a>.';

		$html .= ' Available under the terms of a <a rel="license" href="'.$license['url'].'">';

		if ( in_array('by', $license['rights'] ) ) {
			$html .= 'Attribution ';
		}
		if ( in_array('nc', $license['rights'] ) ) {
			$html .= 'Noncommercial ';
		}
		if ( in_array('sa', $license['rights'] ) ) {
			$html .= 'ShareAlike ';
		}
		if ( in_array('nd', $license['rights'] ) ) {
			$html .= 'NoDeriv ';
		}
		$html .= '</a> license.</span> ';

		$html .= '</span>';

		return $html;
	}

	/**
	* Returns text display of a license.
	*
	* @param array $license As stored by self::license_box and self::update
	* @return string The text output for the license.
	* 
	*/
	static public function text( $license ) {

		$text = '©';
		$text .= get_the_date( 'Y' );
		$text .= ' '.$license['author'].'. Available under the terms of a ';

		if ( in_array('by', $license['rights'] ) ) {
			$text .= 'Attribution ';
		}
		if ( in_array('nc', $license['rights'] ) ) {
			$text .= 'Noncommercial ';
		}
		if ( in_array('sa', $license['rights'] ) ) {
			$text .= 'ShareAlike ';
		}
		if ( in_array('nd', $license['rights'] ) ) {
			$text .= 'NoDeriv ';
		}


		$text .= 'license ('.$license['url'].')';
		return $text;
	}

	/**
	* Callback to add the license options to post and fineart.
	*/
	static public function license_box(){

		global $post;

		$license = get_post_meta( $post->ID, 'license', true );
		if ( !$license ) {
			$license = array( 'rights' => array( 'by', 'sa' ), 'author' => get_bloginfo('name'), 'author_url' => get_bloginfo('url') ); 
		}
	
		if ( in_array('by', $license['rights'] ) ) {
			$checked = 'checked';
		} else {
			$checked = '';
		}

		echo '<div class="cc_option_wrap"><div id="license_attribution"><input id="license_attribution" type="checkbox" name="license_attribution" '.$checked.'><br/>Attribution</input></div></div>';

		if ( in_array('sa', $license['rights'] ) ) {
			$checked = 'checked';
		} else {
			$checked = '';
		}

		echo '<div class="cc_option_wrap"><div id="license_sharealike"><input type="checkbox" name="license_sharealike" '.$checked.'><br/>Share Alike</input></div></div>';

		if ( in_array('nc', $license['rights'] ) ) {
			$checked = 'checked';
		} else {
			$checked = '';
		}

		echo '<div class="cc_option_wrap"><div id="license_noncommercial"><input type="checkbox" name="license_noncommercial" '.$checked.'><br/>Non Commercial</input></div></div>';

		if ( in_array('nd', $license['rights'] ) ) {
			$checked = 'checked';
		} else {
			$checked = '';
		}

		echo '<div class="cc_option_wrap"><div id="license_noderiv"><input type="checkbox" name="license_noderiv" '.$checked.'><br/>No Deriv</input></div></div>';

		echo '<div class="cc_option_wrap">';
		echo '<div class="cc_option_wrap">';
		echo '<div id="license_author_wrap">';

		print '<div id="license_author">Attribution Name <br/><input type="text" value="'.$license['author'].'" name="license_author"></input></div>';
		print '<div id="license_author_url">Attribution URL <br/><input type="text" value="'.$license['author_url'].'" name="license_author_url"></input></div>';

		echo '</div>';

		echo '</div>';

		echo '<div class="cc_option_wrap"><div id="license_or">OR</div></div>';

		if ( !in_array('by', $license['rights'] ) ) {
			$checked = 'checked';
		} else {
			$checked = '';
		}

		echo '<div class="cc_option_wrap"><div id="license_publicdomain"><input id="license_publicdomain" type="checkbox" name="license_publicdomain" '.$checked.'><br/>Public Domain</input></div></div>';

		echo '</div>';

		?>

		<div class="cc_option_wrap">

		<p>Jurisdiction of your license<p>
		<select name="license_jurisdiction">
			<?php 

			if ( $license['jurisdiction'] == '' ) {
				$selected = ' selected="selected"';
			} else {
				$selected = '';
			}
			print '<option  '.$selected.'value="">International</option>';

			foreach ( self::$countries as $code => $title ) {
				if ( $license['jurisdiction'] == $code ) {
					$selected = ' selected="selected"';
				} else {
					$selected = '';
				}
				print '<option '.$selected.' value="'.$code.'">'.$title.'</option>';
			}
			?>
		</select>

		</div>

		<?php

		echo '<br style="clear:both;">';

    }

	/**
	* Callback to save the license options for the post and fineart.
	* @param integer $post_id The ID of the item/post
	* @param array $postdata The submitted information to change the item/post
	*/
	static public function update( $post_id, $postdata ) {

		$noncommercial = isset( $postdata['license_noncommercial'] ) ? $postdata['license_noncommercial'] : false;

		$sharealike = isset( $postdata['license_sharealike'] ) ? $postdata['license_sharealike'] : false;

		$attribution = isset( $postdata['license_attribution'] ) ? $postdata['license_attribution'] : false;

		$noderiv = isset( $postdata['license_noderiv'] ) ? $postdata['license_noderiv'] : false;

		$jurisdiction = isset( $postdata['license_jurisdiction'] ) ? $postdata['license_jurisdiction'] : false;

		if ( $jurisdiction == '' ) {
			$country_title = 'International';
			$country_url = '';
		} else {
			$country_title = self::$countries[$jurisdiction];
			$country_url = $jurisdiction.'/';
		}

		if ( $noderiv && $sharealike ) {

			$messages[] = __('Licensing can not be both ShareAlike and NoDeriv', 'cairn');

		} else {

			if ( !$noderiv && !$sharealike && !$attribution && !$noncommercial ) {
				$license = array(
					'rights' => array(),
					'title' => 'Public Domain', 
					'jurisdiction' => '', 
					'url' => 'http://creativecommons.org/publicdomain/mark/1.0/'
					);
			} else {
				if ( $noncommercial && $sharealike ) {
					// cc-by-nc-sa
					$license = array(
						'rights' => array('by', 'nc', 'sa'),
						'title' => 'Attribution NonCommercial ShareAlike 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by-nc-sa/3.0/'.$country_url
					);
				} else if ( $noncommercial && $noderiv ) {
					// cc-by-nc-nd
					$license = array(
						'rights' => array('by', 'nc', 'nd'),
						'title' => 'Attribution NonCommercial NoDeriv 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by-nc-nd/3.0/'.$country_url
					);
				} else if ( $noderiv ) {
					// cc-by-nd
					$license = array(
						'rights' => array('by', 'nd'),
						'title' => 'Attribution NoDeriv 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by-nd/3.0/'.$country_url
					);
				} else if ( $noncommercial ) {
					// cc-by-nc
					$license = array(
						'rights' => array('by', 'nc'),
						'title' => 'Attribution NonCommercial 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by-nc/3.0/'.$country_url
					);
				} else if ( $sharealike ) {
					// cc-by-sa
					$license = array(
						'rights' => array('by', 'sa'),
						'title' => 'Attribution ShareAlike 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by-sa/3.0/'.$country_url
					);
				} else {
					// cc-by
					$license = array(
						'rights' => array('by'),
						'title' => 'Attribution 3.0 '.$country_title, 
						'jurisdiction' => $jurisdiction, 
						'url' => 'http://creativecommons.org/licenses/by/3.0/'.$country_url
					);
				}

				$license['author'] = $postdata['license_author'];
				$license['author_url'] = $postdata['license_author_url'];


				$old = get_post_meta( $post_id, 'license', true );

				update_post_meta( $post_id, 'license', $license,  $old );
			}
		}
	}
}

?>
