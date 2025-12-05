<script setup>
const props = defineProps({
  searchQuery: { type: String, required: true },
  loading: { type: Boolean, default: false },
  addOfferLoading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits(["update:searchQuery", "clear", "add-doctors"]);

const handleSearchUpdate = (value) => {
  emit("update:searchQuery", value);
};
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID ó Nombre de Médico"
            clearable
            @update:model-value="handleSearchUpdate"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />
    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn 
        color="secondary" 
        variant="outlined" 
        @click="emit('clear')"
        :disabled="props.loading"
      >
        Limpiar Filtro
      </VBtn>
      
      <VSpacer />
      
      <VBtn
        v-if="props.showAddButton"
        color="primary"
        prepend-icon="tabler-plus"
        :loading="props.addOfferLoading"
        :disabled="props.addOfferLoading"
        @click="emit('add-doctors')"
      >
        Añadir Oferta
      </VBtn>
    </VCardActions>
  </VCard>
</template>
