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
  if (!props.bookId) return
  loading.value = true
  try {
    const response = await axios.get(`/purchase-books/${props.bookId}`)
    book.value = response.data?.data || null
  } catch (error) {
    console.error('Error al cargar libro de compras:', error)
    toast.error('No se pudo cargar el detalle del libro de compras.')
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
      e.supplier_name?.toLowerCase().includes(term) ||
      e.rif?.toLowerCase().includes(term) ||
      e.invoice_number?.toLowerCase().includes(term) ||
      e.control_number?.toLowerCase().includes(term) ||
      e.withholding_receipt_number?.toLowerCase().includes(term)
  )
})

// Acciones principales
const handleSync = async () => {
  if (!props.bookId) return
  syncing.value = true
  try {
    const res = await axios.post(`/purchase-books/${props.bookId}/sync`)
    book.value = res.data?.data
    toast.success('Facturas y retenciones sincronizadas exitosamente.')
    emit('book-updated')
  } catch (err) {
    console.error('Error al sincronizar:', err)
    toast.error(err.response?.data?.message || 'Error al sincronizar operaciones.')
  } finally {
    syncing.value = false
  }
}

const handleExportExcel = () => {
  if (!props.bookId) return
  window.open(`/api/purchase-books/${props.bookId}/export/excel`, '_blank')
}

const handleExportPdf = () => {
  if (!props.bookId) return
  window.open(`/api/purchase-books/${props.bookId}/export/pdf`, '_blank')
}

