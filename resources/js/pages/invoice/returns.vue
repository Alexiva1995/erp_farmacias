<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'
import ReturnKpiCards from './components/ReturnKpiCards.vue'
import ReturnFilterBar from './components/ReturnFilterBar.vue'
import ReturnDetailModal from './components/ReturnDetailModal.vue'

// Estados reactivos de tabla paginada en servidor
const loading = ref(false)
const returnsList = ref([])
const totalItems = ref(0)
const itemsPerPage = ref(10)
const page = ref(1)

// Filtros
const search = ref('')
const selectedStatus = ref('')
const dateFrom = ref('')
const dateTo = ref('')

// Modal y Detalles
const detailDialog = ref(false)
const selectedReturn = ref(null)

// Opciones de estado
const statusOptions = [
  { title: 'Todos los estados', value: '' },
  { title: 'Pendiente', value: 'pending' },
  { title: 'Aprobada', value: 'approved' },
  { title: 'Rechazada', value: 'rejected' },
]

// Headers para la Data Table Server
const headers = [
  { title: 'N° FACTURA', key: 'invoice_number', sortable: false, width: '12%' },
  { title: 'PROVEEDOR', key: 'supplier_name', sortable: false, width: '20%' },
  { title: 'PRODUCTO', key: 'product_name', sortable: false, width: '25%' },
  { title: 'CANTIDAD', key: 'quantity', align: 'center', sortable: false, width: '8%' },
  { title: 'REEMBOLSO', key: 'amount_refunded', align: 'end', sortable: false, width: '12%' },
  { title: 'LOTE / VENC.', key: 'lot_info', sortable: false, width: '15%' },
  { title: 'FECHA DEV.', key: 'return_date', sortable: false, width: '10%' },
  { title: 'ESTADO', key: 'status', align: 'center', sortable: false, width: '10%' },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false, width: '12%' },
]

// KPIs estadísticos globales desde el backend
const stats = ref({
  total: 0,
  pending: 0,
  approved: 0,
  rejected: 0,
})

// Cargar devoluciones con paginación en servidor
const fetchReturns = async () => {
  loading.value = true
  try {
    const params = {
      search: search.value || undefined,
      status: selectedStatus.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
      itemsPerPage: itemsPerPage.value,
      page: page.value,
    }

    const { data } = await axios.get('/invoice-returns', { params })
    returnsList.value = data.data || []
    totalItems.value = data.total || 0
    if (data.stats) {
      stats.value = data.stats
    }
  } catch (error) {
    console.error('Error al cargar devoluciones:', error)
    toast.error('Error al cargar las devoluciones de facturas')
  } finally {
    loading.value = false
  }
}

const updateTableOptions = (options) => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  fetchReturns()
}

// Limpiar filtros
const clearFilters = () => {
  search.value = ''
  selectedStatus.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
  fetchReturns()
}

let debounceTimer
watch(
  [search, selectedStatus, dateFrom, dateTo],
  () => {
    page.value = 1
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      fetchReturns()
    }, 300)
  }
)

// Función para copiar productos e información de devolución al portapapeles
const copyReturnData = async (item) => {
  const textToCopy = `========================================
DEVOLUCIÓN DE FACTURA ${item.invoice_number}
========================================
Proveedor: ${item.supplier_name}
RIF: ${item.supplier_rif || 'N/A'}
Fecha Devolución: ${item.return_date || 'N/A'}
Estado Actual: ${(item.status_label || item.status || '').toUpperCase()}
----------------------------------------
DATOS DEL PRODUCTO A DEVOLVER:
• Producto: ${item.product_name}
• Código de Barras / SKU: ${item.barcode || item.sku || 'N/A'}
• Cantidad Devuelta: ${item.quantity}
• Lote: ${item.lot_number || 'N/A'}
• Fecha Vencimiento: ${item.expiration_date || 'N/A'}
• Monto Reembolso: Bs ${formatNumber(item.amount_refunded_bs || item.amount_refunded)}
• Descuento Proveedor: ${item.supplier_discount_percentage}%
========================================`

  try {
    await navigator.clipboard.writeText(textToCopy)
    toast.success(`Datos de devolución de Factura ${item.invoice_number} copiados.`)
  } catch (err) {
    toast.error('No se pudo copiar al portapapeles')
  }
}

// Cambiar estado de una devolución (Aprobar / Rechazar)
const updateStatus = async (item, newStatus) => {
  const isApproval = newStatus === 'approved'

  try {
    await axios.patch(`/invoice-returns/${item.id}/status`, { status: newStatus })
    toast.success(
      isApproval
        ? `Devolución aprobada y Nota de Débito creada con éxito`
        : `Devolución rechazada correctamente`
    )
    await fetchReturns()
  } catch (error) {
    console.error('Error al actualizar devolución:', error)
    toast.error('No se pudo actualizar el estado de la devolución')
  }
}

// Abrir detalle
const openDetail = (item) => {
  selectedReturn.value = item
  detailDialog.value = true
}

