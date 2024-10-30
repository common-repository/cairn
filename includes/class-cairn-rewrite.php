<?php
/**
 * The Cairn Rewrite Class
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
 * All rewrite rules are defined in a JSON file at static/urls.json 
 *
 * @package cairn
 */
class Cairn_Rewrite {

	static public $rules;
	static private $_rules;
	static private $_replace = array(
			'%year%' => array('year', '([0-9]{4})'),
			'%monthnum%' => array('monthnum', '([0-9]{1,2})'),
			'%day%' => array('day', '([0-9]{1,2})'),
			'%hour%' => array('hour', '([0-9]{1,2})'),
			'%minute%' => array('minute', '([0-9]{1,2})'),
			'%second%' => array('second', '([0-9]{1,2})'),
			'%postname%' => array('name', '([^/]+)'),
			'%posts_set%' => array('posts_set', '([^/]+)'),
			'%posts_set_status%' => array('posts_set_status', '([^/]+)'),
			'%category%' => array('category_name', '(.+?)'),
			'%expertise%' => array('expertise', '(.+?)'),
			'%location%' => array('location', '(.+?)'),
			'%tag%' => array('tag', '(.+?)'),
			'%paged%' => array('paged', '([0-9]+)'),
			'%author%' => array('author_name', '([^/]+)'),
			'%pagename%' => array('pagename', '(.+?)'),
			'%search%' => array('s', '(.+)'),
			'%feed%' => array('feed', '(feed|rdf|rss|rss2|atom)'),
	);


	/**
	* Returns the permalink for a matching request. Every request defined in static/urls.json must be unque, so that there are not matching and duplicate urls for the same request.

	* Here is an example usage:
	* <pre>
	* $year = mysql2date("Y", $post->post_date);
	* $month = mysql2date("n", $post->post_date);
	* $uri = Cairn_Rewrite::uri(array(
	*         'post_type' => 'post', 
	*         'year' => $year, 
	*         'monthnum' => $month, 
	*         'name' => $post_name
	*     ), 
	*     'default', 
	*     true, 
	*     false
	* );
	* </pre>

	* @param array $request The request as associative array that will match a rule.
	* @param string $view Name of the view for matching requests.
	* @param boolean $unique Weither to return one or several matches.
	* @param boolean $leavename Weither or not to leave the slug name.

	* @return string The permalink for the request.
	*/
	static public function uri( $request, $view, $unique, $leavename=false ) {

		$uri_matches = array();

		foreach (self::$_replace as $placeholder => $replacements){
			$placeholders[$replacements[0]] = $placeholder;
		}

		foreach (self::$_rules as $rule){
			if ( isset( $rule['request'] ) && 
				!array_diff(array_keys($request), array_keys($rule['request'] )) 
				&& (count(array_keys($request)) == count(array_keys($rule['request'])) )){

				$found = true;
				$uri = substr( $rule['path'], 2 ); //xxx

				foreach($rule['request'] as $rule_key => $rule_value){

					if ($rule_value != $request[$rule_key]){

						if( isset( $placeholders[$rule_key] ) && strstr($rule_value, $placeholders[$rule_key])){
							if ($rule_key == 'name' && $leavename) continue;

	                        $uri = str_replace($placeholders[$rule_key], $request[$rule_key], $uri);

		                } else {

	                        $found = false;
	                    }
	                }
	            }

		        if ($found){
        
	                if(!array_key_exists($rule['view'], $uri_matches)){ 
                    
	                    $uri_matches[$rule['view']] = str_replace('?$', '', $uri);
                    
	                } else {
                    
		                throw new Exception('Duplicate "'.$rule['view'].'" URI exists.');
	
	                }
	            }
	        }
		}

	    if (!$uri_matches) throw new Exception('No URI found.');

	    if (!$unique) return $uri_matches;

	    if ($view == 'default'){

			if ( !array_key_exists( 'default', $uri_matches ) ) {
				$keys = array_keys($uri_matches);
				if (count($keys) == 1){
					return $uri_matches[$keys[0]];
				} else {
					throw new Exception('No default view for URI.');
				}
			}

			return $uri_matches['default'];

	    } else {

	        if ( !array_key_exists( $view, $uri_matches ) ) {
	            throw new Exception('That view for the URI does not exist.');
		    }

	        return $uri_matches[$view];
		}
	}

	/**
	 * Sets the rules to be used.
	 *
	*/
	static public function set_rules( $uri ){
		if ( !isset( self::$_rules ) ) {
			self::$_rules = $uri;
		}
	}

	/**
	 * Generates the rules and stored at self::$rules
	 *
	*/
	static public function generate_rules(){

		self::$rules = array();

		foreach ( self::$_rules as $rule ) {
			foreach ( self::$_replace as $key => $value ) {
				$request_replace[$key] = $value[0];
				$path_replace[$key] = $value[1];
			}

			$path = strtr($rule['path'], $path_replace);
			$path = substr( $path, 2 ); //xxx

			$request = 'index.php?';

			$match_count = 1;

			if ( isset( $rule['request'] ) ) {
				foreach ($rule['request'] as $key => $value){
					$request .= $key.'=';

					if ( strstr( $value, $key ) ) {
						$request .= '$matches['.$match_count.']&';
						$match_count = $match_count + 1;
					} else {
						$request .= $value.'&';
					}
				}
			}

			if ( isset( $rule['robots'] ) && $rule['robots'] == false ) {
				$request .= 'robots=1&';
			}

			$request .= 'view='.$rule['view'];
	
			self::$rules[$path] = $request;
		}
	}


}

?>