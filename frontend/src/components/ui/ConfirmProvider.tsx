import * as React from 'react'

type ConfirmOptions = {
  title?: string
  description?: string
  confirmText?: string
  cancelText?: string
  variant?: 'default' | 'danger'
}

type ConfirmContextValue = {
  confirm: (opts: ConfirmOptions) => Promise<boolean>
}

const ConfirmContext = React.createContext<ConfirmContextValue | null>(null)

export function useConfirm(): ConfirmContextValue['confirm'] {
  const ctx = React.useContext(ConfirmContext)
  if (!ctx) throw new Error('useConfirm must be used within ConfirmProvider')
  return ctx.confirm
}

export function ConfirmProvider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = React.useState(false)
  const [opts, setOpts] = React.useState<ConfirmOptions>({})
  const resolver = React.useRef<((value: boolean) => void) | null>(null)
  const confirmBtnRef = React.useRef<HTMLButtonElement>(null)

  const confirm = React.useCallback((options: ConfirmOptions) => {
    setOpts(options)
    setOpen(true)
    return new Promise<boolean>((resolve) => {
      resolver.current = resolve
    })
  }, [])

  function close(result: boolean) {
    setOpen(false)
    const r = resolver.current
    resolver.current = null
    if (r) r(result)
  }

  React.useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if (!open) return
      if (e.key === 'Escape') close(false)
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open])

  React.useEffect(() => {
    if (open) confirmBtnRef.current?.focus()
  }, [open])

  const variant = opts.variant || 'default'

  return (
    <ConfirmContext.Provider value={{ confirm }}>
      {children}
      {open && (
        <div className="modal-overlay" role="presentation">
          <div className="modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby={opts.description ? 'confirm-desc' : undefined}>
            <div id="confirm-title" className="modal-title">{opts.title || 'Onaylıyor musunuz?'}</div>
            {opts.description && <div id="confirm-desc" className="modal-desc">{opts.description}</div>}
            <div className="modal-actions">
              <button onClick={() => close(false)} className="btn-secondary">{opts.cancelText || 'Vazgeç'}</button>
              <button ref={confirmBtnRef} onClick={() => close(true)} className={variant === 'danger' ? 'btn-danger' : undefined}>{opts.confirmText || 'Onayla'}</button>
            </div>
          </div>
        </div>
      )}
    </ConfirmContext.Provider>
  )
}
