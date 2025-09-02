import { useState } from 'react'
import { api } from '../lib/api'

export default function ProviderKolaysoftDebugPage() {
  const [vkn, setVkn] = useState('')
  const [uuid, setUuid] = useState('')
  const [sourceId, setSourceId] = useState('')
  const [prefixes, setPrefixes] = useState('ABC,DEF')
  const [out, setOut] = useState<any>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function run(fn: () => Promise<any>) {
    setLoading(true); setError(''); setOut(null)
    try {
      const res = await fn()
      setOut(res.data)
    } catch (e: any) {
      setError(e?.response?.data?.message || e?.message || 'Hata')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ padding: 24, display: 'grid', gap: 16 }}>
      <h2>Sağlayıcı Test: Kolaysoft</h2>

      <div className="card" style={{ display: 'grid', gap: 8 }}>
        <strong>Kredi Sorgu</strong>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <input placeholder="VKN/TCKN" value={vkn} onChange={(e) => setVkn(e.target.value)} />
          <button onClick={() => run(() => api.get('/internal/providers/kolaysoft/credit-count', { params: { vkn_tckn: vkn } }))} disabled={!vkn || loading}>Çalıştır</button>
        </div>
      </div>

      <div className="card" style={{ display: 'grid', gap: 8 }}>
        <strong>E‑Fatura Kullanıcı Kontrol</strong>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <input placeholder="VKN/TCKN" value={vkn} onChange={(e) => setVkn(e.target.value)} />
          <button onClick={() => run(() => api.get('/internal/providers/kolaysoft/is-efatura-user', { params: { vknTckn: vkn } }))} disabled={!vkn || loading}>Çalıştır</button>
        </div>
      </div>

      <div className="card" style={{ display: 'grid', gap: 8 }}>
        <strong>Son Fatura No/Tarih</strong>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
          <input placeholder="Source ID (VKN/TCKN)" value={sourceId} onChange={(e) => setSourceId(e.target.value)} />
          <input placeholder="Seri önekleri (virgülle)" value={prefixes} onChange={(e) => setPrefixes(e.target.value)} />
          <button onClick={() => run(() => api.get('/internal/providers/kolaysoft/last-invoice', { params: { source_id: sourceId, documentIdPrefix: prefixes.split(',').map(s => s.trim()).filter(Boolean) } }))} disabled={!sourceId || loading}>Çalıştır</button>
        </div>
      </div>

      <div className="card" style={{ display: 'grid', gap: 8 }}>
        <strong>Durum + Loglar (UUID)</strong>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <input placeholder="ETTN/UUID" value={uuid} onChange={(e) => setUuid(e.target.value)} />
          <button onClick={() => run(() => api.get('/internal/providers/kolaysoft/status-with-logs', { params: { uuid } }))} disabled={!uuid || loading}>Çalıştır</button>
        </div>
      </div>

      {loading && <div>Yükleniyor…</div>}
      {error && <div style={{ color: 'crimson' }}>{error}</div>}
      {out && (
        <pre style={{ whiteSpace: 'pre-wrap', background: '#f8fafc', padding: 12, border: '1px solid #e2e8f0' }}>{JSON.stringify(out, null, 2)}</pre>
      )}
    </div>
  )
}


