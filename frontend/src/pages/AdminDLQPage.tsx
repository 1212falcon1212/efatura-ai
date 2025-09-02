import { useQuery, useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'

export default function AdminDLQPage() {
  const list = useQuery({ queryKey: ['admin_dlq'], queryFn: async () => (await api.get('/admin/dead-letters')).data })
  const del = useMutation({ mutationFn: async (id: number) => { await api.delete(`/admin/dead-letters/${id}`) }, onSuccess: () => list.refetch() })
  const retry = useMutation({ mutationFn: async (id: number) => { await api.post(`/admin/dead-letters/${id}/retry`) }, onSuccess: () => list.refetch() })
  const replayBulk = useMutation({ mutationFn: async () => { await api.post('/admin/webhooks/replay-bulk', { days: 7, limit: 200 }) } })
  const requeueBulk = useMutation({ mutationFn: async () => { await api.post('/admin/invoices/requeue-bulk', { days: 7, limit: 200 }) } })
  return (
    <div>
      <PageHeader title="Admin: DLQ" subtitle="Başarısız işler" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Admin' }, { label: 'DLQ' }]} />
      <div style={{ display:'flex', gap:8, marginBottom:12 }}>
        <button className="btn-secondary" onClick={() => replayBulk.mutate()} disabled={replayBulk.isPending}>Webhook’ları Toplu Replay</button>
        <button className="btn-secondary" onClick={() => requeueBulk.mutate()} disabled={requeueBulk.isPending}>Faturaları Toplu Requeue</button>
      </div>
      {list.isLoading && <div>Yükleniyor…</div>}
      {list.isError && <div>Hata</div>}
      {list.data && (
        <div className="card" style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
            <thead><tr><th style={{ padding:12, borderBottom:'1px solid #eef2f7' }}>ID</th><th style={{ padding:12, borderBottom:'1px solid #eef2f7' }}>Tip</th><th style={{ padding:12, borderBottom:'1px solid #eef2f7' }}>Hata</th><th style={{ padding:12, borderBottom:'1px solid #eef2f7' }}></th></tr></thead>
            <tbody>
              {list.data.data.map((r: any) => (
                <tr key={r.id}>
                  <td style={{ padding:12 }}>{r.id}</td>
                  <td style={{ padding:12 }}>{r.type}</td>
                  <td style={{ padding:12 }}>{r.error}</td>
                  <td style={{ padding:12, textAlign:'right' }}>
                    <button className="btn-secondary" onClick={() => retry.mutate(r.id)} disabled={retry.isPending}>Yeniden Dene</button>
                    <button className="btn-secondary" onClick={() => del.mutate(r.id)} disabled={del.isPending} style={{ marginLeft:8 }}>Sil</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}


