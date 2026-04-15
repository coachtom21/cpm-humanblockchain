(function( $ ) {
	'use strict';

	function renderBackorders( data ) {
		var S = window.cpmHbBackorders && window.cpmHbBackorders.strings ? window.cpmHbBackorders.strings : {};
		var title = S.title || 'Backorders';
		var $box = $( '<section class="cpm-hb-backorders-panel" />' );
		$box.append( $( '<h2 class="cpm-hb-backorders-title" />' ).text( title ) );

		if ( Array.isArray( data ) && data.length === 0 ) {
			var emptyText = S.empty || '';
			if ( window.cpmHbBackorders && window.cpmHbBackorders.showNoPhone && S.noPhone ) {
				emptyText = S.noPhone;
			}
			$box.append( $( '<p class="cpm-hb-backorders-empty" />' ).text( emptyText ) );
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

	function renderVisitorPanel( S, loginUrl ) {
		var title = S.title || 'Your backorders';
		var $box = $( '<section class="cpm-hb-backorders-panel" />' );
		$box.append( $( '<h2 class="cpm-hb-backorders-title" />' ).text( title ) );
		var $p = $( '<p class="cpm-hb-backorders-empty" />' );
		$p.append( document.createTextNode( ( S.loginPrompt || '' ) + ' ' ) );
		if ( loginUrl ) {
			$p.append(
				$( '<a class="cpm-hb-backorders-login-link" />' )
					.attr( 'href', loginUrl )
					.text( 'Log in' )
			);
		}
		$box.append( $p );
		return $box;
	}

	function renderApiMissingPanel( S ) {
		var $box = $( '<section class="cpm-hb-backorders-panel" />' );
		$box.append( $( '<h2 class="cpm-hb-backorders-title" />' ).text( S.title || 'Your backorders' ) );
		$box.append(
			$( '<p class="cpm-hb-backorders-empty cpm-hb-backorders-api-missing" />' ).text( S.apiMissing || '' )
		);
		return $box;
	}

	$( function() {
		var H = window.cpmHbBackorders;
		if ( ! H ) {
			return;
		}

		var $mount = $( '#cpm-hb-backorders-root' );
		if ( ! $mount.length ) {
			return;
		}

		var S = H.strings || {};
		var data = null;
		var raw = null;

		try {
			raw = sessionStorage.getItem( 'cpm_hb_smallstreet_backorders' );
		} catch ( err ) {
			raw = null;
		}

		if ( raw ) {
			try {
				sessionStorage.removeItem( 'cpm_hb_smallstreet_backorders' );
			} catch ( err2 ) {
				// ignore
			}
			try {
				data = JSON.parse( raw );
			} catch ( err3 ) {
				data = null;
			}
		}

		if ( data !== null ) {
			$mount.append( renderBackorders( data ) );
			return;
		}

		if ( H.isVisitor ) {
			$mount.append( renderVisitorPanel( S, H.loginUrl || '' ) );
			return;
		}

		if ( ! H.apiConfigured ) {
			$mount.append( renderApiMissingPanel( S ) );
			return;
		}

		if ( Object.prototype.hasOwnProperty.call( H, 'initialRows' ) ) {
			$mount.append( renderBackorders( H.initialRows ) );
			return;
		}

		$mount.append( renderBackorders( [] ) );
	} );
})( jQuery );
