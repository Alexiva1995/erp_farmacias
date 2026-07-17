<script setup>
// Filtros para sorteos (Lottery)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  numero_de_premios: [String, Number, null],
  fechaHasta_filtro: [String, null],
  fechaDesde_filtro: [String, null],
  laboratory_id: [Array, String, Number, null],
  dish_id: [Array, String, Number, null],
  monto_minimo: [String, Number, null],
  laboratories: { type: Array, default: () => [] },
  dishes: { type: Array, default: () => [] },
});

const emit = defineEmits([
  "update:numero_de_premios",
  "update:fechaHasta_filtro",
  "update:fechaDesde_filtro",
  "update:laboratory_id",
  "update:dish_id",
  "update:monto_minimo",
  "clear",
  "action-sortiar",
]);

const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === 'restaurant');

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
      <div class="d-flex align-center gap-3 w-100 flex-wrap flex-sm-nowrap">
        <VAutocomplete
          :model-value="props.laboratory_id"
          :placeholder="(isRestaurant || brandingStore.settings.business_type === 'minimarket') ? 'Seleccionar marcas...' : 'Seleccionar laboratorios...'"
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
          style="min-width: 200px;"
          @update:model-value="emit('update:laboratory_id', $event)"
        />
        
        <VAutocomplete
          v-if="isRestaurant"
          :model-value="props.dish_id"
          placeholder="Seleccionar platos favoritos..."
          variant="outlined"
          density="compact"
          hide-details
          multiple
          chips
          closable-chips
          :items="props.dishes"
          item-title="name"
          item-value="id"
          clearable
          style="min-width: 250px;"
          @update:model-value="emit('update:dish_id', $event)"
        />
      </div>
    </template>

    <template #actions-extra>
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
