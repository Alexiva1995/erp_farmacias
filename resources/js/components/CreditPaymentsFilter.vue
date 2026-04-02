<script setup>
// Filtros para pagos de crédito — cliente, fecha y moneda
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  client:   String,
  date:     String,
  currency: String,
});

const emit = defineEmits([
  "update:client",
  "update:date",
  "update:currency",
  "clear",
]);

const currencies = ["USD", "COP", "BS"];

const hasAdvancedFilters = computed(() => !!(props.date || props.currency));
</script>

<template>
  <AppFilterBase
    :search="props.client"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por cliente o identificación..."
    class="py-1"
    @update:search="emit('update:client', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Fecha de pago -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.date"
          placeholder="Fecha"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:date', $event)"
        />
      </VCol>

      <!-- Moneda -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.currency"
          :items="currencies"
          placeholder="Moneda"
          clearable
          density="compact"
          hide-details
          prepend-inner-icon="tabler-coin"
          variant="outlined"
          @update:model-value="emit('update:currency', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
