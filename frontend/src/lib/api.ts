import axios from 'axios'

const BASE_URL = (import.meta as any).env?.VITE_API_BASE || 'http://127.0.0.1:9090/api/v1'
export const api = axios.create({ baseURL: BASE_URL })

api.interceptors.request.use((config) => {
	const key = localStorage.getItem('apiKey') || ''
	if (key) {
		config.headers = config.headers ?? {}
		;(config.headers as any)['X-Api-Key'] = key
	}
	const userToken = localStorage.getItem('userToken') || ''
	if (userToken) {
		config.headers = config.headers ?? {}
		;(config.headers as any)['Authorization'] = `Bearer ${userToken}`
	}
	return config
})

api.interceptors.response.use(
	(res) => res,
	(err) => {
		const msg = err?.response?.data?.message || ''
		if (msg === 'insufficient_customer_credits') {
			// UI tarafı dinlesin: paket modalını açmak için event yayınla
			window.dispatchEvent(new CustomEvent('credits:insufficient'))
		}
		return Promise.reject(err)
	}
)


