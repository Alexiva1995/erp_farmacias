<script setup>
const props = defineProps({
  activities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalActivities: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-activity",
  "delete-activity",
]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Actividad", key: "activity", sortable: true, width: "30%" },
  { title: "Descripción", key: "description", sortable: false, width: "40%" },
  { title: "Frecuencia", key: "frequency", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getFrequencyColor = (frequency) => {
  const colors = {
    Diaria: "error",
    Semanal: "warning",
    Bimestral: "info",
    Mensual: "primary",
    Trimestral: "secondary",
    Semestral: "success",
    Anual: "default",
  };
  return colors[frequency] || "default";
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.activities"
      :items-length="props.totalActivities"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-medium">{{ item.id }}</span>
      </template>

      <template #item.activity="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1 font-weight-medium text-high-emphasis">
            {{ item.activity }}
          </span>
        </div>
      </template>

      <template #item.description="{ item }">
        <span class="text-sm text-medium-emphasis">{{ item.description }}</span>
      </template>

      <template #item.frequency="{ item }">
        <VChip
          :color="getFrequencyColor(item.frequency)"
          size="small"
          variant="tonal"
        >
          {{ item.frequency }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <IconBtn @click="emit('edit-activity', item)" color="warning">
          <VIcon icon="tabler-edit" />
          <VTooltip activator="parent" location="top">Editar</VTooltip>
        </IconBtn>
        <IconBtn @click="emit('delete-activity', item.id)" color="error">
          <VIcon icon="tabler-trash" />
          <VTooltip activator="parent" location="top">Eliminar</VTooltip>
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
