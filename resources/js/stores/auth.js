import { buildAbilityForRules } from '@/plugins/ability.js';
import axios from '@/plugins/axios';
import { useAbility } from '@casl/vue';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  
  const ability = useAbility();
  const isAuthenticated = computed(() => !!user.value)
  const isAdmin = computed(() => user.value?.role_id === 1 || user.value?.role_id === 4 || user.value?.role?.name?.toLowerCase() === 'admin' || user.value?.role?.name?.toLowerCase() === 'administrador')
  const isSupervisor = computed(() => user.value?.role_id === 2 || user.value?.role?.name?.toLowerCase() === 'supervisor')
  const isVendedor = computed(() => user.value?.role_id === 3 || user.value?.role?.name?.toLowerCase() === 'employee' || user.value?.role?.name?.toLowerCase() === 'empleado')
  const isContador = computed(() => user.value?.role_id === 5 || user.value?.role?.name?.toLowerCase() === 'contador')
  const isAsesorComercial = computed(() => user.value?.role_id === 6 || user.value?.role?.name?.toLowerCase() === 'asesor comercial')
  const isTelemarketing = computed(() => user.value?.role_id === 7 || user.value?.role?.name?.toLowerCase() === 'telemarketing')
  const isCobranzas = computed(() => user.value?.role_id === 8 || user.value?.role?.name?.toLowerCase() === 'cobranzas y crédito')
  const isAlmacenDespacho = computed(() => user.value?.role_id === 9 || user.value?.role?.name?.toLowerCase() === 'almacén y despacho')
  const isLoaded = ref(false);

  /**
   * Ruta de aterrizaje por defecto según el rol y permisos configurados.
   */
  const defaultHomeRoute = computed(() => {
    if (isContador.value) {
      return '/fiscal/home'
    }
    if (isAsesorComercial.value) {
      return '/wholesale/portfolio'
    }
    if (isTelemarketing.value) {
      return '/wholesale/telemarketing/workstation'
    }
    if (isCobranzas.value) {
      return '/wholesale/collections'
    }
    if (isAlmacenDespacho.value) {
      return '/wholesale/wms/dispatch-board'
    }
    if (isAdmin.value) {
      return '/dashboard'
    }
    return '/tpv/orderUser'
  })

  function updateAbility(userObject) {
    const newAbility = buildAbilityForRules(userObject);
    ability.update(newAbility);
    console.log('Reglas de CASL actualizadas para el usuario:', userObject);
  }

  async function fetchUser() {
    let retries = 3;
    while (retries >= 0) {
      try {
        const response = await axios.get('/user');
        user.value = response.data;
        updateAbility(user.value);
        break;
      } catch (error) {
        if (error.response?.status === 401 || error.response?.status === 419) {
          user.value = null;
          updateAbility(null);
          break; // Confirmado no autenticado
        } else if (retries > 0) {
          console.warn(`Error al obtener usuario. Reintentos restantes: ${retries}. (Posible bloqueo por múltiples pestañas)`, error);
          await new Promise(resolve => setTimeout(resolve, 1500));
          retries--;
        } else {
          console.error('Fallo definitivo al obtener el usuario.', error);
          break; // Fin de reintentos
        }
      }
    }
    isLoaded.value = true;
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

  return { user, isAuthenticated, isAdmin, isVendedor, isSupervisor, isContador, defaultHomeRoute, fetchUser, logout, isLoaded }
})
