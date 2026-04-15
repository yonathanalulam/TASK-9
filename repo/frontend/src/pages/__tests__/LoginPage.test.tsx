import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import LoginPage from '../LoginPage';

// Mock the auth hook — we test LoginPage UI behavior, not the hook internals
const mockLogin = vi.fn();
vi.mock('@/hooks/useAuth', () => ({
  useAuth: () => ({
    login: mockLogin,
    user: null,
    isAuthenticated: false,
    logout: vi.fn(),
  }),
}));

function renderLoginPage() {
  return render(
    <MemoryRouter>
      <LoginPage />
    </MemoryRouter>,
  );
}

describe('LoginPage — form behavior', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders username and password fields', () => {
    renderLoginPage();
    expect(screen.getByLabelText(/username/i)).toBeDefined();
    expect(screen.getByLabelText(/password/i)).toBeDefined();
  });

  it('renders a submit button with correct label', () => {
    renderLoginPage();
    const btn = screen.getByRole('button', { name: /sign in/i });
    expect(btn).toBeDefined();
  });

  it('shows validation error when submitting with empty username', async () => {
    renderLoginPage();
    const submitBtn = screen.getByRole('button', { name: /sign in/i });

    // Submit without filling any fields
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText(/username is required/i)).toBeDefined();
    });

    // login hook must NOT be called for invalid form
    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('shows validation error when submitting with empty password', async () => {
    renderLoginPage();

    const usernameInput = screen.getByLabelText(/username/i);
    fireEvent.change(usernameInput, { target: { value: 'admin' } });

    const submitBtn = screen.getByRole('button', { name: /sign in/i });
    fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(screen.getByText(/password is required/i)).toBeDefined();
    });

    expect(mockLogin).not.toHaveBeenCalled();
  });

  it('calls login with username and password when form is valid', async () => {
    mockLogin.mockResolvedValue(undefined);
    renderLoginPage();

    fireEvent.change(screen.getByLabelText(/username/i), { target: { value: 'admin' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'Demo#Password1!' } });
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(mockLogin).toHaveBeenCalledWith('admin', 'Demo#Password1!');
    });
  });

  it('shows server error message when login rejects', async () => {
    mockLogin.mockRejectedValue(new Error('Bad credentials'));
    renderLoginPage();

    fireEvent.change(screen.getByLabelText(/username/i), { target: { value: 'admin' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'wrong' } });
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      // Server error state should render an error message
      const errorEl = screen.getByText(/login failed|check your credentials/i);
      expect(errorEl).toBeDefined();
    });
  });

  it('shows server error from API response when available', async () => {
    mockLogin.mockRejectedValue({
      response: { data: { error: 'AUTHENTICATION_FAILED' } },
    });
    renderLoginPage();

    fireEvent.change(screen.getByLabelText(/username/i), { target: { value: 'admin' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'wrong' } });
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByText(/AUTHENTICATION_FAILED/i)).toBeDefined();
    });
  });

  it('disables submit button while login is in progress', async () => {
    // Login that never resolves (to test loading state)
    let resolveLogin!: () => void;
    mockLogin.mockReturnValue(new Promise<void>((resolve) => { resolveLogin = resolve; }));
    renderLoginPage();

    fireEvent.change(screen.getByLabelText(/username/i), { target: { value: 'admin' } });
    fireEvent.change(screen.getByLabelText(/password/i), { target: { value: 'pass' } });
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /signing in/i });
      expect((btn as HTMLButtonElement).disabled).toBe(true);
    });

    resolveLogin();
  });

  it('has correct input types for security (password is type=password)', () => {
    renderLoginPage();
    const passwordInput = screen.getByLabelText(/password/i) as HTMLInputElement;
    expect(passwordInput.type).toBe('password');
  });
});
