// Prepares index.html for preview or render:
//  1. downscales each capture to the 1920-wide frame (assets/frames/, regenerated); zoomed stills
//     keep their full 2× capture so text stays sharp in close-ups,
//  2. copies GSAP next to the composition,
//  3. resolves lib/plan.mjs into timed operations (the narration's character timings place each
//     beat on its words) and writes the footage scenes, the MCP panels (real output saved by
//     capture.mjs), scene timing and the audio tracks into index.html between its markers.
//
//   node build.mjs   then   npx hyperframes render -q high -o renders/masthead-demo.mp4
import { readFile, writeFile, mkdir, copyFile, access } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { timeline, cue } from './lib/timeline.mjs';
import { audibleEnd } from './lib/media.mjs';
import { FOOTAGE } from './lib/plan.mjs';

const run = promisify( execFile );
const here = ( p ) => new URL( p, import.meta.url ).pathname;
const exists = ( p ) => access( p ).then( () => true, () => false );
const r2 = ( n ) => Math.round( n * 100 ) / 100;
const r1 = ( n ) => Math.round( n * 10 ) / 10;
const esc = ( s ) => String( s ).replaceAll( '&', '&amp;' ).replaceAll( '<', '&lt;' ).replaceAll( '>', '&gt;' ).replaceAll( '"', '&quot;' );

const W = 1920;
const H = 1080;
const ZOOMED = 1.15; // above this camera scale a still uses its full 2× capture
const TAG = '<div class="tag">Demo site · sample data</div>';

const script = JSON.parse( await readFile( here( 'script.json' ), 'utf8' ) );
const manifest = JSON.parse( await readFile( here( 'assets/shots/manifest.json' ), 'utf8' ) );
const K = W / manifest.viewport.width;
const t = await timeline( script );
const sceneOf = Object.fromEntries( t.scenes.map( ( s ) => [ s.id, s ] ) );
const at = ( id, phrase, edge = 'start' ) => cue( sceneOf[ id ], phrase, edge, t.lead );

// ---------------------------------------------------------------- geometry

function shotOf( name ) {
	const shot = manifest.shots[ name ];
	if ( ! shot ) {
		throw new Error( `No still named ${ name }; run capture.mjs` );
	}
	return shot;
}
const heightOf = ( name ) => Math.round( shotOf( name ).size[ 1 ] * K );

/** 'still#key' or [x, y, w, h] (CSS pixels) to a frame-pixel rect. */
function rectOf( ref ) {
	if ( Array.isArray( ref ) ) {
		return ref.map( ( n ) => n * K );
	}
	const [ name, key ] = ref.split( '#' );
	const box = shotOf( name ).targets[ key ];
	if ( ! box ) {
		throw new Error( `Still ${ name } has no target ${ key }` );
	}
	return box.map( ( n ) => n * K );
}
const centre = ( r ) => [ r[ 0 ] + r[ 2 ] / 2, r[ 1 ] + r[ 3 ] / 2 ];

/** Camera transform that frames a rect, clamped so the still always covers the frame. */
function frame( ref, { zoom = 2.2, pad = 40 } = {}, stillHeight ) {
	if ( ref === 'full' ) {
		return { x: 0, y: 0, scale: 1 };
	}
	const r = rectOf( ref );
	const p = pad * K;
	const scale = r2( Math.max( 1, Math.min( zoom, W / ( r[ 2 ] + 2 * p ), H / ( r[ 3 ] + 2 * p ) ) ) );
	const [ cx, cy ] = centre( r );
	const x = Math.min( 0, Math.max( W - scale * W, W / 2 - scale * cx ) );
	const y = Math.min( 0, Math.max( H - scale * stillHeight, H / 2 - scale * cy ) );
	// Round toward zero so rounding never uncovers a sliver at the right or bottom edge.
	return { x: Math.ceil( x * 10 ) / 10, y: Math.ceil( y * 10 ) / 10, scale };
}

// ---------------------------------------------------------------- footage

const ops = [];
const scenesHtml = [];
const hires = new Set();
const used = new Set();
const warnings = [];

