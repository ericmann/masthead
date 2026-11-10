import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { monthDayLabel } from '../data/dates';
import { runAbility } from '../data/useAbility';

/**
 * ISO 8601 datetime with the site's UTC offset at that moment, from a datetime-local value.
 *
 * @param {string} local    Value such as "2026-11-12T09:00".
 * @param {string} timeZone IANA timezone of the site.
 * @return {string} Such as "2026-11-12T09:00:00-08:00".
 */
export function withSiteOffset( local, timeZone ) {
	const [ datePart, timePart ] = local.split( 'T' );
	const [ year, month, day ] = datePart.split( '-' ).map( Number );
	const [ hour, minute ] = timePart.split( ':' ).map( Number );
	const instant = new Date( Date.UTC( year, month - 1, day, hour, minute ) );
	const parts = new Intl.DateTimeFormat( 'en-US', {
		timeZone,
		timeZoneName: 'longOffset',
	} ).formatToParts( instant );
	const name =
		parts.find( ( part ) => part.type === 'timeZoneName' )?.value || 'GMT';
	const offset = name === 'GMT' ? '+00:00' : name.replace( 'GMT', '' );
	return `${ datePart }T${ timePart.slice( 0, 5 ) }:00${ offset }`;
}

/**
 * Share panel for a published post.
 *
 * @param {Object} props      Props.
 * @param {Object} props.post PlanPost.
 * @return {Element} The panel.
 */
export default function SharePanel( { post } ) {
	const [ connections, setConnections ] = useState( null );
	const [ message, setMessage ] = useState( '' );
	const [ checked, setChecked ] = useState( () => new Set() );
	const [ when, setWhen ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ outcome, setOutcome ] = useState( null );
	const [ queueHidden, setQueueHidden ] = useState( false );
	const [ drafting, setDrafting ] = useState( false );
	const [ draftError, setDraftError ] = useState( null );
	const boot = getBoot();
	const canDraft = Boolean( boot.ai?.supported && boot.caps?.promptAi );

	useEffect( () => {
		let live = true;
		runAbility( 'masthead/share-connections', {} )
			.then( ( result ) => live && setConnections( result ) )
			.catch(
				( error ) =>
					live &&
					setConnections( {
						available: false,
						connections: [],
						reason: error.message,
					} )
			);
		return () => {
			live = false;
		};
	}, [] );

	const published = post.date
		? monthDayLabel( post.date.slice( 0, 10 ) )
		: '';
	const heading = (
		<>
			<h2>{ post.title }</h2>
			{ published ? (
				<p>
					{ sprintf(
						/* translators: %s: short date, such as "Nov 9". */
						__( 'Published %s', 'masthead' ),
						published
					) }
				</p>
			) : null }
		</>
	);

	if ( ! connections ) {
		return (
			<section
				className="masthead-share"
				aria-label={ __( 'Share', 'masthead' ) }
			>
				{ heading }
				<p>{ __( 'Loading connections…', 'masthead' ) }</p>
			</section>
		);
	}

	if ( ! connections.available ) {
		return (
			<section
				className="masthead-share"
				aria-label={ __( 'Share', 'masthead' ) }
			>
				{ heading }
				<p className="masthead-share__unavailable">
					{ sprintf(
						/* translators: %s: reason sharing is unavailable. */
						__( 'Sharing is unavailable: %s', 'masthead' ),
						connections.reason || ''
					) }
				</p>
			</section>
		);
	}

	const ready = message.trim() !== '' && checked.size > 0;
	const toggle = ( id ) =>
		setChecked( ( current ) => {
			const next = new Set( current );
			if ( next.has( id ) ) {
				next.delete( id );
			} else {
				next.add( id );
			}
			return next;
		} );

	const draftWithAi = async () => {
		if (
			message.trim() !== '' &&
			// eslint-disable-next-line no-alert
			! window.confirm(
				__( 'Replace the current message with a draft?', 'masthead' )
			)
		) {
			return;
		}
		setDrafting( true );
		setDraftError( null );
		try {
			const result = await runAbility( 'masthead/draft-share-copy', {
				post_id: post.id,
			} );
			setMessage( result.message );
		} catch ( error ) {
			setDraftError( error.message );
		} finally {
			setDrafting( false );
		}
	};

	const send = async ( at ) => {
		setBusy( true );
		setOutcome( null );
		const input = {
			post_id: post.id,
			message,
			connection_ids: [ ...checked ],
		};
		if ( at ) {
			input.at = withSiteOffset( at, getBoot().timezone );
		}
		try {
			const result = await runAbility( 'masthead/share-post', input );
			setOutcome( { ok: true, status: result.status } );
		} catch ( error ) {
			if ( at && error.code === 'masthead_share_unavailable' ) {
				setQueueHidden( true );
			}
			setOutcome( { ok: false, message: error.message } );
		} finally {
			setBusy( false );
		}
	};

	return (
		<section
			className="masthead-share"
			aria-label={ __( 'Share', 'masthead' ) }
		>
			{ heading }
			<label htmlFor="masthead-share-message">
				{ __( 'Message', 'masthead' ) }
			</label>
			{ canDraft ? (
				<>
					{ ' ' }
					<button
						type="button"
						className="button"
						disabled={ drafting || busy }
						onClick={ draftWithAi }
					>
						{ __( 'Draft with AI', 'masthead' ) }
					</button>
				</>
			) : null }
			{ draftError ? (
				<p role="alert" className="masthead-share__error">
					{ draftError }
				</p>
			) : null }
			<textarea
				id="masthead-share-message"
				rows="4"
				value={ message }
				onChange={ ( event ) => setMessage( event.target.value ) }
			/>
			<fieldset>
				<legend>{ __( 'Share to', 'masthead' ) }</legend>
				{ connections.connections.map( ( connection ) => (
					<p key={ connection.id }>
						<label
							htmlFor={ `masthead-share-connection-${ connection.id }` }
						>
							<input
								id={ `masthead-share-connection-${ connection.id }` }
								type="checkbox"
								checked={ checked.has( connection.id ) }
								onChange={ () => toggle( connection.id ) }
							/>
							{ `${ connection.display_name } (${ connection.service })` }
						</label>
					</p>
				) ) }
			</fieldset>
			<p>
				<button
					type="button"
					className="button button-primary"
					disabled={ ! ready || busy }
					onClick={ () => send( null ) }
				>
					{ __( 'Share now', 'masthead' ) }
				</button>
			</p>
			{ queueHidden ? null : (
				<p>
					<label htmlFor="masthead-share-when">
						{ __( 'Queue for date', 'masthead' ) }
					</label>
					<input
						id="masthead-share-when"
						type="datetime-local"
						value={ when }
						onChange={ ( event ) => setWhen( event.target.value ) }
					/>
					<button
						type="button"
						className="button"
						disabled={ ! ready || ! when || busy }
						onClick={ () => send( when ) }
					>
						{ __( 'Queue for this date', 'masthead' ) }
					</button>
				</p>
			) }
			{ outcome?.ok ? (
				<p role="status">
					{ outcome.status === 'queued'
						? __( 'Share queued.', 'masthead' )
						: __( 'Shared.', 'masthead' ) }
				</p>
			) : null }
			{ outcome && ! outcome.ok ? (
				<p role="alert" className="masthead-share__error">
					{ outcome.message }
				</p>
			) : null }
		</section>
	);
}
