<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  doctorsOffer: { type: Array, required: true },
  loading:      { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page:         { type: Number, required: true },
  totaldoctors: { type: Number, required: true },
  title:        { type: String, default: "" },
});

const emit = defineEmits(["update:options", "edit", "view", "delete"]);

const headers = [
  {
    title: "ID",
    key: "id",
    sortable: true,
    align: "center",
    width: "70px",
    cellClass: "font-weight-black text-primary d-none d-sm-table-cell",
    headerClass: "d-none d-sm-table-cell",
  },
  { title: "Médico",    key: "doctor_name", sortable: true, width: "35%" },
  { title: "% Desc.",   key: "discount",    sortable: true, align: "center", width: "120px" },
  { title: "Vigencia",  key: "validity",    sortable: false, align: "center", width: "180px" },
  { title: "Estado",    key: "is_active",   sortable: true, align: "center", width: "100px" },
  { title: "Acciones",  key: "actions",     sortable: false, align: "center", width: "110px" },
];

const getStatusColor = (isActive) => (isActive ? "success" : "error");
const getStatusText = (isActive) => (isActive ? "ACTIVA" : "INACTIVA");

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
};

const handleView = (doctorOffer) => emit("view", doctorOffer);
const handleEdit = (doctorOffer) => emit("edit", doctorOffer);
const handleDelete = (doctorOffer) => emit("delete", doctorOffer);
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden">
    <VCardTitle v-if="props.title" class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">{{ props.title }}</span>
      <VSpacer />
    </VCardTitle>

    <VDivider />

    <!-- Desktop View -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.doctorsOffer"
        :items-length="props.totaldoctors"
        :loading="props.loading"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="No se encontraron ofertas"
            message="No hay ofertas de médicos disponibles con los filtros actuales."
            icon="tabler-stethoscope-off"
          />
        </template>

        <!-- ID Column -->
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <!-- Doctor Name Column -->
        <template #item.doctor_name="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" style="max-inline-size: 380px;">
              {{ item.doctor?.name || "N/A" }}
            </span>
            <span class="text-super-xs font-weight-bold text-medium-emphasis text-uppercase mt-0-5">
              ID MÉDICO: {{ item.doctor_id }}
            </span>
          </div>
        </template>

        <!-- Discount Column -->
        <template #item.discount="{ item }">
          <span class="font-weight-black text-success text-sm">
            {{ item.discount }}%
          </span>
        </template>

        <!-- Validity Column -->
        <template #item.validity="{ item }">
          <div class="d-flex flex-column align-center text-super-xs font-weight-bold text-medium-emphasis">
            <span>{{ formatDate(item.start_date) }}</span>
            <span class="text-disabled">al {{ formatDate(item.end_date) }}</span>
          </div>
        </template>

        <!-- Active Status Column -->
        <template #item.is_active="{ item }">
          <VChip
            :color="getStatusColor(item.is_active)"
            size="x-small"
            variant="tonal"
            class="font-weight-black px-2 rounded"
          >
            {{ getStatusText(item.is_active) }}
          </VChip>
        </template>

        <!-- Actions Column -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-1">
            <IconBtn
              @click="handleView(item)"
              color="info"
              size="small"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent">Ver Detalle</VTooltip>
            </IconBtn>
            <IconBtn
              @click="handleEdit(item)"
              color="warning"
              size="small"
            >
              <VIcon icon="tabler-edit" size="18" />
              <VTooltip activator="parent">Editar Oferta</VTooltip>
            </IconBtn>
            <IconBtn
              @click="handleDelete(item)"
              color="error"
              size="small"
            >
              <VIcon icon="tabler-trash" size="18" />
              <VTooltip activator="parent">Eliminar Oferta</VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Mobile View -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />

      <div v-if="props.doctorsOffer.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No hay ofertas de médicos disponibles.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.doctorsOffer"
          :key="item.id"
          variant="flat"
          class="product-mobile-card border mb-1"
        >
          <div class="pa-2 pa-sm-3">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="d-flex align-center gap-1">
                <span class="text-primary font-weight-black text-super-xs bg-primary-lighten-5 px-1-5 py-0-5 rounded flex-shrink-0">
                  ID: {{ item.id }}
                </span>
                <span class="text-super-xs font-weight-bold text-disabled uppercase">
                  ID DOC: {{ item.doctor_id }}
                </span>
              </div>
              <VChip
                :color="getStatusColor(item.is_active)"
                size="x-small"
                variant="flat"
                class="font-weight-black px-2 rounded"
              >
                {{ getStatusText(item.is_active) }}
              </VChip>
            </div>

            <h3 class="product-mobile-title font-weight-black text-high-emphasis text-uppercase truncate-2-lines mb-2 text-body-2">
              {{ item.doctor?.name || "MÉDICO NO ASIGNADO" }}
            </h3>

            <!-- Caja compacta de Descuento y Vigencia -->
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1.5 rounded border-dashed-thin">
              <div class="d-flex flex-column">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Descuento:</span>
                <span class="text-xs font-weight-black text-success">
                  {{ item.discount }}% OFF
                </span>
              </div>

              <div class="d-flex flex-column text-end">
                <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Vigencia:</span>
                <span class="text-super-xs font-weight-bold text-medium-emphasis">
                  {{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares en Móvil -->
          <div class="d-flex align-center border-t border-opacity-10 mobile-actions-bar">
            <VBtn
              color="info"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="handleView(item)"
            >
              <VIcon icon="tabler-eye" size="18" />
            </VBtn>
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="warning"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="handleEdit(item)"
            >
              <VIcon icon="tabler-edit" size="18" />
            </VBtn>
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="error"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="handleDelete(item)"
            >
              <VIcon icon="tabler-trash" size="18" />
            </VBtn>
          </div>
        </VCard>
      </div>

      <div class="mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totaldoctors"
          :loading="props.loading"
          @change="(options) => emit('update:options', options)"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.truncate-2-lines {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-data-table th) {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
}
</style>
