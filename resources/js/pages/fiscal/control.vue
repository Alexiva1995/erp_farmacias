<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, reactive, ref } from "vue";
import { useDisplay } from "vuetify";

// --- Composables & Estados ---
const { mobile } = useDisplay();
const fetchingHistory = ref(false);
const commands = ref([]);
const invoiceNumber = ref("");
const zReportNumber = ref("");
let poller = null;

// Gestor de estados de carga independientes por cada botón/acción
const actionLoading = reactive({
  REPORT_X: false,
  REPORT_Z: false,
  REPRINT_REPORT_Z: false,
  ANNUL_INVOICE: false,
  REPRINT_INVOICE: false,
});

// --- Funciones Auxiliares de UI ---
const getCommandColor = (cmd) => {
  if (!cmd) return 'secondary';
  if (cmd.includes('REPORT_Z')) return 'error';
  if (cmd.includes('REPORT_X')) return 'info';
  if (cmd.includes('ANNUL')) return 'warning';
  if (cmd.includes('PRINT_INVOICE')) return 'success';
  return 'secondary';
};

const getStatusColor = (status) => {
  if (status === 'success') return 'success';
  if (status === 'error') return 'error';
  return 'warning';
};

const getStatusIcon = (status) => {
  if (status === 'success') return 'tabler-circle-check';
  if (status === 'error') return 'tabler-alert-circle';
  return 'tabler-clock';
};

// --- Operaciones API ---
const fetchCommands = async (isBackground = false) => {
  if (!isBackground) fetchingHistory.value = true;
  try {
    const response = await axios.get("/commands/history");
    const result = response.data?.data || response.data || [];
    commands.value = Array.isArray(result) ? result : [];
  } catch (error) {
    console.error("Error al obtener historial de comandos fiscales:", error);
  } finally {
    if (!isBackground) fetchingHistory.value = false;
  }
};

const sendCommand = async (commandKey, payload = {}) => {
  actionLoading[commandKey] = true;
  try {
    const response = await axios.post("/commands", {
      command: commandKey,
      payload
    });
    
    toast.success(response.data?.message || "Comando encolado correctamente.");
    await fetchCommands(true);
  } catch (error) {
    console.error(`Error al enviar el comando ${commandKey}:`, error);
    toast.error(error.response?.data?.error || error.response?.data?.message || "Error al conectar con la impresora fiscal.");
  } finally {
    actionLoading[commandKey] = false;
  }
};

// --- Manejadores de Eventos de la Interfaz ---
const handleReportX = () => {
  sendCommand('REPORT_X');
};

const handleReportZ = () => {
  toast.confirm("¿Seguro que desea generar el Reporte Z? Esto cerrará la jornada fiscal actual.", () => {
    sendCommand('REPORT_Z');
  });
};

const handleReprintZ = () => {
  const zNum = zReportNumber.value?.trim();
  if (!zNum) {
    return toast.error("Por favor, ingrese un número de Reporte Z.");
  }
  toast.confirm(`¿Desea reimprimir el Reporte Z #${zNum}?`, () => {
    sendCommand('REPRINT_REPORT_Z', { z_number: zNum });
  });
};

const handleAnnul = () => {
  const invNum = invoiceNumber.value?.trim();
  if (!invNum) {
    return toast.error("Por favor, ingrese un número de factura válido.");
  }
  toast.confirm(`¿Seguro que desea emitir una Nota de Crédito para la factura ${invNum}?`, () => {
    sendCommand('ANNUL_INVOICE', { invoice_number: invNum });
  });
};

// --- Ciclo de Vida ---
onMounted(() => {
  fetchCommands();
  poller = setInterval(() => fetchCommands(true), 5000);
});

onUnmounted(() => {
  if (poller) clearInterval(poller);
});
</script>

