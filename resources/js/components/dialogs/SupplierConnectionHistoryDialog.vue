<script setup>
import { ref, computed, watch } from "vue";
import { useDisplay } from "vuetify";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  supplier: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const { mobile } = useDisplay();

const isVisible = computed({
  get() {
    return props.modelValue;
  },
  set(val) {
    emit("update:modelValue", val);
  },
});

// --- Estado ---
const loading = ref(false);
const historyList = ref([]);
const selectedStatus = ref(null);
const searchInvoiceQuery = ref("");

// --- Carga de datos ---
const fetchHistory = async () => {
  if (!props.supplier?.id) return;
  loading.value = true;
  historyList.value = [];
  selectedStatus.value = null;

  try {
    const response = await axios.get(`/suppliers/${props.supplier.id}/connection-history`);
    historyList.value = response.data.data || [];
    if (historyList.value.length > 0) {
      selectedStatus.value = historyList.value[0];
    }
  } catch (error) {
    console.error("Error al obtener historial de conexiones:", error);
    toast.error("Error al cargar el historial de conexiones.");
  } finally {
    loading.value = false;
  }
};

watch(
  () => [props.modelValue, props.supplier?.id],
  ([visible, supplierId]) => {
    if (visible && supplierId) {
      fetchHistory();
    }
  }
);

const currentInvoices = computed(() => {
  const invs = selectedStatus.value?.details?.invoices || [];
  if (!searchInvoiceQuery.value) return invs;
  const q = searchInvoiceQuery.value.toLowerCase().trim();
  return invs.filter(
    (i) =>
      String(i.invoice_number || "").toLowerCase().includes(q) ||
      String(i.control_number || "").toLowerCase().includes(q)
  );
});

const getStatusColor = (status) => {
  switch (status) {
    case "completed":
      return "success";
    case "processing":
      return "warning";
    case "failed":
      return "error";
    default:
      return "secondary";
  }
};

const getActionColor = (action) => {
  switch (action) {
    case "created":
      return "success";
    case "updated":
      return "info";
    case "skipped":
      return "secondary";
    default:
      return "default";
  }
};

