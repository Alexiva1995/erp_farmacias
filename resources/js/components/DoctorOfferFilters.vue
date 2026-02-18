<script setup>
defineProps({
  addOfferLoading: { type: Boolean, default: false },
  showAddButton: { type: Boolean, default: true },
});

defineEmits(["clear", "add-doctors"]);

const idSearchQuery = defineModel("idSearchQuery");
const searchQuery = defineModel("searchQuery");
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="6">
          <AppTextField
            v-model="idSearchQuery"
            placeholder="Buscar por ID de médico..."
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por nombre de médico..."
            clearable
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
      <VSpacer />

      <VBtn
        v-if="showAddButton"
        color="primary"
        prepend-icon="tabler-plus"
        :loading="addOfferLoading"
        :disabled="addOfferLoading"
        @click="$emit('add-doctors')"
      >
        Añadir Oferta por Médico
      </VBtn>
    </VCardActions>
  </VCard>
</template>
