import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

async function fetchWallet() {
  const res = await api.get('/credits/wallet')
  return res.data
}

async function fetchProviderBalance() {
  const res = await api.get('/credits/provider-balance')
  return res.data
}

export default function CreditsWalletPage() {
  const qc = useQueryClient()
  const { data: wallet, isLoading: wl, isError: we } = useQuery({
    queryKey: ['wallet'],
    queryFn: fetchWallet,
    staleTime: 60_000,
    retry: 2,
    refetchOnWindowFocus: false,
  })
  const ownerSummary = useQuery({
    queryKey: ['owner_summary'],
    queryFn: async () => (await api.get('/credits/summary')).data,
    enabled: (localStorage.getItem('currentUserRole')||'').toLowerCase()==='owner',
    staleTime: 30_000,
  })
  const analytics = useQuery({
    queryKey: ['owner_analytics'],
    queryFn: async () => (await api.get('/credits/analytics')).data,
    enabled: (localStorage.getItem('currentUserRole')||'').toLowerCase()==='owner',
    staleTime: 60_000,
  })
  const prov = useMutation({ mutationFn: fetchProviderBalance })
  const update = useMutation({
    mutationFn: async (payload: any) => { await api.post('/credits/settings', payload) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['wallet'] }) }
  })
  const topup = useMutation({
    mutationFn: async (payload: any) => { await api.post('/credits/purchase', payload) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['wallet'] }) }
  })
  const [pkg, setPkg] = useState('CRD_1K')
  const schema = z.object({
    card_holder_full_name: z.string().min(3),
    card_number: z.string().min(12),
    exp_month: z.string().min(2).max(2),
    exp_year: z.string().min(2).max(4),
    cvc: z.string().min(3).max(4),
    use_3d: z.boolean().default(true),
  })
  type PayForm = z.infer<typeof schema>
  const form = useForm<PayForm>({ resolver: zodResolver(schema), defaultValues: { use_3d: true } })
  const pay = useMutation({
    mutationFn: async (values: PayForm) => {
      const base = { package_code: pkg, ...values }
      const redirect = `${window.location.origin}/api/v1/subscriptions/3d-return`
      const res = await api.post('/credits/pay', values.use_3d ? { ...base, redirect_url: redirect } : base)
      return res.data
    },
    onSuccess: (data: any) => {
      if (data?.three_d) {
        const url = data?.response?.Data?.Url || data?.response?.Data?.ThreeDFormUrl || data?.response?.Data?.RedirectUrl
        if (url) window.location.href = url
      } else {
        qc.invalidateQueries({ queryKey: ['wallet'] })
        alert('Satın alım tamamlandı')
      }
    },
  })

  // Kontör yetersiz hatasında otomatik modal aç (paket seçimi için)
  useState(() => {
    const onInsufficient = () => {
      alert('Kontörünüz tükendi. Lütfen paket satın alın.')
      // sayfa zaten bu; sadece odaklansın
    }
    window.addEventListener('credits:insufficient', onInsufficient)
    return () => window.removeEventListener('credits:insufficient', onInsufficient)
  })

  return (
    <div style={{ padding: 24, display: 'grid', gap: 16 }}>
      <h2>Kontör Cüzdanı</h2>
      {wl && <div>Yükleniyor…</div>}
      {we && <div style={{ color: 'crimson' }}>Hata oluştu</div>}
      {wallet && (
        <div style={{ border: '1px solid #eee', padding: 16 }}>
          <div><strong>Bakiye:</strong> {wallet.balance?.amount ?? wallet.balance} {wallet.balance?.currency ?? 'TRY'}</div>
          {wallet.lowBalanceThreshold && (
            <div><strong>Uyarı Eşiği:</strong> {wallet.lowBalanceThreshold.amount} {wallet.lowBalanceThreshold.currency}</div>
          )}
        </div>
      )}
      {(localStorage.getItem('currentUserRole')||'').toLowerCase()==='owner' && ownerSummary.data && (
        <div className="card" style={{ padding: 16 }}>
          <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between' }}>
            <div style={{ fontWeight:600 }}>Havuz Özeti (Owner)</div>
            <button className="btn-secondary" onClick={() => prov.mutate()} disabled={prov.isPending}>
              {prov.isPending ? 'Sorgulanıyor…' : 'Sağlayıcı bakiyesi getir'}
            </button>
          </div>
          <div>Havuz bakiyesi: {ownerSummary.data.pool.balance} e‑Kontör</div>
          <div>Ayrılmış (rezerv): {ownerSummary.data.pool.reserved} e‑Kontör</div>
          <div>Kullanılabilir: {ownerSummary.data.pool.available} e‑Kontör</div>
          <div style={{ marginTop:8 }}>Müşteri cüzdan toplamı: {ownerSummary.data.customers_total} e‑Kontör</div>
          <div style={{ marginTop:8 }}>
            Kolaysoft (canlı): {
              prov.data
                ? ((prov.data.return?.code === '000')
                    ? `Toplam: ${prov.data.return.totalCredit} • Kalan: ${prov.data.return.remainCredit}`
                    : (prov.data.return?.explanation || '—'))
                : '—'
            }
          </div>
          {analytics.data && (
            <div style={{ marginTop:12, borderTop:'1px solid var(--panel-border)', paddingTop:12 }}>
              <div style={{ fontWeight:600, marginBottom:6 }}>Son 7 Gün Tüketim</div>
              <div style={{ display:'grid', gap:6 }}>
                {Object.entries(analytics.data.usage_last_7_days || {}).map(([day, val]: any) => (
                  <div key={day} style={{ display:'grid', gridTemplateColumns:'90px 1fr', gap:8, alignItems:'center' }}>
                    <div style={{ color:'#475569', fontSize:12 }}>{day}</div>
                    <div style={{ height:8, background:'#e2e8f0', borderRadius:999 }}>
                      <div style={{ width: Math.min(100, Number(val))+'%', height:'100%', background:'var(--brand)', borderRadius:999 }} />
                    </div>
                  </div>
                ))}
              </div>
              <div style={{ fontWeight:600, margin:'12px 0 6px' }}>En Çok Tüketen Organizasyonlar</div>
              <div style={{ display:'grid', gap:6 }}>
                {(analytics.data.top_organizations || []).map((o: any) => (
                  <div key={o.organization_id} style={{ display:'flex', justifyContent:'space-between' }}>
                    <div>{o.organization_name}</div>
                    <div style={{ fontFamily:'monospace' }}>{o.credits} e‑Kontör</div>
                  </div>
                ))}
              </div>
              <div style={{ marginTop:12 }}>
                <button className="btn-secondary" onClick={async ()=>{ await api.post('/credits/reservations/cleanup'); ownerSummary.refetch(); analytics.refetch(); }}>Süresi Geçmiş Rezervasyonları Temizle</button>
              </div>
            </div>
          )}
        </div>
      )}
      <div className="card" style={{ padding: 16 }}>
        <div style={{ fontWeight: 600, marginBottom: 8 }}>Ayarlar</div>
        <form onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget as HTMLFormElement); update.mutate({ low_balance_threshold: Number(fd.get('threshold')||0), auto_topup_enabled: !!fd.get('auto_topup'), doc_limit_daily: Number(fd.get('doc_limit_daily')||0)||undefined, doc_limit_monthly: Number(fd.get('doc_limit_monthly')||0)||undefined, limit_action: String(fd.get('limit_action')||'block'), auto_purchase_enabled: !!fd.get('auto_purchase_enabled'), auto_purchase_package: String(fd.get('auto_purchase_package')||'')||undefined }) }} style={{ display:'grid', gap:8, maxWidth: 620 }}>
          <label>Eşik (TRY)
            <input name="threshold" type="number" step="0.01" defaultValue={wallet?.low_balance_threshold ?? ''} />
          </label>
          <label style={{ display:'flex', alignItems:'center', gap:8 }}>
            <input name="auto_topup" type="checkbox" defaultChecked={!!wallet?.auto_topup_enabled} /> Otomatik yükleme aktif
          </label>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
            <label>Günlük doküman limiti
              <input name="doc_limit_daily" type="number" step="1" defaultValue={wallet?.doc_limit_daily ?? ''} />
            </label>
            <label>Aylık doküman limiti
              <input name="doc_limit_monthly" type="number" step="1" defaultValue={wallet?.doc_limit_monthly ?? ''} />
            </label>
          </div>
          <label>Limit aşıldığında
            <select name="limit_action" defaultValue={wallet?.limit_action || 'block'} className="input">
              <option value="block">Blokla</option>
              <option value="continue">Devam et</option>
            </select>
          </label>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:8 }}>
            <label style={{ display:'flex', alignItems:'center', gap:8 }}>
              <input name="auto_purchase_enabled" type="checkbox" defaultChecked={!!wallet?.auto_purchase_enabled} /> Otomatik paket satın al
            </label>
            <select name="auto_purchase_package" defaultValue={wallet?.auto_purchase_package || ''} className="input">
              <option value="">Paket seçin</option>
              <option value="CRD_100">100 e‑Kontör</option>
              <option value="CRD_200">200 e‑Kontör</option>
              <option value="CRD_500">500 e‑Kontör</option>
              <option value="CRD_1K">1000 e‑Kontör</option>
              <option value="CRD_2_5K">2500 e‑Kontör</option>
              <option value="CRD_5K">5000 e‑Kontör</option>
            </select>
            <label>Eşik (e‑Kontör)
              <input name="auto_purchase_threshold_credits" type="number" step="1" defaultValue={wallet?.auto_purchase_threshold_credits ?? ''} />
            </label>
          </div>
          <button type="submit" disabled={update.isPending}>{update.isPending ? 'Kaydediliyor…' : 'Kaydet'}</button>
        </form>
      </div>
      <div className="card" style={{ padding: 16 }}>
        <div style={{ fontWeight: 600, marginBottom: 8 }}>Kredi Yükle (Paket)</div>
        <div style={{ display:'grid', gap:12 }}>
          <div style={{ display:'grid', gridTemplateColumns:'repeat(3, minmax(0,1fr))', gap:12, overflowX:'auto', scrollSnapType:'x mandatory', paddingBottom:8 }}>
            {[
              { code:'CRD_100',  name:'100 e‑Kontör',  price:'300 TL + KDV',  tag:null, desc:'Hızlı başlangıç' },
              { code:'CRD_200',  name:'200 e‑Kontör',  price:'480 TL + KDV',  tag:null, desc:'Daha uygun fiyat' },
              { code:'CRD_500',  name:'500 e‑Kontör',  price:'1090 TL + KDV', tag:'Avantaj paketi', desc:'Orta ölçek için ideal' },
              { code:'CRD_1K',   name:'1000 e‑Kontör', price:'1790 TL + KDV', tag:null, desc:'Yüksek hacim' },
              { code:'CRD_2_5K', name:'2500 e‑Kontör', price:'3950 TL + KDV', tag:null, desc:'Daha iyi birim fiyat' },
              { code:'CRD_5K',   name:'5000 e‑Kontör', price:'6951 TL + KDV', tag:null, desc:'En iyi birim fiyat' },
            ].map((p) => (
              <div key={p.code} className="card" style={{ borderColor:'#e2e8f0', scrollSnapAlign:'start', minWidth:260 }}>
                {p.tag && <div className="badge" style={{ marginBottom:8 }}>{p.tag}</div>}
                <div style={{ fontWeight:700 }}>{p.name}</div>
                <div style={{ color:'#475569', fontSize:13, marginTop:4 }}>{p.desc}</div>
                <div style={{ fontWeight:800, fontSize:20, marginTop:8 }}>{p.price}</div>
                <button className="btn-secondary" onClick={()=>setPkg(p.code)} style={{ marginTop:8 }} disabled={pkg===p.code}>{pkg===p.code ? 'Seçili' : 'Seç'}</button>
              </div>
            ))}
          </div>

          <form onSubmit={form.handleSubmit((v)=>pay.mutate(v))} style={{ display:'grid', gap:8, maxWidth: 520 }}>
            <label>Ad Soyad
              <input className="input" {...form.register('card_holder_full_name')} />
            </label>
            <label>Kart Numarası
              <input className="input" {...form.register('card_number')} />
            </label>
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:8 }}>
              <label>AA
                <input className="input" {...form.register('exp_month')} />
              </label>
              <label>YY
                <input className="input" {...form.register('exp_year')} />
              </label>
              <label>CVC
                <input className="input" {...form.register('cvc')} />
              </label>
            </div>
            <label style={{ display:'inline-flex', alignItems:'center', gap:8 }}>
              <input type="checkbox" {...form.register('use_3d')} /> 3D Secure ile öde
            </label>
            <div>
              <button type="submit" disabled={pay.isPending}>{pay.isPending ? 'Gönderiliyor…' : 'Satın Al'}</button>
            </div>
          </form>
        </div>
      </div>
      {/* Owner kartına entegre edildi */}
    </div>
  )
}


