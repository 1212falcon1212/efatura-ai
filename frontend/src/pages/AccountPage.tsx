import { useEffect } from 'react'
import { useQuery, useMutation } from '@tanstack/react-query'
import { api } from '../lib/api'
import PageHeader from '../components/ui/PageHeader'
import { useToast } from '../components/ui/ToastProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

const profileSchema = z.object({
  name: z.string().min(2, 'Ad en az 2 karakter olmalı'),
  email: z.string().email(),
})

type ProfileForm = z.infer<typeof profileSchema>

const passwordSchema = z.object({
  oldPassword: z.string().min(6, 'Mevcut şifre en az 6 karakter'),
  newPassword: z.string().min(8, 'Yeni şifre en az 8 karakter'),
})

type PasswordForm = z.infer<typeof passwordSchema>

export default function AccountPage() {
  const toast = useToast()

  const profileForm = useForm<ProfileForm>({ resolver: zodResolver(profileSchema), defaultValues: { name: '', email: '' } })
  const passwordForm = useForm<PasswordForm>({ resolver: zodResolver(passwordSchema), defaultValues: { oldPassword: '', newPassword: '' } })
  const cards = useQuery({ queryKey: ['billing_cards'], queryFn: async () => (await api.get('/billing/cards')).data?.data ?? [] })
  const orgSettings = useQuery({ queryKey: ['org_settings'], queryFn: async () => (await api.get('/auth/org-settings')).data?.data })
  const saveOrg = useMutation({ mutationFn: async (payload: any) => (await api.post('/auth/org-settings', payload)).data, onSuccess: () => { toast.show({ type: 'success', title: 'Organizasyon ayarları güncellendi' }); orgSettings.refetch() } })
  const delCard = async (id: number) => { await api.delete(`/billing/cards/${id}`); toast.show({ type: 'success', title: 'Kart silindi' }); cards.refetch() }

  useEffect(() => {
    ;(async () => {
      const res = await api.get('/auth/me')
      profileForm.reset({ name: res.data.name || '', email: res.data.email || '' })
    })()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function saveProfile(values: ProfileForm) {
    await api.post('/auth/update-profile', { name: values.name })
    toast.show({ type: 'success', title: 'Profil güncellendi' })
  }

  async function changePassword(values: PasswordForm) {
    await api.post('/auth/change-password', { oldPassword: values.oldPassword, newPassword: values.newPassword })
    passwordForm.reset()
    toast.show({ type: 'success', title: 'Şifre değiştirildi' })
  }

  async function logout() {
    try { await api.post('/auth/logout') } catch {}
    localStorage.removeItem('userToken')
    window.location.href = '/login'
  }

  const profileErrors = Object.values(profileForm.formState.errors).map(e => e.message).filter(Boolean) as string[]
  const passwordErrors = Object.values(passwordForm.formState.errors).map(e => e.message).filter(Boolean) as string[]

  return (
    <div>
      <PageHeader title="Hesap" subtitle="Kullanıcı bilgileri ve güvenlik" actions={<button onClick={logout}>Çıkış Yap</button>} crumbs={[{ label: 'Panel', href: '/app' }, { label: 'Hesap' }]} />
      <div style={{ display: 'grid', gap: 16, maxWidth: 520 }}>
        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Profil</div>
          {profileErrors.length > 0 && (
            <div className="card" style={{ background: '#fff7ed', borderColor: '#fdba74', marginBottom: 8 }}>
              <div style={{ color: '#9a3412', fontWeight: 600 }}>Hata</div>
              <ul style={{ margin: '6px 0 0 16px' }}>{profileErrors.map((m, i) => (<li key={i}>{m}</li>))}</ul>
            </div>
          )}
          <form onSubmit={profileForm.handleSubmit(saveProfile)}>
            <div className="field">
              <label>Ad Soyad</label>
              <input className="input" {...profileForm.register('name')} />
            </div>
            <div className="field" style={{ marginTop: 8 }}>
              <label>E‑posta</label>
              <input className="input" {...profileForm.register('email')} disabled />
            </div>
            <div style={{ marginTop: 12 }}>
              <button type="submit" disabled={!profileForm.formState.isDirty || profileForm.formState.isSubmitting}>Kaydet</button>
            </div>
          </form>
        </div>

        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Şifre Değiştir</div>
          {passwordErrors.length > 0 && (
            <div className="card" style={{ background: '#fef2f2', borderColor: '#fca5a5', marginBottom: 8 }}>
              <div style={{ color: '#991b1b', fontWeight: 600 }}>Hata</div>
              <ul style={{ margin: '6px 0 0 16px' }}>{passwordErrors.map((m, i) => (<li key={i}>{m}</li>))}</ul>
            </div>
          )}
          <form onSubmit={passwordForm.handleSubmit(changePassword)}>
            <div className="field">
              <label>Mevcut Şifre</label>
              <input className="input" type="password" {...passwordForm.register('oldPassword')} />
            </div>
            <div className="field" style={{ marginTop: 8 }}>
              <label>Yeni Şifre</label>
              <input className="input" type="password" {...passwordForm.register('newPassword')} />
            </div>
            <div style={{ marginTop: 12 }}>
              <button type="submit" disabled={passwordForm.formState.isSubmitting}>Şifreyi Güncelle</button>
            </div>
          </form>
        </div>

        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>API Anahtarı</div>
          <div className="field">
            <label>Geçerli X-Api-Key</label>
            <input className="input" defaultValue={localStorage.getItem('apiKey') || ''} onBlur={(e) => { localStorage.setItem('apiKey', e.target.value); toast.show({ type: 'success', title: 'API anahtarı kaydedildi' }) }} />
          </div>
        </div>

        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Ödeme Ayarlarım</div>
          {cards.isLoading && <div>Yükleniyor…</div>}
          {!cards.isLoading && (
            <div style={{ display:'grid', gap:8 }}>
              {cards.data.length === 0 && <div>Kayıtlı kart yok.</div>}
              {cards.data.map((c: any) => (
                <div key={c.id} style={{ display:'flex', alignItems:'center', justifyContent:'space-between' }}>
                  <div style={{ fontFamily:'monospace' }}>{c.masked_pan || c.token}</div>
                  <button className="btn-secondary" onClick={() => delCard(c.id)}>Sil</button>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>Profil Bilgileri</div>
          {orgSettings.isLoading && <div>Yükleniyor…</div>}
          {!orgSettings.isLoading && (
            <form onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget as HTMLFormElement); const settings: any = { company_title: fd.get('company_title') || undefined, vkn: fd.get('vkn') || undefined, tckn: fd.get('tckn') || undefined, tax_office: fd.get('tax_office') || undefined, address: fd.get('address') || undefined, city: fd.get('city') || undefined, district: fd.get('district') || undefined, phone: fd.get('phone') || undefined, postal_code: fd.get('postal_code') || undefined, country: fd.get('country') || undefined, company_email: fd.get('company_email') || undefined }; saveOrg.mutate({ settings }) }} style={{ display:'grid', gap:8 }}>
              <div className="field"><label>Ünvan</label><input className="input" name="company_title" defaultValue={orgSettings.data?.settings?.company_title || ''} /></div>
              <div className="field" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                <div><label>VKN</label><input className="input" name="vkn" defaultValue={orgSettings.data?.settings?.vkn || ''} /></div>
                <div><label>TCKN</label><input className="input" name="tckn" defaultValue={orgSettings.data?.settings?.tckn || ''} /></div>
              </div>
              <div className="field"><label>Vergi Dairesi</label><input className="input" name="tax_office" defaultValue={orgSettings.data?.settings?.tax_office || ''} /></div>
              <div className="field"><label>Adres</label><input className="input" name="address" defaultValue={orgSettings.data?.settings?.address || ''} /></div>
              <div className="field" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                <div><label>Şehir</label><input className="input" name="city" defaultValue={orgSettings.data?.settings?.city || ''} /></div>
                <div><label>İlçe</label><input className="input" name="district" defaultValue={orgSettings.data?.settings?.district || ''} /></div>
              </div>
              <div className="field" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                <div><label>Posta Kodu</label><input className="input" name="postal_code" defaultValue={orgSettings.data?.settings?.postal_code || ''} /></div>
                <div><label>Ülke</label><input className="input" name="country" defaultValue={orgSettings.data?.settings?.country || 'Türkiye'} /></div>
              </div>
              <div className="field" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                <div><label>Telefon</label><input className="input" name="phone" defaultValue={orgSettings.data?.settings?.phone || ''} /></div>
                <div><label>Şirket E‑posta</label><input className="input" name="company_email" defaultValue={orgSettings.data?.settings?.company_email || ''} /></div>
              </div>
              <div style={{ marginTop: 8 }}>
                <button type="submit" disabled={saveOrg.isPending}>{saveOrg.isPending ? 'Kaydediliyor…' : 'Kaydet'}</button>
              </div>
            </form>
          )}
        </div>

        <div className="card">
          <div style={{ fontWeight: 600, marginBottom: 8 }}>API Ayarları</div>
          {orgSettings.isLoading && <div>Yükleniyor…</div>}
          {!orgSettings.isLoading && (
            <form onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget as HTMLFormElement); const settings: any = { e_invoice_gb_urn: fd.get('e_invoice_gb_urn') || undefined, e_invoice_pk_urn: fd.get('e_invoice_pk_urn') || undefined }; saveOrg.mutate({ settings }) }} style={{ display:'grid', gap:8 }}>
              <div className="field" style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
                <div><label>GB URN (e‑Fatura)</label><input className="input" name="e_invoice_gb_urn" placeholder="urn:mail:defaultgb" defaultValue={orgSettings.data?.settings?.e_invoice_gb_urn || ''} /></div>
                <div><label>PK URN (e‑Fatura)</label><input className="input" name="e_invoice_pk_urn" placeholder="urn:mail:defaultpk" defaultValue={orgSettings.data?.settings?.e_invoice_pk_urn || ''} /></div>
              </div>
              <div style={{ marginTop: 8 }}>
                <button type="submit" disabled={saveOrg.isPending}>{saveOrg.isPending ? 'Kaydediliyor…' : 'Kaydet'}</button>
              </div>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}


