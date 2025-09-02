import type { ReactNode } from 'react'

export default function PageHeader({
  title,
  subtitle,
  actions,
  crumbs,
}: {
  title: string
  subtitle?: string
  actions?: ReactNode
  crumbs?: Array<{ label: string; href?: string }>
}) {
  return (
    <div style={{ marginBottom: 16 }}>
      {crumbs && crumbs.length > 0 && (
        <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 6 }}>
          {crumbs.map((c, i) => (
            <span key={i}>
              {c.href ? <a href={c.href}>{c.label}</a> : c.label}
              {i < crumbs.length - 1 ? ' / ' : ''}
            </span>
          ))}
        </div>
      )}
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <div style={{ flex: 1 }}>
          <h2 style={{ margin: 0 }}>{title}</h2>
          {subtitle && <div style={{ color: 'var(--muted)', marginTop: 4 }}>{subtitle}</div>}
        </div>
        {actions}
      </div>
    </div>
  )}


