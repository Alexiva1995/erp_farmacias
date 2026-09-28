<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  substitutions: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['view-detail'])

const search = ref('')
const selectedRow = ref(null)
const isDetailDialogOpen = ref(false)

const headers = [
  { title: 'CATEGORÍA', key: 'category', sortable: true, width: '130px' },
  { title: 'PRODUCTO A (FALTANTE)', key: 'product_a', sortable: true, minWidth: '180px' },
  { title: 'CANT.', key: 'discrepancy_a', align: 'center', sortable: true, width: '80px' },
  { title: '', key: 'action', align: 'center', sortable: false, width: '40px' },
  { title: 'PRODUCTO B (SOBRANTE)', key: 'product_b', sortable: true, minWidth: '180px' },
  { title: 'CANT.', key: 'discrepancy_b', align: 'center', sortable: true, width: '80px' },
  { title: 'CONFIANZA', key: 'confidence', align: 'center', sortable: true, width: '130px' },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '80px' },
]

const filteredSubstitutions = computed(() => {
  if (!search.value) return props.substitutions
  const q = search.value.toLowerCase()
  return props.substitutions.filter(item => 
    (item.category && item.category.toLowerCase().includes(q)) ||
    (item.product_a && item.product_a.toLowerCase().includes(q)) ||
    (item.product_b && item.product_b.toLowerCase().includes(q))
  )
})

const handleOpenDetail = item => {
  selectedRow.value = item
  isDetailDialogOpen.value = true
  emit('view-detail', item)
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard class="rounded-lg border shadow-sm">
        <VCardItem class="py-4">
          <div class="d-flex flex-wrap align-center justify-space-between gap-4">
            <div>
              <VCardTitle class="d-flex align-center text-subtitle-1 font-weight-bold text-uppercase">
                <VIcon icon="tabler-arrows-left-right" color="primary" class="me-2" size="22" />
                Cuadrante de Cruce de Códigos (Posibles Sustituciones)
              </VCardTitle>
              <VCardSubtitle>Detección automática de errores de despacho vs pérdidas reales</VCardSubtitle>
            </div>

            <div style="min-width: 260px;">
              <VTextField
                v-model="search"
                density="comfortable"
                variant="outlined"
                placeholder="Buscar cruce o producto..."
                prepend-inner-icon="tabler-search"
                hide-details="auto"
                clearable
              />
            </div>
          </div>
        </VCardItem>

        <VDivider />

        <VDataTable
          :headers="headers"
          :items="filteredSubstitutions"
          :loading="loading"
          items-per-page="10"
          density="comfortable"
        >
          <!-- Categoría -->
          <template #item.category="{ item }">
            <span class="text-primary font-weight-bold text-caption text-truncate d-inline-block" style="max-width: 120px;">
              {{ item.category }}
            </span>
          </template>

          <!-- Producto A -->
          <template #item.product_a="{ item }">
            <span class="text-error font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 240px;" :title="item.product_a">
              {{ item.product_a }}
            </span>
          </template>

          <!-- Cantidad A -->
          <template #item.discrepancy_a="{ item }">
            <VChip size="small" color="error" variant="tonal" class="font-weight-black">
              {{ item.discrepancy_a }}
            </VChip>
          </template>

          <!-- Separador / Icono de intercambio -->
          <template #item.action>
            <VIcon icon="tabler-arrows-exchange-2" color="warning" size="18" />
          </template>

          <!-- Producto B -->
          <template #item.product_b="{ item }">
            <span class="text-success font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 240px;" :title="item.product_b">
              {{ item.product_b }}
            </span>
          </template>

          <!-- Cantidad B -->
          <template #item.discrepancy_b="{ item }">
            <VChip size="small" color="success" variant="tonal" class="font-weight-black">
              {{ item.discrepancy_b }}
            </VChip>
          </template>

          <!-- Nivel de Confianza -->
          <template #item.confidence="{ item }">
            <VChip
              size="small"
              :color="item.confidence?.includes('Alta') ? 'primary' : 'secondary'"
              label
              class="font-weight-black"
            >
              {{ item.confidence }}
            </VChip>
          </template>

          <!-- Acciones de Drill-Down -->
          <template #item.actions="{ item }">
            <VBtn
              size="small"
              variant="text"
              color="primary"
              icon="tabler-eye"
              @click="handleOpenDetail(item)"
            >
              <VIcon icon="tabler-eye" size="20" />
              <VTooltip activator="parent" location="top">Ver detalle del cruce</VTooltip>
            </VBtn>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="d-flex flex-column align-center justify-center py-8">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2 opacity-50" />
              <span class="text-body-2 text-medium-emphasis">
                No se detectaron cruces de códigos directos en este periodo.
              </span>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </VCol>

    <!-- Modal de Detalle (Drill-Down) -->
    <VDialog v-model="isDetailDialogOpen" max-width="600">
      <VCard v-if="selectedRow" class="rounded-lg">
        <VCardItem class="bg-lightprimary py-3">
          <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center">
            <VIcon icon="tabler-arrows-exchange" color="primary" class="me-2" />
            Auditoría de Cruce de Inventario
          </VCardTitle>
        </VCardItem>
        <VDivider />
        <VCardText class="pa-4">
          <div class="mb-4">
            <span class="text-caption text-medium-emphasis d-block font-weight-bold">CATEGORÍA ASOCIADA</span>
            <span class="text-body-1 font-weight-medium text-primary">{{ selectedRow.category }}</span>
          </div>

          <VRow dense>
            <VCol cols="12" sm="6">
              <VCard variant="outlined" color="error" class="pa-3 rounded-lg">
                <span class="text-caption font-weight-bold text-error d-block mb-1">PRODUCTO EN FALTANTE</span>
                <h5 class="text-body-2 font-weight-bold mb-2">{{ selectedRow.product_a }}</h5>
                <VChip size="small" color="error" variant="flat">
                  Discrepancia: {{ selectedRow.discrepancy_a }}
                </VChip>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" color="success" class="pa-3 rounded-lg">
                <span class="text-caption font-weight-bold text-success d-block mb-1">PRODUCTO EN SOBRANTE</span>
                <h5 class="text-body-2 font-weight-bold mb-2">{{ selectedRow.product_b }}</h5>
                <VChip size="small" color="success" variant="flat">
                  Discrepancia: {{ selectedRow.discrepancy_b }}
                </VChip>
              </VCard>
            </VCol>
          </VRow>

          <VAlert type="info" variant="tonal" class="mt-4 text-caption" icon="tabler-info-circle">
            Diagnóstico BI: La simetría en cantidades ({{ Math.abs(selectedRow.discrepancy_a) }} unid.) sugiere una entrega equivocada de SKU durante el despacho o venta en mostrador sin impacto neto negativo.
          </VAlert>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-3 justify-end">
          <VBtn variant="outlined" color="secondary" @click="isDetailDialogOpen = false">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>
