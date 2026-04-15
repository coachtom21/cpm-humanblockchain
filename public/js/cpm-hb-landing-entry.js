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

		function urlHasProofScan() {
			try {
				return new URLSearchParams( window.location.search ).get( 'proof' ) === 'scan';
			} catch ( err ) {
				return false;
			}
		}

		/**
		 * After role selection: open the same “Your Phone Number” modal used by NWP, then Send OTP → verify OTP (public.js).
		 * Buyer + ?proof=scan: server checks wp_nwp_devices and Smallstreet backorders-by-mobile before OTP.
		 * pendingOtpRedirect sends the user to PoD URL after successful verification when the server does not return redirect_url.
		 *
		 * @param {{ buyerProofScan?: boolean }} opts
		 */
		function showPhoneOtpModal( opts ) {
			opts = opts || {};
			var H = window.cpmHbLanding || {};
			var $activate = $( '#cpm-nwp-activate-modal' );
			if ( ! $activate.length ) {
				if ( H.proofOfDeliveryUrl ) {
					window.location.href = H.proofOfDeliveryUrl;
				}
				return;
			}
			// Fallback if AJAX omits redirect_url: backorders only for ?proof=scan buyer flow; else home.
			if ( opts.buyerProofScan ) {
				H.pendingOtpRedirect = H.proofOfDeliveryUrl || '';
			} else {
				H.pendingOtpRedirect = H.homeUrl || '/';
			}
			H.phoneModalFromLanding = true;
			H.buyerProofScan = !! opts.buyerProofScan;
			H.landingRole = opts.buyerProofScan ? 'buyer' : 'seller';

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
			if ( role === 'buyer' && urlHasProofScan() ) {
				showPhoneOtpModal( { buyerProofScan: true } );
				return;
			}
			if ( role === 'buyer' ) {
				var H = window.cpmHbLanding || {};
				window.location.href = H.proofOfDeliveryUrl || '/';
				return;
			}
			showPhoneOtpModal();
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
