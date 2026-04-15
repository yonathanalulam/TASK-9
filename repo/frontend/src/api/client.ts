import axios from 'axios';
import type { AxiosResponse } from 'axios';

const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8080/api/v1',
  headers: {
    'Content-Type': 'application/json',
  },
});

/* ------------------------------------------------------------------ */
/*  Request interceptor: inject Bearer token                          */
/* ------------------------------------------------------------------ */
apiClient.interceptors.request.use((config) => {
  const raw = localStorage.getItem('meridian-auth');
  if (raw) {
    try {
      const persisted = JSON.parse(raw);
      const token: string | null = persisted?.state?.token ?? null;
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
    } catch {
      // corrupt storage — ignore
    }
  }
  return config;
});

/* ------------------------------------------------------------------ */
/*  Response interceptor: unwrap one level (.data), handle 401        */
/*                                                                    */
/*  Axios wraps every HTTP response in its own { data, status, ... }  */
/*  object.  The backend envelope lives at response.data.             */
/*  We return response.data (the FULL envelope { data, meta, error }) */
/*  so callers can access meta.pagination when needed.                */
/* ------------------------------------------------------------------ */
apiClient.interceptors.response.use(
  (response: AxiosResponse) => {
    // Unwrap the axios wrapper: return the backend envelope directly.
    // This means callers get { data, meta, error } from the resolved promise.
    response.data = response.data;
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('meridian-auth');
      if (window.location.pathname !== '/login') {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  },
);

export default apiClient;
