import { useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useToast } from '../components/ui/ToastProvider'
import { useForm, useFieldArray } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import * as React from 'react'
import { TR_CITIES } from '../data/tr-cities'
import { useQuery } from '@tanstack/react-query'
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label.tsx';

const itemSchema = z.object({
	name: z.string().min(1, 'Kalem adı zorunludur'),
	quantity: z.coerce.number().positive('Miktar > 0 olmalı'),
	unit: z.string().min(1, 'Birim zorunludur'),
	vatRate: z.coerce.number().min(0).max(40),
	unitPrice: z.object({
		amount: z.coerce.number().min(0, 'Fiyat negatif olamaz'),
		currency: z.string().min(3).max(3),
	}),
	metadata: z
		.object({
			lineTotal: z.coerce.number().optional(),
			discountPercent: z.coerce.number().optional(),
			discountAmount: z.coerce.number().optional(),
		})
		.optional(),
})

const formSchema = z.object({
	type: z.enum(['e_fatura', 'e_arsiv']),
	issueDate: z.string().optional(),
	customerName: z.string().min(2, 'Müşteri adı en az 2 karakter'),
	customerId: z.number().optional(),
	items: z.array(itemSchema).min(1, 'En az bir kalem ekleyin'),
})

type FormValues = z.infer<typeof formSchema>

type Item = z.infer<typeof itemSchema>

