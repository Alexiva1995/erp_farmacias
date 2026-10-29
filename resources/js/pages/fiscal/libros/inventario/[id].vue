<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const bookId = computed(() => route.params.id);

// Estados reactivos
const loading = ref(false);
const closing = ref(false);
const savingEntry = ref(false);
const book = ref(null);
const entries = ref([]);
const searchQuery = ref("");
const filterType = ref("all"); // 'all', 'with_activity', 'with_withdrawals'

// Modal de edición de renglón
const showEditDialog = ref(false);
const editingEntry = ref(null);
const editForm = reactive({
  unit_cost_bs: 0,
  initial_stock: 0,
  purchase_entries: 0,
  sales_exits: 0,
  withdrawals: 0,
  self_consumptions: 0,
  iva_rate: 0,
  withdrawal_reason: "",
});

const formatCurrency = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatUnits = (val) => {
  return Number(val || 0).toLocaleString("es-VE", {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  });
};

// Cargar Libro
const fetchBook = async () => {
  loading.value = true;
  try {
    const res = await axios.get(`/inventory-books/${bookId.value}`);
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al cargar libro de inventario:", error);
    toast.error("Ocurrió un error al cargar el libro de inventario.");
  } finally {
    loading.value = false;
  }
};

// Alternar switch de ajustes negativos a retiros
const handleToggleAdjustments = async () => {
  if (!book.value) return;
  const newValue = !book.value.include_negative_adjustments_as_withdrawals;
  try {
    const res = await axios.post(`/inventory-books/${bookId.value}/toggle-adjustments`, {
      include: newValue,
    });
    toast.success(res.data?.message || "Preferencia de retiros actualizada.");
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al alternar ajustes de retiros:", error);
    toast.error("No se pudo actualizar la configuración de retiros.");
  }
};

// Cerrar libro
const handleCloseBook = async () => {
  closing.value = true;
  try {
    const res = await axios.post(`/inventory-books/${bookId.value}/close`);
    toast.success("Libro cerrado y congelado exitosamente.");
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al cerrar libro:", error);
    toast.error("No se pudo cerrar el libro.");
  } finally {
    closing.value = false;
  }
};

// Reabrir libro
const handleReopenBook = async () => {
  try {
    const res = await axios.post(`/inventory-books/${bookId.value}/reopen`);
    toast.success("Libro reabierto para ajustes.");
    book.value = res.data?.data || null;
    entries.value = res.data?.data?.entries || [];
  } catch (error) {
    console.error("Error al reabrir libro:", error);
    toast.error("No se pudo reabrir el libro.");
  }
};

// Exportaciones
const handleExportExcel = () => {
  window.open(`/api/inventory-books/${bookId.value}/export/excel`, "_blank");
};

const handleExportPdf = () => {
  window.open(`/api/inventory-books/${bookId.value}/export/pdf`, "_blank");
};

const handleExportActa = () => {
  window.open(`/api/inventory-books/${bookId.value}/export/acta-desincorporacion`, "_blank");
};

// Abrir modal de edición
const openEditDialog = (entry) => {
  editingEntry.value = entry;
  editForm.unit_cost_bs = entry.unit_cost_bs;
  editForm.initial_stock = entry.initial_stock;
  editForm.purchase_entries = entry.purchase_entries;
  editForm.sales_exits = entry.sales_exits;
  editForm.withdrawals = entry.withdrawals;
  editForm.self_consumptions = entry.self_consumptions;
  editForm.iva_rate = entry.iva_rate;
  editForm.withdrawal_reason = entry.withdrawal_reason || "";
  showEditDialog.value = true;
};

// Guardar edición de renglón
const handleSaveEntry = async () => {
  if (!editingEntry.value) return;
  savingEntry.value = true;
  try {
    const res = await axios.put(
      `/inventory-books/${bookId.value}/entries/${editingEntry.value.id}`,
      { ...editForm }
    );
    toast.success("Renglón de inventario actualizado.");
    showEditDialog.value = false;
    await fetchBook();
  } catch (error) {
    console.error("Error al actualizar renglón:", error);
    toast.error(error.response?.data?.message || "No se pudo actualizar el renglón.");
  } finally {
    savingEntry.value = false;
  }
};

