<script setup>
import { ref, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'order-selected'])

const loading = ref(false)
const processingTableId = ref(null)
const zones = ref([])

const fetchFloorOverview = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/dining-floor/overview')
    zones.value = data.zones || data.data || []
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al cargar el salón y mesas.')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) fetchFloorOverview()
  }
)

const getStatusColor = (status) => {
  if (status === 'available') return 'success'
  if (status === 'occupied') return 'error'
  if (status === 'pre_bill') return 'warning'
  return 'secondary'
}

const getStatusLabel = (status) => {
  if (status === 'available') return 'Disponible'
  if (status === 'occupied') return 'Ocupada'
  if (status === 'pre_bill') return 'Pre-cuenta'
  if (status === 'reserved') return 'Reservada'
  return status || 'Mesa'
}

const handleSelectTable = async (table) => {
  processingTableId.value = table.id
  try {
    if (table.current_order_id) {
      await axios.post(`/tpv/orders/${table.current_order_id}/activate`)
      toast.success(`Mesa ${table.name} cargada en el TPV.`)
    } else {
      await axios.post(`/dining-floor/tables/${table.id}/open-order`, {
        guests_count: table.capacity || 2,
      })
      toast.success(`Orden abierta en ${table.name}.`)
    }
    emit('order-selected')
    emit('update:modelValue', false)
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo abrir o cargar la mesa.')
  } finally {
    processingTableId.value = null
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="780"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg">
      <VCardTitle class="d-flex align-center justify-space-between pa-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-armchair" />
          <span class="text-h6 font-weight-bold text-white">Salón y Mesas</span>
        </div>
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          size="small"
          @click="emit('update:modelValue', false)"
        />
      </VCardTitle>

      <VCardText class="pa-4" style="max-height: 70vh; overflow-y: auto;">
        <div v-if="loading" class="py-8 d-flex justify-center">
          <VProgressCircular indeterminate color="primary" />
        </div>

        <div
          v-else-if="zones.length === 0"
          class="py-8 d-flex flex-column align-center justify-center text-center"
        >
          <VIcon icon="tabler-layout-grid-remove" size="40" color="secondary" class="mb-2" />
          <p class="text-body-2 text-medium-emphasis mb-0">
            No hay zonas o mesas configuradas en el módulo de Salón.
          </p>
        </div>

        <div v-else v-for="zone in zones" :key="zone.id" class="mb-5">
          <h4 class="text-subtitle-1 font-weight-bold mb-2 d-flex align-center gap-2">
            <VIcon icon="tabler-map-pin" size="18" color="primary" />
            {{ zone.name }}
          </h4>
          <VRow dense>
            <VCol
              v-for="table in zone.tables || []"
              :key="table.id"
              cols="6"
              sm="4"
              md="3"
            >
              <VCard
                variant="outlined"
                :color="getStatusColor(table.status)"
                class="pa-3 cursor-pointer rounded-lg text-center"
                :loading="processingTableId === table.id"
                @click="handleSelectTable(table)"
              >
                <div class="text-subtitle-2 font-weight-black">{{ table.name }}</div>
                <div class="text-caption text-medium-emphasis mb-1">
                  Capacidad: {{ table.capacity || 2 }}
                </div>
                <VChip size="x-small" :color="getStatusColor(table.status)" variant="tonal">
                  {{ getStatusLabel(table.status) }}
                </VChip>
              </VCard>
            </VCol>
          </VRow>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
