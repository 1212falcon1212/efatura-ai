import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'

async function fetchTransactions() {
  const res = await api.get('/credits/transactions')
  return res.data
}

export default function CreditsTransactionsPage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['credit_tx'], queryFn: fetchTransactions })
  const rows = data?.data ?? []
  return (
    <div style={{ padding: 24 }}>
      <h2>Kontör Hareketleri</h2>
      {isLoading && <div>Yükleniyor…</div>}
      {isError && <div style={{ color: 'crimson' }}>Hata oluştu</div>}
      {!isLoading && !isError && (
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th style={{ textAlign: 'left', borderBottom: '1px solid #ddd', padding: 8 }}>ID</th>
              <th style={{ textAlign: 'left', borderBottom: '1px solid #ddd', padding: 8 }}>Tür</th>
              <th style={{ textAlign: 'left', borderBottom: '1px solid #ddd', padding: 8 }}>Tutar</th>
              <th style={{ textAlign: 'left', borderBottom: '1px solid #ddd', padding: 8 }}>Tarih</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r: any) => (
              <tr key={r.id}>
                <td style={{ padding: 8 }}>{r.id}</td>
                <td style={{ padding: 8 }}>{r.type}</td>
                <td style={{ padding: 8 }}>{r.amount?.amount ?? r.amount} {r.amount?.currency ?? 'TRY'}</td>
                <td style={{ padding: 8 }}>{r.createdAt ?? '-'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}


