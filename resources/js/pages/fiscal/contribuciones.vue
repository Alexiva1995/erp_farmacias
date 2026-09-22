<script setup>
import AppEmptyState from '@/components/AppEmptyState.vue'
import ContributionFormDialog from '@/components/dialogs/ContributionFormDialog.vue'
import ContributionPaymentDialog from '@/components/dialogs/ContributionPaymentDialog.vue'
import SeniatBotSyncDialog from '@/components/dialogs/SeniatBotSyncDialog.vue'
import SmartPasteContributionDialog from '@/components/dialogs/SmartPasteContributionDialog.vue'
import FiscalContributionFilters from '@/components/FiscalContributionFilters.vue'
import FiscalContributionKpis from '@/components/FiscalContributionKpis.vue'
import axios from '@/plugins/axios'
import { Swal, toast } from '@/plugins/sweetalert'
import { onMounted, onUnmounted, ref, watch } from 'vue'
import { useDisplay } from 'vuetify'

const { mobile } = useDisplay()

// Estados reactivos de datos
const contributions = ref([])
const kpis = ref({
  total_pending_amount: 0,
  total_pending_count: 0,
  total_expired_amount: 0,
  total_expired_count: 0,
  total_paid_amount: 0,
  total_paid_count: 0,
  next_due_date: null,
})

const loading = ref(false)
const totalRecords = ref(0)
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref('due_date')
const orderBy = ref('asc')

// Filtros
const search = ref('')
const taxType = ref(null)
const period = ref(null)
const status = ref(null)
const startDate = ref(null)
const endDate = ref(null)

// Diálogos
const showSmartPasteDialog = ref(false)
const showFormDialog = ref(false)
const showPaymentDialog = ref(false)
const showBotDialog = ref(false)
const selectedContribution = ref(null)

const tableHeaders = [
  { title: 'Periodo', key: 'period', sortable: true, width: '10%' },
  { title: 'Impuesto / Contribución', key: 'tax_type', sortable: true, width: '18%' },
  { title: 'Nº Documento', key: 'document_number', sortable: true, width: '15%' },
  { title: 'Fecha Operación', key: 'operation_date', sortable: true },
  { title: 'Fecha Vencimiento', key: 'due_date', sortable: true },
  { title: 'Monto (Bs.)', key: 'amount', align: 'end', sortable: true },
  { title: 'Estado', key: 'status', align: 'center', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center', width: '14%' },
]

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val || 0)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const parts = String(dateStr).split(' ')[0].split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}

const fetchContributions = async () => {
  loading.value = true
  try {
    const response = await axios.get('/fiscal-contributions', {
      params: {
        page: page.value,
        itemsPerPage: itemsPerPage.value,
        search: search.value || undefined,
        tax_type: taxType.value || undefined,
        period: period.value || undefined,
        status: status.value || undefined,
        start_date: startDate.value || undefined,
        end_date: endDate.value || undefined,
        sortBy: sortBy.value,
        orderBy: orderBy.value,
      },
    })

    contributions.value = response.data?.data || []
    kpis.value = response.data?.kpis || kpis.value
    totalRecords.value = response.data?.pagination?.total || 0
  } catch (error) {
    console.error('Error al cargar contribuciones fiscales:', error)
    toast.error('Error al sincronizar datos de contribuciones.')
  } finally {
    loading.value = false
  }
}

const handleTableUpdate = (options) => {
  page.value = options.page
  itemsPerPage.value = options.itemsPerPage
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy[0].key
    orderBy.value = options.sortBy[0].order
  }
  fetchContributions()
}

const handleSort = (sortOption) => {
  sortBy.value = sortOption.key
  orderBy.value = sortOption.order
  page.value = 1
  fetchContributions()
}

const clearFilters = () => {
  search.value = ''
  taxType.value = null
  period.value = null
  status.value = null
  startDate.value = null
  endDate.value = null
  page.value = 1
  fetchContributions()
}

// Acciones de Diálogos
const openCreateForm = () => {
  selectedContribution.value = null
  showFormDialog.value = true
}

const openEditForm = (item) => {
  selectedContribution.value = item
  showFormDialog.value = true
}

const openPaymentDialog = (item) => {
  selectedContribution.value = item
  showPaymentDialog.value = true
}

const handleDelete = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar contribución?',
    text: 'Esta acción eliminará el compromiso fiscal del sistema.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#e53935',
    cancelButtonColor: '#9e9e9e',
    confirmButtonText: 'SÍ, ELIMINAR',
    cancelButtonText: 'CANCELAR',
  })

  if (!result.isConfirmed) return

  try {
    loading.value = true
    const response = await axios.delete(`/fiscal-contributions/${id}`)
    toast.success(response.data?.message || 'Contribución eliminada exitosamente.')
    fetchContributions()
  } catch (error) {
    console.error('Error al eliminar contribución:', error)
    toast.error(error.response?.data?.message || 'Error al eliminar la contribución.')
  } finally {
    loading.value = false
  }
}

let debounceTimer = null
watch([search, taxType, period, status, startDate, endDate], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    page.value = 1
    fetchContributions()
  }, 400)
})

