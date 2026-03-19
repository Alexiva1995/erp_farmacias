<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedSupplier: [Number, String, null],
  startDate: String,
  endDate: String,
  showOverdueOnly: Boolean,
  suppliers: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isLoadingFilters: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedSupplier",
  "update:startDate",
  "update:endDate",
  "update:showOverdueOnly",
  "clear",
  "refresh",
]);

const isFilterVisible = ref(false);

const hasFiltersCount = computed(() => {
  let count = 0;
  if (props.searchQuery) count++;
  if (props.selectedSupplier) count++;
  if (props.startDate) count++;
  if (props.endDate) count++;
  if (props.showOverdueOnly) count++;
  return count;
});

const handleSearchUpdate = (val) => emit("update:searchQuery", val);
const handleSupplierUpdate = (val) => emit("update:selectedSupplier", val);
const handleStartDateUpdate = (val) => emit("update:startDate", val);
const handleEndDateUpdate = (val) => emit("update:endDate", val);
const handleOverdueUpdate = (val) => emit("update:showOverdueOnly", val);
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-credit-card-pay" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Cuentas por Pagar</span>
          <span class="text-super-xs text-disabled font-weight-medium">Gestión de facturas vencidas y por pagar</span>
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
            :model-value="hasFiltersCount > 0"
            :content="hasFiltersCount"
            color="error"
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Filtros' : 'Mostrar Filtros' }}</VTooltip>
        </VBtn>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Actualizar -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg shadow-sm"
          :loading="props.loading"
          @click="emit('refresh')"
        >
          <VIcon icon="tabler-refresh" size="20" />
          <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
        </VBtn>

        <!-- Limpiar -->
        <VBtn
          icon
          variant="tonal"
          color="error"
          size="38"
          class="rounded-lg"
          @click="emit('clear')"
          :disabled="props.loading"
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
            <VCol cols="12" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Número de Factura</span>
              <VTextField
                :model-value="props.searchQuery"
                placeholder="Buscar..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                @update:model-value="handleSearchUpdate"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-search" size="18" color="disabled" class="me-2" />
                </template>
              </VTextField>
            </VCol>

            <!-- Proveedor -->
            <VCol cols="12" md="3">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Proveedor</span>
              <VAutocomplete
                :model-value="props.selectedSupplier"
                :items="props.suppliers"
                :loading="props.isLoadingFilters"
                item-title="name"
                item-value="id"
                placeholder="Seleccionar..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input text-capitalize"
                @update:model-value="handleSupplierUpdate"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-building-factory-2" size="18" color="disabled" class="me-2" />
                </template>
              </VAutocomplete>
            </VCol>

            <!-- Fecha Desde -->
            <VCol cols="12" md="2">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Desde</span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Mínimo..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="handleStartDateUpdate"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" md="2">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Hasta</span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Máximo..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="handleEndDateUpdate"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-check" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Solo Vencidos -->
            <VCol cols="12" md="2" class="d-flex align-center">
              <VCheckbox
                :model-value="props.showOverdueOnly"
                label="Solo Vencidos"
                hide-details
                density="compact"
                color="error"
                @update:model-value="handleOverdueUpdate"
              />
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
