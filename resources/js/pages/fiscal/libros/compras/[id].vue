<script setup>
import axios from '@/plugins/axios'
import { Swal, toast } from '@/plugins/sweetalert'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const bookId = route.params.id

const loading = ref(false)
const syncing = ref(false)
const closing = ref(false)
const book = ref(null)
const search = ref('')

// Modal de edición / creación manual de fila
const showEditModal = ref(false)
const isSavingRow = ref(false)
const isCreatingRow = ref(false)

const entryForm = reactive({
  id: null,
  operation_date: '',
  rif: '',
  supplier_name: '',
  import_planilla_number: '',
  import_dossier_number: '',
  invoice_number: '',
  control_number: '',
  debit_note_number: '',
  credit_note_number: '',
  affected_invoice_number: '',
  document_type: 'FACTURA',
  transaction_type: '01-REG',
  total_purchases_with_vat: 0,
  exempt_purchases: 0,
  general_taxable_base: 0,
  general_aliquot: 0.16,
  general_vat: 0,
  reduced_taxable_base: 0,
  reduced_aliquot: 0.08,
  reduced_vat: 0,
  vat_withheld: 0,
  withholding_receipt_number: '',
})

const fetchBook = async () => {
  if (!bookId) return
  loading.value = true
  try {
    const response = await axios.get(`/purchase-books/${bookId}`)
    book.value = response.data?.data || null
  } catch (error) {
    console.error('Error al cargar libro de compras:', error)
    toast.error('No se pudo cargar el detalle del libro de compras.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchBook()
})

const isZoomActive = computed(() => {
  return Boolean(book.value?.enable_zoom)
})

const filteredEntries = computed(() => {
  const entries = book.value?.entries || []
  if (!search.value) return entries
  const term = search.value.toLowerCase()
  return entries.filter(
    (e) =>
      e.supplier_name?.toLowerCase().includes(term) ||
      e.rif?.toLowerCase().includes(term) ||
      e.invoice_number?.toLowerCase().includes(term) ||
      e.control_number?.toLowerCase().includes(term) ||
      e.withholding_receipt_number?.toLowerCase().includes(term)
  )
})

// Acciones principales
const handleSync = async () => {
  if (!bookId) return
  syncing.value = true
  try {
    const res = await axios.post(`/purchase-books/${bookId}/sync`)
    book.value = res.data?.data || book.value
    toast.success('Transacciones sincronizadas correctamente.')
  } catch (err) {
    console.error('Error al sincronizar:', err)
    toast.error(err.response?.data?.message || 'Error al sincronizar transacciones del libro.')
  } finally {
    syncing.value = false
  }
}

const handleExportExcel = () => {
  if (!bookId) return
  window.open(`/api/purchase-books/${bookId}/export/excel`, '_blank')
}

const handleExportPdf = () => {
  if (!bookId) return
  window.open(`/api/purchase-books/${bookId}/export/pdf`, '_blank')
}

const handleCloseBook = async () => {
  const result = await Swal.fire({
    title: '¿Cerrar Libro de Compras?',
    text: 'Al cerrar el libro quedará congelado con fines fiscales y no se permitirán más ediciones directas.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cerrar libro',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (!result.isConfirmed) return

  closing.value = true
  try {
    const res = await axios.post(`/purchase-books/${bookId}/close`)
    book.value = res.data?.data || book.value
    toast.success('Libro de compras cerrado exitosamente.')
  } catch (err) {
    console.error('Error al cerrar libro:', err)
    toast.error(err.response?.data?.message || 'Error al cerrar el libro.')
  } finally {
    closing.value = false
  }
}

// Edición y creación manual de fila
const openEditModal = (entry) => {
  isCreatingRow.value = false
  Object.assign(entryForm, {
    id: entry.id,
    operation_date: entry.operation_date || '',
    rif: entry.rif || '',
    supplier_name: entry.supplier_name || '',
    import_planilla_number: entry.import_planilla_number || '',
    import_dossier_number: entry.import_dossier_number || '',
    invoice_number: entry.invoice_number || '',
    control_number: entry.control_number || '',
    debit_note_number: entry.debit_note_number || '',
    credit_note_number: entry.credit_note_number || '',
    affected_invoice_number: entry.affected_invoice_number || '',
    document_type: entry.document_type || 'FACTURA',
    transaction_type: entry.transaction_type || '01-REG',
    total_purchases_with_vat: entry.total_purchases_with_vat || 0,
    exempt_purchases: entry.exempt_purchases || 0,
    general_taxable_base: entry.general_taxable_base || 0,
    general_aliquot: entry.general_aliquot ?? 0.16,
    general_vat: entry.general_vat || 0,
    reduced_taxable_base: entry.reduced_taxable_base || 0,
    reduced_aliquot: entry.reduced_aliquot ?? 0.08,
    reduced_vat: entry.reduced_vat || 0,
    vat_withheld: entry.vat_withheld || 0,
    withholding_receipt_number: entry.withholding_receipt_number || '',
  })
  showEditModal.value = true
}

const openCreateModal = () => {
  isCreatingRow.value = true
  Object.assign(entryForm, {
    id: null,
    operation_date: book.value?.start_date || '',
    rif: '',
    supplier_name: '',
    import_planilla_number: '',
    import_dossier_number: '',
    invoice_number: '',
    control_number: '',
    debit_note_number: '',
    credit_note_number: '',
    affected_invoice_number: '',
    document_type: 'FACTURA',
    transaction_type: '01-REG',
    total_purchases_with_vat: 0,
    exempt_purchases: 0,
    general_taxable_base: 0,
    general_aliquot: 0.16,
    general_vat: 0,
    reduced_taxable_base: 0,
    reduced_aliquot: 0.08,
    reduced_vat: 0,
    vat_withheld: 0,
    withholding_receipt_number: '',
  })
  showEditModal.value = true
}

const saveEntry = async () => {
  isSavingRow.value = true
  try {
    if (isCreatingRow.value) {
      await axios.post(`/purchase-books/${bookId}/entries`, entryForm)
      toast.success('Operación agregada exitosamente.')
    } else {
      await axios.put(`/purchase-books/${bookId}/entries/${entryForm.id}`, entryForm)
      toast.success('Operación actualizada exitosamente.')
    }
    showEditModal.value = false
    await fetchBook()
  } catch (err) {
    console.error('Error al guardar fila:', err)
    toast.error(err.response?.data?.message || 'Error al guardar los cambios en la operación.')
  } finally {
    isSavingRow.value = false
  }
}

const deleteEntry = async (entry) => {
  const result = await Swal.fire({
    title: '¿Eliminar operación?',
    text: `Se eliminará la operación N° ${entry.operation_number} (${entry.supplier_name}).`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(`/purchase-books/${bookId}/entries/${entry.id}`)
    toast.success('Operación eliminada.')
    await fetchBook()
  } catch (err) {
    console.error('Error al eliminar fila:', err)
    toast.error(err.response?.data?.message || 'No se pudo eliminar la operación.')
  }
}

// Auto cálculo de IVA al cambiar bases en el formulario
const onGeneralBaseChange = () => {
  const base = parseFloat(entryForm.general_taxable_base) || 0
  const ali = parseFloat(entryForm.general_aliquot) || 0.16
  entryForm.general_vat = +(base * ali).toFixed(2)
  updateTotalPurchases()
}

const onReducedBaseChange = () => {
  const base = parseFloat(entryForm.reduced_taxable_base) || 0
  const ali = parseFloat(entryForm.reduced_aliquot) || 0.08
  entryForm.reduced_vat = +(base * ali).toFixed(2)
  updateTotalPurchases()
}

const updateTotalPurchases = () => {
  const exempt = parseFloat(entryForm.exempt_purchases) || 0
  const genBase = parseFloat(entryForm.general_taxable_base) || 0
  const genVat = parseFloat(entryForm.general_vat) || 0
  const redBase = parseFloat(entryForm.reduced_taxable_base) || 0
  const redVat = parseFloat(entryForm.reduced_vat) || 0
  entryForm.total_purchases_with_vat = +(exempt + genBase + genVat + redBase + redVat).toFixed(2)
}

const formatNumber = (val) => {
  const num = Number(val) || 0
  return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}
</script>

<template>
  <div class="purchase-book-view pa-4">
    <!-- Barra Superior de Navegación con Flecha Atrás -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VBtn
            icon
            variant="tonal"
            color="primary"
            size="small"
            @click="router.push('/fiscal/libros/compras')"
          >
            <VIcon icon="tabler-arrow-left" size="20" />
          </VBtn>

          <div>
            <div class="d-flex align-center gap-2">
              <h1 class="text-h5 font-weight-bold mb-0">
                Libro de Compras — {{ book?.period_label || 'Cargando...' }}
              </h1>
              <VChip
                v-if="book"
                :color="book?.is_closed ? 'error' : 'success'"
                variant="tonal"
                size="small"
                class="font-weight-bold"
              >
                <VIcon start size="14">{{ book?.is_closed ? 'tabler-lock' : 'tabler-lock-open' }}</VIcon>
                {{ book?.is_closed ? 'CERRADO' : 'ABIERTO' }}
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Período: <strong>{{ book?.start_date }}</strong> al <strong>{{ book?.end_date }}</strong>
              &nbsp;|&nbsp; Condición: <strong>{{ book?.company_info?.taxpayer_type }}</strong>
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            v-if="book?.is_open"
            color="primary"
            variant="outlined"
            size="small"
            :loading="syncing"
            prepend-icon="tabler-refresh"
            @click="handleSync"
          >
            Sincronizar
          </VBtn>

          <VBtn
            v-if="book?.is_open"
            color="secondary"
            variant="tonal"
            size="small"
            prepend-icon="tabler-plus"
            @click="openCreateModal"
          >
            Agregar Fila
          </VBtn>

          <VBtn
            color="success"
            variant="outlined"
            size="small"
            prepend-icon="tabler-file-spreadsheet"
            @click="handleExportExcel"
          >
            Excel
          </VBtn>

          <VBtn
            color="error"
            variant="outlined"
            size="small"
            prepend-icon="tabler-file-type-pdf"
            @click="handleExportPdf"
          >
            PDF
          </VBtn>

          <VBtn
            v-if="book?.is_open"
            color="error"
            variant="flat"
            size="small"
            :loading="closing"
            prepend-icon="tabler-lock"
            @click="handleCloseBook"
          >
            Cerrar Libro
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Membrete Fiscal de la Empresa -->
    <VCard class="mb-4 pa-4 rounded-lg bg-surface">
      <VRow align="center" justify="space-between">
        <VCol cols="12" md="8">
          <div class="text-body-2">
            <strong>Razón Social:</strong> {{ book?.company_info?.name || 'N/A' }} &nbsp;|&nbsp;
            <strong>R.I.F.:</strong> {{ book?.company_info?.rif || 'N/A' }}<br>
            <strong>Dirección Fiscal:</strong> {{ book?.company_info?.address || 'N/A' }}
          </div>
        </VCol>
        <VCol cols="12" md="4" class="d-flex justify-end align-center">
          <VChip v-if="isZoomActive" size="small" color="warning" class="font-weight-bold">
            <VIcon start size="14">tabler-percentage</VIcon>
            Alícuota Reducida Zoom (8%) Activa
          </VChip>
        </VCol>
      </VRow>
    </VCard>

    <!-- Buscador Rápido y Tabla de Transacciones -->
    <VCard class="rounded-lg pa-4">
      <VRow density="compact" class="mb-3" align="center">
        <VCol cols="12" sm="6" md="4">
          <VTextField
            v-model="search"
            placeholder="Buscar por Proveedor, RIF, N° Factura..."
            density="compact"
            variant="outlined"
            prepend-inner-icon="tabler-search"
            hide-details
            clearable
          />
        </VCol>
        <VCol cols="12" sm="6" md="8" class="d-flex align-center justify-end text-caption text-medium-emphasis">
          Mostrando {{ filteredEntries.length }} de {{ book?.entries?.length || 0 }} operaciones
        </VCol>
      </VRow>

      <!-- Tabla de Transacciones Tributarias -->
      <div class="table-responsive border rounded-lg bg-surface">
        <VProgressLinear v-if="loading" indeterminate color="primary" />
        <table class="table-book">
          <thead>
            <tr class="header-group">
              <th colspan="13" class="text-center bg-primary-dark">DATOS GENERALES DE LA TRANSACCIÓN</th>
              <th colspan="2" class="text-center bg-primary">TOTALES</th>
              <th colspan="3" class="text-center bg-primary">ALÍCUOTA GENERAL (16%)</th>
              <th v-if="isZoomActive" colspan="3" class="text-center bg-warning text-dark font-weight-bold">
                ZOOM (ALÍCUOTA REDUCIDA 8%)
              </th>
              <th colspan="2" class="text-center bg-primary">RETENCIONES</th>
              <th v-if="book?.is_open" class="text-center bg-primary-dark">ACCIÓN</th>
            </tr>
            <tr class="header-columns">
              <th>Nro.</th>
              <th>Fecha de Operación</th>
              <th>R.I.F.</th>
              <th>Nombre o Razon Social</th>
              <th>Número Planilla Importación (C80 o C81)</th>
              <th>Número Expediente Importación</th>
              <th>Número de Factura</th>
              <th>Número de Control</th>
              <th>Número de Nota Débito</th>
              <th>Número de Nota Crédito</th>
              <th>Número de Factura Afectada</th>
              <th>Tipo de Documento</th>
              <th>Tipo Transacción</th>
              <th class="text-right">Total Compras incluyendo IVA</th>
              <th class="text-right">Compras sin derecho a Crédito</th>
              <th class="text-right">Base Imponible</th>
              <th class="text-center">% Alicuota</th>
              <th class="text-right">IVA General</th>
              <template v-if="isZoomActive">
                <th class="text-right zoom-header">Base Imponible</th>
                <th class="text-center zoom-header">% Alicuota</th>
                <th class="text-right zoom-header">IVA General</th>
              </template>
              <th class="text-right">Iva Retenido</th>
              <th>Número de Comprobante</th>
              <th v-if="book?.is_open" class="text-center">Editar</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="entry in filteredEntries"
              :key="entry.id"
              :class="{ 'row-manual': entry.is_manual_override }"
            >
              <td class="text-center font-weight-bold">{{ entry.operation_number }}</td>
              <td class="text-no-wrap">{{ entry.operation_date }}</td>
              <td class="text-no-wrap font-weight-medium">{{ entry.rif }}</td>
              <td class="text-truncate" style="max-width: 220px;" :title="entry.supplier_name">
                {{ entry.supplier_name }}
              </td>
              <td>{{ entry.import_planilla_number || '-' }}</td>
              <td>{{ entry.import_dossier_number || '-' }}</td>
              <td class="font-weight-medium">{{ entry.invoice_number || '-' }}</td>
              <td>{{ entry.control_number || '-' }}</td>
              <td>{{ entry.debit_note_number || '-' }}</td>
              <td>{{ entry.credit_note_number || '-' }}</td>
              <td>{{ entry.affected_invoice_number || '-' }}</td>
              <td><VChip size="x-small" variant="tonal">{{ entry.document_type }}</VChip></td>
              <td class="text-center"><code>{{ entry.transaction_type }}</code></td>
              <td class="text-right font-weight-medium font-tabular">{{ formatNumber(entry.total_purchases_with_vat) }}</td>
              <td class="text-right font-tabular">{{ formatNumber(entry.exempt_purchases) }}</td>
              <td class="text-right font-tabular">{{ formatNumber(entry.general_taxable_base) }}</td>
              <td class="text-center font-tabular">{{ entry.general_aliquot > 0 ? (entry.general_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
              <td class="text-right font-tabular text-success">{{ formatNumber(entry.general_vat) }}</td>
              <template v-if="isZoomActive">
                <td class="text-right zoom-cell font-tabular">{{ formatNumber(entry.reduced_taxable_base) }}</td>
                <td class="text-center zoom-cell font-tabular">{{ entry.reduced_aliquot > 0 ? (entry.reduced_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                <td class="text-right zoom-cell font-tabular">{{ formatNumber(entry.reduced_vat) }}</td>
              </template>
              <td class="text-right text-error font-weight-bold font-tabular">{{ formatNumber(entry.vat_withheld) }}</td>
              <td class="text-center font-weight-medium">{{ entry.withholding_receipt_number || '-' }}</td>
              <td v-if="book?.is_open" class="text-center">
                <div class="d-flex align-center justify-center gap-1">
                  <VBtn icon variant="text" size="x-small" color="primary" @click="openEditModal(entry)">
                    <VIcon size="16">tabler-pencil</VIcon>
                  </VBtn>
                  <VBtn icon variant="text" size="x-small" color="error" @click="deleteEntry(entry)">
                    <VIcon size="16">tabler-trash</VIcon>
                  </VBtn>
                </div>
              </td>
            </tr>

            <tr v-if="filteredEntries.length === 0">
              <td :colspan="isZoomActive ? 24 : 21" class="text-center py-6 text-medium-emphasis">
                No se encontraron operaciones registradas en este período.
              </td>
            </tr>
          </tbody>

          <!-- Fila de Totales del Libro -->
          <tfoot>
            <tr class="footer-totals">
              <td colspan="13" class="text-right font-weight-bold">TOTALES GENERALES:</td>
              <td class="text-right font-weight-bold text-primary font-tabular">{{ formatNumber(book?.total_purchases_with_vat) }}</td>
              <td class="text-right font-weight-bold font-tabular">{{ formatNumber(book?.total_exempt_purchases) }}</td>
              <td class="text-right font-weight-bold font-tabular">{{ formatNumber(book?.total_general_taxable_base) }}</td>
              <td></td>
              <td class="text-right font-weight-bold text-success font-tabular">{{ formatNumber(book?.total_general_vat) }}</td>
              <template v-if="isZoomActive">
                <td class="text-right font-weight-bold zoom-cell font-tabular">{{ formatNumber(book?.total_reduced_taxable_base) }}</td>
                <td class="zoom-cell"></td>
                <td class="text-right font-weight-bold zoom-cell font-tabular">{{ formatNumber(book?.total_reduced_vat) }}</td>
              </template>
              <td class="text-right font-weight-bold text-error font-tabular">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td :colspan="book?.is_open ? 2 : 1"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Cuadro RESUMEN LIBRO DE COMPRAS (SENIAT) -->
      <VCard variant="outlined" class="mt-4 pa-4 rounded-lg">
        <div class="d-flex align-center gap-2 mb-3">
          <VIcon icon="tabler-clipboard-list" color="primary" />
          <span class="text-subtitle-1 font-weight-bold">RESUMEN LIBRO DE COMPRAS (SENIAT)</span>
        </div>

        <VTable density="compact" class="summary-table">
          <thead>
            <tr class="bg-light">
              <th class="font-weight-bold">Concepto</th>
              <th class="text-right font-weight-bold">Base Imponible</th>
              <th class="text-right font-weight-bold">Crédito Fiscal</th>
              <th class="text-right font-weight-bold">Retenciones IVA</th>
              <th class="text-right font-weight-bold">Anticipo IVA</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Total Compras Exentas y/o Sin Derecho a Crédito Fiscal:</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_exempt_purchases) }}</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Importación Afectas solo Alicuota General:</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Importación Afectas en Alicuota General + Adicional:</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Importación Afectas solo Alicuota Reducida:</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Internas Afectas solo Alicuota General:</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_general_taxable_base) }}</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_general_vat) }}</td>
              <td class="text-right font-tabular text-error font-weight-bold">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Internas Afectas en Alicuota General + Adicional:</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Compras Internas Afectas solo Alicuota Reducida:</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_reduced_taxable_base) }}</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_reduced_vat) }}</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr class="font-weight-bold bg-light">
              <td>TOTALES</td>
              <td class="text-right font-tabular">
                {{
                  formatNumber(
                    (Number(book?.total_exempt_purchases) || 0) +
                    (Number(book?.total_general_taxable_base) || 0) +
                    (Number(book?.total_reduced_taxable_base) || 0)
                  )
                }}
              </td>
              <td class="text-right font-tabular">
                {{
                  formatNumber(
                    (Number(book?.total_general_vat) || 0) +
                    (Number(book?.total_reduced_vat) || 0)
                  )
                }}
              </td>
              <td class="text-right text-error font-tabular">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCard>

    <!-- Modal Editar / Agregar Operación -->
    <VDialog v-model="showEditModal" max-width="850">
      <VCard class="rounded-lg">
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span class="text-h6 font-weight-bold">
            {{ isCreatingRow ? 'Agregar Operación al Libro' : `Editar Operación N° ${entryForm.id}` }}
          </span>
          <VBtn icon variant="text" size="small" @click="showEditModal = false">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-4">
          <VForm @submit.prevent="saveEntry">
            <VRow density="compact">
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.operation_date" type="date" label="Fecha" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.rif" label="R.I.F." variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.supplier_name" label="Razón Social / Proveedor" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.invoice_number" label="N° Factura" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.control_number" label="N° Control" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entryForm.document_type"
                  :items="['FACTURA', 'NOTA DÉBITO', 'NOTA CRÉDITO', 'RETENCIÓN']"
                  label="Tipo Documento"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.transaction_type" label="Tipo Trans. (ej. 01-REG)" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.exempt_purchases" type="number" step="0.01" label="Monto Exento" variant="outlined" density="compact" @input="updateTotalPurchases" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.general_taxable_base" type="number" step="0.01" label="Base Imponible General (16%)" variant="outlined" density="compact" @input="onGeneralBaseChange" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.general_vat" type="number" step="0.01" label="IVA General (16%)" variant="outlined" density="compact" @input="updateTotalPurchases" />
              </VCol>

              <template v-if="isZoomActive">
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.reduced_taxable_base" type="number" step="0.01" label="Base Reducida (Zoom 8%)" variant="outlined" density="compact" @input="onReducedBaseChange" />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.reduced_vat" type="number" step="0.01" label="IVA Reducido (Zoom 8%)" variant="outlined" density="compact" @input="updateTotalPurchases" />
                </VCol>
              </template>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.total_purchases_with_vat" type="number" step="0.01" label="Total Compras c/IVA" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.vat_withheld" type="number" step="0.01" label="IVA Retenido" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_number" label="N° Comprobante Retención" variant="outlined" density="compact" />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-2 mt-4">
              <VBtn variant="text" color="secondary" @click="showEditModal = false">Cancelar</VBtn>
              <VBtn color="primary" type="submit" :loading="isSavingRow">Guardar Operación</VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.purchase-book-view {
  width: 100%;
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.table-book {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.76rem;
  white-space: nowrap;
}

.table-book th,
.table-book td {
  padding: 6px 10px;
  border: 1px solid rgba(var(--v-border-color), 0.12);
}

.table-book thead th {
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.70rem;
  letter-spacing: 0.03em;
}

.header-group th {
  color: #fff;
  padding: 8px 12px;
}

.bg-primary-dark {
  background-color: #2b3a4a !important;
  color: #fff !important;
}

.bg-primary {
  background-color: #3b5066 !important;
  color: #fff !important;
}

.bg-warning {
  background-color: #ffeb3b !important;
  color: #000 !important;
}

.zoom-header {
  background-color: #fff9c4 !important;
  color: #000 !important;
  font-weight: 700;
}

.zoom-cell {
  background-color: rgba(255, 235, 59, 0.12) !important;
}

.footer-totals td {
  background-color: rgba(var(--v-theme-surface-variant), 0.25);
  padding: 8px 10px;
}

.row-manual {
  background-color: rgba(var(--v-theme-info), 0.06);
}

.summary-table td,
.summary-table th {
  padding: 6px 12px !important;
  font-size: 0.8rem;
}

.font-tabular {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
