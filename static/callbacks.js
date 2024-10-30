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

/**
 * Returns license html

 * @param {string} title - The title of the book.
 * @param {string} author - The author of the book.
 * @returns {string} HTML for copyright licensing.
 */
var license_format = function(license) {
	var images = '';
	var titles = [];
	if ( jQuery.inArray('by', license['rights'] ) > -1 ) {
		images += '<div class="attribution"></div> ';
		titles.push('Attribution');
	}
	if ( jQuery.inArray('sa', license['rights'] ) > -1 ) {
		images += '<div class="sharealike"></div> ';
		titles.push('ShareAlike');
	}
	if ( jQuery.inArray('nc', license['rights'] ) > -1 ) {
		images += '<div class="noncommercial"></div> ';
		titles.push('Noncommercial');
	}
	if ( jQuery.inArray('nd', license['rights'] ) > -1 ) {
		images += '<div class="noderiv"></div> ';
		titles.push('NonDeriv');
	}

	var html = '<div id="licensing-icons"><a href="'+license['url']+'">'+images+' <span class="license-text">'+titles.join('-')+'</span></a></div>';
	return html;
}

/**
 * Translates bytes into KB, MB, GB or TB

 * @param {string} title - The title of the book.
 * @param {string} author - The author of the book.
 * @returns {string} Human readable bytes
 */
var human_bytes = function(bytes, precision) {  

	var bytes = parseInt( bytes );
    var kilobyte = 1024;
    var megabyte = kilobyte * 1024;
    var gigabyte = megabyte * 1024;
    var terabyte = gigabyte * 1024;
   
    if ((bytes >= 0) && (bytes < kilobyte)) {
        return bytes + ' B';
 
    } else if ((bytes >= kilobyte) && (bytes < megabyte)) {
        return (bytes / kilobyte).toFixed(precision) + ' KB';
 
    } else if ((bytes >= megabyte) && (bytes < gigabyte)) {
        return (bytes / megabyte).toFixed(precision) + ' MB';
 
    } else if ((bytes >= gigabyte) && (bytes < terabyte)) {
        return (bytes / gigabyte).toFixed(precision) + ' GB';
 
    } else if (bytes >= terabyte) {
        return (bytes / terabyte).toFixed(precision) + ' TB';
 
    } else {
        return bytes + ' B';
    }
}

var bitcoin_delay_status_check_mseconds = 60000; // 60 seconds
var credit_delay_status_check_mseconds = 120000; // 2 minutes

var delay_status_check_timeout = false;
var cancel_status_check = function(){
	clearTimeout( delay_status_check_timeout ) ;
}

var bitcoin_delay_status_check = function(){
	delay_status_check_timeout = setTimeout( bitcoin_status_check, bitcoin_delay_status_check_mseconds );
}

var bitcoin_status_check = function(){

	var method = $('#fmethod').val();

	var name = $('#fname').val();
	var address1 = $('#faddress1').val();
	var address2 = $('#faddress2').val();
	var city = $('#fcity').val();
	var state = $('#fstate').val();
	var country = $('#country').val();
	var zip = $('#zip').val();

	var sip = $('#sip').val();
	var publickey = $('#publickey').val();
	var email = $('#email').val();

	$.ajax({
		type: 'POST',
		url: ajaxurl,
		data: { 
			nonce: nonce,
			method: method,
			name: name,
			address1: address1,
			address2: address2,
			city: city,
			state: state,
			country: country,
			zip: zip,
			sip: sip,
			email: email,
			publickey: publickey,
			action: 'posts_set_bitcoin_status_invoice'
		},
		success: function(data){

			// display the invoice

			if ( data && data['success'] ) {

				// if expired 

				if ( data['received'] ) {

					$().cairn( 'post', '/shop/invoice/thankyou/', false, data['request'] ) // dont show history or url

				} else if ( data['expired'] ) {

					$().cairn( 'post', '/shop/invoice/expired/', false, data['request'] ) // dont show history or url

				} else {

					bitcoin_delay_status_check();


					var minutes_total = Math.floor( data['seconds_remaining'] / 60 );
					var seconds = data['seconds_remaining'] % 60;
					var hours = Math.floor( minutes_total / 60 );
					var minutes = minutes_total % 60;

					var time_remaining = '';

					if ( hours == 1 ) {
						time_remaining += hours + ' hour';
					} else if ( hours > 1 ) {
						time_remaining += hours + ' hours';
					}

					if ( hours > 0 && minutes > 0 ) {
						time_remaining += ' and ';
					}

					if ( minutes == 1 ) {
						time_remaining += minutes+' minute';
					} else if ( minutes > 1 ) {
						time_remaining += minutes+' minutes';
					}

					if ( hours == 0 && minutes < 10 ) {

						if ( minutes > 0 ) {
							time_remaining += ' and ';
						}

						time_remaining += seconds+' seconds';
					}


					display_message( 'We are holding your items for <strong>'+time_remaining+'</strong>.', true );

				}

			} else {

				if ( data && !data['end'] ) {

					// only continue checking if nessasary

					bitcoin_delay_status_check();

				}

				display_message( data['message'], true )

			}
		},
		error: function(data){
			bitcoin_delay_status_check();
		},
		dataType: 'json'
	})

}