const closeDialog = () => {
  isVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="950"
    persistent
    scrollable
    :fullscreen="mobile"
  >
    <VCard class="rounded-xl border-0 shadow-lg overflow-hidden d-flex flex-column" style="max-block-size: 90vh;">
      <!-- Cabecera -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="px-4 py-3 bg-primary d-flex align-center justify-space-between text-white" style="background: linear-gradient(135deg, #7A0099, #E20074) !important;">
          <div class="d-flex align-center gap-2.5">
            <VAvatar color="white" variant="flat" size="36" class="elevation-1">
              <VIcon color="primary" size="20">tabler-history</VIcon>
            </VAvatar>
            <div>
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0" style="color: white !important;">
                Historial de Conexiones y Facturas Traídas
              </h2>
              <span class="text-caption text-white opacity-90 font-weight-medium" style="color: white !important;">
                {{ supplier?.name || "Proveedor" }} (ID #{{ supplier?.id }})
              </span>
            </div>
          </div>
          <VBtn icon variant="tonal" color="white" size="x-small" @click="closeDialog" class="rounded-lg">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <!-- Contenido Principal -->
      <VCardText class="pa-3 bg-light flex-grow-1 overflow-y-auto" style="max-block-size: calc(90vh - 120px);">
        <!-- Estado de carga -->
        <div v-if="loading" class="pa-8 text-center bg-white rounded-lg border shadow-sm">
          <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
          <div class="text-xs font-weight-black text-primary text-uppercase tracking-wider">Cargando historial de conexiones...</div>
        </div>

        <!-- Sin registros -->
        <AppEmptyState
          v-else-if="!historyList.length"
          title="Sin historial de sincronizaciones"
          message="Este proveedor no cuenta con registros previos de conexiones o sincronización de facturas."
          icon="tabler-plug-connected-x"
        />

        <!-- Contenido Dividido en 2 Columnas -->
        <VRow v-else dense align="stretch">
          <!-- Columna 1: Lista de Conexiones Realizadas -->
          <VCol cols="12" md="4" class="pe-md-2">
            <div class="d-flex align-center justify-space-between mb-1.5">
              <span class="text-xs font-weight-black text-uppercase text-medium-emphasis tracking-wider">
                Sincronizaciones
              </span>
              <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black">
                {{ historyList.length }} reg.
              </VChip>
            </div>

            <VCard variant="flat" class="rounded-lg border bg-white overflow-hidden" style="max-height: 520px; overflow-y: auto;">
              <VList lines="two" density="compact" class="pa-1">
                <VListItem
                  v-for="item in historyList"
                  :key="item.id"
                  :active="selectedStatus?.id === item.id"
                  color="primary"
                  class="rounded-lg mb-1 cursor-pointer border"
                  :class="{ 'border-primary bg-primary-lighten-5': selectedStatus?.id === item.id }"
                  @click="selectedStatus = item"
                >
                  <template #prepend>
                    <VAvatar size="28" :color="getStatusColor(item.status)" variant="tonal" class="me-2">
                      <VIcon size="16">
                        {{ item.status === 'completed' ? 'tabler-check' : item.status === 'failed' ? 'tabler-alert-circle' : 'tabler-loader' }}
                      </VIcon>
                    </VAvatar>
                  </template>

                  <VListItemTitle class="text-caption font-weight-bold">
                    {{ item.created_at_formatted }}
                  </VListItemTitle>
                  
                  <VListItemSubtitle class="text-super-xs text-medium-emphasis">
                    {{ item.count_invoice }} facturas · {{ item.count_product }} productos
                  </VListItemSubtitle>

                  <template #append>
                    <VChip
                      :color="getStatusColor(item.status)"
                      size="x-small"
                      variant="flat"
                      class="font-weight-black text-uppercase"
                      style="font-size: 9px; height: 18px;"
                    >
                      {{ item.status }}
                    </VChip>
                  </template>
                </VListItem>
              </VList>
            </VCard>
          </VCol>

          <!-- Columna 2: Detalle de Facturas de la Conexión Seleccionada -->
          <VCol cols="12" md="8" class="ps-md-2 mt-3 mt-md-0">
            <template v-if="selectedStatus">
              <!-- Tarjeta de Resumen de la Conexión -->
              <VCard variant="flat" class="pa-2.5 rounded-lg border bg-white mb-2 shadow-sm">
                <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-1.5">
                  <div class="d-flex align-center gap-2">
                    <span class="text-subtitle-2 font-weight-black text-high-emphasis">
                      Conexión #{{ selectedStatus.id }}
                    </span>
                    <VChip :color="getStatusColor(selectedStatus.status)" size="x-small" variant="tonal" class="font-weight-black text-uppercase">
                      {{ selectedStatus.status }}
                    </VChip>
                  </div>
                  <span class="text-caption text-medium-emphasis font-weight-medium">
                    Ejecutado por: <strong>{{ selectedStatus.user_name }}</strong>
                  </span>
                </div>

                <div class="text-caption text-disabled mb-2">
                  {{ selectedStatus.message || "Conexión procesada correctamente." }}
                </div>

                <VRow dense class="text-center">
                  <VCol cols="4">
                    <div class="pa-1.5 bg-light rounded border">
                      <div class="text-caption font-weight-black text-primary">{{ selectedStatus.count_invoice }}</div>
                      <div class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold" style="font-size: 9px;">Facturas Servidor</div>
                    </div>
                  </VCol>
                  <VCol cols="4">
                    <div class="pa-1.5 bg-light rounded border">
                      <div class="text-caption font-weight-black text-success">{{ selectedStatus.details?.invoices?.length || selectedStatus.count_invoice || 0 }}</div>
                      <div class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold" style="font-size: 9px;">Facturas Analizadas</div>
                    </div>
                  </VCol>
                  <VCol cols="4">
                    <div class="pa-1.5 bg-light rounded border">
                      <div class="text-caption font-weight-black text-info">{{ selectedStatus.count_product }}</div>
                      <div class="text-super-xs text-medium-emphasis text-uppercase font-weight-bold" style="font-size: 9px;">Productos Catálogo</div>
                    </div>
                  </VCol>
                </VRow>
              </VCard>

              <!-- Tabla de Facturas Traídas -->
              <VCard variant="flat" class="rounded-lg border bg-white overflow-hidden shadow-sm">
                <div class="px-3 py-2 border-b d-flex align-center justify-space-between gap-2 bg-light">
                  <span class="text-xs font-weight-black text-uppercase tracking-wider text-high-emphasis">
                    Facturas de Esta Conexión ({{ currentInvoices.length }})
                  </span>
                  <div style="max-width: 200px;">
                    <VTextField
                      v-model="searchInvoiceQuery"
                      placeholder="Filtrar factura/control..."
                      density="compact"
                      hide-details
                      prepend-inner-icon="tabler-search"
                      variant="outlined"
                      class="bg-white text-xs"
                      style="font-size: 11px;"
                    />
                  </div>
                </div>

                <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                  <table class="invoices-audit-table w-100">
                    <thead>
                      <tr>
                        <th class="ps-3 py-1.5 text-left">N° FACTURA</th>
                        <th class="py-1.5 text-left">N° CONTROL</th>
                        <th class="py-1.5 text-left">FECHA</th>
                        <th class="py-1.5 text-end">MONTO USD</th>
                        <th class="pe-3 py-1.5 text-center">ACCIÓN</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="!currentInvoices.length">
                        <td colspan="5" class="text-center py-6 text-caption text-disabled">
                          No hay detalles específicos registrados para esta conexión.
                        </td>
                      </tr>
                      <tr
                        v-for="(inv, idx) in currentInvoices"
                        :key="idx"
                        class="invoice-audit-row"
                      >
                        <td class="ps-3 py-1.5 font-weight-black text-caption text-primary">
                          {{ inv.invoice_number }}
                        </td>
                        <td class="py-1.5 text-caption font-weight-medium text-medium-emphasis">
                          {{ inv.control_number || '—' }}
                        </td>
                        <td class="py-1.5 text-caption text-medium-emphasis">
                          {{ inv.date || '—' }}
                        </td>
                        <td class="py-1.5 text-end text-caption font-weight-bold text-high-emphasis">
                          ${{ Number(inv.total_usd || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                        </td>
                        <td class="pe-3 py-1.5 text-center">
                          <VChip
                            :color="getActionColor(inv.action)"
                            size="x-small"
                            variant="flat"
                            class="font-weight-black text-uppercase"
                            style="font-size: 9px; height: 18px;"
                          >
                            {{ inv.action_label || (inv.action === 'created' ? 'Nueva' : inv.action === 'updated' ? 'Actualizada' : 'Ya Registrada') }}
                          </VChip>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </VCard>
            </template>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botón de Cerrar -->
      <VCardActions class="pa-2.5 bg-white border-t flex-shrink-0 d-flex justify-end">
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-black text-caption px-6"
          height="36"
          @click="closeDialog"
        >
          CERRAR
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.invoices-audit-table {
  border-collapse: collapse;
}

.invoices-audit-table th {
  background: rgba(var(--v-theme-on-surface), 0.03);
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding-block: 6px;
  padding-inline: 8px;
  text-transform: uppercase;
}

.invoices-audit-table td {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05);
  padding-block: 6px;
  padding-inline: 8px;
}

.invoice-audit-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.03);
}
</style>
