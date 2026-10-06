import { Component } from 'react';

/** Last-resort UI for render errors (keeps the rest of the app usable). */
export class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null };
  }

  static getDerivedStateFromError(error) {
    return { error };
  }

  componentDidCatch(error, info) {
    console.error('EDU-SMART render error', error, info);
  }

  render() {
    if (!this.state.error) return this.props.children;

    return (
      <div className="min-vh-100 d-grid p-4" style={{ placeItems: 'center' }}>
        <div className="glass-strong p-5 text-center" style={{ maxWidth: 520, borderRadius: 28 }}>
          <div className="fs-1 mb-2" style={{ color: 'var(--es-critical)' }}><i className="bi bi-bug" aria-hidden="true" /></div>
          <h2 className="fw-800">Something broke on this screen</h2>
          <p className="text-2">{this.state.error.message}</p>
          <button type="button" className="btn btn-primary" onClick={() => { this.setState({ error: null }); window.location.assign('/dashboard'); }}>
            Back to dashboard
          </button>
        </div>
      </div>
    );
  }
}
