import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

export default function SubscriptionPage() {
  const qc = useQueryClient()
  const subQuery = useQuery({
    queryKey: ['subscription_current'],
    queryFn: async () => {
      const res = await api.get('/subscriptions/current')
      return res.data
    },
    refetchOnWindowFocus: false,
  })

  const schema = z.object({
    subscription_invoice_id: z.coerce.number().int().positive(),
    card_holder_full_name: z.string().min(3, 'Ad Soyad gerekli'),
    card_number: z.string().min(12, 'Kart numarası geçersiz'),
    exp_month: z.string().min(2).max(2),
    exp_year: z.string().min(2).max(4),
    cvc: z.string().min(3).max(4),
  })
  type PayForm = z.infer<typeof schema>
  const payForm = useForm<PayForm>({ resolver: zodResolver(schema), defaultValues: { exp_month: '12', exp_year: '26' } })

  const cancelMut = useMutation({
    mutationFn: async () => { await api.post('/subscriptions/cancel') },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['subscription_current'] }) },
  })

  const payMut = useMutation({
    mutationFn: async (values: PayForm) => {
      await api.post('/subscriptions/pay', values)
    },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['subscription_current'] }); alert('Ödeme tamamlandı'); },
  })

  const pay3dMut = useMutation({
    mutationFn: async (values: PayForm) => {
      const invId = values.subscription_invoice_id
      const redirect = `${window.location.origin}/api/v1/subscriptions/3d-return?inv=${invId}`
      const res = await api.post('/subscriptions/pay', { ...values, use_3d: true, redirect_url: redirect })
      return res.data
    },
    onSuccess: (data: any) => {
      // Moka ThreeD yanıtında Data.Url veya benzeri alan olabilir; kullanıcıyı yönlendir
      const url = data?.response?.Data?.Url || data?.response?.Data?.ThreeDFormUrl || data?.response?.Data?.RedirectUrl
      if (url) window.location.href = url
    },
  })

  const lastPendingInvoiceId = subQuery.data?.invoices?.find((i: any) => i.status === 'pending')?.id

  return (
    <div>
      <PageHeader title="Abonelik" subtitle="Mevcut durum" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Abonelik' }]} />
      {subQuery.isLoading && <div>Yükleniyor…</div>}
      {subQuery.isError && <div>Hata oluştu</div>}
      {subQuery.data && (
        <div className="card" style={{ display: 'grid', gap: 12 }}>
          <div>Plan: <b>{subQuery.data?.plan?.name || '-'}</b></div>
          <div>Durum: <b>{subQuery.data?.status}</b></div>
          <div>Dönem: {subQuery.data?.current_period_start} → {subQuery.data?.current_period_end}</div>
          <div>
            {subQuery.data?.status === 'active' && (
              <button
                className="btn-secondary"
                onClick={() => cancelMut.mutate()}
                disabled={cancelMut.isPending || !(['owner','admin'].includes((localStorage.getItem('currentUserRole')||'').toLowerCase()))}
                title={['owner','admin'].includes((localStorage.getItem('currentUserRole')||'').toLowerCase()) ? '' : 'Yalnızca owner/admin iptal edebilir'}
              >İptal Et</button>
            )}
          </div>

          {lastPendingInvoiceId && (
            <div style={{ marginTop: 8 }}>
              <div style={{ fontWeight: 600, marginBottom: 6 }}>Ödeme (Moka)</div>
              <form onSubmit={payForm.handleSubmit((v) => payMut.mutate(v))} style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:12 }}>
                <input type="hidden" {...payForm.register('subscription_invoice_id')} value={lastPendingInvoiceId} />
                <div style={{ gridColumn:'1 / -1' }}>
                  <input className="input" placeholder="Ad Soyad" {...payForm.register('card_holder_full_name')} />
                </div>
                <div style={{ gridColumn:'1 / -1' }}>
                  <input className="input" placeholder="Kart Numarası" inputMode="numeric" {...payForm.register('card_number')} />
                </div>
                <div>
                  <input className="input" placeholder="AA" {...payForm.register('exp_month')} />
                </div>
                <div>
                  <input className="input" placeholder="YY" {...payForm.register('exp_year')} />
                </div>
                <div>
                  <input className="input" placeholder="CVC" {...payForm.register('cvc')} />
                </div>
                <div style={{ gridColumn:'1 / -1', display:'flex', gap:8, justifyContent:'flex-end' }}>
                  <button type="submit" disabled={payMut.isPending}>{payMut.isPending ? 'Ödeniyor…' : 'Öde'}</button>
                  <button type="button" onClick={() => pay3dMut.mutate(payForm.getValues())} disabled={pay3dMut.isPending} className="btn-secondary">{pay3dMut.isPending ? 'Yönlendiriliyor…' : '3D Secure ile Öde'}</button>
                </div>
              </form>
              {Object.values(payForm.formState.errors).length > 0 && (
                <div className="card" style={{ background:'#fff7ed', borderColor:'#fdba74', marginTop: 8 }}>
                  {Object.values(payForm.formState.errors).map((e, i) => (<div key={i} style={{ color:'#9a3412' }}>{e?.message as string}</div>))}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  )
}