for ( const [ id, plan ] of Object.entries( FOOTAGE ) ) {
	const sc = sceneOf[ id ];
	const base = sc.start;
	const when = ( a ) => {
		if ( typeof a === 'number' ) {
			return a;
		}
		return cue( sc, a.say, a.edge, t.lead ) + ( a.dx ?? 0 );
	};
	const beats = plan.beats.map( ( b, i ) => ( { ...b, time: r2( when( b.at ) ), i } ) ).sort( ( a, b ) => a.time - b.time || a.i - b.i );
	for ( const b of beats ) {
		if ( b.time < 0 || b.time > sc.dur ) {
			throw new Error( `${ id }: a beat lands at ${ b.time }s, outside the scene (${ sc.dur }s)` );
		}
	}

	const op = ( sel, from, to, time, dur = 0, ease ) => ops.push( { sel, from, to, t: r2( base + time ), dur: r2( dur ), ...( ease ? { ease } : {} ) } );
	const stills = [];
	const extras = [];
	const overlays = [];
	const visible = {}; // still -> [ [ from, to ], … ]
	const camPoints = [ [ 0, 1 ] ];
	const camTweens = []; // [ t0, t1, from, to ] for the edge check
	let current = null;
	let cam = { x: 0, y: 0, scale: 1 };
	let cursor = null;
	let cursorShown = false;
	let n = 0;

	// The still on screen at a scene time (for camera clamping).
	const stillAt = ( time ) => {
		let name = null;
		for ( const b of beats ) {
			if ( b.time > time + 1e-6 ) {
				break;
			}
			if ( b.still ) {
				name = b.still;
			} else if ( b.type ) {
				name = b.type[ Math.min( b.type.length - 1, Math.floor( ( time - b.time ) / ( b.every ?? 0.25 ) ) ) ];
			} else if ( b.drag ) {
				name = time >= b.time + b.dur ? b.after : b.over;
			}
		}
		return name;
	};

	const showStill = ( name, time, fade = 0.3 ) => {
		if ( ! stills.includes( name ) ) {
			stills.push( name );
		}
		used.add( name );
		const sel = `#st-${ id }-${ name }`;
		if ( current === null ) {
			op( sel, null, { opacity: 1 }, 0 );
		} else if ( current !== name ) {
			op( sel, { opacity: 0 }, { opacity: 1 }, time, fade, 'none' );
			op( `#st-${ id }-${ current }`, null, { opacity: 0 }, time + fade );
			visible[ current ].at( -1 )[ 1 ] = time + fade;
		}
		if ( current !== name ) {
			( visible[ name ] ??= [] ).push( [ time, sc.dur ] );
		}
		current = name;
	};

	const moveCursor = ( to, time, dur ) => {
		if ( ! cursorShown ) {
			op( `#cu-${ id }`, null, { x: r1( to[ 0 ] ), y: r1( to[ 1 ] ) }, time );
			op( `#cu-${ id }`, { opacity: 0 }, { opacity: 1 }, time, 0.3, 'none' );
			cursorShown = true;
		} else if ( dur > 0 ) {
			op( `#cu-${ id }`, { x: r1( cursor[ 0 ] ), y: r1( cursor[ 1 ] ) }, { x: r1( to[ 0 ] ), y: r1( to[ 1 ] ) }, time, dur, 'power2.inOut' );
		} else {
			op( `#cu-${ id }`, null, { x: r1( to[ 0 ] ), y: r1( to[ 1 ] ) }, time );
		}
		cursor = to;
	};

	for ( const b of beats ) {
		const time = b.time;
		if ( b.still ) {
			showStill( b.still, time, b.fade ?? 0.3 );
		}
		if ( b.type ) {
			b.type.forEach( ( name, k ) => showStill( name, time + k * ( b.every ?? 0.25 ), 0.04 ) );
		}
		if ( b.cam ) {
			const dur = b.dur ?? 1.2;
			const end = stillAt( time + dur ) ?? current;
			const target = frame( b.cam, b, heightOf( end ) );
			if ( dur > 0 ) {
				op( `#cam-${ id }`, { ...cam }, target, time, dur, b.ease ?? 'power2.inOut' );
				op( `#cu-${ id } .arrow`, { scale: r2( 1 / cam.scale ) }, { scale: r2( 1 / target.scale ) }, time, dur, b.ease ?? 'power2.inOut' );
			} else {
				op( `#cam-${ id }`, null, target, time );
				op( `#cu-${ id } .arrow`, null, { scale: r2( 1 / target.scale ) }, time );
			}
			camPoints.push( [ time, cam.scale ], [ time + dur, target.scale ] );
			camTweens.push( [ time, time + dur, { ...cam }, target ] );
			cam = target;
		}
		if ( b.cursor ) {
			const to = Array.isArray( b.cursor ) ? b.cursor.map( ( v ) => v * K ) : centre( rectOf( b.cursor ) );
			moveCursor( to, time, b.dur ?? 0.7 );
		}
		if ( b.click ) {
			op( `#rp-${ id }`, null, { x: r1( cursor[ 0 ] ), y: r1( cursor[ 1 ] ), opacity: 1, scale: r2( 1 / cam.scale ) }, time );
			op( `#rp-${ id } i`, { scale: 0.3, opacity: 0.95 }, { scale: 1.5, opacity: 0 }, time, 0.55, 'power1.out' );
			op( `#cu-${ id } svg`, { scale: 1 }, { scale: 0.84 }, time - 0.06, 0.08, 'power1.out' );
			op( `#cu-${ id } svg`, { scale: 0.84 }, { scale: 1 }, time + 0.04, 0.14, 'power1.out' );
		}
		if ( b.hide === 'cursor' && cursorShown ) {
			op( `#cu-${ id }`, { opacity: 1 }, { opacity: 0 }, time, 0.25, 'none' );
			cursorShown = false;
		}
		if ( b.mark || b.line ) {
			const key = `mk-${ id }-${ ++n }`;
			const s = cam.scale;
			const pad = ( b.pad ?? 6 ) * K;
			let style;
			let cls;
			if ( b.mark ) {
				const r = rectOf( b.mark );
				const rect = [ r[ 0 ] - pad, r[ 1 ] - pad, r[ 2 ] + 2 * pad, r[ 3 ] + 2 * pad ];
				const below = rect[ 1 ] < 160;
				cls = `mark${ below ? ' below' : '' }${ b.side === 'right' ? ' right' : '' }`;
				style = `left: ${ r1( rect[ 0 ] ) }px; top: ${ r1( rect[ 1 ] ) }px; width: ${ r1( rect[ 2 ] ) }px; height: ${ r1( rect[ 3 ] ) }px; border-width: ${ r2( 3 / s ) }px; transform-origin: 50% 50%`;
			} else {
				const a = rectOf( b.line[ 0 ] );
				const z = rectOf( b.line[ 1 ] );
				const y = ( a[ 1 ] + a[ 3 ] + z[ 1 ] ) / 2;
				cls = 'line';
				style = `left: ${ r1( a[ 0 ] ) }px; top: ${ r1( y ) }px; width: ${ r1( a[ 2 ] ) }px; border-top-width: ${ r2( 3 / s ) }px`;
			}
			const label = b.label ? `<span class="label" style="font-size: ${ r1( 21 / s ) }px">${ esc( b.label ) }</span>` : '';
			extras.push( `<div id="${ key }" class="${ cls }" style="${ style }">${ label }</div>` );
			op( `#${ key }`, { opacity: 0, scale: 1.05 }, { opacity: 1, scale: 1 }, time, 0.3, 'power2.out' );
			op( `#${ key }`, { opacity: 1 }, { opacity: 0 }, time + ( b.dur ?? 1.5 ) - 0.3, 0.3, 'none' );
		}
		if ( b.drag ) {
			const from = rectOf( b.drag[ 0 ] );
			const to = rectOf( b.drag[ 1 ] );
			const key = `dg-${ id }`;
			const [ fx, fy ] = centre( from );
			const [ tx, ty ] = centre( to );
			const dur = b.dur ?? 1.2;
			extras.push( `<img id="${ key }" class="drag" src="assets/shots/drag-chip.png" alt="" style="left: ${ r1( from[ 0 ] ) }px; top: ${ r1( from[ 1 ] ) }px; width: ${ r1( from[ 2 ] ) }px; height: ${ r1( from[ 3 ] ) }px" />` );
			op( `#${ key }`, { opacity: 0, scale: 1 }, { opacity: 1, scale: 1.06 }, time, 0.18, 'power2.out' );
			op( `#${ key }`, { x: 0, y: 0 }, { x: r1( tx - fx ), y: r1( ty - fy ) }, time + 0.12, dur, 'power2.inOut' );
			op( `#cu-${ id } svg`, { scale: 1 }, { scale: 0.86 }, time, 0.1, 'power1.out' );
			moveCursor( [ tx, ty ], time + 0.12, dur );
			showStill( b.over, time + 0.12 + dur * 0.55, 0.15 );
			showStill( b.after, time + 0.12 + dur, 0.2 );
			op( `#${ key }`, { opacity: 1, scale: 1.06 }, { opacity: 0, scale: 1 }, time + 0.12 + dur, 0.2, 'none' );
			op( `#cu-${ id } svg`, { scale: 0.86 }, { scale: 1 }, time + 0.12 + dur, 0.14, 'power1.out' );
		}
		if ( b.note || b.keys ) {
			const key = `ov-${ id }-${ ++n }`;
			if ( b.note ) {
				overlays.push( `<div id="${ key }" class="note"><small>About this demo</small>${ esc( b.note ) }</div>` );
			} else {
				overlays.push( `<div id="${ key }" class="keys">${ b.keys.split( '  ' ).map( ( k ) => `<span>${ esc( k ) }</span>` ).join( '' ) }</div>` );
			}
			op( `#${ key }`, { opacity: 0, y: 14 }, { opacity: 1, y: 0 }, time, 0.3, 'power2.out' );
			op( `#${ key }`, { opacity: 1 }, { opacity: 0 }, time + ( b.dur ?? 2 ) - 0.25, 0.25, 'none' );
		}
	}

	// The scene label, for the first few seconds.
	op( `#kk-${ id }`, { opacity: 0, x: -16 }, { opacity: 1, x: 0 }, 0.45, 0.45, 'power2.out' );
	op( `#kk-${ id }`, { opacity: 1 }, { opacity: 0 }, 3.6, 0.4, 'none' );

	// Edge check: the camera must never show past the bottom or right edge of a visible still.
	const camAt = ( time ) => {
		let state = { x: 0, y: 0, scale: 1 };
		for ( const [ t0, t1, from, to ] of camTweens ) {
			if ( time >= t1 ) {
				state = to;
			} else if ( time > t0 ) {
				const k = ( time - t0 ) / ( t1 - t0 );
				state = { x: from.x + ( to.x - from.x ) * k, y: from.y + ( to.y - from.y ) * k, scale: from.scale + ( to.scale - from.scale ) * k };
			}
		}
		return state;
	};
	for ( let time = 0; time <= sc.dur; time += 0.05 ) {
		const c = camAt( time );
		for ( const [ name, spans ] of Object.entries( visible ) ) {
			if ( ! spans.some( ( [ a, z ] ) => time >= a && time <= z ) ) {
				continue;
			}
			const bottom = ( H - c.y ) / c.scale;
			const right = ( W - c.x ) / c.scale;
			if ( bottom > heightOf( name ) + 2 || right > W + 2 ) {
				warnings.push( `${ id } at ${ time.toFixed( 2 ) }s (${ ( base + time ).toFixed( 2 ) }s): ${ name } ends at ${ heightOf( name ) }px, the camera shows to ${ Math.round( bottom ) }px` );
				break;
			}
		}
	}

	// Full-size captures for the stills that are on screen while the camera is zoomed in.
	for ( const [ name, spans ] of Object.entries( visible ) ) {
		for ( const [ a, z ] of spans ) {
			const scales = camPoints.filter( ( [ time ] ) => time >= a - 0.01 && time <= z + 0.01 ).map( ( [ , s ] ) => s );
			const before = camPoints.filter( ( [ time ] ) => time <= a ).at( -1 );
			if ( Math.max( before ? before[ 1 ] : 1, ...scales ) > ZOOMED ) {
				hires.add( name );
			}
		}
	}

	const imgs = stills.map( ( name ) => {
		const src = hires.has( name ) ? `assets/shots/${ name }.png` : `assets/frames/${ name }.png`;
		return `<img id="st-${ id }-${ name }" class="still" src="${ src }" alt="" width="${ W }" height="${ heightOf( name ) }" />`;
	} );
	scenesHtml.push(
		[
			`<div id="scene-${ id }" class="scene clip" data-scene="${ id }" data-start="${ sc.start }" data-duration="${ sc.dur }" data-track-index="0">`,
			`  <div id="cam-${ id }" class="cam">`,
			...imgs.map( ( s ) => `    ${ s }` ),
			...extras.map( ( s ) => `    ${ s }` ),
			`    <div id="rp-${ id }" class="ripple"><i></i></div>`,
			`    <div id="cu-${ id }" class="cursor"><div class="arrow"><svg viewBox="0 0 30 36" aria-hidden="true"><path d="M2 2 L2 29 L9.2 22.4 L14 33.2 L19 31 L14.3 20.4 L24 20.4 Z" fill="#fff" stroke="#1b1d1f" stroke-width="2.2" stroke-linejoin="round"/></svg></div></div>`,
			'  </div>',
			`  <div id="kk-${ id }" class="kicker"><b></b>${ esc( plan.kicker ) }</div>`,
			...overlays.map( ( s ) => `  ${ s }` ),
			`  ${ TAG }`,
			'</div>',
		]
			.map( ( l ) => `      ${ l }` )
			.join( '\n' )
	);
}

