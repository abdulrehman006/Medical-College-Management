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
				// data-size picks the stored size: "full" for a hero banner,
				// "large" for a portrait, "medium" (default) for a letterhead or
				// avatar that is never shown big. Documents have no sizes.
				var size = String( $btn.data( 'size' ) || 'medium' );
				if ( isImage && imageOnly && 'full' !== size && att.sizes && att.sizes[ size ] && att.sizes[ size ].url ) {
					url = att.sizes[ size ].url;
				}

				$field.val( url ).trigger( 'change' );

				var $prev = $scope.find( '.mcm-media-preview' ).first();
				if ( isImage ) {
					$prev.empty().append( $( '<img />' ).attr( { src: url, alt: '' } ).css( { maxHeight: '60px', marginTop: '8px', display: 'block' } ) );
				} else {
					// A thumbnail of a PDF is useless — show what was attached.
					$prev.text( ' ' + ( att.filename || att.title || '' ) );
				}

				// Quality warning (never a block): the original is narrower than
				// the field needs, so it will look blurry.
				var minw = parseInt( $btn.data( 'minw' ), 10 ) || 0;
				$scope.find( '.mcm-quality-warning' ).remove();
				if ( isImage && minw && att.width && att.width < minw ) {
					var tpl = ( i18n.tooSmall || 'This image is %1$d px wide; at least %2$d px is recommended, or it may look blurry.' );
					$( '<p class="mcm-quality-warning" role="status"></p>' ).text( tpl.replace( '%1$d', att.width ).replace( '%2$d', minw ) ).insertAfter( $prev );
				}
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.mcm-media-clear', function ( e ) {
			e.preventDefault();
			var $scope = fieldScope( $( this ) );
			$scope.find( '.mcm-media-field' ).first().val( '' ).trigger( 'change' );
			$scope.find( '.mcm-media-preview' ).first().empty();
			$scope.find( '.mcm-quality-warning' ).remove();
		} );

		// Services repeater (Departments → Edit): add / remove / move rows.
		// Inputs are renamed after every change so the saved order is the order
		// on screen. Without this script the rows still work (one empty row is
		// always rendered to add to).
		function renumber( $rep ) {
			var key = $rep.data( 'key' );
			$rep.find( '.mcm-rep-rows > .mcm-rep-row' ).each( function ( i ) {
				var $row = $( this );
				$row.find( '.mcm-rep-legend' ).text( ( ( window.MCMAdmin && MCMAdmin.i18n && MCMAdmin.i18n.service ) || 'Service' ) + ' ' + ( i + 1 ) );
				$row.find( '[name]' ).each( function () {
					this.name = this.name.replace( new RegExp( '^' + key + '\\[[^\\]]*\\]' ), key + '[' + i + ']' );
				} );
				$row.find( '[id]' ).each( function () {
					var old = this.id;
					this.id = old.replace( new RegExp( '^' + key + '-[^-]+-' ), key + '-' + i + '-' );
					$row.find( 'label[for="' + old + '"]' ).attr( 'for', this.id );
				} );
				$row.find( '.mcm-rep-up' ).prop( 'disabled', 0 === i );
				$row.find( '.mcm-rep-down' ).prop( 'disabled', i === $row.siblings().length );
			} );
		}
		$( '.mcm-repeater' ).each( function () { renumber( $( this ) ); } );
		$( document ).on( 'click', '.mcm-rep-add', function () {
			var $rep = $( this ).closest( '.mcm-repeater' );
			var tpl  = $rep.find( 'template.mcm-rep-template' )[ 0 ];
			var $row = $( tpl.innerHTML );
			$rep.find( '.mcm-rep-rows' ).append( $row );
			renumber( $rep );
			$row.find( 'input[type="text"]' ).first().trigger( 'focus' );
		} );
		$( document ).on( 'click', '.mcm-rep-remove', function () {
			var $row = $( this ).closest( '.mcm-rep-row' );
			var $rep = $row.closest( '.mcm-repeater' );
			var $to  = $row.next( '.mcm-rep-row' ).length ? $row.next() : $row.prev();
			$row.remove();
			renumber( $rep );
			( $to.length ? $to.find( 'input' ).first() : $rep.find( '.mcm-rep-add' ) ).trigger( 'focus' );
		} );
		$( document ).on( 'click', '.mcm-rep-up, .mcm-rep-down', function () {
			var $btn = $( this );
			var $row = $btn.closest( '.mcm-rep-row' );
			if ( $btn.hasClass( 'mcm-rep-up' ) ) {
				$row.insertBefore( $row.prev( '.mcm-rep-row' ) );
			} else {
				$row.insertAfter( $row.next( '.mcm-rep-row' ) );
			}
			renumber( $btn.closest( '.mcm-repeater' ) );
			// Keep keyboard focus on the moved row; a button that just became
			// disabled (row now first/last) cannot hold focus, so use its partner.
			( $btn.prop( 'disabled' ) ? $row.find( $btn.hasClass( 'mcm-rep-up' ) ? '.mcm-rep-down' : '.mcm-rep-up' ) : $btn ).trigger( 'focus' );
		} );
	} );
} )( jQuery );
