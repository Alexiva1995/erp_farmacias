<script setup>
import { useAuthStore } from "@/stores/auth";
import { computed, ref } from "vue";

const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  stockStatusFilter: [Boolean, null],
  expProd: [Boolean, null],
  startDate: [String, null],
  endDate: [String, null],
  laboratories: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  days: [String, null],
  stock: [String, null],
  isStrictSearch: { type: Boolean, default: false },
  tipoFiltracion: { type: String, default: "average" },
  isColombian: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:expProd",
  "update:isStrictSearch",
  "update:tipoFiltracion",
  "update:isColombian",
  "clear",
  "add-product",
  "sort",
  "export-pdf",
  "export-excel",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
];

const stock = [
  { title: "Exceso", value: "exceso" },
  { title: "Fallas", value: "fallas" },
  { title: "All", value: "all" },
];

const diasVencimientos = [
  { title: "7", value: 7 },
  { title: "15", value: 15 },
  { title: "30", value: 30 },
  { title: "60", value: 60 },
  { title: "90", value: 90 },
];

const tipoFiltracionOpcion = [
  { title: "Promedio", value: "average" },
  { title: "Ventas", value: "sales" },
  { title: "Combinado", value: "combinado" },
];

// const sortOptions = [
//   {
//     title: "Precio mayor",
//     icon: "tabler-arrow-up",
//     key: "sale_price",
//     order: "desc",
//   },
//   {
//     title: "Precio Menor",
//     icon: "tabler-arrow-down",
//     key: "sale_price",
//     order: "asc",
//   },
//   {
//     title: "Más Unidades",
//     icon: "tabler-plus",
//     key: "valid_stock",
//     order: "desc",
//   },
//   {
//     title: "Menos Unidades",
//     icon: "tabler-minus",
//     key: "valid_stock",
//     order: "asc",
//   },
//   {
//     title: "Fecha pronto a Vencer",
//     icon: "tabler-calendar-time",
//     key: "next_expiration",
//     order: "asc",
//   },
//   {
//     title: "Más Vendidos",
//     icon: "tabler-trending-up",
//     key: "most_sold",
//     order: "desc",
//   },
// ];

const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

const selectedSort = ref(null);

const getStorageKey = () =>
  `product_sort_filter_user_${currentUser.value?.id || "anonymous"}`;

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

const handleClear = () => {
  emit("clear");
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="2">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Producto, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="2">
          <VAutocomplete
            :model-value="props.selectedLaboratory"
            :items="props.laboratories"
            :loading="props.loading"
            label="Laboratorio"
            placeholder="Buscar un laboratorio"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="2">
          <VSelect
            :model-value="props.stockStatusFilter"
            label="Estado de Stock"
            placeholder="Stock"
            :items="stockOptions"
            clearable
            @update:model-value="emit('update:stockStatusFilter', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            clearable
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>

        <VCol cols="12" sm="6" md="3">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
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
      <VSelect
        :model-value="props.stock"
        label="Stock"
        :items="stock"
        clearable
        style="max-inline-size: 150px;"
        @update:model-value="emit('update:stock', $event)"
      />

      <VSelect
        :model-value="props.days"
        label="Días"
        :items="diasVencimientos"
        style="max-inline-size: 150px;"
        @update:model-value="emit('update:days', $event)"
      />

      <VSelect
        :model-value="props.tipoFiltracion"
        label="Calcular Por"
        :items="tipoFiltracionOpcion"
        style="max-inline-size: 150px;"
        @update:model-value="emit('update:tipoFiltracion', $event)"
      />

      <VCheckbox
        :model-value="props.expProd"
        @update:model-value="emit('update:expProd', $event)"
        color="error"
        class="me-2"
      >
        <template #label>
          <div class="d-flex align-center">
            <VIcon icon="tabler-calendar-time" class="me-2" size="20" />
            <span class="text-subtitle-1 font-weight-medium">
              ¿Próximo a Expirar?
            </span>
          </div>
        </template>
      </VCheckbox>

      <VChip v-if="props.expProd" color="error" size="small" class="ms-2">
        <VIcon icon="tabler-alert-triangle" size="14" class="me-1" />
        Filtrando por Expiración
      </VChip>

      <VCheckbox
        :model-value="props.isColombian"
        @update:model-value="emit('update:isColombian', $event)"
        color="info"
        class="me-2"
      >
        <template #label>
          <div class="d-flex align-center">
            <VIcon icon="tabler-flag" class="me-2" size="20" />
            <span class="text-subtitle-1 font-weight-medium">
              COL
            </span>
          </div>
        </template>
      </VCheckbox>

      <VChip v-if="props.isColombian" color="info" size="small" class="ms-2">
        <VIcon icon="tabler-flag" size="14" class="me-1" />
        COL
      </VChip>

      <div class="d-flex align-center gap-2">
        <VCheckbox
          :model-value="props.isStrictSearch"
          @update:model-value="emit('update:isStrictSearch', $event)"
          color="primary"
          class="me-2"
        >
          <template #label>
            <div class="d-flex align-center">
              <VIcon icon="tabler-search" class="me-2" size="20" />
              <span class="text-subtitle-1 font-weight-medium">
                ¿Búsqueda Estricta?
              </span>
            </div>
          </template>
        </VCheckbox>

        <VChip
          v-if="props.isStrictSearch"
          color="primary"
          size="small"
          class="ms-2"
        >
          <VIcon icon="tabler-alert-circle" size="14" class="me-1" />
          Modo Estricto Activo
        </VChip>
      </div>

      <VSpacer />
      <VBtn color="secondary" variant="outlined" @click="handleClear">
        Limpiar Filtros
      </VBtn>
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
          <VListItem @click="emit('export-excel', 'xlsx')">
            <template #prepend>
              <VIcon icon="tabler-file-type-csv" class="me-2" color="success" />
            </template>
            <VListItemTitle class="text-success">Excel</VListItemTitle>
          </VListItem>
          <VListItem @click="emit('export-pdf')">
            <template #prepend>
              <VIcon icon="tabler-file-type-pdf" class="me-2" />
            </template>
            <VListItemTitle>PDF</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
    </VCardActions>
  </VCard>
</template>