var credit_status_check = function(){

	$.ajax({
		type: 'POST',
		url: ajaxurl,
		data: { 
			nonce: nonce,
			action: 'posts_set_credit_status_invoice'
		},
		success: function(data){

			// display the invoice

			if ( data['success'] ) {

				if ( data['expired'] ) {

					$().cairn( 'post', '/shop/invoice/expired/', false, data['request'] ) // dont show history or url

				} else {

					credit_delay_status_check();

					var minutes_total = Math.floor( data['seconds_remaining'] / 60 );
					var seconds = data['seconds_remaining'] % 60;
					var hours = Math.floor( minutes_total / 60 );
					var minutes = minutes_total % 60;

					var time_remaining = '';

					if ( hours == 1 ) {
						time_remaining += hours + ' hour';
					} else if ( hours > 1 ) {
						time_remaining += hours + ' hours';
					}

					if ( hours > 0 && minutes > 0 ) {
						time_remaining += ' and ';
					}

					if ( minutes == 1 ) {
						time_remaining += minutes+' minute';
					} else if ( minutes > 1 ) {
						time_remaining += minutes+' minutes';
					}

					if ( hours == 0 && minutes < 10 ) {

						if ( minutes > 0 ) {
							time_remaining += ' and ';
						}

						time_remaining += seconds+' seconds';
					}

					display_message( 'We are holding your items for <strong>'+time_remaining+'</strong>.', true );

				}

			} else {

				if ( data && !data['end'] ) {

					// only continue checking if nessasary

					credit_delay_status_check();

				}

				display_message( data['message'], true )

			}
		},
		error: function(data){
			credit_delay_status_check();
		},
		dataType: 'json'
	})

}

var credit_delay_status_check = function(){
	delay_status_check_timeout = setTimeout( credit_status_check, credit_delay_status_check_mseconds ); 
}

//var resize_stage = function() {
//	var w = $(window).width();
//	var h = $(window).height();
//	$('#stage').width( w );
//	$('#stage').height( h );		

	// remove sprites entirely and replace with pages jquery.cairn.js
//	$('.sprite').width( w );
//	$('.sprite').height( h );		
//}

//var bind_resize_stage = function(){
//	$(window).bind('resize', function (event) {
//		resize_stage();
//	});
//}

//bind_resize_stage();

var display_message = function( message, error ) {
	if ( message && message != 'false' ) {
		var existing_message = $('#messages').html();
		if ( existing_message != message ) {
			$('#messages').fadeOut(1000, function(){ 
				var t = $(this);
				if ( error ) {
					t.removeClass('success');
				} else {
					t.addClass('success');
				}
				t.html(message).fadeIn() 
			});
		}
	} else {
		$('#messages').fadeOut(function(){
			$(this).empty()
		});
	}
}


