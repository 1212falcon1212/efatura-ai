export function DespatchPreview({ data }: { data: any }) {
  return (
    <div style={{ fontFamily: 'ui-sans-serif, system-ui', background: 'white', color: '#0f172a', border: '1px solid #e2e8f0', borderRadius: 12, padding: 16 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
        <div>
          <div style={{ fontWeight: 700, fontSize: 18 }}>efatura.ai</div>
          <div style={{ color: '#64748b' }}>E‑İRSALİYE</div>
        </div>
        <div style={{ textAlign: 'right' }}>
          <div><strong>No:</strong> {data?.id ?? '-'}</div>
          <div><strong>Mod:</strong> {data?.mode ?? '-'}</div>
        </div>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
        <div>
          <div style={{ color: '#64748b', fontSize: 12 }}>ETTN</div>
          <div style={{ fontWeight: 600 }}>{data?.ettn ?? '-'}</div>
        </div>
        <div>
          <div style={{ color: '#64748b', fontSize: 12 }}>DURUM</div>
          <div style={{ fontWeight: 600 }}>{data?.status ?? '-'}</div>
        </div>
      </div>
    </div>
  )
}

export function openDespatchPrintWindow(data: any) {
  const win = window.open('', 'PRINT', 'height=800,width=600')
  if (!win) return
  const style = `body{font-family:ui-sans-serif,system-ui;margin:0;padding:16px;background:#f8fafc}`
  const html = `<div style="font-family: ui-sans-serif, system-ui; background: white; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <div><div style="font-weight:700;font-size:18px">efatura.ai</div><div style="color:#64748b">E‑İRSALİYE</div></div>
      <div style="text-align:right"><div><strong>No:</strong> ${data?.id ?? '-'}</div><div><strong>Mod:</strong> ${data?.mode ?? '-'}</div></div>
    </div>
    <div><strong>ETTN:</strong> ${data?.ettn ?? '-'}</div>
  </div>`
  win.document.write(`<html><head><title>İrsaliye #${data?.id ?? ''}</title><style>${style}</style></head><body>${html}</body></html>`)
  win.document.close(); win.focus(); win.print()
}
