/* Medical College Manager — front-end AJAX filtering */
( function ( $ ) {
	'use strict';

	function collectTaxes( $wrap ) {
		var taxes = {};
		// Dropdown selects (toolbar layout).
		$wrap.find( '.mcm-filter-tax' ).each( function () {
			var $s = $( this );
			if ( $s.val() ) {
				taxes[ $s.data( 'tax' ) ] = $s.val();
			}
		} );
		// Checkboxes (directory layout) — multiple per taxonomy, OR-combined.
		$wrap.find( '.mcm-filter-check:checked' ).each( function () {
			var tax = $( this ).data( 'tax' );
			var val = $( this ).val();
			taxes[ tax ] = taxes[ tax ] ? taxes[ tax ] + ',' + val : val;
		} );
		return taxes;
	}

	function run( $wrap ) {
		var config = $wrap.data( 'config' ) || {};
		var search = $wrap.find( '.mcm-search' ).val() || '';
		var taxes  = collectTaxes( $wrap );
		var $res   = $wrap.find( '.mcm-results' );

		$res.addClass( 'mcm-loading' ).attr( 'aria-busy', 'true' );

		$.post( MCM.ajaxurl, {
			action: 'mcm_filter',
			nonce:  MCM.nonce,
			config: JSON.stringify( config ),
			taxes:  JSON.stringify( taxes ),
			search: search
		} ).done( function ( resp ) {
			if ( resp && resp.success ) {
				$res.html( resp.data.html );
			}
		} ).always( function () {
			$res.removeClass( 'mcm-loading' ).attr( 'aria-busy', 'false' );
		} );
	}

	// Upper bound when re-fetching a paginated list for printing. High enough
	// for a full timetable or student roll, low enough not to hang the server.
	var PRINT_ALL_LIMIT = 1000;

	// Build the print container and hand off to the browser. `html` is the
	// full re-fetched result set, or null to print what is on screen.
	function doPrint( $wrap, html ) {
		var $results = html
			? $( '<div class="mcm-results"></div>' ).html( html )
			: $wrap.find( '.mcm-results' ).first().clone();

		// Header is filled from the live wrap, but counts the print content.
		buildPrintHeader( $wrap, $results );

		var $area = $( '#mcm-print-area' );
		if ( ! $area.length ) {
			$area = $( '<div id="mcm-print-area"></div>' ).appendTo( document.body );
		}
		$area.empty();

		var $hdr = $wrap.find( '.mcm-print-header' ).first().clone();
		$hdr.css( 'display', 'block' );
		$area.append( $hdr ).append( $results );

		// Letterhead footer (signature block + note), stamped with the moment
		// the document was produced so a printed copy is self-dating.
		var $foot = $wrap.find( '.mcm-print-foot' ).first().clone();
		if ( $foot.length ) {
			$foot.find( '.mcm-print-gen' ).text(
				'Generated ' + new Date().toLocaleString()
			);
			$foot.css( 'display', 'block' );
			$area.append( $foot );
		}

		document.body.classList.add( 'mcm-printing' );
		window.print();
	}

	// Count rendered entries in ANY view. Printing used to count table rows
	// only, so a printable grid, directory or citation list reported "0
	// entries" on the sheet.
	function countEntries( $results ) {
		var sels = [ 'tbody tr', '.mcm-card', '.mcm-list-item', '.mcm-dir-card', '.mcm-pub-item' ];
		for ( var i = 0; i < sels.length; i++ ) {
			var n = $results.find( sels[ i ] ).filter( function () {
				return $( this ).find( '.mcm-empty' ).length === 0;
			} ).length;
			if ( n ) {
				return n;
			}
		}
		return 0;
	}

	// Fill the print header with the active filters, result count and date,
	// reflecting whatever filtering is applied at the moment of printing.
	function buildPrintHeader( $wrap, $countFrom ) {
		var $meta = $wrap.find( '.mcm-print-meta' );
		if ( ! $meta.length ) {
			return;
		}
		var parts = [];

		var s = ( $wrap.find( '.mcm-search' ).val() || '' ).replace( /^\s+|\s+$/g, '' );
		if ( s ) {
			parts.push( 'Search: ' + s );
		}

		$wrap.find( '.mcm-filter-tax' ).each( function () {
			var $sel = $( this );
			if ( $sel.val() ) {
				var label = $sel.attr( 'aria-label' ) || '';
				parts.push( ( label ? label + ': ' : '' ) + $sel.find( 'option:selected' ).text() );
			}
		} );

		var groups = {};
		$wrap.find( '.mcm-filter-check:checked' ).each( function () {
			var tax = $( this ).data( 'tax' );
			groups[ tax ] = groups[ tax ] || [];
			groups[ tax ].push( $.trim( $( this ).parent().text() ) );
		} );
		Object.keys( groups ).forEach( function ( k ) {
			parts.push( groups[ k ].join( ', ' ) );
		} );

		// Count what is actually going to be printed, which is not necessarily
		// what is on screen (a paginated list is re-fetched in full first).
		var count = countEntries( $countFrom && $countFrom.length ? $countFrom : $wrap.find( '.mcm-results' ) );

		var html = '';
		if ( parts.length ) {
			html += '<div class="mcm-print-filters"></div>';
		}
		html += '<div class="mcm-print-sub"></div>';
		$meta.html( html );
		// Use .text() so user-entered/term text is escaped, never injected as HTML.
		if ( parts.length ) {
			$meta.find( '.mcm-print-filters' ).text( parts.join( '   •   ' ) );
		}
		$meta.find( '.mcm-print-sub' ).text(
			count + ( count === 1 ? ' entry' : ' entries' ) + '   •   ' + new Date().toLocaleDateString()
		);
	}

	function debounce( fn, wait ) {
		var t;
		return function () {
			var ctx = this, args = arguments;
			clearTimeout( t );
			t = setTimeout( function () { fn.apply( ctx, args ); }, wait );
		};
	}

	$( function () {
		$( '.mcm-wrap' ).each( function () {
			var $wrap = $( this );

			$wrap.on( 'change', '.mcm-filter-tax, .mcm-filter-check', function () { run( $wrap ); } );

			$wrap.on( 'keyup', '.mcm-search', debounce( function () { run( $wrap ); }, 350 ) );

			// Explicit Search button + Enter key (directory layout).
			$wrap.on( 'click', '.mcm-search-btn', function () { run( $wrap ); } );
			$wrap.on( 'keydown', '.mcm-search', function ( e ) {
				if ( e.which === 13 ) { e.preventDefault(); run( $wrap ); }
			} );

			$wrap.on( 'click', '.mcm-reset', function () {
				$wrap.find( '.mcm-filter-tax' ).val( '' );
				$wrap.find( '.mcm-filter-check' ).prop( 'checked', false );
				$wrap.find( '.mcm-search' ).val( '' );
				run( $wrap );
			} );

			$wrap.on( 'click', '.mcm-print-btn', function () {
				var $btn = $( this );

				// Nothing paginated on screen — print exactly what is shown.
				if ( ! $wrap.find( '.mcm-results .mcm-pagination' ).length ) {
					doPrint( $wrap, null );
					return;
				}

				/*
				 * The list is paginated, so the visible results are only one
				 * page of it. Printing that would silently hand someone a
				 * partial timetable or student roll, so re-fetch the whole set
				 * (same filters, no paging) and print that instead. If the
				 * request fails — offline, or a non-public type the visitor
				 * cannot query — fall back to printing the visible page rather
				 * than printing nothing.
				 */
				var config   = $.extend( {}, $wrap.data( 'config' ) || {} );
				config.paginate = 'no';
				config.paged    = 1;
				config.count    = PRINT_ALL_LIMIT;

				$btn.prop( 'disabled', true );
				$.post( MCM.ajaxurl, {
					action: 'mcm_filter',
					nonce:  MCM.nonce,
					config: JSON.stringify( config ),
					taxes:  JSON.stringify( collectTaxes( $wrap ) ),
					search: $wrap.find( '.mcm-search' ).val() || ''
				} ).done( function ( resp ) {
					doPrint( $wrap, ( resp && resp.success ) ? resp.data.html : null );
				} ).fail( function () {
					doPrint( $wrap, null );
				} ).always( function () {
					$btn.prop( 'disabled', false );
				} );
			} );
		} );

		// Notice ticker / board pause toggle.
		$( document ).on( 'click', '.mcm-scroll-pause', function () {
			var $c = $( this ).closest( '.mcm-ticker, .mcm-board' );
			var paused = $c.toggleClass( 'is-paused' ).hasClass( 'is-paused' );
			$( this ).attr( 'aria-pressed', paused ? 'true' : 'false' );
		} );

		window.addEventListener( 'afterprint', function () {
			document.body.classList.remove( 'mcm-printing' );
			var area = document.getElementById( 'mcm-print-area' );
			if ( area && area.parentNode ) {
				area.parentNode.removeChild( area );
			}
		} );
	} );

} )( jQuery );
