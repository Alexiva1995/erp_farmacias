<script setup>
const props = defineProps({
  status: String,
});

const emit = defineEmits(["update:status", "clear"]);
const options = [
  { value: 0, title: "Pendiente" },
  { value: 1, title: "Parcial" },
  { value: 2, title: "Completo" },
];
</script>

<template>
  <VCard class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12">
          <VSelect
            :model-value="props.status"
            label="Estado"
            :items="options"
            clearable
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 px-6 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
      <VSpacer />
    </VCardActions>
  </VCard>
</template>
