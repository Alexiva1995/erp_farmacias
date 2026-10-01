<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from '@/plugins/axios'

const router = useRouter()
const route = useRoute()
const showOfflineModal = ref(false)

// ─── 1. Detección por Eventos Nativos del DOM ────────────────────────────────
const handleOffline = () => {
  // No mostrar si ya estamos en la vista offline
  if (route.path !== '/tpv/offline') {
    showOfflineModal.value = true
  }
}

const handleOnline = () => {
  showOfflineModal.value = false
}

// ─── 2. Detección por Interceptor Global de Axios ────────────────────────────
const setupAxiosInterceptor = () => {
  axios.interceptors.response.use(
    (response) => response,
    (error) => {
      // Errores de conexión o timeout
      if (!error.response || error.code === 'ECONNABORTED' || error.message === 'Network Error') {
        if (route.path !== '/tpv/offline') {
          showOfflineModal.value = true
        }
      }
      return Promise.reject(error)
    }
  )
}

// ─── 3. Acciones del Modal ───────────────────────────────────────────────────
const goToContingency = () => {
  showOfflineModal.value = false
  router.push('/tpv/offline')
}

const ignoreWarning = () => {
  showOfflineModal.value = false
}

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(() => {
  window.addEventListener('offline', handleOffline)
  window.addEventListener('online', handleOnline)
  setupAxiosInterceptor()
})

onUnmounted(() => {
  window.removeEventListener('offline', handleOffline)
  window.removeEventListener('online', handleOnline)
})
</script>

<template>
  <v-dialog v-model="showOfflineModal" persistent max-width="500" style="z-index: 9999;">
    <v-card color="error" theme="dark">
      <v-card-title class="text-h5 pt-6 text-center font-weight-bold">
        <v-icon icon="mdi-wifi-off" size="50" class="mb-4 d-block mx-auto" />
        CONEXIÓN PERDIDA
      </v-card-title>
      
      <v-card-text class="text-center text-body-1 px-6">
        Se ha detectado una pérdida de conexión con el servidor central.
        <br><br>
        Las operaciones en línea (búsqueda en vivo, créditos, convenios, facturación fiscal online) están detenidas.
      </v-card-text>
      
      <v-card-actions class="justify-center pb-6 d-flex flex-column gap-3">
        <v-btn 
          color="white" 
          variant="elevated" 
          size="large"
          class="font-weight-bold w-75 mx-auto"
          @click="goToContingency"
        >
          Ir al TPV Offline
        </v-btn>
        
        <v-btn 
          color="white" 
          variant="text" 
          size="small"
          class="w-75 mx-auto mt-2 opacity-80"
          @click="ignoreWarning"
        >
          Cerrar alerta y esperar conexión
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
