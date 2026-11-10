// Scene timing from script.json: each scene lasts its voice-over plus a lead-in and tail, never
// less than its `min`. Without a voice-over file (no key yet) the length is estimated from the
// word count at a relaxed narration pace, so a silent draft still has the right shape.
//
// cue() turns a phrase of a scene's narration into seconds from the scene's start, from the
// character timings ElevenLabs returned (audio/vo-<scene>.json), or proportionally without them.
import { access, readFile } from 'node:fs/promises';
import { durationOf } from './media.mjs';

const WORDS_PER_SECOND = 2.6;
const AUDIO = new URL( '../audio/', import.meta.url ).pathname;
const exists = ( p ) => access( p ).then( () => true, () => false );
const round = ( n ) => Math.round( n * 100 ) / 100;

export async function timeline( script ) {
	let at = 0;
	let musicStart = null;
	const scenes = [];
	for ( const s of script.scenes ) {
		const spoken = s.speak ?? s.text;
		const file = `${ AUDIO }vo-${ s.id }.mp3`;
		const voiced = await exists( file );
		const vo = voiced ? await durationOf( file ) : spoken.split( /\s+/ ).length / WORDS_PER_SECOND;
		const alignFile = `${ AUDIO }vo-${ s.id }.json`;
		const alignment = voiced && ( await exists( alignFile ) ) ? JSON.parse( await readFile( alignFile, 'utf8' ) ) : null;
		const dur = Math.max( s.min, script.lead + vo + script.tail );
		if ( musicStart === null && s.music !== false ) {
			musicStart = at;
		}
		scenes.push( { id: s.id, start: round( at ), dur: round( dur ), vo: round( vo ), voiced, voStart: round( at + script.lead ), spoken, alignment } );
		at += dur;
	}
	return { scenes, total: round( at ), musicStart: round( musicStart ?? 0 ), lead: script.lead };
}

/**
 * Seconds from the scene's start at which a phrase of its narration begins (or ends).
 *
 * @param {Object} scene  A scene from timeline().
 * @param {string} phrase Text that occurs in the scene's spoken narration.
 * @param {string} edge   'start' or 'end'.
 * @param {number} lead   Seconds of silence before the voice starts.
 */
export function cue( scene, phrase, edge, lead ) {
	const text = scene.alignment ? scene.alignment.characters.join( '' ) : scene.spoken;
	const index = text.indexOf( phrase );
	if ( index < 0 ) {
		throw new Error( `${ scene.id }: "${ phrase }" is not in the narration` );
	}
	const at = edge === 'end' ? index + phrase.length - 1 : index;
	if ( scene.alignment ) {
		const times = edge === 'end' ? scene.alignment.character_end_times_seconds : scene.alignment.character_start_times_seconds;
		return round( lead + times[ at ] );
	}
	return round( lead + ( at / text.length ) * scene.vo );
}
