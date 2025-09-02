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
    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Vouchers'); XLSX.writeFile(wb, 'vouchers.xlsx')
  })
}
import { useConfirm } from '../components/ui/ConfirmProvider'

async function fetchVouchers({ pageParam, filters }: { pageParam?: string, filters?: Record<string, string> }) {
	const params: Record<string, string> = { ...(filters || {}) }
	if (pageParam) params['page[after]'] = pageParam
	const res = await api.get('/vouchers', { params })
	return { items: res.data?.data ?? [], next: res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null }
}

export default function VouchersPage() {
	const [searchParams, setSearchParams] = useSearchParams()
	const toast = useToast()
	const confirm = useConfirm()
	const [status, setStatus] = React.useState<string>(() => searchParams.get('status') || '')
	const [type, setType] = React.useState<string>(() => searchParams.get('type') || '')
	const [ettn, setEttn] = React.useState<string>(() => searchParams.get('ettn') || '')
	const [sortKey, setSortKey] = React.useState<'id'|'status'|'type'>(() => (searchParams.get('sort') as any) || 'id')
	const [sortDir, setSortDir] = React.useState<'asc'|'desc'>(() => (searchParams.get('order') as any) || 'desc')
	const filters = React.useMemo(() => ({ ...(status ? { status } : {}), ...(type ? { type } : {}), ...(ettn ? { ettn } : {}), sort: sortKey, order: sortDir }), [status, type, ettn, sortKey, sortDir])
	const { data, isLoading, isError, hasNextPage, isFetchingNextPage, fetchNextPage, refetch } = useInfiniteQuery({
		queryKey: ['vouchers', filters],
		queryFn: ({ pageParam }) => fetchVouchers({ pageParam, filters }),
		getNextPageParam: (lastPage) => lastPage.next || undefined,
		initialPageParam: undefined,
	})
	const rows = (data?.pages ?? []).flatMap((p) => p.items)

	React.useEffect(() => {
		const sp = new URLSearchParams()
		if (status) sp.set('status', status)
		if (type) sp.set('type', type)
		if (ettn) sp.set('ettn', ettn)
		if (sortKey) sp.set('sort', sortKey)
		if (sortDir) sp.set('order', sortDir)
		if (searchParams.get('ms')) sp.set('ms', searchParams.get('ms') as string)
		setSearchParams(sp, { replace: true })
	}, [status, type, ettn, sortKey, sortDir, setSearchParams])

	function toggleSort(key: 'id'|'status'|'type') {
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
		if (list.length) { setSortKey(list[0].key as any); setSortDir(list[0].dir) }
	}
	const initialMulti = React.useMemo(() => parseMultiSortParam(searchParams.get('ms')), [])

	const [selectedIds, setSelectedIds] = React.useState<number[]>([])
	const [bulkBusy, setBulkBusy] = React.useState(false)
	function toggleSelect(id: number, checked: boolean) {
		setSelectedIds((prev) => checked ? Array.from(new Set([...prev, id])) : prev.filter((x) => x !== id))
	}
	function toggleSelectAll(checked: boolean) {
		if (checked) setSelectedIds(rows.map((r: any) => r.id))
		else setSelectedIds([])
	}
	async function bulkRetry() {
		if (!selectedIds.length) return
		const ok = await confirm({ title: 'Yeniden dene', description: `${selectedIds.length} kayıt yeniden denensin mi?` })
		if (!ok) return
		setBulkBusy(true)
		try {
			const results = await Promise.allSettled(selectedIds.map((id) => api.post(`/vouchers/${id}/retry`)))
			const success = results.filter(r => r.status === 'fulfilled').length
			const fail = results.length - success
			toast.show({ type: fail ? 'warning' : 'success', title: `Yeniden deneme bitti`, description: `${success} başarılı, ${fail} başarısız` })
			refetch()
		} finally {
			setBulkBusy(false)
		}
	}
	async function bulkCancel() {
		if (!selectedIds.length) return
		const ok = await confirm({ title: 'İptal', description: `${selectedIds.length} kayıt iptal edilsin mi?`, variant: 'danger', confirmText: 'İptal Et' })
		if (!ok) return
		setBulkBusy(true)
		try {
			const results = await Promise.allSettled(selectedIds.map((id) => api.post(`/vouchers/${id}/cancel`)))
			const success = results.filter(r => r.status === 'fulfilled').length
			const fail = results.length - success
			toast.show({ type: fail ? 'warning' : 'success', title: `İptal işlemi bitti`, description: `${success} başarılı, ${fail} başarısız` })
			refetch()
		} finally { setBulkBusy(false) }
	}
	function exportCsvCurrent() {
		const header = ['id','status','type','ettn']
		const lines = ['\uFEFF' + header.join(',')]
		for (const r of rows as any[]) {
			const cols = [r.id, r.status, r.type, r.ettn ?? '']
			lines.push(cols.join(','))
		}
		const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		a.href = url
		a.download = 'vouchers.csv'
		a.click()
		URL.revokeObjectURL(url)
	}
	function exportXlsxCurrent() {
		const ws = XLSX.utils.json_to_sheet((rows as any[]).map((r) => ({ ID: r.id, Durum: r.status, Tip: r.type, ETTN: r.ettn ?? '' })))
		const wb = XLSX.utils.book_new()
		XLSX.utils.book_append_sheet(wb, ws, 'Makbuzlar')
		XLSX.writeFile(wb, 'vouchers.xlsx')
	}
	return (
		<div style={{ padding: 24 }}>
			<PageHeader title="E‑Makbuzlar" actions={<a href="/vouchers/new"><button>Yeni Makbuz</button></a>} crumbs={[{ label: 'Panel', href: '/app' }, { label: 'E‑Makbuzlar' }]} />
			<div style={{ display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap', marginBottom: 12 }}>
				<select value={status} onChange={(e) => setStatus(e.target.value)}>
					<option value="">Durum</option>
					<option value="queued">Queued</option>
					<option value="processing">Processing</option>
					<option value="sent">Sent</option>
					<option value="failed">Failed</option>
					<option value="canceled">Canceled</option>
				</select>
				<select value={type} onChange={(e) => setType(e.target.value)}>
					<option value="">Tip</option>
					<option value="SMM">SMM</option>
					<option value="MM">MM</option>
				</select>
				<input placeholder="ETTN" value={ettn} onChange={(e) => setEttn(e.target.value)} />
				<div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
					<button onClick={exportCsvCurrent}>CSV İndir</button>
					<button onClick={exportXlsxCurrent}>XLSX İndir</button>
				</div>
			</div>
			{selectedIds.length > 0 && (
				<div style={{ display: 'flex', gap: 8, marginBottom: 12 }}>
					<button onClick={bulkRetry} disabled={bulkBusy}>Seçilileri Yeniden Dene</button>
					<button onClick={bulkCancel} disabled={bulkBusy} className="btn-danger">Seçilileri İptal Et</button>
					<div className="badge">{selectedIds.length} seçili</div>
				</div>
			)}
			{isLoading && (
				<div style={{ display: 'grid', gap: 8 }}>
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
							return (<span style={{ padding: '2px 10px', borderRadius: 999, background: bg, color: fg, border: `1px solid ${bd}`, fontSize: 12, fontWeight: 600, textTransform: 'uppercase' }}>{r.status}</span>)
						}},
						{ key: 'type', label: 'Tip', sortable: true },
						{ key: 'ettn', label: 'ETTN' },
						{ key: 'actions', label: '', render: (r: any) => (<a href={`/vouchers/${r.id}`}><button>Detay</button></a>) },
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
					storageKey="dt/vouchers"
					hasMore={!!hasNextPage}
					loadingMore={!!isFetchingNextPage}
					onLoadMore={() => fetchNextPage()}
					initialMultiSort={initialMulti}
					onMultiSortChange={(list) => setMultiSortParam(list)}
					virtualized
					rowHeight={40}
					rowActions={(r: any) => (
						<div style={{ display: 'grid', gap: 6 }}>
							<button onClick={async () => { try { const ok = await confirm({ title: 'Yeniden dene', description: `Makbuz #${r.id} yeniden denensin mi?` }); if (!ok) return; await api.post(`/vouchers/${r.id}/retry`); toast.show({ type: 'success', title: 'Yeniden denendi' }); refetch() } catch { toast.show({ type: 'error', title: 'İşlem başarısız' }) } }}>Yeniden Dene</button>
							<button onClick={async () => { try { const ok = await confirm({ title: 'İptal', description: `Makbuz #${r.id} iptal edilsin mi?`, variant: 'danger', confirmText: 'İptal Et' }); if (!ok) return; await api.post(`/vouchers/${r.id}/cancel`); toast.show({ type: 'success', title: 'İptal edildi' }); refetch() } catch { toast.show({ type: 'error', title: 'İşlem başarısız' }) } }} className="btn-danger">İptal Et</button>
						</div>
					)}
				/>
			)}
		</div>
	)
}



