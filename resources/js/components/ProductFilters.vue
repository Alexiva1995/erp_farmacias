<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "clear",
  "export",
  "add-product",
  "sort",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const sortOptions = [
  {
    title: "Precio mayor",
    icon: "tabler-arrow-up",
    key: "sale_price",
    order: "desc",
  },
  {
    title: "Precio Menor",
    icon: "tabler-arrow-down",
    key: "sale_price",
    order: "asc",
  },
  {
    title: "Más Unidades",
    icon: "tabler-plus",
    key: "valid_stock",
    order: "desc",
  },
  {
    title: "Menos Unidades",
    icon: "tabler-minus",
    key: "valid_stock",
    order: "asc",
  },
  {
    title: "Fecha pronto a Vencer",
    icon: "tabler-calendar-time",
    key: "next_expiration",
    order: "asc",
  },
  {
    title: "Más Vendidos",
    icon: "tabler-trending-up",
    key: "most_sold",
    order: "desc",
  },
];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `product_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

const loadSavedSort = () => {
  try {
    const saved = localStorage.getItem(getStorageKey());
    if (saved) {
      const parsedSort = JSON.parse(saved);
      const isValidSort = sortOptions.find(
        (option) =>
          option.key === parsedSort.key && option.order === parsedSort.order
      );
      if (isValidSort) {
        selectedSort.value = parsedSort;
        emit("sort", parsedSort);
      }
    }
  } catch (error) {
    console.error("Error al cargar el filtro guardado:", error);
  }
};

const saveSortFilter = (sortFilter) => {
  try {
    localStorage.setItem(getStorageKey(), JSON.stringify(sortFilter));
  } catch (error) {
    console.error("Error al guardar el filtro:", error);
  }
};

const handleSortClick = (option) => {
  const sortFilter = { key: option.key, order: option.order };
  selectedSort.value = sortFilter;
  saveSortFilter(sortFilter);
  emit("sort", sortFilter);
};

const clearSortFilter = () => {
  selectedSort.value = null;
  try {
    localStorage.removeItem(getStorageKey());
  } catch (error) {
    console.error("Error al limpiar el filtro:", error);
  }
};

const getSelectedSortTitle = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.title : null;
};

const getSelectedSortIcon = () => {
  if (!selectedSort.value) return null;
  const option = sortOptions.find(
    (opt) =>
      opt.key === selectedSort.value.key &&
      opt.order === selectedSort.value.order
  );
  return option ? option.icon : null;
};

const isOptionSelected = (option) => {
  return (
    selectedSort.value &&
    selectedSort.value.key === option.key &&
    selectedSort.value.order === option.order
  );
};

const handleClear = () => {
  emit("clear");
};

onMounted(() => {
  loadSavedSort();
});

watch(
  () => currentUser.value?.id,
  () => {
    if (currentUser.value?.id) {
      loadSavedSort();
    }
  },
  { immediate: true }
);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorio"
            placeholder="Escribe para buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VAutocomplete
            :model-value="props.selectedOrigin"
            :items="props.origins"
            :loading="props.loading"
            label="Origen"
            placeholder="Escribe para buscar un origen"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedOrigin', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.stockStatusFilter"
            label="Estado de Stock"
            :items="stockOptions"
            clearable
            @update:model-value="emit('update:stockStatusFilter', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Vencimiento Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="4">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Vencimiento Hasta"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:endDate', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="handleClear">
        Limpiar Filtros
      </VBtn>

      <div class="d-flex align-center gap-2">
        <VMenu>
          <template #activator="{ props: menuProps }">
            <VBtn v-bind="menuProps" variant="tonal">
              Ordenar Por
              <VIcon end icon="tabler-chevron-down" />
            </VBtn>
          </template>
          <VList>
            <VListItem
              v-for="(option, index) in sortOptions"
              :key="index"
              :class="{ 'bg-primary-lighten-5': isOptionSelected(option) }"
              @click="handleSortClick(option)"
            >
              <template #prepend>
                <VIcon :icon="option.icon" size="20" class="me-2" />
              </template>
              <VListItemTitle>{{ option.title }}</VListItemTitle>
              <template #append>
                <VIcon
                  v-if="isOptionSelected(option)"
                  icon="tabler-check"
                  size="16"
                  color="primary"
                />
              </template>
            </VListItem>
          </VList>
        </VMenu>

        <VChip
          v-if="selectedSort"
          color="primary"
          variant="tonal"
          size="small"
          closable
          @click:close="clearSortFilter"
        >
          <VIcon :icon="getSelectedSortIcon()" size="14" class="me-1" />
          {{ getSelectedSortTitle() }}
        </VChip>
      </div>

      <VSpacer />

      <VMenu>
        <template #activator="{ props: menuProps }">
          <VBtn
            color="success"
            variant="flat"
            prepend-icon="tabler-upload"
            v-bind="menuProps"
          >
            Exportar
          </VBtn>
        </template>
        <VList>
          <VListItem @click="emit('export', 'xlsx')">
            <template #prepend>
              <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem>
          <VListItem @click="emit('export', 'pdf')">
            <template #prepend>
              <VIcon icon="tabler-file-type-pdf" class="me-2" />
            </template>
            <VListItemTitle>PDF</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-product')"
      >
        Añadir Producto
      </VBtn>
    </VCardActions>
  </VCard>
</template>
