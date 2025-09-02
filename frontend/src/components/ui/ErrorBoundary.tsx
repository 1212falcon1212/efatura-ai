import * as React from 'react'

type Props = { children: React.ReactNode }

type State = { hasError: boolean; error?: any }

export default class ErrorBoundary extends React.Component<Props, State> {
  constructor(props: Props) {
    super(props)
    this.state = { hasError: false }
  }
  static getDerivedStateFromError(error: any) {
    return { hasError: true, error }
  }
  componentDidCatch(error: any, info: any) {
    // could log to service
    console.error('App error:', error, info)
  }
  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: 24 }}>
          <h2>Bir hata oluştu</h2>
          <div style={{ color: '#64748b', marginTop: 8 }}>Sayfayı yenilemeyi deneyin.</div>
          <div style={{ marginTop: 16 }}>
            <button onClick={() => window.location.reload()}>Yenile</button>
          </div>
        </div>
      )
    }
    return this.props.children
  }
}
