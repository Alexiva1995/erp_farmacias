<script setup>
import { ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedFrequency: [String, null],
  frequencies: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedFrequency",
  "clear",
  "add-activity",
  "sort",
]);

const sortOptions = [
  {
    title: "Nombre A-Z",
    icon: "tabler-sort-ascending-letters",
    key: "activity",
    order: "asc",
  },
  {
    title: "Nombre Z-A",
    icon: "tabler-sort-descending-letters",
    key: "activity",
    order: "desc",
  },
  {
    title: "Frecuencia A-Z",
    icon: "tabler-calendar",
    key: "frequency",
    order: "asc",
  },
  {
    title: "Más Recientes",
    icon: "tabler-clock",
    key: "created_at",
    order: "desc",
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

const getSelectedSortTitle = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order,
  );
  return option ? option.title : null;
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

const isFilterVisible = ref(false);

const handleClear = () => {
  emit("clear");
  clearSortFilter();
  isFilterVisible.value = false;
};
</script>

<template>
  <VCard class="rounded-xl border-0 shadow-sm mb-6 overflow-hidden">
    <VCardText class="pa-4">
      <div class="d-flex align-center gap-3">
        <AppTextField
          :model-value="props.searchQuery"
          placeholder="Buscar por actividad o descripción..."
          prepend-inner-icon="tabler-search"
          class="flex-grow-1 premium-input-compact"
          density="compact"
          hide-details
          clearable
          @update:model-value="emit('update:searchQuery', $event)"
        />
        
        <VBtn
          :color="isFilterVisible ? 'primary' : 'secondary'"
          variant="tonal"
          class="rounded-lg px-6 font-weight-black"
          @click="isFilterVisible = !isFilterVisible"
        >
          <VIcon start icon="tabler-filter" size="18" />
          FILTROS
          <VIcon end :icon="isFilterVisible ? 'tabler-chevron-up' : 'tabler-chevron-down'" size="16" />
        </VBtn>

        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg px-6 font-weight-black shadow-sm"
          @click="emit('add-activity')"
        >
          <VIcon start icon="tabler-plus" size="18" />
          NUEVA
        </VBtn>
      </div>

      <VExpandTransition>
        <div v-show="isFilterVisible">
          <VDivider class="my-4 border-dashed opacity-30" />
          <VRow>
            <VCol cols="12" sm="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Filtrar por Frecuencia</span>
              <VSelect
                :model-value="props.selectedFrequency"
                :items="props.frequencies"
                :loading="props.loading"
                placeholder="Seleccionar frecuencia"
                density="compact"
                hide-details
                clearable
                class="premium-input-compact"
                @update:model-value="emit('update:selectedFrequency', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block">Orden de Visualización</span>
              <div class="d-flex align-center gap-2">
                <VMenu>
                  <template #activator="{ props: menuProps }">
                    <VBtn v-bind="menuProps" variant="outlined" color="secondary" density="compact" class="rounded-lg flex-grow-1 h-38">
                      {{ getSelectedSortTitle() || 'ORDENAR POR' }}
                      <VIcon end icon="tabler-chevron-down" size="16" />
                    </VBtn>
                  </template>
                  <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                    <VListItem
                      v-for="(option, index) in sortOptions"
                      :key="index"
                      :class="{ 'bg-primary-lighten-5': isOptionSelected(option) }"
                      @click="handleSortClick(option)"
                    >
                      <template #prepend>
                        <VIcon :icon="option.icon" size="18" class="me-2" />
                      </template>
                      <VListItemTitle class="text-xs font-weight-bold">{{ option.title }}</VListItemTitle>
                      <template #append>
                        <VIcon
                          v-if="isOptionSelected(option)"
                          icon="tabler-check"
                          size="14"
                          color="primary"
                        />
                      </template>
                    </VListItem>
                  </VList>
                </VMenu>
                
                <VBtn 
                  v-if="selectedSort" 
                  icon="tabler-x" 
                  size="32" 
                  variant="tonal" 
                  color="error" 
                  class="rounded-lg" 
                  @click="clearSortFilter" 
                />
              </div>
            </VCol>
          </VRow>
          
          <div class="d-flex justify-end mt-4">
            <VBtn 
              variant="text" 
              color="secondary" 
              size="small" 
              class="font-weight-black" 
              @click="handleClear"
            >
              LIMPIAR FILTROS
            </VBtn>
          </div>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.h-38 {
  height: 38px !important;
}

.border-dashed {
  border-style: dashed !important;
}

:deep(.premium-input-compact) {
  .v-field__input {
    min-height: 38px !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    font-size: 0.8125rem !important;
  }
}
</style>
