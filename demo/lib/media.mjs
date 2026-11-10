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
