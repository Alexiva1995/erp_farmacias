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
  <VCard class="mb-6 p-0">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4">
          <VSelect
            :model-value="props.status"
            label="Estado"
            :items="options"
            clearable
            @update:model-value="emit('update:status', $event)"
          />
        </VCol>
        <VCol cols="12" md="8" class="d-flex justify-end">
          <VBtn color="secondary" variant="outlined" @click="emit('clear')">
            Limpiar Filtros
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>
</template>
