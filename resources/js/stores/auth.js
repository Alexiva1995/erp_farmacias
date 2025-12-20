import { buildAbilityForRules } from '@/plugins/ability.js';
import axios from '@/plugins/axios';
import { useAbility } from '@casl/vue';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  
  const ability = useAbility();
  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role_id === 1)
  const isSupervisor = computed(() => user.value?.role_id === 2)
  const isVendedor = computed(() => user.value?.role_id === 3)
  const isLoaded = ref(false);

  function updateAbility(userObject) {
    const newAbility = buildAbilityForRules(userObject);
    ability.update(newAbility);
    console.log('Reglas de CASL actualizadas para el usuario:', userObject);
  }

  async function fetchUser() {
    try {
      const response = await axios.get('/user')
      user.value = response.data
      updateAbility(user.value);
    } catch (error) {
      user.value = null;
      updateAbility(null);
      console.error('No se pudo obtener el usuario.', error)
    } finally {
        isLoaded.value = true;
    }
  }

  async function logout() {
    try {
      await axios.post('/logout')
      user.value = null
      updateAbility(null);
      window.location.href = '/login'
    } catch (error) {
      console.error('Error durante el logout:', error)
      user.value = null
      window.location.href = '/login'
    }
  }

  return { user, isAuthenticated, isAdmin, isVendedor, isSupervisor, fetchUser, logout, isLoaded }
})