<template>
  <VRow :class="mobile ? 'pa-2' : 'pa-4'">
    <!-- Indicador de Estado del Sistema/Puente -->
    <VCol cols="12">
      <VCard border variant="flat" class="rounded-lg bg-surface">
        <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4 py-3">
          <div class="d-flex align-center gap-3">
            <VAvatar color="primary" variant="tonal" size="40">
              <VIcon icon="tabler-printer" size="22" />
            </VAvatar>
            <div>
              <h4 class="text-subtitle-1 font-weight-black mb-0">Consola de Control Fiscal</h4>
              <span class="text-caption text-medium-emphasis">Gestión directa de comandos y emisión de cierres fiscales</span>
            </div>
          </div>
          
          <div class="d-flex align-center gap-2">
            <VChip color="success" size="small" variant="tonal" class="font-weight-bold">
              <VIcon start icon="tabler-plug-connected" size="14" />
              Puente Fiscal Activo
            </VChip>
            <VBtn
              icon="tabler-refresh"
              variant="text"
              color="secondary"
              size="small"
              :loading="fetchingHistory"
              @click="fetchCommands(false)"
            />
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card de Reportes Diarios -->
    <VCol cols="12" md="6">
      <VCard border variant="flat" class="rounded-lg h-100 d-flex flex-column">
        <VCardItem>
          <template #prepend>
            <div class="pa-2 bg-info-tonal rounded-lg me-1">
              <VIcon icon="tabler-file-analytics" color="info" size="24" />
            </div>
          </template>
          <VCardTitle class="font-weight-black">Reportes Diarios</VCardTitle>
          <VCardSubtitle>Acciones de lectura y cierre de jornada fiscal</VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-4 flex-grow-1">
          <div class="d-flex flex-wrap gap-4 mb-4">
            <VBtn
              color="info"
              variant="tonal"
              prepend-icon="tabler-file-report"
              class="flex-grow-1"
              :loading="actionLoading.REPORT_X"
              @click="handleReportX"
            >
              Reporte X
            </VBtn>
            <VBtn
              color="error"
              prepend-icon="tabler-lock-access"
              class="flex-grow-1"
              :loading="actionLoading.REPORT_Z"
              @click="handleReportZ"
            >
              Reporte Z
            </VBtn>
          </div>

          <VDivider class="my-6" />

          <VLabel class="mb-2 font-weight-bold text-xs uppercase text-disabled letter-spacing-1">
            Reimpresión de Reporte Z
          </VLabel>
          <VTextField
            v-model="zReportNumber"
            label="Número de Reporte Z"
            placeholder="Ej: 0005"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-hash"
            class="mb-3"
          />
          <VBtn
            color="secondary"
            variant="tonal"
            prepend-icon="tabler-printer"
            block
            :loading="actionLoading.REPRINT_REPORT_Z"
            @click="handleReprintZ"
          >
            Reimprimir Reporte Z
          </VBtn>
        </VCardText>
        
        <VCardText class="bg-light-primary rounded-b-lg py-3 mt-auto">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-info-circle" size="16" color="primary" />
            <span class="text-caption text-primary font-weight-medium">
              El reporte Z realiza el cierre fiscal definitivo de la jornada.
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card de Acciones por Factura -->
    <VCol cols="12" md="6">
      <VCard border variant="flat" class="rounded-lg h-100 d-flex flex-column">
        <VCardItem>
          <template #prepend>
            <div class="pa-2 bg-warning-tonal rounded-lg me-1">
              <VIcon icon="tabler-file-invoice" color="warning" size="24" />
            </div>
          </template>
          <VCardTitle class="font-weight-black">Generar Nota de Crédito</VCardTitle>
          <VCardSubtitle>Procesar devoluciones o anulaciones fiscales</VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-4 flex-grow-1">
          <p class="text-sm text-medium-emphasis mb-4">
            Ingrese el número de la factura a la cual se le generará la respectiva nota de crédito.
          </p>
          
          <VTextField
            v-model="invoiceNumber"
            label="Número de Factura"
            placeholder="Ej: 00000054"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-scan"
            class="mb-6"
          />

          <VBtn
            color="warning"
            block
            prepend-icon="tabler-circle-x"
            :loading="actionLoading.ANNUL_INVOICE"
            @click="handleAnnul"
          >
            Generar Nota de Crédito
          </VBtn>
        </VCardText>

        <VCardText class="bg-light-warning rounded-b-lg py-3 mt-auto">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-alert-triangle" size="16" color="warning" />
            <span class="text-caption text-warning font-weight-black uppercase">
              Esta acción emitirá un documento fiscal de crédito.
            </span>
          </div>
        </VCardText>
      </VCard>
    </VCol>

    <!-- Historial de Acciones -->
    <VCol cols="12">
      <VCard border variant="flat" class="rounded-lg">
        <VCardItem>
          <template #prepend>
            <div class="pa-2 bg-secondary-tonal rounded-lg me-1">
              <VIcon icon="tabler-history" color="secondary" size="24" />
            </div>
          </template>
          <VCardTitle class="font-weight-black">Historial de Comandos</VCardTitle>
          <VCardSubtitle>Últimas operaciones enviadas a la impresora fiscal</VCardSubtitle>
        </VCardItem>

        <VDivider class="opacity-10" />

        <!-- Vista de Escritorio (Tabla) -->
        <VTable v-if="!mobile" hover class="premium-table text-no-wrap">
          <thead>
            <tr>
              <th class="text-xs uppercase font-weight-black">Comando</th>
              <th class="text-xs uppercase font-weight-black">Estado</th>
              <th class="text-xs uppercase font-weight-black">Respuesta / Detalle</th>
              <th class="text-xs uppercase font-weight-black">Fecha / Hora</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cmd in commands" :key="cmd.id">
              <td>
                <VChip 
                  :color="getCommandColor(cmd.command)" 
                  size="x-small" 
                  variant="tonal"
                  class="font-weight-black text-super-xs"
                >
                  {{ (cmd.command || '').replace('_', ' ') }}
                </VChip>
              </td>
              <td>
                <div class="d-flex align-center gap-2">
                  <VIcon
                    :icon="getStatusIcon(cmd.status)"
                    :color="getStatusColor(cmd.status)"
                    size="18"
                  />
                  <span class="text-sm font-weight-medium text-capitalize">
                    {{ cmd.status }}
                  </span>
                </div>
              </td>
              <td class="text-truncate" style="max-width: 320px;">
                <span class="text-sm text-medium-emphasis">
                  {{ cmd.response || (cmd.status === 'pending' ? 'Esperando respuesta del puente...' : '-') }}
                </span>
              </td>
              <td>
                <span class="text-sm text-medium-emphasis">{{ cmd.created_at || 'Reciente' }}</span>
              </td>
            </tr>
            <tr v-if="commands.length === 0 && !fetchingHistory">
              <td colspan="4" class="text-center py-8">
                <div class="d-flex flex-column align-center gap-2">
                  <VIcon icon="tabler-database-off" size="40" class="text-disabled" />
                  <span class="text-medium-emphasis font-weight-medium">No hay comandos registrados en la cola.</span>
                </div>
              </td>
            </tr>
          </tbody>
        </VTable>

        <!-- Vista de Móvil (Cards) -->
        <div v-else class="pa-4 flex-column d-flex gap-4">
          <div 
            v-for="cmd in commands" 
            :key="cmd.id"
            class="pa-4 border rounded-lg bg-light-surface d-flex flex-column gap-3"
          >
            <div class="d-flex justify-space-between align-center">
              <VChip 
                :color="getCommandColor(cmd.command)" 
                size="x-small" 
                variant="tonal"
                class="font-weight-black text-super-xs"
              >
                {{ (cmd.command || '').replace('_', ' ') }}
              </VChip>
              <div class="d-flex align-center gap-1">
                <VIcon 
                  :icon="getStatusIcon(cmd.status)" 
                  :color="getStatusColor(cmd.status)" 
                  size="16" 
                />
                <span class="text-xs font-weight-bold text-capitalize text-uppercase" :class="`text-${getStatusColor(cmd.status)}`">
                  {{ cmd.status }}
                </span>
              </div>
            </div>

            <div v-if="cmd.response" class="bg-surface pa-3 rounded border-s-4 border-s-primary">
              <p class="text-xs text-medium-emphasis mb-0 leading-normal">
                {{ cmd.response }}
              </p>
            </div>

            <div class="d-flex align-center justify-space-between mt-1">
              <div class="d-flex align-center gap-1 text-disabled">
                <VIcon icon="tabler-calendar" size="14" />
                <span class="text-super-xs font-weight-medium">{{ cmd.created_at || 'Reciente' }}</span>
              </div>
            </div>
          </div>

          <div v-if="commands.length === 0 && !fetchingHistory" class="text-center py-8 d-flex flex-column align-center gap-2 border rounded-lg border-dashed">
            <VIcon icon="tabler-database-off" size="32" class="text-disabled" />
            <span class="text-xs text-medium-emphasis font-weight-medium">No hay historial disponible</span>
          </div>
        </div>
      </VCard>
    </VCol>

    <!-- Estado del Trabajador (Python) -->
    <VCol cols="12">
      <VAlert
        border="start"
        border-color="info"
        variant="tonal"
        icon="tabler-plug-connected"
        closable
        class="rounded-lg"
      >
        <VAlertTitle class="font-weight-black text-info">Estado del Servicio Impresora</VAlertTitle>
        <p class="mb-0 text-sm">
          Asegúrese de que el script <strong>fiscal_bridge.py</strong> esté ejecutándose en la estación local para procesar automáticamente los comandos encolados.
        </p>
      </VAlert>
    </VCol>
  </VRow>
</template>

<style scoped>
.premium-table :deep(th) {
  color: rgba(var(--v-theme-on-surface), 0.9) !important;
  border-inline: none !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(td) {
  border-inline: none !important;
  color: rgba(var(--v-theme-on-surface), 0.8) !important;
  padding-block: 12px !important;
}

.bg-info-tonal {
  background-color: rgba(var(--v-theme-info), 0.12) !important;
}

.bg-warning-tonal {
  background-color: rgba(var(--v-theme-warning), 0.12) !important;
}

.bg-secondary-tonal {
  background-color: rgba(var(--v-theme-secondary), 0.12) !important;
}

.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.bg-light-warning {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
