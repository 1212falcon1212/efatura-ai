import { StrictMode, Suspense, lazy } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider, Navigate } from 'react-router-dom'
import PublicLayout from './components/PublicLayout.tsx'
import PanelLayout from './components/PanelLayout.tsx'
import InvoicesPage from './pages/InvoicesPage.tsx'
import VouchersPage from './pages/VouchersPage.tsx'
import DespatchesPage from './pages/DespatchesPage.tsx'
import InvoiceCreatePage from './pages/InvoiceCreatePage.tsx'
import VoucherCreatePage from './pages/VoucherCreatePage.tsx'
import DespatchCreatePage from './pages/DespatchCreatePage.tsx'
import InvoiceDetailPage from './pages/InvoiceDetailPage.tsx'
const DashboardLazy = lazy(() => import('./pages/DashboardPage.tsx'))
import HomePage from './pages/public/HomePage.tsx'
import LoginPage from './pages/public/LoginPage.tsx'
import SignupPage from './pages/public/SignupPage.tsx'
import PricingPage from './pages/public/PricingPage.tsx'
import ProductCreatePage from './pages/ProductCreatePage.tsx'
import CreditsWalletPage from './pages/CreditsWalletPage.tsx'
import CreditsTransactionsPage from './pages/CreditsTransactionsPage.tsx'
import WebhookSubscriptionsPage from './pages/WebhookSubscriptionsPage.tsx'
import WebhookDeliveriesPage from './pages/WebhookDeliveriesPage.tsx'
import WebhookDeliveriesDetailPage from './pages/WebhookDeliveriesDetailPage.tsx'
import CustomersPage from './pages/CustomersPage.tsx'
import ProductsPage from './pages/ProductsPage.tsx'
import ProductDetailPage from './pages/ProductDetailPage.tsx'
import ProductEditPage from './pages/ProductEditPage.tsx'
import ProviderKolaysoftDebugPage from './pages/ProviderKolaysoftDebugPage.tsx'
import ApiKeysPage from './pages/ApiKeysPage.tsx'
import ApiDocsPage from './pages/ApiDocsPage.tsx'
import AdminOrganizationsPage from './pages/AdminOrganizationsPage.tsx'
import AdminPlansPage from './pages/AdminPlansPage.tsx'
import AdminCouponsPage from './pages/AdminCouponsPage.tsx'
import AdminDLQPage from './pages/AdminDLQPage.tsx'
import AuditLogsPage from './pages/AuditLogsPage.tsx'
import PlansPage from './pages/PlansPage.tsx'
import SubscriptionPage from './pages/SubscriptionPage.tsx'
import { ToastProvider } from './components/ui/ToastProvider.tsx'
import AccountPage from './pages/AccountPage.tsx'
import InboxInvoicesPage from './pages/InboxInvoicesPage.tsx'
import { ConfirmProvider } from './components/ui/ConfirmProvider.tsx'
import ErrorBoundary from './components/ui/ErrorBoundary.tsx'
import VoucherDetailPage from './pages/VoucherDetailPage.tsx'
import DespatchDetailPage from './pages/DespatchDetailPage.tsx'
import { NotificationProvider } from './components/ui/NotificationProvider.tsx'
import { I18nProvider } from './components/ui/I18nProvider.tsx'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60_000, // 5 dk
      gcTime: 30 * 60_000, // 30 dk
      retry: 1,
      refetchOnWindowFocus: false,
      refetchOnReconnect: true,
      refetchOnMount: false,
    },
  },
})

// Register service worker for basic PWA support (no-op if unavailable)
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
  })
}

const router = createBrowserRouter([
  {
    path: '/',
    element: <PublicLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'invoice/new', element: <Navigate to="/app/invoice/new" replace /> },
      { path: 'product/new', element: <Navigate to="/app/products/new" replace /> },
      { path: 'invoice/create', element: <Navigate to="/app/invoice/new" replace /> },
      { path: 'pricing', element: <PricingPage /> },
      { path: 'docs', element: <ApiDocsPage /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'signup', element: <SignupPage /> },
    ],
  },
  {
    path: '/app',
    element: <PanelLayout />,
    children: [
      { index: true, element: (
        <Suspense fallback={<div style={{ padding: 24 }}><span className="badge">Yükleniyor…</span></div>}>
          <DashboardLazy />
        </Suspense>
      ) },
      { path: 'invoices', element: <InvoicesPage /> },
      { path: 'invoices/inbox', element: <InboxInvoicesPage /> },
      { path: 'invoice/new', element: <InvoiceCreatePage /> },
      { path: 'invoice/:id', element: <InvoiceDetailPage /> },
      { path: 'vouchers', element: <VouchersPage /> },
      { path: 'vouchers/new', element: <VoucherCreatePage /> },
      { path: 'vouchers/:id', element: <VoucherDetailPage /> },
      { path: 'despatches', element: <DespatchesPage /> },
      { path: 'despatches/new', element: <DespatchCreatePage /> },
      { path: 'despatches/:id', element: <DespatchDetailPage /> },
      { path: 'credits/wallet', element: <CreditsWalletPage /> },
      { path: 'credits/transactions', element: <CreditsTransactionsPage /> },
      { path: 'webhooks/subscriptions', element: <WebhookSubscriptionsPage /> },
      { path: 'webhooks/deliveries', element: <WebhookDeliveriesPage /> },
      { path: 'webhooks/deliveries/:id', element: <WebhookDeliveriesDetailPage /> },
      { path: 'customers', element: <CustomersPage /> },
      { path: 'products', element: <ProductsPage /> },
      { path: 'products/:id', element: <ProductDetailPage /> },
      { path: 'products/:id/edit', element: <ProductEditPage /> },
      { path: 'products/new', element: <ProductCreatePage /> },
      { path: 'account', element: <AccountPage /> },
      { path: 'api-keys', element: <ApiKeysPage /> },
      { path: 'api-docs', element: <ApiDocsPage /> },
      { path: 'audit-logs', element: <AuditLogsPage /> },
      { path: 'admin/organizations', element: <AdminOrganizationsPage /> },
      { path: 'admin/plans', element: <AdminPlansPage /> },
      { path: 'admin/coupons', element: <AdminCouponsPage /> },
      { path: 'admin/dlq', element: <AdminDLQPage /> },
      { path: 'plans', element: <PlansPage /> },
      { path: 'subscription', element: <SubscriptionPage /> },
      { path: 'internal/providers/kolaysoft', element: <ProviderKolaysoftDebugPage /> },
    ],
  },
])

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <ConfirmProvider>
          <NotificationProvider>
            <I18nProvider>
              <ErrorBoundary>
                <Suspense fallback={<div style={{ padding: 24 }}><span className="badge">Yükleniyor…</span></div>}>
                  <RouterProvider router={router} />
                </Suspense>
              </ErrorBoundary>
            </I18nProvider>
          </NotificationProvider>
        </ConfirmProvider>
      </ToastProvider>
    </QueryClientProvider>
  </StrictMode>,
)
