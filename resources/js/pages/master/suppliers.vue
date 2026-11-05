<script setup>
import { ref } from 'vue'
import axios from '@/plugins/axios'
import { toast, confirmDialog } from '@/plugins/sweetalert'
import SuppliersListPage from '@/pages/suppliers/list.vue'

const suppliersListRef = ref(null)
const isSyncingAll = ref(false)

const handleSyncFromAllTenants = async () => {
  const isConfirmed = await confirmDialog({
    title: '¿Consolidar Proveedores al Master?',
    text: 'Se extraerán e integrarán los proveedores y estructuras técnicas de Excel/conexiones de todos los tenants hacia tovaerp_master.',
    confirmButtonText: 'Sí, Sincronizar Todo',
    cancelButtonText: 'Cancelar',
  })

  if (!isConfirmed) return

  isSyncingAll.value = true
  try {
    const response = await axios.post('/master-suppliers/sync-from-all-tenants', {
      overwrite: true,
      propagate: false,
    })

    toast.success(response.data?.message || 'Catálogo maestro consolidado exitosamente.')
    if (suppliersListRef.value?.fetchSuppliers) {
      await suppliersListRef.value.fetchSuppliers()
    }
  } catch (error) {
    console.error('Error al sincronizar tenants al master:', error)
    toast.error(error.response?.data?.message || 'Ocurrió un error al consolidar el catálogo maestro.')
  } finally {
    isSyncingAll.value = false
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
          <strong>CATÁLOGO MAESTRO GLOBAL (tovaerp_master) — Directorio Oficial de Proveedores</strong>
          <span class="text-body-2 ms-2">
            Listado completo consolidado y ordenado por ID. Los cambios en estructuras de Excel y conexiones técnicas son propagados a los tenants.
          </span>
        </div>
        <div class="d-flex align-center gap-2">
          <v-btn
            color="primary"
            variant="elevated"
            size="small"
            prepend-icon="tabler-database-import"
            :loading="isSyncingAll"
            :disabled="isSyncingAll"
            @click="handleSyncFromAllTenants"
          >
            Sincronizar Tenants al Master
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

    <SuppliersListPage
      ref="suppliersListRef"
      :is-master="true"
    />
  </div>
</template>
