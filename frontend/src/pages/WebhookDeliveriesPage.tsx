import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useState, useEffect } from 'react'
import { useToast } from '../components/ui/ToastProvider'
import DataTable, { type Column } from '../components/ui/DataTable'
import { lazy, Suspense } from 'react'
const XLSXLazy = lazy(() => import('xlsx'))
import { useNotifications } from '../components/ui/NotificationProvider'
import { useSearchParams, Link } from 'react-router-dom'
import Skeleton from '../components/ui/Skeleton'

async function fetchDeliveries({ pageParam, status, q, from, to }: { pageParam?: string | null, status?: string, q?: string, from?: string, to?: string }) {
  const params: Record<string, string> = {}
  if (status) params.status = status
  if (q) params.q = q
  if (from) params.from = from
  if (to) params.to = to
  if (pageParam) params['page[after]'] = pageParam
  const res = await api.get('/webhooks/deliveries', { params })
  return { items: res.data?.data ?? [], next: (res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null) as string | null }
}

export default function WebhookDeliveriesPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const notif = useNotifications()
  const [searchParams, setSearchParams] = useSearchParams()
  const [status, setStatus] = useState(() => searchParams.get('status') || '')
  const [q, setQ] = useState(() => searchParams.get('q') || '')
  const [from, setFrom] = useState(() => searchParams.get('from') || '')
  const [to, setTo] = useState(() => searchParams.get('to') || '')
  const [presets, setPresets] = useState<Array<{ name: string; q?: string; status?: string; from?: string; to?: string }>>(() => {
    try { return JSON.parse(localStorage.getItem('presets/webhook_deliveries') || '[]') } catch { return [] }
  })
  const [selectedPreset, setSelectedPreset] = useState<string>('')
  const [newPresetName, setNewPresetName] = useState<string>('')

  useEffect(() => {
    const noUrlFilters = !searchParams.get('q') && !searchParams.get('status') && !searchParams.get('from') && !searchParams.get('to')
    if (noUrlFilters) {
      try {
        const savedRaw = localStorage.getItem('filters/webhook_deliveries')
        if (savedRaw) {
          const saved = JSON.parse(savedRaw) as { q?: string; status?: string; from?: string; to?: string }
          setQ(saved.q || '')
          setStatus(saved.status || '')
          setFrom(saved.from || '')
          setTo(saved.to || '')
        }
      } catch {}
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    const sp = new URLSearchParams()
    if (q) sp.set('q', q)
    if (status) sp.set('status', status)
    if (from) sp.set('from', from)
    if (to) sp.set('to', to)
    setSearchParams(sp, { replace: true })
    try { localStorage.setItem('filters/webhook_deliveries', JSON.stringify({ q, status, from, to })) } catch {}
  }, [q, status, from, to, setSearchParams])

  function savePreset() {
    const name = newPresetName.trim()
    if (!name) return
    const next = presets.filter(p => p.name !== name)
    next.unshift({ name, q: q || undefined, status: status || undefined, from: from || undefined, to: to || undefined })
    setPresets(next)
    try { localStorage.setItem('presets/webhook_deliveries', JSON.stringify(next)) } catch {}
    setSelectedPreset(name)
  }
  function applyPreset() {
    const p = presets.find(pr => pr.name === selectedPreset)
    if (!p) return
    setQ(p.q || '')
    setStatus(p.status || '')
    setFrom(p.from || '')
    setTo(p.to || '')
  }
  function deletePreset() {
    if (!selectedPreset) return
    const next = presets.filter(p => p.name !== selectedPreset)
    setPresets(next)
    try { localStorage.setItem('presets/webhook_deliveries', JSON.stringify(next)) } catch {}
    setSelectedPreset('')
  }

  const list = useInfiniteQuery<any, any, any, any, string | null>({
    queryKey: ['webhook_deliveries', status, q, from, to],
    queryFn: ({ pageParam }) => fetchDeliveries({ pageParam: pageParam ?? null, status: status || undefined, q: q || undefined, from: from || undefined, to: to || undefined }),
    getNextPageParam: (last) => last.next || undefined,
    initialPageParam: null,
  })
  const rows = (((list.data?.pages as unknown) as any[]) ?? []).flatMap((p: any) => p.items)
  const hasError = (list as any).isError as boolean || false
  const errorObj = (list as any).error as any
  const isInitialLoading = ((list as any).isLoading as boolean) || (rows.length === 0 && (list as any).isFetching)
  const failedRows = (rows as any[]).filter((r: any) => r.status === 'fail')
  const [selectedIds, setSelectedIds] = useState<Array<number>>([])
  useEffect(() => {
    try { localStorage.setItem('ids/webhook_deliveries', JSON.stringify((rows as any[]).map((r: any) => r.id))) } catch {}
  }, [rows])
  const replay = useMutation({
    mutationFn: async (id: number) => { await api.post(`/webhooks/deliveries/${id}/replay`) },
    onSuccess: (_data, id) => {
      toast.show({ type: 'success', title: 'Yeniden gönderildi' })
      notif.push({ type: 'ok', title: `Webhook #${id} yeniden denendi`, href: `/app/webhooks/deliveries/${id}` })
      qc.invalidateQueries({ queryKey: ['webhook_deliveries'] })
    },
    onError: () => toast.show({ type: 'error', title: 'İşlem başarısız' }),
  })

  const [detail, setDetail] = useState<any | null>(null)

  function setQuickRange(days: number) {
    const end = new Date()
    const start = new Date()
    start.setDate(end.getDate() - days)
    const fmt = (d: Date) => d.toISOString().slice(0, 10)
    setFrom(fmt(start))
    setTo(fmt(end))
  }

  function exportCsv() {
    const header = ['id','event','status','attempts','last_attempt_at']
    const lines = ['\uFEFF' + header.join(',')]
    for (const d of rows as any[]) {
      lines.push([d.id, d.event, d.status, d.attempt_count, d.last_attempt_at || ''].join(','))
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a'); a.href = url; a.download = 'webhook_deliveries.csv'; a.click(); URL.revokeObjectURL(url)
  }
  function exportXlsx() {
    import('xlsx').then((XLSX) => {
      const ws = XLSX.utils.json_to_sheet((rows as any[]).map(d => ({ ID: d.id, Event: d.event, Durum: d.status, Deneme: d.attempt_count, SonDeneme: d.last_attempt_at || '' })))
      const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Deliveries'); XLSX.writeFile(wb, 'webhook_deliveries.xlsx')
    })
  }

  function renderStatus(d: any) {
    const ok = d.status === 'ok'
    const bg = ok ? '#dcfce7' : '#fee2e2'
    const fg = ok ? '#166534' : '#991b1b'
    return <span style={{ padding: '2px 8px', borderRadius: 8, background: bg, color: fg }}>{d.status}</span>
  }
  function nextAttemptAt(d: any): string {
    if (!d || d.status === 'ok') return ''
    const backoff = [60, 300, 1800, 7200, 86400]
    const attempt = Math.max(0, Number(d.attempt_count ?? 0))
    const idx = Math.min(attempt, backoff.length - 1)
    const lastStr = d.last_attempt_at || d.created_at
    if (!lastStr) return ''
    const base = new Date(lastStr)
    const eta = new Date(base.getTime() + backoff[idx] * 1000)
    return eta.toLocaleString()
  }

  const columns: Array<Column<any>> = [
    { key: 'id', label: 'ID' },
    { key: 'event', label: 'Event' },
    { key: 'status', label: 'Durum', render: renderStatus },
    { key: 'attempt_count', label: 'Deneme', align: 'right' },
    { key: 'last_attempt_at', label: 'Son Deneme' },
    { key: 'next_attempt_eta', label: 'Sonraki Deneme', render: (d) => nextAttemptAt(d) },
    { key: 'last_error', label: 'Hata', render: (d) => {
      const msg = (d?.response?.error) || (d?.response?.message) || (d?.error_message) || ''
      const text = typeof msg === 'string' ? msg : JSON.stringify(msg)
      const trimmed = text.length > 60 ? text.slice(0, 57) + '…' : text
      return text ? <span title={text} style={{ color: '#991b1b' }}>{trimmed}</span> : ''
    } },
  ]

  return (
    <div style={{ padding: 24 }}>
      <h2>Webhook Teslimatları</h2>
      <div className="card" style={{ padding: 12, marginBottom: 12, display: 'grid', gap: 6 }}>
        <div style={{ fontWeight: 600 }}>Retry Politikası</div>
        <div style={{ color: '#64748b' }}>Başarısız teslimatlar artan aralıklarla otomatik tekrar denenir: 1 dk → 5 dk → 30 dk → 2 sa → 24 sa.</div>
      </div>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12, flexWrap: 'wrap' }}>
        <input placeholder="Ara (event/url)" value={q} onChange={(e) => setQ(e.target.value)} style={{ width: 240 }} />
        <select value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Durum</option>
          <option value="ok">OK</option>
          <option value="fail">FAIL</option>
        </select>
        <label>Başlangıç<input type="date" value={from} onChange={(e) => setFrom(e.target.value)} style={{ marginLeft: 6 }} /></label>
        <label>Bitiş<input type="date" value={to} onChange={(e) => setTo(e.target.value)} style={{ marginLeft: 6 }} /></label>
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={() => setQuickRange(1)}>Son 1g</button>
          <button onClick={() => setQuickRange(7)}>7g</button>
          <button onClick={() => setQuickRange(30)}>30g</button>
        </div>
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={() => { setStatus('ok'); setQ('') }} className="btn-secondary">OK</button>
          <button onClick={() => { setStatus('fail'); setQ('') }} className="btn-secondary">FAIL</button>
          <button onClick={() => { setStatus(''); setQ('error') }} className="btn-secondary">"error"</button>
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <button onClick={exportCsv}>CSV</button>
          <button onClick={exportXlsx}>XLSX</button>
        </div>
      </div>
      {failedRows.length > 0 && (
        <div className="card" style={{ padding: 10, marginBottom: 12, display: 'flex', alignItems: 'center', gap: 8 }}>
          <span className="badge">{failedRows.length} başarısız</span>
          <button onClick={async () => {
            for (const r of failedRows) {
              try { await replay.mutateAsync(r.id) } catch {}
            }
          }} disabled={replay.isPending}>Tüm başarısızları yeniden dener</button>
          {selectedIds.length > 0 && (
            <button onClick={async () => {
              for (const id of selectedIds) {
                try { await replay.mutateAsync(id) } catch {}
              }
              setSelectedIds([])
            }} disabled={replay.isPending}>Seçiliyi yeniden dener ({selectedIds.length})</button>
          )}
        </div>
      )}
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12, flexWrap: 'wrap' }}>
        <select value={selectedPreset} onChange={(e) => setSelectedPreset(e.target.value)} style={{ minWidth: 160 }}>
          <option value="">Preset seçin</option>
          {presets.map(p => (<option key={p.name} value={p.name}>{p.name}</option>))}
        </select>
        <button onClick={applyPreset} disabled={!selectedPreset}>Uygula</button>
        <button onClick={deletePreset} disabled={!selectedPreset}>Sil</button>
        <input placeholder="Preset adı" value={newPresetName} onChange={(e) => setNewPresetName(e.target.value)} style={{ width: 180 }} />
        <button onClick={savePreset} disabled={!newPresetName.trim()}>Kaydet</button>
      </div>
      {hasError && (
        <div className="card" style={{ padding: 12, marginBottom: 12 }}>
          <div style={{ color: '#991b1b', fontWeight: 600 }}>Liste yüklenemedi</div>
          <div style={{ color: '#64748b', marginTop: 6 }}>{errorObj?.message || 'Bilinmeyen hata'}</div>
          <div style={{ marginTop: 8 }}><button onClick={() => list.refetch()}>Tekrar Dene</button></div>
        </div>
      )}

      {isInitialLoading && (
        <div className="card" style={{ padding: 12, marginBottom: 12 }}>
          <Skeleton height={16} width={220} />
          <Skeleton height={16} width={520} />
          <Skeleton height={16} />
        </div>
      )}

      {!isInitialLoading && !hasError && rows.length === 0 && (
        <div className="card" style={{ padding: 16, marginBottom: 12 }}>
          <div style={{ fontWeight: 600 }}>Kayıt bulunamadı</div>
          <div style={{ color: '#64748b', marginTop: 6 }}>Filtreleri temizleyip yeniden deneyin.</div>
          <div style={{ marginTop: 8 }}>
            <button onClick={() => { setQ(''); setStatus(''); setFrom(''); setTo('') }}>Filtreleri Temizle</button>
          </div>
        </div>
      )}

      <DataTable
        columns={columns}
        rows={rows as any[]}
        getRowId={(r: any) => r.id}
        storageKey="dt/webhook_deliveries"
        hasMore={!!list.hasNextPage}
        loadingMore={!!list.isFetchingNextPage}
        onLoadMore={() => list.fetchNextPage()}
        rowActions={(d: any) => (
          <div style={{ display: 'grid', gap: 6 }}>
            <button onClick={() => setDetail(d)}>Detay</button>
            <Link to={`/app/webhooks/deliveries/${d.id}?${new URLSearchParams({ q, status, from, to }).toString()}`}><button>Sayfada Aç</button></Link>
            <button onClick={() => replay.mutate(d.id)} disabled={replay.isPending}>Yeniden Gönder</button>
          </div>
        )}
        selectable
        selectedIds={selectedIds}
        onToggleSelect={(id, checked) => setSelectedIds((prev) => checked ? [...prev, id as number] : prev.filter((x) => x !== id))}
        onToggleSelectAll={(checked) => setSelectedIds(checked ? (rows as any[]).map((r: any) => r.id) : [])}
        virtualized
        rowHeight={40}
      />
      {detail && (
        <div className="modal-overlay" role="presentation" onClick={() => setDetail(null)}>
          <div className="modal" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
            <div className="modal-title">Teslimat #{detail.id}</div>
            <div style={{ display: 'grid', gap: 8, maxHeight: '60vh', overflow: 'auto' }}>
              <div style={{ color: '#64748b' }}>Event: {detail.event} — Durum: {detail.status}</div>
              <div style={{ fontWeight: 600 }}>Request</div>
              <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(detail.request || {}, null, 2)}</pre>
              <div style={{ fontWeight: 600 }}>Response</div>
              <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(detail.response || {}, null, 2)}</pre>
            </div>
            <div className="modal-actions">
              <button className="btn-secondary" onClick={() => setDetail(null)}>Kapat</button>
              <button onClick={() => replay.mutate(detail.id)} disabled={replay.isPending}>Yeniden Gönder</button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}


