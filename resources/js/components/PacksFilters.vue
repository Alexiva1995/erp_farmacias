<script setup>
import { computed } from 'vue';

const props = defineProps({
  searchQuery: String,
  idSearchQuery: String,
  loading: { type: Boolean, default: false },
  mode: { type: String, default: "packs" },
  showAddButton: { type: Boolean, default: true },
});

const emit = defineEmits([
  "update:searchQuery",
  "update:idSearchQuery",
  "clear",
  "add-pack",
]);

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit('update:searchQuery', value)
});

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit('update:idSearchQuery', value)
});
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            v-model="idSearchQuery"
            placeholder="Buscar por ID"
            clearable
            :disabled="props.loading"
          />
        </VCol>
        <VCol cols="12" sm="6" md="4">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por Nombre"
            clearable
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
        @click="emit('add-pack')"
        :disabled="props.loading"
      >
        Añadir Pack
      </VBtn>
    </VCardActions>
  </VCard>
</template>
