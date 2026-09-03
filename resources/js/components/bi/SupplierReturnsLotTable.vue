<script setup>
/**
 * Tabla de lotes de un laboratorio en el reporte de devoluciones.
 * Componente extraído para mantener la página principal por debajo de 500 líneas.
 */
defineProps({
  /** @type {{ lot_id, product_name, active_ingredient, lot_number, expiration_date, days_to_expiry, quantity, supplier_name, purchase_date, total_amount }[]} */
  lots: {
    type: Array,
    required: true,
    default: () => [],
  },
})

const headers = [
  { title: 'PRODUCTO', key: 'product_name', align: 'start', sortable: true, minWidth: '160px' },
  { title: 'No. LOTE', key: 'lot_number', align: 'center', sortable: false },
  { title: 'VENCIMIENTO', key: 'expiration_date', align: 'center', sortable: true },
  { title: 'DÍAS', key: 'days_to_expiry', align: 'center', sortable: true, width: '80px' },
  { title: 'CANT.', key: 'quantity', align: 'end', sortable: true, width: '80px' },
  { title: 'PROVEEDOR', key: 'supplier_name', align: 'start', sortable: true },
  { title: 'F. COMPRA', key: 'purchase_date', align: 'center', sortable: false },
  { title: 'MONTO USD', key: 'total_amount', align: 'end', sortable: true },
]

/** Formatea una fecha ISO a localización venezolana */
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('es-VE') : '—'

/** Formatea un número como moneda USD */
const fmtMoney = (v) =>
  `$${Number(v ?? 0).toLocaleString('es-VE', { minimumFractionDigits: 2 })}`

/** Retorna color del semáforo según días restantes */
const daysColor = (days) => {
  if (days <= 30) return 'error'
  if (days <= 60) return 'warning'
  return 'info'
}
</script>

<template>
  <VDataTable
    :headers="headers"
    :items="lots"
    :items-per-page="25"
    density="compact"
    class="lot-table"
    no-data-text="Sin lotes registrados"
  >
    <!-- Producto + principio activo -->
    <template #item.product_name="{ item }">
      <div class="d-flex flex-column py-1">
        <span class="text-xs font-weight-black text-uppercase">{{ item.product_name }}</span>
        <span v-if="item.active_ingredient" class="text-super-xs text-disabled">
          {{ item.active_ingredient }}
        </span>
      </div>
    </template>

    <!-- Fecha de vencimiento en rojo -->
    <template #item.expiration_date="{ item }">
      <span class="font-weight-bold text-error text-xs">{{ fmtDate(item.expiration_date) }}</span>
    </template>

    <!-- Semáforo de días restantes -->
    <template #item.days_to_expiry="{ item }">
      <VChip
        :color="daysColor(item.days_to_expiry)"
        variant="tonal"
        size="x-small"
        class="font-weight-black"
      >
        {{ item.days_to_expiry }}d
      </VChip>
    </template>

    <!-- Cantidad -->
    <template #item.quantity="{ item }">
      <span class="font-weight-black text-xs">
        {{ Number(item.quantity).toLocaleString('es-VE') }}
      </span>
    </template>

    <!-- Fecha de compra -->
    <template #item.purchase_date="{ item }">
      <span class="text-xs text-medium-emphasis">{{ fmtDate(item.purchase_date) }}</span>
    </template>

    <!-- Monto en riesgo -->
    <template #item.total_amount="{ item }">
      <span class="font-weight-black text-error text-xs">{{ fmtMoney(item.total_amount) }}</span>
    </template>
  </VDataTable>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

/* Encabezados — compatible dark/light mode */
.lot-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  font-size: 0.62rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.4px !important;
}

.lot-table :deep(td) {
  font-size: 0.72rem !important;
  height: 44px !important;
}
</style>
