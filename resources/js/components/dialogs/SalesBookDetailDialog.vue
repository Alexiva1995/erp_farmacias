<script setup>
import axios from '@/plugins/axios'
import { Swal, toast } from '@/plugins/sweetalert'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  bookId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'book-updated'])

const loading = ref(false)
const syncing = ref(false)
const closing = ref(false)
const book = ref(null)
const search = ref('')

// Modal de edición de fila
const showEditModal = ref(false)
const isSavingRow = ref(false)
const isCreatingRow = ref(false)

const entryForm = reactive({
  id: null,
  operation_date: '',
  identification: '',
  client_name: '',
  export_planilla_number: '',
  document_type: 'REPORTE Z',
  invoice_number: '',
  control_number: '',
  z_report_number: '',
  z_first_invoice: '',
  z_last_invoice: '',
  machine_serial: '',
  debit_note_number: '',
  credit_note_number: '',
  affected_invoice_number: '',
  transaction_type: '01-REG',
  total_sales_with_vat: 0,
  non_taxable_sales: 0,
  taxpayer_taxable_base: 0,
  taxpayer_aliquot: 0.16,
  taxpayer_vat: 0,
  non_taxpayer_taxable_base: 0,
  non_taxpayer_aliquot: 0.08,
  non_taxpayer_vat: 0,
  vat_withheld: 0,
  withholding_receipt_number: '',
  withholding_receipt_date: '',
  igtf_amount: 0,
})

const fetchBook = async () => {
  if (!props.bookId) return
  loading.value = true
  try {
    const response = await axios.get(`/sales-books/${props.bookId}`)
    book.value = response.data?.data || null
  } catch (error) {
    console.error('Error al cargar libro de ventas:', error)
    toast.error('No se pudo cargar el detalle del libro de ventas.')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.modelValue, props.bookId],
  ([val, id]) => {
    if (val && id) {
      fetchBook()
    }
  },
  { immediate: true }
)

const isZoomActive = computed(() => {
  return Boolean(book.value?.enable_zoom)
})

const filteredEntries = computed(() => {
  const entries = book.value?.entries || []
  if (!search.value) return entries
  const term = search.value.toLowerCase()
  return entries.filter(
    (e) =>
      e.client_name?.toLowerCase().includes(term) ||
      e.identification?.toLowerCase().includes(term) ||
      e.z_report_number?.toLowerCase().includes(term) ||
      e.credit_note_number?.toLowerCase().includes(term) ||
      e.affected_invoice_number?.toLowerCase().includes(term) ||
      e.withholding_receipt_number?.toLowerCase().includes(term)
  )
})

// Acciones principales
const handleSync = async () => {
  if (!props.bookId) return
  syncing.value = true
  try {
    const res = await axios.post(`/sales-books/${props.bookId}/sync`)
    book.value = res.data?.data
    toast.success('Reportes Z y Notas de Crédito sincronizados exitosamente.')
    emit('book-updated')
  } catch (err) {
    console.error('Error al sincronizar:', err)
    toast.error(err.response?.data?.message || 'Error al sincronizar operaciones de ventas.')
  } finally {
    syncing.value = false
  }
}

const handleExportExcel = () => {
  if (!props.bookId) return
  window.open(`/api/sales-books/${props.bookId}/export/excel`, '_blank')
}

const handleExportPdf = () => {
  if (!props.bookId) return
  window.open(`/api/sales-books/${props.bookId}/export/pdf`, '_blank')
}

