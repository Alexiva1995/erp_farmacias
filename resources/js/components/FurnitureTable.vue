<script setup>
import { useDisplay } from "vuetify";

const props = defineProps({
  furniture: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalFurniture: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-furniture",
  "delete-furniture",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Nombre", key: "name", sortable: true, width: "35%" },
  { title: "Adquisición", key: "acquisition_year", sortable: true },
  { title: "Costo Original", key: "cost", sortable: true },
  { title: "Depreciación", key: "annual_depreciation_rate", sortable: true },
  { title: "Valor Actual", key: "current_value", sortable: true },
  { title: "Estado", key: "status", sortable: false },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const formatPrice = (price) => {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(price);
};

const calculateCurrentValue = (item) => {
  const currentYear = new Date().getFullYear();
  const yearsDepreciated = Math.max(0, currentYear - item.acquisition_year);
  const totalDepreciation =
    (item.annual_depreciation_rate / 100) * yearsDepreciated;
  const depreciationFactor = Math.max(0, 1 - totalDepreciation);

  return item.cost * depreciationFactor;
};

const getDepreciationStatus = (item) => {
  const currentValue = calculateCurrentValue(item);
  const depreciationPercentage = ((item.cost - currentValue) / item.cost) * 100;

  if (depreciationPercentage <= 20) {
    return { text: "Excelente", color: "success", icon: "tabler-circle-check" };
  } else if (depreciationPercentage <= 50) {
    return { text: "Bueno", color: "info", icon: "tabler-info-circle" };
  } else if (depreciationPercentage <= 80) {
    return { text: "Regular", color: "warning", icon: "tabler-alert-triangle" };
  } else {
    return { text: "Depreciado", color: "error", icon: "tabler-alert-circle" };
  }
};

const getAgeStatus = (acquisitionYear) => {
  const currentYear = new Date().getFullYear();
  const age = currentYear - acquisitionYear;

  if (age <= 2) {
    return { text: "Nuevo", color: "success" };
  } else if (age <= 5) {
    return { text: "Reciente", color: "info" };
  } else if (age <= 10) {
    return { text: "Usado", color: "warning" };
  } else {
    return { text: "Antiguo", color: "error" };
  }
};

const formatDepreciation = (rate) => {
  return `${rate}% anual`;
};
</script>

<template>
  <div class="furniture-table-container">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.furniture"
      :items-length="props.totalFurniture"
      :loading="props.loading"
      class="premium-table rounded-lg border shadow-sm"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-black text-primary text-sm">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar color="primary" variant="tonal" rounded size="36" class="rounded-lg">
            <VIcon icon="tabler-sofa" size="18" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-bold text-high-emphasis leading-tight">
              {{ item.name }}
            </span>
          </div>
        </div>
      </template>

      <template #item.acquisition_year="{ item }">
        <div class="d-flex flex-column align-center">
          <span class="text-sm font-weight-medium">{{ item.acquisition_year }}</span>
          <VChip
            :color="getAgeStatus(item.acquisition_year).color"
            variant="tonal"
            size="x-small"
            class="mt-1 font-weight-bold"
          >
            {{ getAgeStatus(item.acquisition_year).text }}
          </VChip>
        </div>
      </template>

      <template #item.cost="{ item }">
        <span class="text-sm font-weight-semibold">{{ formatPrice(item.cost) }}</span>
      </template>

      <template #item.annual_depreciation_rate="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="item.annual_depreciation_rate > 15 ? 'tabler-trending-down' : 'tabler-trending-up'"
            :color="item.annual_depreciation_rate > 15 ? 'error' : 'success'"
            size="16"
          />
          <span class="text-sm font-weight-medium text-medium-emphasis">{{ formatDepreciation(item.annual_depreciation_rate) }}</span>
        </div>
      </template>

      <template #item.current_value="{ item }">
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-bold text-primary">
            {{ formatPrice(calculateCurrentValue(item)) }}
          </span>
          <span class="text-xs text-disabled font-weight-medium">
            {{ ((calculateCurrentValue(item) / item.cost) * 100).toFixed(1) }}% residual
          </span>
        </div>
      </template>

      <template #item.status="{ item }">
        <VChip
          :color="getDepreciationStatus(item).color"
          variant="tonal"
          size="small"
          class="font-weight-bold"
        >
          <template #prepend>
            <VIcon :icon="getDepreciationStatus(item).icon" size="14" class="mr-1" />
          </template>
          {{ getDepreciationStatus(item).text }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-center gap-2">
          <VTooltip text="Editar">
            <template #activator="{ props: tooltip }">
              <VBtn
                v-bind="tooltip"
                icon="tabler-edit"
                variant="tonal"
                color="warning"
                size="32"
                class="rounded-lg"
                @click="emit('edit-furniture', item)"
              />
            </template>
          </VTooltip>

          <VTooltip text="Eliminar">
            <template #activator="{ props: tooltip }">
              <VBtn
                v-bind="tooltip"
                icon="tabler-trash"
                variant="tonal"
                color="error"
                size="32"
                class="rounded-lg"
                @click="emit('delete-furniture', item.id)"
              />
            </template>
          </VTooltip>
        </div>
      </template>

      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-cards-container pa-4">
      <VRow v-if="props.loading">
        <VCol v-for="n in 5" :key="n" cols="12">
          <VSkeletonLoader type="card" class="rounded-lg" />
        </VCol>
      </VRow>
      <VRow v-else-if="props.furniture.length > 0">
        <VCol v-for="item in props.furniture" :key="item.id" cols="12">
          <VCard class="rounded-lg border shadow-sm furniture-card" variant="flat">
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex align-center gap-3">
                  <VAvatar color="primary" variant="tonal" rounded size="40" class="rounded-lg">
                    <VIcon icon="tabler-sofa" size="20" />
                  </VAvatar>
                  <div>
                    <div class="text-subtitle-1 font-weight-black text-high-emphasis leading-none">{{ item.name }}</div>
                    <div class="text-caption text-disabled font-weight-bold uppercase mt-1">
                      {{ item.id }} • Adquirido en {{ item.acquisition_year }}
                    </div>
                  </div>
                </div>
                <div class="d-flex gap-2">
                  <VBtn
                    icon="tabler-edit"
                    variant="tonal"
                    color="warning"
                    size="36"
                    class="rounded-lg"
                    @click="emit('edit-furniture', item)"
                  />
                  <VBtn
                    icon="tabler-trash"
                    variant="tonal"
                    color="error"
                    size="36"
                    class="rounded-lg"
                    @click="emit('delete-furniture', item.id)"
                  />
                </div>
              </div>

              <VDivider class="my-3 border-dashed" />

              <VRow no-gutters class="mb-4">
                <VCol cols="6">
                  <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Costo Original</div>
                  <div class="text-body-2 font-weight-bold">{{ formatPrice(item.cost) }}</div>
                </VCol>
                <VCol cols="6" class="text-right border-l-dashed pl-4">
                  <div class="text-caption text-disabled uppercase font-weight-bold mb-1">Valor Actual</div>
                  <div class="text-body-2 font-weight-black text-primary">{{ formatPrice(calculateCurrentValue(item)) }}</div>
                </VCol>
              </VRow>

              <div class="d-flex justify-space-between align-center px-4 py-2 bg-light-surface rounded-lg border">
                <div class="d-flex flex-column">
                  <span class="text-xs text-disabled uppercase font-weight-bold">Estado</span>
                  <div class="d-flex align-center gap-1">
                    <VIcon :icon="getDepreciationStatus(item).icon" :color="getDepreciationStatus(item).color" size="14" />
                    <span :class="`text-body-2 font-weight-black text-${getDepreciationStatus(item).color}`">
                      {{ getDepreciationStatus(item).text }}
                    </span>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-xs text-disabled uppercase font-weight-bold">Depreciación</span>
                  <div class="text-body-2 font-weight-bold">{{ item.annual_depreciation_rate }}% / año</div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- No data state -->
      <div v-else class="text-center pa-12">
        <VIcon icon="tabler-sofa-off" size="64" class="mb-4 text-disabled opacity-20" />
        <div class="text-h6 font-weight-bold mb-1">Sin resultados</div>
        <div class="text-body-2 text-disabled">No se encontró mobiliario con esos filtros</div>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-6">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalFurniture / props.itemsPerPage)"
          density="comfortable"
          variant="tonal"
          @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
}

.furniture-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.furniture-card:active {
  transform: scale(0.98);
}

.border-dashed {
  border-style: dashed !important;
  opacity: 0.15;
}

.border-l-dashed {
  border-inline-start: 1px dashed rgba(var(--v-border-color), 0.15);
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 2%);
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.opacity-20 {
  opacity: 0.2;
}
</style>
