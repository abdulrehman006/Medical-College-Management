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
				$code.text( '✓ Copied!' );
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

		// Media Library picker for image settings (letterhead logo).
		$( document ).on( 'click', '.mcm-media-pick', function ( e ) {
			e.preventDefault();

			var $btn   = $( this );
			var $td    = $btn.closest( 'td' );
			var $field = $td.find( '.mcm-media-field' ).first();
			if ( ! $field.length || ! window.wp || ! wp.media ) {
				return;
			}

			// data-type lets the same control pick a document (policy PDF,
			// research paper) as well as an image; default stays images.
			var type    = $btn.data( 'type' ) || 'image';
			var isImage = 0 === type.indexOf( 'image' );

			// A fresh frame per click keeps the selection tied to this field.
			var frame = wp.media( {
				title:    $btn.data( 'title' ) || ( isImage ? 'Select image' : 'Select document' ),
				button:   { text: isImage ? 'Use this image' : 'Use this file' },
				library:  { type: type },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var url = att.url;
				// For images prefer a mid-size file; a letterhead or avatar is
				// never full width. Documents have no sizes.
				if ( isImage && att.sizes && att.sizes.medium && att.sizes.medium.url ) {
					url = att.sizes.medium.url;
				}

				$field.val( url ).trigger( 'change' );

				var $prev = $td.find( '.mcm-media-preview' );
				if ( isImage ) {
					$prev.html( $( '<img />' ).attr( 'src', url ).css( { maxHeight: '60px', marginTop: '8px' } ) );
				} else {
					// A thumbnail of a PDF is useless — show what was attached.
					$prev.text( ' ' + ( att.filename || att.title || '' ) );
				}
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.mcm-media-clear', function ( e ) {
			e.preventDefault();
			var $td = $( this ).closest( 'td' );
			$td.find( '.mcm-media-field' ).val( '' ).trigger( 'change' );
			$td.find( '.mcm-media-preview' ).empty();
		} );
	} );
} )( jQuery );
