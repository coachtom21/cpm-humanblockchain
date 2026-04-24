(function( $ ) {
	'use strict';

	$( function() {
		var $modal = $( '#cpm-hb-landing-entry-modal' );
		var $roleModal = $( '#cpm-hb-role-modal' );
		if ( ! $modal.length ) {
			return;
		}

		/* Localized URLs; script must still run if wp_localize_script fails (mobile cache / conflicts). */
		if ( ! window.cpmHbLanding ) {
			window.cpmHbLanding = {};
		}

		/* Matches PHP: case-insensitive proof=Scan / proof=scan (browsers and URLSearchParams are case-sensitive on the key). */
		function queryProofIsScan() {
			try {
				var q = window.location.search || '';
				var m = q.match( /[?&]proof=([^&]*)/i );
				if ( ! m || m.length < 2 ) {
					return false;
				}
				return decodeURIComponent( m[ 1 ].replace( /\+/g, ' ' ) ).toLowerCase() === 'scan';
			} catch ( e ) {
				return false;
			}
		}

		try {
			if ( queryProofIsScan() ) {
				sessionStorage.setItem( 'cpm_hb_proof_scan', '1' );
			}
		} catch ( err ) {
			// ignore
		}
		if ( window.cpmHbLanding.hasProofScan ) {
			try {
				sessionStorage.setItem( 'cpm_hb_proof_scan', '1' );
			} catch ( err2 ) {
				// ignore
			}
		}

		var state = {
			proof: null,
			final: null
		};

		function setPressed( $group, value ) {
			$group.each( function() {
				var on = value != null && $( this ).data( 'value' ) === value;
				$( this ).attr( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}

		function syncUiFromState() {
			setPressed( $( '.cpm-hb-entry-pill[data-prompt="proof"]' ), state.proof );
			setPressed( $( '.cpm-hb-entry-pill[data-prompt="final"]' ), state.final );
		}

		function urlHasProofScan() {
			if ( queryProofIsScan() ) {
				return true;
			}
			var H = window.cpmHbLanding || {};
			if ( H.hasProofScan ) {
				return true;
			}
			try {
				return sessionStorage.getItem( 'cpm_hb_proof_scan' ) === '1';
			} catch ( err2 ) {
				return false;
			}
		}

		function stripProofScanFromUrl() {
			if ( ! queryProofIsScan() ) {
				return;
			}
			try {
				var u = new URL( window.location.href );
				var toDelete = [];
				u.searchParams.forEach( function( _v, k ) {
					if ( k.toLowerCase() === 'proof' ) {
						toDelete.push( k );
					}
				} );
				if ( toDelete.length === 0 ) {
					return;
				}
				toDelete.forEach( function( k ) {
					u.searchParams.delete( k );
				} );
				var qs = u.searchParams.toString();
				u.search = qs ? '?' + qs : '';
				history.replaceState( {}, '', u.pathname + u.search + u.hash );
			} catch ( err ) {
				// ignore
			}
		}

		/**
		 * True only when this page load is a PoD link: query ?proof=scan or server said so.
		 * Do not use sessionStorage here — stale values caused the ack cookie to be set on normal home dismiss, which hid all future ?proof=scan popups.
		 */
		function proofScanInUrlOrFromServer() {
			if ( queryProofIsScan() ) {
				return true;
			}
			var H = window.cpmHbLanding || {};
			return !! H.hasProofScan;
		}

		/**
		 * Matches PHP cpm_hb_proof_scan_landing_seen — prevents the gate from reappearing on every refresh while ?proof=scan stays in the address bar.
		 */
		function markProofScanLandingDismissed() {
			if ( ! proofScanInUrlOrFromServer() ) {
				return;
			}
			var maxAge = 60 * 60 * 24 * 30;
			var secure = window.location.protocol === 'https:' ? '; Secure' : '';
			try {
				document.cookie = 'cpm_hb_proof_scan_landing_seen=1; path=/; max-age=' + maxAge + '; SameSite=Lax' + secure;
			} catch ( e ) {
				// ignore
			}
			stripProofScanFromUrl();
		}

		$( document ).on( 'click', '.cpm-hb-entry-pill', function() {
			var prompt = $( this ).data( 'prompt' );
			var val = $( this ).data( 'value' );
			if ( prompt === 'proof' ) {
				state.proof = val;
			} else if ( prompt === 'final' ) {
				state.final = val;
			}
			syncUiFromState();
		} );

		function dismissLandingModal() {
			markProofScanLandingDismissed();
			$modal.removeClass( 'active' ).attr( 'aria-hidden', 'true' );
			$( 'body' ).removeClass( 'cpm-hb-landing-entry-active' );
		}

		function showRoleModal( opts ) {
			opts = opts || {};
			if ( ! $roleModal.length ) {
				return;
			}
			if ( opts.preferSeller ) {
				$( '#cpm-hb-role-seller' ).prop( 'checked', true );
			}
			$roleModal.addClass( 'active' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-hb-role-modal-active' );
		}

		function hideRoleModal() {
			if ( ! $roleModal.length ) {
				return;
			}
			$roleModal.removeClass( 'active' ).attr( 'aria-hidden', 'true' );
			$( 'body' ).removeClass( 'cpm-hb-role-modal-active' );
		}

		function persistScanBasic() {
			try {
				sessionStorage.setItem(
					'hb_last_scan',
					JSON.stringify( {
						proof_of_delivery: state.proof,
						final_destination: state.final,
						ts: Date.now()
					} )
				);
			} catch ( e ) {
				// ignore
			}
		}

		function persistScanWithRole() {
			var role = $( 'input[name="cpm_hb_user_role"]:checked' ).val() || 'seller';
			try {
				sessionStorage.setItem(
					'hb_last_scan',
					JSON.stringify( {
						proof_of_delivery: state.proof,
						final_destination: state.final,
						user_role: role,
						ts: Date.now()
					} )
				);
			} catch ( e ) {
				// ignore
			}
		}

		/**
		 * PoD flows that need buyer/seller + OTP before the Proof-of-Delivery (backorder) URL:
		 * - Yes + Yes — delivery proof at final destination
		 * - Yes + No — intermediate/helper delivery (e.g. 1 NWP for helping); not “just enter website”
		 */
		function shouldShowRoleModalForPod() {
			return state.proof === 'yes' && ( state.final === 'yes' || state.final === 'no' );
		}

		function clearNwpFeedback( $el ) {
			if ( ! $el.length ) {
				return;
			}
			$el.removeClass( 'cpm-nwp-inline-feedback--success cpm-nwp-inline-feedback--error' ).addClass( 'cpm-nwp-inline-feedback--hidden' ).empty();
		}

		/**
		 * ?proof=scan PoD flow: after prompts + role, skip phone OTP and go to the proof-of-delivery URL (e.g. backorders).
		 */
		function skipOtpAndGoProofOfDelivery() {
			markProofScanLandingDismissed();
			var H = window.cpmHbLanding || {};
			var base = H.proofOfDeliveryUrl || H.homeUrl || '/';
			try {
				var u = new URL( base, window.location.href );
				u.searchParams.set( 'proof', 'scan' );
				window.location.assign( u.toString() );
			} catch ( err ) {
				var join = base.indexOf( '?' ) >= 0 ? '&' : '?';
				window.location.assign( base + join + 'proof=scan' );
			}
		}

		/**
		 * Logged-in seller on ?proof=scan: mint transaction code + XP without phone OTP.
		 */
		function requestLoggedInSellerPodCode() {
			var H = window.cpmHbLanding || {};
			var ajaxUrl = ( window.cpmNwp && window.cpmNwp.ajaxUrl ) ? window.cpmNwp.ajaxUrl : '';
			if ( ! ajaxUrl || ! H.proofScanNonce ) {
				window.alert( 'Reload the page from a link that includes ?proof=scan, then try again.' );
				return;
			}
			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'cpm_hb_seller_pod_logged_in',
					nonce: H.proofScanNonce
				}
			} )
				.done( function( res ) {
					if ( res && res.success && res.data && res.data.seller_transaction_code ) {
						if ( typeof window.cpmHbShowSellerPodSuccess === 'function' ) {
							window.cpmHbShowSellerPodSuccess( res.data.seller_transaction_code );
						} else {
							window.alert( res.data.seller_transaction_code );
						}
						return;
					}
					var msg = ( res && res.data && res.data.message ) ? res.data.message : 'Request failed.';
					window.alert( msg );
				} )
				.fail( function( xhr ) {
					var msg = 'Request failed.';
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					window.alert( msg );
				} );
		}

		/**
		 * After role selection: open the same “Your Phone Number” modal used by NWP, then Send OTP → verify OTP (public.js).
		 * Buyer + ?proof=scan: server checks wp_nwp_devices before OTP; after verify, redirect to backorder page (no Smallstreet fetch for now).
		 * pendingOtpRedirect sends the user to PoD URL after successful verification when the server does not return redirect_url.
		 *
		 * @param {{ buyerProofScan?: boolean }} opts
		 */
		function showPhoneOtpModal( opts ) {
			opts = opts || {};
			// Must mutate window.cpmHbLanding — `var H = cpmHbLanding || {}` breaks when the global is missing (assignments only hit a throwaway object).
			if ( ! window.cpmHbLanding ) {
				window.cpmHbLanding = {};
			}
			var H = window.cpmHbLanding;
			var N = window.cpmNwp || {};
			if ( ! H.proofScanNonce && N.proofScanNonce ) {
				H.proofScanNonce = N.proofScanNonce;
			}
			if ( ! H.proofOfDeliveryUrl && N.proofOfDeliveryUrl ) {
				H.proofOfDeliveryUrl = N.proofOfDeliveryUrl;
			}
			if ( H.hasProofScan == null && N.hasProofScan != null ) {
				H.hasProofScan = N.hasProofScan;
			}
			var $activate = $( '#cpm-nwp-activate-modal' );
			if ( ! $activate.length ) {
				if ( H.proofOfDeliveryUrl ) {
					window.location.href = H.proofOfDeliveryUrl;
				}
				return;
			}
			var role = typeof opts.landingRole === 'string' && opts.landingRole !== ''
				? opts.landingRole
				: ( opts.buyerProofScan ? 'buyer' : 'seller' );
			// PoD flags: URL/server hasProofScan, or minted cpmNwp nonces, or explicit gate (seller OTP must always send proof nonce for transaction code + API).
			H.podProofScan = proofScanInUrlOrFromServer() || ( !! N.hasProofScan && !! N.proofScanNonce ) || !! opts.fromProofScanPod;
			if ( opts.buyerProofScan && H.proofScanNonce ) {
				H.pendingOtpRedirect = H.proofOfDeliveryUrl || '';
			} else {
				H.pendingOtpRedirect = '';
			}
			H.phoneModalFromLanding = true;
			H.buyerProofScan = !! opts.buyerProofScan;
			H.landingRole = role;
			window.cpmHbSkipPodOtpContext = false;

			$( '#cpm-nwp-verify-otp-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( '#cpm-nwp-discord-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( '#cpm-nwp-register-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearNwpFeedback( $( '#cpm-nwp-verify-feedback' ) );
			clearNwpFeedback( $( '#cpm-nwp-activate-feedback' ) );
			$( '#cpm-nwp-activate-mobile' ).val( '' );
			$activate.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-nwp-modal-open' );
			setTimeout( function() {
				$( '#cpm-nwp-activate-mobile' ).trigger( 'focus' );
			}, 0 );
		}

		$( '#cpm-hb-enter-website' ).on( 'click', function() {
			if ( state.proof == null || state.final == null ) {
				var msg = ( window.cpmHbLanding && window.cpmHbLanding.answerBothPrompts ) ? window.cpmHbLanding.answerBothPrompts : 'Please answer both prompts.';
				window.alert( msg );
				return;
			}
			dismissLandingModal();
			if ( shouldShowRoleModalForPod() ) {
				// Intermediate delivery (not final stop): steer to Seller — paid for helping deliver.
				var preferSeller = state.proof === 'yes' && state.final === 'no';
				showRoleModal( { preferSeller: preferSeller } );
				return;
			}
			persistScanBasic();
		} );

		$( '#cpm-hb-role-continue' ).on( 'click', function() {
			var role = $( 'input[name="cpm_hb_user_role"]:checked' ).val() || 'seller';
			persistScanWithRole();
			hideRoleModal();
			var H = window.cpmHbLanding || {};
			// PoD (?proof=scan): guest seller → intro then OTP; logged-in seller → code + API without OTP; guest buyer → OTP then backorders; logged-in buyer → backorders.
			if ( proofScanInUrlOrFromServer() ) {
				if ( role === 'seller' ) {
					if ( H.isLoggedIn ) {
						requestLoggedInSellerPodCode();
						return;
					}
					var $introPs = $( '#cpm-hb-seller-pod-intro-modal' );
					if ( $introPs.length ) {
						$introPs.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
						$( 'body' ).addClass( 'cpm-nwp-modal-open' );
						return;
					}
					showPhoneOtpModal( {
						landingRole: 'seller',
						buyerProofScan: false,
						fromProofScanPod: true
					} );
					return;
				}
				if ( H.isLoggedIn ) {
					skipOtpAndGoProofOfDelivery();
					return;
				}
				showPhoneOtpModal( {
					landingRole: 'buyer',
					buyerProofScan: true,
					fromProofScanPod: true
				} );
				return;
			}
			showPhoneOtpModal( {
				landingRole: role,
				buyerProofScan: role === 'buyer' && proofScanInUrlOrFromServer()
			} );
		} );

		$( document ).on( 'click', '#cpm-hb-seller-pod-intro-continue', function() {
			var $intro = $( '#cpm-hb-seller-pod-intro-modal' );
			$intro.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			var H = window.cpmHbLanding || {};
			if ( H.isLoggedIn ) {
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				requestLoggedInSellerPodCode();
				return;
			}
			$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			showPhoneOtpModal( {
				landingRole: 'seller',
				buyerProofScan: false,
				fromProofScanPod: true
			} );
		} );

		$( document ).on( 'click', '#cpm-hb-seller-pod-intro-close, #cpm-hb-seller-pod-intro-modal .cpm-nwp-modal-overlay', function() {
			var $intro = $( '#cpm-hb-seller-pod-intro-modal' );
			$intro.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			if ( $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden), .cpm-hb-role-overlay.active' ).length === 0 ) {
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			}
			showRoleModal();
		} );

		$( '#cpm-hb-role-close' ).on( 'click', function() {
			hideRoleModal();
		} );

		$roleModal.on( 'click', function( e ) {
			if ( e.target === $roleModal.get( 0 ) ) {
				hideRoleModal();
			}
		} );

		$( document ).on( 'keydown', function( e ) {
			if ( e.key !== 'Escape' ) {
				return;
			}
			var $sellerIntro = $( '#cpm-hb-seller-pod-intro-modal' );
			if ( $sellerIntro.length && ! $sellerIntro.hasClass( 'cpm-nwp-modal--hidden' ) ) {
				$sellerIntro.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				showRoleModal();
				return;
			}
			if ( $roleModal.hasClass( 'active' ) ) {
				hideRoleModal();
				return;
			}
			if ( $modal.hasClass( 'active' ) ) {
				dismissLandingModal();
			}
		} );

		/* Tap dimmed overlay (outside the card) to close — works when overlay has padding around the shell */
		$modal.on( 'click', function( e ) {
			if ( e.target === $modal.get( 0 ) ) {
				dismissLandingModal();
			}
		} );

		$( '#cpm-hb-landing-entry-modal .cpm-hb-entry-overlay-shell' ).on( 'click', function( e ) {
			if ( e.target === this ) {
				dismissLandingModal();
			}
		} );

		$( '#cpm-hb-landing-dismiss' ).on( 'click', function( e ) {
			e.preventDefault();
			dismissLandingModal();
		} );

		/* Delegated handler + real <a href> in markup (progressive enhancement for mobile WebViews). */
		function appendSkipGateParam( href ) {
			try {
				var u = new URL( href, window.location.href );
				u.searchParams.set( 'cpm_hb_skip_gate', '1' );
				return u.toString();
			} catch ( err ) {
				var join = href.indexOf( '?' ) >= 0 ? '&' : '?';
				return href + join + 'cpm_hb_skip_gate=1';
			}
		}

		function goLandingHome( e ) {
			if ( e && e.preventDefault ) {
				e.preventDefault();
			}
			dismissLandingModal();
			var $a = $( '#cpm-hb-landing-home' );
			var u = ( window.cpmHbLanding && window.cpmHbLanding.homeUrl ) ? window.cpmHbLanding.homeUrl : ( $a.attr( 'href' ) || '/' );
			window.location.assign( appendSkipGateParam( u ) );
		}
		$( document ).on( 'click', '#cpm-hb-landing-home', goLandingHome );

		syncUiFromState();
	} );
})( jQuery );
