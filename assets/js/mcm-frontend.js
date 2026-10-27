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

	function i18n( key, fallback ) {
		return ( window.MCM && MCM.i18n && MCM.i18n[ key ] ) || fallback;
	}

	// The server's message when it sent one (expired page, refused type),
	// otherwise the generic "please refresh" text.
	function errorMessage( resp, xhr ) {
		var data = ( resp && resp.data ) || ( xhr && xhr.responseJSON && xhr.responseJSON.data ) || null;
		return ( data && data.message ) || i18n( 'loadError', 'Could not load results. Please refresh the page and try again.' );
	}

	// One message slot per block, above the results. The current results stay
	// visible underneath, so a failed refresh never blanks the list.
	function showError( $wrap, text ) {
		var $res = $wrap.find( '.mcm-results' ).first();
		var $msg = $wrap.find( '.mcm-ajax-error' ).first();
		if ( ! $msg.length ) {
			$msg = $( '<p class="mcm-ajax-error" role="alert"></p>' ).insertBefore( $res );
		}
		$msg.text( text ).show();
	}

	function clearError( $wrap ) {
		$wrap.find( '.mcm-ajax-error' ).hide().text( '' );
	}

	// page: which results page to load (1 for every new search or filter).
	function run( $wrap, page ) {
		var config = $.extend( {}, $wrap.data( 'config' ) || {} );
		config.paged = page || 1;
		// From now on the block's pager loads pages in place (see below).
		$wrap.data( 'mcmFiltered', true );
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
			if ( resp && resp.success && resp.data && 'string' === typeof resp.data.html ) {
				clearError( $wrap );
				$res.html( resp.data.html );
			} else {
				showError( $wrap, errorMessage( resp, null ) );
			}
		} ).fail( function ( xhr ) {
			showError( $wrap, errorMessage( null, xhr ) );
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
				i18n( 'generated', 'Generated' ) + ' ' + new Date().toLocaleString()
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
			parts.push( i18n( 'search', 'Search:' ) + ' ' + s );
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
			count + ' ' + ( count === 1 ? i18n( 'entry', 'entry' ) : i18n( 'entries', 'entries' ) ) + '   •   ' + new Date().toLocaleDateString()
		);
	}

	// The listing block an element belongs to. Only blocks rendered by
	// render_block() carry data-config; other .mcm-wrap elements (the HOD card,
	// single-page details) are not filterable.
	function blockOf( el ) {
		return $( el ).closest( '.mcm-wrap[data-config]' );
	}

	// Debounced run, one timer per block.
	function runSoon( $wrap ) {
		clearTimeout( $wrap.data( 'mcmTimer' ) );
		$wrap.data( 'mcmTimer', setTimeout( function () { run( $wrap ); }, 350 ) );
	}

	function runNow( $wrap ) {
		clearTimeout( $wrap.data( 'mcmTimer' ) );
		run( $wrap );
	}

	function printBlock( $wrap, $btn ) {
		// A block that is not paginated shows everything it will ever show:
		// print exactly that. A paginated block (whether or not a pager is
		// visible right now) is re-fetched in full below.
		var paginated = /^(yes|1|true|on)$/i.test( String( ( $wrap.data( 'config' ) || {} ).paginate || '' ) );
		if ( ! paginated && ! $wrap.find( '.mcm-results .mcm-pagination' ).length ) {
			doPrint( $wrap, null );
			return;
		}

		/*
		 * The list is paginated, so the visible results are only one page of
		 * it. Printing that would silently hand someone a partial timetable or
		 * student roll, so re-fetch the whole set (same filters and scope, no
		 * paging) and print that instead. If the request fails — offline, or a
		 * type the visitor cannot query — fall back to printing the visible
		 * page rather than printing nothing.
		 */
		var config      = $.extend( {}, $wrap.data( 'config' ) || {} );
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
			doPrint( $wrap, ( resp && resp.success && resp.data ) ? resp.data.html : null );
		} ).fail( function () {
			doPrint( $wrap, null );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	}

	/*
	 * Event delegation on document: every handler works for blocks that exist
	 * at page load AND for blocks inserted later — the Elementor editor
	 * preview (which re-renders a widget on every change), Elementor popups,
	 * tabs and accordions that load content on demand.
	 */
	$( document )
		.on( 'change', '.mcm-wrap .mcm-filter-tax, .mcm-wrap .mcm-filter-check', function () {
			runNow( blockOf( this ) );
		} )
		// 'input' covers typing, pasting, autofill and the search box's clear
		// button — 'keyup' missed everything but the keyboard.
		.on( 'input', '.mcm-wrap .mcm-search', function () {
			runSoon( blockOf( this ) );
		} )
		.on( 'keydown', '.mcm-wrap .mcm-search', function ( e ) {
			if ( 13 === e.which ) {
				e.preventDefault();
				runNow( blockOf( this ) );
			}
		} )
		.on( 'click', '.mcm-wrap .mcm-search-btn', function () {
			runNow( blockOf( this ) );
		} )
		.on( 'click', '.mcm-wrap .mcm-reset', function () {
			var $wrap = blockOf( this );
			$wrap.find( '.mcm-filter-tax' ).val( '' );
			$wrap.find( '.mcm-filter-check' ).prop( 'checked', false );
			$wrap.find( '.mcm-search' ).val( '' );
			runNow( $wrap );
		} )
		.on( 'click', '.mcm-wrap .mcm-print-btn', function () {
			printBlock( blockOf( this ), $( this ) );
		} )
		// Pager of a block the visitor has filtered: load that page in place
		// so the filters are kept. An unfiltered block keeps its normal page
		// links (real URLs, back button, crawlable).
		.on( 'click', '.mcm-wrap .mcm-pagination a', function ( e ) {
			var $wrap = blockOf( this );
			if ( ! $wrap.length || ! $wrap.data( 'mcmFiltered' ) ) {
				return;
			}
			var href  = String( this.getAttribute( 'href' ) || '' );
			var match = /[?&]paged=(\d+)/.exec( href ) || /\/page\/(\d+)/.exec( href );
			e.preventDefault();
			run( $wrap, match ? parseInt( match[ 1 ], 10 ) : 1 );
			if ( $wrap[ 0 ].scrollIntoView ) {
				$wrap[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} )
		// Notice ticker / board pause toggle.
		.on( 'click', '.mcm-scroll-pause', function () {
			var $c     = $( this ).closest( '.mcm-ticker, .mcm-board' );
			var paused = $c.toggleClass( 'is-paused' ).hasClass( 'is-paused' );
			$( this ).attr( 'aria-pressed', paused ? 'true' : 'false' );
		} );

	/*
	 * Per-block initialisation for blocks that appear after page load. With
	 * delegated events nothing needs binding; this only resets the busy state
	 * a re-rendered block may carry and announces it ('mcm:ready') so site
	 * code can hook in. Idempotent.
	 */
	function initScope( $scope ) {
		$scope.find( '.mcm-wrap[data-config]' ).addBack( '.mcm-wrap[data-config]' ).each( function () {
			var $wrap = $( this );
			if ( $wrap.data( 'mcmReady' ) ) {
				return;
			}
			$wrap.data( 'mcmReady', true );
			$wrap.find( '.mcm-results' ).removeClass( 'mcm-loading' ).attr( 'aria-busy', 'false' );
			$wrap.trigger( 'mcm:ready' );
		} );
		$scope.find( '.mcm-dir-checks' ).each( function () {
			facetMore( $( this ) );
		} );
		$scope.find( '.mcm-dir-filters' ).each( function () {
			filterToggle( $( this ) );
		} );
	}

	// Phones/tablets: fold the filter groups behind one "Filters (n)" button
	// (CSS shows it only on narrow screens). n = filters currently ticked.
	function filterToggle( $f ) {
		if ( $f.data( 'mcmFold' ) || ! $f.find( '.mcm-dir-facet' ).length ) {
			return;
		}
		$f.data( 'mcmFold', true ).addClass( 'is-foldable' );
		if ( ! $f.attr( 'id' ) ) {
			$f.attr( 'id', 'mcm-filters-' + Math.random().toString( 36 ).slice( 2, 9 ) );
		}
		var $btn = $( '<button type="button" class="mcm-dir-toggle" aria-expanded="false"><span class="mcm-dir-toggle-label"></span><span class="mcm-dir-toggle-caret" aria-hidden="true">&#9662;</span></button>' ).attr( 'aria-controls', $f.attr( 'id' ) );
		var label = function () {
			var n = $f.find( '.mcm-filter-check:checked' ).length;
			$btn.find( '.mcm-dir-toggle-label' ).text( i18n( 'filters', 'Filters' ) + ( n ? ' (' + n + ')' : '' ) );
		};
		$btn.on( 'click', function () {
			var open = ! $f.hasClass( 'is-open' );
			$f.toggleClass( 'is-open', open );
			$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		} );
		$f.on( 'change', '.mcm-filter-check', label );
		$f.on( 'click', '.mcm-dir-reset', function () {
			setTimeout( label, 0 );
		} );
		label();
		var $search = $f.find( '.mcm-dir-search' );
		if ( $search.length ) {
			$btn.insertAfter( $search );
		} else {
			$f.prepend( $btn );
		}
	}

	// Directory filters: a long list shows its first items and a "Show all"
	// button instead of an inner scrollbar. Ticked items always stay visible.
	// Without script every option is simply listed.
	var FACET_VISIBLE = 6;
	function facetMore( $list ) {
		if ( $list.data( 'mcmMore' ) ) {
			return;
		}
		var $items = $list.children( 'li' );
		if ( $items.length <= FACET_VISIBLE + 2 ) {
			return;
		}
		$list.data( 'mcmMore', true );
		if ( ! $list.attr( 'id' ) ) {
			$list.attr( 'id', 'mcm-facet-list-' + Math.random().toString( 36 ).slice( 2, 9 ) );
		}
		$items.slice( FACET_VISIBLE ).addClass( 'is-extra' );
		var $btn = $( '<button type="button" class="mcm-dir-more" aria-expanded="false"></button>' ).attr( 'aria-controls', $list.attr( 'id' ) );
		var label = function () {
			var open = $list.hasClass( 'is-expanded' );
			$btn.text( open ? i18n( 'showFewer', 'Show fewer' ) : i18n( 'showAll', 'Show all (%d)' ).replace( '%d', $items.length ) ).attr( 'aria-expanded', open ? 'true' : 'false' );
		};
		var keepChecked = function () {
			$items.filter( '.is-extra' ).each( function () {
				$( this ).toggleClass( 'is-pinned', $( this ).find( 'input' ).is( ':checked' ) );
			} );
		};
		$btn.on( 'click', function () {
			var opening = ! $list.hasClass( 'is-expanded' );
			$list.toggleClass( 'is-expanded', opening );
			label();
			if ( opening ) {
				$items.eq( FACET_VISIBLE ).find( 'input' ).trigger( 'focus' );
			}
		} );
		$list.on( 'change', 'input', keepChecked );
		$list.closest( '.mcm-wrap' ).on( 'click', '.mcm-dir-reset', function () {
			setTimeout( keepChecked, 0 );
		} );
		keepChecked();
		label();
		$btn.insertAfter( $list );
	}

	$( function () {
		initScope( $( document ) );
	} );

	// Elementor: widgets rendered in the editor preview or later on the page.
	$( window ).on( 'elementor/frontend/init', function () {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/widget', function ( $scope ) {
				initScope( $scope );
			} );
		}
	} );

	// Elementor Pro popups.
	$( document ).on( 'elementor/popup/show', function ( event, id, instance ) {
		initScope( instance && instance.$element ? instance.$element : $( document ) );
	} );

	window.addEventListener( 'afterprint', function () {
		document.body.classList.remove( 'mcm-printing' );
		var area = document.getElementById( 'mcm-print-area' );
		if ( area && area.parentNode ) {
			area.parentNode.removeChild( area );
		}
	} );

} )( jQuery );
