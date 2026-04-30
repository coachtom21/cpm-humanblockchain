(function( $ ) {
	'use strict';

	$( function() {
		var $registerFeedback = $( '#cpm-nwp-register-feedback' );
		var $activateFeedback = $( '#cpm-nwp-activate-feedback' );
		var $verifyFeedback = $( '#cpm-nwp-verify-feedback' );
		var $activateModal = $( '#cpm-nwp-activate-modal' );
		var $verifyModal = $( '#cpm-nwp-verify-otp-modal' );
		var $discordModal = $( '#cpm-nwp-discord-modal' );
		var $sellerScanSuccessModal = $( '#cpm-hb-seller-scan-success-modal' );

		/**
		 * Country select + national digits → E.164 (server: Cpm_Humanblockchain_Otp_Service::normalize_phone_e164).
		 */
		function cpmNwpBuildMobileE164( countrySelector, nationalSelector ) {
			var $sel = $( countrySelector );
			var $nat = $( nationalSelector );
			if ( ! $sel.length || ! $nat.length ) {
				return '';
			}
			var dial = String( $sel.find( 'option:selected' ).attr( 'data-dial' ) || '1' );
			var d = ( $nat.val() || '' ).replace( /\D/g, '' );
			if ( ! d ) {
				return '';
			}
			if ( dial === '1' ) {
				if ( d.length > 10 && d.charAt( 0 ) === '1' ) {
					d = d.substring( 1 );
				}
				if ( d.length > 10 ) {
					d = d.substring( 0, 10 );
				}
				return d.length ? ( '+1' + d ) : '';
			}
			if ( dial === '977' ) {
				if ( d.length > 10 ) {
					d = d.substring( 0, 10 );
				}
				return '+977' + d;
			}
			if ( dial === '44' ) {
				d = d.replace( /^0+/, '' );
				return d.length ? ( '+44' + d ) : '';
			}
			if ( dial === '91' || dial === '61' ) {
				return '+' + dial + d;
			}
			return '+' + dial + d;
		}

		function cpmNwpBuildRegisterMobileE164() {
			return cpmNwpBuildMobileE164( '#cpm-nwp-phone-country', '#cpm-nwp-mobile-national' );
		}

		function cpmNwpBuildActivateMobileE164() {
			return cpmNwpBuildMobileE164( '#cpm-nwp-activate-phone-country', '#cpm-nwp-activate-mobile-national' );
		}

		var cpmNwpDeviceLookupTimerReg = null;
		var cpmNwpDeviceLookupTimerAct = null;
		var cpmNwpApplyCountrySkip = { register: false, activate: false };

		function cpmNwpGetLookupMinDigits() {
			var n = window.cpmNwp && window.cpmNwp.lookupMinNationalDigits;
			return typeof n === 'number' && n > 0 ? n : 7;
		}

		function cpmNwpSetLookupHint( mode, text ) {
			if ( mode === 'activate' ) {
				$( '#cpm-nwp-activate-lookup-hint' ).text( text || '' );
			} else {
				$( '#cpm-nwp-register-lookup-hint' ).text( text || '' );
			}
		}

		function cpmNwpRunDevicePhoneLookup( mode ) {
			if ( cpmNwpApplyCountrySkip[ mode ] ) {
				return;
			}
			var e164 = mode === 'activate' ? cpmNwpBuildActivateMobileE164() : cpmNwpBuildRegisterMobileE164();
			var nat = ( mode === 'activate'
				? $( '#cpm-nwp-activate-mobile-national' )
				: $( '#cpm-nwp-mobile-national' )
			).val() || '';
			var natD = nat.replace( /\D/g, '' );
			if ( natD.length < cpmNwpGetLookupMinDigits() || ! e164 || e164.length < 4 ) {
				cpmNwpSetLookupHint( mode, '' );
				return;
			}
			var N = window.cpmNwp || {};
			if ( ! N.lookupDeviceAction || ! N.lookupDeviceNonce || ! N.ajaxUrl ) {
				return;
			}
			$.ajax( {
				url: N.ajaxUrl,
				type: 'POST',
				data: {
					action: N.lookupDeviceAction,
					cpm_nwp_lookup_nonce: N.lookupDeviceNonce,
					mobile: e164
				},
				dataType: 'json'
			} )
				.done( function( res ) {
					if ( ! res || ! res.success || ! res.data ) {
						cpmNwpSetLookupHint( mode, '' );
						return;
					}
					var d = res.data;
					if ( ! d.found || ! d.phone_country ) {
						cpmNwpSetLookupHint( mode, '' );
						return;
					}
					var iso = d.phone_country;
					var $sel = mode === 'activate' ? $( '#cpm-nwp-activate-phone-country' ) : $( '#cpm-nwp-phone-country' );
					var $opt = $sel.find( 'option[value="' + iso + '"]' );
					if ( ! $sel.length || ! $opt.length ) {
						cpmNwpSetLookupHint( mode, '' );
						return;
					}
					var hint = ( N.phoneLookup && N.phoneLookup.matched ) ? N.phoneLookup.matched : '';
					if ( $sel.val() !== iso ) {
						cpmNwpApplyCountrySkip[ mode ] = true;
						$sel.val( iso );
						$sel.trigger( 'change' );
						if ( mode === 'activate' ) {
							$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
						} else {
							$( '#cpm-nwp-mobile-e164' ).val( cpmNwpBuildRegisterMobileE164() );
						}
						cpmNwpSetLookupHint( mode, hint );
						setTimeout( function() { cpmNwpApplyCountrySkip[ mode ] = false; }, 50 );
					} else {
						cpmNwpSetLookupHint( mode, hint );
					}
				} )
				.fail( function() { cpmNwpSetLookupHint( mode, '' ); } );
		}

		function cpmNwpScheduleDevicePhoneLookup( mode ) {
			if ( cpmNwpApplyCountrySkip[ mode ] ) {
				return;
			}
			if ( mode === 'activate' ) {
				clearTimeout( cpmNwpDeviceLookupTimerAct );
				cpmNwpDeviceLookupTimerAct = setTimeout( function() { cpmNwpRunDevicePhoneLookup( 'activate' ); }, 420 );
			} else {
				clearTimeout( cpmNwpDeviceLookupTimerReg );
				cpmNwpDeviceLookupTimerReg = setTimeout( function() { cpmNwpRunDevicePhoneLookup( 'register' ); }, 420 );
			}
		}

		function cpmNwpInitRegisterPhoneFields() {
			if ( ! $( '#cpm-nwp-phone-country' ).length ) {
				return;
			}
			if ( window.cpmNwp && window.cpmNwp.registerPhoneDefaultIso ) {
				$( '#cpm-nwp-phone-country' ).val( window.cpmNwp.registerPhoneDefaultIso );
			}
			cpmNwpSetLookupHint( 'register', '' );
			$( '#cpm-nwp-mobile-e164' ).val( cpmNwpBuildRegisterMobileE164() );
		}

		function cpmNwpInitActivatePhoneFields() {
			if ( ! $( '#cpm-nwp-activate-phone-country' ).length ) {
				return;
			}
			if ( window.cpmNwp && window.cpmNwp.registerPhoneDefaultIso ) {
				$( '#cpm-nwp-activate-phone-country' ).val( window.cpmNwp.registerPhoneDefaultIso );
			}
			$( '#cpm-nwp-activate-mobile-national' ).val( '' );
			cpmNwpSetLookupHint( 'activate', '' );
			$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
		}
		window.cpmNwpInitActivatePhoneFields = cpmNwpInitActivatePhoneFields;

		$( document ).on( 'input change', '#cpm-nwp-phone-country, #cpm-nwp-mobile-national', function() {
			$( '#cpm-nwp-mobile-e164' ).val( cpmNwpBuildRegisterMobileE164() );
			cpmNwpScheduleDevicePhoneLookup( 'register' );
		} );
		$( document ).on( 'input change', '#cpm-nwp-activate-phone-country, #cpm-nwp-activate-mobile-national', function() {
			$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
			cpmNwpScheduleDevicePhoneLookup( 'activate' );
		} );

		function closeSellerScanSuccessModal() {
			if ( ! $sellerScanSuccessModal.length ) {
				return;
			}
			$sellerScanSuccessModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( '#cpm-hb-seller-tx-copy-feedback' ).empty();
			if ( $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden)' ).length === 0 ) {
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			}
		}

		function showSellerScanSuccessModal( transactionCode ) {
			if ( ! $sellerScanSuccessModal.length ) {
				return;
			}
			var code = transactionCode || '';
			$( '#cpm-hb-seller-tx-code-display' ).text( code );
			$sellerScanSuccessModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-nwp-modal-open' );
		}

		/** Called from landing-entry.js after logged-in seller PoD AJAX (no OTP). */
		window.cpmHbShowSellerPodSuccess = showSellerScanSuccessModal;

		function closeDiscordModalAndRefresh() {
			$discordModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			window.location.reload();
		}

		function showInlineFeedback( $box, message, type ) {
			if ( ! $box.length ) {
				return;
			}
			$box.removeClass( 'cpm-nwp-inline-feedback--hidden cpm-nwp-inline-feedback--success cpm-nwp-inline-feedback--error' );
			if ( ! message ) {
				$box.addClass( 'cpm-nwp-inline-feedback--hidden' ).empty();
				return;
			}
			$box.addClass( 'cpm-nwp-inline-feedback--' + ( type === 'success' ? 'success' : 'error' ) );
			$box.text( message );
		}

		function clearInlineFeedback( $box ) {
			showInlineFeedback( $box, '', '' );
		}

		/**
		 * Merge cpmNwp ?proof=scan data onto cpmHbLanding so OTP always sends nonces/role (landing-entry may not be enqueued; showPhoneOtpModal used to write to a throwaway {}).
		 */
		function ensureHbLandingFromNwp() {
			var N = window.cpmNwp || {};
			if ( ! window.cpmHbLanding ) {
				window.cpmHbLanding = {};
			}
			var H = window.cpmHbLanding;
			if ( N.proofScanNonce && ! H.proofScanNonce ) {
				H.proofScanNonce = N.proofScanNonce;
			}
			if ( N.proofOfDeliveryUrl && ! H.proofOfDeliveryUrl ) {
				H.proofOfDeliveryUrl = N.proofOfDeliveryUrl;
			}
			if ( N.hasProofScan != null && H.hasProofScan == null ) {
				H.hasProofScan = N.hasProofScan;
			}
		}

		/**
		 * If the page was loaded with ?proof=scan but the user registered a device first, re-apply PoD context before Send OTP / verify.
		 */
		/**
		 * If landing lost landingRole (e.g. only sessionStorage has it), re-read from hb_last_scan so Send OTP / Verify still post cpm_hb_user_role.
		 */
		function cpmNwpRefreshLandingRoleFromSessionStorage() {
			if ( ! window.cpmHbLanding ) {
				window.cpmHbLanding = {};
			}
			var H = window.cpmHbLanding;
			if ( H.landingRole === 'seller' || H.landingRole === 'buyer' ) {
				return;
			}
			try {
				var raw = window.sessionStorage ? window.sessionStorage.getItem( 'hb_last_scan' ) : '';
				if ( ! raw ) {
					return;
				}
				var j = JSON.parse( raw );
				if ( j && ( j.user_role === 'seller' || j.user_role === 'buyer' ) ) {
					H.landingRole = j.user_role;
				}
			} catch ( err ) {
				// ignore
			}
		}

		function applyProofScanContextIfNeeded() {
			if ( window.cpmHbSkipPodOtpContext ) {
				return;
			}
			var N = window.cpmNwp || {};
			ensureHbLandingFromNwp();
			var H = window.cpmHbLanding || {};
			if ( H.phoneModalFromLanding ) {
				return;
			}
			if ( ! N.hasProofScan || ! N.proofScanNonce ) {
				return;
			}
			var role = 'buyer';
			try {
				var raw = sessionStorage.getItem( 'hb_last_scan' );
				if ( raw ) {
					var j = JSON.parse( raw );
					if ( j.user_role === 'seller' || j.user_role === 'buyer' ) {
						role = j.user_role;
					}
				}
			} catch ( err ) {
				// ignore
			}
			H.phoneModalFromLanding = true;
			H.podProofScan = true;
			H.landingRole = role;
			H.buyerProofScan = role === 'buyer';
			H.pendingOtpRedirect = role === 'buyer' ? ( N.proofOfDeliveryUrl || H.proofOfDeliveryUrl || '' ) : '';
		}

		function getReferralFromUrl() {
			var params = new URLSearchParams( window.location.search );
			return params.get( 'issuer' ) || params.get( 'referrer' ) || params.get( 'ref' ) || params.get( 'nwp_issuer' ) || '';
		}

		$( document ).on( 'click', '.cpm-nwp-open-modal', function( e ) {
			e.preventDefault();
			var modalId = $( this ).data( 'cpm-modal' ) || 'cpm-nwp-register-modal';
			var $modal = $( '#' + modalId );
			if ( $modal.length ) {
				var referralId = getReferralFromUrl();
				$( '#cpm-nwp-referral-id' ).val( referralId );
				clearInlineFeedback( $registerFeedback );
				$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				clearInlineFeedback( $verifyFeedback );
				$discordModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				$modal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
				$( 'body' ).addClass( 'cpm-nwp-modal-open' );
				cpmNwpInitRegisterPhoneFields();
			}
		} );

		function closeVerifyShowActivate() {
			$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $verifyFeedback );
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
		}

		function showActivateModalAfterRegistration( $form ) {
			if ( window.cpmNwp && window.cpmNwp.hasProofScan && window.cpmNwp.proofScanNonce ) {
				window.cpmHbSkipPodOtpContext = false;
			}
			applyProofScanContextIfNeeded();
			var regIso = $( '#cpm-nwp-phone-country' ).val() || '';
			var regNat = $( '#cpm-nwp-mobile-national' ).val() || '';
			$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $verifyFeedback );
			$discordModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $registerFeedback );
			$( '#cpm-nwp-register-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$form[0].reset();
			cpmNwpInitRegisterPhoneFields();
			clearInlineFeedback( $activateFeedback );
			if ( $( '#cpm-nwp-activate-phone-country' ).length ) {
				cpmNwpInitActivatePhoneFields();
				if ( regIso ) {
					$( '#cpm-nwp-activate-phone-country' ).val( regIso );
				}
				$( '#cpm-nwp-activate-mobile-national' ).val( regNat );
				$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
				if ( ( regNat || '' ).replace( /\D/g, '' ).length >= cpmNwpGetLookupMinDigits() ) {
					cpmNwpRunDevicePhoneLookup( 'activate' );
				}
			}
			window.cpmNwpActivateFromRegisterSuccess = true;
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-nwp-modal-open' );
			showInlineFeedback( $activateFeedback, 'Device registered. Send OTP to verify your phone.', 'success' );
			$( '#cpm-nwp-activate-mobile-national' ).trigger( 'focus' );
		}

		$( document ).on( 'click', '.cpm-nwp-modal-close, #cpm-nwp-register-modal .cpm-nwp-modal-overlay', function( e ) {
			var $clickedModal = $( e.target ).closest( '.cpm-nwp-modal' );
			$clickedModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			if ( $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden)' ).length === 0 ) {
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			}
		} );

		$( document ).on( 'click', '.cpm-nwp-activate-close', function( e ) {
			var $clickedModal = $( e.target ).closest( '.cpm-nwp-modal' );
			if ( $clickedModal.attr( 'id' ) === 'cpm-nwp-discord-modal' ) {
				closeDiscordModalAndRefresh();
				return;
			}
			if ( $clickedModal.attr( 'id' ) === 'cpm-nwp-verify-otp-modal' ) {
				closeVerifyShowActivate();
				return;
			}
			if ( $clickedModal.attr( 'id' ) === 'cpm-nwp-activate-modal' && window.cpmNwpActivateFromRegisterSuccess ) {
				$clickedModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				clearInlineFeedback( $activateFeedback );
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				window.cpmNwpActivateFromRegisterSuccess = false;
				return;
			}
			if ( $clickedModal.attr( 'id' ) === 'cpm-nwp-activate-modal' && window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding ) {
				$clickedModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				clearInlineFeedback( $activateFeedback );
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				window.cpmHbLanding.phoneModalFromLanding = false;
				window.cpmHbLanding.pendingOtpRedirect = '';
				window.cpmHbLanding.buyerProofScan = false;
				window.cpmHbLanding.podProofScan = false;
				window.cpmHbLanding.landingRole = '';
				return;
			}
			$clickedModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			if ( $clickedModal.attr( 'id' ) === 'cpm-nwp-activate-modal' ) {
				$( '#cpm-nwp-register-modal' ).removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
				clearInlineFeedback( $activateFeedback );
			}
			if ( $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden)' ).length === 0 ) {
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
			}
		} );

		$( document ).on( 'click', '#cpm-nwp-activate-modal .cpm-nwp-modal-overlay', function() {
			if ( window.cpmNwpActivateFromRegisterSuccess ) {
				$activateModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				clearInlineFeedback( $activateFeedback );
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				window.cpmNwpActivateFromRegisterSuccess = false;
				return;
			}
			if ( window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding ) {
				$activateModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				clearInlineFeedback( $activateFeedback );
				$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				window.cpmHbLanding.phoneModalFromLanding = false;
				window.cpmHbLanding.pendingOtpRedirect = '';
				window.cpmHbLanding.buyerProofScan = false;
				window.cpmHbLanding.podProofScan = false;
				window.cpmHbLanding.landingRole = '';
				return;
			}
			$activateModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( '#cpm-nwp-register-modal' ).removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
			clearInlineFeedback( $activateFeedback );
		} );

		$( document ).on( 'click', '#cpm-nwp-verify-otp-modal .cpm-nwp-modal-overlay', function() {
			closeVerifyShowActivate();
		} );

		$( document ).on( 'click', '#cpm-nwp-discord-modal .cpm-nwp-modal-overlay', function() {
			closeDiscordModalAndRefresh();
		} );

		$( document ).on( 'click', '.cpm-nwp-discord-continue', function() {
			closeDiscordModalAndRefresh();
		} );

		function finishSellerScanSuccess() {
			window.location.reload();
		}

		$( document ).on( 'click', '#cpm-hb-seller-tx-copy', function() {
			var text = ( $( '#cpm-hb-seller-tx-code-display' ).text() || '' ).trim();
			var $fb = $( '#cpm-hb-seller-tx-copy-feedback' );
			function showCopied() {
				$fb.text( 'Copied!' );
			}
			function copyFallback() {
				var ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'fixed';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				try {
					if ( document.execCommand( 'copy' ) ) {
						showCopied();
					}
				} catch ( err ) {
					$fb.empty();
				}
				document.body.removeChild( ta );
			}
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( showCopied ).catch( copyFallback );
			} else {
				copyFallback();
			}
		} );

		$( document ).on( 'click', '#cpm-hb-seller-scan-done, #cpm-hb-seller-scan-success-close, #cpm-hb-seller-scan-success-modal .cpm-nwp-modal-overlay', function() {
			finishSellerScanSuccess();
		} );

		$( document ).on( 'keydown', function( e ) {
			if ( e.key !== 'Escape' ) {
				return;
			}
			if ( $sellerScanSuccessModal.length && ! $sellerScanSuccessModal.hasClass( 'cpm-nwp-modal--hidden' ) ) {
				finishSellerScanSuccess();
				return;
			}
			if ( ! $discordModal.hasClass( 'cpm-nwp-modal--hidden' ) ) {
				closeDiscordModalAndRefresh();
				return;
			}
			if ( ! $verifyModal.hasClass( 'cpm-nwp-modal--hidden' ) ) {
				closeVerifyShowActivate();
				return;
			}
			var $visibleModal = $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden)' ).last();
			if ( $visibleModal.length ) {
				$visibleModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
				if ( $visibleModal.attr( 'id' ) === 'cpm-nwp-activate-modal' ) {
					if ( window.cpmNwpActivateFromRegisterSuccess ) {
						clearInlineFeedback( $activateFeedback );
						$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
						window.cpmNwpActivateFromRegisterSuccess = false;
					} else if ( window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding ) {
						clearInlineFeedback( $activateFeedback );
						$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
						window.cpmHbLanding.phoneModalFromLanding = false;
						window.cpmHbLanding.pendingOtpRedirect = '';
						window.cpmHbLanding.buyerProofScan = false;
						window.cpmHbLanding.podProofScan = false;
						window.cpmHbLanding.landingRole = '';
					} else {
						$( '#cpm-nwp-register-modal' ).removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
						clearInlineFeedback( $activateFeedback );
					}
				}
				if ( $( '.cpm-nwp-modal:not(.cpm-nwp-modal--hidden)' ).length === 0 ) {
					$( 'body' ).removeClass( 'cpm-nwp-modal-open' );
				}
			}
		} );

		$( document ).on( 'click', '.cpm-nwp-open-activate-modal', function( e ) {
			e.preventDefault();
			// “Activate device” from the register modal (not PoD) must not inherit ?proof=scan (Smallstreet / buyer checks).
			var fromRegister = $( e.currentTarget ).hasClass( 'cpm-nwp-open-activate-modal--from-register' );
			if ( fromRegister && window.cpmHbLanding ) {
				window.cpmHbSkipPodOtpContext = true;
				window.cpmHbLanding.buyerProofScan = false;
				window.cpmHbLanding.phoneModalFromLanding = false;
				window.cpmHbLanding.podProofScan = false;
				window.cpmHbLanding.landingRole = '';
				window.cpmHbLanding.pendingOtpRedirect = '';
			}
			clearInlineFeedback( $registerFeedback );
			$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $verifyFeedback );
			$discordModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$( '#cpm-nwp-register-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $activateFeedback );
			if ( $( '#cpm-nwp-activate-phone-country' ).length ) {
				var copyIso = $( '#cpm-nwp-phone-country' ).val() || '';
				var copyNat = $( '#cpm-nwp-mobile-national' ).val() || '';
				cpmNwpInitActivatePhoneFields();
				if ( ( copyNat || '' ).replace( /\D/g, '' ).length ) {
					if ( copyIso ) {
						$( '#cpm-nwp-activate-phone-country' ).val( copyIso );
					}
					$( '#cpm-nwp-activate-mobile-national' ).val( copyNat );
					$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
					cpmNwpRunDevicePhoneLookup( 'activate' );
				}
			}
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
		} );

		$( document ).on( 'submit', '#cpm-nwp-activate-form', function( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );
			$( '#cpm-nwp-activate-mobile-e164' ).val( cpmNwpBuildActivateMobileE164() );
			var mobile = $( '#cpm-nwp-activate-mobile-e164' ).val() || '';
			var pe = window.cpmNwp && window.cpmNwp.phoneErrors ? window.cpmNwp.phoneErrors : {};
			var shortMsg = pe.short || 'Please enter a valid mobile number (choose country, then at least 8 local digits).';
			var e164Digits = mobile.replace( /\D/g, '' );
			if ( ! mobile || e164Digits.length < 8 ) {
				showInlineFeedback( $activateFeedback, shortMsg, 'error' );
				return false;
			}
			var natD = ( $( '#cpm-nwp-activate-mobile-national' ).val() || '' ).replace( /\D/g, '' );
			var dial = String( $( '#cpm-nwp-activate-phone-country' ).find( 'option:selected' ).attr( 'data-dial' ) || '1' );
			if ( dial === '977' && natD.length === 11 && /^9[78]/.test( natD ) ) {
				showInlineFeedback( $activateFeedback, pe.npElevenDigits || 'Nepal: enter 10 digits (mobile) without the country code.', 'error' );
				return false;
			}
			clearInlineFeedback( $activateFeedback );
			applyProofScanContextIfNeeded();
			ensureHbLandingFromNwp();
			cpmNwpRefreshLandingRoleFromSessionStorage();
			if ( window.cpmHbLanding && window.cpmNwp && window.cpmNwp.hasProofScan && ! window.cpmHbSkipPodOtpContext
				&& ( window.cpmHbLanding.landingRole === 'seller' || window.cpmHbLanding.landingRole === 'buyer' ) ) {
				window.cpmHbLanding.phoneModalFromLanding = true;
				window.cpmHbLanding.podProofScan = true;
				if ( window.cpmHbLanding.landingRole === 'buyer' ) {
					window.cpmHbLanding.buyerProofScan = true;
				}
				if ( ! window.cpmHbLanding.proofScanNonce && window.cpmNwp.proofScanNonce ) {
					window.cpmHbLanding.proofScanNonce = window.cpmNwp.proofScanNonce;
				}
			}
			$btn.prop( 'disabled', true ).text( 'Sending...' );
			var formData = $form.serialize() + '&action=' + ( window.cpmNwp && window.cpmNwp.sendOtpAction ? window.cpmNwp.sendOtpAction : 'cpm_nwp_send_otp' );
			if ( window.cpmHbLanding && window.cpmHbLanding.buyerProofScan ) {
				formData += '&cpm_hb_buyer_proof_scan=1';
			}
			if ( window.cpmHbLanding && ( window.cpmHbLanding.buyerProofScan || window.cpmHbLanding.podProofScan ) ) {
				formData += '&cpm_hb_proof_scan=1';
				var psn = ( window.cpmHbLanding && window.cpmHbLanding.proofScanNonce ) || ( window.cpmNwp && window.cpmNwp.proofScanNonce ) || '';
				if ( psn ) {
					formData += '&cpm_hb_proof_scan_nonce=' + encodeURIComponent( psn );
				}
			}
			if ( window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding && window.cpmHbLanding.landingRole ) {
				formData += '&cpm_hb_user_role=' + encodeURIComponent( window.cpmHbLanding.landingRole );
			}
			var ajaxUrl = ( window.cpmNwp && window.cpmNwp.ajaxUrl )
				? window.cpmNwp.ajaxUrl
				: ( typeof window.ajaxurl === 'string' && window.ajaxurl ? window.ajaxurl : '' );
			if ( ! ajaxUrl ) {
				showInlineFeedback( $activateFeedback, 'Cannot reach the site (missing AJAX URL). Reload the page.', 'error' );
				$btn.prop( 'disabled', false ).text( 'Send OTP' );
				return false;
			}
			$.ajax( {
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				dataType: 'json'
			} )
				.done( function( res ) {
					if ( res && res.success && res.data && res.data.message ) {
						$( '#cpm-nwp-verify-mobile' ).val( mobile );
						$( '#cpm-nwp-verify-phone-country' ).val( $( '#cpm-nwp-activate-phone-country' ).val() || '' );
						$( '#cpm-nwp-verify-otp-input' ).val( '' );
						$activateModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
						clearInlineFeedback( $activateFeedback );
						$verifyModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
						showInlineFeedback( $verifyFeedback, res.data.message, 'success' );
						$( '#cpm-nwp-verify-otp-input' ).trigger( 'focus' );
					} else {
						var err = ( res && res.data && res.data.message ) ? res.data.message : 'Request failed.';
						showInlineFeedback( $activateFeedback, err, 'error' );
					}
				} )
				.fail( function( xhr ) {
					var msg = 'Request failed. Please check your connection and try again.';
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						msg = xhr.responseJSON.data.message;
					}
					showInlineFeedback( $activateFeedback, msg, 'error' );
				} )
				.always( function() {
					$btn.prop( 'disabled', false ).text( 'Send OTP' );
				} );
			return false;
		} );

		$( document ).on( 'submit', '#cpm-nwp-verify-otp-form', function( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );
			var mobile = $( '#cpm-nwp-verify-mobile' ).val();
			var otp = $( '#cpm-nwp-verify-otp-input' ).val().replace( /\D/g, '' );
			if ( otp.length !== 6 ) {
				showInlineFeedback( $verifyFeedback, 'Please enter the 6-digit code from your SMS.', 'error' );
				return false;
			}
			clearInlineFeedback( $verifyFeedback );
			applyProofScanContextIfNeeded();
			ensureHbLandingFromNwp();
			cpmNwpRefreshLandingRoleFromSessionStorage();
			// Seller / buyer ?proof=scan: ensure landing flags so verify always posts cpm_hb_verify_redirect + proof nonce (server needs these for transaction code + XP API).
			if ( window.cpmHbLanding && window.cpmNwp && window.cpmNwp.hasProofScan && ! window.cpmHbSkipPodOtpContext
				&& ( window.cpmHbLanding.landingRole === 'seller' || window.cpmHbLanding.landingRole === 'buyer' ) ) {
				window.cpmHbLanding.phoneModalFromLanding = true;
				window.cpmHbLanding.podProofScan = true;
				if ( window.cpmHbLanding.landingRole === 'buyer' ) {
					window.cpmHbLanding.buyerProofScan = true;
				}
				if ( ! window.cpmHbLanding.proofScanNonce && window.cpmNwp.proofScanNonce ) {
					window.cpmHbLanding.proofScanNonce = window.cpmNwp.proofScanNonce;
				}
			}
			$btn.prop( 'disabled', true ).text( 'Verifying...' );
			var payload = $form.serialize() + '&action=' + ( window.cpmNwp && window.cpmNwp.verifyOtpAction ? window.cpmNwp.verifyOtpAction : 'cpm_nwp_verify_otp' );
			if ( window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding ) {
				payload += '&cpm_hb_verify_redirect=1';
			}
			if ( window.cpmHbLanding && window.cpmHbLanding.buyerProofScan ) {
				payload += '&cpm_hb_buyer_proof_scan=1';
			}
			if ( window.cpmHbLanding && ( window.cpmHbLanding.buyerProofScan || window.cpmHbLanding.podProofScan ) ) {
				payload += '&cpm_hb_proof_scan=1';
				var pvn = ( window.cpmHbLanding && window.cpmHbLanding.proofScanNonce ) || ( window.cpmNwp && window.cpmNwp.proofScanNonce ) || '';
				if ( pvn ) {
					payload += '&cpm_hb_proof_scan_nonce=' + encodeURIComponent( pvn );
				}
			}
			if ( window.cpmHbLanding && window.cpmHbLanding.phoneModalFromLanding && window.cpmHbLanding.landingRole ) {
				payload += '&cpm_hb_user_role=' + encodeURIComponent( window.cpmHbLanding.landingRole );
			}
			var verifyAjaxUrl = ( window.cpmNwp && window.cpmNwp.ajaxUrl )
				? window.cpmNwp.ajaxUrl
				: ( typeof window.ajaxurl === 'string' && window.ajaxurl ? window.ajaxurl : '' );
			if ( ! verifyAjaxUrl ) {
				showInlineFeedback( $verifyFeedback, 'Cannot reach the site (missing AJAX URL). Reload the page.', 'error' );
				$btn.prop( 'disabled', false ).text( 'Verify & continue' );
				return false;
			}
			$.ajax( {
				url: verifyAjaxUrl,
				type: 'POST',
				data: payload,
				dataType: 'json'
			} )
				.done( function( res ) {
					if ( res && res.success && res.data ) {
						if ( res.data.redirect_url ) {
							if ( res.data.smallstreet_backorders != null ) {
								try {
									sessionStorage.setItem(
										'cpm_hb_smallstreet_backorders',
										JSON.stringify( res.data.smallstreet_backorders )
									);
								} catch ( err ) {
									// ignore
								}
							}
							if ( window.cpmHbLanding ) {
								window.cpmHbLanding.buyerProofScan = false;
								window.cpmHbLanding.podProofScan = false;
								window.cpmHbLanding.landingRole = '';
							}
							window.location.href = res.data.redirect_url;
							return;
						}
						if ( res.data.seller_scan_success && res.data.seller_transaction_code ) {
							$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
							clearInlineFeedback( $verifyFeedback );
							if ( window.cpmHbLanding ) {
								window.cpmHbLanding.buyerProofScan = false;
								window.cpmHbLanding.podProofScan = false;
								window.cpmHbLanding.phoneModalFromLanding = false;
								window.cpmHbLanding.pendingOtpRedirect = '';
								window.cpmHbLanding.landingRole = '';
							}
							showSellerScanSuccessModal( res.data.seller_transaction_code );
							return;
						}
						// PoD buyer path: must run before Discord — otherwise pendingOtpRedirect is cleared and users never reach backorders.
						if ( window.cpmHbLanding && window.cpmHbLanding.pendingOtpRedirect ) {
							var postUrl = window.cpmHbLanding.pendingOtpRedirect;
							window.cpmHbLanding.pendingOtpRedirect = '';
							window.cpmHbLanding.phoneModalFromLanding = false;
							window.cpmHbLanding.buyerProofScan = false;
							window.cpmHbLanding.podProofScan = false;
							window.cpmHbLanding.landingRole = '';
							window.location.href = postUrl;
							return;
						}
						if ( res.data.show_discord_modal ) {
							$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
							clearInlineFeedback( $verifyFeedback );
							if ( window.cpmHbLanding ) {
								window.cpmHbLanding.pendingOtpRedirect = '';
							}
							if ( window.cpmNwp && window.cpmNwp.discordInviteUrl ) {
								$( '#cpm-nwp-discord-join-link' ).attr( 'href', window.cpmNwp.discordInviteUrl );
							}
							$discordModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
							$( 'body' ).addClass( 'cpm-nwp-modal-open' );
							return;
						}
						window.location.reload();
					} else {
						showInlineFeedback( $verifyFeedback, ( res && res.data && res.data.message ) ? res.data.message : 'Verification failed.', 'error' );
					}
				} )
				.fail( function( xhr ) {
					var vmsg = 'Request failed. Please try again.';
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data ) {
						if ( typeof xhr.responseJSON.data === 'string' ) {
							vmsg = xhr.responseJSON.data;
						} else if ( xhr.responseJSON.data.message ) {
							vmsg = xhr.responseJSON.data.message;
						}
					}
					showInlineFeedback( $verifyFeedback, vmsg, 'error' );
				} )
				.always( function() {
					$btn.prop( 'disabled', false ).text( 'Verify & continue' );
				} );
			return false;
		} );

		$( document ).on( 'input', '#cpm-nwp-verify-otp-input', function() {
			var v = $( this ).val().replace( /\D/g, '' ).substring( 0, 6 );
			$( this ).val( v );
		} );

		/**
		 * Optional [data-phone-mask] legacy field — activate/register use country + national fields and DB lookup in this file.
		 */
		$( document ).on( 'input', '[data-phone-mask]', function() {
			var $el = $( this );
			/* Digits only; leading 977 is stripped for Nepal so "+977" in the field is not re-encoded as 977+977+… */
			var raw = $el.val().replace( /\D/g, '' );
			// NANP logic prepends "1" to 10-digit input. A Nepal number typed as 977·984·9158 (10 digits) then becomes
			// 1 977 984 9158 (11 digits) and formats as +1 (977) 984-9158 — wrong. It is +977, not +1 977.
			if ( raw.length === 11 && raw.charAt( 0 ) === '1' && raw.substring( 1, 4 ) === '977' ) {
				raw = raw.substring( 1 );
			}
			// Full Nepal national mobile: same as server /^9[78]\d{8}$/
			var np10 = raw.length === 10 && /^9[78]\d{8}$/.test( raw );
			// Partial Nepal, or a lone 9 (could become 97/98…). US numbers (e.g. 678…) do not match.
			var npPrefix = ( /^9[78]\d{0,8}$/.test( raw ) && raw.length < 10 ) || ( raw.length === 1 && raw === '9' ) || np10;
			var formatted = '';

			if ( npPrefix ) {
				var d = raw;
				// Pasted "9779…" or the displayed "+977" re-fed: strip country code 977 from the digit string once or more, then cap 10.
				while ( d.length > 0 && d.indexOf( '977' ) === 0 && d.length > 3 ) {
					d = d.substring( 3 );
				}
				if ( d === '977' ) {
					d = '';
				}
				if ( d.length > 10 ) {
					d = d.substring( 0, 10 );
				}
				if ( d.length > 0 ) {
					formatted = '+977';
					if ( d.length <= 3 ) {
						formatted += ' ' + d;
					} else if ( d.length <= 6 ) {
						formatted += ' ' + d.substring( 0, 3 ) + ' ' + d.substring( 3 );
					} else {
						formatted += ' ' + d.substring( 0, 3 ) + ' ' + d.substring( 3, 6 ) + ' ' + d.substring( 6 );
					}
				}
			} else {
				var v = raw;
				if ( v.length > 0 && v[0] !== '1' ) {
					v = '1' + v;
				}
				if ( v.length > 1 ) {
					v = v.substring( 0, 11 );
				}
				if ( v.length > 0 ) {
					formatted = '+1';
					if ( v.length > 1 ) {
						formatted += ' (' + v.substring( 1, 4 );
						if ( v.length > 4 ) {
							formatted += ') ' + v.substring( 4, 7 );
							if ( v.length > 7 ) {
								formatted += '-' + v.substring( 7 );
							}
						}
					}
				}
			}
			$el.val( formatted );
		} );

		function generateDeviceHash() {
			var components = [
				navigator.userAgent || '',
				navigator.language || '',
				screen.width + 'x' + screen.height,
				new Date().getTimezoneOffset().toString(),
				( navigator.hardwareConcurrency || '' ).toString()
			];
			var fingerprint = components.join( '|' );
			if ( window.crypto && window.crypto.subtle && window.TextEncoder ) {
				return window.btoa( fingerprint ).replace( /[^A-Za-z0-9]/g, '' ).substring( 0, 64 );
			}
			var h = 0;
			for ( var i = 0; i < fingerprint.length; i++ ) {
				h = ( ( h << 5 ) - h ) + fingerprint.charCodeAt( i ) | 0;
			}
			return 'd' + Math.abs( h ).toString( 16 );
		}

		function getGeoLocation() {
			return new Promise( function( resolve ) {
				if ( ! navigator.geolocation ) {
					resolve( { lat: null, lng: null } );
					return;
				}
				navigator.geolocation.getCurrentPosition(
					function( pos ) {
						resolve( { lat: pos.coords.latitude, lng: pos.coords.longitude } );
					},
					function() {
						resolve( { lat: null, lng: null } );
					},
					{ enableHighAccuracy: false, timeout: 5000 }
				);
			} );
		}

		$( document ).on( 'submit', '#cpm-nwp-register-form', function( e ) {
			e.preventDefault();
			var $form = $( this );
			var $submitBtn = $form.find( 'button[type="submit"]' );
			$( '#cpm-nwp-mobile-e164' ).val( cpmNwpBuildRegisterMobileE164() );
			$( '#cpm-nwp-device-hash' ).val( generateDeviceHash() );
			$submitBtn.prop( 'disabled', true ).text( 'Processing...' );
			clearInlineFeedback( $registerFeedback );

			getGeoLocation().then( function( geo ) {
				$( '#cpm-nwp-geo-lat' ).val( geo.lat || '' );
				$( '#cpm-nwp-geo-lng' ).val( geo.lng || '' );
				$( '#cpm-nwp-mobile-e164' ).val( cpmNwpBuildRegisterMobileE164() );

				var formData = $form.serialize() + '&action=' + ( window.cpmNwp && window.cpmNwp.action ? window.cpmNwp.action : 'cpm_nwp_register_device' );
				var ajaxUrl = ( window.cpmNwp && window.cpmNwp.ajaxUrl ) ? window.cpmNwp.ajaxUrl : '';

				if ( ! ajaxUrl ) {
					showInlineFeedback( $registerFeedback, 'Configuration error. Please refresh and try again.', 'error' );
					$submitBtn.prop( 'disabled', false ).text( 'Confirm Registration' );
					return;
				}

				$.post( ajaxUrl, formData )
					.done( function( res ) {
						if ( res.success && res.data && res.data.message ) {
							showInlineFeedback( $registerFeedback, res.data.message, 'success' );
							setTimeout( function() {
								showActivateModalAfterRegistration( $form );
							}, 700 );
						} else if ( res.success ) {
							showInlineFeedback( $registerFeedback, 'Registration complete.', 'success' );
							setTimeout( function() {
								showActivateModalAfterRegistration( $form );
							}, 700 );
						} else {
							showInlineFeedback( $registerFeedback, ( res.data && res.data.message ) ? res.data.message : 'Registration failed. Please try again.', 'error' );
						}
					} )
					.fail( function() {
						showInlineFeedback( $registerFeedback, 'Request failed. Please check your connection and try again.', 'error' );
					} )
					.always( function() {
						$submitBtn.prop( 'disabled', false ).text( 'Confirm Registration' );
					} );
			} );

			return false;
		} );

		cpmNwpInitRegisterPhoneFields();
		cpmNwpInitActivatePhoneFields();
	} );

})( jQuery );
