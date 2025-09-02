import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import { useToast } from './ui/ToastProvider'
import { useNotifications } from './ui/NotificationProvider'
import { useI18n } from './ui/I18nProvider'
import logoUrl from '../assets/logo.svg'
import OrgSwitcher from './OrgSwitcher'
import { IconHome, IconPaper, IconBox, IconUsers, IconCard, IconChart, IconLink, IconMail, IconBell, IconChevronDown, IconChevronRight } from './ui/Icons'

export default function PanelLayout() {
    const navigate = useNavigate()
    const [role, setRole] = useState<string | null>(() => localStorage.getItem('currentUserRole') || null)
    const [collapsed, setCollapsed] = useState<boolean>(false)
    const [mobileOpen, setMobileOpen] = useState<boolean>(false)
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({
        docs: false,
        catalog: false,
        balance: false,
        security: false,
        webhook: false,
        subs: false,
        tools: false,
        admin: false,
    })
    const toggleGroup = (key: string) => setOpenGroups(s => ({ ...s, [key]: !s[key] }))
    const toast = useToast()
    const notif = useNotifications()
    const { locale, setLocale, t } = useI18n()
    const [theme, setTheme] = useState<string>(() => localStorage.getItem('theme') || 'light')

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme)
        localStorage.setItem('theme', theme)
    }, [theme])

    useEffect(() => {
        const key = localStorage.getItem('apiKey')
        const token = localStorage.getItem('userToken')
        if (!key || !token) navigate('/login')
    }, [navigate])

    const orgQuery = useQuery({
        queryKey: ['organizations','current'],
        queryFn: async () => {
            const res = await api.get('/organizations/current')
            return res.data as { id: string; name: string; currentUserRole: string | null }
        },
        staleTime: 5 * 60 * 1000,
        retry: 2,
        refetchOnWindowFocus: false,
    })

    const currentRole = useMemo(() => orgQuery.data?.currentUserRole ?? role, [orgQuery.data, role])

    useEffect(() => {
        if (orgQuery.data?.currentUserRole) {
            setRole(orgQuery.data.currentUserRole)
            localStorage.setItem('currentUserRole', orgQuery.data.currentUserRole)
        }
    }, [orgQuery.data])

    const organizationName = orgQuery.data?.name || 'efatura.ai'
    const email = ((): string => {
        try { return JSON.parse(atob((localStorage.getItem('userToken')||'').split('.')[1]||''))?.email || '' } catch { return '' }
    })()
    const avatarInitials = (organizationName || 'EA').split(' ').map(s => s[0]).slice(0,2).join('').toUpperCase()

    return (
        <div className="panel" style={{ gridTemplateColumns: collapsed ? '72px 1fr' : '220px 1fr' }}>
            <aside className={`sidebar${mobileOpen ? ' open' : ''}`} style={{ width: collapsed ? 72 : 220, transition: 'width 160ms ease' }}>
                <div className="sidebar-brand" style={{ display: 'flex', alignItems: 'center', justifyContent: collapsed ? 'center' : 'space-between', gap: 8 }}>
                    {!collapsed ? <img src={logoUrl} alt="efatura.ai" height={34} /> : <span style={{ fontWeight: 800 }}>ea</span>}
                    {!collapsed && (
                        <button onClick={() => setCollapsed(true)} style={{ borderRadius: 8, padding: '6px 8px' }}>Daralt</button>
                    )}
                    {collapsed && (
                        <button onClick={() => setCollapsed(false)} style={{ borderRadius: 8, padding: '6px 8px' }}>Genişlet</button>
                    )}
                </div>
                <nav className="sidebar-nav">
                    {!collapsed && <div className="sidebar-label">Menü</div>}
                    <NavLink to="/app" end className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconHome />
                            {!collapsed && t('dashboard')}
                        </span>
                    </NavLink>
                    {!collapsed && (
                        <button className="sidebar-section" aria-expanded={openGroups.docs} onClick={() => toggleGroup('docs')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                            <IconPaper /> <span>Belgeler</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.docs ? <IconChevronDown /> : <IconChevronRight />}</span>
                        </button>
                    )}
                    {openGroups.docs && (
                        <>
                    <NavLink to="/app/invoices" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconPaper />
                            {!collapsed && t('invoices')}
                        </span>
                    </NavLink>
                    <NavLink to="/app/invoices/inbox" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconPaper />
                            {!collapsed && 'Gelen e‑Faturalar'}
                        </span>
                    </NavLink>
                    <NavLink to="/app/vouchers" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconPaper />
                            {!collapsed && t('vouchers')}
                        </span>
                    </NavLink>
                    <NavLink to="/app/despatches" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconBox />
                            {!collapsed && t('despatches')}
                        </span>
                    </NavLink>
                        </>
                    )}
                    {!collapsed && (
                        <button className="sidebar-section" aria-expanded={openGroups.catalog} onClick={() => toggleGroup('catalog')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                            <IconBox /> <span>Katalog</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.catalog ? <IconChevronDown /> : <IconChevronRight />}</span>
                        </button>
                    )}
                    {openGroups.catalog && (
                        <>
                    <NavLink to="/app/customers" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconUsers />
                            {!collapsed && t('customers')}
                        </span>
                    </NavLink>
                    <NavLink to="/app/products" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconBox />
                            {!collapsed && t('products')}
                        </span>
                    </NavLink>
                        </>
                    )}
                    {!collapsed && <div className="sidebar-label">Destek</div>}
                    {!collapsed && (
                        <button className="sidebar-section" aria-expanded={openGroups.balance} onClick={() => toggleGroup('balance')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                            <IconCard /> <span>Bakiye</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.balance ? <IconChevronDown /> : <IconChevronRight />}</span>
                        </button>
                    )}
                    {openGroups.balance && (
                        <>
                    <NavLink to="/app/credits/wallet" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconCard />
                            {!collapsed && t('wallet')}
                        </span>
                    </NavLink>
                    <NavLink to="/app/credits/transactions" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconChart />
                            {!collapsed && t('transactions')}
                        </span>
                    </NavLink>
                        </>
                    )}
                    {!collapsed && (
                        <button className="sidebar-section" aria-expanded={openGroups.security} onClick={() => toggleGroup('security')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                            <IconLink /> <span>Güvenlik</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.security ? <IconChevronDown /> : <IconChevronRight />}</span>
                        </button>
                    )}
                    {openGroups.security && (
                        <>
                    <NavLink to="/app/api-keys" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconLink />
                            {!collapsed && 'API Keys'}
                        </span>
                    </NavLink>
                    <NavLink to="/app/api-docs" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconBell />
                            {!collapsed && 'API Docs'}
                        </span>
                    </NavLink>
                    <NavLink to="/app/audit-logs" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconBell />
                            {!collapsed && 'Audit Log'}
                        </span>
                    </NavLink>
                        </>
                    )}
                    {(['owner','admin','finance'] as const).includes((currentRole || '').toLowerCase() as any) && (
                        <>
                            {!collapsed && (
                                <button className="sidebar-section" aria-expanded={openGroups.subs} onClick={() => toggleGroup('subs')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                                    <IconCard /> <span>Abonelik</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.subs ? <IconChevronDown /> : <IconChevronRight />}</span>
                                </button>
                            )}
                            {openGroups.subs && (
                                <>
                            <NavLink to="/app/plans" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                                <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                                    <IconChart />
                                    {!collapsed && 'Planlar'}
                                </span>
                            </NavLink>
                            <NavLink to="/app/subscription" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                                <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                                    <IconCard />
                                    {!collapsed && 'Abonelik'}
                                </span>
                            </NavLink>
                                </>
                            )}
                        </>
                    )}
                    {!collapsed && <div className="sidebar-label">Diğer</div>}
                    {!collapsed && (
                        <button className="sidebar-section" aria-expanded={openGroups.webhook} onClick={() => toggleGroup('webhook')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                            <IconMail /> <span>Webhook</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.webhook ? <IconChevronDown /> : <IconChevronRight />}</span>
                        </button>
                    )}
                    {openGroups.webhook && (
                        <>
                    <NavLink to="/app/webhooks/subscriptions" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconLink />
                            {!collapsed && t('webhook_subscriptions')}
                        </span>
                    </NavLink>
                    <NavLink to="/app/webhooks/deliveries" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`} style={{ padding: '8px 10px' }}>
                        <span style={{ display:'inline-flex', alignItems:'center', gap:12 }}>
                            <IconMail />
                            {!collapsed && t('webhook_deliveries')}
                        </span>
                    </NavLink>
                        </>
                    )}
                    {currentRole === 'owner' && (
                        <>
                            {!collapsed && (
                                <button className="sidebar-section" aria-expanded={openGroups.tools} onClick={() => toggleGroup('tools')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                                    <IconChart /> <span>İç Araçlar</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.tools ? <IconChevronDown /> : <IconChevronRight />}</span>
                                </button>
                            )}
                            {openGroups.tools && (
                                <NavLink to="/app/internal/providers/kolaysoft" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>{collapsed ? 'St' : 'Sağlayıcı Test'}</NavLink>
                            )}
                            {!collapsed && (
                                <button className="sidebar-section" aria-expanded={openGroups.admin} onClick={() => toggleGroup('admin')} style={{ background:'transparent', border:0, textAlign:'left', width:'100%', cursor:'pointer', display:'flex', alignItems:'center', gap:10, padding:'8px 10px' }}>
                                    <IconBell /> <span>Admin</span> <span style={{ marginLeft:'auto', display:'inline-flex' }}>{openGroups.admin ? <IconChevronDown /> : <IconChevronRight />}</span>
                                </button>
                            )}
                            {openGroups.admin && (
                                <>
                                    <NavLink to="/app/admin/organizations" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>{collapsed ? 'Org' : 'Organizasyonlar'}</NavLink>
                                    <NavLink to="/app/admin/plans" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>{collapsed ? 'Plan' : 'Planlar'}</NavLink>
                                    <NavLink to="/app/admin/dlq" className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>{collapsed ? 'DLQ' : 'DLQ'}</NavLink>
                                </>
                            )}
                        </>
                    )}
                </nav>
                <div className="sidebar-footer" />
            </aside>
            <div className="workspace">
                <header className="topbar">
                    <div className="topbar-left" style={{ display: 'flex', alignItems: 'center', gap: 8, flex: 1 }}>
                        <button aria-label="Menü" className="btn-secondary only-mobile" onClick={() => setMobileOpen(v => !v)} style={{ padding: '6px 10px' }}>☰</button>
                        <div className="topbar-title">efatura.ai Panel</div>
                        <div style={{ marginLeft: 12, flex: 1, maxWidth: 420 }}>
                            <input placeholder="Ara..." className="input" style={{ width: '100%', height: 36 }} />
                        </div>
                    </div>
                    <div className="topbar-actions" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <div className="notif-menu" style={{ position: 'relative' }}>
                            <details>
                                <summary style={{ listStyle: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
                                    <span role="img" aria-label="bell">🔔</span>
                                    {notif.unreadCount > 0 && <span className="badge" title="Okunmamış">{notif.unreadCount}</span>}
                                </summary>
                                <div className="dropdown" style={{ position: 'absolute', right: 0, top: 'calc(100% + 8px)', background: 'white', border: '1px solid #e2e8f0', borderRadius: 10, boxShadow: '0 6px 24px rgba(15, 23, 42, 0.06)', minWidth: 320, maxHeight: 360, overflowY: 'auto' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 12px', borderBottom: '1px solid #eef2f7' }}>
                                        <div style={{ fontWeight: 600 }}>{t('notifications')}</div>
                                        <div style={{ display: 'flex', gap: 8 }}>
                                            <button className="btn-secondary" onClick={notif.markAllRead}>{t('mark_all_read')}</button>
                                            <button className="btn-secondary" onClick={notif.clear}>{t('clear')}</button>
                                        </div>
                                    </div>
                                    <div style={{ display: 'grid' }}>
                                        {notif.notifications.length === 0 && (
                                            <div style={{ padding: '12px' }}>Bildirim yok</div>
                                        )}
                                        {notif.notifications.map(n => (
                                            <div key={n.id} style={{ padding: '10px 12px', borderBottom: '1px solid #f1f5f9', background: n.read ? 'white' : '#f8fafc' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                                    <span>{n.type === 'ok' ? '✅' : n.type === 'fail' ? '❌' : 'ℹ️'}</span>
                                                    <div style={{ fontWeight: 600 }}>{n.title}</div>
                                                </div>
                                                {n.message && <div style={{ color: '#64748b', marginTop: 4 }}>{n.message}</div>}
                                                <div style={{ color: '#94a3b8', fontSize: 12, marginTop: 4 }}>{new Date(n.createdAt).toLocaleString()}</div>
                                                {n.href && <a href={n.href} style={{ fontSize: 12 }}>Detaya git</a>}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </details>
                        </div>
                        <div className="badge">{currentRole || 'user'}</div>
                        <select value={locale} onChange={(e) => setLocale(e.target.value as any)}>
                            <option value="tr">TR</option>
                            <option value="en">EN</option>
                        </select>
                        <button onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')} style={{ borderRadius: 8, padding: '6px 8px' }}>{theme === 'light' ? t('theme_dark') : t('theme_light')}</button>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                            <OrgSwitcher />
                        </div>
                        <div className="user-menu" style={{ position: 'relative' }}>
                            <details>
                                <summary style={{ listStyle: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}>
                                    <span style={{ width: 28, height: 28, borderRadius: 999, background: '#e2e8f0', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700 }}>{avatarInitials}</span>
                                    <span>Hesap ▾</span>
                                </summary>
                                <div className="dropdown" style={{ position: 'absolute', right: 0, top: 'calc(100% + 8px)', background: 'white', border: '1px solid #e2e8f0', borderRadius: 10, boxShadow: '0 6px 24px rgba(15, 23, 42, 0.06)', minWidth: 200 }}>
                                    <a className="dropdown-item" href="/app/account" style={{ display: 'block', padding: '8px 12px' }}>{t('account')}</a>
                                    {currentRole === 'owner' && (
                                      <button className="dropdown-item" style={{ display:'block', width:'100%', textAlign:'left', padding:'8px 12px', background:'transparent', border:0, cursor:'pointer' }} onClick={() => {
                                        const original = localStorage.getItem('originalUserToken');
                                        if (original) {
                                          localStorage.setItem('userToken', original);
                                          localStorage.removeItem('originalUserToken');
                                          localStorage.removeItem('apiKey');
                                          location.reload();
                                        } else {
                                          alert('Orijinal token bulunamadı');
                                        }
                                      }}>Impersonate Modundan Çık</button>
                                    )}
                                    <button className="dropdown-item" style={{ display: 'block', width: '100%', textAlign: 'left', padding: '8px 12px', background: 'transparent', border: 0, cursor: 'pointer' }} onClick={async () => {
                                        try { await api.post('/auth/logout') } catch {}
                                        localStorage.removeItem('userToken');
                                        window.location.href = '/login'
                                    }}>{t('logout')}</button>
                                </div>
                            </details>
                        </div>
                    </div>
                </header>
                <main className="content">
                    <Outlet />
                </main>
            </div>
            {mobileOpen && <div className="sidebar-backdrop" onClick={() => setMobileOpen(false)} />}
        </div>
    )
}


