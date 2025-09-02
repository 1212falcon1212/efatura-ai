import * as React from 'react'

type IconProps = { size?: number; className?: string; strokeWidth?: number }

function baseProps(size?: number, className?: string, strokeWidth?: number) {
	return {
		width: size || 18,
		height: size || 18,
		viewBox: '0 0 24 24',
		fill: 'none',
		stroke: 'currentColor',
		strokeWidth: strokeWidth || 2.2,
		strokeLinecap: 'round' as const,
		strokeLinejoin: 'round' as const,
		className,
	}
}

export const IconHome: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M3 11.5 12 4l9 7.5" />
		<path d="M5.5 10.5V20h13V10.5" />
	</svg>
)

export const IconPaper: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M7 3h8l4 4v14H7z" />
		<path d="M15 3v4h4" />
		<path d="M9 11h6M9 15h6M9 7h2" />
	</svg>
)

export const IconBox: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M3 7l9-4 9 4-9 4-9-4Z" />
		<path d="M12 11v10" />
		<path d="M21 7v10l-9 4-9-4V7" />
	</svg>
)

export const IconUsers: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M16.5 7.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" />
		<path d="M3.5 20a6.5 6.5 0 0 1 17 0" />
	</svg>
)

export const IconCard: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<rect x="3" y="5" width="18" height="14" rx="2" />
		<path d="M3 9h18" />
		<path d="M8 15h4" />
	</svg>
)

export const IconChart: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M4 20V10" />
		<path d="M10 20V6" />
		<path d="M16 20V13" />
		<path d="M3 20h18" />
	</svg>
)

export const IconLink: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M10.5 13.5l3-3" />
		<path d="M7 15a4 4 0 0 1 0-6l2-2a4 4 0 0 1 6 0" />
		<path d="M17 9a4 4 0 0 1 0 6l-2 2a4 4 0 0 1-6 0" />
	</svg>
)

export const IconMail: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<rect x="3" y="5" width="18" height="14" rx="2" />
		<path d="M3 7l9 6 9-6" />
	</svg>
)

export const IconBell: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M6 10a6 6 0 1 1 12 0v4l2 2H4l2-2v-4Z" />
		<path d="M10 20a2 2 0 0 0 4 0" />
	</svg>
)

export const IconLogoScribble: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size || 26, className, strokeWidth || 2)}>
		<path d="M4 14c4-8 12-8 16 0" />
		<path d="M6 12c3-3 9-3 12 0" />
	</svg>
)

export const IconChevronDown: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M6 9l6 6 6-6" />
	</svg>
)

export const IconChevronRight: React.FC<IconProps> = ({ size, className, strokeWidth }) => (
	<svg {...baseProps(size, className, strokeWidth)}>
		<path d="M9 6l6 6-6 6" />
	</svg>
)


