// Masters the render's sound for the web: -16 LUFS integrated, true peak under -1 dBTP after AAC encoding. Two-pass
// loudnorm (measure, then apply the measured values); the video stream is copied, not re-encoded.
//
//   node master.mjs   renders/masthead-demo-raw.mp4 -> renders/masthead-demo.mp4
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const run = promisify( execFile );
const here = ( p ) => new URL( p, import.meta.url ).pathname;
const [ input, output ] = [ process.argv[ 2 ] ?? here( 'renders/masthead-demo-raw.mp4' ), process.argv[ 3 ] ?? here( 'renders/masthead-demo.mp4' ) ];
const TARGET = 'I=-16:TP=-2:LRA=11';

const { stderr } = await run( 'ffmpeg', [ '-hide_banner', '-nostats', '-i', input, '-af', `loudnorm=${ TARGET }:print_format=json`, '-f', 'null', '-' ] );
const measured = JSON.parse( stderr.slice( stderr.lastIndexOf( '{' ), stderr.lastIndexOf( '}' ) + 1 ) );
const filter = `loudnorm=${ TARGET }:measured_I=${ measured.input_i }:measured_TP=${ measured.input_tp }:measured_LRA=${ measured.input_lra }:measured_thresh=${ measured.input_thresh }:offset=${ measured.target_offset }:linear=true`;
await run( 'ffmpeg', [ '-hide_banner', '-loglevel', 'error', '-y', '-i', input, '-c:v', 'copy', '-af', filter, '-ar', '48000', '-c:a', 'aac', '-b:a', '192k', '-movflags', '+faststart', output ] );
console.log( `${ output }: ${ measured.input_i } LUFS -> -16 LUFS (true peak was ${ measured.input_tp } dBTP)` );
