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
  <VCard :class="{ 'mb-6': true, 'elevation-0': false }">
    <VCardText>
      <VRow class="align-center">
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            v-model="idSearchQuery"
            placeholder="Buscar por ID"
            clearable
            :disabled="props.loading"
            density="compact"
          />
        </VCol>
        <VCol cols="12" sm="3" md="2">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por Nombre"
            clearable
            :disabled="props.loading"
            density="compact"
          />
        </VCol>
        <VCol cols="12" sm="auto" md="auto" class="d-flex align-center gap-2">
          <VBtn 
            color="secondary" 
            variant="outlined" 
            size="small"
            @click="emit('clear')"
            :disabled="props.loading"
          >
            Limpiar Filtros
          </VBtn>
          <VBtn
            v-if="props.showAddButton"
            color="primary"
            prepend-icon="tabler-plus"
            size="small"
            @click="emit('add-pack')"
            :disabled="props.loading"
          >
            Añadir Pack
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
