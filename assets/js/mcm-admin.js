/* Medical College Manager — admin (color picker + media picker) */
( function ( $ ) {
	'use strict';
	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.mcm-color-field' ).wpColorPicker();
		}

		// Click a shortcode to copy it to the clipboard, with a brief "Copied!" cue.
		$( document ).on( 'click', '.mcm-copy-sc', function () {
			var $btn = $( this );
			var text = $btn.data( 'copy' );
			var done = function () {
				var $code = $btn.find( 'code' );
				var orig  = $code.text();
				$code.text( '✓ ' + ( ( window.MCMAdmin && window.MCMAdmin.i18n && window.MCMAdmin.i18n.copied ) || 'Copied!' ) );
				$btn.addClass( 'button-primary' );
				setTimeout( function () { $code.text( orig ); $btn.removeClass( 'button-primary' ); }, 1200 );
			};
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, fallbackCopy );
			} else {
				fallbackCopy();
			}
			function fallbackCopy() {
				var $t = $( '<textarea>' ).val( text ).css( { position: 'fixed', opacity: 0 } ).appendTo( 'body' );
				$t[ 0 ].select();
				try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
				$t.remove();
			}
		} );

		// The element holding one media field: a table cell (Settings, Edit
		// term), a post meta-box row (<p class="mcm-meta-field">) or an Add
		// term form row (<div class="form-field">). Looking only for a <td>
		// left the button dead on the last two.
		function fieldScope( $el ) {
			return $el.closest( 'td, .mcm-meta-field, .form-field' );
		}

		// Media Library picker for image and document fields.
		$( document ).on( 'click', '.mcm-media-pick', function ( e ) {
			e.preventDefault();

			var $btn   = $( this );
			var $scope = fieldScope( $btn );
			var $field = $scope.find( '.mcm-media-field' ).first();
			if ( ! $field.length || ! window.wp || ! wp.media ) {
				return;
			}

			// data-type: one MIME family or a comma list, e.g. "image",
			// "application/pdf" or "image,application/pdf".
			var types = $.map( String( $btn.data( 'type' ) || 'image' ).split( ',' ), function ( t ) {
				t = $.trim( t );
				return t ? t : null;
			} );
			var imageOnly = 1 === types.length && 0 === types[ 0 ].indexOf( 'image' );
			var i18n      = ( window.MCMAdmin && window.MCMAdmin.i18n ) || {};

			// A fresh frame per click keeps the selection tied to this field.
			var frame = wp.media( {
				title:    $btn.data( 'title' ) || ( imageOnly ? i18n.selectImage : i18n.selectFile ) || '',
				button:   { text: ( imageOnly ? i18n.useImage : i18n.useFile ) || '' },
				library:  { type: 1 === types.length ? types[ 0 ] : types },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att     = frame.state().get( 'selection' ).first().toJSON();
				var url     = att.url;
				var isImage = 'image' === att.type;
				// For image-only fields prefer a mid-size file; a letterhead or
				// avatar is never full width. Documents have no sizes.
				if ( isImage && imageOnly && att.sizes && att.sizes.medium && att.sizes.medium.url ) {
					url = att.sizes.medium.url;
				}

				$field.val( url ).trigger( 'change' );

				var $prev = $scope.find( '.mcm-media-preview' ).first();
				if ( isImage ) {
					$prev.empty().append( $( '<img />' ).attr( { src: url, alt: '' } ).css( { maxHeight: '60px', marginTop: '8px', display: 'block' } ) );
				} else {
					// A thumbnail of a PDF is useless — show what was attached.
					$prev.text( ' ' + ( att.filename || att.title || '' ) );
				}
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.mcm-media-clear', function ( e ) {
			e.preventDefault();
			var $scope = fieldScope( $( this ) );
			$scope.find( '.mcm-media-field' ).first().val( '' ).trigger( 'change' );
			$scope.find( '.mcm-media-preview' ).first().empty();
		} );
	} );
} )( jQuery );
