import { forwardRef } from 'react';

/**
 * Common Platform Layer — the 6-digit code field used by email verification
 * and password reset. Accepts a pasted "123 456", keeps only digits, and shows
 * the digits spaced out so they are easy to compare with the email.
 */
export const AuthCodeField = forwardRef(function AuthCodeField(
  { id = 'auth-code', value, onChange, error, label = '6-digit code', hint, autoFocus = false },
  ref,
) {
  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={id}>{label}</label>
      <input
        id={id}
        ref={ref}
        type="text"
        inputMode="numeric"
        autoComplete="one-time-code"
        pattern="[0-9]*"
        maxLength={6}
        className={`form-control form-control-lg text-center fw-bold ${error ? 'is-invalid' : ''}`}
        style={{ letterSpacing: '0.6em', fontVariantNumeric: 'tabular-nums' }}
        placeholder="000000"
        value={value}
        onChange={(event) => onChange(event.target.value.replace(/\D/g, '').slice(0, 6))}
        required
        autoFocus={autoFocus}
      />
      {error ? <div className="invalid-feedback d-block">{error}</div> : hint && <div className="small text-3 mt-1">{hint}</div>}
    </div>
  );
});
