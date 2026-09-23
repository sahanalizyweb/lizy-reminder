import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// In development the React app calls /api and Vite forwards it to the Laravel
// server, so no CORS setup is needed. For a production build served from a
// different origin, set VITE_API_URL (see .env.example).
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: process.env.VITE_PROXY_TARGET || 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
