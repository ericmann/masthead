import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Keyboard alternative to dragging: a button that opens a date field.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.post   PlanPost.
 * @param {Function} props.onMove Called with the post and a YYYY-MM-DD date.
 * @return {Element|null} The control, or null for a post that cannot move.
 */
export default function MoveTo( { post, onMove } ) {
	const [ open, setOpen ] = useState( false );
	const [ date, setDate ] = useState(
		post.date ? post.date.slice( 0, 10 ) : ''
	);

	if ( post.status === 'publish' ) {
		return null;
	}

	if ( ! open ) {
		return (
			<button
				type="button"
				className="masthead-move__open"
				onClick={ () => setOpen( true ) }
			>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %s: post title. */
						__( 'Move “%s” to another day', 'masthead' ),
						post.title
					) }
				</span>
				<span aria-hidden="true">{ __( 'Move to…', 'masthead' ) }</span>
			</button>
		);
	}

	return (
		<form
			className="masthead-move"
			onSubmit={ ( event ) => {
				event.preventDefault();
				if ( date ) {
					onMove( post, date );
					setOpen( false );
				}
			} }
		>
			<input
				type="date"
				aria-label={ sprintf(
					/* translators: %s: post title. */
					__( 'New date for “%s”', 'masthead' ),
					post.title
				) }
				value={ date }
				onChange={ ( event ) => setDate( event.target.value ) }
			/>
			<button type="submit" className="button button-small">
				{ __( 'Move', 'masthead' ) }
			</button>
		</form>
	);
}
