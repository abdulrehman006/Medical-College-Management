/**
 * Consultant / Team member edit screen: email is required and must not be
 * used by another record of the same type (one record per person). Saving is
 * locked until it is valid; a phone used by someone else is only a note.
 *
 * Works in the block editor (lockPostSaving) and the classic editor (the
 * form's submit is held back). The server refuses a duplicate as well.
 */
( function () {
	'use strict';

	var cfg = window.MCMPeople;
	var email = document.getElementById( 'mcm_email' );
	if ( ! cfg || ! email ) {
		return;
	}
	var phone = document.getElementById( 'mcm_phone' );
	var LOCK = 'mcm-email';
	var state = 'pending'; // 'ok' | 'bad' | 'pending'.
	var seq = 0;
	var timer = null;
	var waiting = null; // Classic editor: the submit button held while checking.
	var checked = ''; // Email|phone last confirmed by the server.
	var form = null;

	function postId() {
		var f = document.getElementById( 'post_ID' );
		return f ? f.value : '';
	}

	function messageFor( input, id ) {
		var el = document.getElementById( id );
		if ( ! el ) {
			el = document.createElement( 'span' );
			el.id = id;
			el.className = 'mcm-people-msg';
			el.setAttribute( 'aria-live', 'polite' );
			el.style.display = 'block';
			el.style.marginTop = '4px';
			input.insertAdjacentElement( 'afterend', el );
			input.setAttribute( 'aria-describedby', ( ( input.getAttribute( 'aria-describedby' ) || '' ) + ' ' + id ).trim() );
		}
		return el;
	}

	// Text plus an optional link, built without innerHTML.
	function show( el, text, other, color ) {
		el.textContent = '';
		el.style.color = color;
		if ( ! text ) {
			return;
		}
		el.appendChild( document.createTextNode( text ) );
		if ( other && other.url ) {
			var a = document.createElement( 'a' );
			a.href = other.url;
			a.target = '_blank';
			a.rel = 'noopener';
			a.textContent = cfg.i18n.open;
			el.appendChild( document.createTextNode( ' ' ) );
			el.appendChild( a );
		} else if ( other && color === '#b32d2e' ) {
			el.appendChild( document.createTextNode( ' ' + cfg.i18n.askAdmin ) );
		}
	}

	var isBlock = document.body.classList.contains( 'block-editor-page' );

	// The block editor's data store, once the editor has loaded the post.
	function blockEditor() {
		try {
			var d = window.wp && window.wp.data;
			var ed = d && d.select( 'core/editor' );
			if ( ed && typeof ed.getCurrentPostId === 'function' && ed.getCurrentPostId() ) {
				return d;
			}
		} catch ( e ) {}
		return null;
	}

	function apply( ok, text, other ) {
		state = ok ? 'ok' : 'bad';
		email.setAttribute( 'aria-invalid', ok ? 'false' : 'true' );
		show( messageFor( email, 'mcm_email-msg' ), ok ? '' : text, other, '#b32d2e' );
		lockEditor( ok, text, 0 );
		if ( waiting ) {
			var by = waiting;
			waiting = null;
			if ( ok ) {
				if ( by.click ) {
					by.click(); // Same button: Publish, Update or Save Draft.
				} else if ( form.requestSubmit ) {
					form.requestSubmit();
				}
			} else {
				email.focus();
			}
		}
	}

	function lockEditor( ok, text, tries ) {
		if ( ! isBlock ) {
			return;
		}
		var d = blockEditor();
		if ( ! d ) {
			if ( tries < 20 ) {
				setTimeout( function () {
					if ( state === ( ok ? 'ok' : 'bad' ) ) {
						lockEditor( ok, text, tries + 1 );
					}
				}, 250 );
			}
			return;
		}
		try {
			if ( ok ) {
				d.dispatch( 'core/editor' ).unlockPostSaving( LOCK );
				d.dispatch( 'core/notices' ).removeNotice( LOCK );
			} else {
				d.dispatch( 'core/editor' ).lockPostSaving( LOCK );
				d.dispatch( 'core/notices' ).createErrorNotice( text, { id: LOCK, isDismissible: false } );
			}
		} catch ( e ) {}
	}

	function check( immediate ) {
		var value = email.value.trim();
		var key = value.toLowerCase() + '|' + ( phone ? phone.value.trim() : '' );
		// Leaving the field (e.g. clicking Publish) must not re-check a value
		// that was just confirmed: that would hold the save for a round trip.
		if ( key === checked && 'pending' !== state ) {
			return;
		}
		var my = ++seq;
		clearTimeout( timer );
		checked = '';
		if ( '' === value ) {
			apply( false, cfg.i18n.required );
			return;
		}
		if ( ( email.validity && ! email.validity.valid ) || ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			apply( false, cfg.i18n.invalid );
			return;
		}
		state = 'pending';
		timer = setTimeout( function () {
			var body = new FormData();
			body.append( 'action', 'mcm_person_check' );
			body.append( 'nonce', cfg.nonce );
			body.append( 'type', cfg.type );
			body.append( 'post_id', postId() );
			body.append( 'email', value );
			body.append( 'phone', phone ? phone.value : '' );
			fetch( cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( res ) {
					if ( my !== seq ) {
						return; // A newer check is running.
					}
					var data = res && res.success ? res.data : {};
					checked = res && res.success ? key : '';
					if ( data.email ) {
						apply( false, cfg.i18n.taken.replace( '%s', data.email.name ), data.email );
					} else {
						apply( true );
					}
					if ( phone ) {
						show( messageFor( phone, 'mcm_phone-msg' ), data.phone ? cfg.i18n.phone.replace( '%s', data.phone.name ) : '', data.phone, '#996800' );
					}
				} )
				.catch( function () {
					// Network trouble: do not trap the editor; the server still
					// refuses a duplicate when the record is saved.
					if ( my === seq ) {
						apply( true );
					}
				} );
		}, immediate ? 0 : 400 );
	}

	email.addEventListener( 'input', function () {
		check( false );
	} );
	email.addEventListener( 'change', function () {
		check( true );
	} );
	if ( phone ) {
		phone.addEventListener( 'change', function () {
			check( true );
		} );
	}

	// Classic editor: hold the form back until the email is confirmed. The
	// capture phase runs before WordPress greys out the Publish box, so a held
	// submit leaves the buttons usable. A click made while the check is still
	// running goes through by itself once the email is confirmed.
	form = document.getElementById( 'post' );
	if ( form && ! isBlock ) {
		form.addEventListener( 'submit', function ( e ) {
			if ( 'ok' === state ) {
				return;
			}
			e.preventDefault();
			e.stopImmediatePropagation();
			if ( 'pending' === state ) {
				waiting = e.submitter || true;
				check( true );
				return;
			}
			email.focus();
			email.scrollIntoView( { block: 'center' } );
		}, true );
	}

	// The block editor registers its stores after this script runs.
	if ( window.wp && window.wp.domReady ) {
		window.wp.domReady( function () {
			check( true );
		} );
	} else {
		check( true );
	}
} )();
