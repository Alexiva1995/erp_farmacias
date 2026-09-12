<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import AppEmptyState from "@/components/AppEmptyState.vue";

const props = defineProps({
  categoriesOffer: { type: Array, required: true },
  loading:         { type: Boolean, default: false },
  totalOffer:      { type: Number, default: 0 },
  discount:        { type: Number, default: 0 },
  itemsPerPage:    { type: Number, required: true },
  page:            { type: Number, required: true },
  title:           { type: String, default: "" },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

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
  { title: "Categoría",   key: "category.name",       sortable: true, width: "35%" },
  { title: "% Desc.",     key: "discount_percentage", sortable: true, align: "end", width: "100px" },
  { title: "Vigencia",    key: "validity",            sortable: false, align: "center", width: "180px" },
  { title: "Estado",      key: "is_active",           sortable: true, align: "center", width: "110px" },
  { title: "Acciones",    key: "actions",             sortable: false, align: "center", width: "110px" },
];

const formatDate = (dateString) => {
  if (!dateString) return "—";
  return new Date(dateString).toLocaleDateString("es-ES", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  });
};

const isExpired = (endDateStr) => {
  if (!endDateStr) return false;
  const end = new Date(endDateStr);
  const now = new Date();
  return end < now;
};

const isExpiringSoon = (endDateStr) => {
  if (!endDateStr) return false;
  const end = new Date(endDateStr);
  const now = new Date();
  const diffHours = (end - now) / (1000 * 60 * 60);
  return diffHours >= 0 && diffHours <= 24;
};
</script>

