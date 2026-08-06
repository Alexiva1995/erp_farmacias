<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** Datos crudos de sobrestock desde el store */
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
})

const formatMoney = val => `$${Number(val).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const formatNumber = val => Number(val).toLocaleString('en-US')

/** Sobrestock agrupado por SKU — computed memoizado */
const aggregatedOverstock = computed(() => {
  if (!props.items?.length) return []

  const grouped = props.items.reduce((acc, curr) => {
    const key = curr.product_id
    if (!acc[key]) {
      acc[key] = {
        product_id: curr.product_id,
        name: curr.name,
        laboratory_name: curr.laboratory_name,
        stock_actual: 0,
        venta_mensual_promedio: parseFloat(curr.venta_mensual_promedio ?? 0),
        excedente_proyectado: 0,
        costo_excedente: 0,
      }
    }

    acc[key].stock_actual += parseFloat(curr.stock_actual ?? 0)
    acc[key].excedente_proyectado += parseFloat(curr.excedente_proyectado ?? 0)
    acc[key].costo_excedente += parseFloat(curr.costo_excedente ?? 0)

    return acc
  }, {})

  return Object.values(grouped).sort((a, b) => b.costo_excedente - a.costo_excedente)
})

const headers = [
  { title: 'PRODUCTO', key: 'name', align: 'start', sortable: true },
  { title: 'STOCK ACTUAL', key: 'stock_actual', align: 'end', sortable: true },
  { title: 'VTA. PROM', key: 'venta_mensual_promedio', align: 'end', sortable: true },
  { title: 'EXCEDENTE (U)', key: 'excedente_proyectado', align: 'end', sortable: true },
  { title: 'COSTO RIESGO', key: 'costo_excedente', align: 'end', sortable: true },
]

// Emitir el computed para que el padre pueda usarlo (exportar CSV)
defineExpose({ aggregatedOverstock })
</script>

<template>
  <VCard class="rounded-lg border shadow-sm h-full">
    <VCardItem>
      <VCardTitle class="d-flex align-center">
        <VIcon
          icon="tabler-alert-square"
          class="me-2 text-warning"
        />
        Alerta de Sobrestock Proyectado
      </VCardTitle>
    </VCardItem>

    <VDivider class="opacity-10" />

    <VCardText class="pa-0">
      <VDataTable
        :headers="headers"
        :items="aggregatedOverstock"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="overstock-table"
        no-data-text="✅ No se detectaron riesgos de sobrestock"
      >
        <!-- Nombre del producto -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-name">
              {{ item.name }}
            </span>
            <span class="text-super-xs text-disabled">
              ID: {{ item.product_id }} | {{ item.laboratory_name ?? 'Sin laboratorio' }}
            </span>
          </div>
        </template>

        <!-- Venta mensual promedio -->
        <template #item.venta_mensual_promedio="{ item }">
          <span class="text-xs">{{ formatNumber(item.venta_mensual_promedio) }}</span>
        </template>

        <!-- Stock actual -->
        <template #item.stock_actual="{ item }">
          <span class="font-weight-black">{{ formatNumber(item.stock_actual) }}</span>
        </template>

        <!-- Excedente proyectado — rojo si > 0 -->
        <template #item.excedente_proyectado="{ item }">
          <VChip
            :color="item.excedente_proyectado > 0 ? 'error' : 'success'"
            variant="tonal"
            size="small"
            class="font-weight-black"
          >
            {{ formatNumber(item.excedente_proyectado) }}
          </VChip>
        </template>

        <!-- Costo en riesgo -->
        <template #item.costo_excedente="{ item }">
          <span class="font-weight-black text-error">
            {{ formatMoney(item.costo_excedente) }}
          </span>
        </template>

        <!-- Empty state personalizado -->
        <template #no-data>
          <div class="d-flex flex-column align-center justify-center pa-8 text-center">
            <VIcon
              icon="tabler-check-circle"
              size="48"
              color="success"
              class="mb-3"
            />
            <p class="text-body-1 font-weight-bold mb-1">
              Sin riesgos detectados
            </p>
            <p class="text-caption text-disabled">
              No hay productos con sobrestock proyectado en el período seleccionado.
            </p>
          </div>
        </template>
      </VDataTable>
    </VCardText>
  </VCard>
</template>

<style scoped>
.product-name {
  max-inline-size: 350px;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

/* Encabezados de tabla — compatible dark/light mode */
.overstock-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.65rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
}

.overstock-table :deep(td) {
  font-size: 0.7rem !important;
  height: 48px !important;
}
</style>