var welcome_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	var x,y;

	$(window).blur(function() {
		clouds[0].pause();
	});

	$(window).focus(function() {
		clouds[0].play();
	});

	var welcome = $('#welcome-message');
	var wrapper = $('#welcome-video-wrapper');

	wrapper.css('height', $(window).height());
	wrapper.css('overflow', 'hidden');
	welcome.css('top', wrapper.height() - welcome.height() - 50);

	wrapper.width( $(window).width() );
    var clouds = $('#welcome-video');
	var video_og_width = clouds.attr('data-width');
	var video_og_height = clouds.attr('data-height');
	var window_width = $(window).width();

	if ( window_width < 637 ) {
		clouds[0].pause();
	}

	if ( video_og_height / video_og_width * window_width < wrapper.height() ) {
		var video_width = video_og_width / video_og_height * wrapper.height();
		var video_height = wrapper.height();
	} else {
		var video_width = window_width;
		var video_height = video_og_height / video_og_width * window_width;
	}

	clouds.width( video_width );
	clouds.height( video_height );

	clouds.css('position', 'relative');
	clouds.css('top', ($(window).height()-video_height)/2);
	clouds.css('left', ($(window).width()-video_width)/2);
	var welcome = $('#welcome-message');

	$('#portfolio-archive-wrapper').touchSwipeLeft( function(){ $('#portfolio-archive-previous').click() } ); 
	$('#portfolio-archive-wrapper').touchSwipeRight( function(){ $('#portfolio-archive-next').click() } ); 

	var activate_portfolio = function(){

		var previous = $('<div id="portfolio-archive-previous" class="portfolio-navigation" href="">&gt;</div>');
		var next = $('<div id="portfolio-archive-next" class="portfolio-navigation" href="">&gt;</div>');

		var wrapper = $('#portfolio-archive-wrapper');

		wrapper.append( previous );
		wrapper.append( next );

		var width = 0;
		var height = 0;
		var total = 0;
		 $('.portfolio-thumbnail').each(function(){
			total++;
			var t = $(this);
			var img = $('img', t);
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true );
			}
			if ( t.outerHeight( true ) > height ) {
				height = t.outerHeight( true );
			}
			t.height(height);
		 });

		var max = Math.floor( wrapper.width() / width );
		var max_page = Math.floor( total / max );

		var fa = $('.portfolio-archive');

		fa.height(height);
		wrapper.height(height);

		previous.css('top', wrapper.height() / 2.05 - previous.height() );
		next.css('top', wrapper.height() / 2.05  - next.height() );

		var show_page = function(p, o){

			$('.portfolio-thumbnail').each(function(){
				$(this).css('display','none');
			});

			var a = p * o;
			var b = ( p * o ) + o;
			var width = 5; 
			for ( var i = a;i<b; i++ ) {
				var th = $('#portfolio-thumbnail-'+i)
				th.css('display','block');
				width += th.outerWidth( true );
			}

		}

		var advance_page = function( i ){

			var x = page + i;

			$('#portfolio-previous').removeClass('deactive');
			$('#portfolio-next').removeClass('deactive');

			if ( x >= max_page ) {
				$('#portfolio-previous').addClass('deactive');
				// deactivate previous
				x = max_page;
			}

			if ( x <= 0 ) {
				$('#portfolio-next').addClass('deactive');
				// deactive next
				x = 0;
			}

			return x;
		}


		var page = -1;
		var page = advance_page( 1 );


		next.click( function(){
			page = advance_page( -1 );
			show_page( page, max );
		})

		previous.click( function(){
			page = advance_page( 1 );
			show_page( page, max );
		})

		show_page( page, max );

	}

	$.getJSON('/portfolio/?type=json', function(data){
		var output = new EJS({'url' : '/wp-content/plugins/cairn/static/templates/portfolioarchive.ejs'}).render(data);
		$('#portfolio-archive-wrapper').html(output);
		activate_portfolio();
	});


	$('#shop-archive-wrapper').touchSwipeLeft( function(){ $('#shop-archive-previous').click() } ); 
	$('#shop-archive-wrapper').touchSwipeRight( function(){ $('#shop-archive-next').click() } ); 


	var activate_shop = function(){

		var previous = $('<div id="shop-archive-previous" class="fineart-navigation" href="">&gt;</div>');
		var next = $('<div id="shop-archive-next" class="fineart-navigation" href="">&gt;</div>');

		var wrapper = $('#shop-archive-wrapper');

		wrapper.append( previous );
		wrapper.append( next );

		var width = 0;
		var height = 0;
		var total = 0;
		 $('.fineart-thumbnail').each(function(){
			total++;
			var t = $(this);
			var img = $('img', t);
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true )
			}
			if ( t.outerHeight( true ) > height ) {
				height = t.outerHeight( true )
			}
			 t.height(height);
		 });

		var max = Math.floor( wrapper.width() / width );
		var max_page = Math.floor( total / max );

		var fa = $('.fineart-archive');
		fa.height(height);
		wrapper.height(height);

		previous.css('top', wrapper.height() / 2 - previous.height() / 2 );
		next.css('top', wrapper.height() / 2  - next.height() / 2 );

		var show_page = function(p, o){

			$('.fineart-thumbnail').each(function(){
				$(this).css('display','none');
			});

			var a = p * o;
			var b = ( p * o ) + o;
			var width = 5; 
			for ( var i = a;i<b; i++ ) {
				var th = $('#fineart-thumbnail-'+i)
				th.css('display','block');
				width += th.outerWidth( true );
			}
		}

		var advance_page = function( i ){

			var x = page + i;

			$('#archive-previous').removeClass('deactive');
			$('#archive-next').removeClass('deactive');

			if ( x >= max_page ) {
				$('#archive-previous').addClass('deactive');
				// deactivate previous
				x = max_page;
			}

			if ( x <= 0 ) {
				$('#archive-next').addClass('deactive');
				// deactive next
				x = 0;
			}

			return x;
		}


		var page = -1;
		var page = advance_page( 1 );


		next.click( function(){
			page = advance_page( -1 );
			show_page( page, max );
		})
	
		previous.click( function(){
			page = advance_page( 1 );
			show_page( page, max );
		})
	
		show_page( page, max );


	}


	$.getJSON('/shop/?type=json', function(data){
		var output = new EJS({'url' : '/wp-content/plugins/cairn/static/templates/archive.ejs'}).render(data);
		$('#shop-archive-wrapper').html(output);
		activate_shop();
	});


	$.getJSON('/about/?type=json', function(data){
		var output = new EJS({'text' : "<div class='body'><%= items[0]['body'] %></div>"}).render(data);
		$('#about-wrapper').html(output);
	});



}

/**
 * Callback for the Contact Page
 */
var contact_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	$('input, textarea').placeholder();
	
	$('#messages').hide();

	$('#send').click(function(e){

		display_message( 'Sending your message... Please wait.', false );

		var cname = $('#cname').val();
		var cemail = $('#cemail').val();
		var csip = $('#csip').val();
		var cpublickey = $('#cpublickey').val();
		var cmessage = $('#cmessage').val();

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: { 
				nonce: nonce,
				cname: cname,
				cemail: cemail,
				csip: csip,
				cpublickey: cpublickey,
				cmessage: cmessage,
				action: 'cairn_contact'
			},
			success: function(data){
			
				// display the invoice

				if ( data['success'] ) {

					display_message( '<strong>Thank You.</strong> Your message has been processed without errors.', false );

				} else {

					display_message( data['message'], false );

				}
			},
			dataType: 'json'
		})


	})
	

}

var stage_leaving_callback = function(){
}
var stage_loaded_callback = function(){
	cancel_status_check();
	$("html, body").animate({ scrollTop: 0 }, "fast");
}

/**
 * Callback for the Archive Page
 */
