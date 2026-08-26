<script setup>
import { computed, ref } from "vue";
import { useDisplay } from "vuetify";
import { formatCurrency as globalFormatCurrency } from "@/utils/currencyFormatter";
import axios from "@/plugins/axios";
import AppEmptyState from "@/components/AppEmptyState.vue";
import InvoicePhotoPreviewDialog from "@/components/InvoicePhotoPreviewDialog.vue";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  invoices: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalInvoices: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isAdmin: { type: Boolean, default: false },
  actionsMode: {
    type: String,
    default: "default",
  },
  highlightedId: {
    type: [Number, String],
    default: null,
  },
});

const { mobile } = useDisplay();

const emit = defineEmits([
  "update:options",
  "edit-invoice",
  "edit-invoice-form",
  "delete-invoice",
  "approve-invoice",
  "reject-invoice",
  "locate-products",
  "view-details",
  "return-invoice",
  "photo-updated",
]);

const fileInput = ref(null);
const isUploading = ref(false);
const isPreviewDialogVisible = ref(false);
const previewImageUrl = ref("");
const activeInvoiceId = ref(null);

const triggerFileUpload = (invoiceId) => {
  activeInvoiceId.value = invoiceId;
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const onFileSelected = async (event) => {
  const file = event.target.files?.[0];
  if (!file) return;

  isUploading.value = true;
  const formData = new FormData();
  formData.append("file", file);

  try {
    await axios.post(`/invoices/${activeInvoiceId.value}/photo`, formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    
    toast.success("Foto cargada con éxito");
    emit("photo-updated");
  } catch (error) {
    console.error("Error al subir foto:", error);
    toast.error(error.response?.data?.message || "Error al subir la foto");
  } finally {
    isUploading.value = false;
    if (event.target) {
      event.target.value = "";
    }
  }
};

const viewPhoto = (photoPath) => {
  previewImageUrl.value = `/storage/${photoPath}`;
  isPreviewDialogVisible.value = true;
};

const headers = computed(() => {
  const baseHeaders = [
    { title: "ID", key: "id", sortable: true },
    { title: "Proveedor", key: "supplier.name", sortable: true },
    { title: "N° Factura", key: "invoice_number", sortable: true },
    { title: "N° Control", key: "control_number", sortable: true },
    { title: "Emisión", key: "created_invoice_date", sortable: true },
    { title: "Vencimiento", key: "exp_date", sortable: true },
  ];

  if (props.actionsMode === "ordered") {
    baseHeaders.push({ title: "Operador", key: "ordered_by_user.name", sortable: false });
  }

  baseHeaders.push(
    { title: "Total", key: "total_amount", sortable: true },
    { title: "Acciones", key: "actions", sortable: false, align: "center" }
  );

  return baseHeaders;
});

const formatCurrency = (value, currency) => {
  return globalFormatCurrency(Number(value), currency);
};

const formatDate = (dateString) => {
  if (!dateString) return "";
  return new Date(dateString).toLocaleDateString("es-VE", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  });
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
    <!-- Vista Desktop: Tabla -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.invoices"
      :items-length="props.totalInvoices || 0"
      :loading="props.loading"
      hover
      density="compact"
      class="text-no-wrap premium-table"
      :row-props="
        (data) => ({
          class:
            data.item.id === props.highlightedId ? 'highlighted-row' : '',
        })
      "
      @update:options="(options) => emit('update:options', options)"
    >
      <template #no-data>
        <AppEmptyState
          title="No hay facturas"
          message="No se encontraron facturas en el sistema para los filtros aplicados."
          icon="tabler-file-off"
        />
      </template>
      <template #item.id="{ item }">
        <span class="text-sm font-weight-black text-primary">{{ item.id }}</span>
      </template>
      <template #item.supplier\.name="{ item }">
        <span class="font-weight-medium">{{ item.supplier?.name || 'N/A' }}</span>
      </template>
      <template #item.ordered_by_user\.name="{ item }">
        <span v-if="item.ordered_by_user" class="text-sm font-weight-medium">
          {{ item.ordered_by_user.name }}
        </span>
        <span v-else class="text-caption text-medium-emphasis">No asignado</span>
      </template>
      <template #item.total_amount="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            formatCurrency(item.total_amount, item.currency)
          }}</span>
          <span
            v-if="item.currency !== 'USD'"
            class="text-caption text-medium-emphasis"
            >{{ formatCurrency(item.total_usd, "USD") }}</span
          >
        </div>
      </template>
      <template #item.created_invoice_date="{ item }">
        {{ formatDate(item.created_invoice_date) }}
      </template>
      <template #item.exp_date="{ item }">
        <div class="d-flex align-center ga-1">
          <span>{{ formatDate(item.exp_date) }}</span>
          <VChip
            v-if="item.is_indexed"
            color="warning"
            size="x-small"
            variant="flat"
            class="font-weight-bold"
          >
            FA$ Indexada
          </VChip>
        </div>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex ga-2">
          <!-- Botón Devolver: Visible en todas las vistas EXCEPTO en 'Por Ordenar' (location) -->
          <div v-if="props.isAdmin && props.actionsMode !== 'location'">
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              @click="emit('return-invoice', item.id)"
            >
              <VIcon icon="tabler-arrow-back-up" class="me-2" />
              Devolver
            </VBtn>
          </div>

          <!-- Botón Ver/Descargar PDF o Foto (Disponible siempre si existe) -->
          <VTooltip v-if="item.invoice_photo" text="Ver Factura PDF / Foto">
            <template #activator="{ props: tooltipProps }">
              <IconBtn
                v-bind="tooltipProps"
                color="error"
                @click="viewPhoto(item.invoice_photo)"
              >
                <VIcon icon="tabler-file-type-pdf" />
              </IconBtn>
            </template>
          </VTooltip>

          <div v-if="props.actionsMode === 'approval'">
            <VTooltip text="Revisar y Aprobar">
              <template #activator="{ props: tooltipProps }">
                <VBtn
                  v-bind="tooltipProps"
                  color="primary"
                  variant="tonal"
                  size="small"
                  @click="emit('edit-invoice', item)"
                >
                  <VIcon icon="tabler-eye" class="me-2" />
                  Revisar
                </VBtn>
              </template>
            </VTooltip>
          </div>

          <div v-else-if="props.actionsMode === 'location'">
            <VBtn
              color="primary"
              variant="tonal"
              size="small"
              @click="emit('locate-products', item)"
              ><VIcon icon="tabler-map-pin" class="me-2" />Ubicar
              Productos</VBtn
            >
          </div>

          <div v-else-if="props.actionsMode === 'ordered'" class="d-flex ga-2">
            <VTooltip :text="item.invoice_photo ? 'Ver Factura' : 'Subir Foto Factura'">
              <template #activator="{ props: tooltip }">
                <IconBtn 
                  v-bind="tooltip" 
                  @click="item.invoice_photo ? viewPhoto(item.invoice_photo) : triggerFileUpload(item.id)" 
                  :color="item.invoice_photo ? 'success' : 'secondary'"
                  :loading="isUploading && activeInvoiceId === item.id"
                >
                  <VIcon :icon="item.invoice_photo ? 'tabler-photo' : 'tabler-camera'" />
                </IconBtn>
              </template>
            </VTooltip>
            
            <VTooltip text="Ver Detalles">
              <template #activator="{ props: tooltip }">
                <IconBtn v-bind="tooltip" @click="emit('view-details', item)" color="info">
                  <VIcon icon="tabler-eye" />
                </IconBtn>
              </template>
            </VTooltip>
          </div>

          <div v-else class="d-flex ga-1">
            <VTooltip text="Editar Factura">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="warning"
                  @click="emit('edit-invoice-form', item)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>
              </template>
            </VTooltip>
            <VTooltip text="Ver Productos">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  @click="emit('edit-invoice', item)"
                >
                  <VIcon icon="tabler-package" />
                </IconBtn>
              </template>
            </VTooltip>
            <VTooltip text="Eliminar">
              <template #activator="{ props: tooltipProps }">
                <IconBtn
                  v-bind="tooltipProps"
                  color="error"
                  @click="emit('delete-invoice', item.id)"
                >
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </template>
            </VTooltip>
          </div>
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Tarjetas -->
    <div v-else class="pa-4 bg-surface">
      <div v-if="loading" class="d-flex flex-column ga-4">
        <VSkeletonLoader
          v-for="i in 3"
          :key="i"
          type="article, actions"
          class="border rounded-lg pa-3 bg-surface w-100"
          style="height: 180px;"
        />
      </div>
      <div v-else-if="props.invoices.length === 0" class="text-center py-8 text-disabled text-sm">
        No se encontraron facturas
      </div>
      <div v-else class="d-flex flex-column ga-4">
        <VCard
          v-for="item in props.invoices"
          :key="item.id"
          class="mobile-invoice-card border shadow-none"
          :class="{ 'highlighted-card': item.id === props.highlightedId }"
        >
          <VCardText class="pa-3">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex flex-column">
                <span class="text-xs font-weight-bold text-primary mb-1">ID #{{ item.id }}</span>
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase">{{ item.supplier?.name || 'N/A' }}</span>
              </div>
              <div class="text-right">
                <div class="text-xs text-medium-emphasis text-uppercase font-weight-black">Total</div>
                <div class="text-sm font-weight-black text-high-emphasis">
                  {{ formatCurrency(item.total_amount, item.currency) }}
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-dashed" />

            <div class="grid-info mb-3">
              <div class="info-item">
                <span class="label text-medium-emphasis">Factura</span>
                <span class="value text-high-emphasis">{{ item.invoice_number }}</span>
              </div>
              <div class="info-item">
                <span class="label text-medium-emphasis">Emisión</span>
                <span class="value text-primary font-weight-bold">{{ formatDate(item.created_invoice_date) }}</span>
              </div>
              <div class="info-item">
                <span class="label text-medium-emphasis">Control</span>
                <span class="value text-high-emphasis">{{ item.control_number }}</span>
              </div>
              <div class="info-item">
                <span class="label text-medium-emphasis">Vence</span>
                <span class="value text-error font-weight-bold">{{ formatDate(item.exp_date) }}</span>
              </div>
            </div>

            <VDivider class="mb-3" />

            <div class="d-flex flex-wrap ga-2 justify-center">
              <template v-if="props.actionsMode === 'approval'">
                <VBtn color="primary" variant="tonal" size="small" block @click="emit('edit-invoice', item)">
                  <VIcon icon="tabler-eye" />
                </VBtn>
              </template>

              <template v-else-if="props.actionsMode === 'location'">
                <VBtn color="primary" variant="tonal" size="small" block @click="emit('locate-products', item)">
                  <VIcon icon="tabler-map-pin" />
                </VBtn>
              </template>

              <template v-else-if="props.actionsMode === 'ordered'">
                <div class="d-flex ga-2 w-100">
                  <VBtn 
                    :color="item.invoice_photo ? 'success' : 'secondary'" 
                    variant="tonal" 
                    size="small" 
                    class="flex-grow-1 rounded-lg"
                    @click="item.invoice_photo ? viewPhoto(item.invoice_photo) : triggerFileUpload(item.id)"
                    :loading="isUploading && activeInvoiceId === item.id"
                  >
                    <VIcon :icon="item.invoice_photo ? 'tabler-photo' : 'tabler-camera'" />
                  </VBtn>
                  <VBtn color="info" variant="tonal" size="small" class="flex-grow-1 rounded-lg" @click="emit('view-details', item)">
                    <VIcon icon="tabler-eye" />
                  </VBtn>
                </div>
              </template>

              <template v-else>
                <div class="d-flex ga-2 w-100 justify-center mb-2">
                  <VBtn v-if="item.invoice_photo" color="error" variant="tonal" size="small" class="flex-grow-1" @click="viewPhoto(item.invoice_photo)">
                    <VIcon icon="tabler-file-type-pdf" />
                  </VBtn>
                  <VBtn color="warning" variant="tonal" size="small" class="flex-grow-1" @click="emit('edit-invoice-form', item)">
                    <VIcon icon="tabler-edit" />
                  </VBtn>
                  <VBtn color="primary" variant="tonal" size="small" class="flex-grow-1" @click="emit('edit-invoice', item)">
                    <VIcon icon="tabler-package" />
                  </VBtn>
                   <VBtn v-if="props.isAdmin" color="primary" variant="tonal" size="small" class="flex-grow-1" @click="emit('return-invoice', item.id)">
                    <VIcon icon="tabler-arrow-back-up" />
                  </VBtn>
                  <VBtn color="error" variant="tonal" size="small" class="flex-grow-1" @click="emit('delete-invoice', item.id)">
                    <VIcon icon="tabler-trash" />
                  </VBtn>
                </div>
              </template>
            </div>
          </VCardText>
        </VCard>

        <!-- Paginación Móvil (sin mutación directa de props) -->
        <div class="mt-2 d-flex justify-center">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil((props.totalInvoices || 0) / props.itemsPerPage)"
            :total-visible="3"
            density="compact"
            @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
          />
        </div>
      </div>
    </div>
  </VCard>

  <!-- Input de Archivo Oculto -->
  <input
    ref="fileInput"
    type="file"
    accept="image/*"
    style="display: none"
    @change="onFileSelected"
  />

  <!-- Diálogo de Previsualización de Factura Desacoplado -->
  <InvoicePhotoPreviewDialog
    v-model="isPreviewDialogVisible"
    :preview-image-url="previewImageUrl"
  />
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: rgb(var(--v-theme-surface)) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.highlighted-row {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
  transition: background-color 0.3s ease;
}

.highlighted-row:hover {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
}

.mobile-invoice-card {
  transition: transform 0.2s ease;
  border-radius: 8px !important;
}

.highlighted-card {
  border: 1px solid rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
  border-radius: 8px !important;
}

.grid-info {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.info-item .label {
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.info-item .value {
  font-size: 0.85rem;
  font-weight: 500;
}

.text-xs { font-size: 0.75rem !important; }
.ga-4 { gap: 16px !important; }
</style>
