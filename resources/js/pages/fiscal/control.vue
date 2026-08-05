<script setup>
import FiscalCommandHistoryTable from "@/components/fiscal/FiscalCommandHistoryTable.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, reactive, ref } from "vue";
import { useDisplay } from "vuetify";

// --- Composables & Estados ---
const { mobile } = useDisplay();
const fetchingHistory = ref(false);
const checkingConnection = ref(false);
const isBridgeConnected = ref(false); // Por defecto en falso hasta verificar backend
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

const checkBridgeStatus = async (showToast = true) => {
  checkingConnection.value = true;
  try {
    const response = await axios.get("/commands/status");
    isBridgeConnected.value = !!response.data?.is_connected;

    if (showToast) {
      if (isBridgeConnected.value) {
        toast.success("Puente fiscal conectado y respondiendo.");
      } else {
        toast.error("El puente fiscal no está ejecutándose en la estación local.");
      }
    }
  } catch (error) {
    isBridgeConnected.value = false;
    if (showToast) {
      toast.error("Sin comunicación con el puente fiscal.");
    }
  } finally {
    checkingConnection.value = false;
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
    await checkBridgeStatus(false);
  } catch (error) {
    console.error(`Error al enviar el comando ${commandKey}:`, error);
    isBridgeConnected.value = false;
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
  checkBridgeStatus(false);
  poller = setInterval(() => {
    fetchCommands(true);
    checkBridgeStatus(false);
  }, 5000);
});

onUnmounted(() => {
  if (poller) clearInterval(poller);
});
</script>

<template>
  <div class="fiscal-control-container">
    <VRow>
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
              <!-- Botón Dinámico de Conexión del Puente Fiscal -->
              <VBtn
                :color="isBridgeConnected ? 'success' : 'error'"
                size="small"
                variant="tonal"
                class="font-weight-bold"
                :loading="checkingConnection"
                @click="checkBridgeStatus"
              >
                <VIcon
                  start
                  :icon="isBridgeConnected ? 'tabler-plug-connected' : 'tabler-plug-x'"
                  size="16"
                />
                {{ isBridgeConnected ? 'Puente Fiscal Activo' : 'Puente Fiscal Desconectado' }}
              </VBtn>

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
              clearable
            />
            <VBtn
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-printer"
              block
              :disabled="!zReportNumber?.trim()"
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
              clearable
            />

            <VBtn
              color="warning"
              block
              prepend-icon="tabler-circle-x"
              :disabled="!invoiceNumber?.trim()"
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

      <!-- Historial de Acciones (Componente Desacoplado) -->
      <VCol cols="12">
        <FiscalCommandHistoryTable
          :commands="commands"
          :loading="fetchingHistory"
          @refresh="fetchCommands(false)"
        />
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
  </div>
</template>

<style scoped>
.bg-info-tonal {
  background-color: rgba(var(--v-theme-info), 0.12) !important;
}

.bg-warning-tonal {
  background-color: rgba(var(--v-theme-warning), 0.12) !important;
}

.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.bg-light-warning {
  background-color: rgba(var(--v-theme-warning), 0.05) !important;
}
</style>

