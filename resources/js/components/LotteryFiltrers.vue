<script setup>
// Filtros para sorteos (Lottery)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  numero_de_premios: [String, Number, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  laboratory_id: [Array, String, Number, null],
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
  <AppFilterBase
    :has-advanced-filters="hasActiveAdvancedFilters"
    class="py-1"
    @clear="emit('clear')"
  >
    <!-- Laboratorio en lugar de búsqueda -->
    <template #search>
      <VAutocomplete
        :model-value="props.laboratory_id"
        placeholder="Seleccionar laboratorios..."
        variant="outlined"
        density="compact"
        hide-details
        multiple
        chips
        closable-chips
        :items="props.laboratories"
        item-title="name"
        item-value="id"
        clearable
        @update:model-value="emit('update:laboratory_id', $event)"
      />
    </template>

    <template #search-append>
      <!-- Realizar Sorteo -->
      <VBtn
        icon
        color="primary"
        variant="flat"
        size="38"
        class="shadow-sm"
        @click="emit('action-sortiar', 'ok')"
      >
        <VIcon icon="tabler-trophy" />
        <VTooltip activator="parent" location="top">Realizar Sorteo</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Fecha Desde -->
      <VCol cols="12" sm="3">
        <AppDateTimePicker
          :model-value="props.fechaDesde_filtro"
          placeholder="Desde"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaDesde_filtro', $event)"
        />
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" sm="3">
        <AppDateTimePicker
          :model-value="props.fechaHasta_filtro"
          placeholder="Hasta"
          prepend-inner-icon="tabler-calendar"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:fechaHasta_filtro', $event)"
        />
      </VCol>

      <!-- Monto Mínimo -->
      <VCol cols="12" sm="3" md="3">
        <AppTextField
          :model-value="props.monto_minimo"
          type="number"
          placeholder="Monto mínimo"
          prepend-inner-icon="tabler-coin"
          clearable
          density="compact"
          hide-details
          @update:model-value="emit('update:monto_minimo', $event)"
        />
      </VCol>

      <!-- N° de Ganadores -->
      <VCol cols="12" sm="3" md="3">
        <AppTextField
          :model-value="props.numero_de_premios"
          type="number"
          placeholder="N° ganadores"
          prepend-inner-icon="tabler-users"
          clearable
          density="compact"
          hide-details
          @update:model-value="emit('update:numero_de_premios', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
}
</style>
