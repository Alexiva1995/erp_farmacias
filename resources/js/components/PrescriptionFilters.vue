<script setup>
const props = defineProps({
  searchQuery: String,
  idSearchQuery: String,
  mode: String,
  loading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "update:mode",
  "clear",
  "add-prescription",
]);
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <VTextField
            :model-value="idSearchQuery"
            @update:model-value="(value) => emit('update:idSearchQuery', value)"
            placeholder="Buscar por ID"
            variant="outlined"
            clearable
            :disabled="props.loading"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <VSelect
            :model-value="mode"
            @update:model-value="(value) => emit('update:mode', value)"
            :items="[
              { title: 'Todas las ofertas', value: 'all' },
              { title: 'Ofertas activas', value: 'active' },
              { title: 'Ofertas inactivas', value: 'inactive' },
            ]"
            item-title="title"
            item-value="value"
            label="Filtrar por estado"
            variant="outlined"
            :disabled="props.loading"
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
        Limpiar Filtros
      </VBtn>
      <VSpacer />
      <VBtn
        v-if="props.showAddButton"
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-prescription')"
        :disabled="props.loading"
      >
        Añadir Oferta de Receta
      </VBtn>
    </VCardActions>
  </VCard>
</template>