<script setup>
import { computed, onMounted, ref, watch } from "vue";

const props = defineProps({
  searchQuery: String,
  idSearchQuery: String,
  currencyFilter: [Number, String, null],
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "update:currencyFilter",
  "clear",
]);

const currencyOptions = [
  { title: "BS", value: "BS" },
  { title: "USD", value: "USD" },
  { title: "COP", value: "COP" },
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
       <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.idSearchQuery"
            placeholder="Buscar por ID"
            clearable
            @update:model-value="emit('update:idSearchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Identificación, Vendedor"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
         
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.currencyFilter"
            label="Currency"
            :items="currencyOptions"
            clearable
            @update:model-value="emit('update:currencyFilter', $event)"
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
