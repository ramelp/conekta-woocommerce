jQuery(function ($) {
	$(document).ready(function(){
		var public_key = $('input[name=public_key]').val();
		Conekta.setPublishableKey(public_key);

		$('.checkout').off();

		$('.checkout').submit(function(event){
			var $form = $(this);			

			if ( $form.is('.processing') )
			return false;

			$form.addClass('processing');

			var form_data = $form.data();

			if ( form_data["blockUI.isBlocked"] != 1 )
				$form.block({message: null, overlayCSS: {background: '#fff url(' + wc_checkout_params.ajax_loader_url + ') no-repeat center', backgroundSize: '16px 16px', opacity: 0.6}});			

			var card_data = {
				    name: $('#conekta-titular-name').val(),
				    cvc: $('#conekta-card-cvc').val(),
				    exp_month: parseInt($('#conekta-card-expiry').val().split(" ")[0]),
				    exp_year: $('#conekta-card-expiry').val().split(" ")[2],
				    number: $('#conekta-card-number').val().replace(/ /g,"")
				  }

			
			Conekta.token.create({ 'card' : card_data}, conektaSuccessResponseHandler, conektaErrorResponseHandler);
			
			// Prevenir que la información del formulario sea mandado a tu servidor
			return false;
		});
	});

	var conektaSuccessResponseHandler = function(response)  {
	        var token_id = response.id;
	        var $form = $('.checkout');

	        $form.append($('<input type="hidden" name="conektaTokenId" />').val(token_id));

	        $.ajax({
				type: 		'POST',
				url: 		wc_checkout_params.checkout_url,
				data: 		$form.serialize(),
				success: 	function( code ) {
					var result = '';

					try {
						// Get the valid JSON only from the returned string
						if ( code.indexOf("<!--WC_START-->") >= 0 )
							code = code.split("<!--WC_START-->")[1]; // Strip off before after WC_START

						if ( code.indexOf("<!--WC_END-->") >= 0 )
							code = code.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

						// Parse
						result = $.parseJSON( code );

						if ( result.result == 'success' ) {
							window.location = decodeURI(result.redirect);
						} else if ( result.result == 'failure' ) {
							throw "Result failure";
						} else {
							throw "Invalid response";
						}
					}
					catch( err ) {

						if ( result.reload == 'true' ) {
							window.location.reload();
							return;
						}

						// Remove old errors
						$('.woocommerce-error, .woocommerce-message').remove();

						// Add new errors
						if ( result.messages )
							$form.prepend( result.messages );
						else
							$form.prepend( code );

					  	// Cancel processing
						$form.removeClass('processing').unblock();

						// Lose focus for all fields
						$form.find( '.input-text, select' ).blur();

						// Scroll to top
						$('html, body').animate({
						    scrollTop: ($('form.checkout').offset().top - 100)
						}, 1000);

						// Trigger update in case we need a fresh nonce
						if ( result.refresh == 'true' )
							$('body').trigger('update_checkout');

						$('body').trigger('checkout_error');
					}
				},
				dataType: 	"html"
			});

	};

	var conektaErrorResponseHandler = function(response) {
			var $form = $('.checkout');

	    	$form.append($('<input type="hidden" name="conektaTokenError" />').val(response.message));

	    	$.ajax({
				type: 		'POST',
				url: 		wc_checkout_params.checkout_url,
				data: 		$form.serialize(),
				success: 	function( code ) {
					var result = '';

					try {
						// Get the valid JSON only from the returned string
						if ( code.indexOf("<!--WC_START-->") >= 0 )
							code = code.split("<!--WC_START-->")[1]; // Strip off before after WC_START

						if ( code.indexOf("<!--WC_END-->") >= 0 )
							code = code.split("<!--WC_END-->")[0]; // Strip off anything after WC_END

						// Parse
						result = $.parseJSON( code );

						if ( result.result == 'success' ) {
							window.location = decodeURI(result.redirect);
						} else if ( result.result == 'failure' ) {
							throw "Result failure";
						} else {
							throw "Invalid response";
						}
					}
					catch( err ) {

						if ( result.reload == 'true' ) {
							window.location.reload();
							return;
						}

						// Remove old errors
						$('.woocommerce-error, .woocommerce-message').remove();

						// Add new errors
						if ( result.messages )
							$form.prepend( result.messages );
						else
							$form.prepend( code );

					  	// Cancel processing
						$form.removeClass('processing').unblock();

						// Lose focus for all fields
						$form.find( '.input-text, select' ).blur();

						// Scroll to top
						$('html, body').animate({
						    scrollTop: ($('form.checkout').offset().top - 100)
						}, 1000);

						// Trigger update in case we need a fresh nonce
						if ( result.refresh == 'true' )
							$('body').trigger('update_checkout');

						$('body').trigger('checkout_error');
					}
				},
				dataType: 	"html"
			});
	    	
	};
});