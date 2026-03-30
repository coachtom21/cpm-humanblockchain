(function( $ ) {
	'use strict';

	$( function() {
		var $modal = $( '#cpm-hb-membership-modal' );
		var $contact = $( '#cpm-hb-membership-contact-modal' );
		var $continue = $( '#cpm-hb-membership-continue' );
		if ( ! $modal.length ) {
			return;
		}

		var cfg = window.cpmHbMembership || {};
		var selectedTier = null;
		var contactModeGuest = false;

		function openModal() {
			$modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-membership-modal-open' );
		}

		function closeModal() {
			$modal.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
			if ( ! $contact.hasClass( 'is-open' ) ) {
				$( 'body' ).removeClass( 'cpm-hb-membership-modal-open' );
			}
		}

		function openContactModal( guest ) {
			contactModeGuest = !! guest;
			$contact.removeClass( 'cpm-hb-membership-contact--phone-only' );
			$( '#cpm-hb-membership-contact-error' ).prop( 'hidden', true ).text( '' );
			$( '#cpm-hb-membership-contact-form' )[ 0 ].reset();

			if ( guest ) {
				$( '.cpm-hb-membership-email-row' ).show();
				$( '#cpm-hb-membership-field-email' ).prop( 'required', true ).prop( 'readonly', false );
				$( '.cpm-hb-membership-contact-optional' ).show();
			} else {
				$contact.addClass( 'cpm-hb-membership-contact--phone-only' );
				$( '.cpm-hb-membership-email-row' ).hide();
				$( '#cpm-hb-membership-field-email' ).prop( 'required', false );
				$( '.cpm-hb-membership-contact-optional' ).hide();
				if ( cfg.userEmail ) {
					$( '#cpm-hb-membership-field-email' ).val( cfg.userEmail );
				}
			}

			$contact.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-membership-modal-open' );
			setTimeout( function() {
				$( guest ? '#cpm-hb-membership-field-email' : '#cpm-hb-membership-field-phone' ).trigger( 'focus' );
			}, 50 );
		}

		function closeContactModal() {
			$contact.removeClass( 'is-open' ).attr( 'aria-hidden', 'true' );
			if ( ! $modal.hasClass( 'is-open' ) ) {
				$( 'body' ).removeClass( 'cpm-hb-membership-modal-open' );
			}
		}

		function setBusy( busy, $btn, labelBusy, labelIdle ) {
			if ( ! $btn || ! $btn.length ) {
				return;
			}
			$btn.prop( 'disabled', !! busy );
			if ( labelBusy !== undefined && labelIdle !== undefined ) {
				$btn.text( busy ? labelBusy : labelIdle );
			}
		}

		function redirectAfterSuccess() {
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
			var url = cfg.continueUrl || '/';
			closeContactModal();
			closeModal();
			var u = url.split( '#' )[ 0 ];
			var sep = u.indexOf( '?' ) >= 0 ? '&' : '?';
			window.location.href = u + sep + 'membership=' + encodeURIComponent( selectedTier ) + '&membership_granted=1';
		}

		function showApiSuccess( data ) {
			if ( data && data.user_created && data.password_generated && data.password ) {
				var pre = ( cfg.strings && cfg.strings.accountCreated ) || 'Save this password:';
				window.alert( pre + '\n\n' + data.password );
			}
			redirectAfterSuccess();
		}

		function submitMembership( extra ) {
			if ( ! cfg.ajaxUrl || ! cfg.action || ! cfg.nonce ) {
				window.alert( ( cfg.strings && cfg.strings.genericErr ) || 'Configuration error.' );
				return;
			}
			var payload = {
				action: cfg.action,
				nonce: cfg.nonce,
				tier: selectedTier
			};
			if ( extra && typeof extra === 'object' ) {
				$.extend( payload, extra );
			}

			var $btn = $continue;
			var str = cfg.strings || {};
			setBusy( true, $btn, str.submitting, str.continue );

			$.ajax( {
				url: cfg.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: payload
			} )
				.done( function( response ) {
					if ( ! response ) {
						window.alert( str.genericErr || 'Error' );
						return;
					}
					if ( response.needs_phone ) {
						openContactModal( false );
						return;
					}
					if ( response.success && response.data ) {
						var d = response.data;
						if ( d.success ) {
							showApiSuccess( d );
							return;
						}
					}
					var msg =
						( response.data && response.data.message ) ||
						response.message ||
						str.genericErr;
					window.alert( msg );
				} )
				.fail( function( xhr ) {
					var err = xhr.responseJSON;
					var msg =
						( err && err.data && err.data.message ) ||
						( err && err.message ) ||
						str.genericErr;
					window.alert( msg );
				} )
				.always( function() {
					setBusy( false, $btn, str.submitting, str.continue );
				} );
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

		$( document ).on( 'click', '#cpm-hb-membership-contact-close', function() {
			closeContactModal();
		} );

		$modal.on( 'click', function( e ) {
			if ( e.target === $modal.get( 0 ) ) {
				closeModal();
			}
		} );

		$contact.on( 'click', function( e ) {
			if ( e.target === $contact.get( 0 ) ) {
				closeContactModal();
			}
		} );

		$( document ).on( 'click', '#cpm-hb-membership-modal .cpm-hb-membership-card', function() {
			selectedTier = $( this ).data( 'tier' ) || null;
			syncSelection();
		} );

		$( document ).on( 'keydown', function( e ) {
			if ( e.key !== 'Escape' ) {
				return;
			}
			if ( $contact.hasClass( 'is-open' ) ) {
				closeContactModal();
				return;
			}
			if ( $modal.hasClass( 'is-open' ) ) {
				closeModal();
			}
		} );

		$( '#cpm-hb-membership-continue' ).on( 'click', function() {
			if ( ! selectedTier ) {
				return;
			}
			if ( cfg.isLoggedIn ) {
				submitMembership();
				return;
			}
			if ( ! $contact.length ) {
				window.alert( ( cfg.strings && cfg.strings.genericErr ) || 'Error' );
				return;
			}
			openContactModal( true );
		} );

		$( '#cpm-hb-membership-contact-form' ).on( 'submit', function( e ) {
			e.preventDefault();
			var $err = $( '#cpm-hb-membership-contact-error' );
			$err.prop( 'hidden', true ).text( '' );

			var phone = $( '#cpm-hb-membership-field-phone' ).val();
			var email = $( '#cpm-hb-membership-field-email' ).val();
			var extra = {
				phone: phone,
				mobile: phone
			};

			if ( contactModeGuest ) {
				extra.email = email;
				var fn = $( '#cpm-hb-membership-field-first-name' ).val();
				var ln = $( '#cpm-hb-membership-field-last-name' ).val();
				var un = $( '#cpm-hb-membership-field-username' ).val();
				var pw = $( '#cpm-hb-membership-field-password' ).val();
				if ( fn ) {
					extra.first_name = fn;
				}
				if ( ln ) {
					extra.last_name = ln;
				}
				if ( un ) {
					extra.username = un;
				}
				if ( pw ) {
					extra.password = pw;
				}
			}

			var $sub = $( '#cpm-hb-membership-contact-submit' );
			var str = cfg.strings || {};
			setBusy( true, $sub, str.submitting, str.submit );

			$.ajax( {
				url: cfg.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: $.extend(
					{
						action: cfg.action,
						nonce: cfg.nonce,
						tier: selectedTier
					},
					extra
				)
			} )
				.done( function( response ) {
					if ( ! response ) {
						$err.text( str.genericErr || 'Error' ).prop( 'hidden', false );
						return;
					}
					if ( response.success && response.data && response.data.success ) {
						showApiSuccess( response.data );
						return;
					}
					if ( response.needs_phone ) {
						$err.text( response.message || '' ).prop( 'hidden', false );
						return;
					}
					var msg =
						( response.data && response.data.message ) ||
						response.message ||
						str.genericErr;
					$err.text( msg ).prop( 'hidden', false );
				} )
				.fail( function( xhr ) {
					var err = xhr.responseJSON;
					var msg =
						( err && err.data && err.data.message ) ||
						( err && err.message ) ||
						str.genericErr;
					$err.text( msg ).prop( 'hidden', false );
				} )
				.always( function() {
					setBusy( false, $sub, str.submitting, str.submit );
				} );
		} );
	} );
})( jQuery );