<template>
  <VCard class="rounded-lg border shadow-sm overflow-hidden">
    <VCardTitle v-if="props.title" class="d-flex align-center pa-4">
      <span class="text-h6 font-weight-bold">{{ props.title }}</span>
      <VSpacer />
    </VCardTitle>

    <VDivider />

    <!-- Vista de Escritorio (Tabla) -->
    <div class="d-none d-md-block">
      <VDataTableServer
        :items-per-page="props.itemsPerPage"
        :page="props.page"
        :headers="headers"
        :items="props.categoriesOffer"
        :items-length="props.totalOffer"
        :loading="props.loading"
        items-per-page-text="Filas por página:"
        page-text="{0}-{1} de {2}"
        loading-text="Cargando..."
        no-data-text="No hay datos disponibles"
        class="text-no-wrap"
        density="compact"
        @update:options="(options) => emit('update:options', options)"
      >
        <template #no-data>
          <AppEmptyState
            title="No se encontraron ofertas"
            message="No hay ofertas por categoría disponibles con los filtros actuales."
            icon="tabler-folder-off"
          />
        </template>

        <!-- ID Column (Centrado preciso) -->
        <template #item.id="{ item }">
          <span class="font-weight-black text-primary">{{ item.id }}</span>
        </template>

        <!-- Category Column -->
        <template #item.category.name="{ item }">
          <div class="d-flex align-center gap-x-2 py-2">
            <div class="d-flex flex-column min-width-0 flex-grow-1">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate"
                style="max-inline-size: 420px;"
                :title="item.category?.name"
              >
                {{ item.category?.name || "Sin Categoría" }}
              </span>
              <div class="d-flex align-center flex-wrap gap-1 text-super-xs mt-0-5">
                <span class="text-disabled font-weight-normal">
                  ID Categoría: {{ item.category?.id || '—' }}
                </span>
              </div>
            </div>
          </div>
        </template>

        <!-- Discount Percentage -->
        <template #item.discount_percentage="{ item }">
          <span class="text-sm font-weight-bold text-success pe-1">
            {{ parseFloat(item.discount_percentage || 0) }}%
          </span>
        </template>

        <!-- Validity Column (Neutro en una línea con alerta semántica) -->
        <template #item.validity="{ item }">
          <div class="d-flex flex-column align-center">
            <span class="text-xs font-weight-medium text-medium-emphasis">
              {{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}
            </span>
            <span v-if="isExpired(item.end_date)" class="text-super-xs font-weight-bold text-error uppercase mt-0-5">
              Vencida
            </span>
            <span v-else-if="isExpiringSoon(item.end_date)" class="text-super-xs font-weight-bold text-warning uppercase mt-0-5">
              Vence pronto
            </span>
          </div>
        </template>

        <!-- Status Column (Soft Badge con Status Dot y WCAG AA) -->
        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'secondary'"
            size="small"
            variant="tonal"
            class="font-weight-bold px-2 rounded-pill"
          >
            <span class="status-dot me-1" :class="item.is_active ? 'bg-success' : 'bg-secondary'"></span>
            {{ item.is_active ? 'Activa' : 'Inactiva' }}
          </VChip>
        </template>

        <!-- Actions Column (Touch targets amplios y espaciado seguro) -->
        <template #item.actions="{ item }">
          <div class="d-flex justify-center gap-2">
            <IconBtn
              @click="emit('edit-offer', item)"
              color="warning"
              size="small"
              class="action-btn"
            >
              <VIcon icon="tabler-edit" size="19" />
              <VTooltip activator="parent">Editar Oferta</VTooltip>
            </IconBtn>
            <IconBtn
              @click="emit('delete-offer', item.id)"
              color="error"
              size="small"
              class="action-btn"
            >
              <VIcon icon="tabler-trash" size="19" />
              <VTooltip activator="parent">Eliminar Oferta</VTooltip>
            </IconBtn>
          </div>
        </template>
      </VDataTableServer>
    </div>

    <!-- Vista de Móvil -->
    <div class="d-block d-md-none pa-2">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <div v-if="props.categoriesOffer.length === 0 && !props.loading" class="text-center py-8 text-disabled">
        No se encontraron ofertas por categoría.
      </div>

      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.categoriesOffer"
          :key="item.id"
          variant="flat"
          class="product-mobile-card border mb-1"
        >
          <div class="pa-2 pa-sm-3">
            <div class="d-flex gap-2 align-start">
              <div class="flex-grow-1 min-width-0">
                <div class="d-flex align-center gap-1 mb-1">
                  <span class="text-primary font-weight-black text-super-xs bg-primary-lighten-5 px-1-5 py-0-5 rounded flex-shrink-0">
                    ID: {{ item.id }}
                  </span>
                  <VSpacer />
                  <span class="text-xs font-weight-bold text-success flex-shrink-0">
                    {{ parseFloat(item.discount_percentage || 0) }}% OFF
                  </span>
                </div>

                <h3 class="product-mobile-title font-weight-black text-high-emphasis truncate-2-lines mb-1 text-body-2">
                  {{ item.category?.name || "Sin Categoría" }}
                </h3>

                <div class="d-flex align-center flex-wrap gap-x-1 text-super-xs">
                  <span class="text-medium-emphasis">
                    ID Categoría: {{ item.category?.id || '—' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Caja compacta de Estado y Fechas -->
            <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1 mt-2 rounded border-dashed-thin">
              <div class="d-flex align-center gap-1">
                <span class="status-dot" :class="item.is_active ? 'bg-success' : 'bg-secondary'"></span>
                <span :class="item.is_active ? 'text-success' : 'text-medium-emphasis'" class="text-xs font-weight-bold">
                  {{ item.is_active ? 'Activa' : 'Inactiva' }}
                </span>
              </div>
              <div class="d-flex align-center gap-1 text-super-xs font-weight-medium text-medium-emphasis">
                <span>{{ formatDate(item.start_date) }} – {{ formatDate(item.end_date) }}</span>
                <span v-if="isExpired(item.end_date)" class="text-error font-weight-bold uppercase ms-1">Vencida</span>
                <span v-else-if="isExpiringSoon(item.end_date)" class="text-warning font-weight-bold uppercase ms-1">Vence pronto</span>
              </div>
            </div>
          </div>

          <!-- Acciones Rectangulares en Móvil -->
          <div class="d-flex align-center border-t border-opacity-10 mobile-actions-bar">
            <VBtn
              color="warning"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="emit('edit-offer', item)"
            >
              <VIcon icon="tabler-edit" size="18" />
            </VBtn>
            <VDivider vertical class="border-opacity-10" />
            <VBtn
              color="error"
              variant="text"
              class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center"
              height="38"
              @click="emit('delete-offer', item.id)"
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
          :total-items="props.totalOffer"
          :loading="props.loading"
          @change="(options) => emit('update:options', options)"
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.status-dot {
  inline-size: 6px;
  block-size: 6px;
  border-radius: 50%;
  display: inline-block;
}

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
