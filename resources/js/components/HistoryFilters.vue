<script setup>
const props = defineProps({
  searchQuery: String,
  startDate: [String, null],
  endDate: [String, null],
  origins: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
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
    key: "total_amount",
    order: "desc",
  },
  {
    title: "Precio Menor",
    icon: "tabler-arrow-down",
    key: "total_amount",
    order: "asc",
  },
];

const handleSortClick = (option) => {
  emit("sort", { key: option.key, order: option.order });
};
</script>

<template>
  <VCard class="mb-6">
    <!-- La sección de filtros se mantiene exactamente igual -->
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Razon, N Factura..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
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
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>

      <!-- 👉 AÑADIDO: El nuevo menú para ordenar, colocado junto al botón de limpiar -->
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

      <!-- El resto de los botones se mantiene igual -->
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
    </VCardActions>
  </VCard>
</template>
