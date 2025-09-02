import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useParams, useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import Skeleton from '../components/ui/Skeleton'

async function fetchProduct(id: string) {
  const res = await api.get(`/products/${id}`)
  return res.data
}

export default function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { data, isLoading, isError } = useQuery({ queryKey: ['product', id], queryFn: () => fetchProduct(id as string), enabled: !!id })

  const del = useMutation({
    mutationFn: async () => { await api.delete(`/products/${id}`) },
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['products'] }); navigate('/app/products', { replace: true }) },
  })

  return (
    <div style={{ padding: 24 }}>
      <PageHeader title="Ürün Detayı" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Ürünler', href: '/app/products' }, { label: `#${id}` }]} />

      {isLoading && (
        <div style={{ display: 'grid', gap: 8 }}>
          <Skeleton height={36} />
          <Skeleton height={36} />
          <Skeleton height={36} />
        </div>
      )}
      {isError && <div className="card" style={{ padding: 16 }}>Kayıt yüklenemedi.</div>}

      {!isLoading && !isError && data && (
        <div className="card" style={{ padding: 0, maxWidth: 980, overflow: 'hidden' }}>
          <div style={{ padding: 16, borderBottom: '1px solid var(--panel-border, rgba(0,0,0,0.08))', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <div style={{ fontWeight: 600 }}>{data.name} <span style={{ color: 'var(--muted-700,#555)', fontWeight: 400 }}>({data.sku})</span></div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button onClick={() => navigate('/app/products')}>Geri</button>
              <a href={`/app/products/${id}/edit`}><button>Düzenle</button></a>
              <button onClick={() => !del.isPending && del.mutate()} style={{ background: 'var(--danger, #dc2626)', color: '#fff' }}>{del.isPending ? 'Siliniyor…' : 'Sil'}</button>
            </div>
          </div>

          <div style={{ display: 'grid', gap: 0 }}>
            <Section title="GENEL BİLGİLER" />
            <Row label="Ad" value={data.name} />
            <Row label="Ürün / Stok Kodu (SKU)" value={data.sku} />
            <Row label="Alış / Satış Birimi" value={data.unit || '-'} />

            <Divider />

            <Section title="VERGİ VE FİYAT" />
            <Row label="KDV (%)" value={data.vat_rate ?? '-'} />
            <Row label="Vergiler Hariç Satış Fiyatı" value={data.unit_price != null ? `${Number(data.unit_price)} ${data.currency || ''}` : '-'} />

            <Divider />

            <Section title="EK BİLGİLER" />
            <Row label="Barkod" value={data?.metadata?.barcode || '-'} />
            <Row label="Kategori" value={data?.metadata?.category || '-'} />
            <Row label="Tevkifat (%)" value={data?.metadata?.withholding_rate ?? '-'} />
            <Row label="KDV İstisna Kodu" value={data?.metadata?.vat_exemption_code || '-'} />
            <Row label="GTİP Kodu" value={data?.metadata?.gtip_code || '-'} />
            <Row label="Alış Fiyatı" value={data?.metadata?.purchase_price != null ? `${data?.metadata?.purchase_price} ${data.currency || ''}` : '-'} />
            <Row label="Stok Takibi" value={data?.metadata?.stock_tracking ? 'Evet' : 'Hayır'} />
            {data?.metadata?.stock_tracking && (
              <>
                <Row label="Başlangıç Stok" value={data?.metadata?.initial_stock ?? '-'} />
                <Row label="Kritik Stok" value={data?.metadata?.critical_stock ?? '-'} />
              </>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

function Section({ title }: { title: string }) {
  return <div style={{ padding: '12px 16px', fontSize: 12, fontWeight: 600, color: 'var(--muted-700, #555)', letterSpacing: 0.4 }}>{title}</div>
}

function Divider() {
  return <div style={{ height: 1, background: 'var(--panel-border, rgba(0,0,0,0.08))', margin: '8px 0' }} />
}

function Row({ label, value }: { label: string; value: any }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '240px 1fr', columnGap: 16, alignItems: 'center', padding: '10px 16px', borderTop: '1px solid var(--panel-border, rgba(0,0,0,0.08))' }}>
      <div>{label}</div>
      <div>{String(value)}</div>
    </div>
  )
}


