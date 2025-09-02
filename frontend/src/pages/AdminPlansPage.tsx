import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

export default function AdminPlansPage() {
  const qc = useQueryClient()
  const schema = z.object({ code: z.string().min(1, 'code zorunlu'), name: z.string().min(1, 'ad zorunlu'), price_try: z.coerce.number().min(0) })
  type FormValues = z.infer<typeof schema>
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { code: '', name: '', price_try: 0 } })
  const plans = useQuery({ queryKey: ['admin_plans'], queryFn: async () => (await api.get('/admin/plans')).data?.data ?? [] })
  const createMut = useMutation({
    mutationFn: async (values: FormValues) => { await api.post('/admin/plans', values) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['admin_plans'] }); form.reset({ code: '', name: '', price_try: 0 }) },
  })
  return (
    <div>
      <PageHeader title="Admin: Planlar" subtitle="Plan yönetimi" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Admin' }, { label: 'Planlar' }]} />
      <form className="card" onSubmit={form.handleSubmit((v) => createMut.mutate(v))} style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 12 }}>
        <input placeholder="code" {...form.register('code')} />
        <input placeholder="name" {...form.register('name')} />
        <input placeholder="price_try" type="number" {...form.register('price_try')} />
        <button className="btn-primary" type="submit" disabled={createMut.isPending}>Ekle</button>
      </form>
      {Object.values(form.formState.errors).length > 0 && (
        <div className="card" style={{ background:'#fff7ed', borderColor:'#fdba74', marginBottom: 12 }}>
          {Object.values(form.formState.errors).map((e, i) => (<div key={i} style={{ color:'#9a3412' }}>{e?.message as string}</div>))}
        </div>
      )}
      {plans.isLoading && <div>Yükleniyor…</div>}
      {plans.isError && <div>Hata oluştu</div>}
      {!plans.isLoading && !plans.isError && (
        <div className="card" style={{ padding: 0 }}>
          <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
            <thead>
              <tr style={{ textAlign: 'left' }}>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>ID</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Code</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Ad</th>
                <th style={{ padding: 12, borderBottom: '1px solid #eef2f7' }}>Fiyat</th>
              </tr>
            </thead>
            <tbody>
              {plans.data.map((p: any) => (
                <tr key={p.id}>
                  <td style={{ padding: 12 }}>{p.id}</td>
                  <td style={{ padding: 12 }}>{p.code}</td>
                  <td style={{ padding: 12 }}>{p.name}</td>
                  <td style={{ padding: 12 }}>{p.price_try} TL</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}


