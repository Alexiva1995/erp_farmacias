<script setup>
import PurchaseBookDetailDialog from '@/components/dialogs/PurchaseBookDetailDialog.vue'
import axios from '@/plugins/axios'
import { Swal, toast } from '@/plugins/sweetalert'
import { onMounted, reactive, ref } from 'vue'

const loading = ref(false)
const books = ref([])
const totalRecords = ref(0)
const page = ref(1)
const itemsPerPage = ref(15)

// Filtros
const filters = reactive({
  year: new Date().getFullYear(),
  month: null,
  status: null,
})

// Modal de Detalle
const showDetailModal = ref(false)
const selectedBookId = ref(null)

// Modal de Generación Manual de Período
const showGenerateModal = ref(false)
const isGenerating = ref(false)
const generateForm = reactive({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
  fortnight: 1,
})

const monthsList = [
  { value: 1, title: 'Enero' },
  { value: 2, title: 'Febrero' },
  { value: 3, title: 'Marzo' },
  { value: 4, title: 'Abril' },
  { value: 5, title: 'Mayo' },
  { value: 6, title: 'Junio' },
  { value: 7, title: 'Julio' },
  { value: 8, title: 'Agosto' },
  { value: 9, title: 'Septiembre' },
  { value: 10, title: 'Octubre' },
  { value: 11, title: 'Noviembre' },
  { value: 12, title: 'Diciembre' },
]

const yearsList = [2024, 2025, 2026, 2027, 2028]

