import * as React from 'react'

export type Column<T> = {
	key: string
	label: string
	sortable?: boolean
	width?: number | string
	render?: (row: T) => React.ReactNode
	visible?: boolean
	align?: 'left' | 'center' | 'right'
}

export type DataTableProps<T> = {
	columns: Array<Column<T>>
	rows: T[]
	stickyHeader?: boolean
	maxHeight?: string
	compact?: boolean
	sortKey?: string
	sortDir?: 'asc' | 'desc'
	onSortToggle?: (key: string) => void
	selectable?: boolean
	selectedIds?: Array<string | number>
	getRowId: (row: T) => string | number
	onToggleSelect?: (id: string | number, checked: boolean) => void
	onToggleSelectAll?: (checked: boolean) => void
	showColumnSettings?: boolean
	onColumnsChange?: (cols: Array<Column<T>>) => void
	storageKey?: string
	hasMore?: boolean
	loadingMore?: boolean
	onLoadMore?: () => void
	rowActions?: (row: T) => React.ReactNode
	initialMultiSort?: Array<{ key: string; dir: 'asc'|'desc' }>
	onMultiSortChange?: (multi: Array<{ key: string; dir: 'asc'|'desc' }>) => void
	virtualized?: boolean
	rowHeight?: number
}

export default function DataTable<T>(props: DataTableProps<T>) {
	const {
		columns,
		rows,
		stickyHeader = true,
		maxHeight = 'calc(100vh - 260px)',
		compact = false,
		sortKey,
		sortDir,
		onSortToggle,
		selectable = false,
		selectedIds = [],
		getRowId,
		onToggleSelect,
		onToggleSelectAll,
		showColumnSettings = true,
		onColumnsChange,
		storageKey,
		hasMore,
		loadingMore,
		onLoadMore,
		rowActions,
		initialMultiSort,
		onMultiSortChange,
		virtualized = false,
		rowHeight = 40,
	} = props

	type Density = 'comfortable' | 'normal' | 'compact'
	const [localColumns, setLocalColumns] = React.useState<Column<T>[]>(columns)
	const [density, setDensity] = React.useState<Density>(compact ? 'compact' : 'normal')
	const headerRefs = React.useRef<Record<string, HTMLTableCellElement | null>>({})
	const resizingRef = React.useRef<{ key: string; startX: number; startWidth: number } | null>(null)
	const [multiSort, setMultiSort] = React.useState<Array<{ key: string; dir: 'asc'|'desc' }>>(initialMultiSort || [])
	React.useEffect(() => { if (initialMultiSort) setMultiSort(initialMultiSort) }, [initialMultiSort])

	const scrollRef = React.useRef<HTMLDivElement | null>(null)
	const [scrollTop, setScrollTop] = React.useState(0)
	const [containerHeight, setContainerHeight] = React.useState(0)
	const [measuredRowHeight, setMeasuredRowHeight] = React.useState<number | null>(null)

	React.useEffect(() => {
		if (!virtualized) return
		const el = scrollRef.current
		if (!el) return
		function onScroll() { const node = scrollRef.current; if (!node) return; setScrollTop(node.scrollTop) }
		function onResize() { const node = scrollRef.current; if (!node) return; setContainerHeight(node.clientHeight) }
		onResize()
		el.addEventListener('scroll', onScroll)
		window.addEventListener('resize', onResize)
		return () => { el.removeEventListener('scroll', onScroll); window.removeEventListener('resize', onResize) }
	}, [virtualized])

	React.useEffect(() => {
		if (!virtualized) return
		const sc = scrollRef.current
		if (!sc) return
		const tbody = sc.querySelector('tbody') as HTMLTableSectionElement | null
		const tr = tbody?.querySelector('tr') as HTMLTableRowElement | null
		const h = tr?.offsetHeight
		if (h && h > 0 && h !== measuredRowHeight) setMeasuredRowHeight(h)
	}, [virtualized, rows, localColumns, measuredRowHeight])

	React.useEffect(() => {
		// Load persisted config if available
		let next = [...columns]
		if (storageKey) {
			try {
				const raw = localStorage.getItem(storageKey)
				if (raw) {
					const saved = JSON.parse(raw) as { order?: string[]; visibility?: Record<string, boolean>, density?: Density, widths?: Record<string, number | string>, labels?: Record<string,string>, aligns?: Record<string,'left'|'center'|'right'> }
					if (Array.isArray(saved.order)) {
						next.sort((a, b) => {
							const ia = saved.order!.indexOf(a.key)
							const ib = saved.order!.indexOf(b.key)
							return (ia === -1 ? 9999 : ia) - (ib === -1 ? 9999 : ib)
						})
					}
					if (saved.visibility) {
						next = next.map((c) => ({ ...c, visible: saved.visibility![c.key] ?? c.visible }))
					}
					if (saved.density) {
						setDensity(saved.density)
					}
					if (saved.widths) {
						next = next.map((c) => saved.widths && saved.widths[c.key] !== undefined ? ({ ...c, width: saved.widths[c.key] }) : c)
					}
					if (saved.labels) {
						next = next.map((c) => saved.labels && saved.labels[c.key] ? ({ ...c, label: saved.labels[c.key] }) : c)
					}
					if (saved.aligns) {
						next = next.map((c) => saved.aligns && saved.aligns[c.key] ? ({ ...c, align: saved.aligns[c.key] }) : c)
					}
				}
			} catch {}
		}
		setLocalColumns(next)
	}, [columns, storageKey])

	function persist(next: Column<T>[]) {
		if (!storageKey) return
		try {
			const order = next.map((c) => c.key)
			const visibility: Record<string, boolean> = {}
			const widths: Record<string, number | string> = {}
			const labels: Record<string, string> = {}
			const aligns: Record<string, 'left'|'center'|'right'> = {}
			next.forEach((c) => {
				visibility[c.key] = c.visible !== false
				if (c.width !== undefined) widths[c.key] = c.width
				if (c.label) labels[c.key] = c.label
				if (c.align) aligns[c.key] = c.align
			})
			const raw = localStorage.getItem(storageKey)
			let prev: any = {}
			try { prev = raw ? JSON.parse(raw) : {} } catch {}
			localStorage.setItem(storageKey, JSON.stringify({ ...prev, order, visibility, widths, labels, aligns, density }))
		} catch {}
	}

	function updateColumns(next: Column<T>[]) {
		setLocalColumns(next)
		onColumnsChange?.(next)
		persist(next)
	}

	function updateDensity(next: Density) {
		setDensity(next)
		persist(localColumns)
	}

	function handleDrag(srcIdx: number, dstIdx: number) {
		if (srcIdx === dstIdx) return
		const next = [...localColumns]
		const [moved] = next.splice(srcIdx, 1)
		next.splice(dstIdx, 0, moved)
		updateColumns(next)
	}

	const cellPadding = density === 'comfortable' ? 10 : density === 'compact' ? 6 : 8

	React.useEffect(() => {
		function onMove(e: MouseEvent) {
			const r = resizingRef.current
			if (!r) return
			const delta = e.clientX - r.startX
			const newWidth = Math.max(60, r.startWidth + delta)
			setLocalColumns((prev) => prev.map((c) => c.key === r.key ? ({ ...c, width: newWidth }) : c))
		}
		function onUp() {
			if (resizingRef.current) {
				persist(localColumns)
			}
			resizingRef.current = null
			document.removeEventListener('mousemove', onMove)
			document.removeEventListener('mouseup', onUp)
		}
		if (resizingRef.current) {
			document.addEventListener('mousemove', onMove)
			document.addEventListener('mouseup', onUp)
		}
		return () => {
			document.removeEventListener('mousemove', onMove)
			document.removeEventListener('mouseup', onUp)
		}
	}, [localColumns])

	function startResize(key: string, ev: React.MouseEvent) {
		ev.preventDefault()
		const th = headerRefs.current[key]
		const startWidth = th ? th.getBoundingClientRect().width : 120
		resizingRef.current = { key, startX: ev.clientX, startWidth }
	}

	function onHeaderClick(col: Column<T>, e: React.MouseEvent) {
		if ((e.target as HTMLElement).classList.contains('dt-resizer')) return
		if (!col.sortable) return
		if (e.shiftKey) {
			setMultiSort((prev) => {
				const idx = prev.findIndex((s) => s.key === col.key)
				if (idx >= 0) {
					const next = [...prev]
					next[idx] = { key: col.key, dir: (next[idx].dir === 'asc' ? 'desc' : 'asc') as ('asc'|'desc') }
					onMultiSortChange?.(next)
					return next
				}
				const next = [...prev, { key: col.key, dir: 'desc' as ('asc'|'desc') }]
				onMultiSortChange?.(next)
				return next
			})
		} else {
			setMultiSort((prev) => {
				const current = prev.find((s) => s.key === col.key)
				const dir = (current ? (current.dir === 'asc' ? 'desc' : 'asc') : 'desc') as ('asc'|'desc')
				const next = [{ key: col.key, dir }]
				onMultiSortChange?.(next)
				return next
			})
			onSortToggle?.(col.key)
		}
	}

	const sortedRows = React.useMemo(() => {
		if (!multiSort.length) return rows
		const getVal = (row: any, key: string) => row?.[key]
		const copy = [...rows]
		copy.sort((a: any, b: any) => {
			for (const s of multiSort) {
				const va = getVal(a, s.key)
				const vb = getVal(b, s.key)
				if (va == null && vb == null) continue
				if (va == null) return s.dir === 'asc' ? -1 : 1
				if (vb == null) return s.dir === 'asc' ? 1 : -1
				if (va < vb) return s.dir === 'asc' ? -1 : 1
				if (va > vb) return s.dir === 'asc' ? 1 : -1
			}
			return 0
		})
		return copy
	}, [rows, multiSort, localColumns])

	const totalCols = (selectable ? 1 : 0) + localColumns.filter(c => c.visible !== false).length + (rowActions ? 1 : 0)
	const buffer = 5
	const effectiveRowHeight = virtualized ? (measuredRowHeight || rowHeight) : rowHeight
	const startIndex = virtualized ? Math.max(0, Math.floor(scrollTop / effectiveRowHeight) - buffer) : 0
	const visibleCount = virtualized ? Math.ceil((containerHeight || 0) / effectiveRowHeight) + buffer * 2 : sortedRows.length
	const endIndex = virtualized ? Math.min(sortedRows.length, startIndex + visibleCount) : sortedRows.length
	const topSpacer = virtualized ? startIndex * effectiveRowHeight : 0
	const bottomSpacer = virtualized ? (sortedRows.length - endIndex) * effectiveRowHeight : 0
	const visibleRows = virtualized ? sortedRows.slice(startIndex, endIndex) : sortedRows

	return (
		<div>
			{showColumnSettings && (
				<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
					<div className="badge">Tablo</div>
					<details>
						<summary style={{ cursor: 'pointer' }}>Kolonlar ▾</summary>
						<div className="card" style={{ padding: 8, marginTop: 8, minWidth: 280 }}>
							<div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
								<button onClick={() => updateColumns(localColumns.map(c => ({ ...c, visible: true })))}>Tümünü Seç</button>
								<button onClick={() => updateColumns(localColumns.map(c => ({ ...c, visible: false })))}>Temizle</button>
								<button onClick={() => { localStorage.removeItem(storageKey || ''); setLocalColumns(columns); setDensity(compact ? 'compact' : 'normal') }} disabled={!storageKey}>Sıfırla</button>
							</div>
							<div style={{ display: 'grid', gap: 6, marginBottom: 8 }}>
								<label style={{ fontSize: 12, color: '#64748b' }}>Yoğunluk</label>
								<div style={{ display: 'flex', gap: 8 }}>
									<label><input type="radio" name="dt-density" checked={density==='comfortable'} onChange={() => updateDensity('comfortable')} /> Rahat</label>
									<label><input type="radio" name="dt-density" checked={density==='normal'} onChange={() => updateDensity('normal')} /> Normal</label>
									<label><input type="radio" name="dt-density" checked={density==='compact'} onChange={() => updateDensity('compact')} /> Sıkı</label>
								</div>
							</div>
							<ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
								{localColumns.map((col, idx) => (
									<li key={col.key}
										draggable
										onDragStart={(e) => { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(idx)) }}
										onDragOver={(e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move' }}
										onDrop={(e) => { const from = Number(e.dataTransfer.getData('text/plain') || '-1'); if (from >= 0) handleDrag(from, idx) }}
										style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '6px 4px', cursor: 'grab' }}
									>
										<input type="checkbox" checked={col.visible !== false}
											onChange={(e) => {
												const next = [...localColumns]
												next[idx] = { ...col, visible: e.currentTarget.checked }
												updateColumns(next)
											}}
										/>
										<span style={{ flex: 1 }}>{col.label}</span>
										<button onClick={() => {
											const name = window.prompt('Yeni başlık', col.label)
											if (name !== null) {
												const next = [...localColumns]
												next[idx] = { ...col, label: name }
												updateColumns(next)
											}
										}}>Yeniden Adlandır</button>
										<select value={col.align || 'left'} onChange={(e) => {
											const next = [...localColumns]
											next[idx] = { ...col, align: e.currentTarget.value as any }
											updateColumns(next)
										}}>
											<option value="left">Sol</option>
											<option value="center">Orta</option>
											<option value="right">Sağ</option>
										</select>
										<span style={{ color: '#64748b', fontSize: 12 }}>≡</span>
									</li>
								))}
							</ul>
						</div>
					</details>
				</div>
			)}
			<div style={{ overflow: 'auto', maxHeight }} ref={scrollRef}>
			<table style={{ width: '100%', borderCollapse: 'collapse' }}>
				<thead>
					<tr>
						{selectable && (
							<th style={{ position: stickyHeader ? 'sticky' as const : undefined, top: 0, background: 'var(--bg)', padding: cellPadding }}>
								<input
									type="checkbox"
									checked={rows.length > 0 && selectedIds.length > 0 && selectedIds.length === rows.length}
									onChange={(e) => onToggleSelectAll?.(e.currentTarget.checked)}
								/>
							</th>
						)}
						{localColumns.filter(c => c.visible !== false).map((col) => (
							<th
								key={col.key}
								ref={(el) => { headerRefs.current[col.key] = el }}
								style={{ position: stickyHeader ? 'sticky' as const : undefined, top: 0, background: 'var(--bg)', textAlign: 'left', borderBottom: '1px solid #ddd', padding: cellPadding, cursor: col.sortable ? 'pointer' : 'default', width: col.width, userSelect: 'none' }}
								onClick={(e) => onHeaderClick(col, e)}
							>
								<span>
									{col.label} {col.sortable && sortKey === col.key ? (sortDir === 'asc' ? '▲' : '▼') : ''}
									{multiSort.findIndex((s) => s.key === col.key) >= 0 && (
										<sup style={{ marginLeft: 4 }}>{multiSort.findIndex((s) => s.key === col.key) + 1}</sup>
									)}
								</span>
								<span className="dt-resizer" onMouseDown={(e) => startResize(col.key, e)} style={{ position: 'absolute', right: 0, top: 0, width: 6, height: '100%', cursor: 'col-resize' }} />
							</th>
						))}
						{rowActions && (
							<th style={{ position: stickyHeader ? 'sticky' as const : undefined, top: 0, background: 'var(--bg)', borderBottom: '1px solid #ddd', padding: cellPadding }} />
						)}
					</tr>
				</thead>
				<tbody>
					{virtualized && topSpacer > 0 && (
						<tr><td colSpan={totalCols} style={{ height: topSpacer }} /></tr>
					)}
					{visibleRows.map((row) => {
						const id = getRowId(row)
						return (
							<tr key={id as any} style={virtualized ? { height: effectiveRowHeight } : undefined}>
								{selectable && (
									<td style={{ padding: cellPadding }}>
										<input
											type="checkbox"
											checked={selectedIds.includes(id)}
											onChange={(e) => onToggleSelect?.(id, e.currentTarget.checked)}
										/>
									</td>
								)}
								{localColumns.filter(c => c.visible !== false).map((col) => (
									<td key={col.key} style={{ padding: cellPadding, textAlign: col.align || 'left' }}>
										{col.render ? col.render(row) : (row as any)[col.key]}
									</td>
								))}
								{rowActions && (
									<td style={{ padding: cellPadding, textAlign: 'right' }}>
										<details>
											<summary style={{ cursor: 'pointer' }}>⋯</summary>
											<div className="card" style={{ padding: 8, position: 'absolute', right: 0 }}>
												{rowActions(row)}
											</div>
										</details>
									</td>
								)}
							</tr>
						)
					})}
					{virtualized && bottomSpacer > 0 && (
						<tr><td colSpan={totalCols} style={{ height: bottomSpacer }} /></tr>
					)}
				</tbody>
			</table>
			</div>
			{hasMore !== undefined && (
				<div style={{ marginTop: 12, display: 'flex', justifyContent: 'center' }}>
					<button onClick={onLoadMore} disabled={!hasMore || !!loadingMore}>{loadingMore ? 'Yükleniyor…' : hasMore ? 'Daha Fazla Yükle' : 'Hepsi yüklendi'}</button>
				</div>
			)}
		</div>
	)
}


