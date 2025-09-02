import { useInfiniteQuery } from '@tanstack/react-query'
import * as React from 'react'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import Skeleton from '../components/ui/Skeleton'
import { useSearchParams } from 'react-router-dom'
import DataTable, { type Column } from '../components/ui/DataTable'
import { useToast } from '../components/ui/ToastProvider'
function exportXlsx(rows: any[]) {
  import('xlsx').then((XLSX) => {
    const ws = XLSX.utils.json_to_sheet(rows)
    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Invoices'); XLSX.writeFile(wb, 'invoices.xlsx')
  })
}

async function fetchInvoices({ pageParam, filters }: { pageParam?: string, filters?: Record<string, string> }) {
	const params: Record<string, string> = { ...(filters || {}) }
	if (pageParam) params['page[after]'] = pageParam
	const res = await api.get('/invoices', { params })
	return {
		items: res.data?.data ?? [],
		next: res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null,
	}
}

export default function InvoicesPage() {
	const [searchParams, setSearchParams] = useSearchParams()
	const toast = useToast()
	const [q, setQ] = React.useState<string>(() => searchParams.get('q') || '')
	const [qDebounced, setQDebounced] = React.useState<string>(q)
	const searchRef = React.useRef<HTMLInputElement>(null)
	const [status, setStatus] = React.useState<string>(() => searchParams.get('status') || '')
	const [type, setType] = React.useState<string>(() => searchParams.get('type') || '')
	const [sortKey, setSortKey] = React.useState<'id'|'issue_date'|'status'|'grand_total'>(() => (searchParams.get('sort') as any) || 'id')
	const [sortDir, setSortDir] = React.useState<'asc'|'desc'>(() => (searchParams.get('order') as any) || 'desc')

	React.useEffect(() => {
		const t = setTimeout(() => setQDebounced(q), 300)
		return () => clearTimeout(t)
	}, [q])
	React.useEffect(() => {
		function onKey(e: KeyboardEvent) {
			if (e.key === '/') { e.preventDefault(); searchRef.current?.focus() }
			if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); searchRef.current?.focus() }
			if (e.key === 'Escape' && document.activeElement === searchRef.current) (document.activeElement as HTMLElement).blur()
		}
		document.addEventListener('keydown', onKey)
		return () => document.removeEventListener('keydown', onKey)
	}, [])

	const filters = React.useMemo(() => ({ ...(qDebounced ? { q: qDebounced } : {}), ...(status ? { status } : {}), ...(type ? { type } : {}), sort: sortKey, order: sortDir }), [qDebounced, status, type, sortKey, sortDir])
	const { data, isLoading, isError, fetchNextPage, hasNextPage, isFetchingNextPage, refetch } = useInfiniteQuery({
		queryKey: ['invoices', filters],
		queryFn: ({ pageParam }) => fetchInvoices({ pageParam, filters }),
		getNextPageParam: (lastPage) => lastPage.next || undefined,
		initialPageParam: undefined,
	})
	const rows = (data?.pages ?? []).flatMap((p) => p.items)

	function exportCsvCurrent() {
		const header = ['id','status','customer','grand_total','currency','issue_date']
		const lines = ['\uFEFF' + header.join(',')]
		for (const inv of rows as any[]) {
			const amount = inv?.totals?.grandTotal?.amount ?? ''
			const currency = inv?.totals?.grandTotal?.currency ?? ''
			const customer = (inv?.customer?.name ?? '').toString().replace(/\"/g,'\"\"')
			const cols = [inv.id, inv.status, `\"${customer}\"`, amount, currency, inv.issueDate ?? '']
			lines.push(cols.join(','))
		}
		const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		a.href = url
		a.download = 'invoices.csv'
		a.click()
		URL.revokeObjectURL(url)
	}
	function exportXlsxCurrent() {
		const dataRows = (rows as any[]).map((inv) => ({
			ID: inv.id,
			Durum: inv.status,
			Müşteri: inv?.customer?.name ?? '',
			Toplam: inv?.totals?.grandTotal?.amount ?? '',
			ParaBirimi: inv?.totals?.grandTotal?.currency ?? '',
			Tarih: inv.issueDate ?? '',
		}))
		const ws = XLSX.utils.json_to_sheet(dataRows)
		const wb = XLSX.utils.book_new()
		XLSX.utils.book_append_sheet(wb, ws, 'Faturalar')
		XLSX.writeFile(wb, 'invoices.xlsx')
	}

	const [selectedIds, setSelectedIds] = React.useState<number[]>([])
	function toggleSelect(id: number, checked: boolean) {
		setSelectedIds((prev) => checked ? Array.from(new Set([...prev, id])) : prev.filter((x) => x !== id))
	}
	function toggleSelectAll(checked: boolean) {
		if (checked) setSelectedIds(rows.map((r: any) => r.id))
		else setSelectedIds([])
	}

	React.useEffect(() => {
		const sp = new URLSearchParams()
		if (q) sp.set('q', q)
		if (status) sp.set('status', status)
		if (type) sp.set('type', type)
		if (sortKey) sp.set('sort', sortKey)
		if (sortDir) sp.set('order', sortDir)
		if (searchParams.get('ms')) sp.set('ms', searchParams.get('ms') as string)
		setSearchParams(sp, { replace: true })
	}, [q, status, type, sortKey, sortDir, setSearchParams])

	function toggleSort(key: 'id'|'issue_date'|'status'|'grand_total') {
		if (sortKey === key) setSortDir(sortDir === 'asc' ? 'desc' : 'asc')
		else { setSortKey(key); setSortDir('desc') }
	}

	function parseMultiSortParam(value: string | null): Array<{ key: string; dir: 'asc'|'desc' }> {
		if (!value) return []
		return value.split(',').map(pair => {
			const [k,d] = pair.split(':')
			return { key: k, dir: (d === 'asc' ? 'asc' : 'desc') as ('asc'|'desc') }
		}).filter(s => !!s.key)
	}
	function setMultiSortParam(list: Array<{ key: string; dir: 'asc'|'desc' }>) {
		const sp = new URLSearchParams(searchParams)
		if (list.length) sp.set('ms', list.map(s => `${s.key}:${s.dir}`).join(','))
		else sp.delete('ms')
		setSearchParams(sp, { replace: true })
		// Backend sıralaması için ilk anahtar
		if (list.length) { setSortKey(list[0].key as any); setSortDir(list[0].dir) }
	}

	const initialMulti = React.useMemo(() => parseMultiSortParam(searchParams.get('ms')), [])

	return (
		<div>
			<PageHeader
				title="Faturalar"
				actions={<a href="/invoice/new"><button>Yeni Fatura</button></a>}
				crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Faturalar' }]}
			/>
			<div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 12 }}>
				<input ref={searchRef} placeholder="Ara (müşteri)" value={q} onChange={(e) => setQ(e.target.value)} />
				<select value={status} onChange={(e) => setStatus(e.target.value)}>
					<option value="">Durum</option>
					<option value="queued">Queued</option>
					<option value="processing">Processing</option>
					<option value="sent">Sent</option>
					<option value="failed">Failed</option>
					<option value="canceled">Canceled</option>
				</select>
				<select value={type} onChange={(e) => setType(e.target.value)}>
					<option value="">Tür</option>
					<option value="e_fatura">E‑Fatura</option>
					<option value="e_arsiv">E‑Arşiv</option>
				</select>
				<div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
					<button onClick={exportCsvCurrent}>CSV İndir</button>
					<button onClick={exportXlsxCurrent}>XLSX İndir</button>
				</div>
			</div>

			{isLoading && (
				<div style={{ display: 'grid', gap: 8 }}>
					<Skeleton height={36} />
					<Skeleton height={36} />
					<Skeleton height={36} />
					<Skeleton height={36} />
					<Skeleton height={36} />
				</div>
			)}
			{isError && <div>Hata oluştu</div>}
			{!isLoading && !isError && rows.length === 0 && (
				<div className="card" style={{ padding: 24 }}>Kayıt bulunamadı</div>
			)}
			{!isLoading && !isError && rows.length > 0 && (
				<DataTable
					columns={[
						{ key: 'id', label: 'ID', sortable: true },
						{ key: 'status', label: 'Durum', sortable: true, render: (r: any) => {
							const s = String(r.status || '').toLowerCase()
							let bg = 'rgba(2,6,23,.06)', fg = '#0f172a', bd = 'rgba(2,6,23,.12)'
							if (s === 'sent') { bg = 'rgba(34,197,94,.12)'; fg = '#166534'; bd = 'rgba(34,197,94,.25)' }
							else if (s === 'failed') { bg = 'rgba(239,68,68,.12)'; fg = '#991b1b'; bd = 'rgba(239,68,68,.25)' }
							else if (s === 'queued' || s === 'processing') { bg = 'rgba(37,99,235,.10)'; fg = '#1d4ed8'; bd = 'rgba(37,99,235,.25)' }
							else if (s === 'canceled') { bg = 'rgba(100,116,139,.12)'; fg = '#334155'; bd = 'rgba(100,116,139,.25)' }
							return (
								<span style={{ padding: '2px 10px', borderRadius: 999, background: bg, color: fg, border: `1px solid ${bd}`, fontSize: 12, fontWeight: 600, textTransform: 'uppercase' }}>{r.status}</span>
							)
						}},
						{ key: 'customer', label: 'Müşteri', render: (r: any) => r.customer?.name ?? '-' },
						{ key: 'grand_total', label: 'Toplam', sortable: true, render: (r: any) => `${r?.totals?.grandTotal?.amount ?? ''} ${r?.totals?.grandTotal?.currency ?? ''}` },
						{ key: 'issue_date', label: 'Tarih', sortable: true, render: (r: any) => r.issueDate ?? '-' },
						{ key: 'actions', label: '', render: (r: any) => (<a href={`/invoice/${r.id}`}><button>Detay</button></a>) },
					] as Array<Column<any>>}
					rows={rows as any[]}
					stickyHeader
					maxHeight={'calc(100vh - 260px)'}
					sortKey={sortKey}
					sortDir={sortDir}
					onSortToggle={(key) => toggleSort(key as any)}
					selectable
					selectedIds={selectedIds as any}
					getRowId={(r: any) => r.id}
					onToggleSelect={(id, checked) => toggleSelect(id as number, checked)}
					onToggleSelectAll={(checked) => toggleSelectAll(checked)}
					storageKey="dt/invoices"
					hasMore={!!hasNextPage}
					loadingMore={!!isFetchingNextPage}
					onLoadMore={() => fetchNextPage()}
					initialMultiSort={initialMulti}
					onMultiSortChange={(list) => setMultiSortParam(list)}
					virtualized
					rowHeight={40}
					rowActions={(r: any) => (
						<div style={{ display: 'grid', gap: 6 }}>
							<button onClick={async () => { try { await api.post(`/invoices/${r.id}/retry`); toast.show({ type: 'success', title: 'Yeniden denendi' }); refetch() } catch { toast.show({ type: 'error', title: 'İşlem başarısız' }) } }}>Yeniden Dene</button>
							<button onClick={async () => { try { await api.post(`/invoices/${r.id}/cancel`); toast.show({ type: 'success', title: 'İptal edildi' }); refetch() } catch { toast.show({ type: 'error', title: 'İşlem başarısız' }) } }}>İptal Et</button>
						</div>
					)}
				/>
			)}
		</div>
	)
}


