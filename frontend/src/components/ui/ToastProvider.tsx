import { createContext, useCallback, useContext, useMemo, useState } from 'react'

type ToastType = 'info' | 'success' | 'error' | 'warning'

export type ToastOptions = {
  title: string
  description?: string
  type?: ToastType
  durationMs?: number
}

type ToastItem = ToastOptions & { id: string; createdAt: number }

type ToastContextValue = {
  show: (opts: ToastOptions) => void
}

const ToastContext = createContext<ToastContextValue | null>(null)

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) throw new Error('useToast must be used inside <ToastProvider/>')
  return ctx
}

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([])

  const show = useCallback((opts: ToastOptions) => {
    const id = Math.random().toString(36).slice(2)
    const item: ToastItem = {
      id,
      createdAt: Date.now(),
      durationMs: opts.durationMs ?? 4000,
      type: opts.type ?? 'info',
      title: opts.title,
      description: opts.description,
    }
    setToasts((s) => [...s, item])
    setTimeout(() => {
      setToasts((s) => s.filter((t) => t.id !== id))
    }, item.durationMs)
  }, [])

  const value = useMemo(() => ({ show }), [show])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="toast-container">
        {toasts.map((t) => (
          <div key={t.id} className={`toast toast-${t.type}`}>
            <div className="toast-title">{t.title}</div>
            {t.description && <div className="toast-desc">{t.description}</div>}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}


