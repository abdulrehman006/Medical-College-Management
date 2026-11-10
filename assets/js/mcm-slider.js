/**
 * Medical College Manager: Slider widget.
 *
 * Dependency-free. One instance per .mcm-sl[data-mcm-slider]; re-initialised
 * by Elementor's editor each time the widget re-renders.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function fmt( str ) {
		var args = Array.prototype.slice.call( arguments, 1 ), i = 0;
		return String( str ).replace( /%(\d+\$)?d/g, function ( m, pos ) {
			return pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ];
		} );
	}

	function isEditMode() {
		try {
			return !! ( window.elementorFrontend && window.elementorFrontend.isEditMode() );
		} catch ( e ) {
			return false;
		}
	}

	function Slider( el ) {
		this.el = el;
		try {
			this.cfg = JSON.parse( el.getAttribute( 'data-mcm-slider' ) || '{}' );
		} catch ( e ) {
			this.cfg = {};
		}
		this.i18n = this.cfg.i18n || {};
		this.viewport = el.querySelector( '.mcm-sl-viewport' );
		this.track = el.querySelector( '.mcm-sl-track' );
		this.slides = Array.prototype.slice.call( this.track.children );
		this.n = this.slides.length;
		this.index = 0;
		this.pageCount = 0;
		this.timer = null;
		this.paused = { hover: false, focus: false, user: false, hidden: false, offscreen: false, video: false, used: false };
		this.handlers = [];
		this.rtl = getComputedStyle( el ).direction === 'rtl';
		this.edit = isEditMode();
		this.autoplay = !! this.cfg.autoplay && ! reduceMotion && ! this.edit && this.n > 1;
		this.init();
	}

	Slider.prototype.on = function ( target, type, fn, opts ) {
		target.addEventListener( type, fn, opts || false );
		this.handlers.push( [ target, type, fn, opts || false ] );
	};

	Slider.prototype.init = function () {
		var self = this, el = this.el;
		el.style.setProperty( '--mcm-sl-speed', ( reduceMotion ? 0 : ( this.cfg.speed || 0 ) ) + 'ms' );
		el.style.setProperty( '--mcm-sl-delay', ( this.cfg.delay || 5000 ) + 'ms' );
		if ( this.rtl ) {
			el.classList.add( 'is-rtl' );
		}
		this.prevBtn = el.querySelector( '.mcm-sl-prev' );
		this.nextBtn = el.querySelector( '.mcm-sl-next' );
		this.pager = el.querySelector( '.mcm-sl-pager' );
		this.pauseBtn = el.querySelector( '.mcm-sl-pause' );

		if ( this.prevBtn ) {
			this.on( this.prevBtn, 'click', function () { self.used(); self.prev(); } );
		}
		if ( this.nextBtn ) {
			this.on( this.nextBtn, 'click', function () { self.used(); self.next(); } );
		}
		if ( this.pauseBtn ) {
			if ( ! this.autoplay ) {
				this.pauseBtn.hidden = true;
			}
			this.on( this.pauseBtn, 'click', function () {
				self.paused.user = ! self.paused.user;
				self.paused.used = false;
				self.syncPause();
				self.schedule();
			} );
		}
		if ( this.pager ) {
			this.on( this.pager, 'click', function ( e ) {
				var b = e.target.closest( '.mcm-sl-dot' );
				if ( b ) {
					self.used();
					self.goPage( parseInt( b.getAttribute( 'data-page' ), 10 ) );
				}
			} );
		}
		if ( this.cfg.pauseHover ) {
			this.on( el, 'mouseenter', function () { self.paused.hover = true; self.schedule(); } );
			this.on( el, 'mouseleave', function () { self.paused.hover = false; self.schedule(); } );
		}
		// Keyboard focus inside the slider pauses it (WCAG 2.2.2).
		this.on( el, 'focusin', function () { self.paused.focus = true; self.schedule(); } );
		this.on( el, 'focusout', function ( e ) {
			if ( ! el.contains( e.relatedTarget ) ) {
				self.paused.focus = false;
				self.schedule();
			}
		} );
		if ( this.cfg.keyboard ) {
			this.on( el, 'keydown', function ( e ) {
				if ( e.target.closest( 'input, textarea, select, iframe, video' ) ) {
					return;
				}
				var fwd = self.rtl ? 'ArrowLeft' : 'ArrowRight', back = self.rtl ? 'ArrowRight' : 'ArrowLeft';
				if ( e.key === fwd ) {
					e.preventDefault(); self.used(); self.next();
				} else if ( e.key === back ) {
					e.preventDefault(); self.used(); self.prev();
				}
			} );
		}
		this.on( document, 'visibilitychange', function () {
			self.paused.hidden = document.hidden;
			self.slides.forEach( function ( s ) {
				if ( document.hidden ) {
					self.pauseBackground( s );
				} else if ( s.classList.contains( 'is-visible' ) ) {
					self.enterVideo( s );
				}
			} );
			self.schedule();
		} );
		if ( 'IntersectionObserver' in window ) {
			// Until the observer reports, treat the slider as off-screen: nothing
			// moves and no background video starts below the fold. Safari pauses
			// a muted autoplay video that starts off-screen and may not resume it.
			this.paused.offscreen = true;
			this.io = new IntersectionObserver( function ( entries ) {
				var visible = entries[ entries.length - 1 ].isIntersecting;
				if ( visible === ! self.paused.offscreen ) {
					return;
				}
				self.paused.offscreen = ! visible;
				self.slides.forEach( function ( s ) {
					if ( visible && s.classList.contains( 'is-visible' ) ) {
						self.enterVideo( s );
					} else if ( ! visible ) {
						self.pauseBackground( s );
					}
				} );
				self.schedule();
			} );
			this.io.observe( el );
		}
		this.el.querySelectorAll( '.mcm-sl-play' ).forEach( function ( b ) {
			self.on( b, 'click', function () { self.playClick( b.closest( '.mcm-sl-slide' ) ); } );
		} );
		if ( this.cfg.swipe && this.n > 1 ) {
			this.bindSwipe();
		}
		if ( 'ResizeObserver' in window ) {
			this.ro = new ResizeObserver( function () { self.layout(); } );
			this.ro.observe( this.viewport );
		} else {
			this.on( window, 'resize', function () { self.layout(); } );
		}
		if ( this.cfg.stretch ) {
			this.on( window, 'resize', function () { self.stretch(); } );
			this.stretch();
		}
		el.classList.add( 'is-ready' );
		this.layout( true );
		this.syncPause();
	};

	/* ---------- Layout ---------- */

	Slider.prototype.perView = function () {
		var v = parseFloat( getComputedStyle( this.el ).getPropertyValue( '--mcm-sl-per' ) );
		return Math.max( 1, Math.min( this.n, Math.round( v ) || 1 ) );
	};

	Slider.prototype.positions = function () {
		var max = Math.max( 0, this.n - this.per ), step = Math.max( 1, this.cfg.scroll || 1 ), out = [], p;
		for ( p = 0; p < max; p += step ) {
			out.push( p );
		}
		out.push( max );
		return out;
	};

	Slider.prototype.layout = function ( first ) {
		var per = this.perView();
		var fade = this.cfg.effect === 'fade' && per === 1;
		var changed = first || per !== this.per || fade !== this.fade;
		this.per = per;
		this.fade = fade;
		this.el.classList.toggle( 'is-fade', fade );
		this.pos = this.positions();
		if ( changed ) {
			this.buildPager();
		}
		if ( this.cfg.stretch ) {
			this.stretch();
		}
		this.go( Math.min( this.index, this.pos[ this.pos.length - 1 ] ), { instant: true, silent: ! first } );
		this.coverAll();
	};

	Slider.prototype.stretch = function () {
		var el = this.el, host = el.parentElement;
		if ( ! host ) {
			return;
		}
		el.style.width = '';
		el.style.marginLeft = '';
		el.style.marginRight = '';
		var docW = document.documentElement.clientWidth, r = host.getBoundingClientRect();
		el.style.width = docW + 'px';
		if ( this.rtl ) {
			el.style.marginRight = -( docW - r.right ) + 'px';
		} else {
			el.style.marginLeft = -r.left + 'px';
		}
	};

	/* ---------- Navigation ---------- */

	Slider.prototype.pageOf = function ( index ) {
		var i, best = 0;
		for ( i = 0; i < this.pos.length; i++ ) {
			if ( this.pos[ i ] <= index ) {
				best = i;
			}
		}
		return best;
	};

	Slider.prototype.next = function () {
		var page = this.pageOf( this.index );
		if ( page < this.pos.length - 1 ) {
			this.go( this.pos[ page + 1 ] );
		} else if ( this.cfg.loop ) {
			this.go( 0 );
		}
	};

	Slider.prototype.prev = function () {
		var page = this.pageOf( this.index );
		if ( page > 0 ) {
			this.go( this.pos[ page - 1 ] );
		} else if ( this.cfg.loop ) {
			this.go( this.pos[ this.pos.length - 1 ] );
		}
	};

	Slider.prototype.goPage = function ( page ) {
		if ( this.pos[ page ] !== undefined ) {
			this.go( this.pos[ page ] );
		}
	};

	Slider.prototype.offsetFor = function ( index ) {
		var s = this.slides[ index ], s0 = this.slides[ 0 ];
		return s && s0 ? -( s.offsetLeft - s0.offsetLeft ) : 0;
	};

	Slider.prototype.go = function ( index, opts ) {
		opts = opts || {};
		var self = this, prev = this.index, i;
		index = Math.max( 0, Math.min( index, this.pos[ this.pos.length - 1 ] ) );
		this.index = index;

		if ( this.fade ) {
			this.track.style.transform = '';
		} else {
			if ( opts.instant ) {
				this.track.style.transition = 'none';
			}
			this.track.style.transform = 'translate3d(' + this.offsetFor( index ) + 'px,0,0)';
			if ( opts.instant ) {
				void this.track.offsetWidth; // Flush, so the restore below does not animate.
				this.track.style.transition = '';
			}
		}

		for ( i = 0; i < this.n; i++ ) {
			var s = this.slides[ i ], vis = i >= index && i < index + this.per, wasVis = s.classList.contains( 'is-visible' );
			s.classList.toggle( 'is-visible', vis );
			s.classList.toggle( 'is-active', vis );
			s.setAttribute( 'aria-hidden', vis ? 'false' : 'true' );
			if ( vis ) {
				s.removeAttribute( 'inert' );
			} else {
				s.setAttribute( 'inert', '' );
			}
			if ( vis && ( ! wasVis || prev !== index ) ) {
				// Restart Ken Burns / content animation on re-entry.
				var img = s.querySelector( '.mcm-sl-img' ), inner = s.querySelector( '.mcm-sl-inner' );
				[ img, inner ].forEach( function ( node ) {
					if ( node && ! opts.silent ) {
						node.style.animation = 'none';
						void node.offsetWidth;
						node.style.animation = '';
					}
				} );
			}
			if ( vis ) {
				this.enterVideo( s );
			} else {
				this.leaveVideo( s );
			}
		}
		this.syncPager();
		if ( this.prevBtn && this.nextBtn ) {
			var atStart = this.pageOf( index ) === 0, atEnd = this.pageOf( index ) === this.pos.length - 1;
			this.prevBtn.disabled = ! this.cfg.loop && atStart;
			this.nextBtn.disabled = ! this.cfg.loop && atEnd;
			var hide = this.pos.length < 2;
			this.prevBtn.hidden = hide;
			this.nextBtn.hidden = hide;
		}
		this.schedule();
		if ( ! opts.silent && prev !== index ) {
			self.el.dispatchEvent( new CustomEvent( 'mcm-slider:change', { detail: { index: index } } ) );
		}
	};

	/* ---------- Pagination ---------- */

	Slider.prototype.buildPager = function () {
		if ( ! this.pager ) {
			return;
		}
		var type = this.cfg.pager, html = '', i, count = this.pos.length;
		this.pager.innerHTML = '';
		this.pager.hidden = count < 2;
		if ( type === 'fraction' ) {
			this.pager.innerHTML = '<span class="mcm-sl-fraction" aria-hidden="true"></span>';
		} else if ( type === 'progress' ) {
			this.pager.innerHTML = '<span class="mcm-sl-progress"></span>';
			this.pager.setAttribute( 'aria-hidden', 'true' );
		} else {
			for ( i = 0; i < count; i++ ) {
				html += '<button type="button" class="mcm-sl-dot" data-page="' + i + '" aria-label="' + fmt( this.i18n.goTo || 'Go to slide %d', i + 1 ) + '"></button>';
			}
			this.pager.innerHTML = html;
		}
	};

	Slider.prototype.syncPager = function () {
		if ( ! this.pager ) {
			return;
		}
		var page = this.pageOf( this.index ), count = this.pos.length;
		if ( this.cfg.pager === 'fraction' ) {
			var f = this.pager.querySelector( '.mcm-sl-fraction' );
			if ( f ) {
				f.textContent = ( page + 1 ) + ' / ' + count;
			}
		} else if ( this.cfg.pager === 'progress' ) {
			var bar = this.pager.querySelector( '.mcm-sl-progress' );
			if ( bar ) {
				bar.style.width = ( ( page + 1 ) / count * 100 ) + '%';
			}
		} else {
			this.pager.querySelectorAll( '.mcm-sl-dot' ).forEach( function ( d, i ) {
				d.setAttribute( 'aria-current', i === page ? 'true' : 'false' );
			} );
		}
	};

	/* ---------- Autoplay ---------- */

	Slider.prototype.used = function () {
		if ( this.cfg.pauseOnUse && this.autoplay ) {
			this.paused.used = true;
			this.syncPause();
		}
	};

	Slider.prototype.isPaused = function () {
		var p = this.paused;
		return ! this.autoplay || p.hover || p.focus || p.user || p.hidden || p.offscreen || p.video || p.used;
	};

	Slider.prototype.syncPause = function () {
		var stopped = this.paused.user || this.paused.used;
		this.el.classList.toggle( 'is-paused-user', stopped );
		if ( this.pauseBtn ) {
			this.pauseBtn.setAttribute( 'aria-label', stopped ? ( this.i18n.play || 'Play slider' ) : ( this.i18n.pause || 'Pause slider' ) );
		}
		this.viewport.setAttribute( 'aria-live', this.autoplay && ! stopped ? 'off' : 'polite' );
	};

	Slider.prototype.schedule = function () {
		var self = this;
		clearTimeout( this.timer );
		this.timer = null;
		if ( this.isPaused() ) {
			return;
		}
		this.timer = setTimeout( function () { self.next(); }, this.cfg.delay || 5000 );
	};

	/* ---------- Swipe / drag ---------- */

	Slider.prototype.bindSwipe = function () {
		var self = this, vp = this.viewport, start = null, dx = 0, moved = false, base = 0;
		this.on( vp, 'pointerdown', function ( e ) {
			if ( e.button !== 0 || e.target.closest( '.mcm-sl-play, .mcm-sl-video, input, textarea, select' ) ) {
				return;
			}
			start = { x: e.clientX, y: e.clientY, t: Date.now(), id: e.pointerId };
			dx = 0;
			moved = false;
			base = self.offsetFor( self.index );
		} );
		this.on( vp, 'pointermove', function ( e ) {
			if ( ! start || e.pointerId !== start.id ) {
				return;
			}
			dx = e.clientX - start.x;
			if ( ! moved && Math.abs( dx ) > 6 && Math.abs( dx ) > Math.abs( e.clientY - start.y ) ) {
				moved = true;
				self.el.classList.add( 'is-dragging' );
				try { vp.setPointerCapture( e.pointerId ); } catch ( err ) {}
			}
			if ( moved && ! self.fade ) {
				self.track.style.transform = 'translate3d(' + ( base + dx ) + 'px,0,0)';
			}
		} );
		var end = function ( e ) {
			if ( ! start || ( e && e.pointerId !== start.id ) ) {
				return;
			}
			var fast = Math.abs( dx ) > 20 && Date.now() - start.t < 250;
			var far = Math.abs( dx ) > Math.min( 80, vp.offsetWidth / 6 );
			self.el.classList.remove( 'is-dragging' );
			start = null;
			if ( moved ) {
				self.used();
				var forward = self.rtl ? dx > 0 : dx < 0;
				if ( fast || far ) {
					if ( forward ) {
						self.next();
					} else {
						self.prev();
					}
				} else {
					self.go( self.index );
				}
			}
		};
		this.on( vp, 'pointerup', end );
		this.on( vp, 'pointercancel', end );
		// A drag must not also count as a click on a link inside the slide.
		this.on( vp, 'click', function ( e ) {
			if ( moved ) {
				e.preventDefault();
				e.stopPropagation();
				moved = false;
			}
		}, true );
		this.on( vp, 'dragstart', function ( e ) { e.preventDefault(); } );
	};

	/* ---------- Video ---------- */

	Slider.prototype.videoData = function ( slide ) {
		var box = slide.querySelector( '.mcm-sl-video' );
		if ( ! box ) {
			return null;
		}
		try {
			return { box: box, v: JSON.parse( box.getAttribute( 'data-video' ) || '{}' ) };
		} catch ( e ) {
			return null;
		}
	};

	Slider.prototype.embedUrl = function ( v, background ) {
		var id = encodeURIComponent( v.id || '' );
		if ( v.type === 'youtube' ) {
			return 'https://www.youtube-nocookie.com/embed/' + id + '?enablejsapi=1&rel=0&playsinline=1&modestbranding=1&autoplay=1' +
				( background ? '&mute=1&loop=1&controls=0&disablekb=1&playlist=' + id : '' );
		}
		if ( v.type === 'vimeo' ) {
			return 'https://player.vimeo.com/video/' + id + '?dnt=1&autoplay=1' + ( background ? '&background=1&muted=1&loop=1' : '' );
		}
		return '';
	};

	Slider.prototype.makePlayer = function ( d, background, title ) {
		var v = d.v, node;
		if ( v.type === 'hosted' ) {
			node = document.createElement( 'video' );
			node.src = v.src;
			node.playsInline = true;
			node.setAttribute( 'playsinline', '' );
			node.preload = 'auto';
			if ( background ) {
				node.muted = true;
				node.loop = true;
				node.autoplay = true;
				node.setAttribute( 'muted', '' );
				node.setAttribute( 'aria-hidden', 'true' );
				node.tabIndex = -1;
			} else {
				node.controls = true;
				node.autoplay = true;
			}
		} else {
			node = document.createElement( 'iframe' );
			node.src = this.embedUrl( v, background );
			node.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media' );
			node.setAttribute( 'allowfullscreen', '' );
			node.title = title || this.i18n.video || 'Video';
			if ( background ) {
				node.setAttribute( 'aria-hidden', 'true' );
				node.tabIndex = -1;
			}
		}
		d.box.appendChild( node );
		var slide = d.box.closest( '.mcm-sl-slide' );
		var onReady = function () {
			d.box.classList.add( 'is-on' );
			if ( slide ) {
				slide.classList.add( 'is-video-on' );
			}
		};
		node.addEventListener( v.type === 'hosted' ? 'playing' : 'load', onReady );
		this.cover( d.box );
		return node;
	};

	Slider.prototype.command = function ( node, play ) {
		if ( ! node ) {
			return;
		}
		if ( node.tagName === 'VIDEO' ) {
			if ( play ) {
				var pr = node.play();
				if ( pr && pr.catch ) {
					pr.catch( function () {} );
				}
			} else {
				node.pause();
			}
			return;
		}
		var w = node.contentWindow;
		if ( ! w ) {
			return;
		}
		if ( node.src.indexOf( 'youtube' ) !== -1 ) {
			w.postMessage( JSON.stringify( { event: 'command', func: play ? 'playVideo' : 'pauseVideo', args: '' } ), '*' );
		} else {
			w.postMessage( JSON.stringify( { method: play ? 'play' : 'pause' } ), '*' );
		}
	};

	Slider.prototype.bgAllowed = function () {
		if ( reduceMotion ) {
			return false;
		}
		return this.cfg.bgMobile || ! ( window.matchMedia && window.matchMedia( '(max-width: 767px)' ).matches );
	};

	Slider.prototype.enterVideo = function ( slide ) {
		if ( ! slide.classList.contains( 'mcm-sl-slide--video-background' ) ) {
			return;
		}
		var d = this.videoData( slide );
		if ( ! d || ! this.bgAllowed() || this.paused.offscreen || this.paused.hidden ) {
			return;
		}
		var node = d.box.firstElementChild;
		if ( ! node ) {
			this.makePlayer( d, true );
		} else {
			this.command( node, true );
		}
	};

	// Off-screen or hidden tab: background videos stop; a video the visitor
	// started keeps its own controls and is left alone.
	Slider.prototype.pauseBackground = function ( slide ) {
		if ( ! slide.classList.contains( 'mcm-sl-slide--video-background' ) ) {
			return;
		}
		var d = this.videoData( slide );
		if ( d && d.box.firstElementChild ) {
			this.command( d.box.firstElementChild, false );
		}
	};

	Slider.prototype.leaveVideo = function ( slide ) {
		var d = this.videoData( slide );
		if ( ! d || ! d.box.firstElementChild ) {
			return;
		}
		this.command( d.box.firstElementChild, false );
		if ( slide.classList.contains( 'is-playing' ) ) {
			slide.classList.remove( 'is-playing' );
			this.paused.video = false;
			// A click-to-play video is removed so the cover and play button come back.
			d.box.innerHTML = '';
		}
	};

	Slider.prototype.playClick = function ( slide ) {
		var d = this.videoData( slide );
		if ( ! d ) {
			return;
		}
		var heading = slide.querySelector( '.mcm-sl-heading' );
		slide.classList.add( 'is-playing' );
		d.box.innerHTML = '';
		var node = this.makePlayer( d, false, heading ? heading.textContent : '' );
		this.paused.video = true;
		this.used();
		this.schedule();
		try { node.focus(); } catch ( e ) {}
	};

	// Size a background iframe so a 16:9 video covers the slide (like object-fit: cover).
	Slider.prototype.cover = function ( box ) {
		var f = box.querySelector( 'iframe' );
		if ( ! f || ! box.closest( '.mcm-sl-slide--video-background' ) ) {
			return;
		}
		var w = box.offsetWidth, h = box.offsetHeight, ratio = 16 / 9;
		if ( ! w || ! h ) {
			return;
		}
		if ( w / h > ratio ) {
			f.style.width = w + 'px';
			f.style.height = Math.ceil( w / ratio ) + 'px';
		} else {
			f.style.height = h + 'px';
			f.style.width = Math.ceil( h * ratio ) + 'px';
		}
	};

	Slider.prototype.coverAll = function () {
		var self = this;
		this.el.querySelectorAll( '.mcm-sl-video' ).forEach( function ( b ) { self.cover( b ); } );
	};

	/* ---------- Teardown ---------- */

	Slider.prototype.destroy = function () {
		clearTimeout( this.timer );
		this.handlers.forEach( function ( h ) { h[ 0 ].removeEventListener( h[ 1 ], h[ 2 ], h[ 3 ] ); } );
		if ( this.io ) {
			this.io.disconnect();
		}
		if ( this.ro ) {
			this.ro.disconnect();
		}
		delete this.el.mcmSlider;
	};

	function boot( root ) {
		( root || document ).querySelectorAll( '.mcm-sl[data-mcm-slider]' ).forEach( function ( el ) {
			if ( el.mcmSlider ) {
				el.mcmSlider.destroy();
			}
			el.mcmSlider = new Slider( el );
		} );
	}

	window.MCMSlider = { boot: boot };

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { boot(); } );
	} else {
		boot();
	}

	function hookElementor() {
		if ( ! window.elementorFrontend || ! window.elementorFrontend.hooks ) {
			return false;
		}
		window.elementorFrontend.hooks.addAction( 'frontend/element_ready/mcm_slider.default', function ( $scope ) {
			boot( $scope && $scope[ 0 ] ? $scope[ 0 ] : null );
		} );
		return true;
	}
	if ( ! hookElementor() ) {
		window.addEventListener( 'elementor/frontend/init', hookElementor );
	}
}() );
