<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const bookId = computed(() => route.params.id);

const loading = ref(false);
const closing = ref(false);
const savingEntry = ref(false);
const downloadingTxt = ref(false);
const downloadingExcel = ref(false);
const downloadingPdf = ref(false);

const book = ref(null);
const entries = ref([]);
const searchQuery = ref("");

// Modal de edición de renglón
const showEditDialog = ref(false);
const editingEntry = ref(null);
const editForm = reactive({
  document_number: "",
  control_number: "",
  document_type: "01",
  affected_document_number: "0",
  total_document_amount: 0,
  taxable_base: 0,
  tax_amount: 0,
  withheld_amount: 0,
  exempt_amount: 0,
  tax_rate: 16.0,
});

const formatCurrency = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const fetchBook = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/vat-retention-books/${bookId.value}`);
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al cargar reporte de retenciones:", error);
    toast.error("Ocurrió un error al cargar el reporte de retenciones.");
  } finally {
    loading.value = false;
  }
};

const handleCloseBook = async () => {
  closing.value = true;
  try {
    const res = await axios.post(`/vat-retention-books/${bookId.value}/close`);
    toast.success("Período de retenciones cerrado y congelado exitosamente.");
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al cerrar período:", error);
    toast.error("No se pudo cerrar el período.");
  } finally {
    closing.value = false;
  }
};

const handleReopenBook = async () => {
  try {
    const res = await axios.post(`/vat-retention-books/${bookId.value}/reopen`);
    toast.success("Período reabierto para ajustes.");
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al reabrir período:", error);
    toast.error("No se pudo reabrir el período.");
  }
};

const downloadBlob = async (endpoint, defaultFilename, loadingRef) => {
  if (loadingRef) loadingRef.value = true;
  try {
    const res = await axios.get(endpoint, { responseType: "blob" });
    const blob = new Blob([res.data]);
    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = blobUrl;
    link.setAttribute("download", defaultFilename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(blobUrl);
    toast.success("Archivo descargado exitosamente.");
  } catch (err) {
    console.error("Error al descargar archivo:", err);
    toast.error("Ocurrió un error al descargar el archivo.");
  } finally {
    if (loadingRef) loadingRef.value = false;
  }
};

const handleExportTxt = () => {
  const suffix = book.value?.fortnight ? `_Q${book.value.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${bookId.value}/export/txt`, `TxtImpuesto${book.value?.year}${String(book.value?.month).padStart(2, '0')}${suffix}.txt`, downloadingTxt);
};

const handleExportExcel = () => {
  const suffix = book.value?.fortnight ? `_Q${book.value.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${bookId.value}/export/excel`, `reporte_retenciones_iva_${book.value?.year}_${book.value?.month}${suffix}.xlsx`, downloadingExcel);
};

const handleExportPdf = () => {
  const suffix = book.value?.fortnight ? `_Q${book.value.fortnight}` : "";
  downloadBlob(`/vat-retention-books/${bookId.value}/export/pdf`, `reporte_retenciones_iva_${book.value?.year}_${book.value?.month}${suffix}.pdf`, downloadingPdf);
};

const openEditDialog = (entry) => {
  if (book.value?.is_closed) return;
  editingEntry.value = entry;
  editForm.document_number = entry.document_number;
  editForm.control_number = entry.control_number || "";
  editForm.document_type = entry.document_type || "01";
  editForm.affected_document_number = entry.affected_document_number || "0";
  editForm.total_document_amount = entry.total_document_amount;
  editForm.taxable_base = entry.taxable_base;
  editForm.tax_amount = entry.tax_amount;
  editForm.withheld_amount = entry.withheld_amount;
  editForm.exempt_amount = entry.exempt_amount;
  editForm.tax_rate = entry.tax_rate;
  showEditDialog.value = true;
};

const handleSaveEntry = async () => {
  if (!editingEntry.value) return;
  savingEntry.value = true;
  try {
    const res = await axios.put(`/vat-retention-books/${bookId.value}/entries/${editingEntry.value.id}`, editForm);
    toast.success("Renglón actualizado exitosamente.");
    showEditDialog.value = false;
    await fetchBook();
  } catch (err) {
    console.error("Error al actualizar renglón:", err);
    toast.error("No se pudo actualizar el renglón.");
  } finally {
    savingEntry.value = false;
  }
};

const filteredEntries = computed(() => {
  if (!searchQuery.value) return entries.value;
  const q = searchQuery.value.toLowerCase().trim();
  return entries.value.filter((e) => {
    return (
      (e.supplier_name && e.supplier_name.toLowerCase().includes(q)) ||
      (e.supplier_rif && e.supplier_rif.toLowerCase().includes(q)) ||
      (e.document_number && e.document_number.toLowerCase().includes(q)) ||
      (e.control_number && e.control_number.toLowerCase().includes(q)) ||
      (e.retention_number && e.retention_number.toLowerCase().includes(q))
    );
  });
});

onMounted(() => {
  fetchBook();
});
</script>

<template>
  <div class="vat-retention-detail-page">
    <!-- Encabezado con Botones de Acción y Navegación -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            size="small"
            @click="router.push('/fiscal/libros/retenciones')"
          />
          <VAvatar color="primary" variant="tonal" rounded size="44">
            <VIcon icon="tabler-receipt-tax" size="26" />
          </VAvatar>
          <div>
            <div class="d-flex align-center gap-2">
              <h1 class="text-h5 font-weight-bold mb-0">
                {{ book?.period_label || 'Reporte de Retenciones de IVA' }}
              </h1>
              <VChip
                size="small"
                :color="book?.is_open ? 'success' : 'secondary'"
                variant="flat"
              >
                {{ book?.is_open ? 'Abierto' : 'Cerrado' }}
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mb-0">
              Vigencia del período: {{ book?.start_date }} al {{ book?.end_date }}.
            </p>
          </div>
        </div>

        <div class="d-flex align-center flex-wrap gap-2">
          <!-- Botón TXT SENIAT -->
          <VBtn
            color="warning"
            variant="flat"
            prepend-icon="tabler-file-code"
            :loading="downloadingTxt"
            :disabled="downloadingTxt"
            @click="handleExportTxt"
          >
            TXT SENIAT
          </VBtn>

          <!-- Botón Excel -->
          <VBtn
            color="success"
            variant="flat"
            prepend-icon="tabler-file-spreadsheet"
            :loading="downloadingExcel"
            :disabled="downloadingExcel"
            @click="handleExportExcel"
          >
            Excel
          </VBtn>

          <!-- Botón PDF -->
          <VBtn
            color="error"
            variant="flat"
            prepend-icon="tabler-file-type-pdf"
            :loading="downloadingPdf"
            :disabled="downloadingPdf"
            @click="handleExportPdf"
          >
            PDF
          </VBtn>

          <!-- Botón Cerrar / Reabrir -->
          <VBtn
            v-if="book?.is_open"
            color="secondary"
            variant="outlined"
            prepend-icon="tabler-lock"
            :loading="closing"
            :disabled="closing"
            @click="handleCloseBook"
          >
            Cerrar Período
          </VBtn>
          <VBtn
            v-else
            color="primary"
            variant="outlined"
            prepend-icon="tabler-lock-open"
            @click="handleReopenBook"
          >
            Reabrir Período
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Tarjetas de Resumen (KPIs) -->
    <VRow dense class="mb-4">
      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg">
          <div class="d-flex justify-space-between align-center">
            <div>
              <span class="text-caption text-medium-emphasis">Documentos Retenidos</span>
              <h2 class="text-h5 font-weight-bold mt-1 text-info">
                {{ book?.total_documents || 0 }}
              </h2>
            </div>
            <VAvatar color="info" variant="tonal" rounded size="40">
              <VIcon icon="tabler-files" size="22" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg">
          <div class="d-flex justify-space-between align-center">
            <div>
              <span class="text-caption text-medium-emphasis">Total Facturado</span>
              <h2 class="text-h6 font-weight-bold mt-1">
                Bs {{ formatCurrency(book?.total_document_amount) }}
              </h2>
            </div>
            <VAvatar color="primary" variant="tonal" rounded size="40">
              <VIcon icon="tabler-calculator" size="22" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg">
          <div class="d-flex justify-space-between align-center">
            <div>
              <span class="text-caption text-medium-emphasis">Base Imponible</span>
              <h2 class="text-h6 font-weight-bold mt-1">
                Bs {{ formatCurrency(book?.total_taxable_base) }}
              </h2>
            </div>
            <VAvatar color="secondary" variant="tonal" rounded size="40">
              <VIcon icon="tabler-coins" size="22" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="3">
        <VCard class="pa-4 rounded-lg border-primary" style="border: 1.5px solid rgba(var(--v-theme-primary), 0.4);">
          <div class="d-flex justify-space-between align-center">
            <div>
              <span class="text-caption font-weight-bold text-primary">IVA Retenido a Declarar</span>
              <h2 class="text-h5 font-weight-bold mt-1 text-primary">
                Bs {{ formatCurrency(book?.total_withheld_amount) }}
              </h2>
            </div>
            <VAvatar color="primary" variant="flat" rounded size="40">
              <VIcon icon="tabler-percentage" size="22" color="white" />
            </VAvatar>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <!-- Barra de Búsqueda y Filtros de la Tabla -->
    <VCard class="mb-4 pa-3 rounded-lg">
      <VRow dense align="center">
        <VCol cols="12" sm="6" md="5">
          <VTextField
            v-model="searchQuery"
            density="compact"
            variant="outlined"
            placeholder="Buscar por RIF, proveedor, número de factura o comprobante..."
            prepend-inner-icon="tabler-search"
            clearable
            hide-details
          />
        </VCol>
        <VCol cols="12" sm="6" md="7" class="text-right">
          <span class="text-caption text-medium-emphasis">
            Mostrando {{ filteredEntries.length }} de {{ entries.length }} renglones registrados
          </span>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla Detallada con las 16 Columnas del SENIAT -->
    <VCard class="rounded-lg">
      <VTable density="compact" hover class="retention-entries-table">
        <thead>
          <tr class="bg-surface">
            <th class="text-center font-weight-bold" style="width: 45px;">Nº</th>
            <th class="text-center font-weight-bold" style="width: 95px;">FECHA DOC</th>
            <th class="text-center font-weight-bold" style="width: 50px;">TIPO</th>
            <th class="text-center font-weight-bold" style="width: 105px;">RIF PROV.</th>
            <th class="text-left font-weight-bold" style="min-width: 220px;">RAZÓN SOCIAL PROVEEDOR</th>
            <th class="text-center font-weight-bold" style="width: 110px;">Nº FACTURA</th>
            <th class="text-center font-weight-bold" style="width: 100px;">Nº CONTROL</th>
            <th class="text-right font-weight-bold" style="width: 120px;">TOTAL (BS)</th>
            <th class="text-right font-weight-bold" style="width: 120px;">BASE IMP. (BS)</th>
            <th class="text-right font-weight-bold text-primary" style="width: 120px;">IVA RETENIDO (BS)</th>
            <th class="text-center font-weight-bold" style="width: 140px;">COMPROBANTE</th>
            <th class="text-right font-weight-bold" style="width: 100px;">EXENTO (BS)</th>
            <th class="text-center font-weight-bold" style="width: 70px;">ALÍC.</th>
            <th class="text-center font-weight-bold" style="width: 60px;">ACCIÓN</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="entry in filteredEntries"
            :key="entry.id"
            class="table-row-item"
          >
            <td class="text-center font-weight-medium">{{ entry.operation_number }}</td>
            <td class="text-center text-caption">{{ entry.document_date }}</td>
            <td class="text-center">
              <VChip size="x-small" variant="tonal" :color="entry.document_type === '01' ? 'info' : 'warning'">
                {{ entry.document_type === '01' ? 'Fact' : (entry.document_type === '03' ? 'NC' : 'ND') }}
              </VChip>
            </td>
            <td class="text-center font-weight-medium text-caption">{{ entry.supplier_rif }}</td>
            <td class="text-left text-body-2">{{ entry.supplier_name }}</td>
            <td class="text-center font-weight-medium">{{ entry.document_number }}</td>
            <td class="text-center text-caption text-medium-emphasis">{{ entry.control_number || '-' }}</td>
            <td class="text-right text-caption">Bs {{ formatCurrency(entry.total_document_amount) }}</td>
            <td class="text-right text-caption">Bs {{ formatCurrency(entry.taxable_base) }}</td>
            <td class="text-right font-weight-bold text-primary text-caption">
              Bs {{ formatCurrency(entry.withheld_amount) }}
            </td>
            <td class="text-center text-caption font-weight-medium">{{ entry.retention_number }}</td>
            <td class="text-right text-caption">Bs {{ formatCurrency(entry.exempt_amount) }}</td>
            <td class="text-center text-caption">{{ Number(entry.tax_rate).toFixed(1) }}%</td>
            <td class="text-center">
              <VBtn
                icon="tabler-pencil"
                variant="text"
                size="x-small"
                color="secondary"
                :disabled="book?.is_closed"
                title="Editar renglón"
                @click="openEditDialog(entry)"
              />
            </td>
          </tr>

          <tr v-if="filteredEntries.length === 0">
            <td colspan="14" class="pa-6 text-center text-medium-emphasis">
              No se encontraron registros de retenciones para los criterios indicados.
            </td>
          </tr>
        </tbody>
        <tfoot v-if="filteredEntries.length > 0">
          <tr class="bg-surface font-weight-bold" style="border-top: 1.5px solid #000;">
            <td colspan="7" class="text-center font-weight-bold">
              TOTALES CONSOLIDADOS ({{ book?.total_documents }} FACTURAS RETENIDAS)
            </td>
            <td class="text-right">Bs {{ formatCurrency(book?.total_document_amount) }}</td>
            <td class="text-right">Bs {{ formatCurrency(book?.total_taxable_base) }}</td>
            <td class="text-right text-primary font-weight-bold">Bs {{ formatCurrency(book?.total_withheld_amount) }}</td>
            <td class="text-center">-</td>
            <td class="text-right">Bs {{ formatCurrency(book?.total_exempt_amount) }}</td>
            <td class="text-center">-</td>
            <td class="text-center">-</td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>

    <!-- Modal de Edición de Renglón -->
    <VDialog v-model="showEditDialog" max-width="560">
      <VCard class="pa-4 rounded-lg">
        <VCardTitle class="px-0 pt-0 d-flex align-center justify-space-between">
          <span class="text-h6 font-weight-bold">Editar Renglón de Retención</span>
          <VBtn icon="tabler-x" variant="text" size="small" @click="showEditDialog = false" />
        </VCardTitle>
        <VCardText class="px-0 py-3">
          <div class="mb-3">
            <span class="text-caption text-medium-emphasis">Proveedor:</span>
            <div class="font-weight-medium">{{ editingEntry?.supplier_name }} ({{ editingEntry?.supplier_rif }})</div>
            <div class="text-caption">Comprobante: {{ editingEntry?.retention_number }}</div>
          </div>
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="editForm.document_number"
                label="Nº Factura / Documento"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="editForm.control_number"
                label="Nº Control"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="editForm.document_type"
                :items="[
                  { title: '01 - Factura', value: '01' },
                  { title: '02 - Nota de Débito', value: '02' },
                  { title: '03 - Nota de Crédito', value: '03' }
                ]"
                label="Tipo de Documento"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="editForm.affected_document_number"
                label="Doc. Afectado (0 si no aplica)"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.total_document_amount"
                label="Total Factura (Bs)"
                type="number"
                step="0.01"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.taxable_base"
                label="Base Imponible (Bs)"
                type="number"
                step="0.01"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.withheld_amount"
                label="IVA Retenido (Bs)"
                type="number"
                step="0.01"
                variant="outlined"
                density="compact"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.exempt_amount"
                label="Monto Exento (Bs)"
                type="number"
                step="0.01"
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="px-0 pb-0 justify-end gap-2">
          <VBtn variant="outlined" color="secondary" @click="showEditDialog = false">
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :loading="savingEntry"
            :disabled="savingEntry"
            @click="handleSaveEntry"
          >
            Guardar Cambios
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.retention-entries-table th,
.retention-entries-table td {
  white-space: nowrap;
}
</style>
