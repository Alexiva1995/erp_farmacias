import axios from '@/plugins/axios'
import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  
  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.is_admin ?? false)


  async function fetchUser() {
    try {
      const response = await axios.get('/user')
      user.value = response.data
    } catch (error) {
      user.value = null
      console.error('No se pudo obtener el usuario.', error)
    }
  }
  async function logout() {
    try {
      await axios.post('/logout')
      user.value = null
      window.location.href = '/login'
    } catch (error) {
      console.error('Error durante el logout:', error)
      user.value = null
      window.location.href = '/login'
    }
  }

  return { user, isAuthenticated, isAdmin, fetchUser, logout }
})
