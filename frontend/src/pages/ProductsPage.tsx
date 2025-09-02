import { useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import Papa from 'papaparse'
import { useRef, useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import PageHeader from '../components/ui/PageHeader'
import Skeleton from '../components/ui/Skeleton'
import { useSearchParams } from 'react-router-dom'
import DataTable, { type Column } from '../components/ui/DataTable'
function exportXlsx(rows: any[]) {
  import('xlsx').then((XLSX) => {
    const ws = XLSX.utils.json_to_sheet(rows)
    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Products'); XLSX.writeFile(wb, 'products.xlsx')
  })
}

async function fetchProducts(params?: Record<string,string>) {
  const res = await api.get('/products', { params })
  return res.data?.data ?? res.data ?? []
}

export default function ProductsPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  const [q, setQ] = useState<string>(() => searchParams.get('q') || '')
  const [qDebounced, setQDebounced] = useState<string>(q)
  const searchRef = useRef<HTMLInputElement>(null)
  const [sortKey, setSortKey] = useState<'id'|'name'|'sku'>(() => (searchParams.get('sort') as any) || 'id')
  const [sortDir, setSortDir] = useState<'asc'|'desc'>(() => (searchParams.get('order') as any) || 'desc')
  const { data, isLoading, isError } = useQuery({ queryKey: ['products', qDebounced, sortKey, sortDir], queryFn: () => fetchProducts({ ...(qDebounced?{q:qDebounced}:{}), sort: sortKey, order: sortDir }) })
  
  const fileRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    const t = setTimeout(() => setQDebounced(q), 300)
    return () => clearTimeout(t)
  }, [q])
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (e.key === '/') { e.preventDefault(); searchRef.current?.focus() }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); searchRef.current?.focus() }
      if (e.key === 'Escape' && document.activeElement === searchRef.current) (document.activeElement as HTMLElement).blur()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [])
  
  function importCsv(file: File) {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: async (results: Papa.ParseResult<Record<string, any>>) => {
        for (const row of results.data as any[]) {
          const payload: any = { name: row.name || row.Name || '' }
          if (row.sku) payload.sku = row.sku
          const amount = Number(row.price || row.amount || 0)
          if (amount) payload.unitPrice = { amount, currency: 'TRY' }
          await api.post('/products', payload)
        }
        qc.invalidateQueries({ queryKey: ['products'] })
      },
    })
  }

  function exportCsvCurrent() {
    const items = (data as any[]) || []
    const header = ['id','name','sku']
    const lines = ['\uFEFF' + header.join(',')]
    for (const p of items) {
      const cols = [p.id, `"${(p.name||'').replace(/\"/g,'\"\"')}"`, p.sku || '']
      lines.push(cols.join(','))
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'products.csv'
    a.click()
    URL.revokeObjectURL(url)
  }
  function exportXlsxCurrent() {
    const items = (data as any[]) || []
    const ws = XLSX.utils.json_to_sheet(items.map((p) => ({
      ID: p.id,
      Ad: p.name,
      SKU: p.sku || '',
    })))
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, 'Urunler')
    XLSX.writeFile(wb, 'products.xlsx')
  }

  function toggleSort(key: 'id'|'name'|'sku') {
    if (sortKey === key) setSortDir(sortDir === 'asc' ? 'desc' : 'asc')
    else { setSortKey(key); setSortDir('desc') }
  }

  // URL sync
  useEffect(() => {
    const sp = new URLSearchParams()
    if (q) sp.set('q', q)
    if (sortKey) sp.set('sort', sortKey)
    if (sortDir) sp.set('order', sortDir)
    setSearchParams(sp, { replace: true })
  }, [q, sortKey, sortDir, setSearchParams])

  return (
    <div style={{ padding: 24 }}>
      <PageHeader title="Ürünler" subtitle="Kayıt ekleme ve listeleme" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Ürünler' }]} />
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 16 }}>
        <input ref={searchRef} placeholder="Ara (ad/sku)" value={q} onChange={(e) => setQ(e.target.value)} style={{ height:36, padding:'0 10px', background:'#fff', color:'#111', border:'1px solid var(--panel-border, rgba(0,0,0,0.12))' }} />
        <button onClick={() => navigate('/app/products/new')}>Yeni Ürün</button>
        <input type="file" accept=".csv" ref={fileRef} onChange={(e) => e.target.files && importCsv(e.target.files[0])} />
        <div style={{ marginLeft: 'auto' }}>
          <button onClick={exportCsvCurrent}>CSV İndir</button>
          <button onClick={exportXlsxCurrent} style={{ marginLeft: 8 }}>XLSX İndir</button>
        </div>
      </div>
      {isLoading && (
        <div style={{ display: 'grid', gap: 8 }}>
          <Skeleton height={36} />
          <Skeleton height={36} />
          <Skeleton height={36} />
        </div>
      )}
      {isError && <div>Hata oluştu</div>}
      {!isLoading && !isError && (data?.length ?? 0) === 0 && (
        <div className="card" style={{ padding: 24 }}>Kayıt bulunamadı</div>
      )}
      {!isLoading && !isError && (data?.length ?? 0) > 0 && (
        <DataTable
          columns={[
            { key: 'id', label: 'ID', sortable: true },
            { key: 'name', label: 'Ad', sortable: true, render: (r: any) => (<a href={`/app/products/${r.id}`} style={{ color:'#0ea5e9' }}>{r.name}</a>) },
            { key: 'sku', label: 'SKU', sortable: true, render: (r: any) => r.sku ? (<span style={{ padding:'2px 8px', borderRadius: 999, background:'rgba(2,6,23,.06)', border:'1px solid rgba(2,6,23,.12)', fontFamily:'monospace' }}>{r.sku}</span>) : '-' },
            { key: 'unit_price', label: 'Satış Fiyatı', render: (r: any) => (<span style={{ color:'#111', fontFamily:'monospace' }}>{r.unit_price != null ? `${Number(r.unit_price)} ${r.currency || ''}` : '-'}</span>) },
            { key: 'stock', label: 'Stok', render: (r: any) => (r?.metadata?.initial_stock != null ? r.metadata.initial_stock : '-') },
            { key: 'actions', label: '', render: (r: any) => (<a href={`/app/products/${r.id}`}><button>Detay</button></a>) },
          ] as Array<Column<any>>}
          rows={(data as any[]) || []}
          stickyHeader
          maxHeight={'calc(100vh - 300px)'}
          sortKey={sortKey}
          sortDir={sortDir}
          onSortToggle={(key) => toggleSort(key as any)}
          compact
          getRowId={(r: any) => r.id}
          showColumnSettings
          storageKey="dt/products"
          virtualized
          rowHeight={40}
        />
      )}
    </div>
  )
}


