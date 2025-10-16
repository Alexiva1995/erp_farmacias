<script setup>
const props = defineProps({
  myActivities: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "update-status"]);

const headers = [
  { title: "Actividad", key: "activity_name", sortable: true, width: "30%" },
  { title: "Descripción", key: "description", sortable: false, width: "25%" },
  { title: "Frecuencia", key: "frequency", sortable: true },
  { title: "Estado", key: "status", sortable: true },
  { title: "Fecha Asignación", key: "assigned_date", sortable: true },
  { title: "Fecha Completado", key: "completed_date", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];

const getStatusColor = (status) => {
  const statusColors = {
    Pendiente: "warning",
    Completada: "success",
    Cancelada: "error",
  };
  return statusColors[status] || "default";
};

const getStatusIcon = (status) => {
  const statusIcons = {
    Pendiente: "tabler-clock",
    Completada: "tabler-check",
    Cancelada: "tabler-x",
  };
  return statusIcons[status] || "tabler-circle";
};

const getFrequencyColor = (frequency) => {
  const colors = {
    Diaria: "error",
    Semanal: "warning",
    Quincenal: "info",
    Mensual: "success",
  };
  return colors[frequency] || "default";
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("es-ES", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.myActivities"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.activity_name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" size="38" variant="tonal">
            <VIcon icon="tabler-checkbox" size="20" />
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.activity_name }}
            </span>
          </div>
        </div>
      </template>

      <template #item.description="{ item }">
        <span class="text-sm text-medium-emphasis">
          {{ item.description || "Sin descripción" }}
        </span>
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

      <template #item.status="{ item }">
        <VChip
          :color="getStatusColor(item.status)"
          size="small"
          variant="tonal"
        >
          <VIcon :icon="getStatusIcon(item.status)" size="14" class="me-1" />
          {{ item.status }}
        </VChip>
      </template>

      <template #item.assigned_date="{ item }">
        <span class="text-sm">
          {{ formatDate(item.assigned_date) }}
        </span>
      </template>

      <template #item.completed_date="{ item }">
        <span class="text-sm">
          {{ formatDate(item.completed_date) }}
        </span>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn @click="emit('update-status', item)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">
              Cambiar Estado
            </VTooltip>
          </IconBtn>
        </div>
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            @update:model-value="
              (newPage) => emit('update:options', { ...props, page: newPage })
            "
          />
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
