import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

export default function PlansPage() {
  const qc = useQueryClient()
  const schema = z.object({
    subscription_invoice_id: z.coerce.number().int().positive(),
    card_holder_full_name: z.string().min(3),
    card_number: z.string().min(12),
    exp_month: z.string().min(2).max(2),
    exp_year: z.string().min(2).max(4),
    cvc: z.string().min(3).max(4),
  })
  type PayForm = z.infer<typeof schema>
  const payForm = useForm<PayForm>({ resolver: zodResolver(schema) })

  const plansQuery = useQuery({
    queryKey: ['plans'],
    queryFn: async () => {
      const res = await api.get('/plans')
      return res.data?.data ?? []
    },
    staleTime: 60_000,
  })

  const subscribeMut = useMutation({
    mutationFn: async (plan_code: string) => {
      const res = await api.post('/subscriptions/subscribe', { plan_code })
      return res.data
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['subscription_current'] })
      setIsPayOpen(true)
    },
  })

  const subQuery = useQuery({
    queryKey: ['subscription_current'],
    queryFn: async () => (await api.get('/subscriptions/current')).data,
    staleTime: 10_000,
  })

  const payMut = useMutation({
    mutationFn: async (values: PayForm) => { await api.post('/subscriptions/pay', values) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['subscription_current'] }); alert('Ödeme tamamlandı') },
  })

  const pay3dMut = useMutation({
    mutationFn: async (values: PayForm) => {
      const invId = values.subscription_invoice_id
      const redirect = `${window.location.origin}/api/v1/subscriptions/3d-return?inv=${invId}`
      const res = await api.post('/subscriptions/pay', { ...values, use_3d: true, redirect_url: redirect })
      return res.data
    },
    onSuccess: (data: any) => {
      const url = data?.response?.Data?.Url || data?.response?.Data?.ThreeDFormUrl || data?.response?.Data?.RedirectUrl
      if (url) window.location.href = url
    },
  })

  const [isPayOpen, setIsPayOpen] = useState(false)
  const [use3d, setUse3d] = useState(true)

  const pendingInvoiceId = subQuery.data?.invoices?.find((i: any) => i.status === 'pending')?.id

  return (
    <div>
      <PageHeader title="Planlar" subtitle="Paketinizi seçin" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Planlar' }]} />
      {plansQuery.isLoading && <div>Yükleniyor…</div>}
      {plansQuery.isError && <div>Hata oluştu</div>}
      {!plansQuery.isLoading && !plansQuery.isError && (
        <div className="card" style={{ display: 'grid', gap: 12 }}>
          {plansQuery.data.map((p: any) => (
            <div key={p.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: 8, border: '1px solid #eef2f7', borderRadius: 8 }}>
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                <div style={{ fontWeight: 700 }}>{p.name}</div>
                <div style={{ color: '#475569' }}>{p.code}</div>
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
                <div style={{ fontSize: 18, fontWeight: 800 }}>{p.price_try} TL/ay</div>
                <button
                  className="btn-primary"
                  onClick={() => subscribeMut.mutate(p.code)}
                  disabled={subscribeMut.isPending || !(['owner','admin'].includes((localStorage.getItem('currentUserRole')||'').toLowerCase()))}
                  title={['owner','admin'].includes((localStorage.getItem('currentUserRole')||'').toLowerCase()) ? '' : 'Yalnızca owner/admin abone olabilir'}
                >Abone Ol</button>
                {pendingInvoiceId && (
                  <button className="btn-secondary" onClick={() => setIsPayOpen(true)}>Ödeme Yap</button>
                )}
              </div>
            </div>
          ))}
          {isPayOpen && pendingInvoiceId && (
            <div className="modal-overlay" onClick={() => setIsPayOpen(false)}>
              <div className="modal" onClick={(e)=>e.stopPropagation()} style={{ width: 'min(520px, 92vw)' }}>
                <div className="modal-title">Ödeme</div>
                <div className="modal-desc">Kart bilgilerinizle ödemeyi tamamlayın.</div>
                <div style={{ marginTop: 8, fontWeight: 600 }}>
                  Tutar: {subQuery.data?.invoices?.find((i:any)=>i.id===pendingInvoiceId)?.amount_try ?? '—'} TL / ay
                </div>
                <form onSubmit={payForm.handleSubmit((v)=>{ if (use3d) { pay3dMut.mutate(v) } else { payMut.mutate(v) } })} style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap: 12, marginTop: 12 }}>
                  <input type="hidden" {...payForm.register('subscription_invoice_id')} value={pendingInvoiceId} />
                  <div style={{ gridColumn:'1 / -1' }}>
                    <label style={{ fontSize:12, color:'var(--muted)' }}>Ad Soyad</label>
                    <input className="input" placeholder="Ad Soyad" {...payForm.register('card_holder_full_name')} />
                  </div>
                  <div style={{ gridColumn:'1 / -1' }}>
                    <label style={{ fontSize:12, color:'var(--muted)' }}>Kart Numarası</label>
                    <input className="input" placeholder="•••• •••• •••• ••••" inputMode="numeric" {...payForm.register('card_number')} />
                  </div>
                  <div>
                    <label style={{ fontSize:12, color:'var(--muted)' }}>Ay</label>
                    <input className="input" placeholder="AA" {...payForm.register('exp_month')} />
                  </div>
                  <div>
                    <label style={{ fontSize:12, color:'var(--muted)' }}>Yıl</label>
                    <input className="input" placeholder="YY" {...payForm.register('exp_year')} />
                  </div>
                  <div>
                    <label style={{ fontSize:12, color:'var(--muted)' }}>CVC</label>
                    <input className="input" placeholder="CVC" {...payForm.register('cvc')} />
                  </div>
                  <div style={{ gridColumn:'1 / -1', display:'flex', alignItems:'center', gap:8, marginTop: -4 }}>
                    <label style={{ display:'inline-flex', alignItems:'center', gap:8, fontSize:13, color:'var(--muted)' }}>
                      <input type="checkbox" checked={use3d} onChange={(e)=>setUse3d(e.target.checked)} /> 3D Secure ile öde
                    </label>
                  </div>
                  <div className="modal-actions">
                    <button type="button" className="btn-secondary" onClick={() => setIsPayOpen(false)}>Vazgeç</button>
                    <button type="submit" disabled={payMut.isPending || pay3dMut.isPending}>{(payMut.isPending || pay3dMut.isPending) ? 'Gönderiliyor…' : 'Öde'}</button>
                  </div>
                </form>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}


