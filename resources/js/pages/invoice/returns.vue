<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from '@/plugins/axios'
import ReturnKpiCards from './components/ReturnKpiCards.vue'
import ReturnFilterBar from './components/ReturnFilterBar.vue'
import ReturnDetailModal from './components/ReturnDetailModal.vue'

// Estados reactivos
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

// Notificaciones Toast / Snackbar
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
})

const showToast = (text, color = 'success') => {
  snackbar.value = {
    show: true,
    text,
    color,
  }
}

// Opciones de estado
const statusOptions = [
  { title: 'Todos los estados', value: '' },
  { title: 'Pendiente', value: 'pending' },
  { title: 'Aprobada', value: 'approved' },
  { title: 'Rechazada', value: 'rejected' },
]

// Headers para la Data Table
const headers = [
  { title: 'N° FACTURA', key: 'invoice_number', sortable: false },
  { title: 'PROVEEDOR', key: 'supplier_name', sortable: false },
  { title: 'PRODUCTO', key: 'product_name', sortable: false },
  { title: 'CANTIDAD', key: 'quantity', align: 'center', sortable: false },
  { title: 'REEMBOLSO ($)', key: 'amount_refunded', align: 'end', sortable: false },
  { title: 'LOTE / VENC.', key: 'lot_info', sortable: false },
  { title: 'FECHA DEV.', key: 'return_date', sortable: false },
  { title: 'ESTADO', key: 'status', align: 'center', sortable: false },
  { title: 'ACCIONES', key: 'actions', align: 'center', sortable: false },
]

// Cargar devoluciones
const fetchReturns = async () => {
  loading.value = true
  try {
    const params = {
      search: search.value,
      status: selectedStatus.value,
      date_from: dateFrom.value,
      date_to: dateTo.value,
      itemsPerPage: itemsPerPage.value,
      page: page.value,
    }

    const { data } = await axios.get('/invoice-returns', { params })
    returnsList.value = data.data || []
    totalItems.value = data.total || 0
  } catch (error) {
    showToast('Error al cargar las devoluciones de facturas', 'error')
  } finally {
    loading.value = false
  }
}

// Limpiar filtros
const clearFilters = () => {
  search.value = ''
  selectedStatus.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  fetchReturns()
}

// KPIs estadísticos
const stats = computed(() => {
  const pending = returnsList.value.filter(item => item.status === 'pending').length
  const approved = returnsList.value.filter(item => item.status === 'approved').length
  const rejected = returnsList.value.filter(item => item.status === 'rejected').length

  return {
    total: totalItems.value,
    pending,
    approved,
    rejected,
  }
})

// Función para copiar productos e información de devolución al portapapeles ("botoncito de copiar")
const copyReturnData = async (item) => {
  const textToCopy = `========================================
DEVOLUCIÓN DE FACTURA #${item.invoice_number}
========================================
Proveedor: ${item.supplier_name}
RIF: ${item.supplier_rif || 'N/A'}
Fecha Devolución: ${item.return_date || 'N/A'}
Estado Actual: ${item.status_label.toUpperCase()}
----------------------------------------
DATOS DEL PRODUCTO A DEVOLVER:
• Producto: ${item.product_name}
• Código de Barras / SKU: ${item.barcode || item.sku || 'N/A'}
• Cantidad Devuelta: ${item.quantity}
• Lote: ${item.lot_number || 'N/A'}
• Fecha Vencimiento: ${item.expiration_date || 'N/A'}
• Monto Reembolso: $${parseFloat(item.amount_refunded).toFixed(2)}
• Descuento Proveedor: ${item.supplier_discount_percentage}%
========================================`

  try {
    await navigator.clipboard.writeText(textToCopy)
    showToast(`¡Datos de devolución de Factura #${item.invoice_number} copiados al portapapeles!`, 'success')
  } catch (err) {
    showToast('No se pudo copiar al portapapeles', 'error')
  }
}

