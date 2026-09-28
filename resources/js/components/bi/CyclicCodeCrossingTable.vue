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
  { title: 'CATEGORÍA', key: 'category', sortable: true, width: '120px' },
  { title: 'PRODUCTO A (FALTANTE)', key: 'product_a', sortable: true, minWidth: '160px' },
  { title: 'CANT.', key: 'discrepancy_a', align: 'center', sortable: true, width: '70px' },
  { title: '', key: 'action', align: 'center', sortable: false, width: '36px' },
  { title: 'PRODUCTO B (SOBRANTE)', key: 'product_b', sortable: true, minWidth: '160px' },
  { title: 'CANT.', key: 'discrepancy_b', align: 'center', sortable: true, width: '70px' },
  { title: 'POSIBLE VENDEDOR / FACTURA', key: 'suspect', align: 'center', sortable: false, minWidth: '190px' },
  { title: 'CONFIANZA / MOTIVO', key: 'confidence', align: 'center', sortable: true, width: '160px' },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '70px' },
]

const filteredSubstitutions = computed(() => {
  if (!search.value) return props.substitutions
  const q = search.value.toLowerCase()
  return props.substitutions.filter(item => 
    (item.category && item.category.toLowerCase().includes(q)) ||
    (item.product_a && item.product_a.toLowerCase().includes(q)) ||
    (item.product_b && item.product_b.toLowerCase().includes(q)) ||
    (item.active_ingredient_a && item.active_ingredient_a.toLowerCase().includes(q)) ||
    (item.active_ingredient_b && item.active_ingredient_b.toLowerCase().includes(q)) ||
    (item.match_reason && item.match_reason.toLowerCase().includes(q)) ||
    (item.top_suspect?.name && item.top_suspect.name.toLowerCase().includes(q)) ||
    (item.top_suspect?.order_id && String(item.top_suspect.order_id).includes(q))
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
              <VCardSubtitle>Detección inteligente de sustitución de SKU con trazabilidad del vendedor y orden de venta</VCardSubtitle>
            </div>

            <div style="min-width: 260px;">
              <VTextField
                v-model="search"
                density="comfortable"
                variant="outlined"
                placeholder="Buscar cruce, vendedor, molécula..."
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
            <div>
              <span class="text-error font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 200px;" :title="item.product_a">
                {{ item.product_a }}
              </span>
              <span v-if="item.active_ingredient_a" class="d-block text-caption text-medium-emphasis text-truncate" style="max-width: 200px;" :title="item.active_ingredient_a">
                <VIcon icon="tabler-flask" size="12" class="me-1" />{{ item.active_ingredient_a }}
              </span>
            </div>
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
            <div>
              <span class="text-success font-weight-medium text-body-2 text-truncate d-inline-block" style="max-width: 200px;" :title="item.product_b">
                {{ item.product_b }}
              </span>
              <span v-if="item.active_ingredient_b" class="d-block text-caption text-medium-emphasis text-truncate" style="max-width: 200px;" :title="item.active_ingredient_b">
                <VIcon icon="tabler-flask" size="12" class="me-1" />{{ item.active_ingredient_b }}
              </span>
            </div>
          </template>

          <!-- Cantidad B -->
          <template #item.discrepancy_b="{ item }">
            <VChip size="small" color="success" variant="tonal" class="font-weight-black">
              {{ item.discrepancy_b }}
            </VChip>
          </template>

          <!-- Posible Vendedor / Factura -->
          <template #item.suspect="{ item }">
            <div v-if="item.top_suspect" class="d-flex flex-column align-center py-1">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-user-exclamation" size="15" color="warning" />
                <span class="text-body-2 font-weight-bold text-high-emphasis text-truncate" style="max-width: 130px;">
                  {{ item.top_suspect.name }}
                </span>
              </div>
              <div class="d-flex align-center gap-1 mt-1">
                <VChip size="x-small" color="warning" variant="tonal" class="font-weight-bold">
                  Orden #{{ item.top_suspect.order_id }}
                </VChip>
                <VChip size="x-small" :color="item.top_suspect.probability >= 80 ? 'error' : 'warning'" variant="flat" class="font-weight-bold px-1">
                  {{ item.top_suspect.probability }}% prob.
                </VChip>
              </div>
            </div>
            <div v-else class="text-caption text-disabled text-center">
              Sin venta directa en rango
            </div>
          </template>

          <!-- Nivel de Confianza -->
          <template #item.confidence="{ item }">
            <div>
              <VChip
                size="small"
                :color="item.confidence?.includes('Alta') ? 'primary' : 'secondary'"
                label
                class="font-weight-black mb-1"
              >
                {{ item.confidence }}
              </VChip>
              <span v-if="item.match_reason" class="d-block text-caption text-disabled text-truncate" style="max-width: 160px;" :title="item.match_reason">
                {{ item.match_reason }}
              </span>
            </div>
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
              <VTooltip activator="parent" location="top">Auditar venta sospechosa</VTooltip>
            </VBtn>
          </template>

          <!-- Estado Vacío -->
          <template #no-data>
            <div class="d-flex flex-column align-center justify-center py-8">
              <VIcon icon="tabler-check-circle" size="40" color="success" class="mb-2 opacity-50" />
              <span class="text-body-2 text-medium-emphasis">
                No se detectaron cruces de códigos en este periodo.
              </span>
            </div>
          </template>
        </VDataTable>
      </VCard>
    </VCol>

    <!-- Modal de Detalle y Auditoría de Ventas Sospechosas -->
    <VDialog v-model="isDetailDialogOpen" max-width="720">
      <VCard v-if="selectedRow" class="rounded-lg">
        <VCardItem class="py-3 bg-surface border-b">
          <div class="d-flex align-center justify-space-between w-100">
            <VCardTitle class="text-subtitle-1 font-weight-bold d-flex align-center text-primary">
              <VIcon icon="tabler-search" color="primary" class="me-2" />
              Auditoría de Sustitución y Vendedor Imputado
            </VCardTitle>
            <VChip size="small" color="primary" variant="tonal" class="font-weight-bold">
              {{ selectedRow.category }}
            </VChip>
          </div>
        </VCardItem>
        
        <VCardText class="pa-4">
          <!-- Par Faltante vs Sobrante -->
          <VRow dense class="mb-3">
            <VCol cols="12" sm="6">
              <VCard variant="outlined" color="error" class="pa-3 rounded-lg h-100">
                <span class="text-caption font-weight-bold text-error d-block mb-1">PRODUCTO FALTANTE (-{{ Math.abs(selectedRow.discrepancy_a) }})</span>
                <h5 class="text-body-2 font-weight-bold mb-1">{{ selectedRow.product_a }}</h5>
                <p v-if="selectedRow.active_ingredient_a" class="text-caption text-medium-emphasis mb-0">
                  <strong>P. Activo:</strong> {{ selectedRow.active_ingredient_a }}
                </p>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6">
              <VCard variant="outlined" color="success" class="pa-3 rounded-lg h-100">
                <span class="text-caption font-weight-bold text-success d-block mb-1">PRODUCTO SOBRANTE (+{{ selectedRow.discrepancy_b }})</span>
                <h5 class="text-body-2 font-weight-bold mb-1">{{ selectedRow.product_b }}</h5>
                <p v-if="selectedRow.active_ingredient_b" class="text-caption text-medium-emphasis mb-0">
                  <strong>P. Activo:</strong> {{ selectedRow.active_ingredient_b }}
                </p>
              </VCard>
            </VCol>
          </VRow>

          <!-- Tarjeta de Vendedor Imputado Principal -->
          <VCard v-if="selectedRow.top_suspect" variant="tonal" color="warning" class="pa-4 rounded-lg mb-4 border">
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
              <div class="d-flex align-center gap-2">
                <VAvatar color="warning" variant="flat" size="36" rounded="circle">
                  <VIcon icon="tabler-user-alert" size="20" color="white" />
                </VAvatar>
                <div>
                  <span class="text-caption font-weight-bold text-uppercase d-block text-warning">Principal Vendedor Involucrado</span>
                  <span class="text-body-1 font-weight-black text-high-emphasis">{{ selectedRow.top_suspect.name }}</span>
                </div>
              </div>
              <VChip color="warning" variant="flat" class="font-weight-black">
                {{ selectedRow.top_suspect.probability }}% de Certeza
              </VChip>
            </div>
            <div class="d-flex align-center flex-wrap ga-x-4 ga-y-1 text-caption text-medium-emphasis">
              <span><strong>Orden / Factura:</strong> #{{ selectedRow.top_suspect.order_id }}</span>
              <span><strong>Fecha / Hora de Venta:</strong> {{ selectedRow.top_suspect.date }}</span>
            </div>
          </VCard>

          <!-- Tabla de Ventas Candidatas en la Ventana de Auditoría -->
          <div v-if="selectedRow.suspicious_sales && selectedRow.suspicious_sales.length > 0" class="mb-3">
            <span class="text-caption font-weight-bold text-medium-emphasis text-uppercase d-block mb-2">
              Transacciones Registradas en el Período del Conteo
            </span>
            <VTable density="compact" class="border rounded-lg">
              <thead>
                <tr>
                  <th class="text-caption font-weight-bold">ORDEN</th>
                  <th class="text-caption font-weight-bold">FECHA</th>
                  <th class="text-caption font-weight-bold">CAJERO / VENDEDOR</th>
                  <th class="text-caption font-weight-bold">CLIENTE</th>
                  <th class="text-caption font-weight-bold text-end">CANT.</th>
                  <th class="text-caption font-weight-bold text-center">PROBABILIDAD</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(sale, sIdx) in selectedRow.suspicious_sales" :key="sIdx">
                  <td class="text-caption font-weight-bold text-primary">#{{ sale.order_id }}</td>
                  <td class="text-caption">{{ sale.sale_date }}</td>
                  <td class="text-caption font-weight-medium">{{ sale.seller_name }}</td>
                  <td class="text-caption text-truncate" style="max-width: 140px;">{{ sale.client_name }}</td>
                  <td class="text-caption font-weight-bold text-end">{{ sale.quantity }} uds</td>
                  <td class="text-caption text-center">
                    <VChip size="x-small" :color="sale.probability >= 80 ? 'error' : 'warning'" variant="tonal" class="font-weight-bold">
                      {{ sale.probability }}%
                    </VChip>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <div v-else class="text-caption text-disabled text-center pa-4 border rounded-lg mb-3">
            No se registraron ventas directas de estos SKUs en el rango de fechas seleccionado (posible error en recepción o conteo inicial).
          </div>

          <!-- Diagnóstico y Acción de Ajuste -->
          <VAlert type="info" variant="tonal" class="text-caption" icon="tabler-shield-check">
            <strong>Recomendación Operativa:</strong> Al tratarse de una sustitución simétrica por error de despacho en mostrador, se aconseja realizar una <strong>nota de reclasificación contable</strong> para equilibrar el stock sin registrar pérdida por merma.
          </VAlert>
        </VCardText>
        
        <VDivider />
        <VCardActions class="pa-3 justify-end">
          <VBtn variant="tonal" color="secondary" @click="isDetailDialogOpen = false">
            Cerrar Auditoría
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VRow>
</template>
