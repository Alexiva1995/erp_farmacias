<script setup>
defineProps({
  addOfferLoading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["clear", "add-companies"]);

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
            placeholder="Buscar por ID de empresa..."
            clearable
          />
        </VCol>

        <VCol cols="12" sm="6">
          <AppTextField
            v-model="searchQuery"
            placeholder="Buscar por nombre de empresa..."
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
            color="primary"
            prepend-icon="tabler-plus"
            :loading="addOfferLoading"
            :disabled="addOfferLoading"
            @click="$emit('add-companies')"
          >
            Añadir Oferta por Empresa
          </VBtn>
        </VCardActions>
  </VCard>
</template>
