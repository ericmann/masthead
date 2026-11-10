/**
 * A small colour dot for a lane or category.
 *
 * @param {Object} props       Props.
 * @param {string} props.color Hex colour.
 * @param {string} props.label Optional visible label.
 * @return {Element} The dot.
 */
export default function LaneDot( { color, label } ) {
	return (
		<span className="masthead-lane-dot">
			<span
				className="masthead-lane-dot__dot"
				style={ { backgroundColor: color } }
				aria-hidden="true"
			/>
			{ label ? (
				<span className="masthead-lane-dot__label">{ label }</span>
			) : null }
		</span>
	);
}
