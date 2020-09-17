jQuery( document ).ready( function($) {
	$( 'tr.hidden-toggle a' ).click( function( e ) {
		e.preventDefault();

		$(this).parents( 'table' ).addClass( 'showhidden' );

	} )
});