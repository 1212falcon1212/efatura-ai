import { useState } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'

export default function InboxInvoicesPage() {
  const [date, setDate] = useState<string>(new Date().toISOString().slice(0,10))
  const q = useQuery({
    queryKey: ['inbox_invoices', date],
    queryFn: async () => (await api.get(`/internal/providers/kolaysoft/inbox/with-received-date`, { params: { date } })).data,
  })

  const rows: any[] = q.data?.return?.invoiceList || q.data?.invoiceList || []
  const loadMutation = useMutation({
    mutationFn: async (payload: any) => (await api.post('/internal/providers/kolaysoft/inbox/load', payload)).data,
  })

  return (
    <div>
      <PageHeader title="Gelen e‑Faturalar" subtitle="Kolaysoft üzerinden alınan belgeler" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Gelen e‑Faturalar' }]} />
      <div className="card" style={{ display:'grid', gap: 12 }}>
        <div className="field" style={{ display:'flex', gap:8, alignItems:'center' }}>
          <label>Alınma Tarihi</label>
          <input className="input" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
          <button onClick={() => q.refetch()} disabled={q.isFetching}>{q.isFetching ? 'Sorgulanıyor…' : 'Sorgula'}</button>
        </div>
        <details>
          <summary style={{ cursor:'pointer' }}>Fatura Yükle (LoadInboxInvoice)</summary>
          <div className="card" style={{ marginTop:8 }}>
            <form onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget as HTMLFormElement); const guid = String(fd.get('uuid')||'').trim(); if (!guid) return; loadMutation.mutate({ inputDocumentList: [{ documentUUID: guid }] }) }} style={{ display:'flex', gap:8, alignItems:'center' }}>
              <input className="input" name="uuid" placeholder="Document UUID (ETTN)" />
              <button type="submit" disabled={loadMutation.isPending}>{loadMutation.isPending ? 'Yükleniyor…' : 'Yükle'}</button>
            </form>
            {loadMutation.data && <pre style={{ whiteSpace:'pre-wrap', fontSize:12, background:'#f8fafc', padding:8, borderRadius:8, border:'1px solid #e2e8f0', marginTop:8 }}>{JSON.stringify(loadMutation.data, null, 2)}</pre>}
          </div>
        </details>
        {q.isLoading && <div>Yükleniyor…</div>}
        {!q.isLoading && rows.length === 0 && <div>Kayıt bulunamadı.</div>}
        {!q.isLoading && rows.length > 0 && (
          <div style={{ overflowX: 'auto' }}>
            <table className="table">
              <thead>
                <tr>
                  <th>UUID</th>
                  <th>Belge No</th>
                  <th>Tarih</th>
                  <th>Gönderen</th>
                  <th>Tutar</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r: any, i: number) => (
                  <tr key={i}>
                    <td>{r.documentUUID || r.uuid}</td>
                    <td>{r.documentId}</td>
                    <td>{r.receivedDate || r.documentDate}</td>
                    <td>{r.senderTitle || r.senderName}</td>
                    <td>{r.payableAmount ?? r.totalAmount}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}