// Frames: every still the plan uses, downscaled once with a sharp filter.
await mkdir( here( 'assets/frames/' ), { recursive: true } );
for ( const name of used ) {
	if ( hires.has( name ) ) {
		continue;
	}
	await run( 'ffmpeg', [ '-loglevel', 'error', '-y', '-i', here( `assets/shots/${ name }.png` ), '-vf', `scale=${ W }:-1:flags=lanczos`, here( `assets/frames/${ name }.png` ) ] );
}

// ---------------------------------------------------------------- MCP panels (real output)

const table = ( await readFile( here( 'assets/shots/mcp-plan.txt' ), 'utf8' ) ).trimEnd().split( '\n' );
const plan = JSON.parse( await readFile( here( 'assets/shots/mcp-get-plan.json' ), 'utf8' ) );
const fixture = JSON.parse( await readFile( here( 'site/fixture.json' ), 'utf8' ) );
const catName = Object.fromEntries( fixture.categories.map( ( c ) => [ c.slug, c.name ] ) );
const STATUS = { publish: 'published', future: 'scheduled', draft: 'draft' };
const day = ( ymd ) => new Date( `${ ymd }T12:00:00Z` ).toLocaleDateString( 'en-US', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' } );
const command = table[ 0 ].replace( /^\$ /, '' );
const posts = plan.days.flatMap( ( d ) => d.posts.map( ( p ) => ( { d, p } ) ) );
const open = plan.days.filter( ( d ) => d.open );
const [ from, to ] = [ plan.days[ 0 ].date, plan.days.at( -1 ).date ];
const span = `${ day( from ).replace( /^\w+, /, '' ) }–${ Number( to.slice( 8 ) ) }`;
const counted = [ 'no posts', 'one post', 'two posts', 'three posts', 'four posts' ][ posts.length ] ?? `${ posts.length } posts`;
const mcpHtml = [
	'<div class="term">',
	'  <header><span>Terminal · wp-cli</span><span>northfield-notes</span></header>',
	`  <pre><span class="prompt">$ </span><span id="term-cmd" class="cmd">${ [ ...command ].map( ( ch ) => `<span class="ch">${ esc( ch ) }</span>` ).join( '' ) }</span><span class="caret"></span>\n${ table
		.slice( 1 )
		.map( ( line ) => `<span class="out">${ esc( line ) }\n</span>` )
		.join( '' ) }</pre>`,
	'</div>',
	'<div class="chat">',
	'  <header><span>MCP client</span><em>connected to Northfield Notes</em></header>',
	'  <div class="body">',
	'    <div class="bubble me">What’s on my calendar next week?</div>',
	`    <div class="tool">called <b>masthead/get-plan</b> { from: "${ from }", to: "${ to }" }</div>`,
	'    <div class="bubble ai">',
	`      Next week (${ span }) has ${ counted } so far:`,
	'      <ul>',
	...posts.map(
		( { d, p } ) =>
			`        <li><i style="background: ${ esc( p.color ) }"></i><b>${ esc( day( d.date ) ) }</b> · ${ esc( p.title ) } <span style="color: var(--muted)">(${ STATUS[ p.status ] ?? p.status }, ${ esc( catName[ p.primary_category ] ?? p.primary_category ) })</span></li>`
	),
	'      </ul>',
	`      <p class="open">Open: ${ open
		.map( ( d ) => {
			const text = `${ esc( day( d.date ) ) } (${ esc( d.lane.label ) })`;
			return new Date( `${ d.date }T12:00:00Z` ).getUTCDay() === 5 ? `<span id="chat-friday" class="hl"><i></i><span>${ text }</span></span>` : text;
		} )
		.join( ' · ' ) }</p>`,
	'    </div>',
	'  </div>',
	'</div>',
]
	.map( ( l ) => `          ${ l }` )
	.join( '\n' );

// ---------------------------------------------------------------- cues for the cards

const CUE = {
	open: { calendar: at( 'open', 'an editorial calendar' ), rhythm: at( 'open', 'rhythm' ) },
	honest: { rules: [ at( 'honest', 'Masthead never' ), at( 'honest', 'Traffic numbers' ), at( 'honest', 'The AI writes' ), at( 'honest', 'nothing touches' ) ] },
	mcp: { cli: at( 'mcp', 'W-P-C-L-I' ), assistants: at( 'mcp', 'AI assistants' ), ask: at( 'mcp', 'Ask what' ), friday: at( 'mcp', 'Friday' ) },
	close: { free: at( 'close', 'Free and open source' ) },
};

// ---------------------------------------------------------------- write index.html

await mkdir( here( 'assets/vendor/' ), { recursive: true } );
await copyFile( here( 'node_modules/gsap/dist/gsap.min.js' ), here( 'assets/vendor/gsap.min.js' ) );

const T = Object.fromEntries( t.scenes.map( ( s ) => [ s.id, { start: s.start, dur: s.dur } ] ) );
let html = await readFile( here( 'index.html' ), 'utf8' );
html = html.replace( /\/\* timing:start \*\/[\s\S]*?\/\* timing:end \*\//, () => `/* timing:start */ const T = ${ JSON.stringify( T ) }; const TOTAL = ${ t.total }; const CUE = ${ JSON.stringify( CUE ) }; const OPS = ${ JSON.stringify( ops ) }; /* timing:end */` );
html = html.replace( /<!-- footage:start -->[\s\S]*?<!-- footage:end -->/, () => `<!-- footage:start -->\n${ scenesHtml.join( '\n\n' ) }\n      <!-- footage:end -->` );
html = html.replace( /<!-- mcp:start -->[\s\S]*?<!-- mcp:end -->/, () => `<!-- mcp:start -->\n${ mcpHtml }\n          <!-- mcp:end -->` );
html = html.replace( /data-composition-id="main"([^>]*?)data-duration="[\d.]+"/, `data-composition-id="main"$1data-duration="${ t.total }"` );
for ( const s of t.scenes ) {
	html = html.replace( new RegExp( `(data-scene="${ s.id }"[^>]*?)data-start="[\\d.]+" data-duration="[\\d.]+"` ), `$1data-start="${ s.start }" data-duration="${ s.dur }"` );
}

const tracks = [];
for ( const s of t.scenes ) {
	if ( s.voiced ) {
		tracks.push( `<audio id="vo-${ s.id }" src="audio/vo-${ s.id }.mp3" data-start="${ s.voStart }" data-duration="${ s.vo }" data-volume="${ script.voiceVolume ?? 1 }" data-track-index="10"></audio>` );
	}
}
if ( await exists( here( 'audio/music.mp3' ) ) ) {
	const m = script.music;
	// Land the bed's last chord just before the last frame: trim its start by the difference, or,
	// if the piece is shorter than the scenes it plays under, bring it in a little later.
	const ending = await audibleEnd( here( 'audio/music.mp3' ) );
	const end = t.total - ( m.endBefore ?? 0.4 );
	const skip = r2( Math.max( 0, ending - ( end - t.musicStart ) ) );
	const start = r2( Math.max( t.musicStart, end - ending ) );
	if ( start > t.musicStart ) {
		console.warn( `music: the bed is ${ r2( ending ) }s long, so it starts at ${ start }s instead of ${ t.musicStart }s (node tts.mjs music for a longer one)` );
	}
	tracks.push( `<audio id="music" src="audio/music.mp3" data-start="${ start }" data-duration="${ r2( end - start ) }" data-media-start="${ skip }" data-volume="${ m.volume }" data-fade-in="${ m.fadeIn }" data-fade-out="${ m.fadeOut }" data-track-index="11"></audio>` );
}
html = html.replace( /<!-- audio:start -->[\s\S]*?<!-- audio:end -->/, () => `<!-- audio:start -->\n      ${ tracks.join( '\n      ' ) }\n      <!-- audio:end -->` );

await writeFile( here( 'index.html' ), html );
for ( const s of t.scenes ) {
	console.log( `${ s.id.padEnd( 11 ) } ${ s.start.toFixed( 2 ).padStart( 7 ) }s  +${ s.dur.toFixed( 2 ) }s  ${ s.voiced ? 'voice' : 'estimated' }` );
}
for ( const w of warnings.filter( ( w, i, all ) => all.findIndex( ( x ) => x.split( ' at ' )[ 0 ] === w.split( ' at ' )[ 0 ] && Math.abs( parseFloat( x.split( ' at ' )[ 1 ] ) - parseFloat( w.split( ' at ' )[ 1 ] ) ) < 0.5 ) === i ) ) {
	console.warn( `edge: ${ w }` );
}
console.log( `total ${ t.total }s, music from ${ t.musicStart }s, ${ ops.length } footage ops, ${ used.size } stills (${ hires.size } full size)${ tracks.length ? '' : ' (no audio yet: silent draft)' }` );
