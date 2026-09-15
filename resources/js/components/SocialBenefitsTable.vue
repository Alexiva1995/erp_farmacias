<script setup>
import { useDisplay } from "vuetify";
import AppEmptyState from "@/components/AppEmptyState.vue";
import AppMobilePagination from "@/components/AppMobilePagination.vue";

const props = defineProps({
  employees: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "fire-employee",
  "download-settlement",
  "upload-signed",
  "download-signed",
]);

const { mobile } = useDisplay();

const toTitleCase = (str) => {
  if (!str) return "—";
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

const formatIdentification = (val) => {
  if (!val) return "—";
  const cleaned = String(val).replace(/\D/g, "");
  if (!cleaned) return String(val);
  const withDots = cleaned.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `V-${withDots}`;
};

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("es-VE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};

const getStatusColor = (settlementDate) => (settlementDate ? "success" : "warning");
const getStatusText = (settlementDate) => (settlementDate ? "Liquidado" : "Pendiente");

const headers = [
  { title: "ID", key: "id", sortable: true, width: "70px" },
  { title: "Empleado", key: "full_name", sortable: false },
  { title: "Identificación", key: "identification", sortable: false, align: "center" },
  { title: "Correo Electrónico", key: "email", sortable: false },
  { title: "Estado Liquidación", key: "settlement_date", sortable: false, align: "center" },
  { title: "Acciones", key: "actions", sortable: false, align: "end" },
];
</script>

<template>
  <div class="social-benefits-table-container">
    <!-- Vista Desktop -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers"
          :items-per-page="props.itemsPerPage"
          :items="props.employees"
          :items-length="props.total"
          :loading="props.loading"
          :page="props.page"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #no-data>
            <AppEmptyState
              title="No hay empleados"
              message="No se encontraron registros de liquidaciones o prestaciones sociales."
              icon="tabler-calculator-off"
            />
          </template>

          <template #item.id="{ item }">
            <span class="font-weight-bold text-primary">{{ item.id }}</span>
          </template>

          <template #item.full_name="{ item }">
            <div class="d-flex align-center gap-3 py-1">
              <VAvatar size="34" :color="item.settlement_date ? 'secondary' : 'primary'" variant="tonal" class="rounded-lg">
                <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                <span v-else class="text-xs font-weight-bold">{{ item.name?.charAt(0) }}{{ item.last_name?.charAt(0) }}</span>
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-medium text-high-emphasis leading-tight">
                  {{ toTitleCase(item.name + ' ' + item.last_name) }}
                </span>
                <span class="text-super-xs text-medium-emphasis font-weight-medium">
                  {{ item.position?.name || 'Cargo no especificado' }}
                </span>
              </div>
            </div>
          </template>

          <template #item.identification="{ item }">
            <span class="font-weight-semibold text-high-emphasis">{{ formatIdentification(item.identification) }}</span>
          </template>

          <template #item.email="{ item }">
            <span class="text-sm text-medium-emphasis">{{ item.email || '—' }}</span>
          </template>

          <template #item.settlement_date="{ item }">
            <div class="d-flex flex-column align-center">
              <VChip
                :color="getStatusColor(item.settlement_date)"
                size="x-small"
                variant="tonal"
                class="font-weight-bold"
              >
                {{ getStatusText(item.settlement_date) }}
              </VChip>
              <span v-if="item.settlement_date" class="text-super-xs text-medium-emphasis font-weight-medium mt-0.5">
                {{ formatDate(item.settlement_date) }}
              </span>
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <template v-if="!item.settlement_date">
                <IconBtn
                  color="primary"
                  size="small"
                  @click="emit('fire-employee', item)"
                >
                  <VIcon icon="tabler-file-analytics" size="18" />
                  <VTooltip activator="parent">Procesar Liquidación</VTooltip>
                </IconBtn>
              </template>

              <template v-else>
                <IconBtn
                  color="primary"
                  size="small"
                  :disabled="props.loading"
                  @click="emit('download-settlement', item)"
                >
                  <VIcon icon="tabler-file-type-pdf" size="18" />
                  <VTooltip activator="parent">Descargar Liquidación PDF</VTooltip>
                </IconBtn>

                <IconBtn
                  color="warning"
                  size="small"
                  :disabled="props.loading"
                  @click="emit('upload-signed', item)"
                >
                  <VIcon icon="tabler-cloud-upload" size="18" />
                  <VTooltip activator="parent">Subir Documento Firmado</VTooltip>
                </IconBtn>

                <IconBtn
                  v-if="item.signed_document_path"
                  color="success"
                  size="small"
                  :disabled="props.loading"
                  @click="emit('download-signed', item)"
                >
                  <VIcon icon="tabler-download" size="18" />
                  <VTooltip activator="parent">Descargar Documento Firmado</VTooltip>
                </IconBtn>
              </template>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

      <AppEmptyState
        v-if="props.employees.length === 0 && !props.loading"
        title="No hay empleados"
        message="No se encontraron registros de liquidaciones o prestaciones sociales."
        icon="tabler-calculator-off"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.employees"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
                <VAvatar size="42" :color="item.settlement_date ? 'secondary' : 'primary'" variant="tonal" class="rounded-lg">
                  <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                  <span v-else class="text-sm font-weight-bold">{{ item.name?.charAt(0) }}{{ item.last_name?.charAt(0) }}</span>
                </VAvatar>
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-black text-xs uppercase mb-0.5">ID #{{ item.id }}</span>
                  <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                    {{ toTitleCase(item.name + ' ' + item.last_name) }}
                  </h3>
                  <div class="d-flex align-center gap-1 mt-0.5">
                    <span class="text-super-xs text-medium-emphasis font-weight-bold">{{ formatIdentification(item.identification) }}</span>
                    <span class="text-xs text-disabled">•</span>
                    <span class="text-super-xs text-primary font-weight-bold truncate">{{ item.position?.name || 'Cargo no especificado' }}</span>
                  </div>
                </div>
              </div>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <VChip :color="getStatusColor(item.settlement_date)" size="x-small" variant="tonal" class="font-weight-bold">
                  {{ getStatusText(item.settlement_date) }}
                </VChip>
                <span v-if="item.settlement_date" class="text-super-xs text-medium-emphasis font-weight-medium">
                  {{ formatDate(item.settlement_date) }}
                </span>
              </div>

              <div class="d-flex gap-1">
                <template v-if="!item.settlement_date">
                  <IconBtn
                    color="primary"
                    size="x-small"
                    @click="emit('fire-employee', item)"
                  >
                    <VIcon icon="tabler-file-analytics" size="16" />
                    <VTooltip activator="parent">Procesar Liquidación</VTooltip>
                  </IconBtn>
                </template>

                <template v-else>
                  <IconBtn
                    color="primary"
                    size="x-small"
                    :disabled="props.loading"
                    @click="emit('download-settlement', item)"
                  >
                    <VIcon icon="tabler-file-type-pdf" size="16" />
                    <VTooltip activator="parent">Descargar Liquidación PDF</VTooltip>
                  </IconBtn>

                  <IconBtn
                    color="warning"
                    size="x-small"
                    :disabled="props.loading"
                    @click="emit('upload-signed', item)"
                  >
                    <VIcon icon="tabler-cloud-upload" size="16" />
                    <VTooltip activator="parent">Subir Documento Firmado</VTooltip>
                  </IconBtn>

                  <IconBtn
                    v-if="item.signed_document_path"
                    color="success"
                    size="x-small"
                    :disabled="props.loading"
                    @click="emit('download-signed', item)"
                  >
                    <VIcon icon="tabler-download" size="16" />
                    <VTooltip activator="parent">Descargar Documento Firmado</VTooltip>
                  </IconBtn>
                </template>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.total"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
