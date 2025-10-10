import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.js', 'resources/css/app.css'],
      refresh: true,          // harmless in prod builds; useful if you run `vite` in dev
      buildDirectory: 'build' // outputs to public/build (default), explicit for clarity
    }),
  ],
  // If you ever run the dev server inside Docker, uncomment:
  // server: { host: '0.0.0.0', port: 5173, strictPort: true }
})
