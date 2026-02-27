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
  { title: "Liquidación", key: "settlement_date", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits([
  "update:options",
  "fire-employee",
  "download-settlement",
  "upload-signed",
  "download-signed",
]);

// Función para formatear fechas
const formatDate = (dateString) => {
  if (!dateString) return "-";
  return new Date(dateString).toLocaleDateString("es-VE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  });
};
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
      <template #item.settlement_date="{ item }">
        <span class="text-caption font-weight-bold" :class="item.settlement_date ? 'text-success' : 'text-medium-emphasis'">
          {{ formatDate(item.settlement_date) }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <VTooltip v-if="!item.settlement_date" text="Procesar Liquidación" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('fire-employee', item)">
              <VIcon icon="tabler-file-analytics" color="primary" />
            </IconBtn>
          </template>
        </VTooltip>

        <div v-else class="d-flex gap-1">
          <VTooltip text="Descargar Liquidación Generada" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('download-settlement', item)">
                <VIcon icon="tabler-file-type-pdf" color="primary" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Subir Liquidación Firmada" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('upload-signed', item)">
                <VIcon icon="tabler-cloud-upload" color="warning" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip v-if="item.signed_document_path" text="Descargar Liquidación Firmada" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="emit('download-signed', item)">
                <VIcon icon="tabler-download" color="success" />
              </IconBtn>
            </template>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
