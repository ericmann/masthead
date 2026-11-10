// Narration and music from ElevenLabs, per script.json.
//
//   node tts.mjs           every scene's voice-over plus the music bed (skips files already made)
//   node tts.mjs --force   regenerate everything
//   node tts.mjs desk      regenerate only the named scenes' voice-over
//   node tts.mjs music     regenerate only the music bed
//
// Each voice-over comes from the with-timestamps endpoint, so audio/vo-<scene>.json holds the time
// of every character; build.mjs uses it to land clicks and zooms on the words that name them.
// The key is read from ~/.config/news-blog/elevenlabs-key and goes only into the request header.
// The music bed is sized to the scenes it plays under, so run this before build.mjs and again
// (music) whenever the narration changes length.
import { readFile, writeFile, mkdir, access } from 'node:fs/promises';
import { homedir } from 'node:os';
import { durationOf } from './lib/media.mjs';

const API = 'https://api.elevenlabs.io/v1';
const AUDIO = new URL( './audio/', import.meta.url ).pathname;
const script = JSON.parse( await readFile( new URL( './script.json', import.meta.url ), 'utf8' ) );
const key = ( await readFile( `${ homedir() }/.config/news-blog/elevenlabs-key`, 'utf8' ) ).trim();

const args = process.argv.slice( 2 );
const force = args.includes( '--force' );
const named = args.filter( ( a ) => ! a.startsWith( '--' ) );
const exists = ( p ) => access( p ).then( () => true, () => false );
const spoken = ( s ) => s.speak ?? s.text;

async function call( path, body ) {
	const res = await fetch( API + path, {
		method: 'POST',
		headers: { 'xi-api-key': key, 'Content-Type': 'application/json' },
		body: JSON.stringify( body ),
	} );
	if ( ! res.ok ) {
		throw new Error( `POST ${ path }: ${ res.status } ${ ( await res.text() ).slice( 0, 400 ) }` );
	}
	return res;
}

await mkdir( AUDIO, { recursive: true } );
const scenes = script.scenes;
const wantVo = named.length === 0 || named.some( ( n ) => n !== 'music' );
const wantMusic = named.length === 0 || named.includes( 'music' );

if ( wantVo ) {
	console.log( `voice ${ script.voice } = ${ script.voiceId }` );
	for ( const [ i, s ] of scenes.entries() ) {
		const file = `${ AUDIO }vo-${ s.id }.mp3`;
		if ( named.length ? ! named.includes( s.id ) : ! force && ( await exists( file ) ) ) {
			continue;
		}
		const res = await call( `/text-to-speech/${ script.voiceId }/with-timestamps?output_format=mp3_44100_128`, {
			text: spoken( s ),
			model_id: script.ttsModel,
			previous_text: scenes[ i - 1 ] ? spoken( scenes[ i - 1 ] ) : null,
			next_text: scenes[ i + 1 ] ? spoken( scenes[ i + 1 ] ) : null,
			seed: script.seed,
			voice_settings: script.voiceSettings,
		} );
		const { audio_base64: audio, alignment } = await res.json();
		await writeFile( file, Buffer.from( audio, 'base64' ) );
		await writeFile( `${ AUDIO }vo-${ s.id }.json`, `${ JSON.stringify( alignment ) }\n` );
		console.log( `vo-${ s.id }.mp3 ${ ( await durationOf( file ) ).toFixed( 2 ) }s` );
	}
}

if ( wantMusic ) {
	const file = `${ AUDIO }music.mp3`;
	if ( force || named.includes( 'music' ) || ! ( await exists( file ) ) ) {
		// Length: every scene the music plays under, from the voice-over just made, plus room for the
		// silence music_v1 tends to leave after its last chord (build.mjs aligns the audible ending).
		const { timeline } = await import( './lib/timeline.mjs' );
		const t = await timeline( script );
		const ms = Math.ceil( ( t.total - t.musicStart + 10 ) * 1000 );
		const res = await call( '/music?output_format=mp3_44100_128', {
			prompt: script.music.prompt,
			model_id: script.music.model,
			music_length_ms: ms,
			force_instrumental: true,
		} );
		await writeFile( file, Buffer.from( await res.arrayBuffer() ) );
		console.log( `music.mp3 ${ ( await durationOf( file ) ).toFixed( 2 ) }s (asked ${ ms / 1000 }s)` );
	}
}
