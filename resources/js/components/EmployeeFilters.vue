<script setup>
const props = defineProps({
  search: { type: String, default: "" },
  showActiveEmployees: { type: Boolean, default: true },
});

const emit = defineEmits(["update:search", "clear", "add-employee"]);

const options = [
  {
    title: "Activos",
    value: true,
    active: true,
  },
  {
    title: "Despedidos",
    value: false,
  },
];
</script>

<template>
  <VCard title="Filtros" class="mb-6">
    <VCardText>
      <VRow>
        <VCol cols="12" sm="4">
          <AppTextField
            :model-value="props.search"
            placeholder="Buscar por nombre, apellido, cédula o correo"
            clearable
            @update:model-value="emit('update:search', $event)"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <VSelect
            label="Filtrar"
            variant="outlined"
            hide-details="auto"
            :items="options"
            :model-value="props.showActiveEmployees"
            @update:model-value="emit('update:showActiveEmployees', $event)"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined" @click="emit('clear')">
        Limpiar Filtros
      </VBtn>
      <VSpacer />
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        @click="emit('add-employee')"
      >
        Agregar Empleado
      </VBtn>
    </VCardActions>
  </VCard>
</template>
