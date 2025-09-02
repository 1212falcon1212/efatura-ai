import { useQuery } from '@tanstack/react-query'
import { api } from '../lib/api'

export default function OrgSwitcher() {
  const { data } = useQuery({
    queryKey: ['auth_orgs'],
    queryFn: async () => (await api.get('/auth/organizations')).data?.data ?? [],
    staleTime: 5 * 60_000,
  })
  const current = Number(localStorage.getItem('organizationId') || '0')
  function onChange(e: React.ChangeEvent<HTMLSelectElement>) {
    const id = Number(e.target.value)
    const org = (data || []).find((o: any) => o.id === id)
    if (!org) return
    localStorage.setItem('organizationId', String(org.id))
    localStorage.setItem('apiKey', org.apiKey)
    window.location.reload()
  }
  return (
    <select value={current || (data?.[0]?.id ?? '')} onChange={onChange} title="Organizasyon seç">
      {(data || []).map((o: any) => (
        <option key={o.id} value={o.id}>{o.name}</option>
      ))}
    </select>
  )
}


