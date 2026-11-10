import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useCallback, useMemo, useState } from '@wordpress/element';

/**
 * Sets a nested value by dotted path on a copy of an object.
 *
 * @param {Object} source Object to copy.
 * @param {string} path   Dotted path, such as "desk.horizon_days".
 * @param {*}      value  New value.
 * @return {Object} The copy.
 */
function setPath( source, path, value ) {
	const [ head, ...rest ] = path.split( '.' );
	const copy = { ...( source || {} ) };
	copy[ head ] =
		rest.length === 0
			? value
			: setPath( copy[ head ], rest.join( '.' ), value );
	return copy;
}

/**
 * Reads and saves Masthead's site settings through core-data.
 *
 * @return {Object} { rhythm, settings, setRhythm, setSetting, save, isSaving, isSaved, error }.
 */
export function useSettings() {
	const { record, isSaving, error } = useSelect( ( select ) => {
		const store = select( coreStore );
		return {
			record: store.getEditedEntityRecord( 'root', 'site' ),
			isSaving: store.isSavingEntityRecord( 'root', 'site' ),
			error: store.getLastEntitySaveError( 'root', 'site' ),
		};
	}, [] );
	const { editEntityRecord, saveEditedEntityRecord } =
		useDispatch( coreStore );
	const [ isSaved, setIsSaved ] = useState( false );

	const rhythm = record?.masthead_rhythm || null;
	const stored = record?.masthead_settings;
	const settings = useMemo( () => stored || {}, [ stored ] );

	const setRhythm = useCallback(
		( next ) => {
			setIsSaved( false );
			editEntityRecord( 'root', 'site', undefined, {
				masthead_rhythm: next,
			} );
		},
		[ editEntityRecord ]
	);

	const setSetting = useCallback(
		( path, value ) => {
			setIsSaved( false );
			editEntityRecord( 'root', 'site', undefined, {
				masthead_settings: setPath( settings, path, value ),
			} );
		},
		[ editEntityRecord, settings ]
	);

	const save = useCallback( async () => {
		setIsSaved( false );
		await saveEditedEntityRecord( 'root', 'site' );
		setIsSaved( true );
	}, [ saveEditedEntityRecord ] );

	return {
		rhythm,
		settings,
		setRhythm,
		setSetting,
		save,
		isSaving,
		isSaved: isSaved && ! error,
		error,
	};
}
