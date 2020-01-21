( function ( $ ) {

	function initialize(){
		//$( '.restrict-widgets-fastselect' );
		var selector = $( '.restrict-widgets-fastselect' );

		//make sure this select hasn't already been initialized, otherwise we get nested duplicates.
		if(!selector.parent().hasClass("fstElement")) {
			$( '.restrict-widgets-fastselect' ).fastselect();
		}
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
