( function ( $ ) {

	$( document ).ready( function () {
		$( '.restrict-widgets-fastselect' ).fastselect();
	} );

	// ask whether to reset options to defaults
	$( document ).on( 'click', '#reset_rw_options', function () {
		return confirm( rwArgs.resetToDefaults );
	} );

} )( jQuery );	
