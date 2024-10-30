jQuery(document).ready(function($){

     var new_row_html = $('.purchase-options tr:last').html()
     var new_shipping_row_html = $('.shipping-options tr:last').html()

     function new_row(){
         var c = $('.purchase-options tr').size()

		 var html = $('<tr class="purchase-option"><td><p><strong>Option Name:</strong> <input class="purchase-option-name" name="purchase_option['+c+'][name]" type="text" size="20" value=""/></p><p><strong>Printing Cost</strong> <em>(USD)</em>:<input class="purchase-option-price" name="purchase_option['+c+'][printing-cost]" type="text" value="" size="3"/></p><p><strong>Framing Cost</strong> <em>(USD):</em><input class="purchase-option-price" name="purchase_option['+c+'][framing-cost]" type="text" value="" size="3"/></p><p><strong>Production Cost</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['+c+'][production-cost]" type="text" value="" size="3"/></p><p><strong>Artist Fee</strong> <em>(BTC):</em><input class="purchase-option-price" name="purchase_option['+c+'][artist-fee]" type="text" value="" size="3"/></p><p><strong>SKU</strong> <em>(Stock Keeping Unit Number)</em>: <input type="text" class="purchase-option-sku" name="purchase_option['+c+'][sku]" value="" size="3"/></p></td><td><p><strong>Weight</strong> <em>(ounces)</em>:<input class="purchase-option-weight" name="purchase_option['+c+'][weight]" type="text" value="" size="2"/></p><p><strong>Width</strong> <em>(inches)</em>:<br/><input class="purchase-option-width" name="purchase_option['+c+'][width]" type="text" value="" size="2"/></p><p><strong>Height</strong> <em>(inches)</em>:<br/><input class="purchase-option-height" name="purchase_option['+c+'][height]" type="text" value="" size="2"/></p><p><strong>Length</strong> <em>(inches)</em>:<br/><input class="purchase-option-length" name="purchase_option['+c+'][length]" type="text" value="" size="2"/></p></td><td><p class="quantity-field"><strong>Quantity:</strong> <br/><input class="purchase-option-quantity" name="purchase_option['+c+'][quantity]" type="text" value="" size="2"/></p><p><input class="ondemand-field" type="checkbox" name="purchase_option['+c+'][ondemand]"/> <strong>On-Demand</strong></p><p><input type="checkbox" name="purchase_option['+c+'][coa]"/> Certificate of Authenticity</p><p><input type="checkbox" name="purchase_option['+c+'][signed]"/> Signed</p></td><td></td></tr>');

         $('.purchase-options tbody').append(html)
         add_actions(html)
     }

     function shipping_new_row(){
         var c = $('.shipping-options tr').size()
         var country_options = $('.shipping-options select:first').html()
         var html = $('<tr><td><select name="shipping_option['+c+'][country]" class="shipping-option-country">'+country_options+'</select></td><td><input class="shipping-option-single" name="shipping_option['+c+'][single]" value="" size="3" type="text"></td><td><input class="shipping-option-multi" name="shipping_option['+c+'][multi]" value="" size="2" type="text"></td><td></td></tr>')
         $('.shipping-options tbody').append(html)
         shipping_add_actions(html)
     }

     function check_empty(){
         var need_new_row = false
         $('.purchase-options tr:last input:first').each(function(){
             if($(this).val() != '') need_new_row = true
         })
         if(need_new_row) new_row()				  
     }

     function shipping_check_empty(){
         var need_shipping_new_row = false
         $('.shipping-options tr:last input').each(function(){
             if($(this).val() != '') need_shipping_new_row = true
         })
         if(need_shipping_new_row) shipping_new_row()
     }

     $('.purchase-options tr').each(function(){
         add_actions(this)
     })

     $('.shipping-options tr').each(function(){
         shipping_add_actions(this)
     })

     $('.purchase-options input').live('focus', function(){
         check_empty()
     })

     $('.shipping-options input').live('focus', function(){
         shipping_check_empty()
     })

     function add_actions(elm){

		 if ( $('.ondemand-field', elm).attr('checked') ) {
			 var qf = $('.quantity-field', elm);
			 qf.val(0);
			 qf.hide();			
		 }

		 $('.ondemand-field', elm).click(function(){
			var t = $(this);
			 if ( t.attr('checked') ) {
				 var qf = $('.quantity-field', elm);
				 $('input', qf).val(0);
				 qf.hide();
			 } else {
				 var qf = $('.quantity-field', elm);
				 qf.show();
			 }
		 })


         var remove = $('<p class="purchase-option-remove">Remove</p>')
         $('td:last', elm).html(remove)
         remove.click(function(){
		     $(this).parent().parent().fadeOut(function(){$(this).remove()})
         })
     }

     function shipping_add_actions(elm){
         var remove = $('<p class="shipping-option-remove">Remove</p>')
         $('td:last', elm).html(remove)
         remove.click(function(){
		     $(this).parent().parent().fadeOut(function(){$(this).remove()})
         })
     }

})