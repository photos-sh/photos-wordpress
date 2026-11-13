/**
 * Refreshes the Photos.sh progress notice while images are waiting.
 */
( function () {
	'use strict';

	var config = window.photosShProgress;
	var box = document.getElementById( 'photos-sh-progress' );
	if ( ! config || ! box || ! window.fetch ) {
		return;
	}

	function refresh() {
		window
			.fetch( config.url, { credentials: 'same-origin' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( answer ) {
				if ( ! answer || ! answer.success ) {
					return;
				}
				box.innerHTML = answer.data.html;
				box.className = 'notice notice-' + answer.data.type;
				if ( answer.data.pending > 0 ) {
					window.setTimeout( refresh, 5000 );
				}
			} )
			.catch( function () {
				window.setTimeout( refresh, 15000 );
			} );
	}

	window.setTimeout( refresh, 5000 );
} )();