export default function InvoiceCreatePage() {
	const navigate = useNavigate()
	const toast = useToast()
	const [customerSearch, setCustomerSearch] = React.useState('')
	const [customerSelected, setCustomerSelected] = React.useState<{ id: number; name: string } | null>(null)
	const [selectedCustomerDetail, setSelectedCustomerDetail] = React.useState<any | null>(null)
	const [newCustomer, setNewCustomer] = React.useState<{ name: string; surname?: string; vkn?: string; tckn?: string; email?: string; address?: string; city?: string; district?: string }>({ name: '' })
	const [unknownTaxId, setUnknownTaxId] = React.useState(false)
	const [createCustomerOpen, setCreateCustomerOpen] = React.useState(false)
	const [destUrn, setDestUrn] = React.useState('')

	const form = useForm<FormValues>({
		resolver: zodResolver(formSchema) as any,
		defaultValues: {
			type: 'e_fatura',
			issueDate: '',
			customerName: '',
			customerId: undefined,
			items: [{ name: 'Ürün', quantity: 1, unit: 'Adet', vatRate: 20, unitPrice: { amount: 100, currency: 'TRY' } }],
		},
		mode: 'onSubmit',
	})
	const { fields, append, remove, update } = useFieldArray({ control: form.control, name: 'items' })

	// Fatura bilgileri (metadata)
	const [scenario, setScenario] = React.useState<'TEMEL'|'IHRACAT'|'IADESIZ'>('TEMEL')
	const [invoiceKind, setInvoiceKind] = React.useState<'SATIS'|'TEVKIFAT'|'IADE'>('SATIS')
	const [currency, setCurrency] = React.useState<'TRY'|'USD'|'EUR'>('TRY')
	const [issueTime, setIssueTime] = React.useState('')
	const [xsltTemplate, setXsltTemplate] = React.useState('')

	// Wizard adımları
	const steps = ['Temel Bilgiler','Müşteri','Ürün/Hizmet','Ödeme & Kargo','Özet']
	const [step, setStep] = React.useState<number>(1)
	const canPrev = step > 1

	// Ön tanımlı tipler (dropdown)
	const presetOptions = [
		{ value: 'sgk', label: 'SGK Fatura', apply: () => { setScenario('TEMEL'); setInvoiceKind('SATIS'); setXsltTemplate('SGK'); } },
		{ value: 'ihracat', label: 'İhracat', apply: () => { setScenario('IHRACAT'); setInvoiceKind('SATIS'); setCurrency('USD'); } },
		{ value: 'perakende', label: 'Perakende', apply: () => { setScenario('TEMEL'); setInvoiceKind('SATIS'); setCurrency('TRY'); } },
		{ value: 'tevkifat', label: 'Tevkifat', apply: () => { setScenario('TEMEL'); setInvoiceKind('TEVKIFAT'); } },
	]
	const [selectedPreset, setSelectedPreset] = React.useState<string>('')

	// Ek bilgiler
	const [orderNumber] = React.useState('')
	const [orderDate] = React.useState('')
	const [cargoEnabled, setCargoEnabled] = React.useState(false)
	const [cargoCompany, setCargoCompany] = React.useState('')
	const [cargoVkn, setCargoVkn] = React.useState('')
	const [cargoDate, setCargoDate] = React.useState('')
	const [cargoTracking, setCargoTracking] = React.useState('')
	const [paymentMethod, setPaymentMethod] = React.useState<'NAKIT'|'KREDI_KARTI'|'HAVALE'|'PLATFORM'>('NAKIT')
	const [paymentPlatform, setPaymentPlatform] = React.useState('')
	const [paymentPos, setPaymentPos] = React.useState('')
	const [iban, setIban] = React.useState('')
	const [note, setNote] = React.useState('')
	const [orders] = React.useState<Array<{ no: string; date: string }>>([])
    // Internet üzerinden satış modal state
    const [internetSaleOpen, setInternetSaleOpen] = React.useState(false)
    const [internetExists, setInternetExists] = React.useState(false)
    const [internetWeb, setInternetWeb] = React.useState('')
    const [internetPaymentType, setInternetPaymentType] = React.useState('')
    const [internetPlatform, setInternetPlatform] = React.useState('')
    const [internetDate, setInternetDate] = React.useState('')
    const internetPlatformPresets = ['Trendyol','Hepsiburada','N11','Amazon','Shopify','WooCommerce','İyzico','PayU']
    // restore last
    React.useEffect(()=>{
        try {
            const raw = localStorage.getItem('inetSaleLast')
            if (raw) {
                const v = JSON.parse(raw)
                if (v && typeof v === 'object') {
                    if (typeof v.exists === 'boolean') setInternetExists(v.exists)
                    if (typeof v.web === 'string') setInternetWeb(v.web)
                    if (typeof v.paymentType === 'string') setInternetPaymentType(v.paymentType)
                    if (typeof v.platform === 'string') setInternetPlatform(v.platform)
                    if (typeof v.date === 'string') setInternetDate(v.date)
                }
            }
        } catch {}
    },[])
    function saveInternetSaleToStorage(){
        try { localStorage.setItem('inetSaleLast', JSON.stringify({ exists: internetExists, web: internetWeb, paymentType: internetPaymentType, platform: internetPlatform, date: internetDate })) } catch {}
    }

	// Satır toplamı alanında akıcı yazım için geçici giriş değerleri
	const [lineTotalInputs, setLineTotalInputs] = React.useState<Record<number, string>>({})

	function commitLineTotalInput(rowIndex: number) {
		const raw = lineTotalInputs[rowIndex]
		if (raw == null) return
		const normalized = String(raw).replace(/,/g, '.')
		const parsed = Number(normalized)
		const current = form.getValues(`items.${rowIndex}`) as any
		if (!isNaN(parsed)) {
			if (current?.quantity > 0) {
				const unitNet = parsed / current.quantity / (1 + Number(current.vatRate || 0) / 100)
				update(rowIndex, {
					...(current as Item),
					unitPrice: { ...(current.unitPrice || {}), amount: Number(unitNet.toFixed(2)) },
					metadata: { ...((current.metadata) || {}), lineTotal: parsed },
				})
			} else {
				update(rowIndex, { ...(current as Item), metadata: { ...((current.metadata) || {}), lineTotal: parsed } })
			}
		}
		setLineTotalInputs((s) => { const { [rowIndex]: _drop, ...rest } = s; return rest })
	}

	// Ürün arama (satır bazında)
	const [activeProductRow, setActiveProductRow] = React.useState<number | null>(null)
	const [productSearch, setProductSearch] = React.useState('')
	const productQ = (function useDebouncedInline(v: string){ const [dv, setDv] = React.useState(v); React.useEffect(()=>{ const t=setTimeout(()=>setDv(v),300); return ()=>clearTimeout(t) },[v]); return dv }) (productSearch)
	const productsQuery = useQuery({ queryKey: ['products', productQ], queryFn: async () => { if (!productQ || productQ.length < 2) return [] as Array<any>; const res = await api.get('/products', { params: { q: productQ, sort: 'name', order: 'asc' } }); return res.data?.data ?? res.data ?? [] }, staleTime: 60_000, enabled: !!productQ && productQ.length >= 2 })

	// Modal ürün seçici
	const [pickerIdx, setPickerIdx] = React.useState<number | null>(null)
	const [pickerSearch, setPickerSearch] = React.useState('')
	const pickerQ = (function useDebouncedInline(v: string){ const [dv, setDv] = React.useState(v); React.useEffect(()=>{ const t=setTimeout(()=>setDv(v),300); return ()=>clearTimeout(t) },[v]); return dv }) (pickerSearch)
	const pickerProductsQuery = useQuery({ queryKey: ['products_picker', pickerQ], queryFn: async () => { if (!pickerQ) return [] as Array<any>; const res = await api.get('/products', { params: { q: pickerQ, sort: 'name', order: 'asc' } }); return res.data?.data ?? res.data ?? [] }, staleTime: 60_000 })

	function applyProductToRow(idx: number, p: any) {
		const current = form.getValues(`items.${idx}`) as Item
		const amount = p?.unit_price != null ? Number(p.unit_price) : (current?.unitPrice?.amount ?? 0)
		const cur = p?.currency || currency
		update(idx, { name: p?.name || current.name, quantity: current.quantity || 1, unit: p?.unit || current.unit || 'Adet', vatRate: p?.vat_rate != null ? Number(p.vat_rate) : (current.vatRate ?? 0), unitPrice: { amount, currency: cur }, sku: p?.sku } as any)
		setActiveProductRow(null)
	}

	// Toplamlar
	const totals = React.useMemo(() => {
		let subtotal = 0, vatTotal = 0
		const items = form.getValues('items') as Item[]
		for (const it of items) {
			const qty = Number(it.quantity || 0)
			const price = Number((it.unitPrice as any)?.amount || 0)
			const discPct = Number(((it as any)?.metadata?.discountPercent) || 0)
			const discAmt = Number(((it as any)?.metadata?.discountAmount) || 0)
			const netUnit = Math.max(0, price * (1 - discPct / 100) - discAmt)
			const lineNet = Math.max(0, qty * netUnit)
			subtotal += lineNet
			vatTotal += lineNet * Number(it.vatRate || 0) / 100
		}
		return { subtotal: Number(subtotal.toFixed(2)), vatTotal: Number(vatTotal.toFixed(2)), grandTotal: Number((subtotal + vatTotal).toFixed(2)) }
	}, [form.watch('items')])

	// Müşteri arama
	function useDebounced(value: string, delay = 300) { const [v, setV] = React.useState(value); React.useEffect(()=>{ const t=setTimeout(()=>setV(value),delay); return ()=>clearTimeout(t)},[value,delay]); return v }
	const qDebounced = useDebounced(customerSearch)
	const customersQuery = useQuery({ queryKey: ['customers', qDebounced], queryFn: async () => { if (!qDebounced || qDebounced.length < 1) return [] as Array<{ id: number; name: string }>; const res = await api.get('/customers', { params: { q: qDebounced.trim(), sort: 'name', order: 'asc' } }); const arr = res.data?.data ?? res.data ?? []; return arr }, staleTime: 60_000, enabled: !!(qDebounced && qDebounced.length >= 1) })

	// Müşteri adı girilince: eşleşme varsa yeni müşteri formu gizli, yoksa göster
	const customerNameValue = form.watch('customerName')
	const hasCustomerMatches = !!(customersQuery.data && (customersQuery.data as any[]).length > 0)

	async function pickCustomer(id: number, name: string) { setCustomerSelected({ id, name }); form.setValue('customerName', name); form.setValue('customerId', id); try { const res = await api.get(`/customers/${id}`); setSelectedCustomerDetail(res.data) } catch { setSelectedCustomerDetail(null) } }

	async function onSubmit(values: FormValues) {
		try {
            const customerPayload: { id?: number; name: string; tckn_vkn?: string; surname?:string; email?:string; street_address?:string; city?:string; district?:string; tax_office?:string; urn?:string; } = {
                id: form.getValues('customerId'),
                name: form.getValues('customerName'),
            };

            // Eğer yeni müşteri oluşturulduysa, tüm detayları ekle
            if (!customerPayload.id) {
                Object.assign(customerPayload, {
                    name: newCustomer.name || form.getValues('customerName'),
                    surname: newCustomer.surname || '',
                    email: newCustomer.email,
                    street_address: newCustomer.address,
                    city: newCustomer.city,
                    district: newCustomer.district,
                    tckn_vkn: unknownTaxId ? '11111111111' : (newCustomer.vkn || newCustomer.tckn),
                });
            }

			const payload = {
				type: values.type,
				issue_date: values.issueDate || undefined,
				customer: customerPayload,
				items: (values.items as any[]).map((it: any) => ({
                    name: it.name,
                    quantity: it.quantity,
                    unit: it.unit,
                    vat_rate: it.vatRate,
                    unit_price: it.unitPrice,
                    sku: it.sku,
                    metadata: it.metadata || {}
                })),
				metadata: {
					scenario, invoiceKind, currency, issueTime, xsltTemplate,
					orderNumber, orderDate,
					cargo: cargoEnabled ? { company: cargoCompany, vkn: cargoVkn, date: cargoDate, tracking: cargoTracking } : undefined,
					payment: { method: paymentMethod, platform: paymentPlatform, pos: paymentPos, iban, note },
					destinationUrn: destUrn || undefined,
					internetSale: internetExists ? {
                        webAddress: internetWeb,
                        paymentType: internetPaymentType,
                        paymentPlatform: internetPlatform,
                        paymentDate: internetDate
                    } : undefined,
					orders,
				},
			}
			const idempo = (crypto as any).randomUUID ? (crypto as any).randomUUID() : String(Date.now())
			const res = await api.post('/invoices', payload, { headers: { 'X-Idempotency-Key': idempo } })
			if (res.status === 202) { toast.show({ type: 'success', title: 'Fatura kuyruğa alındı' }); navigate('/app/invoices') }
		} catch (e: any) { form.setError('root', { message: e?.response?.data?.message || 'Gönderim sırasında hata oluştu' }) }
	}

	const allErrors = [ ...Object.values(form.formState.errors).flatMap((err: any) => (err?.message ? [err.message] : [])) ]

	function StepHeader() {
		return (
			<div className="card" style={{ padding: 8, display:'flex', gap: 12, alignItems:'center', flexWrap:'wrap' }}>
				{steps.map((s, idx) => (
					<div key={s} onClick={()=>setStep(idx+1)} style={{ cursor:'pointer', padding:'6px 10px', borderRadius:999, background: step===idx+1 ? 'var(--primary, #2563eb)' : 'transparent', color: step===idx+1 ? '#fff' : 'var(--muted-700, #555)', border: '1px solid var(--panel-border, rgba(0,0,0,0.08))' }}>{idx+1}. {s}</div>
				))}
			</div>
		)
	}

	function Navigation() {
		return (
			<div className="sticky-actions">
				<button type="button" onClick={()=>setStep((s)=>Math.max(1,s-1))} disabled={!canPrev}>Geri</button>
				{step < steps.length && (<button type="button" onClick={()=>setStep((s)=>Math.min(steps.length,s+1))}>İleri</button>)}
				{step === steps.length && (<button type="submit" disabled={form.formState.isSubmitting}>{form.formState.isSubmitting ? 'Gönderiliyor…' : 'Kaydet ve Kuyruğa Al'}</button>)}
			</div>
		)
	}

	return (
		<div style={{ padding: 24, maxWidth: 1280 }}>
			<h2>Fatura Oluştur</h2>
			{(allErrors.length > 0 || form.formState.errors.items) && (<div className="card" style={{ background: '#fffbeb', borderColor: '#fde68a', margin: '12px 0' }}><div style={{ color: '#92400e', fontWeight: 600 }}>Hata</div><ul style={{ margin: '6px 0 0 16px' }}>{allErrors.map((m, i) => (<li key={i}>{m}</li>))}{'items' in form.formState.errors && Array.isArray(form.formState.errors.items) && (form.formState.errors.items as any[]).map((itErr, i) => (<li key={`it-${i}`}>{itErr?.root?.message}</li>))}</ul></div>)}
			<form onSubmit={form.handleSubmit(onSubmit)}>
				<StepHeader />
				<div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: 20 }}>
					<div style={{ display: 'grid', gap: 12 }}>
						{step === 1 && (
							<div className="card" style={{ padding: 16 }}>
								<div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:16 }}>
									<div className="card" style={{ padding: 12, display:'grid', gap: 10 }}>
										<label>Tip<select {...form.register('type')} style={{ height:44, width:'100%' }}><option value="e_fatura">E‑Fatura</option><option value="e_arsiv">E‑Arşiv</option></select></label>
										<label>Düzenleme Tarihi<input type="date" {...form.register('issueDate')} style={{ height:44, width:'100%' }} /></label>
										<label>Düzenleme Saati<input type="time" value={issueTime} onChange={(e)=>setIssueTime(e.target.value)} style={{ height:44, width:'100%' }} /></label>
									</div>
									<div className="card" style={{ padding: 12, display:'grid', gap: 10 }}>
										<label>Senaryo<select value={scenario} onChange={(e)=>setScenario(e.target.value as any)} style={{ height:44, width:'100%' }}><option value="TEMEL">Temel</option><option value="IHRACAT">İhracat</option><option value="IADESIZ">İadesiz</option></select></label>
										<label>Tür<select value={invoiceKind} onChange={(e)=>setInvoiceKind(e.target.value as any)} style={{ height:44, width:'100%' }}><option value="SATIS">Satış</option><option value="TEVKIFAT">Tevkifat</option><option value="IADE">İade</option></select></label>
										<label>Para Birimi<select value={currency} onChange={(e)=>setCurrency(e.target.value as any)} style={{ height:44, width:'100%' }}><option value="TRY">TRY</option><option value="USD">USD</option><option value="EUR">EUR</option></select></label>
									</div>
									<div className="card" style={{ padding: 12, gridColumn:'1 / span 2', display:'grid', gap: 10 }}>
										<label>Ön Tanımlı Tipler<select value={selectedPreset} onChange={(e)=>{ setSelectedPreset(e.target.value); const p = presetOptions.find(x=>x.value===e.target.value); if (p) p.apply(); }} style={{ height:44, width:'100%' }}><option value="">— Seç —</option>{presetOptions.map(p=>(<option key={p.value} value={p.value}>{p.label}</option>))}</select></label>
										<label>Şablon (opsiyonel)<input value={xsltTemplate} onChange={(e)=>setXsltTemplate(e.target.value)} style={{ height:44, width:'100%' }} /></label>
									</div>
								</div>
								<Navigation />
							</div>
						)}

						{step === 2 && (
							<div className="card" style={{ padding: 16 }}>
								<div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap: 16 }}>
									<div className="card" style={{ padding: 12 }}>
										<div style={{ position: 'relative', display: 'flex', gap: 8, alignItems: 'center' }}>
											<input
												value={form.watch('customerName')}
												onChange={(e)=>{ form.setValue('customerName', e.target.value); form.setValue('customerId', undefined); setCustomerSelected(null); setSelectedCustomerDetail(null); setCustomerSearch(e.target.value) }}
												placeholder="Müşteri adı yazın"
												style={{ width: '100%', height:44, background:'#fff', color:'#111', border:'1px solid var(--panel-border, rgba(0,0,0,0.12))' }}
												onKeyDown={(e)=>{ if (e.key==='Enter') { const list = (customersQuery.data as any[]||[]); if (list.length>0) { e.preventDefault(); const exact = (list as any[]).find((c:any)=> String(c.name||'').toLowerCase()===String(form.getValues('customerName')||'').toLowerCase()); const c = exact || list[0]; pickCustomer(c.id, c.name); } } }}
												onFocus={()=>{ const v=form.getValues('customerName'); if (v && v.length>=1) setCustomerSearch(v) }}
												onBlur={()=>{ setTimeout(()=>{ const name = String(form.getValues('customerName')||'').trim(); if (!customerSelected && name.length>=1) { const list = (customersQuery.data as any[]||[]); if (list.length>0) { const exact = (list as any[]).find((c:any)=> String(c.name||'').toLowerCase()===name.toLowerCase()); const c = exact || list[0]; pickCustomer(c.id, c.name); } else { setNewCustomer((s)=>({ ...s, name })); setCreateCustomerOpen(true); } } }, 120) }}
											/>
											<button type="button" onClick={()=>{ setNewCustomer((s)=>({ ...s, name: form.getValues('customerName') })); setCreateCustomerOpen(true) }} style={{ height:44 }}>Yeni Müşteri</button>
											{hasCustomerMatches && customerNameValue && !customerSelected && (
												<div className="card" style={{ position:'absolute', top:'100%', left:0, right:0, zIndex: 2000, padding: 0, marginTop: 6, background:'#fff', border:'1px solid var(--panel-border, rgba(0,0,0,0.12))', boxShadow:'0 8px 24px rgba(0,0,0,0.12)', maxHeight: 240, overflow: 'auto' }}>
													{(customersQuery.data as any[]).slice(0,12).map((c)=> (
														<div key={c.id} role="button" tabIndex={0} style={{ padding: 10, borderTop: '1px solid var(--panel-border)', cursor: 'pointer' }} onMouseDown={(e)=>{ e.preventDefault(); e.stopPropagation(); pickCustomer(c.id, c.name) }} onClick={(e)=>{ e.preventDefault(); e.stopPropagation(); pickCustomer(c.id, c.name) }}>{c.name}</div>
													))}
												</div>
											)}
										</div>
										<div style={{ marginTop:8 }}>
											<label>Alıcı Etiketi (URN)
												<input value={destUrn} onChange={(e)=>setDestUrn(e.target.value)} placeholder="örn. urn:mail:defaultpk@lazimbana.com" style={{ width:'100%', height:44 }} />
											</label>
										</div>
								{hasCustomerMatches && customerNameValue && !customerSelected && (<div />)}
								{false && (<div className="card" style={{ padding: 12, marginTop: 8, display:'grid', gap: 8, maxWidth: '100%' }}>
									<div style={{ display:'grid', gridTemplateColumns:'160px 1fr', gap:8, alignItems:'center' }}><label>Ad/Unvan</label><input value={newCustomer.name || customerNameValue} onChange={(e)=>setNewCustomer((s)=>({ ...s, name: e.target.value }))} /></div>
									<div style={{ display:'grid', gridTemplateColumns:'160px 1fr 1fr', gap:8, alignItems:'center' }}>
										<label>VKN / TCKN</label>
										<input placeholder="VKN" value={newCustomer.vkn||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, vkn: e.target.value }))} />
										<input placeholder="TCKN" value={newCustomer.tckn||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, tckn: e.target.value }))} />
									</div>
									<label style={{ display:'inline-flex', alignItems:'center', gap:8 }}>
										<input type="checkbox" checked={unknownTaxId} onChange={(e)=>{ setUnknownTaxId(e.target.checked); if (e.target.checked) setNewCustomer((s)=>({ ...s, vkn: '', tckn: '' })) }} /> TCKN/VKN bilmiyorum (11111111111 gönder)
									</label>
									<div style={{ display:'grid', gridTemplateColumns:'160px 1fr', gap:8, alignItems:'center' }}><label>E‑posta</label><input placeholder="email@example.com" value={newCustomer.email||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, email: e.target.value }))} /></div>
									<div style={{ display:'grid', gridTemplateColumns:'160px 1fr', gap:8, alignItems:'center' }}><label>Adres</label><textarea rows={2} onChange={(e)=>setNewCustomer((s)=>({ ...s, address: e.target.value }))} /></div>
									<div style={{ display:'grid', gridTemplateColumns:'160px 1fr 1fr', gap:8, alignItems:'center' }}>
										<label>İl / İlçe</label>
										<select value={newCustomer.city||''} onChange={(e)=>{ const city=e.target.value; setNewCustomer((s)=>({ ...s, city, district:'' })) }}>
											<option value="">İl seçin</option>
											{TR_CITIES.map(c=> <option key={c.name} value={c.name}>{c.name}</option>)}
										</select>
										<select value={newCustomer.district||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, district: e.target.value }))}>
											<option value="">İlçe</option>
											{(TR_CITIES.find(c=>c.name===newCustomer.city)?.districts||[]).map(d=> <option key={d} value={d}>{d}</option>)}
										</select>
									</div>
								</div>)}
								</div>
								<div className="card" style={{ padding: 12 }}>
									{customerSelected && selectedCustomerDetail ? (
										<div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap: 8 }}>
											<div><strong>VKN/TCKN:</strong> {selectedCustomerDetail.vkn || selectedCustomerDetail.tckn || '-'}</div>
											<div><strong>E‑posta:</strong> {selectedCustomerDetail.email || '-'}</div>
											<div><strong>Telefon:</strong> {selectedCustomerDetail.phone || '-'}</div>
											<div><strong>Adres:</strong> {selectedCustomerDetail.address || '-'}</div>
										</div>
									) : (
										<div style={{ color:'var(--muted-700,#555)' }}>Müşteri seçince detaylar burada görünecek.</div>
									)}
								</div>
							</div>
								<Navigation />
							</div>
						)}

						{step === 3 && (
							<div className="card" style={{ padding: 12 }}>
								{/* Başlık satırı */}
								<div style={{ display:'grid', gridTemplateColumns:'minmax(520px,1fr) 110px 120px 140px 120px 140px 72px', gap:8, color:'var(--muted-600,#6b7280)', fontSize:12, letterSpacing:0.4, textTransform:'uppercase' }}>
									<div>Hizmet / Ürün</div>
									<div style={{ textAlign:'center' }}>Miktar</div>
									<div style={{ textAlign:'center' }}>Birim</div>
									<div style={{ textAlign:'center' }}>Br. Fiyat</div>
									<div style={{ textAlign:'center' }}>Vergi</div>
									<div style={{ textAlign:'center' }}>Toplam</div>
									<div></div>
								</div>
								<div style={{ height:8 }} />
								{/* Satırlar */}
								<div style={{ display:'grid', gap:8 }}>
									{fields.map((field, idx) => {
										const item = form.getValues(`items.${idx}`) as Item
										return (
											<div key={field.id} style={{ display:'grid', gridTemplateColumns:'minmax(520px,1fr) 110px 120px 140px 120px 140px 72px', gap:8, alignItems:'center', background:'var(--panel-input,#fff)', boxShadow:'0 1px 2px rgba(0,0,0,0.06)', padding:'8px 8px', borderRadius:8 }}>
												{/* Ürün arama alanı */}
												<div style={{ position:'relative' }}>
													<input style={{ height:44, padding:'10px 12px', width:'100%', background:'#fff', color:'#111', border:'1px solid var(--panel-border, rgba(0,0,0,0.12))' }} value={form.watch(`items.${idx}.name`)} onFocus={()=>{ setActiveProductRow(idx); setProductSearch(form.getValues(`items.${idx}.name`) || '') }} onChange={(e)=>{ form.setValue(`items.${idx}.name` as any, e.target.value, { shouldDirty: true, shouldTouch: true } as any); setActiveProductRow(idx); setProductSearch(e.target.value) }} placeholder="Hizmet / ürün adını yazın ve aratın" />
													{activeProductRow === idx && productsQuery.data && (productsQuery.data as any[]).length > 0 && (
														<div className="card" style={{ position:'absolute', top:'100%', left:0, zIndex:10, width:'100%', padding:0, marginTop:4, background:'#fff', border:'1px solid var(--panel-border, rgba(0,0,0,0.12))', boxShadow:'0 8px 24px rgba(0,0,0,0.12)' }}>{(productsQuery.data as any[]).slice(0,6).map((p:any)=>(<div key={p.id} style={{ padding:10, borderTop:'1px solid var(--panel-border, rgba(0,0,0,0.08))', cursor:'pointer' }} onClick={()=>applyProductToRow(idx,p)}>{p.name} {p.sku?`(${p.sku})`:''}</div>))}</div>
													)}
												</div>

											{/* Miktar */}
											<div style={{ textAlign:'center' }}>
												<input type="number" step="any" style={{ height:44, width:'100%', textAlign:'right', padding:'0 10px' }} value={form.watch(`items.${idx}.quantity`) as any} onChange={(e)=>update(idx, { ...(item as Item), quantity: Number(e.target.value) })} />
											</div>

											{/* Birim */}
											<div>
												<select style={{ height:44, width:'100%' }} value={form.watch(`items.${idx}.unit`) as any} onChange={(e)=>update(idx, { ...(item as Item), unit: e.target.value })}>
													<option>Adet</option>
													<option>Kg</option>
													<option>Lt</option>
													<option>Metre</option>
												</select>
											</div>

											{/* Birim Fiyat */}
											<div style={{ position:'relative' }}>
												<input type="number" step="any" style={{ height:44, width:'100%', textAlign:'right', padding:'0 26px 0 10px' }} value={(form.watch(`items.${idx}.unitPrice.amount`) as any)} onChange={(e)=>{ const v = Number(e.target.value); update(idx, { ...(item as Item), unitPrice: { ...(form.getValues(`items.${idx}.unitPrice`) as any), amount: v } }); }} />
												<span style={{ position:'absolute', right:8, top:'50%', transform:'translateY(-50%)', color:'#6b7280' }}>₺</span>
											</div>

											{/* Vergi (KDV) */}
											<div style={{ display:'grid', gridTemplateColumns:'52px 1fr', gap:6, alignItems:'center' }}>
												<div style={{ height:44, display:'flex', alignItems:'center', justifyContent:'center', border:'1px solid var(--panel-border, rgba(0,0,0,0.08))', borderRadius:8, color:'#6b7280' }}>KDV</div>
												<select style={{ height:44 }} value={String(form.watch(`items.${idx}.vatRate`) as any)} onChange={(e)=>{ const vr = Number(e.target.value); const it = form.getValues(`items.${idx}`) as any; const total = Number((it as any)?.metadata?.lineTotal || 0); if (total>0 && it.quantity>0) { const unitNet = total / it.quantity / (1 + vr/100); update(idx, { ...(it as Item), vatRate: vr, unitPrice: { ...(it.unitPrice||{}), amount: Number(unitNet.toFixed(2)) }, metadata: { ...((it.metadata)||{}), lineTotal: total } }); } else { update(idx, { ...(it as Item), vatRate: vr }); } }}>
													<option value="0">%0</option>
													<option value="1">%1</option>
													<option value="10">%10</option>
													<option value="20">%20</option>
												</select>
											</div>

											{/* Toplam */}
											<div>
												<input
													type="text"
													inputMode="decimal"
													style={{ height:44, width:'100%', textAlign:'right', padding:'0 10px' }}
													value={(lineTotalInputs[idx] ?? String(((form.getValues(`items.${idx}`) as any)?.metadata?.lineTotal ?? '')))}
													onChange={(e)=> setLineTotalInputs((s)=>({ ...s, [idx]: e.target.value }))}
													onBlur={()=> commitLineTotalInput(idx)}
													onKeyDown={(e)=>{ if (e.key==='Enter') { e.preventDefault(); commitLineTotalInput(idx) } }}
													placeholder="0,00"
												/>
											</div>

											{/* Aksiyonlar */}
											<div style={{ display:'flex', gap:8, justifyContent:'center' }}>
												<button type="button" title="Yeni satır" onClick={()=> append({ name: '', quantity: 1, unit: 'Adet', vatRate: 20, unitPrice: { amount: 0, currency: currency as any }, metadata: { lineTotal: 0 } } as any)} style={{ width:32, height:32 }}>+</button>
												<button type="button" title="Sil" onClick={()=>remove(idx)} className="btn-danger" style={{ width:32, height:32 }}>×</button>
											</div>
										</div>
										)
									})}
								</div>

								{/* Toplam Kar (placeholder) */}
								<div style={{ marginTop: 12, color:'var(--muted-700,#555)' }}>Toplam Kâr: —</div>

								{/* Yeni satır ekle */}
								<div style={{ marginTop: 8 }}>
									<button type="button" onClick={() => append({ name: '', quantity: 1, unit: 'Adet', vatRate: 20, unitPrice: { amount: 0, currency: currency as any }, metadata: { lineTotal: 0 } } as any)}>+ Yeni Satır Ekle</button>
								</div>

								<Navigation />
							</div>
						)}

						{/* Step 3 için toplamlar kartı sayfanın altında */}
						{step === 3 && (
							<div className="card" style={{ padding: 12 }}>
								<strong>Toplamlar</strong>
								<div style={{ display:'grid', gap: 8, marginTop: 8 }}>
									<div style={{ display:'flex', justifyContent:'space-between' }}><span>Toplam KDV</span><span>{totals.vatTotal.toFixed(2)} {currency}</span></div>
									<div style={{ display:'flex', justifyContent:'space-between', fontWeight:600 }}><span>Genel Toplam</span><span>{totals.grandTotal.toFixed(2)} {currency}</span></div>
									<div style={{ display:'flex', justifyContent:'space-between' }}><span>Ara Toplam</span><span>{totals.subtotal.toFixed(2)} {currency}</span></div>
								</div>
							</div>
						)}

						{step === 4 && (
							<div className="card" style={{ padding:12 }}>
								<div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap: 12 }}>
									<div className="card" style={{ padding:12 }}>
										<strong>Ödeme</strong>
										<div style={{ display:'grid', gap:8, marginTop:8 }}>
											<label>Yöntem<select value={paymentMethod} onChange={(e)=>setPaymentMethod(e.target.value as any)}><option value="NAKIT">Nakit</option><option value="KREDI_KARTI">Kredi Kartı</option><option value="HAVALE">Havale/EFT</option><option value="PLATFORM">Pazaryeri/Platform</option></select></label>
											{paymentMethod==='PLATFORM' && (<label>Platform<input value={paymentPlatform} onChange={(e)=>setPaymentPlatform(e.target.value)} placeholder="örn. Trendyol" /></label>)}
											<label>POS / Sanal POS<input value={paymentPos} onChange={(e)=>setPaymentPos(e.target.value)} placeholder="opsiyonel" /></label>
											{paymentMethod==='HAVALE' && (<label>IBAN<input value={iban} onChange={(e)=>setIban(e.target.value)} placeholder="TR.." /></label>)}
											<label>Not<textarea value={note} onChange={(e)=>setNote(e.target.value)} /></label>
                                            <div style={{marginTop:8}}>
                                                <button type="button" onClick={()=>setInternetSaleOpen(true)}>İnternet Üzerinden Satış</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="card" style={{ padding:12 }}>
                                        <strong>Kargo (opsiyonel)</strong>
                                        <div style={{ display:'grid', gap:8, marginTop:8 }}>
                                            <label style={{ display:'inline-flex', alignItems:'center', gap:8 }}><input type="checkbox" checked={cargoEnabled} onChange={(e)=>setCargoEnabled(e.target.checked)} /> Kargo ile gönderim</label>
                                            {cargoEnabled && (<>
                                                <label>Kargo Firması<input value={cargoCompany} onChange={(e)=>setCargoCompany(e.target.value)} /></label>
                                                <label>Kargo VKN/TCKN<input value={cargoVkn} onChange={(e)=>setCargoVkn(e.target.value)} /></label>
                                                <label>Kargo Tarihi<input type="date" value={cargoDate} onChange={(e)=>setCargoDate(e.target.value)} /></label>
                                                <label>Takip No<input value={cargoTracking} onChange={(e)=>setCargoTracking(e.target.value)} /></label>
                                            </>)}
                                        </div>
                                    </div>
                                </div>
                                <Navigation />
                            </div>
                        )}

                        {step === 5 && (
                            <div className="card" style={{ padding: 12 }}>
                                <div style={{ display:'grid', gap: 8 }}>
                                    <div><strong>Müşteri:</strong> {form.watch('customerName') || (customerSelected?.name ?? '-')}</div>
                                    <div><strong>Kalem sayısı:</strong> {fields.length}</div>
                                    <div><strong>Genel Toplam:</strong> {totals.grandTotal.toFixed(2)} {currency}</div>
                                </div>
                                <Navigation />
                            </div>
                        )}
                    </div>

                    {step !== 3 && (
                    <div>
                        <div className="card" style={{ padding: 12 }}>
                            <strong>Toplamlar</strong>
                            <div style={{ display:'grid', gap: 8, marginTop: 8 }}>
                                <div style={{ display:'flex', justifyContent:'space-between' }}><span>Toplam KDV</span><span>{totals.vatTotal.toFixed(2)} {currency}</span></div>
                                <div style={{ display:'flex', justifyContent:'space-between', fontWeight:600 }}><span>Genel Toplam</span><span>{totals.grandTotal.toFixed(2)} {currency}</span></div>
                                <div style={{ display:'flex', justifyContent:'space-between' }}><span>Ara Toplam</span><span>{totals.subtotal.toFixed(2)} {currency}</span></div>
                            </div>
                            <div style={{ marginTop: 12 }}>
                                {step === steps.length ? (
                                    <button type="submit" disabled={form.formState.isSubmitting}>{form.formState.isSubmitting ? 'Gönderiliyor…' : 'Kaydet ve Kuyruğa Al'}</button>
                                ) : (
                                    <button type="button" onClick={()=>setStep((s)=>Math.min(steps.length,s+1))}>İleri</button>
                                )}
                            </div>
                        </div>
                    </div>
                    )}
                </div>
            </form>
            {/* İnternet Üzerinden Satış modal */}
            {internetSaleOpen && (
                <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,0.45)', display:'flex', alignItems:'center', justifyContent:'center', zIndex: 60 }} onClick={()=>setInternetSaleOpen(false)}>
                    <div className="card" role="dialog" aria-modal="true" style={{ width: 720, maxWidth:'92vw', padding:16 }} onClick={(e)=>e.stopPropagation()}>
                        <div style={{ fontSize:18, fontWeight:700, marginBottom:12 }}>İnternet Üzerinden Satış</div>
                        <div style={{ display:'grid', gap:12 }}>
                            <div>
                                <label style={{ display:'flex', alignItems:'center', gap:16 }}>
                                    <span>Faturada satış işlemi internet üzerinden gerçekleşen ürün var mı?</span>
                                </label>
                                <div style={{ display:'flex', gap:16, marginTop:8 }}>
                                    <label style={{ display:'inline-flex', alignItems:'center', gap:8 }}><input type="radio" name="inet_exist" checked={!internetExists} onChange={()=>setInternetExists(false)} /> Hayır, yok</label>
                                    <label style={{ display:'inline-flex', alignItems:'center', gap:8 }}><input type="radio" name="inet_exist" checked={internetExists} onChange={()=>setInternetExists(true)} /> Evet, var</label>
                                </div>
                            </div>
                            {internetExists && (
                                <>
                                    <label>Web Adresi<input autoFocus placeholder="www.ornek.com" value={internetWeb} onChange={(e)=>setInternetWeb(e.target.value)} /></label>
                                    <label>Ödeme Şekli<select value={internetPaymentType} onChange={(e)=>setInternetPaymentType(e.target.value)}>
                                        <option value="">Seçin</option>
                                        <option value="KREDIKARTI/BANKAKARTI">Kredi Kartı / Banka Kartı</option>
                                        <option value="EFT/HAVALE">Havale / EFT</option>
                                        <option value="NAKIT">Nakit</option>
                                    </select></label>
                                    <label>Ödeme Platformu
                                        <select value={internetPlatform} onChange={(e)=>{ setInternetPlatform(e.target.value); if (!internetWeb && e.target.value) { const p = e.target.value.toLowerCase(); if (p.includes('trendyol')) setInternetWeb('trendyol.com'); if (p.includes('hepsiburada')) setInternetWeb('hepsiburada.com'); if (p.includes('amazon')) setInternetWeb('amazon.com.tr'); }} }>
                                            <option value="">Seçin</option>
                                            {internetPlatformPresets.map(p=> <option key={p} value={p}>{p}</option>)}
                                            <option value="DİĞER">Diğer</option>
                                        </select>
                                    </label>
                                    {internetPlatform==='DİĞER' && (<label>Platform Adı<input placeholder="İyzico, PayU, Sanal POS, ..." value={internetPlatform==='DİĞER' ? '' : internetPlatform} onChange={(e)=>setInternetPlatform(e.target.value)} /></label>)}
                                    <label>Ödeme Tarihi<input type="date" value={internetDate} onChange={(e)=>setInternetDate(e.target.value)} /></label>
                                </>
                            )}
                            <div style={{ display:'flex', justifyContent:'flex-end', gap:8 }}>
                                <button className="btn-secondary" onClick={()=>setInternetSaleOpen(false)}>Vazgeç</button>
                                <button onClick={()=>{ 
                                    // basic validation
                                    if (internetExists) {
                                        const urlOk = !internetWeb || /^(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/.test(internetWeb.replace(/^https?:\/\//,''))
                                        if (!urlOk) { alert('Geçerli bir web adresi girin (örn. www.ornek.com)'); return }
                                        if (!internetPaymentType) { alert('Ödeme şeklini seçin'); return }
                                        if (!internetDate) { alert('Ödeme tarihini seçin'); return }
                                    }
                                    saveInternetSaleToStorage();
                                    setInternetSaleOpen(false); 
                                }}>Devam Et</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            {/* Ürün seçici modal */}
            {pickerIdx !== null && (
                <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,0.35)', display:'flex', alignItems:'center', justifyContent:'center', zIndex: 50 }} onClick={()=>setPickerIdx(null)}>
                    <div className="card" style={{ width: 720, maxHeight:'80vh', overflow:'auto', padding: 16 }} onClick={(e)=>e.stopPropagation()}>
                        <div style={{ display:'flex', gap:8, marginBottom: 12 }}>
                            <input placeholder="Ürün ara (ad/sku)" value={pickerSearch} onChange={(e)=>setPickerSearch(e.target.value)} style={{ flex:1 }} />
                            <button type="button" onClick={()=>setPickerIdx(null)}>Kapat</button>
                        </div>
                        <div style={{ display:'grid', gap: 6 }}>
                            {(pickerProductsQuery.data as any[] || []).map((p:any) => (
                                <div key={p.id} style={{ display:'grid', gridTemplateColumns:'1fr 120px 80px 120px', gap: 8, alignItems:'center', padding:'8px 6px', border:'1px solid var(--panel-border, rgba(0,0,0,0.08))', borderRadius: 8 }}>
                                    <div>{p.name} {p.sku ? <span style={{ color:'#6b7280' }}>({p.sku})</span> : null}</div>
                                    <div style={{ textAlign:'right' }}>{p.unit_price != null ? Number(p.unit_price).toFixed(2) : ''} {p.currency || ''}</div>
                                    <div style={{ textAlign:'center' }}>{p.vat_rate != null ? p.vat_rate : '-'}</div>
                                    <div style={{ textAlign:'right' }}>
                                        <button type="button" onClick={()=>{ if (pickerIdx !== null) { applyProductToRow(pickerIdx, p); setPickerIdx(null) } }}>Seç</button>
                                    </div>
                                </div>
                            ))}
                            {pickerProductsQuery.isLoading && (<div>Yükleniyor…</div>)}
                        </div>
                    </div>
                </div>
            )}

            {/* Yeni Müşteri modal */}
            {createCustomerOpen && (
                <div style={{ position:'fixed', inset:0, background:'rgba(0,0,0,0.45)', display:'flex', alignItems:'center', justifyContent:'center', zIndex: 60 }} onClick={()=>setCreateCustomerOpen(false)}>
                    <div className="card" style={{ width: 640, maxWidth:'92vw', maxHeight:'80vh', overflow:'auto', padding:16 }} onClick={(e)=>e.stopPropagation()}>
                        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center' }}>
                            <strong>Yeni Müşteri</strong>
                            <button className="btn-secondary" onClick={()=>setCreateCustomerOpen(false)}>Kapat</button>
                        </div>
                        <div style={{ marginTop:12, display:'grid', gap:8 }}>
                            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                                <div>
                                    <Label htmlFor="customer-name">Ad</Label>
                                    <Input id="customer-name" value={newCustomer.name || ''} onChange={(e)=>setNewCustomer((s)=>({ ...s, name: e.target.value }))} placeholder="Müşteri adı" />
                                </div>
                                <div>
                                    <Label htmlFor="customer-surname">Soyad</Label>
                                    <Input id="customer-surname" value={newCustomer.surname || ''} onChange={(e)=>setNewCustomer((s)=>({ ...s, surname: e.target.value }))} placeholder="Müşteri soyadı" />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label htmlFor="customer-vkn">VKN</Label>
                                    <Input id="customer-vkn" value={newCustomer.vkn||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, vkn: e.target.value }))} />
                                </div>
                                <div>
                                    <Label htmlFor="customer-tckn">TCKN</Label>
                                    <Input id="customer-tckn" value={newCustomer.tckn||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, tckn: e.target.value }))} />
                                </div>
                            </div>
                            <label style={{ display:'inline-flex', alignItems:'center', gap:8 }}><input type="checkbox" checked={unknownTaxId} onChange={(e)=>{ setUnknownTaxId(e.target.checked); if (e.target.checked) setNewCustomer((s)=>({ ...s, vkn: '', tckn: '' })) }} /> TCKN/VKN bilmiyorum (11111111111 gönder)</label>
                            <div>
                                <Label htmlFor="customer-email">E-posta</Label>
                                <Input id="customer-email" value={newCustomer.email||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, email: e.target.value }))} />
                            </div>
                            <div>
                                <Label htmlFor="customer-address">Adres</Label>
                                <Input id="customer-address" value={newCustomer.address||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, address: e.target.value }))} />
                            </div>
                            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                                <div>
                                    <Label htmlFor="customer-city">İl</Label>
                                    <select id="customer-city" value={newCustomer.city||''} onChange={(e)=>{ const city=e.target.value; setNewCustomer((s)=>({ ...s, city, district:'' })) }} style={{ height:44, width:'100%' }}>
                                        <option value="">İl seçin</option>
                                        {TR_CITIES.map(c=> <option key={c.name} value={c.name}>{c.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="customer-district">İlçe</Label>
                                    <select id="customer-district" value={newCustomer.district||''} onChange={(e)=>setNewCustomer((s)=>({ ...s, district: e.target.value }))} style={{ height:44, width:'100%' }}>
                                        <option value="">İlçe</option>
                                        {(TR_CITIES.find(c=>c.name===newCustomer.city)?.districts||[]).map(d=> <option key={d} value={d}>{d}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div style={{ display:'flex', justifyContent:'flex-end', gap:8, marginTop:8 }}>
                                <button className="btn-secondary" onClick={()=>setCreateCustomerOpen(false)}>Vazgeç</button>
                                <button onClick={async ()=>{ try {
                                    const body: any = {
                                        name: newCustomer.name || form.getValues('customerName'),
                                        surname: newCustomer.surname || '',
                                        email: newCustomer.email || undefined,
                                        street_address: newCustomer.address || undefined,
                                        city: newCustomer.city || undefined,
                                        district: newCustomer.district || undefined,
                                    };
                                    if (unknownTaxId && !newCustomer.vkn && !newCustomer.tckn) {
                                        body.tckn_vkn = '11111111111';
                                    } else {
                                        body.tckn_vkn = newCustomer.vkn || newCustomer.tckn || undefined;
                                    }
                                    const res = await api.post('/customers', body);
                                    const created = res.data;
                                    await pickCustomer(created.id, created.name);
                                    setCreateCustomerOpen(false);
                                } catch (e) {
                                    console.error(e);
                                    alert('Müşteri oluşturulamadı. Lütfen alanları kontrol edin.');
                                } }}>Oluştur ve Seç</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    )
}


