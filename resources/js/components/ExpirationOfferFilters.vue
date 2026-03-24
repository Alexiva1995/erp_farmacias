<script setup>
// Filtros para ofertas por vencimiento de productos
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed } from "vue";

const props = defineProps({
  searchQuery:     { type: String,  default: "" },
  status:          { type: String,  default: "" },
  months:          { type: String,  default: "" },
  loading:         { type: Boolean, default: false },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:status",
  "update:months",
  "clear",
  "add-expiration-offer",
]);

const statusOptions = [
  { title: "Todos los estados", value: "" },
  { title: "Activos",           value: "1" },
  { title: "Inactivos",         value: "0" },
];

const monthsOptions = [
  { title: "Cualquier rango", value: ""   },
  { title: "1 mes",           value: "1"  },
  { title: "2 meses",         value: "2"  },
  { title: "3 meses",         value: "3"  },
  { title: "6 meses",         value: "6"  },
  { title: "12 meses",        value: "12" },
];

const hasAdvancedFilters = computed(() =>
  (props.status !== "" && props.status !== null) ||
  (props.months !== "" && props.months !== null)
);
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    :show-add="true"
    add-button-text="Nueva Oferta"
    search-placeholder="Buscar por descuento o meses..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
    @add="emit('add-expiration-offer')"
  >
    <template #advanced-filters>
      <!-- Estado de oferta -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          placeholder="Estado de oferta"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-circle-dot"
          @update:model-value="emit('update:status', $event)"
        />
      </VCol>

      <!-- Meses de vencimiento -->
      <VCol cols="12" sm="6" md="3">
        <VSelect
          :model-value="props.months"
          :items="monthsOptions"
          item-title="title"
          item-value="value"
          placeholder="Meses de vencimiento"
          density="compact"
          hide-details
          clearable
          prepend-inner-icon="tabler-calendar-time"
          @update:model-value="emit('update:months', $event)"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
