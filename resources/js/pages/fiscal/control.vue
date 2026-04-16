<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, onUnmounted, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();
const loading = ref(false);
const commands = ref([]);
const invoiceNumber = ref("");
const zReportNumber = ref("");
const lastZReport = ref(null);
let poller = null;

const fetchCommands = async () => {
  try {
    const response = await axios.get("/fiscal/commands/history");
    commands.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error al obtener historial de comandos:", error);
  }
};

const sendCommand = async (command, payload = {}) => {
  loading.value = true;
  try {
    const response = await axios.post("/fiscal/commands", {
      command,
      payload
    });
    toast.success(response.data.message || "Comando encolado correctamente.");
    fetchCommands();
  } catch (error) {
    console.error("Error al enviar comando:", error);
    toast.error(error.response?.data?.error || "Error al conectar con el servidor.");
  } finally {
    loading.value = false;
  }
};

const handleReportZ = () => {
    toast.confirm("¿Seguro que desea generar el Reporte Z? Esto cerrará la jornada fiscal.", () => {
        sendCommand('REPORT_Z');
    });
};

const handleReprintZ = () => {
    if (!zReportNumber.value) return toast.error("Ingrese un número de Reporte Z.");
    toast.confirm(`¿Desea reimprimir el Reporte Z #${zReportNumber.value}?`, () => {
        sendCommand('REPRINT_REPORT_Z', { z_number: zReportNumber.value });
    });
};

const handleReportX = () => sendCommand('REPORT_X');

const handleAnnul = () => {
  if (!invoiceNumber.value) return toast.error("Ingrese un número de factura.");
  toast.confirm(`¿Seguro que desea ANULAR la factura ${invoiceNumber.value}?`, () => {
    sendCommand('ANNUL_INVOICE', { invoice_number: invoiceNumber.value });
  });
};

const handleReprint = () => {
  if (!invoiceNumber.value) return toast.error("Ingrese un número de factura.");
  sendCommand('REPRINT_INVOICE', { invoice_number: invoiceNumber.value });
};

onMounted(() => {
  fetchCommands();
  poller = setInterval(fetchCommands, 5000);
});

onUnmounted(() => {
  if (poller) clearInterval(poller);
});
</script>

<template>
  <VRow :class="mobile ? 'pa-2' : 'pa-4'">
    <!-- Card de Reportes Diarios -->
    <VCol cols="12" md="6">
      <VCard border variant="flat" class="rounded-lg h-100">
        <VCardItem>
          <template #prepend>
            <div class="pa-2 bg-info-tonal rounded-lg me-1">
              <VIcon icon="tabler-file-analytics" color="info" size="24" />
            </div>
          </template>
          <VCardTitle class="font-weight-black">Reportes Diarios</VCardTitle>
          <VCardSubtitle>Acciones de cierre y lectura fiscal</VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-4">
          <div class="d-flex flex-wrap gap-4">
            <VBtn
              color="info"
              variant="tonal"
              prepend-icon="tabler-file-report"
              class="flex-grow-1"
              :loading="loading"
              @click="handleReportX"
            >
              Reporte X
            </VBtn>
            <VBtn
              color="error"
              prepend-icon="tabler-lock-access"
              class="flex-grow-1"
              :loading="loading"
              @click="handleReportZ"
            >
              Reporte Z
            </VBtn>
          </div>

          <VDivider class="my-6" />

          <VLabel class="mb-2 font-weight-bold text-xs uppercase text-disabled letter-spacing-1">Reimpresión de Reporte Z</VLabel>
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
            :loading="loading"
            @click="handleReprintZ"
          >
            Reimprimir Reporte Z
          </VBtn>
        </VCardText>
        
        <VCardText class="bg-light-primary rounded-b-lg py-3">
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
      <VCard border variant="flat" class="rounded-lg h-100">
        <VCardItem>
          <template #prepend>
            <div class="pa-2 bg-warning-tonal rounded-lg me-1">
              <VIcon icon="tabler-file-invoice" color="warning" size="24" />
            </div>
          </template>
          <VCardTitle class="font-weight-black">Generar Nota de Crédito</VCardTitle>
          <VCardSubtitle>Procesar devoluciones o anulaciones fiscales</VCardSubtitle>
        </VCardItem>

        <VCardText class="pt-4">
          <p class="text-sm text-medium-emphasis mb-4">Ingrese el número de la factura a la cual se le generará la respectiva nota de crédito.</p>
          
          <VTextField
            v-model="invoiceNumber"
            label="Número de Factura"
            placeholder="Ej: 00000054"
            variant="outlined"
            prepend-inner-icon="tabler-scan"
            class="mb-6"
          />

          <VBtn
            color="warning"
            block
            prepend-icon="tabler-circle-x"
            :loading="loading"
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
          <VCardTitle class="font-weight-black">Historial de Acciones</VCardTitle>
          <VCardSubtitle>Últimos comandos procesados por la impresora</VCardSubtitle>
        </VCardItem>

        <VDivider class="opacity-10" />

        <!-- Vista de Escritorio (Tabla) -->
        <VTable v-if="!mobile" hover class="premium-table text-no-wrap">
          <thead>
            <tr>
              <th class="text-xs uppercase font-weight-black">Comando</th>
              <th class="text-xs uppercase font-weight-black">Estado</th>
              <th class="text-xs uppercase font-weight-black">Respuesta / Error</th>
              <th class="text-xs uppercase font-weight-black">Fecha</th>
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
                  {{ cmd.command.replace('_', ' ') }}
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
              <td class="text-truncate" style="max-width: 300px;">
                <span class="text-sm text-medium-emphasis">
                  {{ cmd.response || (cmd.status === 'pending' ? 'Esperando impresora...' : '-') }}
                </span>
              </td>
              <td>
                <span class="text-sm text-medium-emphasis">{{ cmd.created_at }}</span>
              </td>
            </tr>
            <tr v-if="commands.length === 0">
              <td colspan="4" class="text-center py-8">
                <div class="d-flex flex-column align-center gap-2">
                  <VIcon icon="tabler-database-off" size="40" class="text-disabled" />
                  <span class="text-medium-emphasis font-weight-medium">No hay acciones recientes registradas.</span>
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
                {{ cmd.command.replace('_', ' ') }}
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
                <span class="text-super-xs font-weight-medium">{{ cmd.created_at }}</span>
              </div>
            </div>
          </div>

          <div v-if="commands.length === 0" class="text-center py-8 d-flex flex-column align-center gap-2 border rounded-lg border-dashed">
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
        <VAlertTitle class="font-weight-black text-info">Estado del Puente Fiscal</VAlertTitle>
        <p class="mb-0 text-sm">
          Asegúrese de que el servicio <strong>fiscal_bridge.py</strong> esté activo en la estación local para procesar los comandos encolados.
        </p>
      </VAlert>
    </VCol>
  </VRow>
</template>

<script>
// Funciones auxiliares para la UI
const getCommandColor = (cmd) => {
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
</script>

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
</style>
