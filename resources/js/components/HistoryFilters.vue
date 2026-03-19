<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  startDate: [String, null],
  endDate: [String, null],
  origins: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "add-product",
  "sort",
]);

const isFilterVisible = ref(false);

const sortOptions = [
  {
    title: "Precio mayor",
    icon: "tabler-arrow-up",
    key: "total_amount",
    order: "desc",
  },
  {
    title: "Precio Menor",
    icon: "tabler-arrow-down",
    key: "total_amount",
    order: "asc",
  },
  {
    title: "Fecha Reciente",
    icon: "tabler-calendar-up",
    key: "invoice_date",
    order: "desc",
  },
  {
    title: "Fecha Antigua",
    icon: "tabler-calendar-down",
    key: "invoice_date",
    order: "asc",
  },
];

const selectedSort = ref(null);

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  emit("sort", { key: undefined, order: undefined });
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order,
  );
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const activeFiltersCount = computed(() => {
  let count = 0;
  if (props.searchQuery) count++;
  if (props.startDate) count++;
  if (props.endDate) count++;
  return count;
});

const handleClear = () => {
  emit("clear");
  clearSortFilter();
};
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-receipt-tax" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Historial Fiscal</span>
          <span class="text-super-xs text-disabled font-weight-medium">Gestionar facturación</span>
        </div>
      </div>

      <VSpacer />

      <div class="d-flex align-center gap-2">
        <!-- Toggle Filtros -->
        <VBtn
          icon
          variant="tonal"
          :color="isFilterVisible ? 'primary' : 'secondary'"
          size="38"
          @click="isFilterVisible = !isFilterVisible"
          class="rounded-lg"
        >
          <VBadge
            :model-value="activeFiltersCount > 0"
            :content="activeFiltersCount"
            color="error"
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Filtros' : 'Mostrar Filtros' }}</VTooltip>
        </VBtn>

        <!-- Ordenar -->
        <VMenu transition="scale-transition">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon
              variant="tonal"
              color="secondary"
              size="38"
              class="rounded-lg"
            >
              <VIcon :icon="getSelectedSortIcon() || 'tabler-sort-ascending'" size="20" />
              <VTooltip activator="parent" location="top">Ordenar Lista</VTooltip>
            </VBtn>
          </template>
          <VList class="rounded-lg shadow-lg border-0 pa-2" min-width="200">
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :value="option"
              class="rounded-md mb-1"
              :active="isOptionSelected(option)"
              color="primary"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="18" class="me-3" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
            </VListItem>
            <VDivider v-if="selectedSort" class="my-2 opacity-10" />
            <VListItem
              v-if="selectedSort"
              class="rounded-md"
              color="error"
              @click="clearSortFilter"
            >
              <template #prepend>
                <VIcon icon="tabler-sort-ascending" size="18" class="me-3" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold text-error">Limpiar Orden</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Exportar con submenú -->
        <VMenu transition="scale-transition">
          <template #activator="{ props: menuProps }">
            <VBtn
              v-bind="menuProps"
              icon
              variant="tonal"
              color="success"
              size="38"
              class="rounded-lg"
            >
              <VIcon icon="tabler-download" size="20" />
              <VTooltip activator="parent" location="top">Exportar Datos</VTooltip>
            </VBtn>
          </template>
          <VList class="rounded-lg shadow-lg border-0 pa-2" min-width="150">
            <VListItem class="rounded-md mb-1" color="success" @click="emit('export', 'xlsx')">
              <template #prepend>
                <VIcon icon="tabler-file-type-csv" size="18" class="me-3" color="success" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold text-success">Excel (XLSX)</VListItemTitle>
            </VListItem>
            <VListItem class="rounded-md" color="error" @click="emit('export', 'pdf')">
              <template #prepend>
                <VIcon icon="tabler-file-type-pdf" size="18" class="me-3" color="error" />
              </template>
              <VListItemTitle class="text-xs font-weight-bold text-error">PDF Document</VListItemTitle>
            </VListItem>
          </VList>
        </VMenu>

        <!-- Limpiar Todo -->
        <VBtn
          icon
          variant="tonal"
          color="error"
          size="38"
          class="rounded-lg"
          @click="handleClear"
          :disabled="activeFiltersCount === 0 && !selectedSort"
        >
          <VIcon icon="tabler-filter-x" size="20" />
          <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Búsqueda -->
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Búsqueda General</span>
              <VTextField
                :model-value="props.searchQuery"
                placeholder="ID, Razón, Factura..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                @update:model-value="emit('update:searchQuery', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-search" size="18" color="disabled" class="me-2" />
                </template>
              </VTextField>
            </VCol>

            <!-- Fecha Desde -->
            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Desde</span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Fecha inicio"
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:startDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-event" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Hasta</span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Fecha fin"
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:endDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-event" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.leading-none {
  line-height: 1;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.premium-input) {
  .v-field__outline {
    --v-field-border-opacity: 0.1;
  }
}
</style>
