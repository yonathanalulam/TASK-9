import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    // Exclude Playwright E2E specs — those run via playwright test, not vitest
    exclude: ['**/node_modules/**', '**/e2e/**', '**/dist/**'],
    coverage: {
      // Coverage driver: @vitest/coverage-v8 (in devDependencies)
      provider: 'v8',
      reporter: ['text', 'lcov'],
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/test/**',
        'src/**/*.d.ts',
        'src/**/*.config.*',
        '**/node_modules/**',
        '**/e2e/**',
        // IndexedDB / offline mutation queue — not testable in jsdom
        'src/services/mutationQueue/db.ts',
        'src/services/mutationQueue/MutationQueue.ts',
        'src/services/mutationQueue/useMutationQueue.ts',
        'src/services/mutationQueue/index.ts',
        // Pure type definitions
        'src/api/types.ts',
        // App root and main entry (router/provider wiring only)
        'src/App.tsx',
        'src/main.tsx',
      ],
    },
  },
});
