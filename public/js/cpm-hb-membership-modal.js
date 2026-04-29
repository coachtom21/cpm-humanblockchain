(function( $ ) {
	'use strict';

	$( function() {
		var $modal = $( '#cpm-hb-membership-modal' );
		var $contact = $( '#cpm-hb-membership-contact-modal' );
		var $continue = $( '#cpm-hb-membership-continue' );
		var $branch = $( '#cpm-hb-membership-branch' );
		if ( ! $modal.length ) {
			return;
		}

		var cfg = window.cpmHbMembership || {};
		var selectedTier = null;
		var contactModeGuest = false;

		function tierFromElement( $el ) {
			var t = ( $el.attr( 'data-tier' ) || $el.data( 'tier' ) || '' );
			if ( typeof t === 'string' && t ) {
				return t.trim() || null;
			}
			if ( t != null ) {
				return String( t ).trim() || null;
			}
			return null;
		}

		function getBranchValue() {
			if ( ! $branch.length ) {
				return '';
			}
			var v = $branch.val();
			return v && String( v ).trim() !== '' ? String( v ).trim() : '';
		}

		function openModal() {
			$modal.addClass( 'is-open' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-membership-modal-open' );
		}

		function resetMembershipFormView() {
			var $success = $( '#cpm-hb-membership-success' );
			$success.prop( 'hidden', true ).attr( 'aria-hidden', 'true' );
			$( '#cpm-hb-membership-success-msg' ).empty();
			$modal.find( '.cpm-hb-membership-intro, .cpm-hb-membership-branch-row, .cpm-hb-membership-grid, .cpm-hb-membership-actions' ).show();
			if ( $branch.length ) {
				$branch.val( '' );
			}
		}

		function closeModal() {
			resetMembershipFormView();
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

		function showMembershipSuccessMessage( data ) {
			var str = cfg.strings || {};
			var msg = '';
			if ( data && typeof data.message === 'string' && data.message.trim() !== '' ) {
				msg = data.message.trim();
			} else {
				msg = str.membershipSuccess || str.successNext || 'Membership updated.';
			}
			$( '#cpm-hb-membership-success-msg' ).text( msg );
			var $ok = $( '#cpm-hb-membership-success' );
			$ok.prop( 'hidden', false ).attr( 'aria-hidden', 'false' );
			$modal.find( '.cpm-hb-membership-intro, .cpm-hb-membership-branch-row, .cpm-hb-membership-grid, .cpm-hb-membership-actions' ).hide();
			setTimeout( function() {
				$ok.trigger( 'focus' );
			}, 50 );
		}

		function finishMembershipSuccess( data ) {
			try {
				sessionStorage.setItem(
					'cpm_hb_selected_membership_tier',
					JSON.stringify( {
						tier: selectedTier,
						branch: getBranchValue(),
						ts: Date.now()
					} )
				);
			} catch ( err ) {
				// ignore
			}
			closeContactModal();
			openModal();
			showMembershipSuccessMessage( data || {} );
		}

		function rememberTierAndGo( data ) {
			if ( ! data || typeof data !== 'object' ) {
				return false;
			}
			var url = ( typeof data.redirect_url === 'string' && data.redirect_url.trim() !== '' )
				? data.redirect_url.trim()
				: '';
			if ( ! url && data.data && typeof data.data.redirect_url === 'string' ) {
				url = data.data.redirect_url.trim();
			}
			if ( ! url && selectedTier ) {
				var baseRaw = cfg.pmproCheckoutBaseUrl || cfg.checkoutBaseUrl || '';
				var base = String( baseRaw ).split( '#' )[ 0 ];
				if ( base ) {
					var q = ( base.indexOf( '?' ) >= 0 ? '&' : '?' ) + 'cpm_hb_tier=' + encodeURIComponent( selectedTier );
					var b = getBranchValue();
					if ( b ) {
						q += '&cpm_hb_branch=' + encodeURIComponent( b );
					}
					url = base + q;
				}
			}
			if ( ! url ) {
				return false;
			}
			try {
				sessionStorage.setItem(
					'cpm_hb_selected_membership_tier',
					JSON.stringify( { tier: selectedTier, branch: getBranchValue(), ts: Date.now() } )
				);
			} catch ( err ) {
				// ignore
			}
			window.location.href = url;
			return true;
		}

		function showApiSuccess( data ) {
			if ( rememberTierAndGo( data ) ) {
				return;
			}
			if ( data && data.user_created && data.password_generated && data.password ) {
				var pre = ( cfg.strings && cfg.strings.accountCreated ) || 'Save this password:';
				window.alert( pre + '\n\n' + data.password );
			}
			finishMembershipSuccess( data );
		}

		function submitMembership( extra ) {
			if ( ! cfg.ajaxUrl || ! cfg.action || ! cfg.nonce ) {
				window.alert( ( cfg.strings && cfg.strings.genericErr ) || 'Configuration error.' );
				return;
			}
			var payload = {
				action: cfg.action,
				nonce: cfg.nonce,
				tier: selectedTier,
				branch: getBranchValue(),
				cpm_hb_branch: getBranchValue()
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
				var t = tierFromElement( $c );
				var on = t && selectedTier && t === selectedTier;
				$c.toggleClass( 'is-selected', !! on );
				$c.attr( 'aria-checked', on ? 'true' : 'false' );
			} );
			$continue.prop( 'disabled', ! selectedTier || ! getBranchValue() );
		}

		$( document ).on( 'click', '.cpm-hb-open-membership-modal', function( e ) {
			e.preventDefault();
			resetMembershipFormView();
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

		$( document ).on( 'click', '#cpm-hb-membership-modal .cpm-hb-membership-card', function( e ) {
			selectedTier = tierFromElement( $( e.currentTarget ) );
			syncSelection();
		} );

		$( document ).on( 'change', '#cpm-hb-membership-branch', function() {
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
			if ( ! selectedTier || ! getBranchValue() ) {
				return;
			}
			if ( cfg.isLoggedIn ) {
				submitMembership();
				return;
			}
			if ( cfg.skipGuestContact ) {
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
						tier: selectedTier,
						branch: getBranchValue(),
						cpm_hb_branch: getBranchValue()
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
