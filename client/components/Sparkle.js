/**
 * The Desk's sparkle glyph. Decorative: always hidden from assistive technology.
 *
 * @param {Object} props      Props.
 * @param {number} props.size Width and height in pixels.
 * @return {Element} The icon.
 */
export default function Sparkle( { size = 16 } ) {
	return (
		<svg
			className="masthead-sparkle"
			width={ size }
			height={ size }
			viewBox="0 0 16 16"
			aria-hidden="true"
			focusable="false"
		>
			<path
				d="M8 1.5l1.4 3.6L13 6.5 9.4 7.9 8 11.5 6.6 7.9 3 6.5l3.6-1.4z"
				fill="currentColor"
			/>
			<path
				d="M13 10.5l.6 1.4 1.4.6-1.4.6-.6 1.4-.6-1.4-1.4-.6 1.4-.6z"
				fill="currentColor"
			/>
		</svg>
	);
}
