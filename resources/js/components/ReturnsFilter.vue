<script setup>
const props = defineProps({
  search: String,
  status: String,
  supplier: String,
  startDate: String,
  endDate: String,
  seller: String,
  sellers: Array,
});

const emit = defineEmits([
  "update:search",
  "update:status",
  "update:supplier",
  "update:startDate",
  "update:endDate",
  "update:seller",
  "clear",
]);

const statuses = [
  { title: "Todas", value: "" },
  { title: "Pendiente", value: "pending" },
  { title: "Aprobado", value: "Approved" },
  { title: "Rechazado", value: "Rejected" },
];

const isAdvancedFiltersVisible = ref(false);

const toggleAdvancedFilters = () => {
  isAdvancedFiltersVisible.value = !isAdvancedFiltersVisible.value;
};

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.startDate ||
    props.endDate ||
    props.status ||
    props.seller
  );
});
</script>

<template>
  <VCard class="mb-4 elevation-1 border-0 rounded-lg overflow-hidden">
    <VCardText class="pa-3">
      <!-- Fila Principal: Búsqueda y Botones de Acción -->
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="6" lg="5">
          <AppTextField
            :model-value="props.search"
            placeholder="BUSCAR DEVOLUCIÓN, PRODUCTO O N° ORDEN..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            class="premium-input-compact"
            @update:model-value="emit('update:search', $event)"
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
            class="rounded-lg"
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

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros (Solo Icono) -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="emit('clear')"
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
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="FECHA INICIAL"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                class="premium-input-compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="FECHA FINAL"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-calendar-event"
                class="premium-input-compact"
                :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="2">
              <VSelect
                :model-value="props.status"
                :items="statuses"
                placeholder="ESTADO"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-filter-cog"
                variant="outlined"
                class="premium-input-compact"
                @update:model-value="emit('update:status', $event)"
              />
            </VCol>

            <VCol cols="12" sm="6" md="4">
              <VSelect
                :model-value="props.seller"
                :items="props.sellers ?? []"
                item-title="username"
                item-value="id"
                placeholder="VENDEDOR / RESPONSABLE"
                clearable
                density="compact"
                hide-details
                prepend-inner-icon="tabler-user-search"
                variant="outlined"
                class="premium-input-compact"
                @update:model-value="emit('update:seller', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.premium-input-compact :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;

  color: rgba(0, 0, 0, 15%) !important;
}

.premium-input-compact :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;

  color: rgb(var(--v-theme-primary)) !important;
}

/* Forzar altura y alineación en todos los campos */
.premium-input-compact :deep(.v-field) {
  padding-inline-start: 12px !important;
  border-radius: 8px !important;
  min-block-size: 38px !important;
}

.premium-input-compact :deep(.v-field__input) {
  display: flex !important;
  align-items: center !important;
  padding-block: 0 !important;
  font-size: 0.75rem !important;
  font-weight: 700;
  min-block-size: 38px !important;
  text-transform: uppercase;
}

.premium-input-compact :deep(.v-select__selection),
.premium-input-compact :deep(.v-select__selection-text) {
  font-size: 0.75rem !important;
  font-weight: 700;
  text-transform: uppercase;
}

.gap-1 { gap: 4px !important; }

.gap-2 { gap: 8px !important; }
</style>
