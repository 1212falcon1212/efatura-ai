import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const schema = z.object({
  name: z.string().min(1, 'Ad zorunlu'),
  sku: z.string().min(1, 'SKU zorunlu'),
  unit: z.string().min(1),
  vat: z.coerce.number().min(0).max(100).default(20),
  price: z.coerce.number().min(0).optional(),
  currency: z.string().min(1).default('TRY'),
  barcode: z.string().optional().default(''),
  category: z.string().optional().default(''),
  withholding: z.coerce.number().min(0).max(100).optional(),
  vatExemptionCode: z.string().optional().default(''),
  gtip: z.string().optional().default(''),
  purchasePrice: z.coerce.number().min(0).optional(),
  stockTracking: z.boolean().default(false),
  initialStock: z.coerce.number().min(0).optional(),
  criticalStock: z.coerce.number().min(0).optional(),
})

type FormValues = z.infer<typeof schema>

export default function ProductCreatePage() {
  const navigate = useNavigate()
  const { register, handleSubmit, watch, formState: { errors, isSubmitting } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { unit: 'Adet', vat: 20, currency: 'TRY', stockTracking: false },
  })
  const stockTracking = watch('stockTracking')
  const vat = watch('vat')

  // UI styles for consistent alignment and bigger controls
  const rowStyle = {
    display: 'grid',
    gridTemplateColumns: '240px 1fr',
    columnGap: 16,
    alignItems: 'center',
    padding: '10px 16px',
    borderTop: '1px solid var(--panel-border, rgba(0,0,0,0.08))',
  } as const
  const inputStyle = {
    height: 40,
    padding: '8px 12px',
    borderRadius: 8,
    border: '1px solid var(--panel-border, rgba(0,0,0,0.12))',
    width: '100%',
    boxSizing: 'border-box' as const,
    background: '#fff',
    color: '#111'
  }
  const sectionTitleStyle = {
    padding: '8px 16px',
    fontSize: 12,
    fontWeight: 600,
    color: 'var(--muted-700, #555)',
    letterSpacing: 0.4,
  } as const
  const noteStyle = { fontSize: 12, color: 'var(--muted-600, #666)', marginTop: 6 }
  const dividerStyle = { height: 1, background: 'var(--panel-border, rgba(0,0,0,0.08))' }

  const create = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload: any = {
        name: values.name,
        sku: values.sku,
        unit: values.unit,
        vat_rate: values.vat,
        currency: values.currency,
      }
      if (values.price !== undefined) payload.unit_price = values.price
      const metadata: Record<string, any> = {}
      if (values.barcode) metadata.barcode = values.barcode
      if (values.category) metadata.category = values.category
      if (values.withholding !== undefined) metadata.withholding_rate = values.withholding
      if (values.vatExemptionCode) metadata.vat_exemption_code = values.vatExemptionCode
      if (values.gtip) metadata.gtip_code = values.gtip
      if (values.purchasePrice !== undefined) metadata.purchase_price = values.purchasePrice
      if (values.stockTracking) metadata.stock_tracking = true
      if (values.initialStock !== undefined) metadata.initial_stock = values.initialStock
      if (values.criticalStock !== undefined) metadata.critical_stock = values.criticalStock
      if (Object.keys(metadata).length) payload.metadata = metadata
      await api.post('/products', payload)
    },
    onSuccess: () => {
      navigate('/app/products', { replace: true })
    },
    onError: () => {},
  })

  return (
    <div style={{ padding: 24 }}>
      <PageHeader title="Yeni Ürün" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Ürünler', href: '/app/products' }, { label: 'Yeni' }]} />
      <div className="card" style={{ padding: 0, maxWidth: 980, overflow: 'hidden' }}>
        <div style={{ padding: 16, borderBottom: '1px solid var(--panel-border, rgba(0,0,0,0.08))', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div style={{ fontWeight: 600 }}>Ürün Bilgileri</div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button onClick={() => navigate('/app/products')}>Vazgeç</button>
            <button onClick={handleSubmit((v) => create.mutate(v))} disabled={isSubmitting || create.isPending}>
              {create.isPending ? 'Kaydediliyor…' : 'Kaydet'}
            </button>
          </div>
        </div>

        <div style={{ display: 'grid', gap: 0 }}>
          <div style={sectionTitleStyle}>GENEL BİLGİLER</div>
          <div style={rowStyle}>
            <label htmlFor="p-name">Ad</label>
            <input id="p-name" {...register('name')} style={inputStyle} />
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-sku">Ürün / Stok Kodu (SKU)</label>
            <input id="p-sku" {...register('sku')} style={inputStyle} />
          </div>

          <div style={rowStyle}>
            <label htmlFor="p-unit">Alış / Satış Birimi</label>
            <select id="p-unit" {...register('unit')} style={inputStyle as any}>
              <option value="Adet">Adet</option>
              <option value="Saat">Saat</option>
              <option value="KG">KG</option>
              <option value="PAKET">PAKET</option>
            </select>
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-barcode">Barkod Numarası</label>
            <input id="p-barcode" {...register('barcode')} style={inputStyle} />
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-category">Kategorisi</label>
            <input id="p-category" {...register('category')} style={inputStyle} />
          </div>

          <div style={{ ...dividerStyle, margin: '12px 0' }} />

          <div style={sectionTitleStyle}>VERGİ VE FİYAT</div>
          <div style={rowStyle}>
            <label htmlFor="p-vat">KDV (%)</label>
            <div>
              <input id="p-vat" {...register('vat')} style={inputStyle} />
              <div style={noteStyle}>Örn: 20. KDV muaf ise 0 giriniz ve istisna kodunu doldurun.</div>
            </div>
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-wht">Tevkifat (%)</label>
            <div>
              <input id="p-wht" {...register('withholding')} style={inputStyle} />
              <div style={noteStyle}>Varsa satıra uygulanacak tevkifat oranı.</div>
            </div>
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-gtip">GTİP Kodu</label>
            <input id="p-gtip" {...register('gtip')} style={inputStyle} />
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-vat-exc">KDV İstisna Kodu</label>
            <div>
              <input id="p-vat-exc" {...register('vatExemptionCode')} style={inputStyle} />
              <div style={noteStyle}>KDV 0 ise zorunlu olabilir. Kolaysoft istisna kodlarıyla uyumlu giriniz.</div>
            </div>
          </div>

          <div style={rowStyle}>
            <label htmlFor="p-pprice">Alış Fiyatı (vergiler hariç)</label>
            <input id="p-pprice" {...register('purchasePrice')} style={inputStyle} />
          </div>
          <div style={rowStyle}>
            <label htmlFor="p-sprice">Vergiler Hariç Satış Fiyatı</label>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 160px', gap: 12 }}>
              <input id="p-sprice" {...register('price')} style={inputStyle} />
              <select {...register('currency')} style={inputStyle as any}>
                <option value="TRY">TRY</option>
                <option value="USD">USD</option>
                <option value="EUR">EUR</option>
              </select>
            </div>
          </div>

          <div style={{ ...dividerStyle, margin: '12px 0' }} />

          <div style={sectionTitleStyle}>STOK</div>
          <div style={rowStyle}>
            <span>Stok Takibi</span>
            <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
              <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                <input type="radio" name="stock" checked={stockTracking === true} onChange={() => (document.getElementById('stock-yes') as HTMLInputElement)?.click()} /> Yapılsın
              </label>
              <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                <input type="radio" name="stock" checked={stockTracking === false} onChange={() => (document.getElementById('stock-no') as HTMLInputElement)?.click()} /> Yapılmasın
              </label>
              <input id="stock-yes" type="checkbox" {...register('stockTracking')} style={{ display:'none' }} />
              <input id="stock-no" type="checkbox" onChange={() => {}} style={{ display:'none' }} />
            </div>
          </div>
          {stockTracking && (
            <>
              <div style={rowStyle}>
                <label htmlFor="p-istock">Başlangıç Stok Miktarı</label>
                <input id="p-istock" {...register('initialStock')} style={inputStyle} />
              </div>
              <div style={rowStyle}>
                <label htmlFor="p-cstock">Kritik Stok Uyarısı</label>
                <input id="p-cstock" {...register('criticalStock')} style={inputStyle} />
              </div>
            </>
          )}

          <div style={{ height: 1, background: 'var(--panel-border, rgba(0,0,0,0.08))', margin: '12px 0' }} />
          <div style={{ position: 'sticky', bottom: 0, background: 'var(--panel-bg, #fff)', padding: 12, display: 'flex', gap: 8, justifyContent: 'flex-end', borderTop: '1px solid var(--panel-border, rgba(0,0,0,0.08))' }}>
            <button onClick={() => navigate('/app/products')}>Vazgeç</button>
            <button onClick={handleSubmit((v) => create.mutate(v))} disabled={isSubmitting || create.isPending}>
              {create.isPending ? 'Kaydediliyor…' : 'Kaydet'}
            </button>
          </div>
          {Object.values(errors).length > 0 && (
            <div style={{ color: 'var(--danger, #dc2626)', padding: '0 16px 12px' }}>
              {Object.values(errors).map((e, i) => (<div key={i}>{e?.message as string}</div>))}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}


