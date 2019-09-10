( function ( $ ) {

	function initialize(){
	    $( '.restrict-widgets-fastselect' ).fastselect();
	};

	$( document ).ready( function () {
		initialize();

		$(document).ajaxComplete(function () {
	      initialize()
	    });
	} );

	// ask whether to reset options to defaults
	$( document ).on( 'click', '#reset_rw_options', function () {
		return confirm( rwArgs.resetToDefaults );
	} );

} )( jQuery );	
