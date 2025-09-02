import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api } from '../lib/api'
import { useToast } from '../components/ui/ToastProvider'
import PageHeader from '../components/ui/PageHeader'
import { useConfirm } from '../components/ui/ConfirmProvider'
import Skeleton from '../components/ui/Skeleton'

export default function WebhookDeliveriesDetailPage() {
  const { id } = useParams()
  const nav = useNavigate()
  const [sp] = useSearchParams()
  const toast = useToast()
  const confirm = useConfirm()
  const [data, setData] = useState<any>(null)
  const [loading, setLoading] = useState(true)
  const [idList, setIdList] = useState<number[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    try {
      const raw = localStorage.getItem('ids/webhook_deliveries')
      if (raw) setIdList(JSON.parse(raw))
    } catch {}
  }, [])

  useEffect(() => {
    let mounted = true
    ;(async () => {
      try {
        setLoading(true)
        setError(null)
        const res = await api.get(`/webhooks/deliveries/${id}`)
        if (mounted) setData(res.data)
      } catch (e: any) {
        if (mounted) setError(e?.response?.data?.message || 'Kayıt yüklenemedi')
      } finally { if (mounted) setLoading(false) }
    })()
    return () => { mounted = false }
  }, [id])

  function goDelta(delta: number) {
    if (!id) return
    const current = Number(id)
    const idx = idList.indexOf(current)
    const nextIdx = idx + delta
    if (nextIdx >= 0 && nextIdx < idList.length) {
      const nextId = idList[nextIdx]
      nav(`/app/webhooks/deliveries/${nextId}?${sp.toString()}`)
    }
  }

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === 'ArrowLeft') goDelta(-1)
      if (e.key === 'ArrowRight') goDelta(1)
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [idList, id])

  async function replay() {
    if (!id) return
    const ok = await confirm({
      title: 'Yeniden Gönder?',
      description: `Teslimat #${id} için webhook çağrısı tekrar denensin mi?`,
      confirmText: 'Evet, gönder',
    })
    if (!ok) return
    await api.post(`/webhooks/deliveries/${id}/replay`)
    toast.show({ type: 'success', title: 'Yeniden gönderildi' })
  }

  function copyJson(obj: any) {
    try { navigator.clipboard.writeText(JSON.stringify(obj || {}, null, 2)); toast.show({ type: 'success', title: 'Kopyalandı' }) } catch {}
  }

  function copyText(text: string) {
    try { navigator.clipboard.writeText(text); toast.show({ type: 'success', title: 'Kopyalandı' }) } catch {}
  }

  const nextAttemptAt = useMemo(() => {
    if (!data || data?.status === 'ok') return null
    const backoff = [60, 300, 1800, 7200, 86400] // 1m, 5m, 30m, 2h, 24h
    const attempt = Math.max(0, Number(data?.attempt_count ?? 0))
    const idx = Math.min(attempt, backoff.length - 1)
    const lastStr = data?.last_attempt_at || data?.created_at
    if (!lastStr) return null
    const base = new Date(lastStr)
    return new Date(base.getTime() + backoff[idx] * 1000)
  }, [data])

  return (
    <div style={{ padding: 24 }}>
      <PageHeader
        title={`Webhook Teslimatı #${id}`}
        crumbs={[{ label: 'Webhooklar', href: '/app/webhooks/deliveries' }, { label: `#${id}` }]}
        actions={(
          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={() => goDelta(-1)}>← Önceki</button>
            <button onClick={() => goDelta(1)}>Sonraki →</button>
            <button onClick={replay}>Yeniden Gönder</button>
          </div>
        )}
      />

      {loading && (
        <div className="card" style={{ padding: 16 }}>
          <div style={{ display: 'grid', gap: 8 }}>
            <Skeleton height={20} width={160} />
            <Skeleton height={16} width={260} />
            <Skeleton height={220} />
          </div>
        </div>
      )}

      {!loading && error && (
        <div className="card" style={{ padding: 16 }}>
          <div style={{ color: '#ef4444', marginBottom: 8 }}>Hata: {error}</div>
          <button onClick={() => nav(0)}>Tekrar Dene</button>
        </div>
      )}

      {!loading && data && (
        <div className="card" style={{ padding: 16, display: 'grid', gap: 12 }}>
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
            <div><span style={{ color: '#64748b' }}>Event:</span> <strong>{data.event || '-'}</strong></div>
            <div>
              <span style={{ color: '#64748b' }}>Durum:</span>{' '}
              <span style={{
                padding: '2px 8px', borderRadius: 8,
                background: data.status === 'ok' ? '#dcfce7' : '#fee2e2',
                color: data.status === 'ok' ? '#166534' : '#991b1b',
              }}>{data.status || '-'}</span>
            </div>
            <div><span style={{ color: '#64748b' }}>Deneme:</span> {data.attempt_count ?? 0}</div>
            {data.last_attempt_at && (<div><span style={{ color: '#64748b' }}>Son deneme:</span> {new Date(data.last_attempt_at).toLocaleString()}</div>)}
            {nextAttemptAt && (<div title={nextAttemptAt.toISOString()}><span style={{ color: '#64748b' }}>Sonraki deneme (öngörü):</span> {nextAttemptAt.toLocaleString()}</div>)}
            {data.target_url && (<div style={{ overflowWrap: 'anywhere' }}><span style={{ color: '#64748b' }}>URL:</span> {data.target_url}</div>)}
            <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
              <button className="btn-secondary" onClick={() => nav(`/app/webhooks/deliveries?${sp.toString()}`)}>Listeye Dön</button>
              <button className="btn-secondary" onClick={() => copyText(String(id))}>ID Kopyala</button>
            </div>
          </div>

          <div className="card" style={{ padding: 12 }}>
            <div style={{ fontWeight: 600, marginBottom: 6 }}>Retry Politikası</div>
            <div style={{ color: '#64748b' }}>Başarısız teslimatlar artan aralıklarla otomatik tekrar denenir: 1 dk → 5 dk → 30 dk → 2 sa → 24 sa.</div>
          </div>

          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <div style={{ fontWeight: 600 }}>Request</div>
            <button className="btn-secondary" onClick={() => copyJson(data.request)}>Kopyala</button>
          </div>
          <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(data.request || {}, null, 2)}</pre>

          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <div style={{ fontWeight: 600 }}>Response</div>
            <button className="btn-secondary" onClick={() => copyJson(data.response)}>Kopyala</button>
          </div>
          <pre style={{ whiteSpace: 'pre-wrap' }}>{JSON.stringify(data.response || {}, null, 2)}</pre>

          <div style={{ color: '#64748b', fontSize: 12 }}>
            İpucu: ←/→ ile kayıtlar arasında gezinebilirsiniz.
          </div>
        </div>
      )}
    </div>
  )
}
