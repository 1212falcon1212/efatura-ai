import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'

export default function ApiKeysPage() {
  const qc = useQueryClient()
  const [name, setName] = useState('Default Key')
  const { data, isLoading, isError } = useQuery({
    queryKey: ['api_keys'],
    queryFn: async () => {
      const res = await api.get('/auth/api-keys')
      return res.data?.data ?? []
    },
    staleTime: 30_000,
  })

  const createMut = useMutation({
    mutationFn: async () => {
      const res = await api.post('/auth/api-keys', { name })
      return res.data
    },
    onSuccess: (res) => {
      alert(`Yeni API anahtarı oluşturuldu: ${res.key}`)
      qc.invalidateQueries({ queryKey: ['api_keys'] })
    },
  })

  const revokeMut = useMutation({
    mutationFn: async (id: number) => {
      await api.post(`/auth/api-keys/${id}/revoke`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['api_keys'] }),
  })

  return (
    <div>
      <PageHeader title="API Anahtarları" subtitle="Erişim anahtarlarını yönet" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'API Keys' }]} />
      <div className="card" style={{ marginBottom: 16, display: 'flex', gap: 8, alignItems: 'center' }}>
        <input value={name} onChange={(e) => setName(e.target.value)} placeholder="Anahtar adı" style={{ padding: '8px 10px', border: '1px solid #e2e8f0', borderRadius: 8 }} />
        <button className="btn-primary" onClick={() => createMut.mutate()} disabled={createMut.isPending}>Oluştur</button>
      </div>
      {isLoading && <div>Yükleniyor…</div>}
      {isError && <div>Hata oluştu</div>}
      {!isLoading && !isError && (
        <div className="card" style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
            <thead>
              <tr style={{ textAlign: 'left' }}>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>ID</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Ad</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Key</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Son Kullanım</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Durum</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}></th>
              </tr>
            </thead>
            <tbody>
              {data.map((k: any) => (
                <tr key={k.id}>
                  <td style={{ padding: 12 }}>{k.id}</td>
                  <td style={{ padding: 12 }}>{k.name}</td>
                  <td style={{ padding: 12, fontFamily: 'monospace' }}>{k.key}</td>
                  <td style={{ padding: 12 }}>{k.last_used_at ? new Date(k.last_used_at).toLocaleString() : '-'}</td>
                  <td style={{ padding: 12 }}>{k.revoked_at ? 'Revoked' : 'Active'}</td>
                  <td style={{ padding: 12 }}>
                    {!k.revoked_at && (
                      <button className="btn-secondary" onClick={() => revokeMut.mutate(k.id)} disabled={revokeMut.isPending}>Revoke</button>
                    )}
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


