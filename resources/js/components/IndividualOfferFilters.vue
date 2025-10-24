<script setup>
const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery", 
  "update:searchQuery", 
  "clear", 
  "add-product"
]);

const onIdSearchInput = (value) => {
  emit("update:idSearchQuery", value);
};

const onSearchInput = (value) => {
  emit("update:searchQuery", value);
};
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.idSearchQuery"
            placeholder="Buscar por ID de Oferta"
            clearable
            @update:model-value="onIdSearchInput"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID ó Nombre de Producto"
            clearable
            @update:model-value="onSearchInput"
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
        @click="$emit('add-product')"
      >
        Añadir Oferta
      </VBtn>
    </VCardActions>
  </VCard>
</template>
