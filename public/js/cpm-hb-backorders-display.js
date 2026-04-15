(function( $ ) {
	'use strict';

	function renderBackorders( data ) {
		var S = window.cpmHbBackorders && window.cpmHbBackorders.strings ? window.cpmHbBackorders.strings : {};
		var title = S.title || 'Backorders';
		var $box = $( '<section class="cpm-hb-backorders-panel" />' );
		$box.append( $( '<h2 class="cpm-hb-backorders-title" />' ).text( title ) );

		if ( Array.isArray( data ) && data.length === 0 ) {
			$box.append( $( '<p class="cpm-hb-backorders-empty" />' ).text( S.empty || '' ) );
			return $box;
		}
		if ( Array.isArray( data ) && data.length && typeof data[0] === 'object' && data[0] !== null ) {
			var keys = Object.keys( data[0] );
			var $table = $( '<table class="cpm-hb-backorders-table" />' );
			var $thead = $( '<thead><tr /></tr></thead>' );
			var $hr = $thead.find( 'tr' );
			keys.forEach( function( k ) {
				$hr.append( $( '<th scope="col" />' ).text( k ) );
			} );
			var $tbody = $( '<tbody />' );
			data.forEach( function( row ) {
				var $tr = $( '<tr />' );
				keys.forEach( function( k ) {
					var v = row[ k ];
					$tr.append( $( '<td />' ).text( v !== null && v !== undefined ? String( v ) : '' ) );
				} );
				$tbody.append( $tr );
			} );
			$table.append( $thead ).append( $tbody );
			$box.append( $table );
		} else if ( data && typeof data === 'object' ) {
			var $pre = $( '<pre class="cpm-hb-backorders-json" />' );
			$pre.text( JSON.stringify( data, null, 2 ) );
			$box.append( $pre );
		} else {
			$box.append( $( '<p class="cpm-hb-backorders-empty" />' ).text( S.empty || '' ) );
		}
		return $box;
	}

	$( function() {
		var raw;
		try {
			raw = sessionStorage.getItem( 'cpm_hb_smallstreet_backorders' );
		} catch ( err ) {
			return;
		}
		if ( ! raw ) {
			return;
		}
		try {
			sessionStorage.removeItem( 'cpm_hb_smallstreet_backorders' );
		} catch ( err2 ) {
			// ignore
		}
		var data;
		try {
			data = JSON.parse( raw );
		} catch ( err3 ) {
			return;
		}
		var $target = $( 'main .entry-content' ).first();
		if ( ! $target.length ) {
			$target = $( '.entry-content' ).first();
		}
		if ( ! $target.length ) {
			$target = $( 'article' ).first();
		}
		if ( ! $target.length ) {
			$target = $( 'body' );
		}
		$target.prepend( renderBackorders( data ) );
	} );
})( jQuery );
