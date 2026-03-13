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
  <VCard class="mb-6 overflow-visible" variant="flat" border>
    <VCardText class="pa-3">
      <VRow align="center" no-gutters class="gap-2">
        <!-- Buscador Principal -->
        <VCol cols="12" md="4" lg="5">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por Nombre, Descripción..."
            prepend-inner-icon="tabler-search"
            clearable
            density="compact"
            persistent-placeholder
            hide-details
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>

        <!-- Búsqueda Estricta -->
        <VCol cols="auto" class="d-none d-sm-flex">
          <VCheckbox
            :model-value="props.isStrictSearch"
            label="Estricta"
            color="primary"
            density="compact"
            hide-details
            @update:model-value="emit('update:isStrictSearch', $event)"
          />
        </VCol>

        <VSpacer />

        <div class="d-flex align-center gap-1">
          <!-- Añadir Grupo -->
          <VBtn
            icon
            color="success"
            variant="tonal"
            size="38"
            @click="emit('add-group')"
          >
            <VIcon icon="tabler-plus" />
            <VTooltip activator="parent" location="top">Añadir Grupo</VTooltip>
          </VBtn>

          <VDivider vertical class="mx-1 my-2" />

          <!-- Limpiar Filtros -->
          <VBtn
            icon
            variant="text"
            color="secondary"
            size="38"
            @click="handleClear"
          >
            <VIcon icon="tabler-eraser" />
            <VTooltip activator="parent" location="top">Limpiar Filtros</VTooltip>
          </VBtn>
        </div>
      </VRow>
    </VCardText>
  </VCard>
</template>

<style scoped>
.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
</style>
