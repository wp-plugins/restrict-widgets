( function ( $ ) {

	function initialize(){
		$('.restrict-widgets-fastselect').each(function (index, item) {
			//check if the parent element is dynamic (draggable) or if the instance ID has been filled in
			if(! $(this).closest('form').parent().parent().attr('id').includes('__i__') ) {
				$(this).fastselect();
			}
		});
	};

	$( document ).ready( function () {
		initialize();

		//This function happens on a heartbeat basis as well as a LOT of other times... check the console.
		$(document).ajaxComplete(function() {
			initialize()
		});
	} );

	// ask whether to reset options to defaults
	$( document ).on( 'click', '#reset_rw_options', function () {
		return confirm( rwArgs.resetToDefaults );
	} );

} )( jQuery );	
