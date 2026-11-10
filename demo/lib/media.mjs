import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const run = promisify( execFile );

/** Seconds of audio or video in a file (ffprobe). */
export async function durationOf( file ) {
	const { stdout } = await run( 'ffprobe', [ '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', file ] );
	return Number.parseFloat( stdout );
}

/** Width and height of an image or video (ffprobe). */
export async function sizeOf( file ) {
	const { stdout } = await run( 'ffprobe', [ '-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=p=0', file ] );
	return stdout.trim().split( ',' ).map( Number );
}

/** Seconds into a file where its sound ends: the start of a silence that runs to the end of the file. */
export async function audibleEnd( file, floorDb = -50 ) {
	const total = await durationOf( file );
	const { stderr } = await run( 'ffmpeg', [ '-hide_banner', '-nostats', '-i', file, '-af', `silencedetect=noise=${ floorDb }dB:d=0.5`, '-f', 'null', '-' ] );
	const starts = [ ...stderr.matchAll( /silence_start: ([\d.]+)/g ) ].map( ( x ) => Number( x[ 1 ] ) );
	const ends = [ ...stderr.matchAll( /silence_end: ([\d.]+)/g ) ].map( ( x ) => Number( x[ 1 ] ) );
	// A trailing silence either has no end, or (newer ffmpeg) ends where the file does.
	if ( starts.length > ends.length || ( ends.length && total - ends.at( -1 ) < 0.1 ) ) {
		return starts.at( -1 );
	}
	return total;
}
