<script setup>
const props = defineProps({
  searchQuery: String,
  offer: String,
  idSearchQuery: String,
  currencyFilter: [Number, String, null],
  sellerFilter: [Number, String, null],
  sellers: {
    type: Array,
    default: () => [],
  },
  startDate: {
    type: String,
    default: null,
  },
  endDate: {
    type: String,
    default: null,
  },
  showDateFilters: {
    type: Boolean,
    default: false,
  },
  showStateFilters: {
    type: Boolean,
    default: false,
  },
  stateFilter: [Number, String, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "update:currencyFilter",
  "update:sellerFilter",
  "clear",
  "update:startDate",
  "update:endDate",
  "update:stateFilter",
  "update:offer",
]);

const currencyOptions = [
  { title: "BS", value: "BS" },
  { title: "USD", value: "USD" },
  { title: "COP", value: "COP" },
];

const stateOptions = [
  { title: "Completada", value: "Completed" },
  { title: "Abandonada", value: "Abandoned" },
  { title: "Cancelada", value: "Cancelled" },
];

const offerOptions = [
  { title: "Empresa", value: "company_offer" },
  { title: "Médico", value: "doctor_offer" },
  { title: "Recipe", value: "preescription_offer" },
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
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="6" sm="4" md="2" lg="2">
          <AppTextField
            :model-value="props.idSearchQuery"
            placeholder="ID"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:idSearchQuery', $event)"
          />
        </VCol>
        <VCol cols="6" sm="4" md="2" lg="2">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Identificación, Vendedor"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="6" sm="4" md="2" lg="2">
          <VSelect
            :model-value="props.currencyFilter"
            label="Moneda"
            :items="currencyOptions"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:currencyFilter', $event)"
          />
        </VCol>
        <VCol cols="6" sm="4" md="2" lg="2">
          <VSelect
            :model-value="props.sellerFilter"
            label="Vendedor"
            :items="props.sellers"
            item-title="username"
            item-value="id"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:sellerFilter', $event)"
          />
        </VCol>
        <VCol cols="6" sm="4" md="2" lg="2">
          <VSelect
            :model-value="props.offer"
            label="Descuentos"
            :items="offerOptions"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:offer', $event)"
          />
        </VCol>
        <VCol v-if="props.showStateFilters" cols="6" sm="4" md="2" lg="2">
          <VSelect
            :model-value="props.stateFilter"
            label="Estado"
            :items="stateOptions"
            clearable
            density="compact"
            hide-details
            @update:model-value="emit('update:stateFilter', $event)"
          />
        </VCol>
        <VCol v-if="props.showDateFilters" cols="6" sm="4" md="2" lg="2">
          <AppDateTimePicker
            :model-value="props.startDate"
            placeholder="Desde"
            clearable
            density="compact"
            hide-details
            :config="{
              altInput: true,
              altFormat: 'Y-m-d',
              dateFormat: 'Y-m-d',
            }"
            @update:model-value="emit('update:startDate', $event)"
          />
        </VCol>
        <VCol v-if="props.showDateFilters" cols="6" sm="4" md="2" lg="2">
          <AppDateTimePicker
            :model-value="props.endDate"
            placeholder="Hasta"
            clearable
            density="compact"
            hide-details
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

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
    </VCardActions>
  </VCard>
</template>
