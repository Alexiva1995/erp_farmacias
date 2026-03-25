<script setup>
import { computed, ref } from "vue";

const props = defineProps({
  numero_de_premios: [String, Number, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  laboratory_id: [String, Number, null],
  monto_minimo: [String, Number, null],
  laboratories: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:numero_de_premios",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "update:laboratory_id",
  "update:monto_minimo",
  "clear",
  "action-sortiar",
]);

const isAdvancedFilterVisible = ref(false);

const hasActiveAdvancedFilters = computed(() => {
  return (
    props.fechaDesde_filtro ||
    props.fechaHasta_filtro ||
    (props.monto_minimo && props.monto_minimo > 0) ||
    (props.numero_de_premios && props.numero_de_premios != 3)
  );
});
</script>

<template>
  <VCard class="mb-6 rounded-lg border shadow-sm overflow-visible">
    <VCardText class="pa-3">
      <VRow align="center" no-gutters class="gap-2 px-2">
        <!-- Laboratorio (Visible) -->
        <VCol cols="12" sm="5" md="4" lg="4">
          <VAutocomplete
            :model-value="props.laboratory_id"
            placeholder="LABORATORIO..."
            variant="outlined"
            density="compact"
            hide-details
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            clearable
            class="premium-input shadow-sm"
            @update:model-value="emit('update:laboratory_id', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Toggle Filtros -->
          <VBtn
            icon
            variant="tonal"
            :color="isAdvancedFilterVisible ? 'primary' : 'secondary'"
            size="38"
            class="shadow-sm"
            @click="isAdvancedFilterVisible = !isAdvancedFilterVisible"
          >
            <VIcon :icon="isAdvancedFilterVisible ? 'tabler-filter-off' : 'tabler-filter'" />
            <VTooltip activator="parent" location="top">Filtros Avanzados</VTooltip>
            <VBadge
              v-if="hasActiveAdvancedFilters && !isAdvancedFilterVisible"
              color="error"
              dot
              offset-x="3"
              offset-y="-3"
            />
          </VBtn>

          <!-- Realizar Sorteo -->
          <VBtn
            icon
            color="primary"
            variant="flat"
            size="38"
            class="shadow-primary-sm"
            @click="emit('action-sortiar', 'ok')"
          >
            <VIcon icon="tabler-trophy" />
            <VTooltip activator="parent" location="top">Realizar Sorteo</VTooltip>
          </VBtn>

          <VDivider
            vertical
            class="mx-1 my-2"
          />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            :disabled="!props.laboratory_id && !hasActiveAdvancedFilters"
            @click="emit('clear')"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>

      <!-- Filtros Avanzados Colapsables -->
      <VExpandTransition>
        <div v-show="isAdvancedFilterVisible">
          <VDivider class="my-3 border-opacity-10" />
          <VRow dense class="px-2 pb-2">
            <!-- Fecha Desde -->
            <VCol cols="12" sm="3">
              <AppDateTimePicker
                placeholder="FECHA DESDE..."
                :model-value="props.fechaDesde_filtro"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:fechaDesde_filtro', $event)"
              />
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" sm="3">
              <AppDateTimePicker
                placeholder="FECHA HASTA..."
                :model-value="props.fechaHasta_filtro"
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                :config="{
                  altInput: true,
                  altFormat: 'Y-m-d',
                  dateFormat: 'Y-m-d',
                }"
                @update:model-value="emit('update:fechaHasta_filtro', $event)"
              />
            </VCol>

            <!-- Monto Mínimo -->
            <VCol cols="12" sm="3" md="2">
              <AppTextField
                type="number"
                :model-value="props.monto_minimo"
                placeholder="MONTO MÍNIMO..."
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                @update:model-value="emit('update:monto_minimo', $event)"
              />
            </VCol>

            <!-- N° de Ganadores -->
            <VCol cols="12" sm="3" md="2">
              <AppTextField
                type="number"
                :model-value="props.numero_de_premios"
                placeholder="N° GANADORES..."
                variant="outlined"
                density="compact"
                hide-details
                clearable
                class="premium-input shadow-sm"
                @update:model-value="emit('update:numero_de_premios', $event)"
              />
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary-sm {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.2) !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }

.premium-input :deep(.v-field__outline) {
  --v-field-border-opacity: 0.15 !important;
  color: rgba(var(--v-border-color), 1) !important;
}

.premium-input :deep(.v-field--focused .v-field__outline) {
  --v-field-border-opacity: 1 !important;
  color: rgb(var(--v-theme-primary)) !important;
}

.premium-input :deep(.v-field) {
  border-radius: 8px !important;
  background-color: white !important;
}

.premium-input :deep(.v-field__input),
.premium-input :deep(.v-select__selection),
.premium-input :deep(.v-select__selection-text) {
  font-size: 0.75rem !important;
  font-weight: 700;
  text-transform: uppercase;
}
</style>
