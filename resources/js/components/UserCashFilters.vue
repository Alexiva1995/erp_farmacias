<script setup>
// Filtros Cierre de Caja por Usuario
import AppFilterBase from "@/components/AppFilterBase.vue";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:filters", "refresh"]);

const filters = ref({
  search: "",
  date_start: null,
  date_end: null,
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.date_start) count++;
  if (filters.value.date_end) count++;
  return count;
});

const clearFilters = () => {
  filters.value.date_start = null;
  filters.value.date_end = null;
  filters.value.search = "";
};

watch(
  filters,
  (val) => {
    emit("update:filters", val);
  },
  { deep: true }
);

const handleRefresh = () => {
  emit("refresh");
};

const hasAdvancedFilters = computed(() => true); // Siempre tiene rango de fechas
</script>

<template>
  <AppFilterBase
    :search="filters.search"
    :has-advanced-filters="hasAdvancedFilters"
    search-placeholder="Buscar por ID o Cliente..."
    @update:search="filters.search = $event"
    @clear="clearFilters"
  >
    <template #actions-extra>
      <VBtn
        icon
        color="info"
        variant="tonal"
        size="38"
        class="ml-1"
        :loading="props.loading"
        @click="handleRefresh"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Refrescar Datos</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Fecha Inicio -->
      <VCol cols="12" sm="6">
        <AppDateTimePicker
          v-model="filters.date_start"
          placeholder="Fecha Inicio"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-event"
        />
      </VCol>

      <!-- Fecha Fin -->
      <VCol cols="12" sm="6">
        <AppDateTimePicker
          v-model="filters.date_end"
          placeholder="Fecha Fin"
          clearable
          density="compact"
          hide-details
          :config="{ altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          prepend-inner-icon="tabler-calendar-plus"
        />
      </VCol>
    </template>
  </AppFilterBase>
</template>
