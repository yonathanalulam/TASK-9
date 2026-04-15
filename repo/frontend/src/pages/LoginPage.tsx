import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuth } from '@/hooks/useAuth';

const schema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
});

type FormData = z.infer<typeof schema>;

export default function LoginPage() {
  const { login } = useAuth();
  const [serverError, setServerError] = useState('');

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({ resolver: zodResolver(schema) });

  const onSubmit = async (data: FormData) => {
    setServerError('');
    try {
      await login(data.username, data.password);
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { error?: string } } })?.response?.data?.error ||
        'Login failed. Please check your credentials.';
      setServerError(msg);
    }
  };

  return (
    <div
      style={{
        minHeight: '100vh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'var(--color-bg)',
      }}
    >
      <div className="card" style={{ width: 380 }}>
        <h1 style={{ fontSize: 22, fontWeight: 700, marginBottom: 4 }}>Meridian</h1>
        <p style={{ color: 'var(--color-text-muted)', marginBottom: 24, fontSize: 13 }}>
          Sign in to your account
        </p>

        {serverError && (
          <div
            style={{
              background: '#fee2e2',
              color: '#991b1b',
              padding: '10px 14px',
              borderRadius: 'var(--radius)',
              marginBottom: 16,
              fontSize: 13,
            }}
          >
            {serverError}
          </div>
        )}

        <form onSubmit={handleSubmit(onSubmit)}>
          <div className="form-group">
            <label htmlFor="username">Username</label>
            <input id="username" type="text" autoFocus {...register('username')} />
            {errors.username && <div className="form-error">{errors.username.message}</div>}
          </div>

          <div className="form-group">
            <label htmlFor="password">Password</label>
            <input id="password" type="password" {...register('password')} />
            {errors.password && <div className="form-error">{errors.password.message}</div>}
          </div>

          <button
            type="submit"
            className="btn btn-primary"
            disabled={isSubmitting}
            style={{ width: '100%', justifyContent: 'center', marginTop: 8 }}
          >
            {isSubmitting ? 'Signing in...' : 'Sign in'}
          </button>
        </form>
      </div>
    </div>
  );
}
