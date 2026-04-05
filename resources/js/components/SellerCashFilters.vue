<script setup>
// Filtros Cierre de Caja por Vendedor
import AppFilterBase from "@/components/AppFilterBase.vue";
import axios from "@/plugins/axios";
import { computed, onMounted, ref } from "vue";

const props = defineProps({
  searchQuery: [String, Number],
  startDate: { type: String, default: null },
  endDate: { type: String, default: null },
  showDateFilters: { type: Boolean, default: false },
  showStateFilters: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "update:startDate",
  "update:endDate",
  "refresh",
]);

const sellers = ref([]);
const loadingSellers = ref(false);

const fetchSellers = async () => {
  loadingSellers.value = true;
  try {
    const response = await axios.get("/finances/cash-closure/sellers");
    sellers.value = response.data.map((seller) => ({
      ...seller,
      username: seller.username
        .replace(/[._]/g, " ")
        .split(" ")
        .map(
          (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase(),
        )
        .join(" "),
    }));
  } catch (error) {
    console.error("Error cargando vendedores", error);
  } finally {
    loadingSellers.value = false;
  }
};

onMounted(() => {
  fetchSellers();
});

const hasAdvancedFilters = computed(() => {
  if (!props.showDateFilters) return false;
  return !!(props.startDate || props.endDate);
});
</script>

<template>
  <AppFilterBase
    :search="''"
    :has-advanced-filters="hasAdvancedFilters"
    :show-advanced-toggle="props.showDateFilters"
    search-placeholder="..."
    class="py-1"
    @clear="emit('clear')"
  >
    <template #search>
      <!-- Búsqueda por Vendedor, que es el centro de este componente -->
      <VCol class="px-0">
        <VAutocomplete
          :model-value="props.searchQuery"
          :items="sellers"
          item-title="username"
          item-value="id"
          placeholder="Filtrar por Vendedor..."
          variant="outlined"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
          style="min-width: 200px"
          :loading="loadingSellers"
          @update:model-value="emit('update:searchQuery', $event)"
        >
          <template #prepend-inner>
            <VIcon
              icon="tabler-user-search"
              size="18"
              color="disabled"
              class="me-2"
            />
          </template>
        </VAutocomplete>
      </VCol>
    </template>

    <template #actions-extra>
      <!-- Actualizar -->
      <VBtn
        icon
        color="primary"
        variant="tonal"
        size="38"
        class="ml-1"
        :loading="props.loading"
        @click="emit('refresh')"
      >
        <VIcon icon="tabler-refresh" size="20" />
        <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
      </VBtn>
    </template>

    <template #advanced-filters>
      <!-- Rango de Fechas -->
      <!-- Fecha Desde -->
      <VCol cols="12" md="6" v-if="props.showDateFilters">
        <AppDateTimePicker
          :model-value="props.startDate"
          placeholder="Rango Desde..."
          variant="outlined"
          density="compact"
          hide-details
          color="primary"
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:startDate', $event)"
        >
          <template #prepend-inner>
            <VIcon
              icon="tabler-calendar"
              size="18"
              color="disabled"
              class="me-2"
            />
          </template>
        </AppDateTimePicker>
      </VCol>

      <!-- Fecha Hasta -->
      <VCol cols="12" md="6" v-if="props.showDateFilters">
        <AppDateTimePicker
          :model-value="props.endDate"
          placeholder="Rango Hasta..."
          variant="outlined"
          density="compact"
          hide-details
          color="primary"
          clearable
          :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
          @update:model-value="emit('update:endDate', $event)"
        >
          <template #prepend-inner>
            <VIcon
              icon="tabler-calendar-check"
              size="18"
              color="disabled"
              class="me-2"
            />
          </template>
        </AppDateTimePicker>
      </VCol>
    </template>
  </AppFilterBase>
</template>
