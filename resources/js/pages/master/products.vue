<script setup>
import { ref } from 'vue'
import axios from 'axios'
import InventoryProductsPage from '@/pages/inventory/products/index.vue'

const syncing = ref(false)
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const syncFarmatodoImages = async () => {
  if (syncing.value) return
  syncing.value = true

  try {
    const { data } = await axios.post('/api/master-catalog/sync-farmatodo-images')
    const stats = data?.data || {}
    snackbar.value = {
      show: true,
      color: stats.aborted ? 'warning' : 'success',
      text: `${data?.message || 'Completado'} — Procesados: ${stats.processed ?? 0}, Actualizados: ${stats.updated ?? 0}, No encontrados: ${stats.not_found ?? 0}`,
    }
  } catch (error) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: error?.response?.data?.message || 'Error al sincronizar imágenes.',
    }
  } finally {
    syncing.value = false
  }
}
</script>

<template>
  <div>
    <v-alert
      type="info"
      variant="tonal"
      color="primary"
      density="compact"
      class="mb-4"
      border="start"
    >
      <div class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div>
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Inventario</strong>
          <span class="text-body-2 ms-2">
            Los productos creados o modificados aquí actualizan el diccionario oficial de todas las farmacias.
          </span>
        </div>
        <div class="d-flex align-center gap-2">
          <v-btn
            color="primary"
            variant="outlined"
            size="small"
            prepend-icon="tabler-photo-down"
            :loading="syncing"
            :disabled="syncing"
            @click="syncFarmatodoImages"
          >
            Sincronizar Imágenes (Lote 20)
          </v-btn>
          <v-chip
            color="primary"
            size="small"
            variant="flat"
          >
            MASTER
          </v-chip>
        </div>
      </div>
    </v-alert>

    <InventoryProductsPage :is-master="true" />

    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="5000"
      location="bottom end"
    >
      {{ snackbar.text }}
    </v-snackbar>
  </div>
</template>
