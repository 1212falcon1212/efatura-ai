import { useMutation } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const schema = z.object({
  customerVkn: z.string().min(10, 'VKN/TCKN 10-11 haneli olmalı').max(11),
  customerEmail: z.string().email('Geçerli e‑posta giriniz'),
  documentId: z.string().min(1, 'Belge no zorunlu'),
  price: z.coerce.number().min(0.01, 'Tutar > 0 olmalı'),
})
type FormValues = z.infer<typeof schema>

export default function InvoiceCreateStandalone() {
  const navigate = useNavigate()
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({ resolver: zodResolver(schema) })

  const create = useMutation({
    mutationFn: async (values: FormValues) => {
      const payload: any = {
        profile: 'EARSIVFATURA',
        documentId: values.documentId,
        issueDate: new Date().toISOString().slice(0,10),
        receiver: { identifier: values.customerVkn, email: values.customerEmail },
        items: [ { name: 'Hizmet', quantity: 1, unitPrice: values.price, vatRate: 20 } ],
      }
      await api.post('/invoices', payload)
    },
    onSuccess: () => { navigate('/app/invoices', { replace: true }) },
  })

  return (
    <div style={{ padding: 24, maxWidth: 720, margin: '0 auto' }}>
      <h1>Fatura Oluştur</h1>
      <p>Panel dışında hızlı e‑Arşiv fatura oluşturma.</p>
      <form onSubmit={handleSubmit((v) => create.mutate(v))} style={{ display: 'grid', gap: 12, marginTop: 16 }}>
        <input placeholder="Alıcı VKN/TCKN" {...register('customerVkn')} />
        <input placeholder="Alıcı e‑posta" {...register('customerEmail')} />
        <input placeholder="Belge No" {...register('documentId')} />
        <input placeholder="Tutar (TRY)" type="number" step="0.01" {...register('price')} />
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


