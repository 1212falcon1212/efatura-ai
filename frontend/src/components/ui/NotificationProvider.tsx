import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

export type AppNotification = {
  id: string
  type: 'ok' | 'fail' | 'info'
  title: string
  message?: string
  href?: string
  createdAt: string
  read?: boolean
}

type NotificationContextValue = {
  notifications: AppNotification[]
  unreadCount: number
  push: (n: Omit<AppNotification, 'id' | 'createdAt' | 'read'>) => void
  markAllRead: () => void
  clear: () => void
}

const NotificationContext = createContext<NotificationContextValue | null>(null)

const STORAGE_KEY = 'notifications'
const MAX_ITEMS = 100

export function NotificationProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<AppNotification[]>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY)
      if (!raw) return []
      const parsed = JSON.parse(raw) as AppNotification[]
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  })
  const firstMountedRef = useRef(true)

  useEffect(() => {
    // İlk mount'ta gereksiz yazmayı engelle
    if (firstMountedRef.current) {
      firstMountedRef.current = false
      return
    }
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)) } catch {}
  }, [items])

  const unreadCount = useMemo(() => items.filter(i => !i.read).length, [items])

  function push(n: Omit<AppNotification, 'id' | 'createdAt' | 'read'>) {
    const id = `${Date.now()}_${Math.random().toString(36).slice(2)}`
    const createdAt = new Date().toISOString()
    setItems(prev => [{ id, createdAt, read: false, ...n }, ...prev].slice(0, MAX_ITEMS))
  }

  function markAllRead() {
    setItems(prev => prev.map(i => ({ ...i, read: true })))
  }

  function clear() {
    setItems([])
  }

  const value = useMemo<NotificationContextValue>(() => ({ notifications: items, unreadCount, push, markAllRead, clear }), [items, unreadCount])

  return (
    <NotificationContext.Provider value={value}>
      {children}
    </NotificationContext.Provider>
  )
}

export function useNotifications(): NotificationContextValue {
  const ctx = useContext(NotificationContext)
  if (!ctx) throw new Error('useNotifications must be used within NotificationProvider')
  return ctx
}