// Formateador de moneda en Bs
const formatNumber = (value) => {
  const num = Number(value) || 0
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}

// Formateadores de color para Chips
const getStatusColor = (status) => {
  switch (status) {
    case 'pending':
      return 'warning'
    case 'approved':
      return 'success'
    case 'rejected':
      return 'error'
    default:
      return 'secondary'
  }
}

onMounted(() => {
  fetchReturns()
})
</script>

<template>
  <div>
    <!-- Componente Desacoplado: Tarjetas KPI -->
    <ReturnKpiCards :stats="stats" :loading="loading" />

    <!-- Componente Desacoplado: Barra de Filtros -->
    <ReturnFilterBar
      v-model:search="search"
      v-model:status="selectedStatus"
      v-model:dateFrom="dateFrom"
      v-model:dateTo="dateTo"
      :status-options="statusOptions"
      @filter-change="fetchReturns"
      @clear="clearFilters"
    />

    <!-- Tabla principal de Devoluciones (VDataTableServer) -->
    <VCard class="elevation-2 rounded-lg border">
      <VDataTableServer
        :headers="headers"
        :items="returnsList"
        :items-length="totalItems"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        density="compact"
        class="elevation-0"
        @update:options="updateTableOptions"
      >
        <!-- Columna Factura (Limpia sin almohadilla #) -->
        <template #item.invoice_number="{ item }">
          <span class="font-weight-black text-primary">
            {{ item.invoice_number }}
          </span>
        </template>

        <!-- Columna Proveedor -->
        <template #item.supplier_name="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-medium text-high-emphasis">{{ item.supplier_name }}</span>
            <span v-if="item.supplier_rif" class="text-caption text-medium-emphasis">RIF: {{ item.supplier_rif }}</span>
          </div>
        </template>

        <!-- Columna Producto -->
        <template #item.product_name="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-bold text-high-emphasis">{{ item.product_name }}</span>
            <span class="text-caption text-medium-emphasis">
              Barras/SKU: {{ item.barcode || item.sku || 'N/A' }}
            </span>
          </div>
        </template>

        <!-- Columna Cantidad -->
        <template #item.quantity="{ item }">
          <VChip color="primary" size="small" variant="tonal" class="font-weight-bold">
            {{ item.quantity }}
          </VChip>
        </template>

        <!-- Columna Reembolso (En Bs en texto oscuro/negro) -->
        <template #item.amount_refunded="{ item }">
          <span class="font-weight-black text-high-emphasis">
            Bs {{ formatNumber(item.amount_refunded_bs || item.amount_refunded) }}
          </span>
        </template>

        <!-- Columna Lote / Vencimiento -->
        <template #item.lot_info="{ item }">
          <div class="d-flex flex-column text-caption">
            <span><strong>Lote:</strong> {{ item.lot_number || 'N/A' }}</span>
            <span><strong>Venc:</strong> {{ item.expiration_date || 'N/A' }}</span>
          </div>
        </template>

        <!-- Columna Fecha Devolución -->
        <template #item.return_date="{ item }">
          <span class="text-body-2 text-medium-emphasis">
            {{ item.return_date || 'N/A' }}
          </span>
        </template>

        <!-- Columna Estado -->
        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            size="small"
            variant="tonal"
            class="font-weight-bold text-uppercase"
          >
            {{ item.status_label || item.status }}
          </VChip>
        </template>

        <!-- Columna Acciones Estilo IconBtn de Inventario -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center ga-1">
            <IconBtn
              size="small"
              color="secondary"
              @click="copyReturnData(item)"
            >
              <VIcon icon="tabler-copy" size="18" />
              <VTooltip activator="parent" location="top">Copiar datos</VTooltip>
            </IconBtn>

            <IconBtn
              size="small"
              color="info"
              @click="openDetail(item)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent" location="top">Ver detalle</VTooltip>
            </IconBtn>

            <template v-if="item.status === 'pending'">
              <IconBtn
                size="small"
                color="success"
                @click="updateStatus(item, 'approved')"
              >
                <VIcon icon="tabler-check" size="18" />
                <VTooltip activator="parent" location="top">Aprobar devolución</VTooltip>
              </IconBtn>

              <IconBtn
                size="small"
                color="error"
                @click="updateStatus(item, 'rejected')"
              >
                <VIcon icon="tabler-x" size="18" />
                <VTooltip activator="parent" location="top">Rechazar devolución</VTooltip>
              </IconBtn>
            </template>
          </div>
        </template>

        <!-- Estado Vacío -->
        <template #no-data>
          <div class="pa-8 text-center">
            <VIcon icon="tabler-package-off" size="48" color="medium-emphasis" class="mb-2" />
            <p class="text-subtitle-1 text-medium-emphasis">No se encontraron devoluciones registradas.</p>
          </div>
        </template>
      </VDataTableServer>
    </VCard>

    <!-- Componente Desacoplado: Modal de Detalle con Header Corporativo -->
    <ReturnDetailModal
      v-model="detailDialog"
      :item="selectedReturn"
      @copy="copyReturnData"
    />
  </div>
</template>
