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
  expirationDays: [String, null],
  stock: [String, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:stockStatusFilter",
  "update:startDate",
  "update:endDate",
  "update:expProd",
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
  { title: "Faltas", value: "faltas" },
  { title: "All", value: "all" },
];

const diasVencimientos = [
  { title: "15", value: 15 },
  { title: "30", value: 30 },
  { title: "60", value: 60 },
  { title: "90", value: 90 },
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
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.expirationDays"
            label="Dias de vencimiento"
            :items="diasVencimientos"
            clearable
            @update:model-value="emit('update:expirationDays', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="props.stock"
            label="Stock"
            :items="stock"
            clearable
            @update:model-value="emit('update:stock', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <v-checkbox
            v-model="props.expProd"
            color="primary"
            label="Pos. Exp"
            @update:model-value="emit('update:expProd', $event)"
            hide-details
          ></v-checkbox>
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="handleClear">
        Limpiar Filtros
      </VBtn>

      <!-- <div class="d-flex align-center gap-2">
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
      </div> -->

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
