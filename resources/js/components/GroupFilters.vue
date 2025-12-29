<script setup>
const props = defineProps({
  searchQuery: String,
  isStrictSearch: Boolean,
  loading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:isStrictSearch",
  "clear",
  "add-group",
]);

const handleClear = () => {
  emit("clear");
};
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Producto, Cód. Barra, C. Activo..."
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
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

      <VBtn
        color="success"
        prepend-icon="tabler-plus"
        @click="emit('add-group')"
      >
        Añadir Grupo
      </VBtn>
    </VCardActions>
  </VCard>
</template>