// Cálculo reactivo en el modal de edición
const computedModalFinalStock = computed(() => {
  return (
    Number(editForm.initial_stock || 0) +
    Number(editForm.purchase_entries || 0) -
    Number(editForm.sales_exits || 0) -
    Number(editForm.withdrawals || 0) -
    Number(editForm.self_consumptions || 0)
  );
});

const computedModalFinalValueBs = computed(() => {
  return computedModalFinalStock.value * Number(editForm.unit_cost_bs || 0);
});

const computedModalWithdrawalVatBs = computed(() => {
  return (
    Number(editForm.withdrawals || 0) *
    Number(editForm.unit_cost_bs || 0) *
    Number(editForm.iva_rate || 0)
  );
});

// Filtrado de renglones en tabla
const filteredEntries = computed(() => {
  let list = entries.value;

  if (filterType.value === "with_withdrawals") {
    list = list.filter((e) => Number(e.withdrawals) > 0);
  } else if (filterType.value === "with_activity") {
    list = list.filter(
      (e) =>
        Number(e.purchase_entries) > 0 ||
        Number(e.sales_exits) > 0 ||
        Number(e.withdrawals) > 0 ||
        Number(e.self_consumptions) > 0
    );
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      (e) =>
        String(e.product_code).toLowerCase().includes(q) ||
        String(e.product_description).toLowerCase().includes(q)
    );
  }

  return list;
});

const tableHeaders = [
  { title: "Nº", key: "operation_number", width: "50px", align: "center", sortable: false },
  { title: "CÓDIGO", key: "product_code", width: "80px", align: "end", sortable: false },
  { title: "DESCRIPCIÓN DEL PRODUCTO", key: "product_description", minWidth: "220px", sortable: false },
  { title: "COSTO UNIT. BS", key: "unit_cost_bs", align: "end", sortable: false },
  { title: "EXIST. INICIAL", key: "initial_stock", align: "end", sortable: false },
  { title: "ENTRADAS", key: "purchase_entries", align: "end", sortable: false },
  { title: "VENTAS", key: "sales_exits", align: "end", sortable: false },
  { title: "RETIROS", key: "withdrawals", align: "end", sortable: false },
  { title: "AUTOCONS.", key: "self_consumptions", align: "end", sortable: false },
  { title: "EXISTENCIA", key: "final_stock", align: "end", sortable: false },
  { title: "VALOR ANTERIOR (BS)", key: "previous_value_bs", align: "end", sortable: false },
  { title: "COSTO ENTRADA (BS)", key: "purchase_entries_cost_bs", align: "end", sortable: false },
  { title: "COSTO VENTAS (BS)", key: "sales_exits_cost_bs", align: "end", sortable: false },
  { title: "COSTO RETIROS (BS)", key: "withdrawals_cost_bs", align: "end", sortable: false },
  { title: "VALOR INVENTARIO (BS)", key: "final_inventory_value_bs", align: "end", sortable: false },
  { title: "ACCIONES", key: "actions", align: "center", sortable: false, width: "70px" },
];

onMounted(() => {
  fetchBook();
});
</script>

