import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '../lib/api'
import Papa from 'papaparse'
import { useRef, useState, useEffect } from 'react'
import PageHeader from '../components/ui/PageHeader'
import Skeleton from '../components/ui/Skeleton'
import DataTable, { type Column } from '../components/ui/DataTable'
import { useSearchParams } from 'react-router-dom'
function exportXlsx(rows: any[]) {
  import('xlsx').then((XLSX) => {
    const ws = XLSX.utils.json_to_sheet(rows)
    const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Customers'); XLSX.writeFile(wb, 'customers.xlsx')
  })
}

async function fetchCustomers(params?: Record<string,string>) {
  const res = await api.get('/customers', { params })
  return res.data?.data ?? res.data ?? []
}

export default function CustomersPage() {
  const qc = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()
  const [q, setQ] = useState<string>(() => searchParams.get('q') || '')
  const [qDebounced, setQDebounced] = useState<string>(q)
  const searchRef = useRef<HTMLInputElement>(null)
  const [sortKey, setSortKey] = useState<'id'|'name'|'email'>(() => (searchParams.get('sort') as any) || 'id')
  const [sortDir, setSortDir] = useState<'asc'|'desc'>(() => (searchParams.get('order') as any) || 'desc')
  const { data, isLoading, isError } = useQuery({ queryKey: ['customers', qDebounced, sortKey, sortDir], queryFn: () => fetchCustomers({ ...(qDebounced?{q:qDebounced}:{}), sort: sortKey, order: sortDir }) })
  const [name, setName] = useState('')
  const [vkn, setVkn] = useState('')
  const [email, setEmail] = useState('')
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

  const create = useMutation({
    mutationFn: async () => {
      const payload: any = { name }
      if (vkn) payload.vkn = vkn
      if (email) payload.email = email
      await api.post('/customers', payload)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['customers'] }),
  })

  function importCsv(file: File) {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: async (results: Papa.ParseResult<Record<string, any>>) => {
        for (const row of results.data as any[]) {
          const payload: any = { name: row.name || row.Name || row.Musteri || '' }
          if (row.vkn) payload.vkn = row.vkn
          if (row.tckn) payload.tckn = row.tckn
          if (row.email) payload.email = row.email
          await api.post('/customers', payload)
        }
        qc.invalidateQueries({ queryKey: ['customers'] })
      },
    })
  }

  function exportCsvCurrent() {
    const items = (data as any[]) || []
    const header = ['id','name','vkn_or_tckn','email']
    const lines = ['\uFEFF' + header.join(',')]
    for (const c of items) {
      const cols = [c.id, `"${(c.name||'').replace(/\"/g,'\"\"')}"`, c.vkn || c.tckn || '', c.email || '']
      lines.push(cols.join(','))
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'customers.csv'
    a.click()
    URL.revokeObjectURL(url)
  }
  function exportXlsxCurrent() {
    const items = (data as any[]) || []
    const ws = XLSX.utils.json_to_sheet(items.map((c) => ({
      ID: c.id,
      AdUnvan: c.name,
      VKNorTCKN: c.vkn || c.tckn || '',
      Eposta: c.email || '',
    })))
    const wb = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(wb, ws, 'Musteriler')
    XLSX.writeFile(wb, 'customers.xlsx')
  }

  function toggleSort(key: 'id'|'name'|'email') {
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
      <PageHeader title="Müşteriler" subtitle="Kayıt ekleme ve listeleme" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Müşteriler' }]} />
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 16 }}>
        <input ref={searchRef} placeholder="Ara (ad/vkn/email)" value={q} onChange={(e) => setQ(e.target.value)} />
        <input placeholder="Ad/Unvan" value={name} onChange={(e) => setName(e.target.value)} />
        <input placeholder="VKN" value={vkn} onChange={(e) => setVkn(e.target.value)} />
        <input placeholder="E-posta" value={email} onChange={(e) => setEmail(e.target.value)} />
        <button onClick={() => create.mutate()} disabled={!name}>Ekle</button>
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
            { key: 'name', label: 'Ad/Unvan', sortable: true },
            { key: 'vkn', label: 'VKN/TCKN', render: (r: any) => {
              const v = r.vkn || r.tckn || ''
              return v ? (<span style={{ padding: '2px 8px', borderRadius: 999, background: 'rgba(2,6,23,.06)', border: '1px solid rgba(2,6,23,.12)', fontFamily: 'monospace' }}>{v}</span>) : '-'
            } },
            { key: 'email', label: 'E-posta', sortable: true, render: (r: any) => r.email ? (<span style={{ fontFamily: 'monospace' }}>{r.email}</span>) : '-' },
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
          storageKey="dt/customers"
          virtualized
          rowHeight={40}
        />
      )}
    </div>
  )
}


