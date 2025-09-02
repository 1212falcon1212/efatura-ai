import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useToast } from '../components/ui/ToastProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const formSchema = z.object({
	type: z.enum(['SMM', 'MM']),
	ettn: z.string().optional(),
	destinationEmail: z.string().email('Geçerli bir e‑posta girin').optional().or(z.literal('')),
	xml: z.string().optional().or(z.literal('')),
})

type FormValues = z.infer<typeof formSchema>

export default function VoucherCreatePage() {
	const navigate = useNavigate()
	const toast = useToast()
	const form = useForm<FormValues>({
		resolver: zodResolver(formSchema) as any,
		defaultValues: { type: 'SMM', ettn: '', destinationEmail: '', xml: '' },
	})

	async function onSubmit(values: FormValues) {
		try {
			const payload: any = { type: values.type }
			if (values.xml) payload.xml = values.xml
			if (values.ettn) payload.ettn = values.ettn
			if (values.destinationEmail) payload.destinationEmail = values.destinationEmail
			const idempo = (crypto as any).randomUUID ? (crypto as any).randomUUID() : String(Date.now())
			await api.post('/vouchers', payload, { headers: { 'X-Idempotency-Key': idempo } })
			toast.show({ type: 'success', title: 'Makbuz kuyruğa alındı' })
			navigate('/app/vouchers')
		} catch (e: any) {
			form.setError('root', { message: e?.response?.data?.message || 'Gönderim sırasında hata oluştu' })
		}
	}

	const allErrors = [
		...Object.values(form.formState.errors).flatMap((err: any) => (err?.message ? [err.message] : [])),
	]

	return (
		<div style={{ padding: 24, maxWidth: 900 }}>
			<h2>E‑Makbuz Oluştur</h2>
			{allErrors.length > 0 && (
				<div className="card" style={{ background: '#fffbeb', borderColor: '#fde68a', margin: '12px 0' }}>
					<div style={{ color: '#92400e', fontWeight: 600 }}>Hata</div>
					<ul style={{ margin: '6px 0 0 16px' }}>{allErrors.map((m, i) => (<li key={i}>{m}</li>))}</ul>
				</div>
			)}
			<form onSubmit={form.handleSubmit(onSubmit)}>
				<div style={{ display: 'grid', gap: 12 }}>
					<label>
						Tip
						<select {...form.register('type')} style={{ marginLeft: 8 }}>
							<option value="SMM">SMM</option>
							<option value="MM">MM</option>
						</select>
					</label>
					<label>
						ETTN (opsiyonel)
						<input {...form.register('ettn')} style={{ marginLeft: 8, width: 280 }} />
					</label>
					<label>
						Hedef E‑posta (opsiyonel)
						<input {...form.register('destinationEmail')} style={{ marginLeft: 8, width: 320 }} />
					</label>
					<label style={{ display: 'grid' }}>
						XML (UBL)
						<textarea {...form.register('xml')} rows={12} style={{ fontFamily: 'monospace' }} />
					</label>
					<div className="sticky-actions">
						<div />
						<div>
							<button type="submit" disabled={form.formState.isSubmitting}>{form.formState.isSubmitting ? 'Gönderiliyor…' : 'Kaydet ve Kuyruğa Al'}</button>
							{form.formState.errors.root?.message && <span style={{ color: 'crimson', marginLeft: 12 }}>{form.formState.errors.root?.message}</span>}
						</div>
					</div>
				</div>
			</form>
		</div>
	)
}


