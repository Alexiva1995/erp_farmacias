<script setup>
const props = defineProps({
  doctorsOffer: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  totaldoctors: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit", "view", "delete"]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Médico", key: "doctor_name", sortable: true, width: "25%" },
  {
    title: "% Descuento",
    key: "discount",
    sortable: true,
    width: "120px",
  },
  // { title: "Vol Min", key: "min_volume", sortable: true, width: "100px" },
  // { title: "Vol Max", key: "max_volume", sortable: true, width: "100px" },
  { title: "Fecha Inicio", key: "start_date", sortable: true, width: "120px" },
  { title: "Fecha Final", key: "end_date", sortable: true, width: "120px" },
  { title: "Estatus", key: "is_active", sortable: true, width: "100px" },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];

const formatDate = (dateString) => {
  if (!dateString) return "N/A";

  return new Date(dateString).toLocaleDateString("es-ES");
};

const getStatusBadge = (isActive) => {
  return isActive ? "Activa" : "Inactiva";
};

const getStatusColor = (isActive) => {
  return isActive ? "success" : "error";
};

const getDiscountPercentage = (scales) => {
  if (!scales || scales.length === 0) return "N/A";

  if (scales.length === 1) return `${scales[0].discount_percentage}%`;
  const min = Math.min(...scales.map((s) => s.discount_percentage));
  const max = Math.max(...scales.map((s) => s.discount_percentage));

  return `${min}% - ${max}%`;
};

const getVolumeRange = (scales) => {
  if (!scales || scales.length === 0) return { min: "N/A", max: "N/A" };
  const min = Math.min(...scales.map((s) => s.min_volume));
  const max = Math.max(...scales.map((s) => s.max_volume));

  return { min, max };
};

const handleView = (doctorOffer) => {
  emit("view", doctorOffer);
};

const handleEdit = (doctorOffer) => {
  emit("edit", doctorOffer);
};

const handleDelete = (doctorOffer) => {
  emit("delete", doctorOffer);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.doctorsOffer"
      :items-length="props.totaldoctors"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.doctor_name="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{
            item.doctor?.name || "N/A"
          }}</span>
          <span class="text-caption text-disabled"
            >ID: {{ item.doctor_id }}</span
          >
        </div>
      </template>

      <template #item.discount="{ item }">
        <VChip size="small" color="primary" variant="flat">
          {{ item.discount }}%
        </VChip>
      </template>

      <!-- <template #item.min_volume="{ item }">
        {{ getVolumeRange(item.scales).min }}
      </template>

      <template #item.max_volume="{ item }">
        {{ getVolumeRange(item.scales).max }}
      </template> -->

      <template #item.start_date="{ item }">
        {{ formatDate(item.start_date) }}
      </template>

      <template #item.end_date="{ item }">
        {{ formatDate(item.end_date) }}
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="getStatusColor(item.is_active)"
          size="small"
          variant="flat"
        >
          {{ getStatusBadge(item.is_active) }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center">
          <VBtn
            icon
            size="small"
            color="info"
            variant="text"
            @click="handleView(item)"
          >
            <VIcon icon="tabler-eye" size="20" />
          </VBtn>

          <VBtn
            icon
            size="small"
            color="primary"
            variant="text"
            @click="handleEdit(item)"
          >
            <VIcon icon="tabler-edit" size="20" />
          </VBtn>

          <VBtn
            icon
            size="small"
            color="error"
            variant="text"
            @click="handleDelete(item)"
          >
            <VIcon icon="tabler-trash" size="20" />
          </VBtn>
        </div>
      </template>

      <template #loading>
        <VSkeletonLoader type="table-row@10" />
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
.gap-1 {
  gap: 4px;
}
</style>
