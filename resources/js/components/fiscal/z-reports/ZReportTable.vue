<script setup>
import AppEmptyState from "@/components/AppEmptyState.vue";
import { ref } from "vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  reports: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalReports: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "view-detail"]);

const fileInput = ref(null);
const verifyingId = ref(null);
const activeReportId = ref(null);

const triggerUpload = (id) => {
  activeReportId.value = id;
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const handleFileUpload = async (event) => {
  const file = event.target.files[0];
  if (!file || !activeReportId.value) return;

  verifyingId.value = activeReportId.value;
  const formData = new FormData();
  formData.append("image", file);

  try {
    const response = await axios.post(`/fiscal/z-reports/${activeReportId.value}/verify`, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    const updatedStatus = response.data.data?.status || response.data.status;
    const notes = response.data.data?.ai_verification_notes || response.data.ai_verification_notes;

    const reportIndex = props.reports.findIndex((r) => r.id === activeReportId.value);
    if (reportIndex !== -1) {
      props.reports[reportIndex].status = updatedStatus;
      props.reports[reportIndex].ai_verification_notes = notes;
    }

    if (updatedStatus === "COMPROBADO") {
      toast.success("Verificación exitosa: Los datos coinciden.");
    } else {
      toast.warning("Discrepancia detectada en el ticket.");
    }
  } catch (error) {
    console.error("Error AI verify:", error);
    toast.error("Error al verificar la imagen con la IA.");
  } finally {
    verifyingId.value = null;
    activeReportId.value = null;
    event.target.value = "";
  }
};

const headers = [
  {
    title: "N° REPORTE",
    key: "report_number",
    sortable: true,
    value: (item) => item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}`,
    cellProps: { class: "text-sm font-weight-black", style: "color: #e91e63 !important;" },
  },
  {
    title: "FECHA",
    key: "report_date",
    sortable: true,
    value: (item) => {
      const dt = item.report_date;
      if (!dt) return "";
      if (typeof dt === "string" && /^\d{4}-\d{2}-\d{2}/.test(dt.trim())) {
        const parts = dt.trim().split("T")[0].split("-");
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
      }
      return new Date(dt).toLocaleDateString("es-VE");
    },
    cellProps: { class: "text-sm text-medium-emphasis" },
  },

  {
    title: "EXENTO",
    key: "exempt_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.exempt_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "BASE",
    key: "base_16_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.base_16_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "IVA",
    key: "iva_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.iva_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "SUBTOTAL",
    key: "subtotal",
    sortable: false,
    align: "end",
    value: (item) => formatCurrency((Number(item.total_amount) || 0) - (Number(item.igtf_amount) || 0)),
    cellProps: { class: "text-sm font-weight-bold text-high-emphasis" },
  },
  {
    title: "IGTF",
    key: "igtf_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.igtf_amount),
    cellProps: { class: "text-sm font-weight-bold text-error" },
  },
  {
    title: "TOTAL",
    key: "total_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.total_amount),
    cellProps: { class: "text-sm font-weight-black text-high-emphasis" },
  },
  { title: "ESTADO", key: "status", sortable: true, align: "center" },
  { title: "ACCIÓN", key: "actions", sortable: false, align: "center" },
];

const formatCurrency = (value) => {
  return new Intl.NumberFormat("es-VE", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};
</script>

<template>
  <div class="z-report-table-container">
    <!-- Vista de Escritorio -->
    <div class="d-none d-md-block">
      <VCard border variant="flat" class="rounded-lg overflow-hidden">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.reports"
          :items-length="props.totalReports"
          :loading="props.loading"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Estado Vacío -->
          <template #no-data>
            <AppEmptyState
              icon="tabler-receipt-off"
              title="No se encontraron reportes Z"
              description="Ajusta los filtros de búsqueda o el rango de fechas para visualizar los cortes Z."
              class="py-6"
            />
          </template>

          <template #item.report_number="{ item }">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-file-analytics" size="18" :color="item.status === 'open' ? 'success' : 'primary'" />
              <span :class="['font-weight-black', item.status === 'open' ? 'text-success' : 'text-primary']">
                {{ item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}` }}
              </span>
            </div>
          </template>

          <template #item.total_amount="{ item }">
            <div class="d-flex flex-column align-end gap-1">
              <span class="font-weight-black text-success">
                Bs. {{ formatCurrency(item.total_amount) }}
              </span>
              <VChip
                size="x-small"
                :color="item.status === 'open' ? 'success' : (item.invoices_count > 0 ? 'info' : 'secondary')"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ item.invoices_count }} {{ item.invoices_count === 1 ? 'Doc' : 'Docs' }}
              </VChip>
            </div>
          </template>

          <template #item.status="{ item }">
            <VTooltip v-if="item.status === 'DISCREPANCIA'" location="top" :text="item.ai_verification_notes || 'Discrepancia detectada'">
              <template #activator="{ props: tooltipProps }">
                <VChip
                  v-bind="tooltipProps"
                  color="error"
                  variant="flat"
                  size="x-small"
                  class="font-weight-black text-uppercase"
                >
                  <VIcon icon="tabler-alert-triangle" size="12" class="me-1" />
                  DISCREPANCIA
                </VChip>
              </template>
            </VTooltip>
            <VTooltip v-else-if="item.status === 'COMPROBADO'" location="top" text="Datos validados correctamente por IA">
              <template #activator="{ props: tooltipProps }">
                <VChip
                  v-bind="tooltipProps"
                  color="info"
                  variant="flat"
                  size="x-small"
                  class="font-weight-black text-uppercase"
                >
                  <VIcon icon="tabler-check" size="12" class="me-1" />
                  COMPROBADO
                </VChip>
              </template>
            </VTooltip>
            <VChip
              v-else-if="item.status === 'open'"
              color="success"
              variant="flat"
              size="x-small"
              class="font-weight-black text-uppercase animate-pulse"
            >
              <VIcon icon="tabler-player-play" size="12" class="me-1" />
              En Curso
            </VChip>
            <VChip
              v-else
              color="secondary"
              variant="tonal"
              size="x-small"
              class="font-weight-medium text-uppercase"
            >
              Cerrado
            </VChip>
          </template>

          <!-- Acciones -->
          <template #item.actions="{ item }">
            <div class="d-flex justify-center gap-1">
              <VTooltip location="top" text="Ver Ticket Corte Z">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="text"
                    color="primary"
                    @click="emit('view-detail', item)"
                  >
                    <VIcon icon="tabler-printer" size="20" />
                  </VBtn>
                </template>
              </VTooltip>
              <VTooltip v-if="item.status === 'closed' || item.status === 'COMPROBADO' || item.status === 'DISCREPANCIA'" location="top" text="Verificar con IA (Subir foto)">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="text"
                    color="info"
                    :loading="verifyingId === item.id"
                    @click="triggerUpload(item.id)"
                  >
                    <VIcon icon="tabler-camera-check" size="20" />
                  </VBtn>
                </template>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>

        <!-- Hidden File Input for AI Verification -->
        <input type="file" ref="fileInput" accept="image/*" class="d-none" @change="handleFileUpload" />
      </VCard>
    </div>

    <!-- Vista Móvil Responsive -->
    <div class="d-block d-md-none">
      <div v-if="props.loading" class="d-flex flex-column gap-3">
        <VCard v-for="i in 3" :key="i" border variant="flat" class="pa-4 rounded-lg">
          <VSkeletonLoader type="list-item-two-line, text" />
        </VCard>
      </div>

      <div v-else-if="props.reports.length === 0">
        <VCard border variant="flat" class="pa-6 rounded-lg text-center">
          <AppEmptyState
            icon="tabler-receipt-off"
            title="Sin Reportes Z"
            description="No hay registros para este período."
          />
        </VCard>
      </div>

      <div v-else class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.reports"
          :key="item.id"
          border
          variant="flat"
          class="pa-4 rounded-lg shadow-sm"
        >
          <div class="d-flex justify-space-between align-center mb-2 border-b pb-2">
            <div class="d-flex align-center gap-1">
              <VIcon icon="tabler-file-analytics" color="primary" size="18" />
              <span class="text-subtitle-2 font-weight-black text-primary">
                {{ item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}` }}
              </span>
            </div>
            <VChip size="x-small" color="info" variant="tonal" class="font-weight-bold">
              {{ item.invoices_count }} Docs
            </VChip>
          </div>

          <div class="d-flex flex-column gap-1 text-sm mb-3">
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Fecha:</span>
              <span class="font-weight-medium">{{ item.report_date }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Exento:</span>
              <span>Bs. {{ formatCurrency(item.exempt_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Base:</span>
              <span>Bs. {{ formatCurrency(item.base_16_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">IVA:</span>
              <span>Bs. {{ formatCurrency(item.iva_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between font-weight-bold">
              <span class="text-disabled">Subtotal:</span>
              <span>Bs. {{ formatCurrency((Number(item.total_amount) || 0) - (Number(item.igtf_amount) || 0)) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span class="text-disabled">IGTF:</span>
              <span class="text-error font-weight-bold">Bs. {{ formatCurrency(item.igtf_amount) }}</span>
            </div>
            <div class="d-flex justify-space-between border-t pt-1 font-weight-bold">
              <span>Total:</span>
              <span class="text-high-emphasis font-weight-black">Bs. {{ formatCurrency(item.total_amount) }}</span>
            </div>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              class="flex-grow-1"
              variant="tonal"
              color="primary"
              size="small"
              prepend-icon="tabler-printer"
              @click="emit('view-detail', item)"
            >
              Ticket Z
            </VBtn>
            <VBtn
              v-if="item.status === 'closed' || item.status === 'COMPROBADO' || item.status === 'DISCREPANCIA'"
              variant="tonal"
              color="info"
              size="small"
              prepend-icon="tabler-camera-check"
              :loading="verifyingId === item.id"
              @click="triggerUpload(item.id)"
            >
              Verificar
            </VBtn>
          </div>
        </VCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(thead th) {
  background-color: rgba(var(--v-theme-surface), 0.8) !important;
  font-weight: 800 !important;
  font-size: 0.75rem !important;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.premium-table :deep(tbody tr:hover) {
  background-color: rgba(var(--v-theme-primary), 0.03) !important;
}
</style>