var archive_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	if ( $(window).width() > 637 ) {

		var wrapper = $('#shop-landing-archive-wrapper');

		var wrapper_height = $(window).height() - wrapper.position().top

		wrapper.height( wrapper_height );

		var previous = $('<div id="archive-previous" class="fineart-navigation" href="">&gt;</div>');
		var next = $('<div id="archive-next" class="fineart-navigation" href="">&gt;</div>');

		wrapper.append( previous );
		wrapper.append( next );

		var width = 0;
		var height = 0;
		var total = 0;
		 $('.fineart-thumbnail').each(function(){
			total++;
			var t = $(this);
			var img = $('img', t);
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true )
			}
			if ( img.height() > height ) {
				height = img.height()
			}
			t.height(height);
		 });

		var max = Math.floor( wrapper.width() / width );
		var max_page = Math.floor( total / max );

		var fa = $('.fineart-archive');

		fa.height( height );

		fa.css('top', $(window).height() / 2 - wrapper.position().top - height / 2 );
		previous.css('top', ( wrapper.height() / 2 ) - previous.height()  );
		next.css('top', ( wrapper.height() / 2 ) - next.height() );

		var show_page = function(p, o){

			$('.fineart-thumbnail').each(function(){
				$(this).css('display','none');
			});

			var a = p * o;
			var b = ( p * o ) + o;
			var width = 5; 
			for ( var i = a;i<b; i++ ) {
				var th = $('#fineart-thumbnail-'+i)
				th.css('display','block');
				width += th.outerWidth( true );
			}

			$('.fineart-archive').css('left', ( wrapper.width() / 2 ) - ( width / 2 ) )
	
		}

		var advance_page = function( i ){

			var x = page + i;

			$('#archive-previous').removeClass('deactive');
			$('#archive-next').removeClass('deactive');

			if ( x >= max_page ) {
				$('#archive-previous').addClass('deactive');
				// deactivate previous
				x = max_page;
			}

			if ( x <= 0 ) {
				$('#archive-next').addClass('deactive');
				// deactive next
				x = 0;
			}

			return x;
		}


		var page = -1;
		var page = advance_page( 1 );


		next.click( function(){
			page = advance_page( -1 );
			show_page( page, max );
		})
	
		previous.click( function(){
			page = advance_page( 1 );
			show_page( page, max );
		})
	
		show_page( page, max );

	} else {

		var width = 0;
		 $('.fineart-thumbnail').each(function(){
			var t = $(this);
			var img = $('img', t);
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true )
			}
		 });
	
		var max = Math.floor( $(window).width() / width );
		var max_width = max * width;

		$('.fineart-archive').css('width', max_width );
		$('.fineart-thumbnail').css('display', 'block');
	
	}


}

/**
 * Callback for the Archive Page
 */
var portfolioarchive_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	if ( $(window).width() > 637 ) {

		var wrapper = $('#portfolio-landing-archive-wrapper')

		var wrapper_height = $(window).height() - wrapper.position().top

		wrapper.height( wrapper_height );

		var previous = $('<div id="archive-previous" class="portfolio-navigation" href="">&gt;</div>');
		var next = $('<div id="archive-next" class="portfolio-navigation" href="">&gt;</div>');

		wrapper.append( previous );
		wrapper.append( next );

		var width = 0;
		var height = 0;
		var total = 0;
		 $('.portfolio-thumbnail').each(function(){
			total++;
			var t = $(this);
			var img = $('img', t);
			img.css('top', t.height() / 2 - img.height() / 2 );
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true )
			}
			if ( img.height() > height ) {
				height = img.height()
			}
			t.height(height);
		 });
	
		var max = Math.floor( $(window).width() / width );
		var max_page = Math.floor( total / max );

		var fa = $('.portfolio-archive');

		fa.css('top', $(window).height() / 2 - wrapper.position().top - height / 2 );
		previous.css('top', ( wrapper.height() / 2 ) - previous.height() );
		next.css('top', ( wrapper.height() / 2 ) - next.height() );

		var show_page = function(p, o){

			$('.portfolio-thumbnail').each(function(){
				$(this).css('display','none');
			});

			var a = p * o;
			var b = ( p * o ) + o;
			var width = 5; 
			for ( var i = a;i<b; i++ ) {
				var th = $('#portfolio-thumbnail-'+i)
				th.css('display','block');
				width += th.outerWidth( true );
			}

			$('.portfolio-archive').css('left', ( wrapper.width() / 2 ) - ( width / 2 ) )
	
		}

		var advance_page = function( i ){

			var x = page + i;

			$('#portfolio-previous').removeClass('deactive');
			$('#portfolio-next').removeClass('deactive');

			if ( x >= max_page ) {
				$('#portfolio-previous').addClass('deactive');
				// deactivate previous
				x = max_page;
			}

			if ( x <= 0 ) {
				$('#portfolio-next').addClass('deactive');
				// deactive next
				x = 0;
			}

			return x;
		}


		var page = -1;
		var page = advance_page( 1 );


		next.click( function(){
			page = advance_page( -1 );
			show_page( page, max );
		})
	
		previous.click( function(){
			page = advance_page( 1 );
			show_page( page, max );
		})
	
		show_page( page, max );

	} else {

		var width = 0;
		 $('.portfolio-thumbnail').each(function(){
			var t = $(this);
			var img = $('img', t);
			if ( t.outerWidth( true ) > width ) {
				width = t.outerWidth( true )
			}
		 });
	
		var max = Math.floor( $(window).width() / width );
		var max_width = max * width;

		$('.portfolio-archive').css('width', max_width );
		$('.portfolio-thumbnail').css('display', 'block');
	
	}


}

/**
 * Callback for the News Page
 */
var news_callback = function(){
	$('#single').height($(window).height())
	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}
}

/**
 * Callback for the Thank You Page
 */
var thankyou_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	$('#print-button').click(function(){
		window.print();
	})

	$('#publickeyhidebutton').hide();		
	$('#publickeyshow').hide();		

	$('#publickeyhidebutton').click(function(){
		$('#publickeyshow').hide();
		$('#publickeyshowbutton').show();
		$('#publickeyhidebutton').hide();
	});		

	$('#publickeyshowbutton').click(function(){
		$('#publickeyshow').show();
		$('#publickeyshowbutton').hide();
		$('#publickeyhidebutton').show();
	});

	// get the method of the transaction to see if we need to poll
	var method = $('#fmethod').val();

	// get the status of the transaction to see if we need to poll
	var status = $('#status').val();

	if ( method == 'btc' && status == 'received' ) {		
		bitcoin_delay_status_check_mseconds = 300000; // 5 minutes
		bitcoin_delay_status_check();
	}
			
}

/**
 * Callback for the Bitcoin Payment Page
 */
