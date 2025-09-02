import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useToast } from '../components/ui/ToastProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const formSchema = z.object({
	mode: z.enum(['despatch', 'receipt']),
	ettn: z.string().optional(),
	xml: z.string().optional().or(z.literal('')),
})

type FormValues = z.infer<typeof formSchema>

export default function DespatchCreatePage() {
	const navigate = useNavigate()
	const toast = useToast()
	const form = useForm<FormValues>({
		resolver: zodResolver(formSchema) as any,
		defaultValues: { mode: 'despatch', ettn: '', xml: '' },
	})

	async function onSubmit(values: FormValues) {
		try {
			const payload: any = { mode: values.mode }
			if (values.xml) payload.xml = values.xml
			if (values.ettn) payload.ettn = values.ettn
			const idempo = (crypto as any).randomUUID ? (crypto as any).randomUUID() : String(Date.now())
			await api.post('/despatches', payload, { headers: { 'X-Idempotency-Key': idempo } })
			toast.show({ type: 'success', title: 'İrsaliye kuyruğa alındı' })
			navigate('/app/despatches')
		} catch (e: any) {
			form.setError('root', { message: e?.response?.data?.message || 'Gönderim sırasında hata oluştu' })
		}
	}

	const allErrors = [
		...Object.values(form.formState.errors).flatMap((err: any) => (err?.message ? [err.message] : [])),
	]

	return (
		<div style={{ padding: 24, maxWidth: 900 }}>
			<h2>E‑İrsaliye Oluştur</h2>
			{allErrors.length > 0 && (
				<div className="card" style={{ background: '#fffbeb', borderColor: '#fde68a', margin: '12px 0' }}>
					<div style={{ color: '#92400e', fontWeight: 600 }}>Hata</div>
					<ul style={{ margin: '6px 0 0 16px' }}>{allErrors.map((m, i) => (<li key={i}>{m}</li>))}</ul>
				</div>
			)}
			<form onSubmit={form.handleSubmit(onSubmit)}>
				<div style={{ display: 'grid', gap: 12 }}>
					<label>
						Mod
						<select {...form.register('mode')} style={{ marginLeft: 8 }}>
							<option value="despatch">İrsaliye</option>
							<option value="receipt">İrsaliye Yanıtı</option>
						</select>
					</label>
					<label>
						ETTN (opsiyonel)
						<input {...form.register('ettn')} style={{ marginLeft: 8, width: 280 }} />
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


