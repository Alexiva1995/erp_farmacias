<script setup>
const props = defineProps({
  employees: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: false },
  { title: "Nombre", key: "name", sortable: false },
  { title: "Apellido", key: "last_name", sortable: false },
  { title: "Identificación", key: "identification", sortable: false },
  { title: "Correo", key: "email", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits([
  "update:options",
  "fire-employee",
  "edit-employee",
  "delete-employee",
  "generate-resignation",
  "reset-2fa",
]);
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.employees"
      :items-length="props.total"
      :loading="loading"
      :loading-text="'Cargando empleados...'"
      :page="props.page"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.actions="{ item }">
        <VTooltip text="Editar empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('edit-employee', item)">
              <VIcon icon="tabler-pencil" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Ver empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" icon :href="'/rrhh/employees/' + item.id">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Generar Renuncia" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="emit('generate-resignation', item)"
              color="warning"
            >
              <VIcon icon="tabler-file-text" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Despedir empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('fire-employee', item)">
              <VIcon icon="tabler-cancel" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Reiniciar autenticación" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('reset-2fa', item.id)">
              <VIcon icon="tabler-auth-2fa" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Eliminar empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('delete-employee', item.id)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
