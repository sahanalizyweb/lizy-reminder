import { api, tokenStore } from './api'

export const authService = {
  async login(email, password) {
    const { token, user } = await api.post('/login', { email, password })
    tokenStore.set(token)
    return user
  },

  async me() {
    const { user } = await api.get('/me')
    return user
  },

  async logout() {
    try {
      await api.post('/logout')
    } finally {
      tokenStore.clear()
    }
  },
}
