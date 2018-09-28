jQuery( function( $ ){

	// Display payment form.
	$( '#sell_media_payment_gateway' ).show();
	$( '#sell_media_payment_gateway' ).find( 'input').removeAttr( 'checked' );
	$( '#sell_media_payment_gateway' ).find( 'input#manual_purchase').attr( 'checked', 'checked' );
	
	// hide paypal select
	$( 'input#paypal' ).hide();
	$( "label[for='paypal']" ).hide();

	// hide purchase note
	$( "#manual_purchase_note" ).hide();
	$( "label[for='manual_purchase_note']" ).hide();
	
	// Submit to payment gateway
	$(document).on('click', '.sell-media-cart-checkout', function(){
		
	 var amount = $('.sell-media-cart-grand-total').text();
	 var email = $('#manual_purchase_email').val();
	 var name = $('#manual_purchase_full_name').val();
	 if(email === ''){
		alert("Please Enter Email");
		window.location.reload();
	}
	else{
	 amount = amount.substr(1);
	 var pay_amount = amount.replace(',', '');
		
		var handler = PaystackPop.setup({
			key: sell_media_paystack.key,
			email: email,
			amount: parseInt(pay_amount, 10)*100,
			metadata: {
				custom_fields: [
					{
						display_name: "Name",
						variable_name: "name",
						value: name
					},
					
				]
			},
			callback: function( msg ) {
				
				//window.location.assign(window.location.hostname + '/?manual=true')
				sell_media_paystack.run_func;

				var selected_payment = $( '#sell_media_payment_gateway' ).find( 'input:checked' );
				if( 'manual_purchase' == selected_payment.val() )
					$("#sell_media_payment_gateway").submit();
						
					},
			onClose: function( ) {
				alert('The transaction was not complete');
				window.location.reload();
				// $('.sell-media-cart-checkout').prop('disabled', false).text(sell_media.checkout_text);
			},
			});
			handler.openIframe();

		}








		// var selected_payment = $( '#sell_media_payment_gateway' ).find( 'input:checked' );
		// if( 'manual_purchase' == selected_payment.val() )
		// 	$("#sell_media_payment_gateway").submit();
	});

	// $(document).on( 'click', 'input[name="gateway"]', function(){
	// 	if( 'manual_purchase' == $(this).val() ){
	// 		$('.sell-media-manual-purchase-meta').slideDown();
	// 	}
	// 	else{
	// 		$('.sell-media-manual-purchase-meta').slideUp();
	// 	}
	// });
});


