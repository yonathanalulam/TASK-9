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
      // Enforced thresholds — run_tests.sh runs `npx vitest run --coverage` which fails below these.
      provider: 'v8',
      reporter: ['text', 'lcov'],
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/test/**',
        'src/**/*.d.ts',
        'src/**/*.config.*',
        '**/node_modules/**',
        '**/e2e/**',
      ],
      thresholds: {
        lines: 35,
        functions: 30,
        branches: 25,
        statements: 35,
      },
    },
  },
});
