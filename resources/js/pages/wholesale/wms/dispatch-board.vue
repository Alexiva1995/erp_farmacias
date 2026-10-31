<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'

const isLoading = ref(false)
const isProcessing = ref(false)
const orders = ref([])
const routes = ref([])

const filters = reactive({
  workflow_status: '',
  delivery_route_id: null,
  search: '',
})

const workflowTabs = [
  { value: '', label: 'Todas las Etapas', icon: 'tabler-layout-kanban' },
  { value: 'approved_credit', label: 'Por Iniciar Picking', icon: 'tabler-Clipboard-list' },
  { value: 'picking', label: 'En Picking FEFO', icon: 'tabler-package' },
  { value: 'checking', label: 'Doble Chequeo y Embalaje', icon: 'tabler-barcode' },
  { value: 'packed', label: 'Listas para Despacho', icon: 'tabler-box-seam' },
  { value: 'dispatched', label: 'En Ruta / Despachadas', icon: 'tabler-truck-delivery' },
]

const showOperationModal = ref(false)
const activeOrder = ref(null)
const operationMode = ref('picking') // 'picking' | 'packing' | 'dispatch'

const packingForm = reactive({
  packages_count: 1,
  cold_boxes_count: 0,
  delivery_route_id: null,
  items: [],
})

const getStatusColor = (status) => {
  switch (status) {
    case 'approved_credit':
      return 'info'
    case 'picking':
      return 'warning'
    case 'checking':
      return 'purple'
    case 'packed':
      return 'primary'
    case 'dispatched':
      return 'success'
    default:
      return 'secondary'
  }
}

const getStatusLabel = (status) => {
  switch (status) {
    case 'approved_credit':
      return 'Aprobado / Cola Almacén'
    case 'picking':
      return 'En Picking (FEFO)'
    case 'checking':
      return 'En Doble Chequeo'
    case 'packed':
      return 'Embalado (Bultos/Cavas)'
    case 'dispatched':
      return 'Despachado en Ruta'
    default:
      return status
  }
}

const fetchOrders = async () => {
  isLoading.value = true
  try {
    const params = {
      workflow_status: filters.workflow_status || undefined,
      delivery_route_id: filters.delivery_route_id || undefined,
      search: filters.search || undefined,
    }
    const { data } = await axios.get('/wholesale/wms/orders', { params })
    orders.value = data.data?.data || data.data || []
  } catch (error) {
    console.error('Error al cargar órdenes WMS:', error)
    orders.value = []
    toast.error('Error al cargar el tablero de almacén WMS')
  } finally {
    isLoading.value = false
  }
}

const fetchRoutes = async () => {
  try {
    const { data } = await axios.get('/wholesale/wms/routes')
    routes.value = data.data || []
  } catch (error) {
    console.error('Error cargando rutas logísticas:', error)
  }
}

const startOrderPicking = async (order) => {
  isProcessing.value = true
  try {
    const { data } = await axios.post(`/wholesale/wms/orders/${order.id}/start-picking`)
    toast.success(data.message || 'Picking FEFO iniciado')
    await fetchOrders()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al iniciar el picking')
  } finally {
    isProcessing.value = false
  }
}

const openOperationModal = (order, mode) => {
  activeOrder.value = order
  operationMode.value = mode
  packingForm.packages_count = Number(order.packages_count || 1) || 1
  packingForm.cold_boxes_count = Number(order.cold_boxes_count || 0)
  packingForm.delivery_route_id = order.delivery_route_id || null
  const details = order.order_details || order.details || []
  packingForm.items = details.map(detail => ({
    order_detail_id: detail.id,
    product_name: detail.product?.name || detail.product_name || `Producto #${detail.product_id}`,
    barcode: detail.product?.barcode || detail.barcode || 'S/C',
    lot_number: detail.product_lot?.lot_number || detail.lot?.lot_number || 'FEFO Automático',
    expiration_date: detail.product_lot?.expiration_date || detail.lot?.expiration_date || 'N/A',
    requested_quantity: Number(detail.quantity),
    picked_quantity: Number(detail.picked_quantity || detail.quantity),
    checked_quantity: Number(detail.checked_quantity || detail.picked_quantity || detail.quantity),
  }))
  showOperationModal.value = true
}

const submitOperation = async () => {
  if (!activeOrder.value) return
  isProcessing.value = true
  try {
    if (operationMode.value === 'picking') {
      const { data } = await axios.post(`/wholesale/wms/orders/${activeOrder.value.id}/complete-picking`, {
        items: packingForm.items.map(i => ({
          order_detail_id: i.order_detail_id,
          picked_quantity: i.picked_quantity,
        })),
      })
      toast.success(data.message || 'Picking completado')
    } else if (operationMode.value === 'packing') {
      const { data } = await axios.post(`/wholesale/wms/orders/${activeOrder.value.id}/complete-packing`, {
        packages_count: packingForm.packages_count,
        cold_boxes_count: packingForm.cold_boxes_count,
        items: packingForm.items.map(i => ({
          order_detail_id: i.order_detail_id,
          checked_quantity: i.checked_quantity,
        })),
      })
      toast.success(data.message || 'Embalaje y doble chequeo completados')
    } else if (operationMode.value === 'dispatch') {
      const { data } = await axios.post(`/wholesale/wms/orders/${activeOrder.value.id}/dispatch`, {
        delivery_route_id: packingForm.delivery_route_id,
      })
      toast.success(data.message || 'Orden despachada hacia la farmacia')
    }

    showOperationModal.value = false
    await fetchOrders()
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error procesando operación de almacén')
  } finally {
    isProcessing.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchOrders(), fetchRoutes()])
})
</script>