onMounted(() => {
  fetchContributions()
})

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
  <div class="fiscal-contribuciones-page pb-12">
    <div class="d-flex flex-column gap-2 mt-1">
      <!-- KPIs Superiores -->
      <FiscalContributionKpis :kpis="kpis" :loading="loading" />

      <!-- Filtros y Barra de Acciones -->
      <FiscalContributionFilters
        v-model:search="search"
        v-model:tax-type="taxType"
        v-model:period="period"
        v-model:status="status"
        v-model:start-date="startDate"
        v-model:end-date="endDate"
        :loading="loading"
        @clear="clearFilters"
        @sort="handleSort"
        @open-smart-paste="showSmartPasteDialog = true"
        @open-manual-form="openCreateForm"
        @open-bot-sync="showBotDialog = true"
      />

      <!-- Tabla Principal de Contribuciones -->
      <VCard class="ma-0 rounded-lg border shadow-sm overflow-hidden bg-surface">
        <VCardTitle class="pa-4 px-6 d-flex align-center justify-space-between flex-wrap gap-2">
          <div class="d-flex align-center">
            <VAvatar color="primary" variant="tonal" size="32" class="me-3 rounded-lg">
              <VIcon icon="tabler-receipt-tax" size="18" />
            </VAvatar>
            <span class="text-sm font-weight-black uppercase">Compromisos de Pago Tributarios</span>
          </div>

          <VChip size="small" color="primary" variant="tonal" class="font-weight-black">
            {{ totalRecords }} Registros
          </VChip>
        </VCardTitle>

        <VDivider class="opacity-10" />

        <!-- Vista Desktop -->
        <VDataTableServer
          v-if="!mobile"
          :headers="tableHeaders"
          :items="contributions"
          :items-length="totalRecords"
          :items-per-page="itemsPerPage"
          :page="page"
          :loading="loading"
          class="text-no-wrap premium-table"
          @update:options="handleTableUpdate"
        >
          <template #no-data>
            <AppEmptyState
              title="No hay contribuciones fiscales"
              message="No se encontraron compromisos de pago registrados para los filtros seleccionados."
              icon="tabler-receipt-off"
            />
          </template>

          <template #item.period="{ item }">
            <span class="text-xs font-weight-bold text-high-emphasis">{{ item.period }}</span>
          </template>

          <template #item.tax_type="{ item }">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-bold text-xs"
            >
              {{ item.tax_type }}
            </VChip>
          </template>

          <template #item.document_number="{ item }">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-hash" size="14" color="disabled" />
              <span class="text-xs font-weight-bold text-primary">{{ item.document_number }}</span>
            </div>
          </template>

          <template #item.operation_date="{ item }">
            <span class="text-xs text-disabled uppercase">{{ formatDate(item.operation_date) }}</span>
          </template>

          <template #item.due_date="{ item }">
            <span
              class="text-xs font-weight-bold"
              :class="item.is_expired ? 'text-error' : 'text-high-emphasis'"
            >
              {{ formatDate(item.due_date) }}
            </span>
          </template>

          <template #item.amount="{ item }">
            <span class="text-xs font-weight-bold text-success">{{ formatCurrency(item.amount) }}</span>
          </template>

          <template #item.status="{ item }">
            <VChip
              v-if="item.status === 'paid'"
              size="x-small"
              color="success"
              variant="flat"
              class="font-weight-bold text-super-xs"
            >
              <VIcon start icon="tabler-circle-check" size="12" />
              PAGADO
            </VChip>
            <VChip
              v-else-if="item.is_expired"
              size="x-small"
              color="error"
              variant="flat"
              class="font-weight-bold text-super-xs"
            >
              <VIcon start icon="tabler-alert-circle" size="12" />
              VENCIDO
            </VChip>
            <VChip
              v-else
              size="x-small"
              color="warning"
              variant="tonal"
              class="font-weight-bold text-super-xs"
            >
              <VIcon start icon="tabler-clock" size="12" />
              PENDIENTE
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <!-- Botón Pagar / Revertir -->
              <VBtn
                icon
                variant="text"
                size="30"
                :color="item.status === 'paid' ? 'warning' : 'success'"
                class="rounded-lg shadow-sm"
                @click="openPaymentDialog(item)"
              >
                <VIcon :icon="item.status === 'paid' ? 'tabler-rotate-clockwise' : 'tabler-cash'" size="18" />
                <VTooltip activator="parent" location="top">
                  {{ item.status === 'paid' ? 'Revertir a Pendiente' : 'Marcar como Pagado' }}
                </VTooltip>
              </VBtn>

              <!-- Botón Editar -->
              <VBtn
                icon
                variant="text"
                size="30"
                color="info"
                class="rounded-lg shadow-sm"
                @click="openEditForm(item)"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent" location="top">Editar</VTooltip>
              </VBtn>

              <!-- Botón Eliminar -->
              <VBtn
                icon
                variant="text"
                size="30"
                color="error"
                class="rounded-lg shadow-sm"
                @click="handleDelete(item.id)"
              >
                <VIcon icon="tabler-trash" size="18" />
                <VTooltip activator="parent" location="top">Eliminar</VTooltip>
              </VBtn>
            </div>
          </template>

          <template #bottom>
            <VDivider class="opacity-10" />
            <div class="d-flex align-center justify-space-between pa-4">
              <div class="d-flex align-center gap-4">
                <span class="text-super-xs text-disabled font-weight-bold uppercase">Mostrar</span>
                <VSelect
                  :model-value="itemsPerPage"
                  :items="[10, 25, 50, 100]"
                  variant="outlined"
                  density="compact"
                  hide-details
                  style="max-width: 80px;"
                  class="text-xs font-weight-black"
                  @update:model-value="(val) => { itemsPerPage = val; page = 1; fetchContributions(); }"
                />
                <span class="text-super-xs text-disabled font-weight-bold uppercase">de {{ totalRecords }} registros</span>
              </div>
              <VPagination
                :model-value="page"
                :length="Math.ceil(totalRecords / itemsPerPage) || 1"
                size="small"
                class="premium-pagination"
                @update:model-value="(newPage) => { page = newPage; fetchContributions(); }"
              />
            </div>
          </template>
        </VDataTableServer>

        <!-- Vista Móvil Responsive -->
        <div v-else class="d-flex flex-column gap-3 pa-3">
          <template v-if="contributions.length > 0">
            <VCard
              v-for="item in contributions"
              :key="item.id"
              class="border rounded-lg pa-4 shadow-xs"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                  {{ item.tax_type }}
                </VChip>
                <VChip
                  v-if="item.status === 'paid'"
                  size="x-small"
                  color="success"
                  class="font-weight-bold"
                >
                  PAGADO
                </VChip>
                <VChip
                  v-else-if="item.is_expired"
                  size="x-small"
                  color="error"
                  class="font-weight-bold"
                >
                  VENCIDO
                </VChip>
                <VChip
                  v-else
                  size="x-small"
                  color="warning"
                  variant="tonal"
                  class="font-weight-bold"
                >
                  PENDIENTE
                </VChip>
              </div>

              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-xs text-disabled">Documento #</span>
                <span class="text-xs font-weight-bold text-primary">{{ item.document_number }}</span>
              </div>

              <div class="d-flex justify-space-between align-center mb-1">
                <span class="text-xs text-disabled">Periodo</span>
                <span class="text-xs font-weight-bold">{{ item.period }}</span>
              </div>

              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-xs text-disabled">Vence:</span>
                <span :class="item.is_expired ? 'text-error font-weight-bold text-xs' : 'text-xs'">
                  {{ formatDate(item.due_date) }}
                </span>
              </div>

              <VDivider class="my-2 opacity-10" />

              <div class="d-flex justify-space-between align-center mb-3">
                <span class="text-xs font-weight-bold uppercase">Monto</span>
                <span class="text-sm font-weight-black text-success">Bs. {{ formatCurrency(item.amount) }}</span>
              </div>

              <div class="d-flex gap-2">
                <VBtn
                  variant="tonal"
                  size="small"
                  :color="item.status === 'paid' ? 'warning' : 'success'"
                  class="flex-grow-1 text-xs font-weight-bold rounded-lg"
                  @click="openPaymentDialog(item)"
                >
                  {{ item.status === 'paid' ? 'Revertir' : 'Marcar Pagado' }}
                </VBtn>
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="info"
                  @click="openEditForm(item)"
                >
                  <VIcon icon="tabler-edit" size="18" />
                </VBtn>
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  color="error"
                  @click="handleDelete(item.id)"
                >
                  <VIcon icon="tabler-trash" size="18" />
                </VBtn>
              </div>
            </VCard>

            <div class="d-flex justify-center mt-2">
              <VPagination
                :model-value="page"
                :length="Math.ceil(totalRecords / itemsPerPage) || 1"
                size="small"
                @update:model-value="(newPage) => { page = newPage; fetchContributions(); }"
              />
            </div>
          </template>

          <VAlert v-else type="info" variant="tonal" class="rounded-lg text-xs">
            No hay compromisos de pago para mostrar.
          </VAlert>
        </div>
      </VCard>
    </div>

    <!-- Diálogo Pegado Inteligente -->
    <SmartPasteContributionDialog
      v-model="showSmartPasteDialog"
      @imported="fetchContributions"
    />

    <!-- Diálogo Formulario Manual (Crear / Editar) -->
    <ContributionFormDialog
      v-model="showFormDialog"
      :contribution="selectedContribution"
      @saved="fetchContributions"
    />

    <!-- Diálogo Marcar Pago -->
    <ContributionPaymentDialog
      v-model="showPaymentDialog"
      :contribution="selectedContribution"
      @saved="fetchContributions"
    />

    <!-- Diálogo Sincronización Bot -->
    <SeniatBotSyncDialog
      v-model="showBotDialog"
      @synced="fetchContributions"
    />
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.premium-table :deep(.v-data-table-header th) {
  background: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.05rem !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
}

.premium-table :deep(.v-data-table__td) {
  padding-block: 10px !important;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
}
</style>
