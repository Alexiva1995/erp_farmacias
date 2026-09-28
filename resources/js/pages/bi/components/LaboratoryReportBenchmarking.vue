<script setup>
import { useCurrencyConverter } from '@/components/useCurrencyConverter';

const props = defineProps({
  labA: {
    type: [Number, String, null],
    default: null
  },
  labB: {
    type: [Number, String, null],
    default: null
  },
  laboratories: {
    type: Array,
    default: () => []
  },
  benchmarkingData: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  },
  loadingBenchmarking: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits([
  'update:labA',
  'update:labB',
  'fetchBenchmarking'
]);

const { formatCurrency } = useCurrencyConverter();

const formatPercent = (val) => `${parseFloat(val || 0).toFixed(1)}%`;
</script>

<template>
  <VCard border class="mt-4 rounded-lg overflow-hidden">
    <VCardTitle class="pa-4 bg-primary text-white d-flex align-center">
      <VIcon icon="tabler-arrows-left-right" class="me-2" color="white" />
      <span class="text-subtitle-1 font-weight-bold">Comparativa Lado a Lado (Benchmarking)</span>
    </VCardTitle>

    <VCardText class="pa-4">
      <VRow align="center">
        <VCol cols="12" md="5">
          <VAutocomplete
            :model-value="labA"
            @update:model-value="emit('update:labA', $event); emit('fetchBenchmarking')"
            :items="laboratories"
            item-title="name"
            item-value="id"
            label="Laboratorio A"
            placeholder="Seleccionar Laboratorio A"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-flask"
            clearable
            :disabled="loading || loadingBenchmarking"
          />
        </VCol>

        <VCol cols="12" md="2" class="text-center">
          <VChip color="primary" variant="flat" class="font-weight-black my-1">VS</VChip>
        </VCol>

        <VCol cols="12" md="5">
          <VAutocomplete
            :model-value="labB"
            @update:model-value="emit('update:labB', $event); emit('fetchBenchmarking')"
            :items="laboratories"
            item-title="name"
            item-value="id"
            label="Laboratorio B"
            placeholder="Seleccionar Laboratorio B"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
            prepend-inner-icon="tabler-flask"
            clearable
            :disabled="loading || loadingBenchmarking"
          />
        </VCol>
      </VRow>

      <div v-if="loadingBenchmarking" class="pa-10">
        <VSkeletonLoader type="card" height="250" />
      </div>

      <VRow v-else-if="benchmarkingData.lab_a" class="mt-6 border-t pt-6">
        <!-- Detalle Lab A -->
        <VCol cols="12" md="6" class="border-e">
          <div class="px-2">
            <div class="d-flex align-center gap-4 mb-6">
              <VAvatar color="primary" variant="tonal" size="52">
                <VIcon icon="tabler-flask" size="28" />
              </VAvatar>
              <div class="overflow-hidden">
                <div class="text-subtitle-1 font-weight-black text-primary text-uppercase text-truncate">
                  {{ laboratories.find(l => l.id === labA)?.name }}
                </div>
                <div class="text-caption text-medium-emphasis">Rendimiento en el periodo</div>
              </div>
            </div>
            
            <div class="d-flex flex-column gap-3">
              <VCard variant="tonal" color="primary" class="pa-3 rounded-lg d-flex justify-space-between align-center">
                <span class="text-caption font-weight-bold">PARTICIPACIÓN (VS B)</span>
                <span class="text-h5 font-weight-black">{{ benchmarkingData.lab_a.share_relative }}%</span>
              </VCard>
              <div class="d-flex justify-space-between align-center pa-2 border-b">
                <span class="text-caption text-medium-emphasis">TICKET PROMEDIO</span>
                <span class="text-body-2 font-weight-bold">{{ formatCurrency(benchmarkingData.lab_a.details?.stats?.avg_ticket) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center pa-2 border-b">
                <span class="text-caption text-medium-emphasis">MARGEN ESTIMADO</span>
                <VChip size="small" color="success" variant="tonal" class="font-weight-bold">
                  {{ formatPercent(benchmarkingData.lab_a.details?.stats?.avg_margin_percent) }}
                </VChip>
              </div>
            </div>
          </div>
        </VCol>

        <!-- Detalle Lab B -->
        <VCol cols="12" md="6">
          <div class="px-2">
            <div class="d-flex align-center gap-4 mb-6 justify-end">
              <div class="text-right overflow-hidden">
                <div class="text-subtitle-1 font-weight-black text-success text-uppercase text-truncate">
                  {{ laboratories.find(l => l.id === labB)?.name }}
                </div>
                <div class="text-caption text-medium-emphasis">Rendimiento en el periodo</div>
              </div>
              <VAvatar color="success" variant="tonal" size="52">
                <VIcon icon="tabler-flask" size="28" />
              </VAvatar>
            </div>
            
            <div class="d-flex flex-column gap-3">
              <VCard variant="tonal" color="success" class="pa-3 rounded-lg d-flex justify-space-between align-center">
                <span class="text-caption font-weight-bold text-right">PARTICIPACIÓN (VS A)</span>
                <span class="text-h5 font-weight-black">{{ benchmarkingData.lab_b.share_relative }}%</span>
              </VCard>
              <div class="d-flex justify-space-between align-center pa-2 border-b">
                <span class="text-caption text-medium-emphasis">TICKET PROMEDIO</span>
                <span class="text-body-2 font-weight-bold">{{ formatCurrency(benchmarkingData.lab_b.details?.stats?.avg_ticket) }}</span>
              </div>
              <div class="d-flex justify-space-between align-center pa-2 border-b">
                <span class="text-caption text-medium-emphasis">MARGEN ESTIMADO</span>
                <VChip size="small" color="success" variant="tonal" class="font-weight-bold">
                  {{ formatPercent(benchmarkingData.lab_b.details?.stats?.avg_margin_percent) }}
                </VChip>
              </div>
            </div>
          </div>
        </VCol>

        <!-- Categorías compartidas (Head-to-Head) -->
        <VCol v-if="benchmarkingData.shared_groups.length" cols="12" class="mt-6 pt-4 border-t">
          <div class="text-subtitle-2 font-weight-black mb-4 d-flex align-center">
            <VIcon icon="tabler-swords" class="me-2 text-warning" />
            <span>Competencia Directa por Categoría</span>
          </div>
          
          <VRow>
            <VCol v-for="group in benchmarkingData.shared_groups" :key="group.group_id" cols="12" md="6">
              <VCard border class="pa-4 rounded-lg h-100 bg-surface">
                <div class="d-flex justify-space-between align-center mb-3">
                  <span class="text-caption font-weight-black text-uppercase">{{ group.name }}</span>
                </div>

                <!-- Barra Bicolor de Distribución -->
                <div class="d-flex w-100 rounded-pill overflow-hidden border bar-container">
                  <div 
                    class="d-flex align-center justify-center text-white font-weight-bold bar-item" 
                    :style="{ width: group.share_a + '%', backgroundColor: '#E20074' }"
                  >
                    <span v-if="group.share_a > 12">{{ group.share_a }}%</span>
                  </div>
                  <div 
                    class="d-flex align-center justify-center text-white font-weight-bold bar-item" 
                    :style="{ width: group.share_b + '%', backgroundColor: '#28C76F' }"
                  >
                    <span v-if="group.share_b > 12">{{ group.share_b }}%</span>
                  </div>
                </div>

                <!-- Detalles de Ingresos y Unidades -->
                <div class="d-flex justify-space-between mt-2 text-caption font-weight-bold">
                  <div class="d-flex flex-column">
                    <span class="text-primary">{{ formatCurrency(group.revenue_a) }}</span>
                    <span class="text-primary opacity-75">{{ group.units_a }} Unds</span>
                  </div>
                  <div class="d-flex flex-column text-right">
                    <span class="text-success">{{ formatCurrency(group.revenue_b) }}</span>
                    <span class="text-success opacity-75">{{ group.units_b }} Unds</span>
                  </div>
                </div>

                <!-- Listado de Productos Competidores -->
                <div class="d-flex flex-column flex-sm-row justify-space-between mt-4 gap-2">
                  <!-- Lab A Products -->
                  <div class="flex-grow-1 flex-shrink-1 overflow-hidden min-w-120">
                     <div 
                       v-for="p in group.products_a" 
                       :key="p.id" 
                       class="text-caption font-weight-medium text-uppercase text-truncate mb-1 d-flex justify-space-between gap-1 text-primary"
                     >
                        <span class="text-truncate flex-grow-1">{{ p.name }}</span>
                        <span class="font-weight-black opacity-80 ms-1">{{ p.units }}U</span>
                     </div>
                  </div>
                  <!-- Lab B Products -->
                  <div class="flex-grow-1 flex-shrink-1 text-right overflow-hidden min-w-120">
                     <div 
                       v-for="p in group.products_b" 
                       :key="p.id" 
                       class="text-caption font-weight-medium text-uppercase text-truncate text-success mb-1 d-flex justify-space-between gap-1"
                     >
                        <span class="font-weight-black opacity-80 me-1">{{ p.units }}U</span>
                        <span class="text-truncate flex-grow-1">{{ p.name }}</span>
                     </div>
                  </div>
                </div>
              </VCard>
            </VCol>
          </VRow>
        </VCol>
      </VRow>

      <VEmptyState
        v-else
        icon="tabler-arrows-left-right"
        title="Sin comparativa activa"
        text="Selecciona dos laboratorios en los desplegables superiores para comparar su rendimiento"
        class="py-8"
      />
    </VCardText>
  </VCard>
</template>

<style scoped>
.bar-container {
  height: 16px;
}

.bar-item {
  font-size: 9px;
  line-height: 1;
}

.min-w-120 {
  min-width: 120px;
}

.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }
</style>
