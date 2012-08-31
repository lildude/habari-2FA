(function ($) {
	var init = function () {
		if ( $('#secret input[type="text"]').val() == '' ) {
			$('#secret input[type="text"]').val(create_secret);
		}
		// Make Secret field read-only.  We can remove this once the optionscontrol_text formcontrol is updated
		$('#secret input[type="text"]').attr("readonly", "readonly");
		
		$('#create_secret').click(function() {
			$('#secret input[type="text"]').val(create_secret);
			// Disable the show button until we save the form
			$('#show_hide_qr').attr("disabled", "disabled").val( _t( 'Save form to refresh and view QR' ) );
		});
		$('#show_hide_qr').click(function() {
			$('#qr_code').slideToggle('slow');
		});
	}
	$(init);
})(jQuery);

function create_secret() {
	var chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
	var secret = '';
	for ( var i = 0; i < 16; i++ ) {
		var r = Math.floor(Math.random()*32);
		secret += chars.substr(r,1);
	}
	return secret;
}