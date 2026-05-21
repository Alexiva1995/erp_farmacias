<script setup lang="ts">
import axios from '@/plugins/axios'
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

interface Cycle {
  cycle_id: number
  start_date: string
  end_date: string | null
  cycle_status: 'active' | 'closed' | 'cancelled'
  total_products: number
  total_surplus: number
  total_shortage: number
  net_total: number
}

// ── Estado ────
const cycles = ref<Cycle[]>([])
const loading = ref(true)
const router = useRouter()

// ── Cargar Ciclos de Inventario ────
const fetchCycles = async () => {
  try {
    loading.value = true
    const response = await axios.get('/inventory/cycle/summary', {
      params: {
        page: 1,
        itemsPerPage: 5,
        sortBy: 'cycle_id',
        orderBy: 'desc',
      },
    })
    
    if (response.data && response.data.data) {
      cycles.value = response.data.data || []
    }
  } catch (error) {
    console.error('Error al cargar ciclos de inventario:', error)
  } finally {
    loading.value = false
  }
}

// ── Resoluciones de Estado ────
const getStatusColor = (status: string) => {
  if (status === 'active') return 'success'
  if (status === 'closed') return 'info'
  if (status === 'cancelled') return 'error'
  return 'secondary'
}

const getStatusText = (status: string) => {
  if (status === 'active') return 'Activo'
  if (status === 'closed') return 'Cerrado'
  if (status === 'cancelled') return 'Cancelado'
  return 'Desconocido'
}

// ── Formateadores ────
const formatPrice = (val: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val)
}

const formatDate = (dateStr: string | null) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('es-ES', {
    day: 'numeric',
    month: 'short',
  }).format(date)
}

// Navegación al detalle del ciclo
const viewCycleDetails = (cycleId: number) => {
  router.push(`/cyclics/details?id=${cycleId}`)
}

onMounted(() => {
  fetchCycles()
})
</script>

<template>
  <VCard title="Últimos Ciclos de Inventario">
    <template #append>
      <div class="mt-n4 me-n2">
        <VBtn
          icon
          variant="text"
          size="small"
          color="default"
          @click="fetchCycles"
        >
          <VIcon icon="tabler-refresh" />
        </VBtn>
      </div>
    </template>

    <VCardText class="position-relative pa-0" style="min-height: 250px;">
      <!-- Loading State -->
      <div v-if="loading" class="d-flex justify-center align-center position-absolute w-100 h-100 top-0 left-0" style="z-index: 2; background: rgba(var(--v-theme-surface), 0.7);">
        <VProgressCircular indeterminate color="primary" />
      </div>

      <!-- Empty State -->
      <div v-else-if="cycles.length === 0" class="d-flex flex-column align-center justify-center py-8 text-center px-4">
        <VAvatar color="secondary" variant="tonal" size="48" class="mb-2">
          <VIcon icon="tabler-clipboard-off" size="24" />
        </VAvatar>
        <span class="text-high-emphasis font-weight-bold text-body-1 mb-1">Sin Registros</span>
        <span class="text-medium-emphasis text-body-2">No se han iniciado conteos de inventario</span>
      </div>

      <!-- Cycles Table -->
      <div v-else class="table-responsive">
        <VTable class="text-no-wrap transaction-table">
          <thead>
            <tr>
              <th class="ps-6">CICLO</th>
              <th>FECHAS</th>
              <th>ESTADO</th>
              <th class="pe-6 text-end">BALANCE NETO</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="cycle in cycles"
              :key="cycle.cycle_id"
              class="cycle-row cursor-pointer"
              @click="viewCycleDetails(cycle.cycle_id)"
            >
              <!-- Ciclo ID -->
              <td class="ps-6 py-3">
                <div class="d-flex align-center">
                  <VAvatar
                    size="36"
                    color="primary"
                    variant="tonal"
                    class="me-3"
                  >
                    <VIcon icon="tabler-archive" size="20" />
                  </VAvatar>
                  <div>
                    <span class="font-weight-black text-primary text-body-1">
                      #{{ cycle.cycle_id }}
                    </span>
                    <p class="text-caption text-medium-emphasis mb-0">
                      {{ cycle.total_products }} productos
                    </p>
                  </div>
                </div>
              </td>

              <!-- Fechas -->
              <td class="py-3">
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-semibold text-high-emphasis">
                    {{ formatDate(cycle.start_date) }} - {{ formatDate(cycle.end_date) }}
                  </span>
                  <span class="text-caption text-medium-emphasis">Periodo</span>
                </div>
              </td>

              <!-- Estado -->
              <td class="py-3">
                <VChip
                  label
                  :color="getStatusColor(cycle.cycle_status)"
                  size="small"
                  class="font-weight-bold text-uppercase"
                >
                  {{ getStatusText(cycle.cycle_status) }}
                </VChip>
              </td>

              <!-- Balance Neto -->
              <td class="pe-6 py-3 text-end">
                <span
                  class="font-weight-black text-body-1"
                  :class="cycle.net_total >= 0 ? 'text-success' : 'text-error'"
                >
                  {{ formatPrice(cycle.net_total) }}
                </span>
                <p class="text-caption text-medium-emphasis mb-0">
                  {{ cycle.net_total >= 0 ? 'Sobrante' : 'Faltante' }}
                </p>
              </td>
            </tr>
          </tbody>
        </VTable>
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.transaction-table {
  &.v-table .v-table__wrapper > table > tbody > tr:not(:last-child) > td {
    border-block-end: 1px solid rgba(var(--v-border-color), 0.1) !important;
  }
}

.cycle-row {
  transition: transform 0.2s ease, background-color 0.2s ease;
  
  &:hover {
    background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
    transform: translateY(-1px);
  }
}
</style>
