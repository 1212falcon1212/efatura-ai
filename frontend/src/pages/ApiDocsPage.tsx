import PageHeader from '../components/ui/PageHeader'

export default function ApiDocsPage() {
  const url = '/api/v1/docs/openapi.yaml'
  return (
    <div>
      <PageHeader title="API Dokümanı" subtitle="OpenAPI" crumbs={[{ label: 'Panel', href: '/app' }, { label: 'API Docs' }]} />
      <div className="card" style={{ padding: 12, marginBottom: 12 }}>
        <div style={{ marginBottom: 8 }}>OpenAPI dosyasını görüntülemek için aşağıdaki bağlantıyı kullanın veya Redocly/Swagger ile açın.</div>
        <div style={{ display: 'flex', gap: 8 }}>
          <a className="btn-secondary" href={url} target="_blank" rel="noreferrer">OpenAPI (YAML)</a>
          <a className="btn-secondary" href="/api/v1/docs/redoc" target="_blank" rel="noreferrer">Redoc ile Aç</a>
          <a className="btn-secondary" href={`https://petstore.swagger.io/?url=${encodeURIComponent(window.location.origin + url)}`} target="_blank" rel="noreferrer">Swagger ile Aç</a>
        </div>
      </div>
      <div className="card" style={{ padding: 12 }}>
        <iframe title="OpenAPI" src="/api/v1/docs/redoc" style={{ width: '100%', height: '70vh', border: 0 }} />
      </div>
    </div>
  )
}