var bitcoin_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	$('#publickeyhidebutton').hide();		
	$('#publickeyshow').hide();		

	$('#publickeyhidebutton').click(function(){
		$('#publickeyshow').hide();
		$('#publickeyshowbutton').show();
		$('#publickeyhidebutton').hide();
	});		

	$('#publickeyshowbutton').click(function(){
		$('#publickeyshow').show();
		$('#publickeyshowbutton').hide();
		$('#publickeyhidebutton').show();
	});

	$('#paywithbitcoin').click(function(){
		$(this).addClass('disabled');
	})

	// poll to see if the invoice is confirmed
	bitcoin_delay_status_check_mseconds = 15000; // 15 seconds
	bitcoin_delay_status_check();

}

/**
 * Callback for the Credit Card Payment Page
 */
var credit_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	$('#publickeyhidebutton').hide();		
	$('#publickeyshow').hide();		

	$('#publickeyhidebutton').click(function(){
		$('#publickeyshow').hide();
		$('#publickeyshowbutton').show();
		$('#publickeyhidebutton').hide();
	});		

	$('#publickeyshowbutton').click(function(){
		$('#publickeyshow').show();
		$('#publickeyshowbutton').hide();
		$('#publickeyhidebutton').show();
	});

	// poll to see if the invoice is expired
	credit_delay_status_check();

	$('#submit').removeAttr('disabled');

	$('#submit').click(function(){

		$("html, body").animate({ scrollTop: 0 }, "fast");

		var method = $('#fmethod').val();

		var cc_number = $('#cc-number').val();
		var cc_cvc = $('#cc-cvc').val();
		var cc_exp_month = $('#cc-exp-month').val();
		var cc_exp_year = $('#cc-exp-year').val();

		var name = $('#fname').val();
		var address1 = $('#faddress1').val();
		var address2 = $('#faddress2').val();
		var city = $('#fcity').val();
		var state = $('#fstate').val();
		var country = $('#country').val();
		var zip = $('#zip').val();

		var sip = $('#sip').val();
		var publickey = $('#publickey').val();
		var email = $('#email').val();

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: { 
				nonce: nonce,
				method: method,
				cc_number: cc_number,
				cc_cvc: cc_cvc,
				cc_exp_month: cc_exp_month,
				cc_exp_year: cc_exp_year,
				name: name,
				address1: address1,
				address2: address2,
				city: city,
				state: state,
				country: country,
				zip: zip,
				sip: sip,
				publickey: publickey,
				email: email,
				action: 'posts_set_credit_authorize_invoice'
			},
			success: function(data){
			
				// display the invoice

				if ( data['success'] ) {

					$().cairn( 'post', '/shop/invoice/thankyou/', false, data['request'] ) // dont show history or url

				} else {

					display_message( data['message'], true )

				}
			},
			dataType: 'json'
		})


	})
			
}

/**
 * Callback for the Cart Page
 */
