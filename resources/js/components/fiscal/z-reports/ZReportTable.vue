<script setup>
import DialogCloseBtn from "@core/components/DialogCloseBtn.vue";
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

const isPhotoModalOpen = ref(false);
const previewReport = ref(null);

const openPhotoPreview = (item) => {
  previewReport.value = item;
  isPhotoModalOpen.value = true;
};

const triggerUpload = (id) => {
  activeReportId.value = id;
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const resolveImageUrl = (item) => {
  if (!item) return null;
  if (item.image_url) return item.image_url;
  if (item.image_path) {
    return item.image_path.startsWith("http") ? item.image_path : `/storage/${item.image_path}`;
  }
  return null;
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

    const updatedData = response.data.data;
    const reportIndex = props.reports.findIndex((r) => r.id === activeReportId.value);
    if (reportIndex !== -1 && updatedData) {
      Object.assign(props.reports[reportIndex], updatedData);
    }

    if (updatedData?.status === "COMPROBADO") {
      toast.success("Verificación exitosa: Los datos coinciden con la foto.");
    } else {
      toast.warning("Discrepancia detectada entre el sistema y la foto del Reporte Z.");
    }
  } catch (error) {
    console.error("Error AI verify:", error);
    const msg = error.response?.data?.message || "Error al verificar la imagen con la IA.";
    toast.error(msg);
  } finally {
    verifyingId.value = null;
    activeReportId.value = null;
    event.target.value = "";
  }
};

