import { useMutation } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const schema = z.object({
  name: z.string().min(1, 'Ad zorunlu'),
  sku: z.string().min(1, 'SKU zorunlu'),
  unit: z.string().optional(),
  vat: z.coerce.number().min(0).max(100).optional(),
  price: z.coerce.number().min(0).optional(),
})
type FormValues = z.infer<typeof schema>

export default function ProductCreateStandalone() {
  const navigate = useNavigate()
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { unit: 'Adet' } })

  const create = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload: any = { name: values.name, sku: values.sku }
      if (values.unit) payload.unit = values.unit
      if (values.vat !== undefined) payload.vat_rate = values.vat
      if (values.price !== undefined) { payload.unit_price = values.price; payload.currency = 'TRY' }
      await api.post('/products', payload)
    },
    onSuccess: () => { navigate('/app/products', { replace: true }) },
  })

  return (
    <div style={{ padding: 24, maxWidth: 720, margin: '0 auto' }}>
      <h1>Ürün Oluştur</h1>
      <p>Panel dışında hızlı ürün ekleme.</p>
      <form onSubmit={handleSubmit((v) => create.mutate(v))} style={{ display: 'grid', gap: 12, marginTop: 16 }}>
        <input placeholder="Ad" {...register('name')} />
        <input placeholder="SKU" {...register('sku')} />
        <input placeholder="Birim (ör. ADET)" {...register('unit')} />
        <input placeholder="KDV (%)" type="number" step="1" {...register('vat')} />
        <input placeholder="Fiyat (TRY)" type="number" step="0.01" {...register('price')} />
        <button type="submit" disabled={isSubmitting || create.isPending}>
          {create.isPending ? 'Kaydediliyor…' : 'Kaydet ve Tabloyu Aç'}
        </button>
        {Object.values(errors).length > 0 && (
          <div style={{ color: 'var(--danger, #dc2626)' }}>
            {Object.values(errors).map((e, i) => (<div key={i}>{e?.message as string}</div>))}
          </div>
        )}
      </form>
    </div>
  )
}


