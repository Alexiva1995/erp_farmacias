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

const emit = defineEmits(["update:options", "pay-employee", "fire-employee"]);
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.employees"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.actions="{ item }">
        <VTooltip text="Pagar a empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('pay-employee', item)">
              <VIcon icon="tabler-currency-dollar" />
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
      </template>
    </VDataTableServer>
  </VCard>
</template>
