export function InvoicePreview({ data }: { data: any }) {
  const items: any[] = Array.isArray(data?.items) ? data.items : []
  const grand = data?.totals?.grandTotal
  return (
    <div style={{ fontFamily: 'ui-sans-serif, system-ui', background: 'white', color: '#0f172a', border: '1px solid #e2e8f0', borderRadius: 12, padding: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
        <div>
          <div style={{ fontWeight: 700, fontSize: 18 }}>efatura.ai</div>
          <div style={{ color: '#64748b' }}>FATURA</div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div><strong>No:</strong> {data?.id ?? '-'}</div>
          <div><strong>Tarih:</strong> {data?.issueDate ?? '-'}</div>
        </div>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
        <div>
          <div style={{ color: '#64748b', fontSize: 12 }}>MÜŞTERİ</div>
          <div style={{ fontWeight: 600 }}>{data?.customer?.name ?? '-'}</div>
          <div style={{ color: '#64748b' }}>{data?.customer?.email ?? ''}</div>
        </div>
        <div>
          <div style={{ color: '#64748b', fontSize: 12 }}>SATAN</div>
          <div style={{ fontWeight: 600 }}>Şirket Adı</div>
          <div style={{ color: '#64748b' }}>Adres satırı</div>
        </div>
      </div>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr>
            <th style={{ textAlign: 'left', borderBottom: '1px solid #e2e8f0', padding: '8px 4px' }}>Ürün</th>
            <th style={{ textAlign: 'right', borderBottom: '1px solid #e2e8f0', padding: '8px 4px' }}>Miktar</th>
            <th style={{ textAlign: 'right', borderBottom: '1px solid #e2e8f0', padding: '8px 4px' }}>Birim Fiyat</th>
            <th style={{ textAlign: 'right', borderBottom: '1px solid #e2e8f0', padding: '8px 4px' }}>Tutar</th>
          </tr>
        </thead>
        <tbody>
          {items.length === 0 && (
            <tr><td colSpan={4} style={{ padding: '8px 4px', color: '#64748b' }}>Kalem bulunamadı</td></tr>
          )}
          {items.map((it: any, i: number) => {
            const amount = Number(it?.unitPrice?.amount || 0) * Number(it?.quantity || 0)
            return (
              <tr key={i}>
                <td style={{ padding: '8px 4px' }}>{it?.name ?? '-'}</td>
                <td style={{ padding: '8px 4px', textAlign: 'right' }}>{it?.quantity ?? '-'}</td>
                <td style={{ padding: '8px 4px', textAlign: 'right' }}>{it?.unitPrice?.amount ?? '-'} {it?.unitPrice?.currency ?? ''}</td>
                <td style={{ padding: '8px 4px', textAlign: 'right' }}>{amount.toFixed(2)} {it?.unitPrice?.currency ?? ''}</td>
              </tr>
            )
          })}
        </tbody>
      </table>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 12 }}>
        <div style={{ minWidth: 240 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <div>Ara Toplam</div>
            <div>{data?.totals?.subTotal?.amount ?? '-'} {data?.totals?.subTotal?.currency ?? ''}</div>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <div>KDV</div>
            <div>{data?.totals?.vatTotal?.amount ?? '-'} {data?.totals?.vatTotal?.currency ?? ''}</div>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 16 }}>
            <div>GENEL TOPLAM</div>
            <div>{grand?.amount ?? '-'} {grand?.currency ?? ''}</div>
          </div>
        </div>
      </div>
    </div>
  )
}

export function openInvoicePrintWindow(data: any) {
  const win = window.open('', 'PRINT', 'height=800,width=600')
  if (!win) return
  const style = `body{font-family:ui-sans-serif,system-ui;margin:0;padding:16px;background:#f8fafc}`
  const container = document.createElement('div')
  container.innerHTML = `
    <div style="font-family: ui-sans-serif, system-ui; background: white; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:12px">
        <div><div style="font-weight:700;font-size:18px">efatura.ai</div><div style="color:#64748b">FATURA</div></div>
        <div style="text-align:right"><div><strong>No:</strong> ${data?.id ?? '-'}</div><div><strong>Tarih:</strong> ${data?.issueDate ?? '-'}</div></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div><div style="color:#64748b;font-size:12px">MÜŞTERİ</div><div style="font-weight:600">${data?.customer?.name ?? '-'}</div></div>
        <div><div style="color:#64748b;font-size:12px">SATAN</div><div style="font-weight:600">Şirket Adı</div></div>
      </div>
      <div><strong>Toplam:</strong> ${data?.totals?.grandTotal?.amount ?? '-'} ${data?.totals?.grandTotal?.currency ?? ''}</div>
    </div>`
  win.document.write(`<html><head><title>Fatura #${data?.id ?? ''}</title><style>${style}</style></head><body>${container.innerHTML}</body></html>`)
  win.document.close()
  win.focus()
  win.print()
}