const tableHeaders = [
  { title: 'Período', key: 'period_label', sortable: false },
  { title: 'Rango de Fechas', key: 'dates', sortable: false },
  { title: 'Tipo', key: 'period_type', sortable: false, align: 'center' },
  { title: 'Operaciones', key: 'entries_count', sortable: false, align: 'center' },
  { title: 'Total Compras c/IVA', key: 'total_purchases_with_vat', sortable: false, align: 'end' },
  { title: 'Exento', key: 'total_exempt_purchases', sortable: false, align: 'end' },
  { title: 'Base Imponible (16%)', key: 'total_general_taxable_base', sortable: false, align: 'end' },
  { title: 'Crédito Fiscal (IVA)', key: 'total_general_vat', sortable: false, align: 'end' },
  { title: 'IVA Retenido', key: 'total_vat_withheld', sortable: false, align: 'end' },
  { title: 'Estado', key: 'status', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center', width: '160px' },
]

const fetchBooks = async () => {
  loading.value = true
  try {
    const response = await axios.get('/purchase-books', {
      params: {
        page: page.value,
        per_page: itemsPerPage.value,
        year: filters.year || undefined,
        month: filters.month || undefined,
        status: filters.status || undefined,
      },
    })
    books.value = response.data?.data || []
    totalRecords.value = response.data?.pagination?.total || 0
  } catch (error) {
    console.error('Error al cargar libros de compras:', error)
    toast.error('Ocurrió un error al cargar los libros de compras.')
  } finally {
    loading.value = false
  }
}

const openBookDetail = (id) => {
  selectedBookId.value = id
  showDetailModal.value = true
}

const handleExportExcel = (id) => {
  window.open(`/api/purchase-books/${id}/export/excel`, '_blank')
}

const handleExportPdf = (id) => {
  window.open(`/api/purchase-books/${id}/export/pdf`, '_blank')
}

const handleGeneratePeriod = async () => {
  isGenerating.value = true
  try {
    const res = await axios.post('/purchase-books/generate-period', generateForm)
    toast.success('Período generado exitosamente.')
    showGenerateModal.value = false
    await fetchBooks()
    if (res.data?.data?.id) {
      openBookDetail(res.data.data.id)
    }
  } catch (err) {
    console.error('Error al generar período:', err)
    toast.error(err.response?.data?.message || 'No se pudo generar el período.')
  } finally {
    isGenerating.value = false
  }
}

const formatNumber = (val) => {
  const num = Number(val) || 0
  return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

onMounted(() => {
  fetchBooks()
})
</script>

<template>
  <div class="purchase-books-page">
    <!-- Encabezado de la Vista -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded size="44">
            <VIcon icon="tabler-book" size="26" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-bold mb-0">Libro de Compras</h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Gestión fiscal reglamentaria de facturas, retenciones de IVA, alícuotas y cuadro resumen SENIAT.
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VBtn color="primary" prepend-icon="tabler-calendar-plus" @click="showGenerateModal = true">
            Nuevo Período
          </VBtn>
          <VBtn icon variant="outlined" color="secondary" :loading="loading" @click="fetchBooks">
            <VIcon icon="tabler-refresh" />
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Filtros -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <VRow density="compact" align="center">
        <VCol cols="12" sm="4" md="3">
          <VSelect
            v-model="filters.year"
            :items="yearsList"
            label="Año Fiscal"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            @update:model-value="fetchBooks"
          />
        </VCol>
        <VCol cols="12" sm="4" md="3">
          <VSelect
            v-model="filters.month"
            :items="monthsList"
            item-title="title"
            item-value="value"
            label="Mes"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            @update:model-value="fetchBooks"
          />
        </VCol>
        <VCol cols="12" sm="4" md="3">
          <VSelect
            v-model="filters.status"
            :items="[
              { title: 'Todos', value: null },
              { title: 'Abiertos', value: 'open' },
              { title: 'Cerrados', value: 'closed' },
            ]"
            item-title="title"
            item-value="value"
            label="Estado"
            density="compact"
            variant="outlined"
            hide-details
            @update:model-value="fetchBooks"
          />
        </VCol>
        <VCol cols="12" sm="12" md="3" class="d-flex justify-end">
          <VBtn variant="text" color="secondary" @click="Object.assign(filters, { year: new Date().getFullYear(), month: null, status: null }); fetchBooks()">
            Limpiar Filtros
          </VBtn>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla Maestro de Libros -->
    <VCard class="rounded-lg">
      <VDataTable
        :headers="tableHeaders"
        :items="books"
        :loading="loading"
        density="comfortable"
        hover
        no-data-text="No se encontraron libros de compras para los filtros seleccionados."
      >
        <template #item.period_label="{ item }">
          <div class="d-flex flex-column">
            <span class="font-weight-bold text-high-emphasis">{{ item.period_label }}</span>
            <span class="text-caption text-medium-emphasis">
              {{ item.company_info?.name }}
            </span>
          </div>
        </template>

        <template #item.dates="{ item }">
          <span class="text-caption">
            {{ item.start_date }} al {{ item.end_date }}
          </span>
        </template>

        <template #item.period_type="{ item }">
          <VChip size="small" variant="tonal" :color="item.period_type === 'fortnightly' ? 'info' : 'primary'">
            {{ item.period_type === 'fortnightly' ? 'Quincenal' : 'Mensual' }}
          </VChip>
        </template>

        <template #item.entries_count="{ item }">
          <VChip size="x-small" variant="flat" color="secondary" class="font-weight-bold">
            {{ item.entries_count }} ops
          </VChip>
        </template>

        <template #item.total_purchases_with_vat="{ item }">
          <span class="font-weight-bold text-high-emphasis">
            {{ formatNumber(item.total_purchases_with_vat) }}
          </span>
        </template>

        <template #item.total_exempt_purchases="{ item }">
          <span>{{ formatNumber(item.total_exempt_purchases) }}</span>
        </template>

        <template #item.total_general_taxable_base="{ item }">
          <span>{{ formatNumber(item.total_general_taxable_base) }}</span>
        </template>

        <template #item.total_general_vat="{ item }">
          <span class="text-success font-weight-medium">{{ formatNumber(item.total_general_vat) }}</span>
        </template>

        <template #item.total_vat_withheld="{ item }">
          <span class="text-error font-weight-bold">{{ formatNumber(item.total_vat_withheld) }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            size="small"
            :color="item.is_closed ? 'error' : 'success'"
            variant="tonal"
            class="font-weight-bold"
          >
            <VIcon start size="14">{{ item.is_closed ? 'tabler-lock' : 'tabler-lock-open' }}</VIcon>
            {{ item.is_closed ? 'Cerrado' : 'Abierto' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex align-center justify-center gap-1">
            <!-- Ojito para Ver Detalle -->
            <VTooltip text="Ver Transacciones del Libro" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon
                  variant="tonal"
                  size="small"
                  color="primary"
                  @click="openBookDetail(item.id)"
                >
                  <VIcon size="18">tabler-eye</VIcon>
                </VBtn>
              </template>
            </VTooltip>

            <!-- Exportar Excel -->
            <VTooltip text="Exportar a Excel" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon
                  variant="text"
                  size="small"
                  color="success"
                  @click="handleExportExcel(item.id)"
                >
                  <VIcon size="18">tabler-file-spreadsheet</VIcon>
                </VBtn>
              </template>
            </VTooltip>

            <!-- Exportar PDF -->
            <VTooltip text="Exportar a PDF" location="top">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  icon
                  variant="text"
                  size="small"
                  color="error"
                  @click="handleExportPdf(item.id)"
                >
                  <VIcon size="18">tabler-file-type-pdf</VIcon>
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Modal Detalle del Libro de Compras -->
    <PurchaseBookDetailDialog
      v-model="showDetailModal"
      :book-id="selectedBookId"
      @book-updated="fetchBooks"
    />

    <!-- Modal para Generar Período Específico -->
    <VDialog v-model="showGenerateModal" max-width="480px" persistent>
      <VCard>
        <VCardTitle class="bg-primary text-white py-3 px-4 d-flex align-center justify-space-between">
          <span class="text-subtitle-1 font-weight-bold text-white">Generar Período de Libro</span>
          <VBtn icon variant="text" color="white" density="compact" @click="showGenerateModal = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VCardText class="pa-4">
          <VForm @submit.prevent="handleGeneratePeriod">
            <VRow density="compact">
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="generateForm.year"
                  :items="yearsList"
                  label="Año *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="generateForm.month"
                  :items="monthsList"
                  item-title="title"
                  item-value="value"
                  label="Mes *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="generateForm.fortnight"
                  :items="[
                    { title: 'Quincena 1 (Del 01 al 15)', value: 1 },
                    { title: 'Quincena 2 (Del 16 al fin de mes)', value: 2 },
                  ]"
                  item-title="title"
                  item-value="value"
                  label="Quincena (si aplica Sujeto Pasivo Especial)"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
            </VRow>
            <div class="d-flex justify-end gap-2 mt-4">
              <VBtn variant="text" color="secondary" @click="showGenerateModal = false">Cancelar</VBtn>
              <VBtn color="primary" type="submit" :loading="isGenerating">Generar Período</VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.purchase-books-page {
  width: 100%;
}
</style>
