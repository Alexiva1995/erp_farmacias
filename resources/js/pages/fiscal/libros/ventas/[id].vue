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
  if (!bookId) return
  loading.value = true
  try {
    const response = await axios.get(`/sales-books/${bookId}`)
    book.value = response.data?.data || null
  } catch (error) {
    console.error('Error al cargar libro de ventas:', error)
    toast.error('No se pudo cargar el detalle del libro de ventas.')
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
      e.client_name?.toLowerCase().includes(term) ||
      e.identification?.toLowerCase().includes(term) ||
      e.z_report_number?.toLowerCase().includes(term) ||
      e.credit_note_number?.toLowerCase().includes(term) ||
      e.invoice_number?.toLowerCase().includes(term) ||
      e.control_number?.toLowerCase().includes(term)
  )
})

// Acciones principales
const handleSync = async () => {
  if (!bookId) return
  syncing.value = true
  try {
    const res = await axios.post(`/sales-books/${bookId}/sync`)
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
  window.open(`/api/sales-books/${bookId}/export/excel`, '_blank')
}

const handleExportPdf = () => {
  if (!bookId) return
  window.open(`/api/sales-books/${bookId}/export/pdf`, '_blank')
}

const handleCloseBook = async () => {
  const result = await Swal.fire({
    title: '¿Cerrar Libro de Ventas?',
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
    const res = await axios.post(`/sales-books/${bookId}/close`)
    book.value = res.data?.data || book.value
    toast.success('Libro de ventas cerrado exitosamente.')
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
    identification: '0',
    client_name: 'CLIENTES VARIOS',
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
  showEditModal.value = true
}

const saveEntry = async () => {
  isSavingRow.value = true
  try {
    if (isCreatingRow.value) {
      await axios.post(`/sales-books/${bookId}/entries`, entryForm)
      toast.success('Operación agregada exitosamente.')
    } else {
      await axios.put(`/sales-books/${bookId}/entries/${entryForm.id}`, entryForm)
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
    text: `Se eliminará la operación N° ${entry.operation_number} (${entry.client_name || entry.document_type}).`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (!result.isConfirmed) return

  try {
    await axios.delete(`/sales-books/${bookId}/entries/${entry.id}`)
    toast.success('Operación eliminada.')
    await fetchBook()
  } catch (err) {
    console.error('Error al eliminar fila:', err)
    toast.error(err.response?.data?.message || 'No se pudo eliminar la operación.')
  }
}

// Auto cálculo de IVA al cambiar bases en el formulario
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
  const exempt = parseFloat(entryForm.non_taxable_sales) || 0
  const base16 = parseFloat(entryForm.taxpayer_taxable_base) || 0
  const vat16 = parseFloat(entryForm.taxpayer_vat) || 0
  const base8 = parseFloat(entryForm.non_taxpayer_taxable_base) || 0
  const vat8 = parseFloat(entryForm.non_taxpayer_vat) || 0

  entryForm.total_sales_with_vat = +(exempt + base16 + vat16 + base8 + vat8).toFixed(2)
}

const formatNumber = (val) => {
  const num = parseFloat(val) || 0
  return num.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <div class="sales-book-view">
    <!-- Encabezado de la Vista Dedicada con Acciones Homologadas -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VBtn
            icon
            variant="tonal"
            color="secondary"
            size="38"
            rounded="circle"
            @click="router.push('/fiscal/libros/ventas')"
          >
            <VIcon icon="tabler-arrow-left" />
            <VTooltip activator="parent" location="top">Volver a Libros de Ventas</VTooltip>
          </VBtn>

          <div>
            <div class="d-flex align-center gap-2">
              <h1 class="text-h6 font-weight-bold mb-0">
                Libro de Ventas: {{ book?.period_label || 'Cargando...' }}
              </h1>
              <VChip
                :color="book?.is_open ? 'success' : 'error'"
                size="small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ book?.status_label }}
              </VChip>
              <VChip v-if="isZoomActive" size="small" color="warning" class="font-weight-bold">
                <VIcon start size="14">tabler-percentage</VIcon>
                Alícuota Reducida Zoom (8%) Activa
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mb-0 mt-1">
              Período: <strong>{{ book?.start_date }}</strong> al <strong>{{ book?.end_date }}</strong>
              &nbsp;|&nbsp; Condición: <strong>{{ book?.company_info?.taxpayer_type }}</strong>
            </p>
          </div>
        </div>

        <div class="d-flex align-center gap-1 flex-wrap">
          <!-- Menú Exportar Homologado con AppFilterBase -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
                icon
                color="success"
                variant="tonal"
                size="38"
                rounded="circle"
              >
                <VIcon icon="tabler-file-export" />
                <VTooltip activator="parent" location="top">Exportar Reporte</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem @click="handleExportExcel">
                <template #prepend>
                  <VIcon icon="tabler-file-type-csv" size="18" color="success" />
                </template>
                <VListItemTitle>Excel</VListItemTitle>
              </VListItem>
              <VListItem @click="handleExportPdf">
                <template #prepend>
                  <VIcon icon="tabler-file-type-pdf" size="18" color="error" />
                </template>
                <VListItemTitle>PDF</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Cerrar Libro -->
          <VBtn
            v-if="book?.is_open"
            icon
            color="error"
            variant="tonal"
            size="38"
            rounded="circle"
            :loading="closing"
            @click="handleCloseBook"
          >
            <VIcon icon="tabler-lock" />
            <VTooltip activator="parent" location="top">Cerrar Libro</VTooltip>
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Buscador Rápido y Tabla de Transacciones -->
    <VCard class="rounded-lg pa-4">
      <VRow density="compact" class="mb-3" align="center">
        <VCol cols="12" sm="6" md="4">
          <VTextField
            v-model="search"
            placeholder="Buscar por Reporte Z, Cliente, RIF, NC, Factura..."
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
              <td class="text-truncate" style="max-width: 220px;" :title="entry.client_name">
                {{ entry.client_name }}
              </td>
              <td>{{ entry.export_planilla_number || '-' }}</td>
              <td><VChip size="x-small" variant="tonal">{{ entry.document_type }}</VChip></td>
              <td class="font-weight-medium">{{ entry.invoice_number || '-' }}</td>
              <td>{{ entry.control_number || '-' }}</td>
              <td class="text-center font-weight-bold">{{ entry.z_report_number || '-' }}</td>
              <td class="text-center">{{ entry.z_first_invoice || '-' }}</td>
              <td class="text-center">{{ entry.z_last_invoice || '-' }}</td>
              <td class="text-no-wrap">{{ entry.machine_serial || '-' }}</td>
              <td>{{ entry.debit_note_number || '-' }}</td>
              <td class="text-center text-error font-weight-bold">{{ entry.credit_note_number || '-' }}</td>
              <td class="text-center">{{ entry.affected_invoice_number || '-' }}</td>
              <td class="text-center"><code>{{ entry.transaction_type }}</code></td>
              <td class="text-right font-weight-medium font-tabular" :class="{ 'text-error': entry.total_sales_with_vat < 0 }">
                {{ formatNumber(entry.total_sales_with_vat) }}
              </td>
              <td class="text-right font-tabular">{{ formatNumber(entry.non_taxable_sales) }}</td>
              <td class="text-right font-tabular">{{ formatNumber(entry.taxpayer_taxable_base) }}</td>
              <td class="text-center font-tabular">{{ entry.taxpayer_aliquot > 0 ? (entry.taxpayer_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
              <td class="text-right font-tabular text-success">{{ formatNumber(entry.taxpayer_vat) }}</td>
              <template v-if="isZoomActive">
                <td class="text-right zoom-cell font-tabular">{{ formatNumber(entry.non_taxpayer_taxable_base) }}</td>
                <td class="text-center zoom-cell font-tabular">{{ entry.non_taxpayer_aliquot > 0 ? (entry.non_taxpayer_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                <td class="text-right zoom-cell font-tabular">{{ formatNumber(entry.non_taxpayer_vat) }}</td>
              </template>
              <td class="text-right text-error font-weight-bold font-tabular">{{ formatNumber(entry.vat_withheld) }}</td>
              <td class="text-center font-weight-medium">{{ entry.withholding_receipt_number || '-' }}</td>
              <td class="text-center">{{ entry.withholding_receipt_date || '-' }}</td>
              <td class="text-right font-medium font-tabular">{{ formatNumber(entry.igtf_amount) }}</td>
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
              <td class="text-right font-weight-bold text-primary font-tabular">{{ formatNumber(book?.total_sales_with_vat) }}</td>
              <td class="text-right font-weight-bold font-tabular">{{ formatNumber(book?.total_non_taxable_sales) }}</td>
              <td class="text-right font-weight-bold font-tabular">{{ formatNumber(book?.total_taxpayer_taxable_base) }}</td>
              <td></td>
              <td class="text-right font-weight-bold text-success font-tabular">{{ formatNumber(book?.total_taxpayer_vat) }}</td>
              <template v-if="isZoomActive">
                <td class="text-right font-weight-bold zoom-cell font-tabular">{{ formatNumber(book?.total_non_taxpayer_taxable_base) }}</td>
                <td class="zoom-cell"></td>
                <td class="text-right font-weight-bold zoom-cell font-tabular">{{ formatNumber(book?.total_non_taxpayer_vat) }}</td>
              </template>
              <td class="text-right font-weight-bold text-error font-tabular">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td></td>
              <td></td>
              <td class="text-right font-weight-bold font-tabular">{{ formatNumber(book?.total_igtf) }}</td>
              <td v-if="book?.is_open"></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Cuadro RESUMEN LIBRO DE VENTAS (SENIAT) -->
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
              <td class="text-right font-tabular">{{ formatNumber(book?.total_non_taxable_sales) }}</td>
              <td class="text-center">-</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Ventas Exportación Afectas solo Alicuota General:</td>
              <td class="text-center">41</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-center">-</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Ventas Internas Afectas solo Alicuota General:</td>
              <td class="text-center">42</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_taxpayer_taxable_base) }}</td>
              <td class="text-center">43</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_taxpayer_vat) }}</td>
              <td class="text-right font-tabular text-error font-weight-bold">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Ventas Internas Afectas en Alicuota General + Adicional:</td>
              <td class="text-center">442</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-center">452</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Ventas Internas Afectas solo Alicuota Reducida:</td>
              <td class="text-center">443</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_non_taxpayer_taxable_base) }}</td>
              <td class="text-center">453</td>
              <td class="text-right font-tabular">{{ formatNumber(book?.total_non_taxpayer_vat) }}</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr>
              <td>Total Ajustes a Periodos Anteriores:</td>
              <td class="text-center">46</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-center">47</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr class="font-weight-bold bg-light">
              <td>Total Ventas y Debitos Fiscales del Período:</td>
              <td></td>
              <td class="text-right font-tabular">
                {{
                  formatNumber(
                    (Number(book?.total_non_taxable_sales) || 0) +
                    (Number(book?.total_taxpayer_taxable_base) || 0) +
                    (Number(book?.total_non_taxpayer_taxable_base) || 0)
                  )
                }}
              </td>
              <td></td>
              <td class="text-right font-tabular">
                {{
                  formatNumber(
                    (Number(book?.total_taxpayer_vat) || 0) +
                    (Number(book?.total_non_taxpayer_vat) || 0)
                  )
                }}
              </td>
              <td class="text-right text-error font-tabular">{{ formatNumber(book?.total_vat_withheld) }}</td>
              <td class="text-right font-tabular">0,00</td>
            </tr>
            <tr class="font-weight-bold">
              <td colspan="6">TOTAL I.G.T.F.:</td>
              <td class="text-right text-warning font-weight-bold font-tabular">{{ formatNumber(book?.total_igtf) }}</td>
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
                <VTextField v-model="entryForm.identification" label="Cédula / RIF" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.client_name" label="Nombre o Razón Social" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entryForm.document_type"
                  :items="['REPORTE Z', 'FACTURA', 'NOTA DE DEBITO', 'NOTA DE CREDITO', 'RETENCIÓN']"
                  label="Tipo Documento"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.z_report_number" label="N° Reporte Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.z_first_invoice" label="Factura Inicial Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.z_last_invoice" label="Factura Final Z" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.machine_serial" label="Reg. Máquina" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.invoice_number" label="N° Factura" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.control_number" label="N° Control" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.credit_note_number" label="N° Nota Crédito" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.debit_note_number" label="N° Nota Débito" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.affected_invoice_number" label="N° Factura Afectada" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.transaction_type" label="Tipo Trans. (ej. 01-REG)" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.non_taxable_sales" type="number" step="0.01" label="Ventas No Gravadas" variant="outlined" density="compact" @input="updateTotalSales" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.taxpayer_taxable_base" type="number" step="0.01" label="Base Imponible (16%)" variant="outlined" density="compact" @input="onTaxpayerBaseChange" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.taxpayer_vat" type="number" step="0.01" label="IVA (16%)" variant="outlined" density="compact" @input="updateTotalSales" />
              </VCol>

              <template v-if="isZoomActive">
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.non_taxpayer_taxable_base" type="number" step="0.01" label="Base Reducida (Zoom 8%)" variant="outlined" density="compact" @input="onNonTaxpayerBaseChange" />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.non_taxpayer_vat" type="number" step="0.01" label="IVA Reducido (Zoom 8%)" variant="outlined" density="compact" @input="updateTotalSales" />
                </VCol>
              </template>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.total_sales_with_vat" type="number" step="0.01" label="Total Ventas c/IVA" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.vat_withheld" type="number" step="0.01" label="IVA Retenido" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_number" label="N° Comprobante Retención" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_date" type="date" label="Fecha Recepción Retención" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.igtf_amount" type="number" step="0.01" label="IGTF (3%)" variant="outlined" density="compact" />
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
.sales-book-view {
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

.row-credit-note {
  background-color: rgba(var(--v-theme-error), 0.08);
}

.row-retention {
  background-color: rgba(var(--v-theme-success), 0.08);
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