<template>
  <VContainer fluid>
    <!-- Encabezado Principal -->
    <VCard class="mb-4" elevation="2">
      <VCardText class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div>
          <h2 class="text-h5 font-weight-bold text-primary">
            Tablero de Almacén y Despacho Mayorista (WMS / FEFO)
          </h2>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Flujo de Picking por Lote más Próximo a Vencer (FEFO), Doble Chequeo con Escáner, Embalaje de Cavas Frías y Despacho por Ruta.
          </p>
        </div>

        <div class="d-flex align-center gap-3">
          <VSelect
            v-model="filters.delivery_route_id"
            :items="routes"
            item-title="name"
            item-value="id"
            label="Filtrar por Ruta de Despacho"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            style="min-width: 240px;"
            @update:model-value="fetchOrders"
          />
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-reload"
            :loading="isLoading"
            @click="fetchOrders"
          >
            Actualizar Cola
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VTabs
        v-model="filters.workflow_status"
        color="primary"
         show-arrows
        @update:model-value="fetchOrders"
      >
        <VTab
          v-for="tab in workflowTabs"
          :key="tab.value"
          :value="tab.value"
        >
          {{ tab.label }}
        </VTab>
      </VTabs>
    </VCard>

    <!-- Tabla de Órdenes en Almacén -->
    <VCard elevation="2">
      <VCardText>
        <VRow class="mb-3">
          <VCol cols="12" md="5">
            <VTextField
              v-model="filters.search"
              label="Buscar por N° de Pedido, Farmacia o RIF..."
              prepend-inner-icon="tabler-search"
              density="compact"
              variant="outlined"
              clearable
              hide-details
              @keyup.enter="fetchOrders"
            />
          </VCol>
        </VRow>

        <VProgressLinear v-if="isLoading" indeterminate color="primary" class="mb-3" />

        <VTable density="comfortable">
          <thead>
            <tr>
              <th>Pedido #</th>
              <th>Farmacia Destino</th>
              <th>Plazo Sub-Factura</th>
              <th>Ruta Logística</th>
              <th class="text-center">
                Renglones / Bultos
              </th>
              <th class="text-center">
                Estado WMS
              </th>
              <th class="text-right">
                Total (USD)
              </th>
              <th class="text-center">
                Acción Operativa
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td class="font-weight-bold">
                #{{ order.id }}
                <div v-if="order.parent_b2b_order_id" class="text-caption text-medium-emphasis">
                  Padre: #{{ order.parent_b2b_order_id }}
                </div>
              </td>
              <td>
                <div class="font-weight-bold">
                  {{ order.client?.name || 'Farmacia Cliente' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ order.client?.identification || order.client?.identification_number || '' }}
                </div>
              </td>
              <td>
                <VChip size="small" color="info" variant="tonal">
                  {{ order.credit_term_days > 0 ? `Crédito ${order.credit_term_days} días` : 'Contado (0 días)' }}
                </VChip>
              </td>
              <td>
                {{ order.delivery_route?.name || 'Por asignar ruta' }}
              </td>
              <td class="text-center">
                <div>{{ (order.order_details || order.details || []).length }} ítems</div>
                <div v-if="order.packages_count > 0" class="text-caption font-weight-medium text-primary">
                  {{ order.packages_count }} cajas / {{ order.cold_boxes_count || 0 }} cavas
                </div>
              </td>
              <td class="text-center">
                <VChip :color="getStatusColor(order.b2b_workflow_status)" size="small">
                  {{ getStatusLabel(order.b2b_workflow_status) }}
                </VChip>
              </td>
              <td class="text-right font-weight-bold">
                ${{ Number(order.total || order.total_amount || 0).toFixed(2) }}
              </td>
              <td class="text-center">
                <VBtn
                  v-if="order.b2b_workflow_status === 'approved_credit'"
                  size="small"
                  color="info"
                  prepend-icon="tabler-play"
                  :loading="isProcessing"
                  @click="startOrderPicking(order)"
                >
                  Iniciar Picking
                </VBtn>

                <VBtn
                  v-else-if="order.b2b_workflow_status === 'picking'"
                  size="small"
                  color="warning"
                  prepend-icon="tabler-check"
                  @click="openOperationModal(order, 'picking')"
                >
                  Confirmar Picking FEFO
                </VBtn>

                <VBtn
                  v-else-if="order.b2b_workflow_status === 'checking'"
                  size="small"
                  color="primary"
                  prepend-icon="tabler-box-seam"
                  @click="openOperationModal(order, 'packing')"
                >
                  Chequeo y Embalaje
                </VBtn>

                <VBtn
                  v-else-if="order.b2b_workflow_status === 'packed'"
                  size="small"
                  color="success"
                  prepend-icon="tabler-truck-delivery"
                  @click="openOperationModal(order, 'dispatch')"
                >
                  Despachar Ruta
                </VBtn>

                <VChip
                  v-else-if="order.b2b_workflow_status === 'dispatched'"
                  size="small"
                  color="success"
                  variant="outlined"
                >
                  En Tránsito / Entregado
                </VChip>
              </td>
            </tr>
            <tr v-if="!isLoading && orders.length === 0">
              <td colspan="8" class="text-center py-8 text-medium-emphasis">
                No hay órdenes pendientes en esta etapa del almacén.
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
    </VCard>

    <!-- Modal Operativo de Picking, Doble Chequeo/Embalaje y Despacho -->
    <VDialog v-model="showOperationModal" max-width="820">
      <VCard v-if="activeOrder">
        <VCardTitle class="pa-4 font-weight-bold d-flex align-center justify-space-between">
          <span>
            {{
              operationMode === 'picking'
                ? `Confirmación de Picking FEFO - Pedido #${activeOrder.id}`
                : operationMode === 'packing'
                  ? `Doble Chequeo y Embalaje - Pedido #${activeOrder.id}`
                  : `Despacho en Ruta Logística - Pedido #${activeOrder.id}`
            }}
          </span>
          <VChip size="small" color="primary">
            {{ activeOrder.client?.name }}
          </VChip>
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-4">
          <!-- Controles de Bultos y Cavas en modo Embalaje -->
          <VRow v-if="operationMode === 'packing'" class="mb-3">
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="packingForm.packages_count"
                type="number"
                min="1"
                label="Cantidad de Cajas / Bultos Sellados"
                prepend-inner-icon="tabler-box"
                density="compact"
                variant="outlined"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                v-model.number="packingForm.cold_boxes_count"
                type="number"
                min="0"
                label="Cantidad de Cavas Refrigeradas (Cadena de Frío)"
                prepend-inner-icon="tabler-snowflake"
                density="compact"
                variant="outlined"
              />
            </VCol>
          </VRow>

          <!-- Selección de Ruta en modo Despacho -->
          <VRow v-if="operationMode === 'dispatch'" class="mb-3">
            <VCol cols="12">
              <VSelect
                v-model="packingForm.delivery_route_id"
                :items="routes"
                item-title="name"
                item-value="id"
                label="Asignar o Confirmar Ruta de Despacho"
                prepend-inner-icon="tabler-truck"
                density="compact"
                variant="outlined"
              />
              <VAlert type="info" variant="tonal" density="compact" class="mt-3">
                Al despachar este pedido, si la farmacia destino está interconectada en el ERP (<code>linked_tenant_id</code>), la factura y sus lotes FEFO se inyectarán automáticamente como Compra Pendiente de Recepción en su sistema.
              </VAlert>
            </VCol>
          </VRow>

          <!-- Tabla de verificación de lotes FEFO y cantidades -->
          <VTable v-if="operationMode !== 'dispatch'" density="compact">
            <thead>
              <tr>
                <th>Producto / Código</th>
                <th>Lote Asignado (FEFO)</th>
                <th class="text-center">
                  Solicitado
                </th>
                <th class="text-center" style="width: 160px;">
                  {{ operationMode === 'picking' ? 'Cant. Recolectada' : 'Cant. Chequeada' }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in packingForm.items" :key="item.order_detail_id">
                <td>
                  <div class="font-weight-medium">
                    {{ item.product_name }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    EAN: {{ item.barcode }}
                  </div>
                </td>
                <td>
                  <VChip size="x-small" color="secondary" variant="tonal">
                    Lote: {{ item.lot_number }}
                  </VChip>
                  <div class="text-caption text-medium-emphasis">
                    Vence: {{ item.expiration_date }}
                  </div>
                </td>
                <td class="text-center font-weight-bold">
                  {{ item.requested_quantity }}
                </td>
                <td class="text-center">
                  <VTextField
                    v-if="operationMode === 'picking'"
                    v-model.number="item.picked_quantity"
                    type="number"
                    min="0"
                    :max="item.requested_quantity"
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                  <VTextField
                    v-else
                    v-model.number="item.checked_quantity"
                    type="number"
                    min="0"
                    :max="item.picked_quantity"
                    density="compact"
                    variant="outlined"
                    hide-details
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>

        <VDivider />
        <VCardActions class="pa-4 justify-end">
          <VBtn variant="text" @click="showOperationModal = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="elevated"
            :loading="isProcessing"
            @click="submitOperation"
          >
            Confirmar y Avanzar Etapa
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
