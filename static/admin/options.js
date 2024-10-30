jQuery(document).ready(function($){

	var handle_image_upload = function(elm) {
		var parent = $(elm.target).parents('tr');
		var document = window.document;
		window.orig_send_to_editor = window.send_to_editor;
		window.send_to_editor = function ( html ) {
			$( '.image-source-url', parent ).attr('value', $( 'img', html ).attr( 'src' ) );
			$( '.image-source-id', parent ).attr('value', $( 'img', html ).attr( 'data-id' ) );
			window.tb_remove();
			window.send_to_editor = window.orig_send_to_editor;
		};
		window.tb_show( 'Upload Image', 'media-upload.php?tab=library&post_mime_type=image&TB_iframe=1&width=700&height=500' );
		return false;
	};

	var handle_video_upload = function(elm) {
		var parent = $(elm.target).parents('tr');
		var document = window.document;
		window.orig_send_to_editor = window.send_to_editor;
		window.send_to_editor = function ( html ) {
			$( '.video-source-url', parent ).attr('value', $( 'source', html ).attr( 'src' ) );
			$( '.video-source-type', parent ).attr('value', $( 'source', html ).attr( 'type' ) );
			$( '.video-source-id', parent ).attr('value', $( 'source', html ).attr( 'data-id' ) );
			window.tb_remove();
			window.send_to_editor = window.orig_send_to_editor;
			check_empty_video()
		};
		window.tb_show( 'Upload Video', 'media-upload.php?tab=library&post_mime_type=video&TB_iframe=1&width=700&height=500' );
		return false;
	};
	
	var new_row_html = $('.download-options tr:last').html()
	var new_atrow_html = $('.download-options tr:last').html()

     function new_vrow(){
         var c = $('.video-sources tr').size()
		 var html = $('<tr class="video-source"><td><input class="video-source-id" name="videos['+c+'][id]" type="text" value="" size="3"/></td><td><input class="video-source-type" name="videos['+c+'][type]" type="text" value="" size="3"/></td><td><input class="video-source-url" name="videos['+c+'][url]" type="text" value="" size="20"/><a class="video-source-upload-button add-new-h2">Select Video</a></td><td><input class="video-source-max" name="videos['+c+'][max]" type="text" value="" size="3"/></td><td><input type="radio" value="'+c+'" name="videos_feed"/></td><td></td></tr>');
         $('.video-sources tbody').append(html)
         add_actions_video(html)
     }

     function new_row(){
         var c = $('.download-options tr').size()
         var html = $('<tr><td><input class="download-option-name" name="downloads['+c+'][name]" size="8" value="" type="text"></td><td><input class="download-option-format" name="downloads['+c+'][format]" value="" size="3" type="text"></td><td><input class="download-option-size" name="downloads['+c+'][size]" value="" size="3" type="text"></td><td><input class="download-option-url" name="downloads['+c+'][url]" value="" size="20" type="text"></td><td></td></tr>')
         $('.download-options tbody').append(html)
         add_actions(html)
     }

     function new_atrow(){
         var c = $('.attribution-options tr').size()
         var html = $('<tr><td><input class="attribution-option-html" name="attributions['+c+'][html]" size="8" value="" type="text"></td><td></td></tr>')
         $('.attribution-options tbody').append(html)
         add_actions_at(html)
     }

     function check_empty(){
         var need_new_row = false
         $('.download-options tr:last input:first').each(function(){
             if($(this).val() != '') need_new_row = true
         })
         if(need_new_row) new_row()				  

     }

     function check_empty_at(){
         var need_new_atrow = false
         $('.attribution-options tr:last input:first').each(function(){
             if($(this).val() != '') need_new_atrow = true
         })
		 if(need_new_atrow) new_atrow();
     }

     function check_empty_video(){
         var need_new_atrow = false
         $('.video-sources tr:last input:first').each(function(){
             if($(this).val() != '') need_new_atrow = true
         })
		 if(need_new_atrow) new_vrow();
     }

     $('.download-options tr').each(function(){
         add_actions(this)
     })

     $('.video-sources tr').each(function(){
         add_actions_video(this)
     })

     $('.attribution-options tr').each(function(){
         add_actions_at(this)
     })

     $('.attribution-options input').live('focus', function(){
         check_empty_at()
     })

     $('.video-sources input').live('focus', function(){
         check_empty_video()
     })

     function add_actions(elm){
         var remove = $('<p class="download-option-remove">Remove</p>')
         $('td:last', elm).html(remove)
         remove.click(function(){
		     $(this).parent().parent().fadeOut(function(){$(this).remove()})
         })
     }

     function add_actions_at(elm){
         var remove = $('<p class="attribution-option-remove">Remove</p>')
         $('td:last', elm).html(remove)
         remove.click(function(){
		     $(this).parent().parent().fadeOut(function(){$(this).remove()})
         })
     }

     function add_actions_video(elm){
		 $('.video-source-upload-button', elm ).on( 'click', handle_video_upload );
         var remove = $('<p class="attribution-option-remove">Remove</p>')
         $('td:last', elm).html(remove)
         remove.click(function(){
		     $(this).parent().parent().fadeOut(function(){$(this).remove()})
         })
     }

	$('.image-upload-button').on( 'click', handle_image_upload );

})