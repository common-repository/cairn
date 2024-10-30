/*
Copyright (C) 2013 Braydon Fuller <http://braydon.com/>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

(function($){
	var _requests = {};
	var _templates = {};
	var _images = {};
	var _callback = false;
	var __callback = false;
	var _requesting = {};
	var _placeholders = {};
	var _duration = 800;
	var _staticduration = 400;
	var _queryvars = {};
	var _minwidth = 320;
	var _minheight = 400;
	var _maxwidth = 4000;
	var _sprite_ids = [];
	var _timeout = 60; //seconds
	var _data = false;
	var _tags = {
		'%year%' : ['year', '([0-9]{4})'],
		'%monthnum%' : ['monthnum', '([0-9]{1,2})'],
		'%day%' : ['day', '([0-9]{1,2})'],
		'%paged%' : ['paged', '([0-9]+)'],
		'%hour%' : ['hour', '([0-9]{1,2})'],
		'%minute%' : ['minute', '([0-9]{1,2})'],
		'%second%' : ['second', '([0-9]{1,2})'],
		'%postname%' : ['name', '([^/]+)'],
		'%posts_set%' : ['posts_set', '([^/]+)'],
		'%posts_set_status%' : ['posts_set_status', '([^/]+)'],
		'%category%' : ['category_name', '(.+?)'],
		'%expertise%' : ['expertise', '(.+?)'],
		'%location%' : ['location', '(.+?)'],
		'%post_tag%' : ['tag', '(.+?)'],
		'%author%' : ['author_name', '([^/]+)'],
		'%pagename%' : ['pagename', '([^/]+)'],
		'%search%' : ['s', '(.+)'],
		'%feed%' : ['feed', '(feed|rdf|rss|rss2|atom)']
	}
	var _response = null;
	var _rules = [];

   /** 
    * Methods for the Cairn jQuery plugin for navigating to URI from within JavaScript for faster browsing.
	* 
    * @exports jQuery/cairn
    * @version 1.0
    */
	var methods = {
		homeurl : function(){
			return _data['homeurl'];
		},
		mediaurl : function(){
			return _data['mediaurl'];
		},
		get_excerpt : function( text, length ){
			return text.substring(0, length)+'...';
		},
		is : function( tag ) {
			if ( typeof(_queryvars[tag]) != 'undefined' ) {
				return true
			} else {
				return false 
			}
		},
		qv : function( tag ) {
			if ( typeof(_queryvars[tag]) != 'undefined' ) {
				return _queryvars[tag]
			} else {
				return false 
			}
		},
		/**
		 * Initialize the environment with our uri rules 
		 * @example 
		 * var data = {
		 *     "uri": [
		 *         { 
		 *             "path" : "^/$",
		 *             "view" : "main",
		 *             "title": "Welcome",
		 *             "callback" : "welcome_callback",
		 *             "class" : "welcome",
		 *             "template": "/templates/welcome.ejs"
		 *         },
		 *         { 
		 *             "path" : "^/news/%year%/%monthnum%/%postname%/?$",
		 *             "callback" : "news_callback",
		 *             "view" : "main",
		 *             "title": "News",
		 *             "request" : {
		 *                 "post_type" : "post", 
		 *                 "year" : "%year%",
		 *                 "monthnum" : "%monthnum%",
		 *                 "name" : "%postname%"
		 *             },
		 *             "class" : "single",
		 *             "template": "/templates/single.ejs"
		 *          }
		 *     ]
		 * }
		 * $().cairn('init', data, 'stage_loaded_callback', 'stage_leaving_callback');
		 * @param {array} d - An array of uri rules.
		 */
		init : function(d, endcallback, startcallback){
			_callback = endcallback
			__callback = startcallback
			_data = d

			var l = _data['uri'].length;
			for ( var i=0;i<l;i++) {
				// push an index of ids
				if ( typeof( _data['uri'][i]['template']) != 'undefined' ) {
					_data['uri'][i]['template'] += '?=v'+_data['version'];
					var sprite_id = _data['uri'][i]['template']
					_sprite_ids.push(sprite_id);
				}

			}

			for ( var placeholder in _tags ) {
				_placeholders[_tags[placeholder][0]] = placeholder
			}

		    $('a').live('click', function(event){
				var local = false;
				if ( $(this).attr('rel') != 'external' ) {
					if ( $(this).attr('rel') == 'cairn' ) {
						local = true;
					}
					if ( this.href.search( _data['homeurl'] ) >= 0 ) {
						local = true; 
					}
				}
				if ( local ) {
					methods['click'](this, event)					
				}
			})

			var body = $('body')
			body.append($('<div id="stage"></div>'));

			// initialize rules
			var l = _data['uri'].length;

			for (var i=0;i<l;i++) {
				var path = _data['uri'][i]['path'];
				var pathvars = [];
				for ( var t in _tags ) {
					var p = new RegExp( t );
					var m = p.test( path );
					if ( m ) {
						path = path.replace(t, _tags[t][1]);
						pathvars.push(_tags[t][0]);
					}
				}
				var rule = _data['uri'][i];
				rule['pathvars'] = pathvars;
				rule['path'] = path;
				_rules.push(rule);
			}

			// history change
			$(window).bind('popstate', function (event) {
		        var uri = window.location.href.replace( _data['homeurl'], '')
				methods['goto'](uri, false)
			});

			// initialize response
			_response = new Response();
		},
		uri_rule : function(uri){
			var l = _rules.length;

			// get query variable form query string
			var qv = function(key, uri){
				key = key.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
				var regex = new RegExp("[\\?&]"+key+"=([^&#]*)");
				var qs = regex.exec(uri);
				if ( qs == null ) {
						return false;
				} else {
					return qs[1];
				}
			}

			// find a matching url rule
			for ( var i=0;i<l;i++ ) {
				var p = new RegExp( _rules[i]['path'] );
				var hash_split = uri.split("#")
				var split_uri = hash_split[0].split("?")
				var m = p.test( split_uri[0] )
				if ( m ) {
					var _r = _rules[i];
					var match = split_uri[0].match(p)
					var pathvars = _rules[i]['pathvars']
					_queryvars = {}
					for ( var qv_tag in _placeholders ) {
						var qv_value = qv( qv_tag, uri )
						if ( qv_value ) {
							_queryvars[qv_tag] = qv_value;
						}
					}
					for ( var r_tag in _r['request'] ) {
						if ( typeof( _queryvars[r_tag] ) == 'undefined' ) {
							_queryvars[r_tag] = _r['request'][r_tag];
						}
					}
					for ( var r=0; r<pathvars.length; r++ ) {
						_queryvars[pathvars[r]] = match[ r+1 ]
					}
					return _r;
				}
			}
			return false;
		},
		/**
		 * Preload images in advance.
		 * @example 
		 * var images = ['a.png', 'b.png', 'c.png'];
		 * $().cairn('preload', images);
		 * @param {array} preload - An array of image sources.
		 */
		preload : function(preload) {
			for (i = 0; i < preload.length; i++) {
				if ( _images[preload[i]] == undefined ) {
					_images[preload[i]] = $('<img/>');
					_images[preload[i]].attr('src', preload[i])
				}
			}
		},
		/**
		 * Request a link in advance, so that when the link is clicked it loads instantly.
		 * @example 
		 * $().cairn('preclick', $('#previous'));
		 * @param {object} elm - An anchor tag HTML element.
		 */
		preclick : function(elm){
			var href = $(elm).attr('href')
			if ( href != undefined ) {
				if ( href.search( _data['homeurl'] ) >= 0 ) {
					var uri = href.replace( _data['homeurl'], '')
				} else {
					var uri = href
					href = _data['homeurl'] + href
				}
				var a = document.createElement('a')
				a.href = href
				$.ajax({
					url: a.href,
					data: {
						type: 'json'
					},
					success: function(data){
						var data_json = JSON.stringify(data);
						$(elm).attr('data-href', data_json);

						// gallery and window sizes
						var w = $(window).width();

						var gallery = $('#fineart-gallery');
						if ( gallery.size() > 0 ) {

							var gallery_height = $(window).height() - (gallery.position().top)*2;

							var sp = $('#fineart-gallery-info').position();

							if ( sp.left > 0 ) {
								var s = w-sp.left;
							} else {
								var s = 15; // padding
							}

							var gallery_width = w-s-gallery.position().left;

							// preload images at the correct size
							var images = [];

							var img_src = data['items'][0]['image'][0];
							var tw = data['items'][0]['image'][1];
							var th = data['items'][0]['image'][2];

							var image_height = th / tw * gallery_width;
							if ( image_height > gallery_height ) {
								var image_width = Math.round( tw / th * gallery_height );
								var image_height = Math.round( th / tw * image_width );
								var image_src_width = Math.ceil( image_width / 100 ) * 100;
								var image_src_height = Math.round( th / tw * image_src_width );
							} else {
								var image_width = Math.round( tw / th * image_height );
								var image_height = Math.round( image_height );
								var image_src_height = Math.ceil( image_height / 100 ) * 100;
								var image_src_width = Math.round( tw / th * image_src_height );
							}
							images.push( img_src+'?w='+image_src_width+'&h='+image_src_height );

							methods['preload'](images);
						} 
					},
					dataType: 'json'
				});
			}
		},
		/**
		 * Replace the default link behavior for faster navigation.
		 * @example 
		 * $('a').live('click', function(event){
		 *     $().cairn('click', this, event);
		 * })
		 * @param {object} elm - The anchor tag element.
		 * @param {event} event - A click event.
		 */
		click : function(elm, event){
	        event.preventDefault();

			var href = $(elm).attr('href')
			if ( href.search( _data['homeurl'] ) >= 0 ) {
				var uri = href.replace( _data['homeurl'], '')
			} else {
				var uri = href
				href = _data['homeurl'] + href
			}
	        var rule = methods['uri_rule']( uri )
			var a = document.createElement('a')
			a.href = href

			var data_json = $(elm).attr('data-href');

			if ( data_json ) {
				data = $.parseJSON( data_json );
				wp_response = data;
				methods['request']( uri );
				if ( typeof( history.pushState ) != 'undefined' ) {
					history.pushState( rule, rule['title'], a.pathname );
				}
			} else {

				$.ajax({
					url: a.href,
					type: 'GET',
					data: {
						type: 'json'
					},
					success: function(data){
						wp_response = data
						methods['request']( uri );
						if ( typeof( history.pushState ) != 'undefined' ) {
							history.pushState( rule, rule['title'], a.pathname );
						}
					},
					dataType: 'json'
				});

			}
		},
		/**
		 * Posts data and goes to the desired URI.
		 * @example 
		 * $().cairn('post', '/gallery/invoice/bitcoin/', false, data['request'] )
		 * @param {string} uri - The URI to navigate to.
		 * @param {boolean} pushstate - Weither or not to push the history state in the browser.
		 * @param {array} request - The associative array to post to the server.
		 */
		post : function(uri, pushstate, request){
			if ( typeof( pushstate ) == 'undefined' ) pushstate = true;
			if ( typeof( request ) == 'undefined' ) request = {};
	        var rule = methods['uri_rule']( uri )
			var a = document.createElement('a')
			a.href = _data['homeurl']+uri

			var data = {
				type: 'json',
				request: request
			}
			$.ajax({
				url: a.href,
				type: 'POST',
				data: data,
				success: function(data){
					wp_response = data
					methods['request']( uri );
					if ( pushstate && typeof( history.pushState ) != 'undefined' ) {
						history.pushState( rule, rule['title'], a.pathname );
					}
				},
				error: function(){
				},
				dataType: 'json'
			});

		},
		/**
		 * Prepare the environment and request the URI be rendered.
		 * @example 
		 * $().cairn('goto', '/gallery/');
		 * @param {string} uri - The URI to navigate to.
		 * @param {boolean} pushstate - If true it will push thet history state in the browser.
		 * @param {array} request - Any additional variables sent on the request query variable.
		 */
		goto : function(uri, pushstate, request){
			if ( typeof( pushstate ) == 'undefined' ) pushstate = true;
			if ( typeof( request ) == 'undefined' ) request = {};
	        var rule = methods['uri_rule']( uri )
			var a = document.createElement('a')
			a.href = _data['homeurl']+uri

			var data = {
				type: 'json',
				request: request
			}
			$.ajax({
				url: a.href,
				type: 'GET',
				data: data,
				success: function(data){
					wp_response = data
					methods['request']( uri );
					if ( pushstate && typeof( history.pushState ) != 'undefined' ) {
						history.pushState( rule, rule['title'], a.pathname );
					}
				},
				error: function(){
				},
				dataType: 'json'
			});

		},
		/**
		 * Request the URI be rendered by matching rules defined using init.
		 * @example 
		 * $().cairn('request', '/gallery/');
		 * @param {string} uri - The URI to navigate to.
		 */
		request : function(uri){

			if (!_requesting['status']) {

				//xxx:be able to skip this because of a preload

				_requesting['status'] = true;
				_requesting['uri'] = uri;

				var rule = methods['uri_rule'](uri);

				// only the sprites we need
				var requested_sprite_ids = [];
				requested_sprite_ids.push( rule['template'] )

				// remove sprites that are garbage
				var jl = _sprite_ids.length;
				for ( var j=0;j<jl;j++ ){
					var jjl = requested_sprite_ids.length;
					var jj = 0;
					var remove_id = _sprite_ids[j];
					while ( jj<jjl ){
						if ( _sprite_ids[j] == requested_sprite_ids[jj] ) {
							remove_id = false;
							break;
						}
						jj++;
					}
					if ( remove_id ) {
						$('#'+remove_id).remove();
					}

				}

				if ( rule ) {

					var loader = $('<div id="cairn-loader-icon"></div>')
					loader.css('top', $(window).height()/2 - 24)
					$('body').append(loader);
	
					var sprites = {};
					var templates = [];
					var requests = [];
					var mediaurl = _data['mediaurl'];

					var sprite_id = rule['template'];

					if ( sprite_id != undefined ) {
						templates.push(mediaurl+sprite_id);
					} else {
						console.log('Template "'+sprite_id+'" is not defined.');
					}


					var loaded = false
					var load_errors = [];

					var check_request_timeout = function(){
						if ( loaded == false ) {
							loaded = true
							console.log('Request Timeout')
							_requesting['status'] = false;
						}
					}

					var check_request = function(){
						
						if ( requests_checklist.length == 0 &&
							templates_checklist.length == 0 ) {
							loaded = true;
							clearTimeout(request_timeout)
							if ( load_errors.length == 0 ) {
								$('#cairn-loader-icon').remove();

								if ( rule['title'] ) {
									document.title = rule['title'] + ' | ' + _data['title'] + ' | ' + _data['description'];
								} else {
									document.title = _data['title'] + ' | ' + _data['description'];
								}
								_response.render( rule, rule['callback'] )
								_requesting['status'] = false;
							} else {
								console.log( 'Request had errors: '+load_errors.join(',') )
								_requesting['status'] = false;
							}
						}
					}

					var request_timeout = setTimeout( check_request_timeout, _timeout*1000 ) // ten seconds

					var requests_checklist = [];
					for ( var id in requests ) {
						requests_checklist.push(id)
					}
					for ( var id in requests ) {

						// fill in our variables
						var _send_data = requests[id];
						var send_data = {}
						for ( var sdi in _send_data ) {
							send_data[sdi] = _send_data[sdi]
							if ( _tags[ send_data[sdi] ] != undefined ) {
								var value = _queryvars[ _tags[ send_data[sdi] ][0] ];
								if ( value != undefined ) {
									send_data[sdi] = value
								}
							}
						}
						send_data['sprite'] = id
						send_data['action'] = 'query'

						// do our request
						$.ajax({
							url: ajaxurl,
							data: send_data,
							success: function(data){
								for (var ii = 0; ii < requests_checklist.length; ii++) {
									if (requests_checklist[ii] == data['sprite'] ) { 
										requests_checklist.splice(ii,1); 
									}
								}
								_requests[data['sprite']] = data['response'];
								check_request();
							},
							error: function(xhr, options, error){
								load_errors.push( error );
								for (var ii = 0; ii < requests_checklist.length; ii++) {
									if (requests_checklist[ii] == data['sprite'] ) { 
										requests_checklist.splice(ii,1); 
									}
								}
								_requests[data['sprite']] = false;
								check_request();
							},
							dataType: 'json'
						});
					}

					var templates_checklist = templates;
					var l = templates.length;
					for ( var i=0;i<l;i++ ) {
						var src = templates[i];
						$.ajax({
							url: src,
							success: function(data){
								for (var ii = 0; ii < templates_checklist.length; ii++) {
									if (templates_checklist[ii] == this.url) { 
										templates_checklist.splice(ii,1); 
									}
								}
								_templates[this.url] = data;
								check_request();
							},
							error: function(xhr, options, error){
								load_errors.push( error );
								for (var ii = 0; ii < templates_checklist.length; ii++) {
									if (templates_checklist[ii] == this.url) { 
										templates_checklist.splice(ii,1); 
									}
								}
								check_request();
							},
							dataType: 'html'
						});
					}

				} else {
					console.log( 'Request Not Found' )
					_requesting['status'] = false;
				}

			} else {
				// remove all sprites
				var l = _sprite_ids.length;
				for (var xx=0;xx<l;xx++){
					$('#'+_sprite_ids[xx]).remove();
				}
				// reset requesting state
				_requesting['status'] = false;	
				// start over
				methods['request'](uri)
			}
		}
	}

	var Response = function(){

		this.render = function( rule, callback ) { 

//			$('#stage').empty();
			$('body').empty();

			this.draw( rule )

			// initial request callback
			if ( typeof(_callback) != 'undefined' ) {
				var fn = eval(_callback)
				fn()
			}

			if ( typeof(callback) != 'undefined' ) {
				var fn = eval(callback)
				fn()
			}
		}

		this.draw = function( rule ){

			$('body').scrollTop(0)

			var stage = $( 'body' );
			var wh = $(window).height();
			var ww = $(window).width();

			if ( ww < _minwidth ) {
				ww = _minwidth;
			} 

			if ( wh < _minheight ) {
				wh = _minheight;
			}

			var sh = wh

			stage.css( 'overflow-x', 'hidden' );

			stage.height(sh);
			stage.width(ww);

			var mediaurl = _data['mediaurl'];

			var namespace = {};
			namespace = wp_response;
			namespace['homeurl'] = _data['homeurl'];
			var sid = mediaurl+rule['template'];
			var output = new EJS({'text' : _templates[sid]}).render(namespace);
			stage.attr('class', rule['class']);
			stage.append( $(output) );
		}
	}

	$.fn.cairn = function(method){
		if (methods[method]){
			return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
		} else if (typeof method === 'object' || ! method) {
			return methods.init.apply(this, arguments);
		} else {
			$.error('Method ' + method + ' does not exist on jQuery.cairn');
        } 
	}

})(jQuery);