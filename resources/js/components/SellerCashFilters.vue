<script setup>
import axios from '@/plugins/axios';
import { onMounted, ref, computed } from "vue";

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
  "refresh"
]);

const isFilterVisible = ref(false);
const sellers = ref([]);
const loadingSellers = ref(false);

const hasFiltersCount = computed(() => {
  let count = 0;
  if (props.startDate) count++;
  if (props.endDate) count++;
  return count;
});

const fetchSellers = async () => {
    loadingSellers.value = true;
    try {
        const response = await axios.get('/finances/cash-closure/sellers');
        sellers.value = response.data.map(seller => ({
            ...seller,
            username: seller.username
                .replace(/[._]/g, ' ')
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ')
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
</script>

<template>
  <VCard class="mb-6 rounded-xl border-0 shadow-sm overflow-hidden bg-surface">
    <!-- Barra de Acciones Principal -->
    <VCardActions class="pa-4 px-6 d-flex align-center bg-surface flex-wrap gap-4">
      <div class="d-flex align-center gap-2 me-4">
        <VAvatar color="primary" variant="tonal" size="38" class="rounded-lg">
          <VIcon icon="tabler-cash-banknote" size="20" />
        </VAvatar>
        <div class="d-flex flex-column">
          <span class="text-sm font-weight-black uppercase leading-none mb-1">Cierre de Caja</span>
          <span class="text-super-xs text-disabled font-weight-medium">Control de ingresos y arqueos</span>
        </div>
      </div>

      <!-- Búsqueda por Vendedor (Siempre visible en desktop si no está colapsado) -->
      <div class="flex-grow-1 min-w-200">
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
          class="premium-input rounded-lg"
          :loading="loadingSellers"
          @update:model-value="emit('update:searchQuery', $event)"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-user-search" size="18" color="disabled" class="me-2" />
          </template>
        </VAutocomplete>
      </div>

      <div class="d-flex align-center gap-2 ms-auto">
        <!-- Toggle Filtros de Fecha -->
        <VBtn
          v-if="props.showDateFilters"
          icon
          variant="tonal"
          :color="isFilterVisible ? 'primary' : 'secondary'"
          size="38"
          @click="isFilterVisible = !isFilterVisible"
          class="rounded-lg"
        >
          <VBadge
            :model-value="hasFiltersCount > 0"
            :content="hasFiltersCount"
            color="error"
            offset-x="3"
            offset-y="3"
          >
            <VIcon :icon="isFilterVisible ? 'tabler-calendar-off' : 'tabler-calendar-search'" size="20" />
          </VBadge>
          <VTooltip activator="parent" location="top">{{ isFilterVisible ? 'Ocultar Fechas' : 'Filtrar por Fecha' }}</VTooltip>
        </VBtn>

        <VDivider vertical class="mx-1 my-2" />

        <!-- Actualizar -->
        <VBtn
          icon
          color="primary"
          variant="flat"
          size="38"
          class="rounded-lg shadow-sm"
          :loading="props.loading"
          @click="emit('refresh')"
        >
          <VIcon icon="tabler-refresh" size="20" />
          <VTooltip activator="parent" location="top">Actualizar Datos</VTooltip>
        </VBtn>
      </div>
    </VCardActions>

    <!-- Panel de Filtros Colapsable -->
    <VExpandTransition>
      <div v-show="isFilterVisible && props.showDateFilters">
        <VDivider class="opacity-10" />
        <VCardText class="pa-6 pt-4 bg-surface-variant-opacity-2">
          <VRow>
            <!-- Fecha Desde -->
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Rango de Fecha: Desde</span>
              <AppDateTimePicker
                :model-value="props.startDate"
                placeholder="Desde..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:startDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Fecha Hasta -->
            <VCol cols="12" md="4">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Rango de Fecha: Hasta</span>
              <AppDateTimePicker
                :model-value="props.endDate"
                placeholder="Hasta..."
                variant="outlined"
                density="compact"
                hide-details
                color="primary"
                clearable
                class="premium-input"
                :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
                @update:model-value="emit('update:endDate', $event)"
              >
                <template #prepend-inner>
                  <VIcon icon="tabler-calendar-check" size="18" color="disabled" class="me-2" />
                </template>
              </AppDateTimePicker>
            </VCol>

            <!-- Limpiar -->
            <VCol cols="12" md="4" class="d-flex align-end">
              <VBtn
                variant="tonal"
                color="error"
                block
                class="rounded-lg font-weight-black text-xs h-10"
                prepend-icon="tabler-filter-x"
                @click="emit('clear')"
              >
                LIMPIAR FILTROS
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </div>
    </VExpandTransition>
  </VCard>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}

.leading-none {
  line-height: 1;
}

.min-w-200 {
  min-inline-size: 200px;
}

.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

:deep(.premium-input) {
  .v-field__outline {
    border-radius: 6px;

    --v-field-border-opacity: 0.1;
  }

  .v-field__input {
    font-size: 0.875rem;
  }
}

.h-10 {
  block-size: 40px !important;
}

.text-xs {
  font-size: 0.7rem !important;
}
</style>
```
