<script setup>
import { computed } from "vue";

const props = defineProps({
  idSearchQuery: { type: String, default: "" },
  searchQuery: { type: String, default: "" },
  addOfferLoading: { type: Boolean, default: false },
});

const emit = defineEmits([
  "update:idSearchQuery",
  "update:searchQuery",
  "clear",
  "add-product",
]);

const idSearchQuery = computed({
  get: () => props.idSearchQuery,
  set: (value) => emit("update:idSearchQuery", value),
});

const searchQuery = computed({
  get: () => props.searchQuery,
  set: (value) => emit("update:searchQuery", value),
});
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6" md="6">
          <AppTextField
            v-model="idSearchQuery"
            placeholder="Buscar por ID"
            clearable
            :disabled="props.addOfferLoading"
          />
        </VCol>
        <VCol cols="12" sm="6" md="6">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por Nombre"
            clearable
            :disabled="props.addOfferLoading"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn
        color="secondary"
        variant="outlined"
        :disabled="props.addOfferLoading"
        @click="emit('clear')"
      >
        Limpiar Filtros
      </VBtn>
      <VSpacer />
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        :loading="props.addOfferLoading"
        :disabled="props.addOfferLoading"
        @click="emit('add-product')"
      >
        Añadir Oferta
      </VBtn>
    </VCardActions>
  </VCard>
</template>
