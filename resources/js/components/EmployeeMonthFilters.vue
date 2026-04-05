<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedMonth: Number,
  selectedYear: Number,
  selectedSort: Object,
  availableMonths: Array,
  availableYears: Array,
  sortOptions: Array,
  isLocked: Boolean,
  loading: Boolean,
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedMonth",
  "update:selectedYear",
  "clear",
  "lock-month",
  "sort",
]);

const isAdvancedFiltersVisible = ref(false);

const handleSortClick = (option) => {
  emit("sort", option);
};

const getSelectedSortIcon = () => {
  if (!props.selectedSort) return "tabler-sort-ascending";
  const option = props.sortOptions.find(
    (opt) =>
      opt.key === props.selectedSort.key &&
      opt.order === props.selectedSort.order
  );
  return option ? option.icon : "tabler-sort-ascending";
};

const isOptionSelected = (option) => {
  return (
    props.selectedSort &&
    props.selectedSort.key === option.key &&
    props.selectedSort.order === option.order
  );
};

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const handleClear = () => {
  emit("clear");
  isAdvancedFiltersVisible.value = false;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.selectedMonth !== new Date().getMonth() + 1 ||
    props.selectedYear !== new Date().getFullYear() ||
    (props.selectedSort && props.selectedSort.key !== "scores.total")
  );
});
</script>

<template>
  <VCard class="ma-0 border shadow-sm">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Acciones Rápidas -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar empleado..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFiltersVisible ? 'primary' : 'secondary'"
            size="38"
            @click="toggleAdvancedFilters"
          >
            <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Ordenar Por (Solo Icono) -->
          <VMenu>
            <template #activator="{ props: menuProps }">
              <VBtn 
                v-bind="menuProps" 
                icon
                variant="tonal" 
                color="secondary"
                size="38"
              >
                <VIcon :icon="getSelectedSortIcon()" />
                <VTooltip activator="parent" location="top">Ordenar Por</VTooltip>
              </VBtn>
            </template>
            <VList density="compact">
              <VListItem
                v-for="(option, index) in props.sortOptions"
                :key="index"
                :active="isOptionSelected(option)"
                color="primary"
                @click="handleSortClick(option)"
              >
                <template #prepend>
                  <VIcon :icon="option.icon" size="20" />
                </template>
                <VListItemTitle>{{ option.title }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>

          <!-- Cerrar Mes -->
          <VBtn
            v-if="!props.isLocked"
            icon
            color="error"
            variant="flat"
            size="38"
            @click="emit('lock-month')"
          >
            <VIcon icon="tabler-lock" />
            <VTooltip activator="parent" location="top">Cerrar Mes</VTooltip>
          </VBtn>
          <VChip 
            v-else 
            color="success" 
            variant="tonal" 
            class="rounded-lg h-38 font-weight-black"
            size="small"
          >
            <VIcon start icon="tabler-lock-check" size="18" />
            HISTÓRICO
          </VChip>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Colapsable -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow dense>
            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.selectedMonth"
                :items="props.availableMonths"
                item-title="title"
                item-value="value"
                placeholder="Mes"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar"
                @update:model-value="emit('update:selectedMonth', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VSelect
                :model-value="props.selectedYear"
                :items="props.availableYears"
                placeholder="Año"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                @update:model-value="emit('update:selectedYear', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

:deep(.v-field__input) {
  font-size: 0.8125rem !important;
}

.h-38 {
  height: 38px !important;
}
</style>
