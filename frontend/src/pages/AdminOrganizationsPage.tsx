import { useQuery, useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useState } from 'react'

export default function AdminOrganizationsPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['admin_orgs'],
    queryFn: async () => (await api.get('/admin/organizations')).data,
  })
  const [adj, setAdj] = useState<{orgId?: number, amount?: string}>({})
  const adjust = useMutation({
    mutationFn: async () => { await api.post('/admin/credits/adjust', { organization_id: adj.orgId, amount: Number(adj.amount||0), reason: 'owner_manual' }) },
    onSuccess: () => { alert('Güncellendi'); },
  })
  const impersonate = useMutation({
    mutationFn: async (orgId: number) => (await api.post('/auth/impersonate', { organization_id: orgId })).data,
    onSuccess: (data) => {
      // UI: token ve apiKey’i localStorage’a yazıp sayfayı yenileyelim
      const current = localStorage.getItem('userToken')
      if (current) localStorage.setItem('originalUserToken', current)
      localStorage.setItem('userToken', data.token)
      localStorage.setItem('apiKey', data.apiKey)
      alert(`Impersonate: org #${data.organizationId} role=${data.role}`)
      location.reload()
    }
  })
  return (
    <div>
      <PageHeader title="Admin: Organizasyonlar" subtitle="Liste" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Admin' }, { label: 'Organizasyonlar' }]} />
      {isLoading && <div>Yükleniyor…</div>}
      {isError && <div>Hata oluştu</div>}
      {data && (
        <div className="card" style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
            <thead>
              <tr style={{ textAlign: 'left' }}>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>ID</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Ad</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Tarih</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}></th>
              </tr>
            </thead>
            <tbody>
              {data.data.map((o: any) => (
                <tr key={o.id}>
                  <td style={{ padding: 12 }}>{o.id}</td>
                  <td style={{ padding: 12 }}>{o.name}</td>
                  <td style={{ padding: 12 }}>{o.created_at}</td>
                  <td style={{ padding: 12 }}>
                    <form onSubmit={(e)=>{ e.preventDefault(); setAdj({ orgId: o.id, amount: (e.currentTarget as any).amount.value }); adjust.mutate(); }} style={{ display:'flex', gap:8, alignItems:'center' }}>
                      <input name="amount" className="input" placeholder="± e‑Kontör" style={{ width:120 }} />
                      <button className="btn-secondary" type="submit" disabled={adjust.isPending}>Uygula</button>
                    </form>
                    <div style={{ marginTop:8 }}>
                      <button className="btn-secondary" onClick={()=>impersonate.mutate(o.id)} disabled={impersonate.isPending}>Kılığına Gir</button>
                    </div>
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


