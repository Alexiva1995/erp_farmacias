<script setup>
const props = defineProps({
  searchQuery: String,
});

const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "sort",
  "add-supplier",
]);

const sortOptions = [
  {
    title: "Deuda mayor",
    icon: "tabler-arrow-up",
    key: "debt",
    order: "desc",
  },
  {
    title: "Deuda menor",
    icon: "tabler-arrow-down",
    key: "debt",
    order: "asc",
  },
  {
    title: "Más Calificación",
    icon: "tabler-plus",
    key: "latestScore.score",
    order: "desc",
  },
  {
    title: "Menos Calificación",
    icon: "tabler-minus",
    key: "latestScore.score",
    order: "asc",
  },
];

const handleSortClick = (option) => {
  emit("sort", { key: option.key, order: option.order });
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Nombre de Proveedor..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
        <VCol cols="12" sm="2" md="6">
          <VRow>
            <VCol cols="6" sm="2" md="3">
              <VBtn color="secondary" variant="outlined" @click="emit('clear')">
                Limpiar Filtros
              </VBtn>
            </VCol>
            <VCol cols="6" sm="2" md="3">
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
            </VCol>
          </VRow>
        </VCol>
        <VCol
          cols="12"
          sm="3"
          md="2"
          class="text-right d-flex align-center justify-end"
        >
          <VBtn
            color="primary"
            variant="text"
            prepend-icon="tabler-plus"
            @click="emit('add-supplier')"
          >
            Añadir Proveedor
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