var cart_callback = function(){

	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}

	$('input, textarea').placeholder();

	$('#cart-back').click(function(){
		history.go(-1);
	});

	$('#contact-help-button').click(function(){
		$(this).hide();
		$('#contact-help').show();
	})

	$('#totals-total-show-details').live('click', function(event){
		if ( event.handled !== true ) {
			var t = $(this);
			if ( t.hasClass('open') ) {
				$('#fineart-thecart-price-details').slideUp();
				t.removeClass('open');
			} else {
				$('#fineart-thecart-price-details').slideDown();
				t.addClass('open');
			}
			event.handled = true;
		}
		return false;
	})

	$('.quantity-field').change(function(){
		var t = $(this);
		var selected_id = t.attr('data-id');
		var option = t.attr('data-option');
		var quantity = t.val();
		var pitem = t.parents('.fineart-thecart-item');

		if ( quantity == 0 ) {

			//todo: this exists in two places				
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: { 
					selected_post: {
						id: selected_id,
						option: option
					},
					action: 'posts_set_remove_post'
				},
				success: function(data){
					pitem.remove();
					calculate_total();
				},
				dataType: 'json'
			})

		} else if ( quantity > 0 ) {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					selected_post: {
						id: selected_id,
						option: option,
						quantity: quantity
					},
					action: 'posts_set_quantity'
				},
				success: function(data){

					if ( data['success'] ) {

						$('#messages').fadeOut(function(){$(this).addClass('success').html('<span>Successfully updated quantity to <strong> '+quantity+'</strong></span>').fadeIn(1000); calculate_total();})

					} else {

						display_message( data['message'], true );

					}
				},
				error: function(data){
					$('#messages').fadeOut().removeClass('success').html('There was a connection error.').fadeIn(1000);

				},
				dataType: 'json'
			})
		}
		
	});

	var calculate_total = function(){
		var valid = validate_calculate();
		if ( !valid[0] ) {
			display_message( valid[1], true );
		} else {
			var zip = $('#zip').val();
			var country = $('#country').val();
			var method = $('input[type="radio"]:checked', $('#cart-payment')).val();

			$('#totals').empty().html('<div id="totals-total-none">Calculating...</div>');
			$.ajax({
				url: ajaxurl,
				type: 'GET',
				data: {
					zip: zip,
					method: method,
					country: country,
					action: 'posts_set_calculate'
				},
				success: function(data){

					if ( data['success'] ) {

						if ( method == 'credit' || method == 'paypal' ) {
							var total_string = data['total_usd'].toFixed(2) + ' USD';
						} else if ( method == 'btc' )  {
							var total_string = data['total_btc'] + ' BTC';
						}

						display_message( 'Successfully calculated total to <strong>'+total_string+'</strong>', false );

						var output = new EJS({'url' : templatesurl+'prices.ejs'}).render(data);

						$('#totals').html( $(output) );

						var valid = validate_all();

						if ( valid[0] ) {
							$('#submit').removeAttr('disabled');
						}


					} else {
						display_message( 'Unfortunately, unable to <strong>calculate shipping.</strong> Please try again.', true );
						$('#submit').attr('disabled', 'disabled');	
					}

				},
				dataType: 'json'
			})
		}
	}

	var validate_calculate = function(){
		var zip = $('#zip').val();
		var country = $('#country').val();
		var usa = country.search(/United States/i);

		var payment = $('input[type="radio"]:checked', $('#cart-payment')).val();
		if ( payment ) {
			if ( !zip && !usa ) {
				return [false, 'Please enter your <strong>zip or country</strong> to calculate total.'];
			} 
		} else {
			return [false, 'Please choose a <strong>payment method</strong> to calculate total.'];
		}

		return [true];
	}

	var validate_basic_nice = function(){
		var valid = validate_all();
		if ( !valid[0] ) {
			display_message( valid[1], true );
			$('#submit').attr('disabled', 'disabled');
		} else {
			display_message( 'Thank you, please enter contact information.', false );
			$('#submit').removeAttr('disabled');
		}
	}

	var validate_all = function(){

		var valid_calculate = validate_calculate();
		
		if ( !valid_calculate[0] ) {
			return valid_calculate;;
		}

		var name = $('#fname').val();
		var address = $('#faddress1').val();
		var city = $('#fcity').val();
		var state = $('#fstate').val();

		if ( !name || name == '' || 
			!address || address == '' || 
			!city || city == '' || 
			!state || state == ''
		   ) {
			return [false, 'Please enter <strong>shipping information</strong>.'];
		}

		var publickey = $('#publickey').val();
		var email = $('#email').val();
		var sip = $('#sip').val();

		if ( email && email.search(/@/) < 0 ) {
			return [false, 'Email address is not valid format.'];	
		}

		if ( sip && sip.search(/@/) < 0 ) {
			return [false, 'SIP phone address is not valid format.'];
		}

		if ( publickey && !email ) {
			return [false, 'Public key specified without email.'];	
		}

		if ( publickey ) {
			if ( publickey.search(/BEGIN PGP PUBLIC KEY BLOCK/) < 0 ) {
				return [false, 'Public key does not have BEGIN statement'];	
			}
			if ( publickey.search(/END PGP PUBLIC KEY BLOCK/) < 0 ) {
				return [false, 'Public key does not have END statement'];	
			}

		}

		var amount_btc = $('#amount_btc').val();
		var amount_usd = $('#amount_usd').val();	

		if ( ( !amount_btc || amount_btc ) == ''&& ( !amount_usd || amount_usd == '' ) ) 
			return [false, 'Total is not calculated.'];	

		return [true];
	}

	$('#submit').click(function(){

		$("html, body").animate({ scrollTop: 0 }, "fast");

		var method = $('input[type="radio"]:checked', $('#cart-payment')).val();		

		var name = $('#fname').val();
		var address1 = $('#faddress1').val();
		var address2 = $('#faddress2').val();
		var city = $('#fcity').val();
		var state = $('#fstate').val();
		var country = $('#country').val();
		var zip = $('#zip').val();
		var amount = $('#shop-cart').attr('data-amount');

		var sip = $('#sip').val();
		var publickey = $('#publickey').val();
		var email = $('#email').val();

		if ( method == 'btc' ) {

			display_message('Creating invoice with a <strong>new bitcoin address</strong>... please wait.', false );
			var action = 'posts_set_bitcoin_process_invoice';
			var hold_url = '/shop/invoice/bitcoin/'

		} else if ( method == 'credit' ) {

			display_message('Creating invoice for <strong>credit card transaction</strong>... please wait.', false );
			var action = 'posts_set_credit_process_invoice';
			var hold_url = '/shop/invoice/credit/';

		}

		$.ajax({
			type: 'POST',
			url: ajaxurl,
			data: { 
				nonce: nonce,
				method: method,
				name: name,
				address1: address1,
				address2: address2,
				city: city,
				state: state,
				country: country,
				zip: zip,
				sip: sip,
				email: email,
				publickey: publickey,
				amount : amount,
				action: action
			},
			success: function(data){
			
				// display the invoice

				if ( data['success'] ) {

					$().cairn( 'post', hold_url, false, data['request'] ) // dont show history or url

				} else {

					display_message( data['message'], true )

				}
			},
			dataType: 'json'
		})


	})
	$('#fname').change(function(){
		validate_basic_nice();
	})
	$('#email').change(function(){
		validate_basic_nice();
	})
	$('#sip').change(function(){
		validate_basic_nice();
	})
	$('#publickey').change(function(){
		validate_basic_nice();
	})
	$('#faddress1').change(function(){
		validate_basic_nice();
	})
	$('#faddress2').change(function(){
		validate_basic_nice();
	})
	$('#city').change(function(){
		validate_basic_nice();
	})
	$('#fstate').change(function(){
		validate_basic_nice();
	})
	$('#country').change(function(){
		calculate_total();
	})
	$('#zip').keyup(function(){
		var t = $(this);
		if ( t.val().toString().length == 5 ) {
			calculate_total();
		}
	})
	$('#zip').change(function(){
		calculate_total();
	});
	$('input[type="radio"]', $('#cart-payment')).click(function(){
		calculate_total();	
	})
	$('.fineart-thecart-removeitem').click(function(){
		var t = $(this);
		t.addClass('active');
		var buy_id = t.attr('data-id');
		var option = t.attr('data-option');
		var item = t.parents('.fineart-thecart-item');
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: { 
				selected_post: {
					id: buy_id,
					option: option
				},
				action: 'posts_set_remove_post'
			},
			success: function(data){
				item.remove();
				calculate_total();
			},
			dataType: 'json'
		})
	})
}
/**
 * Callback for the About Page
 */
