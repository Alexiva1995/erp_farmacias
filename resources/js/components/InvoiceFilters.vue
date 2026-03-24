<script setup>
const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  startDate: [String, null],
  endDate: [String, null],
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

import { ref, computed } from "vue";

const isAdvancedFiltersVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return props.selectedSupplier || props.startDate || props.endDate;
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "clear",
]);

const handleClear = () => {
  emit("clear");
  isAdvancedFiltersVisible.value = false;
};
</script>

<template>
  <VCard class="mb-6 border-0 shadow-sm overflow-hidden">
    <VCardText class="pa-3">
      <!-- Barra de Búsqueda Principal (Siempre Visible) -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por N° Factura, Control..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            hide-details
            class="premium-input-compact"
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
            @click="isAdvancedFiltersVisible = !isAdvancedFiltersVisible"
          >
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFiltersVisible"
              color="error"
              dot
              offset-x="2"
              offset-y="-2"
            >
              <VIcon :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            </VBadge>
            <VIcon v-else :icon="isAdvancedFiltersVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2 border-opacity-10" />

          <!-- Limpiar Filtros (Siempre Visible) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" size="20" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Panel de Filtros Avanzado -->
      <VExpandTransition>
        <div v-show="isAdvancedFiltersVisible">
          <VDivider class="my-3 border-opacity-10" />
          
          <VRow>
            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Proveedor
              </span>
              <VAutocomplete
                :model-value="props.selectedSupplier"
                :items="props.suppliers"
                :loading="props.loading"
                placeholder="Seleccionar proveedor"
                item-title="name"
                item-value="id"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-select-compact"
                @update:model-value="emit('update:selectedSupplier', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Recibido Desde
              </span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Fecha inicio"
                density="compact"
                hide-details
                class="premium-input-compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-1 d-block pe-1">
                Recibido Hasta
              </span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Fecha fin"
                density="compact"
                hide-details
                class="premium-input-compact"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>
