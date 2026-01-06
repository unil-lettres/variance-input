import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import { config as loadEnv } from 'dotenv'

loadEnv()

function resolveBasePath() {
  const raw = process.env.ADMIN_BASE_PATH || ''
  const trimmed = raw.trim()

  if (trimmed === '' || trimmed === '/') {
    return '/'
  }

  const withoutSlashes = trimmed.replace(/^\/|\/$/g, '')
  return `/${withoutSlashes}/`
}

export default defineConfig(({ mode }) => ({
  base: resolveBasePath(),
  build: {
    sourcemap: mode === 'development',
  },
  plugins: [
    laravel({
      input: [
        'resources/js/app.js',
        'resources/css/app.css',
        'resources/js/editor.js',
        'resources/js/editor-comparison.js',
      ],
      refresh: true,          // harmless in prod builds; useful if you run `vite` in dev
      buildDirectory: 'build' // outputs to public/build (default), explicit for clarity
    }),
  ],
  // If you ever run the dev server inside Docker, uncomment:
  // server: { host: '0.0.0.0', port: 5173, strictPort: true }
}))
