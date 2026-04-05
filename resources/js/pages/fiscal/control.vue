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
    <VCol cols="12">
      <h1 class="text-h4 mb-4">Control de Máquina Fiscal</h1>
      <p class="text-subtitle-1 text-muted">Gestione reportes, anulaciones y mantenimiento del hardware fiscal.</p>
    </VCol>

    <!-- Card de Reportes Diarios -->
    <VCol cols="12" md="6">
      <VCard title="Reportes Diarios" subtitle="Acciones de cierre y lectura" elevation="2">
        <VCardText class="d-flex flex-wrap gap-4 py-6">
          <VBtn
            color="primary"
            prepend-icon="mdi-file-document-outline"
            size="large"
            :loading="loading"
            @click="handleReportX"
          >
            Reporte X (Lectura)
          </VBtn>
          <VBtn
            color="error"
            variant="elevated"
            prepend-icon="mdi-lock-reset"
            size="large"
            :loading="loading"
            @click="handleReportZ"
          >
            Reporte Z (Cierre)
          </VBtn>
          <VDivider class="my-4" />
          <VTextField
            v-model="zReportNumber"
            label="Número de Reporte Z"
            placeholder="Ej: 0005"
            variant="outlined"
            density="compact"
            class="mb-2"
          />
          <VBtn
            color="secondary"
            variant="tonal"
            prepend-icon="mdi-printer-refresh"
            block
            :loading="loading"
            @click="handleReprintZ"
          >
            Reimprimir Reporte Z
          </VBtn>
        </VCardText>
        <VDivider />
        <VCardText class="text-caption">
          Nota: El reporte Z realiza el cierre de la jornada fiscal. El reporte X es solo de lectura.
        </VCardText>
      </VCard>
    </VCol>

    <!-- Card de Acciones por Factura -->
    <VCol cols="12" md="6">
      <VCard title="Acciones sobre Facturas" subtitle="Anulaciones y Re-impresiones" elevation="2">
        <VCardText class="py-6">
          <VTextField
            v-model="invoiceNumber"
            label="Número de Factura Fiscal"
            placeholder="Ej: 00000054"
            variant="outlined"
            prepend-inner-icon="mdi-barcode-scan"
            class="mb-4"
          />
          <div class="d-flex gap-4">
            <VBtn
              color="warning"
              variant="tonal"
              prepend-icon="mdi-close-circle-outline"
              :loading="loading"
              @click="handleAnnul"
            >
              Anular Factura
            </VBtn>
            <VBtn
              color="info"
              variant="tonal"
              prepend-icon="mdi-printer-eye"
              :loading="loading"
              @click="handleReprint"
            >
              Re-imprimir
            </VBtn>
          </div>
        </VCardText>
        <VDivider />
        <VCardText class="text-caption text-warning">
          Atención: Asegúrese de que el número de factura sea el correcto antes de anular.
        </VCardText>
      </VCard>
    </VCol>

    <!-- Tabla de Historial de Comandos -->
    <VCol cols="12">
      <VCard title="Historial de Acciones" subtitle="Últimos comandos enviados a la impresora">
        <VTable hover>
          <thead>
            <tr>
              <th class="text-left">Comando</th>
              <th class="text-left">Estado</th>
              <th class="text-left">Respuesta / Error</th>
              <th class="text-left">Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cmd in commands" :key="cmd.id">
              <td>
                <VChip :color="getCommandColor(cmd.command)" size="small" variant="flat">
                  {{ cmd.command }}
                </VChip>
              </td>
              <td>
                <VIcon
                  :icon="getStatusIcon(cmd.status)"
                  :color="getStatusColor(cmd.status)"
                  class="me-2"
                />
                {{ cmd.status }}
              </td>
              <td class="text-truncate" style="max-width: 300px;">
                {{ cmd.response || (cmd.status === 'pending' ? 'Esperando impresora...' : '-') }}
              </td>
              <td>{{ cmd.created_at }}</td>
            </tr>
            <tr v-if="commands.length === 0">
              <td colspan="4" class="text-center py-4 text-muted">No hay acciones recientes.</td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </VCol>

    <!-- Estado del Trabajador (Python) -->
    <VCol cols="12">
      <VAlert
        type="info"
        variant="tonal"
        title="Estado del Puente Fiscal"
        text="Asegúrese de que el script 'fiscal_bridge.py' esté ejecutándose en la computadora conectada a la impresora para procesar estos comandos."
        closable
      />
    </VCol>
  </VRow>
</template>

<script>
// Funciones auxiliares para la UI
const getCommandColor = (cmd) => {
  if (cmd.includes('REPORT_Z')) return 'error';
  if (cmd.includes('REPORT_X')) return 'primary';
  if (cmd.includes('ANNUL')) return 'warning';
  if (cmd.includes('PRINT_INVOICE')) return 'success';
  return 'info';
};

const getStatusColor = (status) => {
  if (status === 'success') return 'success';
  if (status === 'error') return 'error';
  return 'warning';
};

const getStatusIcon = (status) => {
  if (status === 'success') return 'mdi-check-circle';
  if (status === 'error') return 'mdi-alert-circle';
  return 'mdi-clock-outline';
};
</script>

<style scoped>
.gap-4 {
  gap: 1rem;
}
</style>
