<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import axios from '@/plugins/axios'
import OfflineOrder from '@/pages/tpv/offlineOrder.vue'

const router = useRouter()
const route = useRoute()
const showOfflineModal = ref(false)
const isFullscreenOffline = ref(false)

// ─── 1. Detección por Eventos Nativos del DOM ────────────────────────────────
const handleOffline = () => {
  if (route.path !== '/tpv/offlineOrder' && !isFullscreenOffline.value) {
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
      if (!error.response || error.code === 'ECONNABORTED' || error.message === 'Network Error') {
        if (route.path !== '/tpv/offlineOrder' && !isFullscreenOffline.value) {
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
  // Al estar empaquetado directamente en memoria, se abre instantáneamente a 0ms sin peticiones de red
  isFullscreenOffline.value = true
}

const ignoreWarning = () => {
  showOfflineModal.value = false
}

const closeOfflineView = () => {
  isFullscreenOffline.value = false
}

import { syncCatalogInBackground } from '@/composables/useOfflineCatalogSync'

let backgroundSyncInterval = null

// ─── Ciclo de Vida ───────────────────────────────────────────────────────────
onMounted(() => {
  window.addEventListener('offline', handleOffline)
  window.addEventListener('online', handleOnline)
  setupAxiosInterceptor()

  // Sincronización automática silenciosa del catálogo al iniciar la app
  if (navigator.onLine) {
    syncCatalogInBackground()
  }

  // Repetir sincronización silenciosa cada 15 minutos mientras haya conexión
  backgroundSyncInterval = setInterval(() => {
    if (navigator.onLine) {
      syncCatalogInBackground()
    }
  }, 15 * 60 * 1000)
})

onUnmounted(() => {
  window.removeEventListener('offline', handleOffline)
  window.removeEventListener('online', handleOnline)
  if (backgroundSyncInterval) {
    clearInterval(backgroundSyncInterval)
  }
})
</script>

<template>
  <div>
    <!-- Modal de Advertencia de Desconexión -->
    <VDialog
      v-model="showOfflineModal"
      persistent
      max-width="520"
      style="z-index: 9999;"
    >
      <VCard class="pa-2 rounded-lg text-center" elevation="10">
        <VCardItem class="justify-center pb-2 pt-6">
          <VAvatar
            color="error"
            variant="tonal"
            size="72"
            class="mb-3"
          >
            <VIcon
              icon="tabler-wifi-off"
              size="40"
              color="error"
            />
          </VAvatar>
          <VCardTitle class="text-h5 font-weight-bold text-high-emphasis">
            Conexión al Servidor Perdida
          </VCardTitle>
        </VCardItem>

        <VCardText class="px-6 py-2 text-body-1 text-medium-emphasis">
          Se ha detectado una pérdida de conexión con el servidor central.
          <br><br>
          Las operaciones en línea han sido pausadas. Puedes continuar vendiendo en modo contingencia con tu catálogo local en caché.
        </VCardText>

        <VCardActions class="pa-6 pt-4 d-flex flex-column gap-3">
          <VBtn
            color="error"
            variant="flat"
            size="large"
            block
            class="font-weight-bold"
            prepend-icon="tabler-shopping-cart"
            @click="goToContingency"
          >
            Abrir TPV Offline (Contingencia)
          </VBtn>

          <VBtn
            color="secondary"
            variant="text"
            size="default"
            block
            @click="ignoreWarning"
          >
            Cerrar alerta y esperar reconexión
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Vista de Contingencia en Pantalla Completa (Embebida en memoria sin requerir red) -->
    <VDialog
      v-model="isFullscreenOffline"
      fullscreen
      transition="dialog-bottom-transition"
      style="z-index: 10000;"
    >
      <VCard class="d-flex flex-column h-100 bg-background">
        <!-- Barra Superior de Contingencia -->
        <VToolbar color="warning" density="compact" elevation="2">
          <VIcon icon="tabler-wifi-off" class="ms-4 me-2" />
          <VToolbarTitle class="font-weight-bold text-body-1">
            MODO CONTINGENCIA OFFLINE — ERP FARMACIAS
          </VToolbarTitle>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="text"
            color="white"
            class="me-2"
            @click="closeOfflineView"
          />
        </VToolbar>

        <!-- Componente TPV Offline (Cargado en memoria) -->
        <div class="flex-grow-1 overflow-y-auto pa-4">
          <OfflineOrder @synced="closeOfflineView" />
        </div>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.gap-3 {
  gap: 12px;
}
</style>