const handleCloseBook = async () => {
  const result = await Swal.fire({
    title: '¿Cerrar Libro de Compras?',
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
    const res = await axios.post(`/purchase-books/${props.bookId}/close`)
    book.value = res.data?.data
    toast.success('El libro de compras ha sido cerrado definitivamente.')
    emit('book-updated')
  } catch (err) {
    console.error('Error al cerrar libro:', err)
    toast.error(err.response?.data?.message || 'No se pudo cerrar el libro.')
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
      await axios.post(`/purchase-books/${props.bookId}/entries`, entryForm)
      toast.success('Operación agregada exitosamente.')
    } else {
      await axios.put(`/purchase-books/${props.bookId}/entries/${entryForm.id}`, entryForm)
      toast.success('Operación actualizada exitosamente.')
    }
    showEditModal.value = false
    await fetchBook()
    emit('book-updated')
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
    await axios.delete(`/purchase-books/${props.bookId}/entries/${entry.id}`)
    toast.success('Operación eliminada.')
    await fetchBook()
    emit('book-updated')
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
  <VDialog
    :model-value="modelValue"
    max-width="96vw"
    scrollable
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <VCard class="purchase-book-dialog">
      <!-- Barra Superior -->
      <VCardTitle class="d-flex align-center justify-space-between py-3 px-4 bg-primary text-white">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-book" size="24" />
          <div class="d-flex flex-column">
            <span class="text-h6 font-weight-bold text-white">
              Libro de Compras — {{ book?.period_label }}
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
              Sincronizar Facturas/Ret.
            </VBtn>

            <VBtn
              v-if="book?.is_open"
              color="secondary"
              variant="tonal"
              size="small"
              @click="openCreateModal"
            >
              <VIcon start size="16">tabler-plus</VIcon>
              Agregar Fila
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
            <VChip v-if="isZoomActive" size="x-small" color="warning" class="ms-2 font-weight-bold">
              Columnas Zoom (8%) Activas
            </VChip>
          </VCol>
        </VRow>

        <!-- Tabla de Transacciones -->
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
                <th>N°</th>
                <th>Fecha</th>
                <th>R.I.F.</th>
                <th>Razón Social</th>
                <th>C80/C81</th>
                <th>Expediente</th>
                <th>N° Factura</th>
                <th>N° Control</th>
                <th>N° ND</th>
                <th>N° NC</th>
                <th>Fac. Afectada</th>
                <th>Tipo Doc.</th>
                <th>Trans.</th>
                <th class="text-right">Total c/IVA</th>
                <th class="text-right">Exento</th>
                <th class="text-right">Base Imp.</th>
                <th class="text-center">%</th>
                <th class="text-right">IVA Gen.</th>
                <template v-if="isZoomActive">
                  <th class="text-right zoom-header">Base Imp.</th>
                  <th class="text-center zoom-header">%</th>
                  <th class="text-right zoom-header">IVA Red.</th>
                </template>
                <th class="text-right">IVA Retenido</th>
                <th>N° Comprobante</th>
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
                <td class="text-no-wrap">{{ entry.rif }}</td>
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
                <td class="text-right font-weight-medium">{{ formatNumber(entry.total_purchases_with_vat) }}</td>
                <td class="text-right">{{ formatNumber(entry.exempt_purchases) }}</td>
                <td class="text-right">{{ formatNumber(entry.general_taxable_base) }}</td>
                <td class="text-center">{{ entry.general_aliquot > 0 ? (entry.general_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                <td class="text-right">{{ formatNumber(entry.general_vat) }}</td>
                <template v-if="isZoomActive">
                  <td class="text-right zoom-cell">{{ formatNumber(entry.reduced_taxable_base) }}</td>
                  <td class="text-center zoom-cell">{{ entry.reduced_aliquot > 0 ? (entry.reduced_aliquot * 100).toFixed(0) + '%' : '0%' }}</td>
                  <td class="text-right zoom-cell">{{ formatNumber(entry.reduced_vat) }}</td>
                </template>
                <td class="text-right text-error font-weight-bold">{{ formatNumber(entry.vat_withheld) }}</td>
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
                <td class="text-right font-weight-bold text-primary">{{ formatNumber(book?.total_purchases_with_vat) }}</td>
                <td class="text-right font-weight-bold">{{ formatNumber(book?.total_exempt_purchases) }}</td>
                <td class="text-right font-weight-bold">{{ formatNumber(book?.total_general_taxable_base) }}</td>
                <td></td>
                <td class="text-right font-weight-bold text-success">{{ formatNumber(book?.total_general_vat) }}</td>
                <template v-if="isZoomActive">
                  <td class="text-right font-weight-bold zoom-cell">{{ formatNumber(book?.total_reduced_taxable_base) }}</td>
                  <td class="zoom-cell"></td>
                  <td class="text-right font-weight-bold zoom-cell">{{ formatNumber(book?.total_reduced_vat) }}</td>
                </template>
                <td class="text-right font-weight-bold text-error">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td :colspan="book?.is_open ? 2 : 1"></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Cuadro RESUMEN LIBRO DE COMPRAS (SENIAT) -->
        <VCard variant="outlined" class="mt-4 pa-4 rounded-lg">
          <div class="d-flex align-center gap-2 mb-3">
            <VIcon icon="tabler-clipboard-list" color="primary" />
            <span class="text-subtitle-1 font-weight-bold">RESUMEN LIBRO DE COMPRAS</span>
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
                <td class="text-right">{{ formatNumber(book?.total_exempt_purchases) }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Compras Importación Afectas solo Alicuota General:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Compras Importación Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Compras Importación Afectas solo Alicuota Reducida:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Compras Internas Afectas solo Alicuota General:</td>
                <td class="text-right">{{ formatNumber(book?.total_general_taxable_base) }}</td>
                <td class="text-right">{{ formatNumber(book?.total_general_vat) }}</td>
                <td class="text-right">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr>
                <td>Total Compras Internas Afectas en Alicuota General + Adicional:</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr v-if="isZoomActive">
                <td>Total Compras Internas Afectas solo Alicuota Reducida (Zoom):</td>
                <td class="text-right">{{ formatNumber(book?.total_reduced_taxable_base) }}</td>
                <td class="text-right">{{ formatNumber(book?.total_reduced_vat) }}</td>
                <td class="text-right">0,00</td>
                <td class="text-right">0,00</td>
              </tr>
              <tr class="font-weight-bold bg-light">
                <td>TOTALES</td>
                <td class="text-right">
                  {{
                    formatNumber(
                      (Number(book?.total_exempt_purchases) || 0) +
                      (Number(book?.total_general_taxable_base) || 0) +
                      (Number(book?.total_reduced_taxable_base) || 0)
                    )
                  }}
                </td>
                <td class="text-right">
                  {{
                    formatNumber(
                      (Number(book?.total_general_vat) || 0) +
                      (Number(book?.total_reduced_vat) || 0)
                    )
                  }}
                </td>
                <td class="text-right">{{ formatNumber(book?.total_vat_withheld) }}</td>
                <td class="text-right">0,00</td>
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
            {{ isCreatingRow ? 'Nueva Operación en Libro de Compras' : 'Editar Operación N° ' + entryForm.operation_number }}
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
                <VTextField v-model="entryForm.rif" label="R.I.F. *" variant="outlined" density="compact" placeholder="J-12345678-9" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.supplier_name" label="Razón Social *" variant="outlined" density="compact" />
              </VCol>

              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.invoice_number" label="N° de Factura" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.control_number" label="N° de Control" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.affected_invoice_number" label="N° Factura Afectada" variant="outlined" density="compact" />
              </VCol>

              <VCol cols="12" sm="6">
                <VSelect
                  v-model="entryForm.document_type"
                  :items="['FACTURA', 'COMPROBANTE DE RETENCION DE IVA', 'NOTA DE DEBITO', 'NOTA DE CREDITO']"
                  label="Tipo de Documento *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VSelect
                  v-model="entryForm.transaction_type"
                  :items="['01-REG', '02-COMP', '03-ANU', '05-RET']"
                  label="Tipo Transacción *"
                  variant="outlined"
                  density="compact"
                />
              </VCol>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.exempt_purchases" label="Exento (Sin derecho a crédito)" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalPurchases" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.general_taxable_base" label="Base Imponible General (16%)" type="number" step="0.01" variant="outlined" density="compact" @input="onGeneralBaseChange" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.general_vat" label="IVA General" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalPurchases" />
              </VCol>

              <template v-if="isZoomActive">
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.reduced_taxable_base" label="Base Imponible Reducida (Zoom 8%)" type="number" step="0.01" variant="outlined" density="compact" @input="onReducedBaseChange" />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.reduced_aliquot" label="% Alícuota Reducida" type="number" step="0.01" variant="outlined" density="compact" @input="onReducedBaseChange" />
                </VCol>
                <VCol cols="12" sm="4">
                  <VTextField v-model.number="entryForm.reduced_vat" label="IVA Reducido" type="number" step="0.01" variant="outlined" density="compact" @input="updateTotalPurchases" />
                </VCol>
              </template>

              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.total_purchases_with_vat" label="Total Compras c/IVA *" type="number" step="0.01" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model.number="entryForm.vat_withheld" label="IVA Retenido" type="number" step="0.01" variant="outlined" density="compact" />
              </VCol>
              <VCol cols="12" sm="4">
                <VTextField v-model="entryForm.withholding_receipt_number" label="N° de Comprobante" variant="outlined" density="compact" />
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
.purchase-book-dialog {
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