const handleCloseBook = async () => {
  const result = await Swal.fire({
    title: '¿Cerrar Libro de Ventas?',
    text: 'Una vez cerrado el libro, no se podrán modificar ni resincronizar transacciones de este período.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, cerrar libro',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (!result.isConfirmed) return

  closing.value = true
  try {
    const res = await axios.post(`/sales-books/${props.bookId}/close`)
    book.value = res.data?.data
    toast.success('El libro de ventas ha sido cerrado definitivamente.')
    emit('book-updated')
  } catch (err) {
    console.error('Error al cerrar libro:', err)
    toast.error(err.response?.data?.message || 'No se pudo cerrar el libro de ventas.')
  } finally {
    closing.value = false
  }
}

// Modal de edición y adición
const openEditModal = (entry) => {
  isCreatingRow.value = false
  Object.assign(entryForm, {
    id: entry.id,
    operation_date: entry.operation_date || '',
    identification: entry.identification || '',
    client_name: entry.client_name || '',
    export_planilla_number: entry.export_planilla_number || '',
    document_type: entry.document_type || 'REPORTE Z',
    invoice_number: entry.invoice_number || '',
    control_number: entry.control_number || '',
    z_report_number: entry.z_report_number || '',
    z_first_invoice: entry.z_first_invoice || '',
    z_last_invoice: entry.z_last_invoice || '',
    machine_serial: entry.machine_serial || '',
    debit_note_number: entry.debit_note_number || '',
    credit_note_number: entry.credit_note_number || '',
    affected_invoice_number: entry.affected_invoice_number || '',
    transaction_type: entry.transaction_type ? entry.transaction_type.replace('REGISTRO', 'REG') : '01-REG',
    total_sales_with_vat: entry.total_sales_with_vat || 0,
    non_taxable_sales: entry.non_taxable_sales || 0,
    taxpayer_taxable_base: entry.taxpayer_taxable_base || 0,
    taxpayer_aliquot: entry.taxpayer_aliquot ?? 0.16,
    taxpayer_vat: entry.taxpayer_vat || 0,
    non_taxpayer_taxable_base: entry.non_taxpayer_taxable_base || 0,
    non_taxpayer_aliquot: entry.non_taxpayer_aliquot ?? 0.08,
    non_taxpayer_vat: entry.non_taxpayer_vat || 0,
    vat_withheld: entry.vat_withheld || 0,
    withholding_receipt_number: entry.withholding_receipt_number || '',
    withholding_receipt_date: entry.withholding_receipt_date || '',
    igtf_amount: entry.igtf_amount || 0,
  })
  showEditModal.value = true
}

const openCreateModal = () => {
  isCreatingRow.value = true
  Object.assign(entryForm, {
    id: null,
    operation_date: book.value?.start_date || '',
    identification: '',
    client_name: '',
    export_planilla_number: '',
    document_type: 'COMPROBANTE DE RETENCIÓN DE IVA',
    invoice_number: '',
    control_number: '',
    z_report_number: '',
    z_first_invoice: '',
    z_last_invoice: '',
    machine_serial: '',
    debit_note_number: '',
    credit_note_number: '',
    affected_invoice_number: '',
    transaction_type: '05-RETENCIÓN',
    total_sales_with_vat: 0,
    non_taxable_sales: 0,
    taxpayer_taxable_base: 0,
    taxpayer_aliquot: 0.16,
    taxpayer_vat: 0,
    non_taxpayer_taxable_base: 0,
    non_taxpayer_aliquot: 0.08,
    non_taxpayer_vat: 0,
    vat_withheld: 0,
    withholding_receipt_number: '',
    withholding_receipt_date: '',
    igtf_amount: 0,
  })
  showEditModal.value = true
}

const saveEntry = async () => {
  isSavingRow.value = true
  try {
    if (isCreatingRow.value) {
      await axios.post(`/sales-books/${props.bookId}/entries`, entryForm)
      toast.success('Operación agregada exitosamente.')
    } else {
      await axios.put(`/sales-books/${props.bookId}/entries/${entryForm.id}`, entryForm)
      toast.success('Operación actualizada exitosamente.')
    }
    showEditModal.value = false
    await fetchBook()
    emit('book-updated')
  } catch (err) {
    console.error('Error al guardar fila de ventas:', err)
    toast.error(err.response?.data?.message || 'Error al guardar los cambios en la operación.')
  } finally {
    isSavingRow.value = false
  }
}

const deleteEntry = async (entry) => {
  const result = await Swal.fire({
    title: '¿Eliminar operación?',
    text: `Se eliminará la operación N° ${entry.operation_number} (${entry.client_name || entry.document_type}).`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(`/sales-books/${props.bookId}/entries/${entry.id}`)
    toast.success('Operación eliminada.')
    await fetchBook()
    emit('book-updated')
  } catch (err) {
    console.error('Error al eliminar fila:', err)
    toast.error(err.response?.data?.message || 'No se pudo eliminar la operación.')
  }
}

// Auto cálculo de IVA al cambiar bases en formulario
const onTaxpayerBaseChange = () => {
  const base = parseFloat(entryForm.taxpayer_taxable_base) || 0
  const ali = parseFloat(entryForm.taxpayer_aliquot) || 0.16
  entryForm.taxpayer_vat = +(base * ali).toFixed(2)
  updateTotalSales()
}

const onNonTaxpayerBaseChange = () => {
  const base = parseFloat(entryForm.non_taxpayer_taxable_base) || 0
  const ali = parseFloat(entryForm.non_taxpayer_aliquot) || 0.08
  entryForm.non_taxpayer_vat = +(base * ali).toFixed(2)
  updateTotalSales()
}

const updateTotalSales = () => {
  const nonTax = parseFloat(entryForm.non_taxable_sales) || 0
  const tBase = parseFloat(entryForm.taxpayer_taxable_base) || 0
  const tVat = parseFloat(entryForm.taxpayer_vat) || 0
  const ntBase = parseFloat(entryForm.non_taxpayer_taxable_base) || 0
  const ntVat = parseFloat(entryForm.non_taxpayer_vat) || 0
  entryForm.total_sales_with_vat = +(nonTax + tBase + tVat + ntBase + ntVat).toFixed(2)
}

const formatNumber = (val) => {
  const num = Number(val) || 0
  return new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="96vw"
    scrollable
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="sales-book-dialog">
      <!-- Barra Superior -->
      <VCardTitle class="d-flex align-center justify-space-between py-3 px-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-report-money" size="24" />
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-bold text-white">
              Libro de Ventas — {{ book?.period_label }}
            </span>
            <span class="text-caption text-white opacity-80">
              Período: {{ book?.start_date }} al {{ book?.end_date }} | Condición: {{ book?.company_info?.taxpayer_type }}
            </span>
          </div>
        </div>

        <div class="d-flex align-center gap-2">
          <VChip
            :color="book?.is_closed ? 'error' : 'success'"
            variant="flat"
            size="small"
            class="font-weight-bold"
          >
            <VIcon start size="14">{{ book?.is_closed ? 'tabler-lock' : 'tabler-lock-open' }}</VIcon>
            {{ book?.is_closed ? 'CERRADO' : 'ABIERTO' }}
          </VChip>

          <VBtn icon variant="text" color="white" density="compact" @click="$emit('update:modelValue', false)">
            <VIcon icon="tabler-x" />
          </VBtn>
        </div>
      </VCardTitle>

      <!-- Membrete y Botonera -->
      <VCardText class="pa-4 bg-light">
        <VRow align="center" justify="space-between" class="mb-3">
          <VCol cols="12" md="7">
            <div class="text-body-2">
              <strong>Razón Social:</strong> {{ book?.company_info?.name }} &nbsp;|&nbsp;
              <strong>R.I.F.:</strong> {{ book?.company_info?.rif }}<br>
              <strong>Dirección Fiscal:</strong> {{ book?.company_info?.address }}
            </div>
          </VCol>

          <VCol cols="12" md="5" class="d-flex justify-end gap-2 flex-wrap">
            <VBtn
              v-if="book?.is_open"
              color="primary"
              variant="outlined"
              size="small"
              :loading="syncing"
              @click="handleSync"
            >
              <VIcon start size="16">tabler-refresh</VIcon>
              Sincronizar Z / NC
            </VBtn>

            <VBtn
              v-if="book?.is_open"
              color="secondary"
              variant="tonal"
              size="small"
              @click="openCreateModal"
            >
              <VIcon start size="16">tabler-plus</VIcon>
              Agregar Fila (Retención/NC)
            </VBtn>

            <VBtn color="success" variant="outlined" size="small" @click="handleExportExcel">
              <VIcon start size="16">tabler-file-spreadsheet</VIcon>
              Excel
            </VBtn>

            <VBtn color="error" variant="outlined" size="small" @click="handleExportPdf">
              <VIcon start size="16">tabler-file-type-pdf</VIcon>
              PDF
            </VBtn>

            <VBtn
              v-if="book?.is_open"
              color="error"
              variant="flat"
              size="small"
              :loading="closing"
              @click="handleCloseBook"
            >
              <VIcon start size="16">tabler-lock</VIcon>
              Cerrar Libro
            </VBtn>
          </VCol>
        </VRow>

        <!-- Filtro Rápido -->
        <VRow density="compact" class="mb-2">
          <VCol cols="12" sm="6" md="4">
            <VTextField
              v-model="search"
              placeholder="Buscar por Reporte Z, Cliente, RIF, NC..."
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-search"
              hide-details
              clearable
            />
          </VCol>
          <VCol cols="12" sm="6" md="8" class="d-flex align-center justify-end text-caption text-medium-emphasis">
            Mostrando {{ filteredEntries.length }} de {{ book?.entries?.length || 0 }} operaciones
            <VChip v-if="isZoomActive" size="x-small" color="warning" class="ms-2 font-weight-bold">
              Columnas No Contribuyentes (Zoom) Activas
            </VChip>
          </VCol>
        </VRow>

        <!-- Tabla de Transacciones -->
        <div class="table-responsive border rounded-lg bg-surface">
          <VProgressLinear v-if="loading" indeterminate color="primary" />
          <table class="table-book">
            <thead>
              <tr class="header-group">
                <th colspan="16" class="text-center bg-primary-dark">DATOS GENERALES DE LA TRANSACCIÓN</th>
                <th colspan="2" class="text-center bg-primary">TOTALES</th>
                <th colspan="3" class="text-center bg-primary">GRAVADAS A CONTRIBUYENTES (16%)</th>
                <th v-if="isZoomActive" colspan="3" class="text-center bg-warning text-dark font-weight-bold">
                  A NO CONTRIBUYENTES (ZOOM 8%)
                </th>
                <th colspan="3" class="text-center bg-primary">RETENCIONES</th>
                <th class="text-center bg-primary-dark">IGTF</th>
                <th v-if="book?.is_open" class="text-center bg-primary-dark">ACCIÓN</th>
              </tr>
              <tr class="header-columns">
                <th>Nro.</th>
                <th>FECHA</th>
                <th>CEDULA O R.I.F.</th>
                <th>NOMBRE O RAZÓN SOCIAL</th>
                <th>Nº PLANILLA DE EXPORTACIÓN</th>
                <th>TIPO DE DOCUMENTO</th>
                <th>Nº FACTURA</th>
                <th>Nº DE CONTROL</th>
                <th>NUMERO DE REPORTE Z</th>
                <th>Número de Factura inicial del Reporte Z</th>
                <th>Número de Factura Final del Reporte Z</th>
                <th>REG. MAQUINA</th>
                <th>Nº NOTA DE DEBITO</th>
                <th>Nº NOTA DE CREDITO</th>
                <th>Nº FACTURA AFECTADA</th>
                <th>TIPO DE TRANSACCION</th>
                <th class="text-right">TOTAL VENTAS INCLUYENDO IVA</th>
                <th class="text-right">VENTAS INTERNAS NO GRAVADAS</th>
                <th class="text-right">BASE IMPONIBLE</th>
                <th class="text-center">%IVA</th>
                <th class="text-right">IVA</th>
                <template v-if="isZoomActive">
                  <th class="text-right zoom-header">BASE IMPONIBLE</th>
                  <th class="text-center zoom-header">%IVA</th>
                  <th class="text-right zoom-header">IVA</th>
                </template>
                <th class="text-right">IVA RETENIDO</th>
                <th>Nº COMPROBANTE DE RETENCIÓN</th>
                <th>FECHA DE RECEPCION COMPROBANTE DE RETENCIÓN</th>
                <th class="text-right">IGTF</th>
                <th v-if="book?.is_open" class="text-center">Editar</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in filteredEntries"
                :key="entry.id"
                :class="{
                  'row-manual': entry.is_manual_override,
                  'row-credit-note': entry.transaction_type === '03-REGISTRO',
                  'row-retention': entry.transaction_type === '05-RETENCIÓN',
                }"
              >
                <td class="text-center font-weight-bold">{{ entry.operation_number }}</td>
                <td class="text-no-wrap">{{ entry.operation_date }}</td>
                <td class="text-no-wrap font-weight-medium">{{ entry.identification }}</td>
                <td class="text-truncate" style="max-width: 200px;" :title="entry.client_name">
                  {{ entry.client_name }}
                </td>
                <td>{{ entry.export_planilla_number || '-' }}</td>
                <td><VChip size="x-small" variant="tonal">{{ entry.document_type }}</VChip></td>
                <td>{{ entry.invoice_number || '-' }}</td>
                <td>{{ entry.control_number || '-' }}</td>
                <td class="text-center font-weight-bold">{{ entry.z_report_number || '-' }}</td>
                <td class="text-center">{{ entry.z_first_invoice || '-' }}</td>
                <td class="text-center">{{ entry.z_last_invoice || '-' }}</td>
                <td class="text-no-wrap">{{ entry.machine_serial || '-' }}</td>
                <td>{{ entry.debit_note_number || '-' }}</td>
                <td class="text-center text-error font-weight-bold">{{ entry.credit_note_number || '-' }}</td>
                <td class="text-center">{{ entry.affected_invoice_number || '-' }}</td>
                <td class="text-center"><code>{{ entry.transaction_type }}</code></td>
                <td class="text-right font-weight-medium" :class="{ 'text-error': entry.total_sales_with_vat < 0 }">
                  {{ formatNumber(entry.total_sales_with_vat) }}
                </td>
                <td class="text-right">{{ formatNumber(entry.non_taxable_sales) }}</td>
                <td class="text-right">{{ formatNumber(entry.taxpayer_taxable_base) }}</td>
                <td class="text-center">{{ entry.taxpayer_aliquot > 0 ? (entry.taxpayer_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                <td class="text-right">{{ formatNumber(entry.taxpayer_vat) }}</td>
                <template v-if="isZoomActive">
                  <td class="text-right zoom-cell">{{ formatNumber(entry.non_taxpayer_taxable_base) }}</td>
                  <td class="text-center zoom-cell">{{ entry.non_taxpayer_aliquot > 0 ? (entry.non_taxpayer_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                  <td class="text-right zoom-cell">{{ formatNumber(entry.non_taxpayer_vat) }}</td>
                </template>
                <td class="text-right text-error font-weight-bold">{{ formatNumber(entry.vat_withheld) }}</td>
                <td class="text-center font-weight-medium">{{ entry.withholding_receipt_number || '-' }}</td>
                <td class="text-center">{{ entry.withholding_receipt_date || '-' }}</td>
                <td class="text-right font-weight-medium">{{ formatNumber(entry.igtf_amount) }}</td>
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
                <td :colspan="isZoomActive ? 29 : 26" class="text-center py-6 text-medium-emphasis">
                  No se encontraron operaciones registradas en este período.
                </td>
              </tr>
            </tbody>

            <!-- Fila de Totales del Libro -->
            <tfoot>
              <tr class="footer-totals">
                <td colspan="16" class="text-right font-weight-bold">TOTALES GENERALES:</td>
                <td class="text-right font-weight-bold text-primary">{{ formatNumber(book?.total_sales_with_vat) }}</td>
                <td class="text-right font-weight-bold">{{ formatNumber(book?.total_non_taxable_sales) }}</td>
                <td class="text-right font-weight-bold">{{ formatNumber(book?.total_taxpayer_taxable_base) }}</td>
                <td></td>
                <td class="text-right font-weight-bold text-success">{{ formatNumber(book?.total_taxpayer_vat) }}</td>
                <template v-if="isZoomActive">
                  <td class="text-right font-weight-bold zoom-cell">{{ formatNumber(book?.total_non_taxpayer_taxable_base) }}</td>
                  <td class="zoom-cell"></td>
                  <td class="text-right font-weight-bold zoom-cell">{{ formatNumber(book?.total_non_taxpayer_vat) }}</td>
                </template>
                <td class="text-right font-weight-bold text-error">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td colspan="2"></td>
                <td class="text-right font-weight-bold text-warning">{{ formatNumber(book?.total_igtf) }}</td>
                <td v-if="book?.is_open"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Cuadro RESUMEN DE LIBRO DE VENTAS (SENIAT) -->
        <VCard variant="outlined" class="mt-4 pa-4 rounded-lg">
          <div class="d-flex align-center gap-2 mb-3">
            <VIcon icon="tabler-clipboard-list" color="primary" />
            <span class="text-subtitle-1 font-weight-bold">RESUMEN DE LIBRO DE VENTAS</span>
          </div>

          <VTable density="compact" class="summary-table">
            <thead>
              <tr class="bg-light">
                <th class="font-weight-bold">Concepto</th>
                <th class="text-center font-weight-bold">ITEM</th>
                <th class="text-right font-weight-bold">Base Imponible</th>
                <th class="text-center font-weight-bold">ITEM</th>
                <th class="text-right font-weight-bold">Débito Fiscal</th>
                <th class="text-right font-weight-bold">IVA Retenido</th>
                <th class="text-right font-weight-bold">I.G.T.F.</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Total Ventas Internas no Gravadas:</td>
                <td class="text-center">40</td>
                <td class="text-right">{{ formatNumber(book?.total_non_taxable_sales) }}</td>
                <td class="text-center">-</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Ventas Exportación Afectas solo Alicuota General:</td>
                <td class="text-center">41</td>
                <td class="text-right">0,00</td>
                <td class="text-center">-</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Ventas Internas Afectas solo Alicuota General:</td>
                <td class="text-center">42</td>
                <td class="text-right">{{ formatNumber(book?.total_taxpayer_taxable_base) }}</td>
                <td class="text-center">43</td>
                <td class="text-right">{{ formatNumber(book?.total_taxpayer_vat) }}</td>
                <td class="text-right">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Ventas Internas Afectas en Alicuota General + Adicional:</td>
                <td class="text-center">442</td>
                <td class="text-right">0,00</td>
                <td class="text-center">452</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Ventas Internas Afectas solo Alicuota Reducida:</td>
                <td class="text-center">443</td>
                <td class="text-right">{{ formatNumber(book?.total_non_taxpayer_taxable_base) }}</td>
                <td class="text-center">453</td>
                <td class="text-right">{{ formatNumber(book?.total_non_taxpayer_vat) }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Ajustes a Periodos Anteriores:</td>
                <td class="text-center">46</td>
                <td class="text-right">0,00</td>
                <td class="text-center">47</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr class="font-weight-bold bg-light">
                <td>Total Ventas y Debitos Fiscales del Período:</td>
                <td></td>
                <td class="text-right">
                  {{
                    formatNumber(
                      (Number(book?.total_non_taxable_sales) || 0) +
                      (Number(book?.total_taxpayer_taxable_base) || 0) +
                      (Number(book?.total_non_taxpayer_taxable_base) || 0)
                    )
                  }}
                </td>
                <td></td>
                <td class="text-right">
                  {{
                    formatNumber(
                      (Number(book?.total_taxpayer_vat) || 0) +
                      (Number(book?.total_non_taxpayer_vat) || 0)
                    )
                  }}
                </td>
                <td class="text-right">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr class="font-weight-bold">
                <td colspan="6">TOTAL I.G.T.F.:</td>
                <td class="text-right text-warning font-weight-bold">{{ formatNumber(book?.total_igtf) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VCardText>
    </VCard>

    <!-- Modal para Editar / Crear Fila -->
    <VDialog v-model="showEditModal" max-width="850px" persistent>
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between bg-primary text-white py-3 px-4">
          <span class="text-subtitle-1 font-weight-bold text-white">
            {{ isCreatingRow ? 'Nueva Operación en Libro de Ventas' : 'Editar Operación N° ' + entryForm.operation_number }}
          </span>
          <VBtn icon variant="text" color="white" density="compact" @click="showEditModal = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-4">
          <VForm @submit.prevent="saveEntry">
            <VRow density="compact">
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.operation_date" label="Fecha de Operación *" type="date" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.identification" label="Cédula o R.I.F. *" variant="outlined" density="compact" placeholder="0 o J-12345678-9" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.client_name" label="Nombre o Razón Social *" variant="outlined" density="compact" />
              </VCol>

              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entryForm.document_type"
                  :items="['REPORTE Z', 'NOTA DE CRÉDITO', 'COMPROBANTE DE RETENCIÓN DE IVA', 'FACTURA', 'NOTA DE DÉBITO']"
                  label="Tipo de Documento *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entryForm.transaction_type"
                  :items="['01-REG', '03-REG', '05-RET']"
                  label="Tipo Transacción *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.machine_serial" label="Reg. Máquina Fiscal" variant="outlined" density="compact" />
              </VCol>

              <VCol cols="12" sm="3">
                <VTextField v-model="entryForm.z_report_number" label="N° Reporte Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entryForm.z_first_invoice" label="Factura Inicial Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entryForm.z_last_invoice" label="Factura Final Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entryForm.affected_invoice_number" label="N° Factura Afectada" variant="outlined" density="compact" />
              </VCol>

              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.credit_note_number" label="N° Nota de Crédito" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.control_number" label="N° de Control" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.non_taxable_sales" label="Ventas No Gravadas" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalSales" />
              </VCol>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.taxpayer_taxable_base" label="Base Imponible (16%)" type="number" step="0.01" variant="outlined" density="compact" @input="onTaxpayerBaseChange" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.taxpayer_vat" label="IVA (16%)" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalSales" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.total_sales_with_vat" label="Total Ventas c/IVA *" type="number" step="0.01" variant="outlined" density="compact" />
              </VCol>

              <template v-if="isZoomActive">
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.non_taxpayer_taxable_base" label="Base No Contribuyentes (Zoom 8%)" type="number" step="0.01" variant="outlined" density="compact" @input="onNonTaxpayerBaseChange" />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.non_taxpayer_vat" label="IVA Reducido" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalSales" />
                </VCol>
              </template>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.vat_withheld" label="IVA Retenido" type="number" step="0.01" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_number" label="N° Comprobante Retención" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_date" label="Fecha Recepción Retención" type="date" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.igtf_amount" label="IGTF" type="number" step="0.01" variant="outlined" density="compact" />
              </VCol>
            </VRow>

            <div class="d-flex justify-end gap-2 mt-4">
              <VBtn variant="text" color="secondary" @click="showEditModal = false">Cancelar</VBtn>
              <VBtn color="primary" type="submit" :loading="isSavingRow">Guardar Cambios</VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </VDialog>
</template>

<style scoped>
.sales-book-dialog {
  max-height: 94vh;
}
.table-responsive {
  overflow-x: auto;
  max-height: 520px;
}
.table-book {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.775rem;
}
.table-book th,
.table-book td {
  padding: 5px 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.table-book thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  color: #fff;
  font-weight: 600;
  white-space: nowrap;
}
.header-columns th {
  background-color: #2b5797;
  top: 26px;
}
.bg-primary-dark {
  background-color: #1a365d;
}
.zoom-header {
  background-color: #f59e0b !important;
  color: #111 !important;
}
.zoom-cell {
  background-color: rgba(254, 243, 199, 0.5);
}
.row-manual {
  background-color: rgba(238, 242, 255, 0.4);
}
.row-credit-note {
  background-color: rgba(254, 226, 226, 0.35);
}
.row-retention {
  background-color: rgba(240, 253, 244, 0.45);
}
.footer-totals td {
  position: sticky;
  bottom: 0;
  background-color: #f8fafc;
  z-index: 1;
  border-top: 2px solid #2b5797;
}
.summary-table th,
.summary-table td {
  padding: 6px 10px;
}
</style>
