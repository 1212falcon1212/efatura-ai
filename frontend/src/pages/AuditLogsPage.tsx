import { useInfiniteQuery } from '@tanstack/react-query'
import { api } from '../lib/api'
import DataTable, { type Column } from '../components/ui/DataTable'
import { useSearchParams } from 'react-router-dom'

async function fetchLogs({ pageParam, action, q }: { pageParam?: string | null, action?: string, q?: string }) {
  const params: Record<string, string> = {}
  if (pageParam) params['page[after]'] = pageParam
  if (action) params.action = action
  if (q) params.q = q
  const res = await api.get('/audit-logs', { params })
  return { items: res.data?.data ?? [], next: (res.headers['x-next-cursor'] || res.headers['X-Next-Cursor'] || null) as string | null }
}

export default function AuditLogsPage() {
  const [sp, setSp] = useSearchParams()
  const action = sp.get('action') || ''
  const q = sp.get('q') || ''
  const list = useInfiniteQuery<any, any, any, any, string | null>({
    queryKey: ['audit_logs', action, q],
    queryFn: ({ pageParam }) => fetchLogs({ pageParam: pageParam ?? null, action: action || undefined, q: q || undefined }),
    getNextPageParam: (last) => last.next || undefined,
    initialPageParam: null,
  })
  const rows = (((list.data?.pages as unknown) as any[]) ?? []).flatMap((p: any) => p.items)
  const columns: Array<Column<any>> = [
    { key: 'id', label: 'ID' },
    { key: 'created_at', label: 'Tarih' },
    { key: 'user_id', label: 'Kullanıcı' },
    { key: 'action', label: 'Aksiyon' },
    { key: 'entity_type', label: 'Tip' },
    { key: 'entity_id', label: 'Ref' },
    { key: 'context', label: 'Context', render: (r) => <code style={{ fontSize: 12 }}>{JSON.stringify(r.context)}</code> },
  ]
  return (
    <div style={{ padding: 24 }}>
      <h2>Audit Log</h2>
      <DataTable
        columns={columns}
        rows={rows as any[]}
        getRowId={(r: any) => r.id}
        storageKey="dt/audit_logs"
        hasMore={!!list.hasNextPage}
        loadingMore={!!list.isFetchingNextPage}
        onLoadMore={() => list.fetchNextPage()}
        virtualized
        rowHeight={40}
      />
    </div>
  )
}


