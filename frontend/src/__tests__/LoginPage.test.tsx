import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import LoginPage from '../pages/public/LoginPage'
import { ToastProvider } from '../components/ui/ToastProvider'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../lib/api', () => ({
  api: { post: vi.fn(async () => ({ data: { token: 't', apiKey: 'k' } })) }
}))

function wrapper(children: React.ReactNode) {
  return (
    <MemoryRouter>
      <ToastProvider>{children}</ToastProvider>
    </MemoryRouter>
  )
}

test('login form validates and submits', async () => {
  render(wrapper(<LoginPage />) as any)
  const email = screen.getByPlaceholderText('E-posta')
  const pass = screen.getByPlaceholderText('Şifre')
  const btn = screen.getByRole('button', { name: /giriş yap/i })
  await userEvent.type(email, 'user@example.com')
  await userEvent.type(pass, 'Password1!')
  await userEvent.click(btn)
  // Submit sonrası API çağrısı tetiklenmiş olmalı
  const { api } = await import('../lib/api')
  expect((api.post as any).mock.calls[0][0]).toBe('/auth/login')
})


