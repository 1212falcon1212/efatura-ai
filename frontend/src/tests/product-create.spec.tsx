import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import ProductCreatePage from '../pages/ProductCreatePage'

vi.mock('../lib/api', () => ({
  api: {
    post: vi.fn().mockResolvedValue({ data: { id: 1 } }),
  }
}))

function wrapper(children: React.ReactNode) {
  const qc = new QueryClient()
  return (
    <QueryClientProvider client={qc}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
}

describe('ProductCreatePage', () => {
  it('validasyon hatası gösterir ve başarılı kayıtta gönderir', async () => {
    render(wrapper(<ProductCreatePage />))

    // Önce kaydetmeye çalış (hata beklenir)
    fireEvent.click(screen.getByText('Kaydet'))
    expect(await screen.findByText(/Ad zorunlu/i)).toBeTruthy()

    // Formu doldur
    fireEvent.change(screen.getByLabelText('Ad'), { target: { value: 'Deneme Ürün' } })
    fireEvent.change(screen.getByLabelText('Ürün / Stok Kodu (SKU)'), { target: { value: 'SKU-1' } })
    fireEvent.change(screen.getByLabelText('KDV (%)'), { target: { value: '20' } })

    fireEvent.click(screen.getAllByText('Kaydet')[0])

    await waitFor(() => {
      const { api } = require('../lib/api')
      expect(api.post).toHaveBeenCalled()
    })
  })
})


