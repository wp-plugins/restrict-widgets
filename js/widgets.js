( function ( $ ) {

	var current_sidebar;

	// only for customizer
	$( document ).on( 'click', '#accordion-panel-widgets .accordion-section h3.accordion-section-title', function () {
		// get sidebars (widgets are empty)
		current_sidebar = $( this ).parent().find( 'ul' ).first();

		$( current_sidebar ).on( 'click', '.widget-top', function () {
			// get widgets
			$( this ).closest( '.widget' ).find( '.restrict-widgets select.select2' ).each( function( i, el ) {
				// initialize select2 only for original selects
				if ( typeof $( el ).data( 'select2' ) === 'undefined' ) {
					$( el ).select2();
				}
			} );
		} );
	} );

	// only for widgets.php (not for customizer)
	$( document ).on( 'ajaxComplete', function () {
		$( '.widgets-sortables select.select2' ).each( function( i, el ) {
			setTimeout( function() {
				// initialize select2 only for original selects
				if ( typeof $( el ).data( 'select2' ) === 'undefined' ) {
					$( el ).select2();
				}
			}, 150 );
		} );
	} );

	// update select2 in customizer when adding new widget
	$( document ).on( 'click', '.widget-tpl', function () {
		// delay initialization of select2
		setTimeout( function() {
			current_sidebar.children().eq( -2 ).find( '.restrict-widgets select.select2' ).select2();
		}, 150 );
	} );

	$( document ).ready( function () {
		// only for widgets.php (not for customizer)
		$( '.widgets-sortables select.select2' ).select2();

		for ( i in rwArgs.restrict_widgets ) {
			$( "div[id*='" + rwArgs.restrict_widgets[i] + "'] div" ).remove();
		}

		for ( i in rwArgs.restrict_class ) {
			$( "div[id*='" + rwArgs.restrict_class[i] + "-__i__']" ).remove();
		}

		for ( i in rwArgs.restrict_nonclass ) {
			$( "div[id*='_" + rwArgs.restrict_nonclass[i] + "']" ).remove();
		}

		if ( rwArgs.restrict_orphan_sidebar == 1 ) {
			$( '.orphan-sidebar' ).remove();
		}
	} );

} )( jQuery );	