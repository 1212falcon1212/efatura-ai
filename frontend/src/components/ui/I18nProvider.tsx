import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'

type Locale = 'tr' | 'en'

type Dict = Record<string, string>

const tr: Dict = {
  dashboard: 'Dashboard',
  invoices: 'Faturalar',
  vouchers: 'E‑Makbuz',
  despatches: 'E‑İrsaliye',
  customers: 'Müşteriler',
  products: 'Ürünler',
  wallet: 'Cüzdan',
  transactions: 'Hareketler',
  webhook_subscriptions: 'Abonelikler',
  webhook_deliveries: 'Teslimatlar',
  account: 'Hesap',
  logout: 'Çıkış Yap',
  notifications: 'Bildirimler',
  mark_all_read: 'Tümünü okundu işaretle',
  clear: 'Temizle',
  theme_dark: 'Koyu',
  theme_light: 'Aydınlık',
}

const en: Dict = {
  dashboard: 'Dashboard',
  invoices: 'Invoices',
  vouchers: 'E‑Receipt',
  despatches: 'E‑Despatch',
  customers: 'Customers',
  products: 'Products',
  wallet: 'Wallet',
  transactions: 'Transactions',
  webhook_subscriptions: 'Subscriptions',
  webhook_deliveries: 'Deliveries',
  account: 'Account',
  logout: 'Logout',
  notifications: 'Notifications',
  mark_all_read: 'Mark all read',
  clear: 'Clear',
  theme_dark: 'Dark',
  theme_light: 'Light',
}

const I18nContext = createContext<{ locale: Locale; setLocale: (l: Locale) => void; t: (k: string) => string } | null>(null)

export function I18nProvider({ children }: { children: ReactNode }) {
  const [locale, setLocale] = useState<Locale>(() => (localStorage.getItem('locale') as Locale) || 'tr')

  useEffect(() => { try { localStorage.setItem('locale', locale) } catch {} }, [locale])

  const dict = locale === 'en' ? en : tr
  const value = useMemo(() => ({ locale, setLocale, t: (k: string) => dict[k] ?? k }), [locale])
  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
}

export function useI18n() {
  const ctx = useContext(I18nContext)
  if (!ctx) throw new Error('useI18n must be used within I18nProvider')
  return ctx
}


