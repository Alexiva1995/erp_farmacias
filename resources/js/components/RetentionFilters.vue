<script setup>
// Filtros Retenciones de IVA (RetentionFilters)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  search:        String,
  supplierId:    [Number, String, null],
  startDate:     String,
  endDate:       String,
  suppliers:     { type: Array,   default: () => [] },
  loading:       { type: Boolean, default: false },
  selectedCount: { type: Number,  default: 0 },
  currentTab:    { type: String,  default: "pending" },
});

const emit = defineEmits([
  "update:search",
  "update:supplierId",
  "update:startDate",
  "update:endDate",
  "clear",
  "bulk-generate",
]);

const hasAdvancedFilters = computed(() =>
  !!(props.supplierId || props.startDate || props.endDate)
);
</script>

<template>
  <AppFilterBase
    :search="props.search"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Factura o Proveedor..."
    class="py-1"
    @update:search="emit('update:search', $event)"
    @clear="emit('clear')"
  >
    <template #actions-extra>
      <!-- Acción Masiva (Generación Batch) -->
      <VExpandTransition>
        <VBtn
          v-show="props.selectedCount > 0 && props.currentTab === 'pending'"
          icon
          color="success"
          variant="flat"
          size="38"
          class="ml-1"
          @click="emit('bulk-generate')"
        >
          <VIcon icon="tabler-check" />
          <VTooltip activator="parent" location="top">Generar {{ props.selectedCount }} Retenciones</VTooltip>
        </VBtn>
      </VExpandTransition>
    </template>

    <template #advanced-filters>
      <!-- Proveedor -->
      <VCol cols="12" sm="4">
        <VSelect
          :model-value="props.supplierId"
          :items="props.suppliers"
          item-title="name"
          item-value="id"
          placeholder="Proveedor"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-building-factory-2"
          @update:model-value="emit('update:supplierId', $event)"
        />
      </VCol>

      <!-- Fecha Inicial -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Fecha Inicial"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <!-- Fecha Final -->
      <VCol cols="12" sm="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Fecha Final"
          variant="outlined"
          density="compact"
          hide-details
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
