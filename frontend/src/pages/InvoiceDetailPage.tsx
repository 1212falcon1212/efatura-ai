import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { InvoicePreview, openInvoicePrintWindow } from '../components/preview/InvoicePreview'

export default function InvoiceDetailPage() {
	const { id } = useParams()
	const navigate = useNavigate()
	const [data, setData] = useState<any>(null)
	const [loading, setLoading] = useState(true)
	const [error, setError] = useState('')
	const [busy, setBusy] = useState(false)

	useEffect(() => {
		let mounted = true
		;(async () => {
			try {
				setLoading(true)
				const res = await api.get(`/invoices/${id}`)
				if (mounted) setData(res.data)
			} catch (e: any) {
				if (mounted) setError(e?.response?.data?.message || 'Yükleme hatası')
			} finally {
				if (mounted) setLoading(false)
			}
		})()
		return () => {
			mounted = false
		}
	}, [id])

	async function cancel() {
		if (!id) return
		setBusy(true)
		try {
			const idempo = crypto.randomUUID ? crypto.randomUUID() : String(Date.now())
			const res = await api.post(`/invoices/${id}/cancel`, {}, { headers: { 'X-Idempotency-Key': idempo } })
			setData(res.data)
		} finally {
			setBusy(false)
		}
	}

	async function retry() {
		if (!id) return
		setBusy(true)
		try {
			const idempo = crypto.randomUUID ? crypto.randomUUID() : String(Date.now())
			const res = await api.post(`/invoices/${id}/retry`, {}, { headers: { 'X-Idempotency-Key': idempo } })
			setData(res.data)
		} finally {
			setBusy(false)
		}
	}

	return (
		<div style={{ padding: 24 }}>
			<button onClick={() => navigate(-1)}>Geri</button>
			<h2>Fatura Detayı</h2>
			{loading && <div>Yükleniyor…</div>}
			{error && <div style={{ color: 'crimson' }}>{error}</div>}
			{!loading && data && (
				<div style={{ display: 'grid', gap: 12 }}>
					<div>
						<strong>ID:</strong> {data.id}
					</div>
					<div>
						<strong>Durum:</strong> {data.status}
					</div>
					<div>
						<strong>Müşteri:</strong> {data.customer?.name ?? '-'}
					</div>
					<div>
						<strong>Toplam:</strong> {data.totals?.grandTotal?.amount} {data.totals?.grandTotal?.currency}
					</div>
					<div style={{ display: 'flex', gap: 8 }}>
						{['queued','processing'].includes(data.status) && (
							<button onClick={cancel} disabled={busy}>İptal Et</button>
						)}
						{data.status === 'failed' && (
							<button onClick={retry} disabled={busy}>Yeniden Dene</button>
						)}
						<button onClick={() => openInvoicePrintWindow(data)}>Yazdır</button>
					</div>
					<div>
						<h3>Önizleme</h3>
						<InvoicePreview data={data} />
					</div>
					{data.xml && (
						<div>
							<h3>XML</h3>
							<pre style={{ whiteSpace: 'pre-wrap', background: '#f8f8f8', padding: 12 }}>{data.xml}</pre>
						</div>
					)}
				</div>
			)}
		</div>
	)
}