const formatDate = (dt) => {
  if (!dt) return "";
  if (typeof dt === "string" && /^\d{4}-\d{2}-\d{2}/.test(dt.trim())) {
    const parts = dt.trim().split("T")[0].split("-");
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }
  return new Date(dt).toLocaleDateString("es-VE");
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
    value: (item) => formatDate(item.report_date),
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
    cellProps: { class: "text-sm text-medium-emphasis" },
  },
  {
    title: "IGTF",
    key: "igtf_amount",
    sortable: true,
    align: "end",
    value: (item) => formatCurrency(item.igtf_amount),
    cellProps: { class: "text-sm text-medium-emphasis" },
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

          <!-- N° Reporte -->
          <template #item.report_number="{ item }">
            <div class="d-flex flex-column gap-0.5">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-file-analytics" size="18" :color="item.status === 'open' ? 'success' : 'primary'" />
                <span :class="['font-weight-black', item.status === 'open' ? 'text-success' : 'text-primary']">
                  {{ item.report_number_padded || `Z${String(item.report_number).padStart(6, "0")}` }}
                </span>
              </div>
              <div
                v-if="item.discrepancies_map?.report_number"
                class="text-caption text-error font-weight-bold d-flex align-center gap-1"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ item.discrepancies_map.report_number.photo_value }}
              </div>
            </div>
          </template>

          <!-- Fecha -->
          <template #item.report_date="{ item }">
            <div class="d-flex flex-column">
              <span>{{ formatDate(item.report_date) }}</span>
            </div>
          </template>

          <!-- Exento -->
          <template #item.exempt_amount="{ item }">
            <div class="d-flex flex-column align-end">
              <span :class="[item.discrepancies_map?.exempt_amount ? 'text-error font-weight-bold text-decoration-line-through' : 'text-medium-emphasis']">
                {{ formatCurrency(item.exempt_amount) }}
              </span>
              <div
                v-if="item.discrepancies_map?.exempt_amount"
                class="text-caption text-error font-weight-black d-flex align-center gap-1 bg-error-tonal px-1 rounded mt-0.5"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ formatCurrency(item.discrepancies_map.exempt_amount.photo_value) }}
              </div>
            </div>
          </template>

          <!-- Base 16% -->
          <template #item.base_16_amount="{ item }">
            <div class="d-flex flex-column align-end">
              <span :class="[item.discrepancies_map?.base_16_amount ? 'text-error font-weight-bold text-decoration-line-through' : 'text-medium-emphasis']">
                {{ formatCurrency(item.base_16_amount) }}
              </span>
              <div
                v-if="item.discrepancies_map?.base_16_amount"
                class="text-caption text-error font-weight-black d-flex align-center gap-1 bg-error-tonal px-1 rounded mt-0.5"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ formatCurrency(item.discrepancies_map.base_16_amount.photo_value) }}
              </div>
            </div>
          </template>

          <!-- IVA 16% -->
          <template #item.iva_amount="{ item }">
            <div class="d-flex flex-column align-end">
              <span :class="[item.discrepancies_map?.iva_amount ? 'text-error font-weight-bold text-decoration-line-through' : 'text-medium-emphasis']">
                {{ formatCurrency(item.iva_amount) }}
              </span>
              <div
                v-if="item.discrepancies_map?.iva_amount"
                class="text-caption text-error font-weight-black d-flex align-center gap-1 bg-error-tonal px-1 rounded mt-0.5"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ formatCurrency(item.discrepancies_map.iva_amount.photo_value) }}
              </div>
            </div>
          </template>

          <!-- Subtotal -->
          <template #item.subtotal="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-medium-emphasis">
                {{ formatCurrency((Number(item.total_amount) || 0) - (Number(item.igtf_amount) || 0)) }}
              </span>
            </div>
          </template>

          <!-- IGTF 3% -->
          <template #item.igtf_amount="{ item }">
            <div class="d-flex flex-column align-end">
              <span :class="[item.discrepancies_map?.igtf_amount ? 'text-error font-weight-bold text-decoration-line-through' : 'text-medium-emphasis']">
                {{ formatCurrency(item.igtf_amount) }}
              </span>
              <div
                v-if="item.discrepancies_map?.igtf_amount"
                class="text-caption text-error font-weight-black d-flex align-center gap-1 bg-error-tonal px-1 rounded mt-0.5"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ formatCurrency(item.discrepancies_map.igtf_amount.photo_value) }}
              </div>
            </div>
          </template>

          <!-- Total Final -->
          <template #item.total_amount="{ item }">
            <div class="d-flex flex-column align-end gap-0.5">
              <span :class="['text-sm font-weight-black', item.discrepancies_map?.total_amount ? 'text-error text-decoration-line-through' : 'text-high-emphasis']">
                {{ formatCurrency(item.total_amount) }}
              </span>
              <div
                v-if="item.discrepancies_map?.total_amount"
                class="text-caption text-error font-weight-black d-flex align-center gap-1 bg-error-tonal px-1 rounded"
              >
                <VIcon icon="tabler-camera" size="12" />
                {{ formatCurrency(item.discrepancies_map.total_amount.photo_value) }}
              </div>
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

          <!-- Estado -->
          <template #item.status="{ item }">
            <VTooltip v-if="item.status === 'DISCREPANCIA'" location="top" max-width="320">
              <template #activator="{ props: tooltipProps }">
                <VChip
                  v-bind="tooltipProps"
                  color="error"
                  variant="flat"
                  size="x-small"
                  class="font-weight-black text-uppercase cursor-pointer"
                  @click="emit('view-detail', item)"
                >
                  <VIcon icon="tabler-alert-triangle" size="12" class="me-1" />
                  DISCREPANCIA
                </VChip>
              </template>
              <div class="pa-1 text-xs">
                <div class="font-weight-bold mb-1">Discrepancias detectadas:</div>
                <div v-if="item.discrepancies && item.discrepancies.length > 0">
                  <div v-for="(disc, dIdx) in item.discrepancies" :key="dIdx" class="mb-1">
                    • <strong>{{ disc.label || disc.field }}:</strong> Sistema {{ formatCurrency(disc.system_value) }} vs Foto {{ formatCurrency(disc.photo_value) }}
                  </div>
                </div>
                <div v-else>{{ item.ai_verification_notes || 'Valores no coinciden con la foto.' }}</div>
              </div>
            </VTooltip>
            <VTooltip v-else-if="item.status === 'COMPROBADO'" location="top" text="Datos validados correctamente con la foto por IA">
              <template #activator="{ props: tooltipProps }">
                <VChip
                  v-bind="tooltipProps"
                  color="info"
                  variant="flat"
                  size="x-small"
                  class="font-weight-black text-uppercase cursor-pointer"
                  @click="emit('view-detail', item)"
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
            <div class="d-flex justify-center align-center gap-1">
              <!-- Ver Ticket Detalle Z -->
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

              <!-- Ver Foto Z si existe -->
              <VTooltip v-if="resolveImageUrl(item)" location="top" text="Ver Foto del Reporte Z">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon
                    size="small"
                    variant="text"
                    color="success"
                    @click.stop="openPhotoPreview(item)"
                  >
                    <VIcon icon="tabler-photo" size="20" />
                  </VBtn>
                </template>
              </VTooltip>

              <!-- Subir / Verificar con IA -->
              <VTooltip
                v-if="item.status === 'closed' || item.status === 'COMPROBADO' || item.status === 'DISCREPANCIA'"
                location="top"
                :text="item.image_path ? 'Re-verificar con Foto (IA)' : 'Verificar con IA (Subir foto)'"
              >
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
            <div class="d-flex align-center gap-1">
              <VChip
                v-if="item.status === 'DISCREPANCIA'"
                color="error"
                variant="flat"
                size="x-small"
                class="font-weight-black"
              >
                DISCREPANCIA
              </VChip>
              <VChip
                v-else-if="item.status === 'COMPROBADO'"
                color="info"
                variant="flat"
                size="x-small"
                class="font-weight-black"
              >
                COMPROBADO
              </VChip>
              <VChip size="x-small" color="info" variant="tonal" class="font-weight-bold">
                {{ item.invoices_count }} Docs
              </VChip>
            </div>
          </div>

          <div class="d-flex flex-column gap-1 text-sm mb-3">
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Fecha:</span>
              <span class="font-weight-medium">{{ formatDate(item.report_date) }}</span>
            </div>

            <!-- Exento -->
            <div class="d-flex justify-space-between align-center">
              <span class="text-disabled">Exento:</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold text-decoration-line-through': item.discrepancies_map?.exempt_amount}">
                  Bs. {{ formatCurrency(item.exempt_amount) }}
                </span>
                <div v-if="item.discrepancies_map?.exempt_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(item.discrepancies_map.exempt_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- Base -->
            <div class="d-flex justify-space-between align-center">
              <span class="text-disabled">Base:</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold text-decoration-line-through': item.discrepancies_map?.base_16_amount}">
                  Bs. {{ formatCurrency(item.base_16_amount) }}
                </span>
                <div v-if="item.discrepancies_map?.base_16_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(item.discrepancies_map.base_16_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- IVA -->
            <div class="d-flex justify-space-between align-center">
              <span class="text-disabled">IVA:</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold text-decoration-line-through': item.discrepancies_map?.iva_amount}">
                  Bs. {{ formatCurrency(item.iva_amount) }}
                </span>
                <div v-if="item.discrepancies_map?.iva_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(item.discrepancies_map.iva_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- Subtotal -->
            <div class="d-flex justify-space-between">
              <span class="text-disabled">Subtotal:</span>
              <span>Bs. {{ formatCurrency((Number(item.total_amount) || 0) - (Number(item.igtf_amount) || 0)) }}</span>
            </div>

            <!-- IGTF -->
            <div class="d-flex justify-space-between align-center">
              <span class="text-disabled">IGTF:</span>
              <div class="text-end">
                <span :class="{'text-error font-weight-bold text-decoration-line-through': item.discrepancies_map?.igtf_amount}">
                  Bs. {{ formatCurrency(item.igtf_amount) }}
                </span>
                <div v-if="item.discrepancies_map?.igtf_amount" class="text-caption text-error font-weight-bold d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(item.discrepancies_map.igtf_amount.photo_value) }}
                </div>
              </div>
            </div>

            <!-- Total -->
            <div class="d-flex justify-space-between align-center border-t pt-1 font-weight-bold">
              <span>Total:</span>
              <div class="text-end">
                <span :class="['text-high-emphasis font-weight-black', {'text-error text-decoration-line-through': item.discrepancies_map?.total_amount}]">
                  Bs. {{ formatCurrency(item.total_amount) }}
                </span>
                <div v-if="item.discrepancies_map?.total_amount" class="text-caption text-error font-weight-black d-flex align-center justify-end gap-1">
                  <VIcon icon="tabler-camera" size="12" />
                  Bs. {{ formatCurrency(item.discrepancies_map.total_amount.photo_value) }}
                </div>
              </div>
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
              v-if="resolveImageUrl(item)"
              variant="tonal"
              color="success"
              size="small"
              icon
              @click="openPhotoPreview(item)"
            >
              <VIcon icon="tabler-photo" size="18" />
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

    <!-- Modal Visor de Foto del Reporte Z -->
    <VDialog
      v-model="isPhotoModalOpen"
      max-width="600"
      scrollable
    >
      <VCard class="rounded-xl overflow-hidden">
        <!-- Header Premium Institucional -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
              <VIcon icon="tabler-photo" color="primary" size="22" />
            </VAvatar>
            <div class="d-flex flex-column leading-none text-white">
              <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
                Foto del Reporte Z {{ previewReport?.report_number_padded || `Z${String(previewReport?.report_number || '').padStart(6, '0')}` }}
              </h2>
              <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
                Comprobante físico de corte fiscal
              </span>
            </div>
            <VSpacer />
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="isPhotoModalOpen = false"
            />
          </div>
        </VCardTitle>

        <VCardText class="pa-4 bg-background text-center">
          <div v-if="resolveImageUrl(previewReport)" class="d-flex justify-center align-center">
            <img
              :src="resolveImageUrl(previewReport)"
              alt="Foto del Reporte Z"
              class="rounded-lg shadow border"
              style="max-width: 100%; max-height: 65vh; object-fit: contain;"
            />
          </div>
        </VCardText>

        <VCardActions class="pa-4 bg-white border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="outlined"
                block
                class="font-weight-black rounded-lg uppercase"
                @click="isPhotoModalOpen = false"
              >
                Cerrar
              </VBtn>
            </VCol>
            <VCol cols="6" class="pa-1">
              <VBtn
                color="primary"
                variant="flat"
                block
                class="font-weight-black rounded-lg shadow-primary uppercase"
                prepend-icon="tabler-download"
                :href="resolveImageUrl(previewReport)"
                target="_blank"
                :download="`${previewReport?.report_number_padded || `Z${String(previewReport?.report_number || '').padStart(6, '0')}`}.jpg`"
              >
                Descargar Foto
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.text-super-xs {
  font-size: 0.65rem;
}
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

.bg-error-tonal {
  background-color: rgba(var(--v-theme-error), 0.12);
}

.cursor-pointer {
  cursor: pointer;
}
</style>

