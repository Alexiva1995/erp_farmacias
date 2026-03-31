<script setup>
// Filtros de Nómina (Payroll)
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: { type: String, default: "" },
  startDate:   { type: [String, null], default: null },
  endDate:     { type: [String, null], default: null },
  selectedStatus: { type: [Number, null], default: null },
  loading:     { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:startDate",
  "update:endDate",
  "update:selectedStatus",
  "clear",
  "generated",
  "download-bulk",
  "refresh",
]);

const isGenerating = ref(false);

const statusOptions = [
  { title: "Finalizada", value: 1 },
  { title: "Pendiente",  value: 0 },
];

const hasAdvancedFilters = computed(() => !!(props.startDate || props.endDate || props.selectedStatus !== null));

const handleManualPayment = async () => {
  isGenerating.value = true;
  try {
    emit("generated");
  } finally {
    isGenerating.value = false;
  }
};
</script>

<template>
  <AppFilterBase
    :search="props.searchQuery"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por ID..."
    @update:search="emit('update:searchQuery', $event)"
    @clear="emit('clear')"
  >
    <template #advanced-filters>
      <!-- Rango de Fechas -->
      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Desde"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar"
          @update:model-value="emit('update:startDate', $event)"
        />
      </VCol>

      <VCol cols="12" sm="6" md="4">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Hasta"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-check"
          @update:model-value="emit('update:endDate', $event)"
        />
      </VCol>

      <!-- Estado -->
      <VCol cols="12" sm="6" md="4">
        <VSelect
          :model-value="props.selectedStatus"
          :items="statusOptions"
          placeholder="Estado de Nómina"
          clearable
          density="compact"
          hide-details
          variant="outlined"
          prepend-inner-icon="tabler-list-check"
          @update:model-value="emit('update:selectedStatus', $event)"
        />
      </VCol>
    </template>

    <template #actions-extra>
      <VBtn
        color="success"
        variant="elevated"
        size="38"
        icon
        class="rounded-circle shadow-sm"
        @click="emit('download-bulk')"
      >
        <VIcon
          icon="tabler-file-spreadsheet"
          size="20"
        />
        <VTooltip
          activator="parent"
          location="top"
        >
          Descargar Todo 2025
        </VTooltip>
      </VBtn>

      <VBtn
        color="primary"
        variant="elevated"
        size="38"
        icon
        class="rounded-circle shadow-primary"
        :loading="isGenerating"
        :disabled="isGenerating"
        @click="handleManualPayment"
      >
        <VIcon
          icon="tabler-player-play-filled"
          size="20"
        />
        <VTooltip
          activator="parent"
          location="top"
        >
          Generar Nómina Manual
        </VTooltip>
      </VBtn>
    </template>
  </AppFilterBase>
</template>

<style scoped>
.shadow-sm {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}
</style>
