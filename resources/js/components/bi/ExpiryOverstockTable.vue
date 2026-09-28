<script setup>
import { ref, computed } from 'vue'
import Swal from 'sweetalert2'

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

const emit = defineEmits(['notify'])

const formatMoney = val => `$${Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
const formatNumber = val => Number(val || 0).toLocaleString('en-US')

// Estado para filas expandidas (Drill-down por lote)
const expandedRows = ref([])

// Filtro rápido por segmento de riesgo
const selectedSegment = ref('all') // 'all' | 'critical' | 'alert' | 'preventive' | 'overstock'

// Estados de modales de acción operativa
const actionModal = ref({
  show: false,
  type: '', // 'offer' | 'sales_goal' | 'supplier_return'
  product: null,
  form: {
    discount_pct: 20,
    goal_units: 0,
    commission_bonus: 5,
    return_reason: 'Próximo a caducar (<90 días)',
    notes: '',
  },
  processing: false,
})

/** Sobrestock agrupado por SKU con agregación de lotes para drill-down */
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
        has_overstock_risk: false,
        risk_label: null,
        status: curr.status ?? 'estable',
        color: curr.color ?? 'success',
        lots: [],
      }
    }

    acc[key].stock_actual         += parseFloat(curr.stock_actual ?? 0)
    acc[key].excedente_proyectado += parseFloat(curr.excedente_proyectado ?? 0)
    acc[key].costo_excedente      += parseFloat(curr.costo_excedente ?? 0)

    // Agregar lote individual para el drill-down
    acc[key].lots.push({
      lot_number: curr.lot_number || 'S/L',
      expiration_date: curr.expiration_date ? curr.expiration_date.slice(0, 10) : 'N/A',
      days_to_expiry: curr.days_to_expiry ?? 0,
      stock_actual: parseFloat(curr.stock_actual ?? 0),
      excedente_proyectado: parseFloat(curr.excedente_proyectado ?? 0),
      costo_excedente: parseFloat(curr.costo_excedente ?? 0),
      status: curr.status ?? 'estable',
      color: curr.color ?? 'success',
    })

    if (curr.has_overstock_risk) {
      acc[key].has_overstock_risk = true
    }

    // Semáforo: priorizar el estado más crítico
    const priority = { vencido: 4, critico: 3, moderado: 2, estable: 1 }
    const currentP = priority[curr.status] ?? 1
    const storedP  = priority[acc[key].status] ?? 1
    if (currentP > storedP) {
      acc[key].status = curr.status
      acc[key].color  = curr.color
    }

    return acc
  }, {})

  // Unificación semántica: si tiene excedente en riesgo, el status no puede ser 'estable'
  const result = Object.values(grouped).map(item => {
    // Cálculo de Días de Cobertura (DIO)
    const dailySales = item.venta_mensual_promedio / 30
    item.dio = dailySales > 0 ? Math.round(item.stock_actual / dailySales) : 999

    if (item.has_overstock_risk && item.excedente_proyectado > 0) {
      const unidades = Math.ceil(item.excedente_proyectado)
      item.risk_label = `Sobrestock: ${unidades} ${unidades === 1 ? 'u' : 'u'}`
      
      // Corrección semántica estricta: nunca verde si hay unidades en riesgo
      if (item.status === 'estable') {
        item.status = 'moderado'
        item.color  = 'warning'
      }
    } else {
      item.risk_label = null
    }

    return item
  })

  return result.sort((a, b) => b.costo_excedente - a.costo_excedente)
})

/** Filtrado reactivo por segmento rápido de riesgo */
const filteredOverstock = computed(() => {
  const list = aggregatedOverstock.value
  if (selectedSegment.value === 'critical') {
    return list.filter(item => item.status === 'critico' || item.status === 'vencido' || item.lots.some(l => l.days_to_expiry <= 30))
  }
  if (selectedSegment.value === 'alert') {
    return list.filter(item => item.status === 'moderado' || item.lots.some(l => l.days_to_expiry > 30 && l.days_to_expiry <= 90))
  }
  if (selectedSegment.value === 'preventive') {
    return list.filter(item => item.lots.some(l => l.days_to_expiry > 90 && l.days_to_expiry <= 180))
  }
  if (selectedSegment.value === 'overstock') {
    return list.filter(item => item.excedente_proyectado > 0 || item.has_overstock_risk)
  }
  return list
})

const headers = [
  { title: '', key: 'data-table-expand', width: 40 },
  { title: 'PRODUCTO / SKU', key: 'name', align: 'start', sortable: true },
  { title: 'ESTADO', key: 'status', align: 'center', sortable: true },
  { title: 'COBERTURA (DIO)', key: 'dio', align: 'end', sortable: true },
  { title: 'STOCK', key: 'stock_actual', align: 'end', sortable: true },
  { title: 'VTA. PROM (POND.)', key: 'venta_mensual_promedio', align: 'end', sortable: true },
  { title: 'EXCEDENTE (U)', key: 'excedente_proyectado', align: 'end', sortable: true },
  { title: 'COSTO RIESGO', key: 'costo_excedente', align: 'end', sortable: true },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false },
]

const statusMap = {
  vencido:  { label: 'Vencido',  color: 'error',   icon: 'tabler-circle-x' },
  critico:  { label: 'Crítico',  color: 'error',   icon: 'tabler-alert-circle' },
  moderado: { label: 'Moderado', color: 'warning',  icon: 'tabler-alert-triangle' },
  estable:  { label: 'Estable',  color: 'success',  icon: 'tabler-circle-check' },
}

// ─── Gestión de Acciones Rápidas con SweetAlert2 ───────────────────────────
const openAction = (type, product) => {
  actionModal.value = {
    show: true,
    type,
    product,
    form: {
      discount_pct: 25,
      goal_units: Math.ceil(product.excedente_proyectado || product.stock_actual),
      commission_bonus: 5,
      return_reason: 'Próximo a caducar (<90 días)',
      notes: '',
    },
    processing: false,
  }
}

const confirmAction = async () => {
  const prodName = actionModal.value.product?.name
  const actionType = actionModal.value.type

  let confirmTitle = '¿Confirmar acción?'
  let confirmText = `Se aplicará la acción comercial para ${prodName}`

  if (actionType === 'offer') {
    confirmTitle = '¿Activar Oferta de Liquidación?'
    confirmText = `Se configurará un ${actionModal.value.form.discount_pct}% de descuento en TPV para acelerar la rotación.`
  } else if (actionType === 'sales_goal') {
    confirmTitle = '¿Asignar Meta de Ventas?'
    confirmText = `Se asignará un objetivo de ${actionModal.value.form.goal_units} u. con +${actionModal.value.form.commission_bonus}% de comisión extra.`
  } else if (actionType === 'supplier_return') {
    confirmTitle = '¿Generar Solicitud de Devolución?'
    confirmText = `Se registrará la petición formal de canje ante el laboratorio proveedor.`
  }

  const result = await Swal.fire({
    title: confirmTitle,
    text: confirmText,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#E20074',
    cancelButtonColor: '#7A0099',
    confirmButtonText: 'Sí, aplicar',
    cancelButtonText: 'Cancelar',
    customClass: {
      confirmButton: 'v-btn v-btn--density-default v-theme--light bg-primary text-white me-2',
      cancelButton: 'v-btn v-btn--density-default v-theme--light bg-secondary text-white',
    },
  })

  if (!result.isConfirmed) return

  actionModal.value.processing = true
  setTimeout(() => {
    actionModal.value.processing = false
    let msg = ''

    if (actionType === 'offer') {
      msg = `Oferta de liquidación del ${actionModal.value.form.discount_pct}% creada para ${prodName}`
    } else if (actionType === 'sales_goal') {
      msg = `Meta de ventas de ${actionModal.value.form.goal_units} u. asignada para ${prodName}`
    } else if (actionType === 'supplier_return') {
      msg = `Solicitud de canje/devolución enviada al laboratorio para ${prodName}`
    }

    actionModal.value.show = false
    emit('notify', { message: msg, color: 'success' })
  }, 500)
}

// Emitir el computed para que el padre pueda usarlo (exportar CSV / PDF)
defineExpose({ aggregatedOverstock, filteredOverstock })
</script>

<template>
  <VCard class="rounded-lg border shadow-sm h-100 d-flex flex-column">
    <VCardItem class="pb-2">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center">
          <VIcon
            icon="tabler-alert-square"
            class="me-2 text-warning"
          />
          <span class="text-h6 font-weight-bold">Alerta de Sobrestock Proyectado</span>
        </div>
        <span class="text-caption text-disabled">
          {{ filteredOverstock.length }} de {{ aggregatedOverstock.length }} productos
        </span>
      </VCardTitle>

      <!-- Segmentos rápidos de riesgo -->
      <div class="d-flex align-center gap-1 flex-wrap mt-2">
        <VChip
          size="small"
          :variant="selectedSegment === 'all' ? 'flat' : 'outlined'"
          :color="selectedSegment === 'all' ? 'primary' : 'secondary'"
          class="cursor-pointer"
          @click="selectedSegment = 'all'"
        >
          Todos ({{ aggregatedOverstock.length }})
        </VChip>
        <VChip
          size="small"
          :variant="selectedSegment === 'critical' ? 'flat' : 'outlined'"
          color="error"
          class="cursor-pointer"
          @click="selectedSegment = 'critical'"
        >
          Crítico &lt;30d
        </VChip>
        <VChip
          size="small"
          :variant="selectedSegment === 'alert' ? 'flat' : 'outlined'"
          color="warning"
          class="cursor-pointer"
          @click="selectedSegment = 'alert'"
        >
          Alerta 30-90d
        </VChip>
        <VChip
          size="small"
          :variant="selectedSegment === 'preventive' ? 'flat' : 'outlined'"
          color="info"
          class="cursor-pointer"
          @click="selectedSegment = 'preventive'"
        >
          Preventivo 90-180d
        </VChip>
        <VChip
          size="small"
          :variant="selectedSegment === 'overstock' ? 'flat' : 'outlined'"
          color="secondary"
          class="cursor-pointer"
          @click="selectedSegment = 'overstock'"
        >
          Solo Sobrestock
        </VChip>
      </div>
    </VCardItem>

    <VDivider class="opacity-10" />

    <VCardText class="pa-0 flex-grow-1">
      <VDataTable
        v-model:expanded="expandedRows"
        :headers="headers"
        :items="filteredOverstock"
        :loading="loading"
        :items-per-page="itemsPerPage"
        item-value="product_id"
        show-expand
        class="overstock-table"
        no-data-text="✅ No se detectaron riesgos para este segmento"
      >
        <!-- Nombre del producto + badge "Sobrestock en Riesgo" -->
        <template #item.name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate product-name">
              {{ item.name }}
            </span>
            <span class="text-super-xs text-disabled">
              ID: {{ item.product_id }} | {{ item.laboratory_name ?? 'Sin laboratorio' }}
            </span>
            <div class="d-flex align-center gap-1 mt-1">
              <VChip
                v-if="item.risk_label"
                color="error"
                variant="tonal"
                size="x-small"
                class="font-weight-bold risk-chip"
                prepend-icon="tabler-clock-exclamation"
              >
                {{ item.risk_label }}
              </VChip>
              <VChip
                size="x-small"
                variant="outlined"
                color="secondary"
                class="font-weight-bold"
              >
                {{ item.lots.length }} {{ item.lots.length === 1 ? 'lote' : 'lotes' }}
              </VChip>
            </div>
          </div>
        </template>

        <!-- Semáforo de estado con unificación semántica -->
        <template #item.status="{ item }">
          <VChip
            :color="statusMap[item.status]?.color ?? 'secondary'"
            variant="tonal"
            size="x-small"
            class="font-weight-bold"
          >
            <VIcon :icon="statusMap[item.status]?.icon ?? 'tabler-circle'" size="12" class="me-1" />
            {{ statusMap[item.status]?.label ?? item.status }}
          </VChip>
        </template>

        <!-- Días de Cobertura (DIO) -->
        <template #item.dio="{ item }">
          <div class="text-end">
            <span :class="`font-weight-bold text-xs ${item.dio > 90 ? 'text-error' : item.dio > 45 ? 'text-warning' : 'text-success'}`">
              {{ item.dio >= 999 ? '> 365 d' : `${item.dio} d` }}
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

        <!-- Excedente proyectado -->
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

        <!-- Acciones Operativas Rápidas -->
        <template #item.actions="{ item }">
          <VMenu location="bottom end">
            <template #activator="{ props: menuProps }">
              <VBtn
                icon
                variant="text"
                size="small"
                v-bind="menuProps"
                color="secondary"
              >
                <VIcon icon="tabler-dots-vertical" size="18" />
              </VBtn>
            </template>
            <VList density="compact" class="py-1">
              <VListItem
                prepend-icon="tabler-tag"
                title="Crear Oferta de Liquidación"
                @click="openAction('offer', item)"
              />
              <VListItem
                prepend-icon="tabler-target-arrow"
                title="Asignar Meta a Vendedores"
                @click="openAction('sales_goal', item)"
              />
              <VListItem
                prepend-icon="tabler-truck-return"
                title="Solicitar Canje a Proveedor"
                @click="openAction('supplier_return', item)"
              />
            </VList>
          </VMenu>
        </template>

        <!-- ─── Drill-Down por Lote (Fila Expandida) ────────────────────── -->
        <template #expanded-row="{ columns, item }">
          <tr>
            <td :colspan="columns.length" class="pa-3 bg-light-surface">
              <div class="rounded border pa-3 bg-surface">
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-xs font-weight-bold text-primary d-flex align-center">
                    <VIcon icon="tabler-layers-subtract" size="16" class="me-1" />
                    Desglose de Lotes Individuales — {{ item.name }}
                  </span>
                  <span class="text-super-xs text-disabled">
                    Total: {{ item.lots.length }} lote(s) en almacén
                  </span>
                </div>

                <VTable density="compact" class="drilldown-table rounded">
                  <thead>
                    <tr>
                      <th class="text-start">LOTE</th>
                      <th class="text-center">VENCIMIENTO</th>
                      <th class="text-center">DÍAS RESTANTES</th>
                      <th class="text-end">STOCK LOTE</th>
                      <th class="text-end">EXCEDENTE (U)</th>
                      <th class="text-end">COSTO RIESGO</th>
                      <th class="text-center">ESTADO</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(lot, lIdx) in item.lots" :key="lIdx">
                      <td class="font-weight-bold text-xs">{{ lot.lot_number }}</td>
                      <td class="text-center text-xs">{{ lot.expiration_date }}</td>
                      <td class="text-center text-xs">
                        <span :class="lot.days_to_expiry <= 90 ? 'text-error font-weight-bold' : ''">
                          {{ lot.days_to_expiry }} d
                        </span>
                      </td>
                      <td class="text-end font-weight-bold text-xs">{{ formatNumber(lot.stock_actual) }}</td>
                      <td class="text-end text-xs">
                        <span :class="lot.excedente_proyectado > 0 ? 'text-error font-weight-bold' : ''">
                          {{ formatNumber(lot.excedente_proyectado) }}
                        </span>
                      </td>
                      <td class="text-end text-xs font-weight-bold" :class="lot.costo_excedente > 0 ? 'text-error' : ''">
                        {{ formatMoney(lot.costo_excedente) }}
                      </td>
                      <td class="text-center">
                        <VChip
                          :color="statusMap[lot.status]?.color ?? 'secondary'"
                          size="x-small"
                          variant="tonal"
                        >
                          {{ statusMap[lot.status]?.label ?? lot.status }}
                        </VChip>
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </td>
          </tr>
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

    <!-- ─── Modal de Acciones Operativas Rápidas ───────────────────────── -->
    <VDialog v-model="actionModal.show" max-width="500">
      <VCard class="rounded-lg">
        <VCardItem>
          <VCardTitle class="d-flex align-center">
            <VIcon
              :icon="actionModal.type === 'offer' ? 'tabler-tag' : actionModal.type === 'sales_goal' ? 'tabler-target-arrow' : 'tabler-truck-return'"
              class="me-2 text-primary"
            />
            <span v-if="actionModal.type === 'offer'">Crear Oferta de Liquidación</span>
            <span v-else-if="actionModal.type === 'sales_goal'">Asignar Meta a Fuerza de Ventas</span>
            <span v-else>Solicitar Canje / Devolución</span>
          </VCardTitle>
          <VCardSubtitle class="text-truncate mt-1">
            {{ actionModal.product?.name }} (ID: {{ actionModal.product?.product_id }})
          </VCardSubtitle>
        </VCardItem>

        <VDivider class="opacity-10" />

        <VCardText class="pa-4">
          <!-- Formulario: Oferta de Liquidación -->
          <template v-if="actionModal.type === 'offer'">
            <p class="text-caption text-medium-emphasis mb-3">
              Configure el descuento automático en TPV para evacuar las <strong>{{ formatNumber(actionModal.product?.excedente_proyectado || actionModal.product?.stock_actual) }} unidades</strong> en riesgo.
            </p>
            <VTextField
              v-model.number="actionModal.form.discount_pct"
              label="Porcentaje de Descuento (%)"
              type="number"
              min="5"
              max="90"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              class="mb-3"
              prepend-inner-icon="tabler-percentage"
            />
          </template>

          <!-- Formulario: Meta a Vendedores -->
          <template v-else-if="actionModal.type === 'sales_goal'">
            <p class="text-caption text-medium-emphasis mb-3">
              Incentive la rotación activa del producto estableciendo comisión especial por venta rápida.
            </p>
            <VTextField
              v-model.number="actionModal.form.goal_units"
              label="Unidades Objetivo"
              type="number"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              class="mb-3"
              prepend-inner-icon="tabler-package"
            />
            <VTextField
              v-model.number="actionModal.form.commission_bonus"
              label="Bono de Comisión Extra (%)"
              type="number"
              min="1"
              max="30"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              class="mb-3"
              prepend-inner-icon="tabler-coin"
            />
          </template>

          <!-- Formulario: Solicitud de Devolución -->
          <template v-else>
            <p class="text-caption text-medium-emphasis mb-3">
              Genere la solicitud de canje formal ante el laboratorio <strong>{{ actionModal.product?.laboratory_name ?? 'Proveedor' }}</strong>.
            </p>
            <VSelect
              v-model="actionModal.form.return_reason"
              :items="['Próximo a caducar (<90 días)', 'Sobrestock por baja rotación', 'Lote con política de canje acordada']"
              label="Motivo de Devolución"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              class="mb-3"
            />
            <VTextField
              v-model="actionModal.form.notes"
              label="Observaciones adicionales"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
              placeholder="Ej. Notificado a representante de ventas"
            />
          </template>
        </VCardText>

        <VDivider class="opacity-10" />

        <VCardActions class="pa-4 justify-end">
          <VBtn
            variant="text"
            color="secondary"
            :disabled="actionModal.processing"
            @click="actionModal.show = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            variant="flat"
            color="primary"
            :loading="actionModal.processing"
            @click="confirmAction"
          >
            Confirmar Acción
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.product-name {
  max-inline-size: 450px;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1.2;
}

/* Chip de alerta de sobrestock en riesgo */
.risk-chip {
  font-size: 0.6rem !important;
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
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

.drilldown-table :deep(th) {
  font-size: 0.6rem !important;
  height: 32px !important;
}

.drilldown-table :deep(td) {
  font-size: 0.65rem !important;
  height: 36px !important;
}
</style>