var about_callback = function(){
}

/**
 * Callback for the Page Template
 */
var page_callback = function(){
	if ( $(window).width() > 637 ) {
		$('#logo img').attr('src', largelogo[0]);
	} else {
		$('#logo img').attr('src', smalllogo[0]);
	}
}

// register onLoad event with anonymous function
window.onload = function (e) {
    var evt = e || window.event,// define event (cross browser)
    imgs,                   // images collection
    i;                      // used in local loop
    // if preventDefault exists, then define onmousedown event handlers
    if (evt.preventDefault) {
        // collect all images on the page
        imgs = document.getElementsByTagName('img');
        // loop through fetched images
        for (i = 0; i < imgs.length; i++) {
            // and define onmousedown event handler
            imgs[i].onmousedown = disable_dragging;
        }
    }
};
 
// disable image dragging
function disable_dragging(e) {
    e.preventDefault();
}

/**
 * Callback for the Gallery Page
 */
var gallery_callback = function(){

	$('#gallery-logo').click(function(){

		$().cairn( 'goto', '/' );
		
	})

	if ( $(window).width() < 637 ) {

		$('#info-below').remove();

		var moreinfo = $('<div id="info-below"><div id="info-below-icon"></div></div>');

		$('#stage').append(moreinfo);

		moreinfo.click(function(){
			$('#stage').animate({ scrollTop: $(document).height() }, 1000);
		});

		$('#stage').scroll(function(){

			if ( $(this).scrollTop() > 10 ) {
				$('#info-below').hide();
			} else {
				$('#info-below').fadeIn();
			}
		})

	} else {
		$('#info-below').remove();
	}

	// fullscreen events for mozilla

	$(window).bind('resize', function (e) {

		if ( $(window).width() > 637 ) {

			if ( !e.trigger ) {

				if ( $('.gallery').size() > 0 ) {

					$('#stage').width('100%');
					$('#stage').height('100%');

					var uri = window.location.href.replace( homeurl, '' );

					$().cairn( 'goto', uri );

				}

				e.trigger = true;
			}
		}

	});

	// firefox
	if ( $('body')[0].mozRequestFullScreen ) {	
		$('.fineart-gallery-image').css('cursor', 'pointer');
	}

	$('.fineart-gallery-image').mousedown(function(e){

		if ( $(window).width() > 637 ) {

			// cancel click and drag of image swipe will work
			e.preventDefault();
	
			var elm = $('body')[0];

			// firefox
			if ( elm.mozRequestFullScreen ) {
				$('.fineart-gallery-image').css('cursor', 'default');
				elm.mozRequestFullScreen();
			}

		}

	});

	$(window).bind('keydown', function( event ) {
		if ( !event.navigation ) {
			if ( event.which == 39 ) {
				$('#previous').click()
			} else if ( event.which == 37 ) {
				$('#next').click()
			}
		}
		event.navigation = true;
	});

	var buy = $('#fineart-buybutton');
	var buy_id = buy.attr('data-id');
	buy.click(function(){
		var option = $('#fineart-buyoptions').val();
		if ( $(this).hasClass('selected') ) return;
		$(this).addClass('active');
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: { 
				selected_post: { 
					id: buy_id,
					option: option,
					quantity: 1
				},
				action: 'posts_set_add_post'
			},
			success: function(data){
				var cart_url = '/shop/invoice/';
				$().cairn('post', cart_url, true, data );
			},
			dataType: 'json'
		})
	})

    $().cairn('preclick', $('#previous'));

	$('#fineart-gallery').touchSwipeLeft( function(){ $('#previous').click() } ); 
	$('#fineart-gallery').touchSwipeRight( function(){ $('#next').click() } ); 

	var gallery = $('#fineart-gallery');

	var scale_gallery = function(elm){

		if ( $(window).width() > 637 ) { 
			$('#fineart-gallery-info').height(window.innerHeight);
		}

		$('#thumbnails').css('right', ($('#fineart-navigation').width() / 2) - ($('#thumbnails').width() / 2 ));

		var w = $(window).width();
		var h = $(window).height();

		var stage = $('body');
		var template = $('.template');

		stage.width(w).height(h);
		template.width(w).height(h);

		var gallery_height = $(window).height() - ($('#fineart-gallery').position().top)*2;

		var sp = $('#fineart-gallery-info').position();

		if ( sp.left > 0 ) {
			var s = w-sp.left;
		} else {
			var s = 15; // padding
		}

		var gallery = $('#fineart-gallery');
		var gallery_width = w-s-$('#fineart-gallery').position().left;

		gallery.width( gallery_width );
		gallery.height( gallery_height );
		$('.fineart-gallery-image', gallery).each(function(){
			var t = $(this)
			var tw = t.attr('width')
			var th = t.attr('height')
			//var image_height = gallery_height * th / tw;
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
				t.css( 'position', 'relative' )
				t.css( 'top', gallery_height/2 - image_height/2 )
			}
			t.height( image_height );
			t.width( image_width );
			t.attr('src', t.attr('data-src')+'?w='+image_src_width+'&h='+image_src_height );
		})
	}

	$('.fineart-gallery-image', gallery).hide();
	var first =	$('.fineart-gallery-image:first', gallery);
	first.show();
	scale_gallery(first);

	$(window).off('resize');
	$(window).resize(function(){
		scale_gallery();
	})

	$('.fineart-gallery-navigation-item:first').addClass('active');
	$('.fineart-gallery-image', gallery).each(function(i){
		var t = $(this);
		$('#fineart-gallery-navigation-'+i).click(function(){
			$('#fineart-gallery > .fineart-gallery-image').hide();
			t.show();
			scale_gallery(t);
			$('.fineart-gallery-navigation-item').removeClass('active')
			$(this).addClass('active')
		})
		i++;
	})
}


