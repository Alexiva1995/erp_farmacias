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

// Facturas filtradas de la conexión seleccionada
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

const formatAmount = (inv) => {
  const bs = Number(inv.total_amount || 0);
  const usd = Number(inv.total_usd || 0);

  if (bs > 0 && usd > 0) {
    return `Bs. ${bs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ($${usd.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;
  } else if (bs > 0) {
    return `Bs. ${bs.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  } else if (usd > 0) {
    return `$${usd.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }
  return '—';
};

const getStatusBadge = (status) => {
  switch (status) {
    case "completed":
      return { label: "Exitosa", color: "success", icon: "tabler-circle-check-filled" };
    case "processing":
      return { label: "Procesando", color: "warning", icon: "tabler-loader" };
    case "failed":
      return { label: "Fallida", color: "error", icon: "tabler-alert-triangle-filled" };
    default:
      return { label: status || "Pendiente", color: "secondary", icon: "tabler-help-circle" };
  }
};

const getMethodBadge = (method) => {
  switch (String(method || '').toLowerCase()) {
    case 'ftp':
    case 'api':
      return { label: 'FTP / API', color: 'primary', icon: 'tabler-api' };
    case 'bot_scraper':
    case 'dronena_bot':
    case 'drosymca_bot':
      return { label: 'Bot Scraper', color: 'info', icon: 'tabler-robot' };
    case 'cli':
      return { label: 'Terminal CLI', color: 'secondary', icon: 'tabler-terminal-2' };
    case 'email':
      return { label: 'Correo', color: 'warning', icon: 'tabler-mail' };
    case 'cron_job':
      return { label: 'Cron Job', color: 'purple', icon: 'tabler-clock' };
    default:
      return { label: method ? String(method).toUpperCase() : 'API', color: 'primary', icon: 'tabler-plug' };
  }
};

const closeDialog = () => {
  isVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="isVisible"
    max-width="1050"
    persistent
    :fullscreen="mobile"
  >
    <VCard class="rounded-xl border-0 shadow-lg d-flex flex-column history-dialog-card" style="height: 85vh; max-height: 740px; min-height: 540px; overflow: hidden;">
      <!-- Cabecera con espaciado amplio y elegante -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div class="px-6 py-5 bg-primary d-flex align-center justify-space-between text-white" style="background: linear-gradient(135deg, #7A0099, #E20074) !important;">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="42" class="me-3 elevation-1 flex-shrink-0">
              <VIcon color="primary" size="24">tabler-history</VIcon>
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-1" style="color: white !important;">
                Historial de Conexiones — {{ supplier?.name || "Proveedor" }} (ID #{{ supplier?.id }})
              </h2>
              <span class="text-caption text-white opacity-90 font-weight-medium" style="color: white !important; font-size: 11px;">
                Auditoría de facturas y sincronizaciones automáticas
              </span>
            </div>
          </div>
          <VBtn icon variant="tonal" color="white" size="x-small" @click="closeDialog" class="rounded-lg">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <!-- Sub-Barra de Resumen de Conexión Seleccionada -->
      <div v-if="selectedStatus" class="px-4 py-2 border-b bg-surface flex-shrink-0 d-flex flex-wrap align-center justify-space-between gap-2">
        <div class="d-flex align-center gap-2 flex-wrap">
          <span class="text-caption font-weight-bold text-high-emphasis">
            Conexión: #{{ selectedStatus.id }} ({{ selectedStatus.created_at_formatted }})
          </span>
          <span class="text-disabled font-weight-light">|</span>
          <div class="d-flex align-center gap-1">
            <span class="text-caption text-medium-emphasis">Método:</span>
            <VChip
              :color="getMethodBadge(selectedStatus.method).color"
              size="x-small"
              variant="tonal"
              class="font-weight-black text-uppercase px-2"
              style="font-size: 10px; height: 20px;"
            >
              <VIcon start size="12">{{ getMethodBadge(selectedStatus.method).icon }}</VIcon>
              {{ getMethodBadge(selectedStatus.method).label }}
            </VChip>
          </div>
          <span class="text-disabled font-weight-light">|</span>
          <div class="d-flex align-center gap-1">
            <span class="text-caption text-medium-emphasis">Estado:</span>
            <VChip
              :color="getStatusBadge(selectedStatus.status).color"
              size="x-small"
              variant="tonal"
              class="font-weight-black text-uppercase px-2"
              style="font-size: 10px; height: 20px;"
            >
              <VIcon start size="12">{{ getStatusBadge(selectedStatus.status).icon }}</VIcon>
              {{ getStatusBadge(selectedStatus.status).label }}
            </VChip>
          </div>
          <span class="text-disabled font-weight-light">|</span>
          <span class="text-caption text-medium-emphasis">
            Por: <strong class="text-high-emphasis">{{ selectedStatus.user_name }}</strong>
          </span>
        </div>

        <!-- Métricas compactas -->
        <div class="d-flex align-center gap-1.5 flex-wrap">
          <VChip size="x-small" variant="outlined" color="primary" class="font-weight-bold" style="font-size: 10px; height: 22px;">
            {{ selectedStatus.count_invoice }} Facturas Servidor
          </VChip>
          <VChip size="x-small" variant="outlined" color="success" class="font-weight-bold" style="font-size: 10px; height: 22px;">
            {{ selectedStatus.details?.invoices?.length || selectedStatus.count_invoice || 0 }} Facturas Analizadas
          </VChip>
          <VChip size="x-small" variant="outlined" color="info" class="font-weight-bold" style="font-size: 10px; height: 22px;">
            {{ selectedStatus.count_product }} Productos
          </VChip>
        </div>
      </div>

      <!-- Contenido Principal: 2 Columnas con scroll interno garantizado -->
      <VCardText class="pa-3 bg-light flex-grow-1 d-flex flex-column overflow-hidden" style="height: 520px; max-height: 580px;">
        <!-- Estado de carga -->
        <div v-if="loading" class="h-100 d-flex flex-column align-center justify-center bg-white rounded-lg border shadow-sm">
          <VProgressCircular indeterminate color="primary" size="36" class="mb-2" />
          <div class="text-xs font-weight-black text-primary text-uppercase tracking-wider">Cargando historial...</div>
        </div>

        <!-- Sin registros -->
        <div v-else-if="!historyList.length" class="h-100 d-flex align-center justify-center bg-white rounded-lg border">
          <AppEmptyState
            title="Sin historial de sincronizaciones"
            message="Este proveedor no cuenta con registros previos de conexiones o sincronización de facturas."
            icon="tabler-plug-connected-x"
          />
        </div>

        <!-- Contenido en 2 Columnas -->
        <div v-else class="d-flex flex-grow-1 gap-3 overflow-hidden" style="height: 100%; min-height: 0;">
          <!-- Columna 1: Historial Sincronizaciones -->
          <div style="width: 32%; min-width: 260px;" class="d-flex flex-column h-100 overflow-hidden">
            <VCard variant="flat" class="rounded-lg border bg-white d-flex flex-column h-100 overflow-hidden shadow-sm">
              <div class="px-3 py-2 border-b bg-light d-flex align-center justify-space-between flex-shrink-0">
                <span class="text-xs font-weight-black text-uppercase text-medium-emphasis tracking-wider">
                  Historial Sincronizaciones
                </span>
                <VChip size="x-small" variant="tonal" color="primary" class="font-weight-black">
                  {{ historyList.length }}
                </VChip>
              </div>

              <!-- Lista con scroll interno -->
              <div class="flex-grow-1 overflow-y-auto pa-2 custom-scroll" style="min-height: 0;">
                <div
                  v-for="item in historyList"
                  :key="item.id"
                  class="sync-item pa-2 mb-1.5 rounded-lg border cursor-pointer transition-all"
                  :class="{
                    'sync-item-active': selectedStatus?.id === item.id,
                  }"
                  @click="selectedStatus = item"
                >
                  <div class="d-flex align-center justify-space-between mb-1">
                    <div class="d-flex align-center gap-1.5">
                      <span
                        class="status-dot"
                        :class="item.status === 'completed' ? 'dot-success' : item.status === 'failed' ? 'dot-error' : 'dot-warning'"
                      ></span>
                      <span class="text-caption font-weight-bold text-high-emphasis">
                        {{ item.created_at_formatted }}
                      </span>
                    </div>
                    <span class="text-caption font-weight-black text-medium-emphasis" style="font-size: 11px;">
                      ({{ item.count_invoice }})
                    </span>
                  </div>
                  <div class="d-flex align-center justify-space-between ps-3" style="font-size: 10px;">
                    <span class="text-caption text-medium-emphasis" style="font-size: 10px;">
                      #{{ item.id }} · {{ item.count_product }} productos
                    </span>
                    <VChip
                      size="x-small"
                      variant="tonal"
                      :color="getMethodBadge(item.method).color"
                      class="font-weight-black px-1"
                      style="height: 16px; font-size: 9px;"
                    >
                      {{ getMethodBadge(item.method).label }}
                    </VChip>
                  </div>
                </div>
              </div>
            </VCard>
          </div>

          <!-- Columna 2: Facturas Obtenidas -->
          <div style="width: 68%; flex: 1 1 0;" class="d-flex flex-column h-100 overflow-hidden">
            <VCard variant="flat" class="rounded-lg border bg-white d-flex flex-column h-100 overflow-hidden shadow-sm">
              <!-- Barra de Búsqueda y Título -->
              <div class="px-3 py-1.5 border-b bg-light d-flex align-center justify-space-between gap-2 flex-shrink-0">
                <div class="d-flex align-center gap-1.5">
                  <span class="text-xs font-weight-black text-uppercase text-high-emphasis tracking-wider">
                    Facturas Obtenidas (#{{ selectedStatus?.id }})
                  </span>
                  <VChip size="x-small" variant="flat" color="primary" class="font-weight-black px-1.5" style="height: 18px; font-size: 10px;">
                    {{ currentInvoices.length }}
                  </VChip>
                </div>
                <div style="max-width: 220px; width: 100%;">
                  <VTextField
                    v-model="searchInvoiceQuery"
                    placeholder="Buscar factura / control..."
                    density="compact"
                    hide-details
                    prepend-inner-icon="tabler-search"
                    variant="outlined"
                    class="bg-white text-xs search-input"
                  />
                </div>
              </div>

              <!-- Tabla con scroll interno visible y garantizado -->
              <div class="flex-grow-1 overflow-y-auto custom-scroll" style="min-height: 0;">
                <table class="invoices-audit-table w-100">
                  <thead class="sticky-thead">
                    <tr>
                      <th class="ps-3 py-2 text-left">N° FACTURA</th>
                      <th class="py-2 text-left">N° CONTROL</th>
                      <th class="py-2 text-left">FECHA</th>
                      <th class="py-2 text-end">MONTO</th>
                      <th class="pe-3 py-2 text-center" style="width: 130px;">ACCIÓN</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!currentInvoices.length">
                      <td colspan="5" class="text-center py-8 text-caption text-disabled">
                        No hay facturas registradas en este lote de sincronización.
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
                        {{ formatAmount(inv) }}
                      </td>
                      <td class="pe-3 py-1.5 text-center">
                        <VChip
                          v-if="inv.action === 'created'"
                          color="success"
                          size="x-small"
                          variant="flat"
                          class="font-weight-black text-uppercase px-2"
                          style="font-size: 9px; height: 20px;"
                        >
                          Nueva
                        </VChip>
                        <VChip
                          v-else-if="inv.action === 'updated'"
                          color="info"
                          size="x-small"
                          variant="tonal"
                          class="font-weight-black text-uppercase px-2"
                          style="font-size: 9px; height: 20px;"
                        >
                          Actualizada
                        </VChip>
                        <VTooltip
                          v-else-if="inv.action === 'failed'"
                          location="top"
                          max-width="360px"
                        >
                          <template #activator="{ props: tooltipProps }">
                            <VChip
                              v-bind="tooltipProps"
                              color="error"
                              size="x-small"
                              variant="flat"
                              class="font-weight-black text-uppercase px-2 cursor-pointer"
                              style="font-size: 9px; height: 20px;"
                            >
                              Error
                              <VIcon end size="11" icon="tabler-help-circle" class="ms-1" />
                            </VChip>
                          </template>
                          <div class="pa-1 text-caption text-white font-weight-medium" style="font-size: 11px; line-height: 1.4;">
                            {{ inv.error_message || 'Error al procesar la factura en la base de datos' }}
                          </div>
                        </VTooltip>
                        <VChip
                          v-else
                          color="secondary"
                          size="x-small"
                          variant="tonal"
                          class="font-weight-black text-uppercase px-2"
                          style="font-size: 9px; height: 20px;"
                        >
                          Ya Registrada
                        </VChip>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </VCard>
          </div>
        </div>
      </VCardText>

      <!-- Pie de acciones full-width adaptado a los modales del sistema -->
      <VCardActions class="pa-3 bg-white border-t flex-shrink-0">
        <VBtn
          color="secondary"
          variant="outlined"
          block
          height="44"
          class="font-weight-bold text-caption rounded-lg uppercase"
          @click="closeDialog"
        >
          CERRAR
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.sync-item {
  background-color: #ffffff;
  border-color: rgba(var(--v-theme-on-surface), 0.08);
}

.sync-item:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
  border-color: rgba(var(--v-theme-primary), 0.2);
}

.sync-item-active {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
  border-color: rgb(var(--v-theme-primary)) !important;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.dot-success {
  background-color: #28c76f;
  box-shadow: 0 0 4px rgba(40, 199, 111, 0.6);
}

.dot-warning {
  background-color: #ff9f43;
  box-shadow: 0 0 4px rgba(255, 159, 67, 0.6);
}

.dot-error {
  background-color: #ea5455;
  box-shadow: 0 0 4px rgba(234, 84, 85, 0.6);
}

.invoices-audit-table {
  border-collapse: collapse;
}

.sticky-thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f8f9fa;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  padding-block: 8px;
  padding-inline: 8px;
  text-transform: uppercase;
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}

.invoices-audit-table td {
  border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05);
  padding-block: 6px;
  padding-inline: 8px;
}

.invoice-audit-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.03);
}

.custom-scroll {
  overflow-y: auto !important;
  scrollbar-width: thin;
  scrollbar-color: rgba(var(--v-theme-primary), 0.35) rgba(0, 0, 0, 0.04);
}

.custom-scroll::-webkit-scrollbar {
  width: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.03);
}

.custom-scroll::-webkit-scrollbar-thumb {
  background-color: rgba(var(--v-theme-primary), 0.35);
  border-radius: 4px;
}

.search-input :deep(.v-field__input) {
  font-size: 11px !important;
  padding-top: 4px !important;
  padding-bottom: 4px !important;
  min-height: 28px !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}
</style>
