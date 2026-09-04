(function( $ ) {
	'use strict';

	$( function() {
		if ( typeof cpmNwpAdmin === 'undefined' ) {
			return;
		}
		var $btn = $( '#cpm-nwp-qr-generate' );
		if ( ! $btn.length ) {
			return;
		}
		var $url = $( '#cpm_nwp_qr_url' );
		var $st = $( '#cpm-nwp-qr-gen-status' );
		var $wrap = $( '#cpm-nwp-qr-preview' );
		var $img = $( '#cpm-nwp-qr-preview-img' );
		var $edit = $( '#cpm-nwp-qr-preview-edit' );
		var $dlPng = $( '#cpm-nwp-qr-dl-png' );
		var $dlJpg = $( '#cpm-nwp-qr-dl-jpg' );
		var $dlSep = $( '#cpm-nwp-qr-dl-sep' );

		function setDownloadLinks( pngUrl, jpgUrl, attachmentId ) {
			if ( pngUrl && $dlPng.length ) {
				$dlPng.attr( 'href', pngUrl ).show();
			}
			if ( jpgUrl && $dlJpg.length ) {
				$dlJpg.attr( 'href', jpgUrl ).show();
			}
			if ( $dlSep.length && pngUrl && jpgUrl ) {
				$dlSep.show();
			}
			if ( attachmentId && cpmNwpAdmin.postEdit && $edit.length ) {
				var el = cpmNwpAdmin.postEdit + '?post=' + encodeURIComponent( attachmentId ) + '&action=edit';
				var mediaLabel = cpmNwpAdmin.mediaStr || 'Open in Media Library';
				$edit.attr( 'href', el ).text( mediaLabel ).show();
			}
		}

		$btn.on( 'click', function() {
			var u = ( $url.val() || '' ).trim();
			if ( u === '' || ( u.indexOf( 'http://' ) !== 0 && u.indexOf( 'https://' ) !== 0 ) ) {
				window.alert( cpmNwpAdmin.strNoUrl );
				return;
			}
			$st.text( cpmNwpAdmin.strWorking );
			$btn.prop( 'disabled', true );
			$.post(
				cpmNwpAdmin.ajaxUrl,
				{
					action: 'cpm_nwp_generate_qr',
					cpm_nwp_qr_nonce: cpmNwpAdmin.nonce,
					qr_url: u
				}
			)
				.done( function( res ) {
					if ( res && res.success && res.data ) {
						$st.text( res.data.message || '' );
						if ( res.data.imageUrl ) {
							$img.attr( 'src', res.data.imageUrl ).show();
						}
						$wrap.show().attr( 'aria-hidden', 'false' );
						setDownloadLinks(
							res.data.downloadPng || '',
							res.data.downloadJpg || '',
							res.data.attachmentId || 0
						);
						$btn.text( cpmNwpAdmin.strRegen ).data( 'cpm-regenerate', '1' );
					} else {
						var err = ( res && res.data && res.data.message ) ? res.data.message : 'Error';
						$st.text( err );
					}
				} )
				.fail( function( xhr ) {
					var m = cpmNwpAdmin.strReqFail || cpmNwpAdmin.strNoUrl;
					if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						m = xhr.responseJSON.data.message;
					}
					$st.text( m );
				} )
				.always( function() {
					$btn.prop( 'disabled', false );
				} );
		} );
	} );

})( jQuery );
