<script setup>
const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  isActive: { type: [String, Number], default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery", 
  "update:searchQuery", 
  "update:isActive",
  "clear", 
  "add-categories"
]);

const onIdSearchInput = (value) => {
  emit("update:idSearchQuery", value);
};

const onSearchInput = (value) => {
  emit("update:searchQuery", value);
};

const onIsActiveChange = (value) => {
  emit("update:isActive", value);
};
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.idSearchQuery"
            placeholder="Buscar por ID de Oferta"
            clearable
            @update:model-value="onIdSearchInput"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID ó Nombre de Categoría"
            clearable
            @update:model-value="onSearchInput"
          />
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VSelect
            :model-value="props.isActive"
            :items="[
              { value: '', title: 'Todos los estados' },
              { value: 1, title: 'Activas' },
              { value: 0, title: 'Inactivas' },
            ]"
            item-title="title"
            item-value="value"
            label="Filtrar por estado"
            clearable
            @update:model-value="onIsActiveChange"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="$emit('clear')">
        Limpiar Filtros
      </VBtn>
      <VSpacer />
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        :loading="props.addOfferLoading"
        :disabled="props.addOfferLoading"
        @click="$emit('add-categories')"
      >
        Añadir Oferta de Categoría
      </VBtn>
    </VCardActions>
  </VCard>
</template>
