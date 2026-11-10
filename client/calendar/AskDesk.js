import { useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { getBoot } from '../data/boot';
import { runAbility } from '../data/useAbility';

/**
 * "Ask the Desk": propose a series from a sentence, edit the dated parts, create it on Accept.
 *
 * @param {Object}   props           Props.
 * @param {Function} props.onCreated Async; refreshes the plan after a series is created.
 * @return {Element} The box.
 */
export default function AskDesk( { onCreated = () => {} } ) {
	const boot = getBoot();
	const [ prompt, setPrompt ] = useState( '' );
	const [ start, setStart ] = useState( '' );
	const [ proposal, setProposal ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ created, setCreated ] = useState( '' );
	const nextId = useRef( 0 );

	const ask = async ( event ) => {
		event.preventDefault();
		if ( prompt.trim() === '' ) {
			return;
		}
		setBusy( true );
		setError( null );
		setCreated( '' );
		try {
			const input = { prompt: prompt.trim() };
			if ( start !== '' ) {
				input.start = start;
			}
			const result = await runAbility( 'masthead/propose-series', input );
			setProposal( {
				name: result.name,
				category: result.category,
				cadence: result.cadence,
				lane: result.lane,
				parts: result.parts.map( ( part ) => ( {
					...part,
					id: nextId.current++,
				} ) ),
			} );
		} catch ( caught ) {
			setError( caught.message );
		}
		setBusy( false );
	};

	const changePart = ( id, changes ) =>
		setProposal( ( current ) => ( {
			...current,
			parts: current.parts.map( ( part ) =>
				part.id === id ? { ...part, ...changes } : part
			),
		} ) );

	const accept = async () => {
		setBusy( true );
		setError( null );
		try {
			const result = await runAbility( 'masthead/accept-series', {
				name: proposal.name.trim(),
				category: proposal.category,
				cadence: proposal.cadence,
				parts: proposal.parts.map( ( part ) => ( {
					date: part.date,
					title: part.title.trim(),
					outline: part.outline,
				} ) ),
			} );
			const count = result.post_ids.length;
			setCreated(
				sprintf(
					/* translators: %d: number of planned drafts. */
					_n(
						'Created %d planned draft',
						'Created %d planned drafts',
						count,
						'masthead'
					),
					count
				)
			);
			setProposal( null );
			setPrompt( '' );
			await onCreated();
		} catch ( caught ) {
			setError( caught.message );
		}
		setBusy( false );
	};

	const valid =
		proposal &&
		proposal.name.trim() !== '' &&
		proposal.parts.length > 0 &&
		proposal.parts.every(
			( part ) => part.date !== '' && part.title.trim() !== ''
		);

	return (
		<section className="masthead-ask">
			<h3>{ __( 'Ask the Desk', 'masthead' ) }</h3>
			<form onSubmit={ ask }>
				<label htmlFor="masthead-ask-prompt">
					{ __( 'Ask the Desk', 'masthead' ) }
				</label>
				<input
					id="masthead-ask-prompt"
					type="text"
					value={ prompt }
					placeholder={ __(
						'A 4-part security series starting in January',
						'masthead'
					) }
					onChange={ ( event ) => setPrompt( event.target.value ) }
				/>{ ' ' }
				<label htmlFor="masthead-ask-start">
					{ __( 'Start on', 'masthead' ) }
				</label>{ ' ' }
				<input
					id="masthead-ask-start"
					type="date"
					value={ start }
					onChange={ ( event ) => setStart( event.target.value ) }
				/>{ ' ' }
				<button
					type="submit"
					className="button"
					disabled={ busy || prompt.trim() === '' }
				>
					{ __( 'Ask', 'masthead' ) }
				</button>
			</form>
			<p className="description">
				{ __( 'The AI provider is configured in', 'masthead' ) }{ ' ' }
				{ boot.urls?.connectors ? (
					<a href={ boot.urls.connectors }>
						{ __( 'Settings › Connectors', 'masthead' ) }
					</a>
				) : (
					__( 'Settings › Connectors', 'masthead' )
				) }
				.
			</p>
			<div className="screen-reader-text" aria-live="polite">
				{ created }
			</div>
			{ created ? (
				<p className="masthead-ask__created">{ created }</p>
			) : null }
			{ error ? (
				<div className="notice notice-error inline" role="alert">
					<p>{ error }</p>
				</div>
			) : null }
			{ proposal ? (
				<div className="masthead-ask__proposal">
					<p>
						<label htmlFor="masthead-ask-name">
							{ __( 'Series name', 'masthead' ) }
						</label>
						<input
							id="masthead-ask-name"
							type="text"
							value={ proposal.name }
							onChange={ ( event ) =>
								setProposal( {
									...proposal,
									name: event.target.value,
								} )
							}
						/>
					</p>
					<p>
						{ sprintf(
							/* translators: %s: lane label. */
							__( 'Lane: %s', 'masthead' ),
							proposal.lane?.label || ''
						) }
					</p>
					<ol className="masthead-ask__parts">
						{ proposal.parts.map( ( part, index ) => (
							<li key={ part.id }>
								<input
									type="date"
									value={ part.date }
									aria-label={ sprintf(
										/* translators: %d: part number. */
										__( 'Part %d date', 'masthead' ),
										index + 1
									) }
									onChange={ ( event ) =>
										changePart( part.id, {
											date: event.target.value,
										} )
									}
								/>{ ' ' }
								<input
									type="text"
									value={ part.title }
									aria-label={ sprintf(
										/* translators: %d: part number. */
										__( 'Part %d title', 'masthead' ),
										index + 1
									) }
									onChange={ ( event ) =>
										changePart( part.id, {
											title: event.target.value,
										} )
									}
								/>{ ' ' }
								<button
									type="button"
									className="button-link"
									aria-label={ sprintf(
										/* translators: %d: part number. */
										__( 'Remove part %d', 'masthead' ),
										index + 1
									) }
									onClick={ () =>
										setProposal( ( current ) => ( {
											...current,
											parts: current.parts.filter(
												( item ) => item.id !== part.id
											),
										} ) )
									}
								>
									{ __( 'Remove part', 'masthead' ) }
								</button>
								<ul>
									{ ( part.outline || [] ).map(
										( line, position ) => (
											<li key={ position }>{ line }</li>
										)
									) }
								</ul>
							</li>
						) ) }
					</ol>
					<p>
						<button
							type="button"
							className="button button-primary"
							disabled={ busy || ! valid }
							onClick={ accept }
						>
							{ __( 'Accept series', 'masthead' ) }
						</button>{ ' ' }
						<button
							type="button"
							className="button-link"
							disabled={ busy }
							onClick={ () => {
								setProposal( null );
								setError( null );
							} }
						>
							{ __( 'Discard', 'masthead' ) }
						</button>
					</p>
				</div>
			) : null }
		</section>
	);
}