/**
 * Callback for the Gallery Page
 */
var portfolio_callback = function(){

	$(window).blur(function() {
		var state = document.mozFullScreen || document.webkitIsFullScreen || document.fullscreen;
		if ( !state ) {
			$('video')[0].pause();
		}
	});

	$(window).focus(function() {
		if ( $(window).width() > 637 ) {
			$('video')[0].play();
		}
	});

	$('#gallery-logo').click(function(){
		$().cairn( 'goto', '/' );
	})

	$('video')[0].addEventListener("ended", function(){ $('#previous').click() });

	$(window).bind('keydown', function( event ) {
		if ( !event.navigation ) {
			if ( event.which == 39 ) {
				$('#previous').click()
			} else if ( event.which == 37 ) {
				$('#next').click()
			}
		}
		event.navigation = true;
	});

	$('#fineart-gallery-video-poster, #fineart-gallery-video-play').live('click', function(){ 
		var t = $('video');
		t.removeClass('hidden');
		var te = t[0];
		t.attr('controls', 'controls');
		if ( te.mozRequestFullScreen ) {
			te.mozRequestFullScreen(); 
		} else if ( te.webkitEnterFullscreen ){
			te.webkitEnterFullscreen(); 
		}
		te.play();
	});

	$('video')[0].addEventListener('webkitbeginfullscreen', function(e) {
		document.webkitIsFullScreen = true;
	}, false);

	$('video')[0].addEventListener('webkitendfullscreen', function(e) { 
		document.webkitIsFullScreen = false;
		$(this).addClass('hidden');
		this.pause();
	}, false);

	$(document).bind('mozfullscreenchange webkitfullscreenchange fullscreenchange', function(e){
		var state = document.mozFullScreen || document.webkitIsFullScreen || document.fullscreen;
		if ( !state ) {
			$('video')[0].pause();
			$('video').addClass('hidden');
		} 
	})

	var scale_gallery = function(){

		var stage = $('body');
		var template = $('.template');
		var navigation = $('#fineart-navigation');
		var gallery = $('#fineart-gallery');
		var poster = $('#fineart-gallery-video-poster-wrapper');
		var video = $('#fineart-gallery-video');

		if ( $(window).width() < 637 ) { 
			poster.removeClass('hidden');
			video.addClass('hidden');
			video[0].pause();
			navigation.addClass('fineart-navigation-video-thumbnail');
			navigation.removeClass('fineart-navigation-video-theatre');
			gallery.addClass('thumbnail');
		} else {
			poster.addClass('hidden');
			video.removeClass('hidden');
			video[0].play();
			navigation.removeClass('fineart-navigation-video-thumbnail');
			navigation.addClass('fineart-navigation-video-theatre');
			gallery.removeClass('thumbnail');
			video.attr('controls', 'controls').attr('autoplay', 'true');
			$('#fineart-gallery-info').height(window.innerHeight);
		}

		var w = $(window).width();
		var h = $(window).height();
		stage.width(w).height(h);
		template.width(w).height(h);

		var gallery_height = $(window).height() - ($('#fineart-gallery').position().top)*2;

		var sp = $('#fineart-gallery-info').position();

		if ( sp.left > 0 ) {
			var s = w-sp.left;
		} else {
			var s = 0; // padding
		}
	
		var gallery_width = w-s-$('#fineart-gallery').position().left;

		gallery.width( gallery_width );

		$('#thumbnails').css('right', ($('#fineart-navigation').width() / 2) - ($('#thumbnails').width() / 2 ));
		// video scaling
		var video_element = $('video', gallery);
		var video_source = $('source', $('.fineart-gallery-video', gallery));
		var video_original_width = video_source.attr('width')
		var video_original_height = video_source.attr('height')
		var video_height = video_original_height / video_original_width * gallery_width;
		var video_width = Math.round( video_original_width / video_original_height * video_height );
		var video_height = Math.round( video_height );
		video_element.attr('height', video_height );
		video_element.attr('width', video_width );
		video_element.css( 'position', 'relative' )

		// poster scaling			
		var image_element = $('img', gallery);
		var image_original_width = image_element.attr('width')
		var image_original_height = image_element.attr('height')
		var image_height = image_original_height / image_original_width * gallery_width;
		var image_width = Math.round( image_original_width / image_original_height * image_height );
		var image_height = Math.round( image_height );
		image_element.attr('height', image_height );
		image_element.attr('width', image_width );

		if ( $(window).width() > 637 ) {
			gallery.height( gallery_height );
			video_element.css( 'top', gallery_height/2 - video_height/2 )
		} else {
			gallery.height( video_height );
			var playbutton = $('#fineart-gallery-video-play');
			playbutton.css('top', video_height/2 - playbutton.height()/2);
			playbutton.css('left', video_width/2 - playbutton.width()/2);
		}

	}

	scale_gallery();

	// image events
	$('#fineart-gallery-video-poster').touchSwipeLeft( function(){ $('#previous').click() } ); 
	$('#fineart-gallery-video-poster').touchSwipeRight( function(){ $('#next').click() } ); 

	$(window).off('resize');
	$(window).resize(function(){
		var state = document.mozFullScreen || document.webkitIsFullScreen || document.fullscreen;
		if ( !state ) {
			scale_gallery();
		}
	})
	
}

