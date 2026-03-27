(function( $ ) {
	'use strict';

	$( function() {
		var $modal = $( '#cpm-hb-landing-entry-modal' );
		var $roleModal = $( '#cpm-hb-role-modal' );
		if ( ! $modal.length || ! window.cpmHbLanding ) {
			return;
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
			$modal.removeClass( 'active' );
			$( 'body' ).removeClass( 'cpm-hb-landing-entry-active' );
		}

		function showRoleModal() {
			if ( ! $roleModal.length ) {
				return;
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

		function bothPromptsYes() {
			return state.proof === 'yes' && state.final === 'yes';
		}

		function clearNwpFeedback( $el ) {
			if ( ! $el.length ) {
				return;
			}
			$el.removeClass( 'cpm-nwp-inline-feedback--success cpm-nwp-inline-feedback--error' ).addClass( 'cpm-nwp-inline-feedback--hidden' ).empty();
		}

		/**
		 * After role selection: open the same “Your Phone Number” modal used by NWP, then Send OTP → verify OTP (public.js).
		 * pendingOtpRedirect sends the user to PoD URL after successful verification when the server does not return redirect_url.
		 */
		function showPhoneOtpModal() {
			var $activate = $( '#cpm-nwp-activate-modal' );
			if ( ! $activate.length ) {
				if ( window.cpmHbLanding.proofOfDeliveryUrl ) {
					window.location.href = window.cpmHbLanding.proofOfDeliveryUrl;
				}
				return;
			}
			window.cpmHbLanding.pendingOtpRedirect = window.cpmHbLanding.proofOfDeliveryUrl || '';
			window.cpmHbLanding.phoneModalFromLanding = true;

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
			if ( bothPromptsYes() ) {
				showRoleModal();
				return;
			}
			persistScanBasic();
		} );

		$( '#cpm-hb-role-continue' ).on( 'click', function() {
			persistScanWithRole();
			hideRoleModal();
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
			}
		} );

		$( '#cpm-hb-landing-home' ).on( 'click', function() {
			dismissLandingModal();
		} );

		syncUiFromState();
	} );
})( jQuery );
