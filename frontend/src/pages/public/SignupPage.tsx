import { useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { useToast } from '../../components/ui/ToastProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

export default function SignupPage() {
  const nav = useNavigate()
  const toast = useToast()
  const schema = z.object({
    email: z.string().email('Geçerli bir e‑posta girin'),
    organization: z.string().min(2, 'Organizasyon adı en az 2 karakter olmalı'),
    password: z.string().min(8, 'Şifre en az 8 karakter olmalı'),
    confirmPassword: z.string().optional(),
    // Organizasyon fatura profil alanları (TCKN hariç zorunlu)
    company_title: z.string().min(2, 'Ünvan zorunludur'),
    vkn: z.string().optional(),
    tckn: z.string().optional(),
    tax_office: z.string().min(2, 'Vergi dairesi zorunludur'),
    address: z.string().min(3, 'Adres zorunludur'),
    city: z.string().min(2, 'Şehir zorunludur'),
    district: z.string().min(2, 'İlçe zorunludur'),
    phone: z.string().min(4, 'Telefon zorunludur'),
    postal_code: z.string().min(3, 'Posta kodu zorunludur'),
    country: z.string().min(2, 'Ülke zorunludur').default('Türkiye'),
    company_email: z.string().email('Geçerli bir şirket e‑posta girin'),
    birthDate: z.string().optional(),
  }).superRefine((val, ctx) => {
    if (val.confirmPassword && val.confirmPassword !== val.password) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['confirmPassword'],
        message: 'Şifreler uyuşmuyor',
      })
    }
    if (val.tckn && !/^\d{11}$/.test(val.tckn)) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['tckn'], message: 'TCKN 11 haneli olmalıdır' })
    }
    if (val.vkn && !/^\d{10}$/.test(val.vkn)) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['vkn'], message: 'VKN 10 haneli olmalıdır' })
    }
    if (!val.vkn && !val.tckn) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, path: ['vkn'], message: 'VKN veya TCKN’den en az biri zorunlu' })
    }
  })
  type FormValues = z.infer<typeof schema>
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm({ resolver: zodResolver(schema) })

  async function onSubmit(values: FormValues) {
    const { email, organization, password, confirmPassword, birthDate, ...rest } = values
    const res = await api.post('/auth/signup', { email, organization, password, ...rest })
    localStorage.setItem('userToken', res.data.token)
    if (res.data.apiKey) localStorage.setItem('apiKey', res.data.apiKey)
    toast.show({ type: 'success', title: 'Kayıt başarılı' })
    nav('/app')
  }
  return (
    <div style={{ padding: 24 }}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 560px', gap: 24, alignItems: 'start', maxWidth: 1100, margin: '8vh auto' }}>
        <div className="card" style={{ padding: 20 }}>
          <div style={{ fontSize: 18, fontWeight: 700, marginBottom: 8 }}>Tek API ile e‑Belge</div>
          <div style={{ color: '#475569', lineHeight: 1.5 }}>
            e‑Fatura, e‑Arşiv ve e‑İrsaliye işlemlerini tek panelden yönetin. Otomatik kontör yönetimi,
            akıllı hata yakalama, webhook’lar ve güçlü raporlama ile entegre olun. Dakikalar içinde başlayın.
          </div>
          <ul style={{ marginTop: 12, paddingLeft: 18, color: '#334155' }}>
            <li>Güvenli ödeme ve otomatik kontör satın alma</li>
            <li>Webhook ve DLQ ile güvenilir teslimat</li>
            <li>Owner havuzu ve müşteri limitleri</li>
          </ul>
        </div>
        <div className="card" style={{ maxWidth: 560 }}>
          <h2 style={{ marginTop: 0 }}>Kayıt Ol</h2>
          <form onSubmit={handleSubmit(onSubmit)} style={{ display: 'grid', gap: 12 }}>
          <div className="field">
            <label>E‑posta</label>
            <input className="input" placeholder="ornek@domain.com" {...register('email')} />
            {errors.email && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.email.message}</div>}
          </div>
          <div className="field">
            <label>Organizasyon Adı</label>
            <input className="input" placeholder="Şirket Ltd." {...register('organization')} />
            {errors.organization && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.organization.message}</div>}
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Ünvan</label>
              <input className="input" placeholder="Şirket Unvanı" {...register('company_title')} />
              {errors.company_title && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.company_title.message}</div>}
            </div>
            <div>
              <label>Vergi Dairesi</label>
              <input className="input" placeholder="Beykoz VD" {...register('tax_office')} />
              {errors.tax_office && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.tax_office.message}</div>}
            </div>
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>VKN</label>
              <input className="input" placeholder="0000000000" maxLength={10} {...register('vkn')} />
              {errors.vkn && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.vkn.message}</div>}
            </div>
            <div>
              <label>TCKN (opsiyonel)</label>
              <input className="input" placeholder="00000000000" maxLength={11} {...register('tckn')} />
              {errors.tckn && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.tckn.message}</div>}
            </div>
          </div>
          <div className="field">
            <label>Adres</label>
            <input className="input" placeholder="Açık adres" {...register('address')} />
            {errors.address && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.address.message}</div>}
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Şehir</label>
              <input className="input" placeholder="İstanbul" {...register('city')} />
              {errors.city && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.city.message}</div>}
            </div>
            <div>
              <label>İlçe</label>
              <input className="input" placeholder="Kadıköy" {...register('district')} />
              {errors.district && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.district.message}</div>}
            </div>
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Posta Kodu</label>
              <input className="input" placeholder="34000" {...register('postal_code')} />
              {errors.postal_code && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.postal_code.message}</div>}
            </div>
            <div>
              <label>Ülke</label>
              <input className="input" placeholder="Türkiye" defaultValue="Türkiye" {...register('country')} />
              {errors.country && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.country.message}</div>}
            </div>
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Telefon</label>
              <input className="input" placeholder="5xx xxx xx xx" {...register('phone')} />
              {errors.phone && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.phone.message}</div>}
            </div>
            <div>
              <label>Şirket E‑posta</label>
              <input className="input" placeholder="fatura@firma.com" {...register('company_email')} />
              {errors.company_email && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.company_email.message}</div>}
            </div>
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Doğum Tarihi (opsiyonel)</label>
              <input className="input" type="date" {...register('birthDate')} />
              {errors.birthDate && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.birthDate.message}</div>}
            </div>
          </div>
          <div className="field" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            <div>
              <label>Şifre</label>
              <input className="input" placeholder="••••••••" type="password" {...register('password')} />
              {errors.password && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.password.message}</div>}
            </div>
            <div>
              <label>Şifre Doğrulama (opsiyonel)</label>
              <input className="input" placeholder="••••••••" type="password" {...register('confirmPassword')} />
              {errors.confirmPassword && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.confirmPassword.message}</div>}
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 4 }}>
            <button type="submit" disabled={isSubmitting}>{isSubmitting ? 'Kaydediliyor...' : 'Kayıt Ol'}</button>
          </div>
          </form>
        </div>
      </div>
    </div>
  )
}


