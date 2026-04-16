(function( $ ) {
	'use strict';

	function getOrderIdFromRow( row ) {
		if ( ! row || typeof row !== 'object' ) {
			return 0;
		}
		if ( row.id != null && row.id !== '' ) {
			var a = parseInt( row.id, 10 );
			return isNaN( a ) ? 0 : a;
		}
		if ( row.order_number != null && row.order_number !== '' ) {
			var b = parseInt( row.order_number, 10 );
			return isNaN( b ) ? 0 : b;
		}
		return 0;
	}

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
			var selectAllLbl = S.selectAll || '';
			var selectRowLbl = S.selectRow || '';
			var $table = $( '<table class="cpm-hb-backorders-table" />' );
			var $thead = $( '<thead><tr /></tr></thead>' );
			var $hr = $thead.find( 'tr' );
			var $thSelect = $( '<th scope="col" class="cpm-hb-backorders-col-select" />' );
			var $cbAll = $( '<input type="checkbox" class="cpm-hb-backorders-select-all" />' );
			if ( selectAllLbl ) {
				$cbAll.attr( 'aria-label', selectAllLbl );
				$cbAll.attr( 'title', selectAllLbl );
			}
			$thSelect.append( $cbAll );
			$hr.append( $thSelect );
			keys.forEach( function( k ) {
				$hr.append( $( '<th scope="col" />' ).text( k ) );
			} );
			var $tbody = $( '<tbody />' );
			data.forEach( function( row, rowIdx ) {
				var $tr = $( '<tr />' );
				$tr.data( 'cpmHbRow', row );
				var $cb = $( '<input type="checkbox" class="cpm-hb-backorders-row-select" />' );
				if ( selectRowLbl ) {
					var rowLabel = selectRowLbl.indexOf( '%' ) !== -1
						? selectRowLbl.replace( /%s/g, String( rowIdx + 1 ) )
						: selectRowLbl + ' ' + String( rowIdx + 1 );
					$cb.attr( 'aria-label', rowLabel );
				}
				$tr.append( $( '<td class="cpm-hb-backorders-col-select" />' ).append( $cb ) );
				keys.forEach( function( k ) {
					var v = row[ k ];
					$tr.append( $( '<td />' ).text( v !== null && v !== undefined ? String( v ) : '' ) );
				} );
				$tbody.append( $tr );
			} );
			$table.append( $thead ).append( $tbody );

			$table.on( 'change', '.cpm-hb-backorders-select-all', function() {
				var on = $( this ).prop( 'checked' );
				$tbody.find( '.cpm-hb-backorders-row-select' ).prop( 'checked', on );
				$( this ).prop( 'indeterminate', false );
				$box.trigger( 'cpmHbBackordersSelectionChange' );
			} );
			$table.on( 'change', '.cpm-hb-backorders-row-select', function() {
				var $rows = $tbody.find( '.cpm-hb-backorders-row-select' );
				var total = $rows.length;
				var n = $rows.filter( ':checked' ).length;
				$cbAll.prop( 'checked', total > 0 && n === total );
				$cbAll.prop( 'indeterminate', n > 0 && n < total );
				$box.trigger( 'cpmHbBackordersSelectionChange' );
			} );

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

	function attachBuyerDeliveryFlow( $panel ) {
		var H = window.cpmHbBackorders;
		var S = ( H && H.strings ) ? H.strings : {};
		if ( ! H || ! H.canConfirmDelivery || ! H.ajaxUrl || ! H.confirmNonce ) {
			return;
		}

		var $actions = $( '<div class="cpm-hb-backorders-actions" />' );
		var $hint = $( '<p class="cpm-hb-backorders-actions-hint" />' ).text( S.continueDeliveryHint || '' );
		var $btn = $( '<button type="button" class="cpm-hb-backorders-continue" disabled />' ).text( S.continueDelivery || 'Confirm delivery' );
		$actions.append( $hint ).append( $btn );
		$panel.append( $actions );

		var $modal = $( '<div class="cpm-hb-backorders-modal cpm-hb-backorders-modal--hidden" role="dialog" aria-modal="true" aria-hidden="true" />' );
		var $backdrop = $( '<div class="cpm-hb-backorders-modal-backdrop" />' );
		var $shell = $( '<div class="cpm-hb-backorders-modal-shell" />' );
		var $close = $( '<button type="button" class="cpm-hb-backorders-modal-close" aria-label="' + ( S.cancel || 'Close' ) + '">&times;</button>' );
		var $mtitle = $( '<h3 class="cpm-hb-backorders-modal-title" />' ).text( S.modalTitle || 'Transaction code' );
		var $mbody = $( '<p class="cpm-hb-backorders-modal-text" />' ).text( S.modalBody || '' );
		var $input = $( '<input type="text" class="cpm-hb-backorders-modal-input" autocomplete="one-time-code" />' );
		$input.attr( 'placeholder', S.transactionPlaceholder || '' );
		var $feedback = $( '<p class="cpm-hb-backorders-modal-feedback" role="alert" />' );
		var $rowBtns = $( '<div class="cpm-hb-backorders-modal-buttons" />' );
		var $btnCancel = $( '<button type="button" class="cpm-hb-backorders-modal-btn cpm-hb-backorders-modal-btn--secondary" />' ).text( S.cancel || 'Cancel' );
		var $btnSubmit = $( '<button type="button" class="cpm-hb-backorders-modal-btn cpm-hb-backorders-modal-btn--primary" />' ).text( S.submitConfirm || 'Submit' );
		$rowBtns.append( $btnCancel ).append( $btnSubmit );
		$shell.append( $close ).append( $mtitle ).append( $mbody ).append( $input ).append( $feedback ).append( $rowBtns );
		$modal.append( $backdrop ).append( $shell );
		$( 'body' ).append( $modal );

		function syncContinueButton() {
			var n = $panel.find( '.cpm-hb-backorders-row-select:checked' ).length;
			$btn.prop( 'disabled', n === 0 );
		}

		$panel.on( 'cpmHbBackordersSelectionChange', syncContinueButton );
		syncContinueButton();

		function openModal() {
			if ( $btn.prop( 'disabled' ) ) {
				return;
			}
			$feedback.text( '' ).removeClass( 'cpm-hb-backorders-modal-feedback--error cpm-hb-backorders-modal-feedback--ok' );
			$input.val( '' );
			$modal.removeClass( 'cpm-hb-backorders-modal--hidden' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-backorders-modal-open' );
			setTimeout( function() {
				$input.trigger( 'focus' );
			}, 0 );
		}

		function closeModal() {
			$modal.addClass( 'cpm-hb-backorders-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( 'body' ).removeClass( 'cpm-hb-backorders-modal-open' );
		}

		$btn.on( 'click', openModal );
		$close.on( 'click', closeModal );
		$btnCancel.on( 'click', closeModal );
		$backdrop.on( 'click', closeModal );

		$btnSubmit.on( 'click', function() {
			var orderIds = [];
			$panel.find( '.cpm-hb-backorders-row-select:checked' ).closest( 'tr' ).each( function() {
				var row = $( this ).data( 'cpmHbRow' );
				var oid = getOrderIdFromRow( row );
				if ( oid > 0 ) {
					orderIds.push( oid );
				}
			} );
			var code = ( $input.val() || '' ).trim();
			if ( orderIds.length === 0 || ! code ) {
				$feedback.text( S.enterCodeAndOrders || '' ).removeClass( 'cpm-hb-backorders-modal-feedback--ok' ).addClass( 'cpm-hb-backorders-modal-feedback--error' );
				return;
			}
			$btnSubmit.prop( 'disabled', true ).text( S.submitting || '…' );
			$.ajax( {
				url: H.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: H.confirmAction,
					nonce: H.confirmNonce,
					transaction_code: code,
					order_ids: JSON.stringify( orderIds )
				}
			} )
				.done( function( res ) {
					if ( res && res.success && res.data && res.data.message ) {
						$feedback.text( res.data.message ).removeClass( 'cpm-hb-backorders-modal-feedback--error' ).addClass( 'cpm-hb-backorders-modal-feedback--ok' );
						var dest = ( res.data.redirect_url && typeof res.data.redirect_url === 'string' )
							? res.data.redirect_url
							: ( H && H.homeUrl ? H.homeUrl : '/' );
						setTimeout( function() {
							window.location.href = dest;
						}, 900 );
					} else {
						var err = ( res && res.data && res.data.message ) ? res.data.message : 'Request failed.';
						$feedback.text( err ).addClass( 'cpm-hb-backorders-modal-feedback--error' );
					}
				} )
				.fail( function( xhr ) {
					var msg = 'Request failed.';
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					$feedback.text( msg ).addClass( 'cpm-hb-backorders-modal-feedback--error' );
				} )
				.always( function() {
					$btnSubmit.prop( 'disabled', false ).text( S.submitConfirm || 'Submit' );
				} );
		} );
	}

	function mountBackordersTable( $mount, rows ) {
		var $panel = renderBackorders( rows );
		$mount.append( $panel );
		if ( window.cpmHbBackorders && window.cpmHbBackorders.canConfirmDelivery && Array.isArray( rows ) && rows.length ) {
			attachBuyerDeliveryFlow( $panel );
		}
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
			mountBackordersTable( $mount, Array.isArray( data ) ? data : [] );
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
			mountBackordersTable( $mount, Array.isArray( H.initialRows ) ? H.initialRows : [] );
			return;
		}

		mountBackordersTable( $mount, [] );
	} );
})( jQuery );