<template>
  <div class="inventory-book-detail-page">
    <!-- Encabezado de Navegación y Período -->
    <VCard class="mb-4 pa-4 rounded-lg">
      <div class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div class="d-flex align-center gap-3">
          <VBtn icon variant="tonal" size="small" @click="router.push('/fiscal/libros/inventario')">
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div>
            <div class="d-flex align-center gap-2">
              <h1 class="text-h5 font-weight-bold mb-0">
                {{ book?.period_label || "Libro de Inventario" }}
              </h1>
              <VChip
                v-if="book"
                :color="book.is_closed ? 'success' : 'warning'"
                size="small"
                label
                class="font-weight-bold"
              >
                {{ book.is_closed ? "Cerrado" : "Abierto" }}
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mb-0">
              Rango: {{ book?.start_date }} al {{ book?.end_date }} | Art. 177 LISR y Art. 91 RLISR
            </p>
          </div>
        </div>

        <!-- Grupo de botones estilizados como en AppFilterBase -->
        <div class="d-flex align-center flex-wrap gap-1">
          <!-- Exportar Excel -->
          <VBtn
            icon
            color="success"
            variant="tonal"
            size="38"
            rounded="circle"
            @click="handleExportExcel"
          >
            <VIcon icon="tabler-file-spreadsheet" size="20" />
            <VTooltip activator="parent" location="top">Exportar Excel SENIAT</VTooltip>
          </VBtn>

          <!-- Exportar PDF Libro -->
          <VBtn
            icon
            color="error"
            variant="tonal"
            size="38"
            rounded="circle"
            @click="handleExportPdf"
          >
            <VIcon icon="tabler-file-type-pdf" size="20" />
            <VTooltip activator="parent" location="top">Exportar PDF Libro Art. 177</VTooltip>
          </VBtn>

          <!-- Exportar Acta Desincorporación -->
          <VBtn
            v-if="Number(book?.total_withdrawal_units || 0) > 0"
            icon
            color="warning"
            variant="tonal"
            size="38"
            rounded="circle"
            @click="handleExportActa"
          >
            <VIcon icon="tabler-file-certificate" size="20" />
            <VTooltip activator="parent" location="top">Acta Desincorporación y Débito IVA</VTooltip>
          </VBtn>

          <!-- Cerrar Período / Congelar -->
          <VBtn
            v-if="book?.is_open"
            icon
            color="primary"
            variant="tonal"
            size="38"
            rounded="circle"
            :loading="closing"
            @click="handleCloseBook"
          >
            <VIcon icon="tabler-lock" size="20" />
            <VTooltip activator="parent" location="top">Cerrar y Congelar Período</VTooltip>
          </VBtn>

          <!-- Reabrir Período -->
          <VBtn
            v-if="book?.is_closed"
            icon
            color="warning"
            variant="tonal"
            size="38"
            rounded="circle"
            @click="handleReopenBook"
          >
            <VIcon icon="tabler-lock-open" size="20" />
            <VTooltip activator="parent" location="top">Reabrir Período para Ajustes</VTooltip>
          </VBtn>
        </div>
      </div>
    </VCard>

    <!-- Tarjetas de Resumen Ejecutivo -->
    <VRow dense class="mb-4">
      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border">
          <div class="text-caption text-medium-emphasis">Valor Anterior Bs</div>
          <div class="text-subtitle-1 font-weight-bold text-slate-800">
            {{ formatCurrency(book?.total_previous_value_bs) }} Bs.
          </div>
          <span class="text-caption font-weight-medium text-medium-emphasis">
            {{ formatUnits(book?.total_initial_units) }} unid.
          </span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border">
          <div class="text-caption text-medium-emphasis">Entradas Compras</div>
          <div class="text-subtitle-1 font-weight-bold text-success">
            +{{ formatCurrency(book?.total_purchase_cost_bs) }} Bs.
          </div>
          <span class="text-caption font-weight-medium text-success">
            +{{ formatUnits(book?.total_purchase_units) }} unid.
          </span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border">
          <div class="text-caption text-medium-emphasis">Salidas Ventas</div>
          <div class="text-subtitle-1 font-weight-bold text-info">
            -{{ formatCurrency(book?.total_sales_cost_bs) }} Bs.
          </div>
          <span class="text-caption font-weight-medium text-info">
            -{{ formatUnits(book?.total_sales_units) }} unid.
          </span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border">
          <div class="text-caption text-medium-emphasis">Retiros / Mermas</div>
          <div class="text-subtitle-1 font-weight-bold text-warning">
            -{{ formatCurrency(book?.total_withdrawal_cost_bs) }} Bs.
          </div>
          <span class="text-caption font-weight-medium text-warning">
            -{{ formatUnits(book?.total_withdrawal_units) }} unid.
          </span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border bg-primary-subtle">
          <div class="text-caption font-weight-bold text-primary">Valor Inventario Final</div>
          <div class="text-subtitle-1 font-weight-bold text-primary">
            {{ formatCurrency(book?.total_final_inventory_value_bs) }} Bs.
          </div>
          <span class="text-caption font-weight-bold text-primary">
            {{ formatUnits(book?.total_final_units) }} unid. finales
          </span>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="2">
        <VCard class="pa-3 text-center rounded-lg border bg-amber-50">
          <div class="text-caption font-weight-bold text-amber-900">Débito IVA Retiros</div>
          <div class="text-subtitle-1 font-weight-bold text-error">
            {{ formatCurrency(book?.total_withdrawal_vat_bs) }} Bs.
          </div>
          <span class="text-caption text-medium-emphasis">Forma 30 SENIAT</span>
        </VCard>
      </VCol>
    </VRow>

    <!-- Barra de Filtros y Búsqueda de Productos -->
    <VCard class="mb-4 pa-3 rounded-lg">
      <VRow dense align="center">
        <VCol cols="12" sm="5" md="4">
          <VTextField
            v-model="searchQuery"
            density="compact"
            variant="outlined"
            placeholder="Buscar por código ID o descripción..."
            prepend-inner-icon="tabler-search"
            clearable
            hide-details
          />
        </VCol>

        <VCol cols="12" sm="4" md="4">
          <VBtnToggle
            v-model="filterType"
            mandatory
            density="compact"
            color="primary"
            variant="outlined"
          >
            <VBtn value="all">Todos ({{ entries.length }})</VBtn>
            <VBtn value="with_activity">Con Movimiento</VBtn>
            <VBtn value="with_withdrawals">Con Retiros</VBtn>
          </VBtnToggle>
        </VCol>

        <VCol cols="12" sm="3" md="4" class="d-flex align-center justify-end gap-3">
          <VSwitch
            v-if="book?.is_open"
            :model-value="book.include_negative_adjustments_as_withdrawals"
            color="primary"
            density="compact"
            hide-details
            @update:model-value="handleToggleAdjustments"
          >
            <template #label>
              <span class="text-caption font-weight-medium">Ajustes a Retiros</span>
            </template>
          </VSwitch>
          <span class="text-caption text-medium-emphasis text-no-wrap">
            {{ filteredEntries.length }} / {{ entries.length }}
          </span>
        </VCol>
      </VRow>
    </VCard>

    <!-- Tabla Detallada Art. 177 LISR -->
    <VCard class="rounded-lg">
      <VDataTable
        :headers="tableHeaders"
        :items="filteredEntries"
        :loading="loading"
        items-per-page="20"
        density="compact"
        class="elevation-0 table-art-177"
      >
        <!-- Slot Código -->
        <template #item.product_code="{ item }">
          <span class="font-weight-bold text-caption">{{ item.product_code }}</span>
        </template>

        <!-- Slot Descripción -->
        <template #item.product_description="{ item }">
          <div class="d-flex align-center gap-1">
            <span class="text-caption font-weight-medium">{{ item.product_description }}</span>
            <VChip
              v-if="item.is_manual_override"
              size="x-small"
              color="info"
              variant="tonal"
              label
            >
              Manual
            </VChip>
          </div>
        </template>

        <!-- Slot Costo Unitario -->
        <template #item.unit_cost_bs="{ item }">
          <span class="font-weight-bold">{{ formatCurrency(item.unit_cost_bs) }}</span>
        </template>

        <!-- Slot Existencia Final -->
        <template #item.final_stock="{ item }">
          <span class="font-weight-bold text-slate-900">{{ formatUnits(item.final_stock) }}</span>
        </template>

        <!-- Slot Retiros -->
        <template #item.withdrawals="{ item }">
          <span
            v-if="item.withdrawals > 0"
            class="font-weight-bold text-warning"
          >
            {{ formatUnits(item.withdrawals) }}
          </span>
          <span v-else class="text-medium-emphasis">0</span>
        </template>

        <!-- Slot Valor Inventario -->
        <template #item.final_inventory_value_bs="{ item }">
          <span class="font-weight-bold text-primary">
            {{ formatCurrency(item.final_inventory_value_bs) }}
          </span>
        </template>

        <!-- Slot Acciones -->
        <template #item.actions="{ item }">
          <VBtn
            v-if="book?.is_open"
            icon
            variant="text"
            size="x-small"
            color="primary"
            title="Ajustar Renglón / Retiro"
            @click="openEditDialog(item)"
          >
            <VIcon size="16">tabler-edit</VIcon>
          </VBtn>
        </template>

        <template #no-data>
          <div class="py-6 text-center text-medium-emphasis">
            No hay productos que coincidan con la búsqueda.
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Modal para Ajustar / Reclasificar Renglón -->
    <VDialog v-model="showEditDialog" max-width="600" persistent>
      <VCard class="rounded-lg">
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <div class="d-flex align-center gap-2">
            <VAvatar color="primary" variant="tonal" size="36">
              <VIcon icon="tabler-adjustments" size="20" />
            </VAvatar>
            <div>
              <span class="text-h6 font-weight-bold">Ajustar Renglón Art. 177</span>
              <div class="text-caption text-medium-emphasis">{{ editingEntry?.product_description }}</div>
            </div>
          </div>
          <VBtn icon variant="text" size="small" :disabled="savingEntry" @click="showEditDialog = false">
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-4">
          <VRow dense>
            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.unit_cost_bs"
                label="Costo Unitario en Bs"
                type="number"
                step="0.0001"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.initial_stock"
                label="Existencia Inicial"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.purchase_entries"
                label="Entradas Compras"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.sales_exits"
                label="Salidas Ventas"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.withdrawals"
                label="Retiros (Mermas/Vencimientos)"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model.number="editForm.self_consumptions"
                label="Auto Consumos"
                type="number"
                step="0.01"
                min="0"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VSelect
                v-model="editForm.iva_rate"
                :items="[
                  { title: 'Exento (0%)', value: 0 },
                  { title: 'General (16%)', value: 0.16 },
                ]"
                label="Tratamiento IVA Retiro"
                variant="outlined"
                density="compact"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <VTextField
                v-model="editForm.withdrawal_reason"
                label="Motivo Desincorporación"
                placeholder="Vencimiento, rotura, merma..."
                variant="outlined"
                density="compact"
              />
            </VCol>
          </VRow>

          <!-- Previsualización de Ecuación Cuadrada -->
          <VCard variant="tonal" color="primary" class="pa-3 mt-3 rounded">
            <div class="d-flex justify-space-between text-caption font-weight-medium">
              <span>Existencia Final Resultante:</span>
              <span class="font-weight-bold">{{ formatUnits(computedModalFinalStock) }} unid.</span>
            </div>
            <div class="d-flex justify-space-between text-caption font-weight-medium mt-1">
              <span>Valor Total Inventario:</span>
              <span class="font-weight-bold">{{ formatCurrency(computedModalFinalValueBs) }} Bs.</span>
            </div>
            <div
              v-if="editForm.withdrawals > 0"
              class="d-flex justify-space-between text-caption font-weight-medium mt-1 text-warning"
            >
              <span>Débito IVA Estimado por Retiro:</span>
              <span class="font-weight-bold">{{ formatCurrency(computedModalWithdrawalVatBs) }} Bs.</span>
            </div>
          </VCard>

          <div class="d-flex justify-end gap-2 mt-5">
            <VBtn variant="outlined" color="secondary" :disabled="savingEntry" @click="showEditDialog = false">
              Cancelar
            </VBtn>
            <VBtn color="primary" :loading="savingEntry" @click="handleSaveEntry">
              Guardar y Recalcular
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.inventory-book-detail-page {
  width: 100%;
}
.table-art-177 :deep(th) {
  font-size: 11px !important;
  font-weight: 700 !important;
}
.table-art-177 :deep(td) {
  font-size: 11.5px !important;
}
</style>
