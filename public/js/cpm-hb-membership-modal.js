(function( $ ) {
	'use strict';

	$( function() {
		var $modal = $( '#cpm-hb-membership-modal' );
		var $continue = $( '#cpm-hb-membership-continue' );
		if ( ! $modal.length ) {
			return;
		}

		var selectedTier = null;

		function openModal() {
			$modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-membership-modal-open' );
		}

		function closeModal() {
			$modal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
			$( 'body' ).removeClass( 'cpm-hb-membership-modal-open' );
		}

		function syncSelection() {
			$modal.find( '.cpm-hb-membership-card' ).each( function() {
				var $c = $( this );
				var on = $c.data( 'tier' ) === selectedTier;
				$c.toggleClass( 'is-selected', on );
				$c.attr( 'aria-checked', on ? 'true' : 'false' );
			} );
			$continue.prop( 'disabled', ! selectedTier );
		}

		$( document ).on( 'click', '.cpm-hb-open-membership-modal', function( e ) {
			e.preventDefault();
			openModal();
		} );

		$( document ).on( 'click', '#cpm-hb-membership-close', function() {
			closeModal();
		} );

		$modal.on( 'click', function( e ) {
			if ( e.target === $modal.get( 0 ) ) {
				closeModal();
			}
		} );

		$( document ).on( 'click', '#cpm-hb-membership-modal .cpm-hb-membership-card', function() {
			selectedTier = $( this ).data( 'tier' ) || null;
			syncSelection();
		} );

		$( document ).on( 'keydown', function( e ) {
			if ( e.key !== 'Escape' || ! $modal.hasClass( 'is-open' ) ) {
				return;
			}
			closeModal();
		} );

		$( '#cpm-hb-membership-continue' ).on( 'click', function() {
			if ( ! selectedTier ) {
				return;
			}
			try {
				sessionStorage.setItem(
					'cpm_hb_selected_membership_tier',
					JSON.stringify( {
						tier: selectedTier,
						ts: Date.now()
					} )
				);
			} catch ( err ) {
				// ignore
			}
			var url = ( window.cpmHbMembership && window.cpmHbMembership.continueUrl ) ? window.cpmHbMembership.continueUrl : '/';
			closeModal();
			if ( url ) {
				var u = url.split( '#' )[0];
				var sep = u.indexOf( '?' ) >= 0 ? '&' : '?';
				window.location.href = u + sep + 'membership=' + encodeURIComponent( selectedTier );
			}
		} );
	} );
})( jQuery );
