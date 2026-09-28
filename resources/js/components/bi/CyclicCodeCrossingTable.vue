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

const search = ref('')

const headers = [
  { title: 'CATEGORÍA', key: 'category', sortable: true, width: '120px' },
  { title: 'PRODUCTO A (FALTANTE)', key: 'product_a', sortable: true, minWidth: '160px' },
  { title: 'CANT.', key: 'discrepancy_a', align: 'center', sortable: true, width: '70px' },
  { title: '', key: 'action', align: 'center', sortable: false, width: '36px' },
  { title: 'PRODUCTO B (SOBRANTE)', key: 'product_b', sortable: true, minWidth: '160px' },
  { title: 'CANT.', key: 'discrepancy_b', align: 'center', sortable: true, width: '70px' },
  { title: 'CONFIANZA', key: 'confidence', align: 'end', sortable: true, width: '120px' },
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

            <div style="min-width: 250px;">
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
            <span class="text-primary font-weight-bold text-caption text-truncate d-inline-block" style="max-width: 110px;">
              {{ item.category }}
            </span>
          </template>

          <!-- Producto A -->
          <template #item.product_a="{ item }">
            <span class="text-error font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 220px;" :title="item.product_a">
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
            <span class="text-success font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 220px;" :title="item.product_b">
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
              :color="item.confidence === 'Alta' || item.confidence === '100%' ? 'primary' : 'secondary'"
              label
              class="font-weight-black"
            >
              {{ item.confidence }}
            </VChip>
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
  </VRow>
</template>