// Cambiar estado de una devolución (Aprobar / Rechazar)
const updateStatus = async (item, newStatus) => {
  const actionText = newStatus === 'approved' ? 'aprobar' : 'rechazar'
  if (!confirm(`¿Está seguro de que desea ${actionText} esta devolución de la factura #${item.invoice_number}?`)) {
    return
  }

  try {
    await axios.patch(`/invoice-returns/${item.id}/status`, { status: newStatus })
    showToast(`Devolución ${newStatus === 'approved' ? 'aprobada' : 'rechazada'} exitosamente`, 'success')
    await fetchReturns()
  } catch (error) {
    showToast(`Error al ${actionText} la devolución`, 'error')
  }
}

// Abrir detalle
const openDetail = (item) => {
  selectedReturn.value = item
  detailDialog.value = true
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

    <!-- Tabla principal de Devoluciones -->
    <v-card class="elevation-3 rounded-lg border">
      <v-data-table
        :headers="headers"
        :items="returnsList"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="elevation-0"
      >
        <!-- Columna Factura -->
        <template #item.invoice_number="{ item }">
          <div class="font-weight-black text-primary">
            #{{ item.invoice_number }}
          </div>
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
          <v-chip color="info" size="small" variant="tonal" class="font-weight-bold">
            {{ item.quantity }}
          </v-chip>
        </template>

        <!-- Columna Reembolso -->
        <template #item.amount_refunded="{ item }">
          <span class="font-weight-black text-success">
            ${{ parseFloat(item.amount_refunded).toFixed(2) }}
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
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            variant="elevated"
            class="font-weight-bold text-uppercase"
          >
            {{ item.status_label }}
          </v-chip>
        </template>

        <!-- Columna Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <v-btn
              icon="tabler-copy"
              color="primary"
              variant="tonal"
              size="small"
              @click="copyReturnData(item)"
            >
              <v-icon icon="tabler-copy" size="18" />
              <v-tooltip activator="parent" location="top">Copiar datos de devolución</v-tooltip>
            </v-btn>

            <v-btn
              icon="tabler-eye"
              color="info"
              variant="tonal"
              size="small"
              @click="openDetail(item)"
            >
              <v-icon icon="tabler-eye" size="18" />
              <v-tooltip activator="parent" location="top">Ver detalle</v-tooltip>
            </v-btn>

            <template v-if="item.status === 'pending'">
              <v-btn
                icon="tabler-check"
                color="success"
                variant="tonal"
                size="small"
                @click="updateStatus(item, 'approved')"
              >
                <v-icon icon="tabler-check" size="18" />
                <v-tooltip activator="parent" location="top">Aprobar devolución</v-tooltip>
              </v-btn>

              <v-btn
                icon="tabler-x"
                color="error"
                variant="tonal"
                size="small"
                @click="updateStatus(item, 'rejected')"
              >
                <v-icon icon="tabler-x" size="18" />
                <v-tooltip activator="parent" location="top">Rechazar devolución</v-tooltip>
              </v-btn>
            </template>
          </div>
        </template>

        <!-- Estado Vacío -->
        <template #no-data>
          <div class="pa-8 text-center">
            <v-icon icon="tabler-package-off" size="48" color="medium-emphasis" class="mb-2" />
            <p class="text-subtitle-1 text-medium-emphasis">No se encontraron devoluciones registradas.</p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Componente Desacoplado: Modal de Detalle -->
    <ReturnDetailModal
      v-model="detailDialog"
      :item="selectedReturn"
      @copy="copyReturnData"
    />

    <!-- Toast de Feedback -->
    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
      location="top right"
    >
      {{ snackbar.text }}
      <template #actions>
        <v-btn color="white" variant="text" @click="snackbar.show = false">
          Cerrar
        </v-btn>
      </template>
    </v-snackbar>
  </div>
</template>

<style scoped>
.gap-1 {
  gap: 4px;
}
.gap-2 {
  gap: 8px;
}
</style>
