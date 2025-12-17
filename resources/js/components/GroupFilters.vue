<script setup>
const props = defineProps({
  searchQuery: String,
  // selectedLaboratory: [Number, String, null],
  // selectedOrigin: [Number, String, null],
  // laboratories: { type: Array, default: () => [] },
  // origins: { type: Array, default: () => [] },
  // stockStatusFilter: [Boolean, null],
  isStrictSearch: Boolean,
});

const emit = defineEmits([
  "update:searchQuery",
  // "update:selectedLaboratory",
  // "update:selectedOrigin",
  // "update:stockStatusFilter",
  "update:isStrictSearch",
  "clear",
  "add-group",
]);

const stockOptions = [
  { title: "Con Stock", value: true },
  { title: "Sin Stock", value: false },
  { title: "Todos", value: null },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="8" class="mt-0">
          <VRow class="ga-2 align-center">
            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
              clearable
              @update:model-value="emit('update:searchQuery', $event)"
            />
            <VCheckbox
              label="Búsqueda Estricta"
              :model-value="props.isStrictSearch"
              @update:model-value="emit('update:isStrictSearch', $event)"
            />
          </VRow>
        </VCol>
        <VCol cols="12" sm="2" class="mt-1">
          <VBtn color="secondary" variant="outlined" @click="$emit('clear')">
            Limpiar Filtro
          </VBtn>
        </VCol>
        <VCol cols="12" sm="2" class="mt-1">
          <VBtn
            color="primary"
            prepend-icon="tabler-plus"
            @click="$emit('add-group')"
          >
            Añadir Grupo
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
