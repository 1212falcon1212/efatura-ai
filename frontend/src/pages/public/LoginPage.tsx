import { useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import { useToast } from '../../components/ui/ToastProvider'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'

export default function LoginPage() {
  const nav = useNavigate()
  const toast = useToast()
  const schema = z.object({
    email: z.string().email('Geçerli bir e‑posta girin'),
    password: z.string().min(8, 'Şifre en az 8 karakter olmalı'),
  })
  type FormValues = z.infer<typeof schema>
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormValues>({ resolver: zodResolver(schema) })

  async function onSubmit(values: FormValues) {
    const res = await api.post('/auth/login', values)
    if (res.data?.token) localStorage.setItem('userToken', res.data.token)
    if (res.data?.apiKey) localStorage.setItem('apiKey', res.data.apiKey)
    toast.show({ type: 'success', title: 'Giriş başarılı' })
    nav('/app')
  }
  return (
    <div style={{ padding: 24 }}>
      <div className="card" style={{ maxWidth: 480, margin: '10vh auto' }}>
        <form onSubmit={handleSubmit(onSubmit)} style={{ display: 'grid', gap: 12 }}>
          <h2 style={{ marginTop: 0 }}>Giriş Yap</h2>
          <div className="field">
            <label>E‑posta</label>
            <input className="input" placeholder="ornek@domain.com" {...register('email')} />
            {errors.email && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.email.message}</div>}
          </div>
          <div className="field">
            <label>Şifre</label>
            <input className="input" placeholder="••••••••" type="password" {...register('password')} />
            {errors.password && <div style={{ color: 'crimson', fontSize: 12 }}>{errors.password.message}</div>}
          </div>
          <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
            <button type="submit" disabled={isSubmitting}>{isSubmitting ? 'Giriş...' : 'Giriş Yap'}</button>
          </div>
        </form>
      </div>
    </div>
  )
}


