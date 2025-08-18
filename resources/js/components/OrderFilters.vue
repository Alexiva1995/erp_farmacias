<script setup>
const props = defineProps({
  searchQuery: String,
  selectedLaboratory: [Number, String, null],
  selectedOrigin: [Number, String, null],
  laboratories: { type: Array, default: () => [] },
  origins: { type: Array, default: () => [] },
  stockStatusFilter: [Boolean, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:selectedLaboratory",
  "update:selectedOrigin",
  "update:stockStatusFilter",
  "clear",
  "back",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
  { title: "Todos", value: null },
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
    title: "Más Vendidos",
    icon: "tabler-plus",
    key: "sales_average",
    order: "desc",
  },
  {
    title: "Menos Vendidos",
    icon: "tabler-minus",
    key: "sales_average",
    order: "asc",
  },
  {
    title: "Fecha pronto a Vencer",
    icon: "tabler-calendar-time",
    key: "next_expiration",
    order: "asc",
  },
];

const handleSortClick = (option) => {
  emit("sort", { key: option.key, order: option.order });
};

const handleBack = () => {
  emit("back");
};
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedLaboratory"
            label="Laboratorio"
            :items="props.laboratories"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedLaboratory', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.selectedOrigin"
            label="Origen"
            :items="props.origins"
            item-title="name"
            item-value="id"
            clearable
            @update:model-value="emit('update:selectedOrigin', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.stockStatusFilter"
            label="Estado de Stock"
            :items="stockOptions"
            clearable
            @update:model-value="emit('update:stockStatusFilter', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')"> Limpiar Filtros</VBtn>
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
            @click="handleSortClick(option)"
          >
            <template #prepend>
              <VIcon :icon="option.icon" size="20" class="me-2" />
            </template>
            <VListItemTitle>{{ option.title }}</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <VSpacer />

        <VSpacer />

      <VBtn
        color="primary"
        prepend-icon="tabler-arrow-back"
        @click="handleBack"
      >
        Volver
      </VBtn>

    </VCardActions>
  </VCard>
</template>
