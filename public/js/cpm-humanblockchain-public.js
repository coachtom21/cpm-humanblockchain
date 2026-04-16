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
			}
		} );

		function closeVerifyShowActivate() {
			$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $verifyFeedback );
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
		}

		function showActivateModalAfterRegistration( $form ) {
			var mobileVal = $( '#cpm-nwp-mobile' ).val() || '';
			$verifyModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $verifyFeedback );
			$discordModal.addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			clearInlineFeedback( $registerFeedback );
			$( '#cpm-nwp-register-modal' ).addClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'true' );
			$form[0].reset();
			clearInlineFeedback( $activateFeedback );
			$( '#cpm-nwp-activate-mobile' ).val( mobileVal );
			window.cpmNwpActivateFromRegisterSuccess = true;
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
			$( 'body' ).addClass( 'cpm-nwp-modal-open' );
			showInlineFeedback( $activateFeedback, 'Device registered. Send OTP to verify your phone.', 'success' );
			$( '#cpm-nwp-activate-mobile' ).trigger( 'focus' );
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
			// Standard “Activate device” from the register modal must not inherit landing PoD flags (buyer + proof=scan),
			// or Send OTP would require Smallstreet checks and fail for a normal activation.
			if ( window.cpmHbLanding ) {
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
			$activateModal.removeClass( 'cpm-nwp-modal--hidden' ).attr( 'aria-hidden', 'false' );
		} );

		$( document ).on( 'submit', '#cpm-nwp-activate-form', function( e ) {
			e.preventDefault();
			var $form = $( this );
			var $btn = $form.find( 'button[type="submit"]' );
			var mobile = $( '#cpm-nwp-activate-mobile' ).val();
			var digits = mobile.replace( /\D/g, '' );
			var pe = window.cpmNwp && window.cpmNwp.phoneErrors ? window.cpmNwp.phoneErrors : {};
			var shortMsg = pe.short || 'Please enter a valid mobile number (at least 10 digits, or full international +977…).';
			if ( digits.length < 10 ) {
				showInlineFeedback( $activateFeedback, shortMsg, 'error' );
				return false;
			}
			if ( window.cpmNwp && window.cpmNwp.defaultCountry === 'NP' && digits.length === 11 && /^9[78]/.test( digits ) ) {
				showInlineFeedback( $activateFeedback, pe.npElevenDigits || 'Nepal numbers must be 10 digits without +977 (you have 11). Example: 9849158973 or +9779849158973.', 'error' );
				return false;
			}
			clearInlineFeedback( $activateFeedback );
			$btn.prop( 'disabled', true ).text( 'Sending...' );
			var formData = $form.serialize() + '&action=' + ( window.cpmNwp && window.cpmNwp.sendOtpAction ? window.cpmNwp.sendOtpAction : 'cpm_nwp_send_otp' );
			if ( window.cpmHbLanding && window.cpmHbLanding.buyerProofScan ) {
				formData += '&cpm_hb_buyer_proof_scan=1';
			}
			if ( window.cpmHbLanding && ( window.cpmHbLanding.buyerProofScan || window.cpmHbLanding.podProofScan ) ) {
				formData += '&cpm_hb_proof_scan=1';
				if ( window.cpmHbLanding.proofScanNonce ) {
					formData += '&cpm_hb_proof_scan_nonce=' + encodeURIComponent( window.cpmHbLanding.proofScanNonce );
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
				if ( window.cpmHbLanding.proofScanNonce ) {
					payload += '&cpm_hb_proof_scan_nonce=' + encodeURIComponent( window.cpmHbLanding.proofScanNonce );
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

		$( document ).on( 'input', '[data-phone-mask]', function() {
			var v = $( this ).val().replace( /\D/g, '' );
			if ( v.length > 0 && v[0] !== '1' ) {
				v = '1' + v;
			}
			if ( v.length > 1 ) {
				v = v.substring( 0, 11 );
			}
			var formatted = '';
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
			$( this ).val( formatted );
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
			$( '#cpm-nwp-device-hash' ).val( generateDeviceHash() );
			$submitBtn.prop( 'disabled', true ).text( 'Processing...' );
			clearInlineFeedback( $registerFeedback );

			getGeoLocation().then( function( geo ) {
				$( '#cpm-nwp-geo-lat' ).val( geo.lat || '' );
				$( '#cpm-nwp-geo-lng' ).val( geo.lng || '' );

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
	} );

})( jQuery );
