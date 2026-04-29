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
						if ( res.data.attachmentId && cpmNwpAdmin.postEdit ) {
							var el = cpmNwpAdmin.postEdit + '?post=' + encodeURIComponent( res.data.attachmentId ) + '&action=edit';
							$edit.html( '<a href="' + el + '">' + cpmNwpAdmin.mediaStr + '</a>' );
						}
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
