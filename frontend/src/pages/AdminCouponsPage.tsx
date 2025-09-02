import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useConfirm } from '../components/ui/ConfirmProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

async function fetchCoupons({ pageParam }: { pageParam?: string | null }) {
  const params: Record<string, string> = {}
  if (pageParam) params['page[after]'] = pageParam
  const res = await api.get('/admin/coupons', { params })
  return { items: res.data?.data ?? [], next: (res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null) as string | null }
}

export default function AdminCouponsPage() {
  const qc = useQueryClient()
  const confirm = useConfirm()
  const schema = z.object({
    code: z.string().min(1, 'Kod zorunlu'),
    percent_off: z.coerce.number().min(1).max(100),
  })
  type FormValues = z.infer<typeof schema>
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { code: '', percent_off: 10 } })
  const list = useInfiniteQuery<any, any, any, any, string | null>({
    queryKey: ['admin_coupons'],
    queryFn: ({ pageParam }) => fetchCoupons({ pageParam: pageParam ?? null }),
    getNextPageParam: (last) => last.next || undefined,
    initialPageParam: null,
  })
  const rows = (((list.data?.pages as unknown) as any[]) ?? []).flatMap((p: any) => p.items)

  const create = useMutation({
    mutationFn: async (payload: FormValues) => { await api.post('/admin/coupons', { ...payload, active: true }) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin_coupons'] }); form.reset({ code: '', percent_off: 10 }) }
  })
  const del = useMutation({
    mutationFn: async (id: number) => { await api.delete(`/admin/coupons/${id}`) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin_coupons'] }) }
  })

  return (
    <div style={{ padding: 24, display: 'grid', gap: 12 }}>
      <h2>Kuponlar</h2>
      <form onSubmit={form.handleSubmit((v) => create.mutate(v))} style={{ display:'flex', gap:8, alignItems:'center', flexWrap:'wrap' }}>
        <input placeholder="Kod" {...form.register('code')} style={{ width: 160 }} />
        <input type="number" step="1" placeholder="% İndirim" {...form.register('percent_off')} style={{ width: 120 }} />
        <button type="submit" disabled={create.isPending}>{create.isPending ? 'Ekleniyor…' : 'Ekle'}</button>
      </form>
      {Object.values(form.formState.errors).length > 0 && (
        <div className="card" style={{ background:'#fff7ed', borderColor:'#fdba74' }}>
          {Object.values(form.formState.errors).map((e, i) => (<div key={i} style={{ color:'#9a3412' }}>{e?.message as string}</div>))}
        </div>
      )}
      <div className="card" style={{ padding: 8 }}>
        <table>
          <thead><tr><th>ID</th><th>Kod</th><th>%</th><th>Aktif</th><th></th></tr></thead>
          <tbody>
            {rows.map((r: any) => (
              <tr key={r.id}>
                <td>{r.id}</td>
                <td>{r.code}</td>
                <td>{r.percent_off}</td>
                <td>{r.active ? 'Evet' : 'Hayır'}</td>
                <td style={{ textAlign:'right' }}>
                  <button className="btn-danger" onClick={async () => { const ok = await confirm({ title: 'Sil', description: `Kupon #${r.id} silinsin mi?`, variant: 'danger', confirmText: 'Sil' }); if (!ok) return; del.mutate(r.id) }}>Sil</button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {list.hasNextPage && (
          <div style={{ marginTop: 8 }}><button onClick={() => list.fetchNextPage()} disabled={list.isFetchingNextPage}>{list.isFetchingNextPage ? 'Yükleniyor…' : 'Daha Fazla'}</button></div>
        )}
      </div>
    </div>
  )
}


