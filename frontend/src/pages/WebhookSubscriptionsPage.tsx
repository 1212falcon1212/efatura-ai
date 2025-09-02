import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useState, useEffect } from 'react'
import { useToast } from '../components/ui/ToastProvider'
import { useConfirm } from '../components/ui/ConfirmProvider'
import DataTable, { type Column } from '../components/ui/DataTable'
// XLSX'i dinamik yükleyeceğiz
import { useSearchParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

async function fetchSubs({ pageParam, q, from, to }: { pageParam?: string | null, q?: string, from?: string, to?: string }) {
  const params: Record<string, string> = {}
  if (pageParam) params['page[after]'] = pageParam
  if (q) params.q = q
  if (from) params.from = from
  if (to) params.to = to
  const res = await api.get('/webhooks/subscriptions', { params })
  return {
    items: res.data?.data ?? [],
    next: (res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null) as string | null,
  }
}

export default function WebhookSubscriptionsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const confirm = useConfirm()
  const [searchParams, setSearchParams] = useSearchParams()
  const schema = z.object({
    url: z.string().url('Geçerli bir URL girin'),
    secret: z.string().min(6, 'En az 6 karakter'),
    events: z.string().min(1, 'En az bir event'),
  })
  type FormValues = z.infer<typeof schema>
  const { register, handleSubmit, reset, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { url: '', secret: '', events: 'invoice.sent,invoice.failed' }
  })
  const [q, setQ] = useState(() => searchParams.get('q') || '')
  const [from, setFrom] = useState(() => searchParams.get('from') || '')
  const [to, setTo] = useState(() => searchParams.get('to') || '')
  const [presets, setPresets] = useState<Array<{ name: string; q?: string; from?: string; to?: string }>>(() => {
    try { return JSON.parse(localStorage.getItem('presets/webhook_subscriptions') || '[]') } catch { return [] }
  })
  const [selectedPreset, setSelectedPreset] = useState<string>('')
  const [newPresetName, setNewPresetName] = useState<string>('')
  function setQuickRange(days: number) {
    const end = new Date()
    const start = new Date()
    start.setDate(end.getDate() - days)
    const fmt = (d: Date) => d.toISOString().slice(0, 10)
    setFrom(fmt(start))
    setTo(fmt(end))
  }

  useEffect(() => {
    const noUrlFilters = !searchParams.get('q') && !searchParams.get('from') && !searchParams.get('to')
    if (noUrlFilters) {
      try {
        const savedRaw = localStorage.getItem('filters/webhook_subscriptions')
        if (savedRaw) {
          const saved = JSON.parse(savedRaw) as { q?: string; from?: string; to?: string }
          setQ(saved.q || '')
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
    if (from) sp.set('from', from)
    if (to) sp.set('to', to)
    setSearchParams(sp, { replace: true })
    try { localStorage.setItem('filters/webhook_subscriptions', JSON.stringify({ q, from, to })) } catch {}
  }, [q, from, to, setSearchParams])

  function savePreset() {
    const name = newPresetName.trim()
    if (!name) return
    const next = presets.filter(p => p.name !== name)
    next.unshift({ name, q: q || undefined, from: from || undefined, to: to || undefined })
    setPresets(next)
    try { localStorage.setItem('presets/webhook_subscriptions', JSON.stringify(next)) } catch {}
    setSelectedPreset(name)
  }
  function applyPreset() {
    const p = presets.find(pr => pr.name === selectedPreset)
    if (!p) return
    setQ(p.q || '')
    setFrom(p.from || '')
    setTo(p.to || '')
  }
  function deletePreset() {
    if (!selectedPreset) return
    const next = presets.filter(p => p.name !== selectedPreset)
    setPresets(next)
    try { localStorage.setItem('presets/webhook_subscriptions', JSON.stringify(next)) } catch {}
    setSelectedPreset('')
  }

  const list = useInfiniteQuery<any, any, any, any, string | null>({
    queryKey: ['webhook_subs', q, from, to],
    queryFn: ({ pageParam }) => fetchSubs({ pageParam: pageParam ?? null, q: q || undefined, from: from || undefined, to: to || undefined }),
    getNextPageParam: (last) => last.next || undefined,
    initialPageParam: null,
  })
  const rows = (((list.data?.pages as unknown) as any[]) ?? []).flatMap((p: any) => p.items)

  const create = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload = { url: values.url, secret: values.secret, events: values.events.split(',').map((e) => e.trim()).filter(Boolean) }
      const idempo = crypto.randomUUID ? crypto.randomUUID() : String(Date.now())
      await api.post('/webhooks/subscriptions', payload, { headers: { 'X-Idempotency-Key': idempo } })
    },
    onSuccess: () => { toast.show({ type: 'success', title: 'Eklendi' }); qc.invalidateQueries({ queryKey: ['webhook_subs'] }); reset() },
    onError: () => { toast.show({ type: 'error', title: 'Ekleme başarısız' }) },
  })

  const del = useMutation({
    mutationFn: async (id: number) => { await api.delete(`/webhooks/subscriptions/${id}`) },
    onSuccess: () => { toast.show({ type: 'success', title: 'Silindi' }); qc.invalidateQueries({ queryKey: ['webhook_subs'] }) },
    onError: () => { toast.show({ type: 'error', title: 'Silme başarısız' }) },
  })

  function exportCsv() {
    const header = ['id','url','events']
    const lines = ['\uFEFF' + header.join(',')]
    for (const w of rows as any[]) {
      lines.push([w.id, `"${(w.url||'').replace(/\"/g,'\"\"')}"`, (w.events||[]).join('|')].join(','))
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a'); a.href = url; a.download = 'webhook_subscriptions.csv'; a.click(); URL.revokeObjectURL(url)
  }
  function exportXlsx() {
    import('xlsx').then((XLSX) => {
      const ws = XLSX.utils.json_to_sheet((rows as any[]).map(w => ({ ID: w.id, URL: w.url, Events: (w.events||[]).join(', ') })))
      const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Subs'); XLSX.writeFile(wb, 'webhook_subscriptions.xlsx')
    })
  }

  const columns: Array<Column<any>> = [
    { key: 'id', label: 'ID', sortable: false },
    { key: 'url', label: 'URL' },
    { key: 'events', label: 'Events', render: (w: any) => (w.events || []).join(', ') },
    { key: 'actions', label: '', render: (w: any) => (
      <button onClick={async () => { const ok = await confirm({ title: 'Sil', description: `Abonelik #${w.id} silinsin mi?`, variant: 'danger', confirmText: 'Sil' }); if (!ok) return; del.mutate(w.id) }} className="btn-danger">Sil</button>
    ) },
  ]

  return (
    <div style={{ padding: 24 }}>
      <h2>Webhook Abonelikleri</h2>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12, flexWrap: 'wrap' }}>
        <input placeholder="Ara (URL/Events)" value={q} onChange={(e) => setQ(e.target.value)} style={{ width: 280 }} />
        <label>Başlangıç<input type="date" value={from} onChange={(e) => setFrom(e.target.value)} style={{ marginLeft: 6 }} /></label>
        <label>Bitiş<input type="date" value={to} onChange={(e) => setTo(e.target.value)} style={{ marginLeft: 6 }} /></label>
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={() => setQuickRange(1)}>Son 1g</button>
          <button onClick={() => setQuickRange(7)}>7g</button>
          <button onClick={() => setQuickRange(30)}>30g</button>
        </div>
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={() => { setQ('invoice.sent') }} className="btn-secondary">invoice.sent</button>
          <button onClick={() => { setQ('invoice.failed') }} className="btn-secondary">invoice.failed</button>
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <button onClick={exportCsv}>CSV</button>
          <button onClick={exportXlsx}>XLSX</button>
        </div>
      </div>
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
      <form onSubmit={handleSubmit((v) => create.mutate(v))} style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 16 }}>
        <div>
          <input placeholder="URL" style={{ width: 360 }} {...register('url')} />
          {errors.url && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.url.message}</div>}
        </div>
        <div>
          <input placeholder="Secret" style={{ width: 200 }} {...register('secret')} />
          {errors.secret && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.secret.message}</div>}
        </div>
        <div>
          <input placeholder="Events (virgülle)" style={{ width: 300 }} {...register('events')} />
          {errors.events && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.events.message}</div>}
        </div>
        <button type="submit" disabled={isSubmitting || create.isPending}>{create.isPending ? 'Ekleniyor...' : 'Ekle'}</button>
      </form>
      <DataTable
        columns={columns}
        rows={rows as any[]}
        getRowId={(r: any) => r.id}
        storageKey="dt/webhook_subs"
        hasMore={!!list.hasNextPage}
        loadingMore={!!list.isFetchingNextPage}
        onLoadMore={() => list.fetchNextPage()}
        virtualized
        rowHeight={40}
      />
    </div>
  )
}


