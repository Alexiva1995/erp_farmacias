<script setup>

const props = defineProps({
  searchQuery: String,
  loading: { type: Boolean, default: false },
  mode: { type: String, default: "products" },
  showAddButton: { type: Boolean, default: true },
});


const emit = defineEmits([
  "update:searchQuery",
  "clear",
  "add-pack",
]);

</script>
<template>
<VCard title="Filtros" class="mb-6">
<VCardText>
     <VRow>
      <VCol cols="12" sm="6" md="4">
          <AppTextField
            :model-value="props.searchQuery"
            placeholder="Buscar por ID, Pack"
            clearable
            @update:model-value="emit('update:searchQuery', $event)"
          />
        </VCol>
     </VRow>
</VCardText>
<VDivider />
 <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')"> Limpiar Filtros</VBtn>
      <VSpacer />
        <VSpacer />

      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-pack')"
      >
        Añadir Pack
      </VBtn>
    </VCardActions>
</VCard>
</template>
