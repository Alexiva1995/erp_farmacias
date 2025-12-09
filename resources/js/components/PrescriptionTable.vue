<script setup>
const props = defineProps({
  prescriptions: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalPrescriptions: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-prescription",
  "delete-prescription",
  "view-prescription",
]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Nombre", key: "name", sortable: true, width: "120px" },
  {
    title: "Descuento %",
    key: "discount_percentage",
    sortable: true,
    width: "120px",
  },
  // { title: "Productos", key: "products_count", sortable: true, width: "100px" },
  // { title: "Costo Total", key: "total_cost", sortable: true, width: "120px" },
  { title: "Fecha Inicio", key: "start_date", sortable: true, width: "120px" },
  { title: "Fecha Fin", key: "end_date", sortable: true, width: "120px" },
  { title: "Estado", key: "is_active", sortable: true, width: "100px" },
  {
    title: "Vigente",
    key: "is_currently_active",
    sortable: true,
    width: "140px",
  },
  {
    title: "Acciones",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];

const formatDate = (date) => {
  if (!date) return "-";
  return new Date(date).toLocaleDateString("es-ES");
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat("es-ES", {
    style: "currency",
    currency: "USD",
  }).format(amount || 0);
};

const handleEdit = (prescription) => {
  emit("edit-prescription", prescription);
};

const handleDelete = (prescription) => {
  emit("delete-prescription", prescription);
};

const handleView = (prescription) => {
  emit("view-prescription", prescription);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.prescriptions"
      :items-length="props.totalPrescriptions"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.name="{ item }">
        {{ item.name }}
      </template>
      <template #item.discount_percentage="{ item }">
        <VChip variant="outlined" color="primary" size="small">
          {{ item.discount_percentage }}%
        </VChip>
      </template>

      <!-- <template #item.products_count="{ item }">
        <VChip variant="outlined" color="info" size="small">
          {{ item.products_count || 0 }}
        </VChip>
      </template>

      <template #item.total_cost="{ item }">
        <span class="font-weight-bold">
          {{ formatCurrency(item.total_cost) }}
        </span>
      </template> -->

      <template #item.start_date="{ item }">
        <span class="text-caption">
          {{ formatDate(item.start_date) }}
        </span>
      </template>

      <template #item.end_date="{ item }">
        <span class="text-caption">
          {{ formatDate(item.end_date) }}
        </span>
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="item.is_active ? 'success' : 'error'"
          variant="flat"
          size="small"
        >
          {{ item.is_active ? "Activo" : "Inactivo" }}
        </VChip>
      </template>

      <template #item.is_currently_active="{ item }">
        <VChip
          :color="item.is_currently_active ? 'success' : 'warning'"
          variant="flat"
          size="small"
        >
          {{ item.is_currently_active ? "Sí" : "No" }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          color="info"
          @click="handleView(item)"
        >
          <VIcon>tabler-eye</VIcon>
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="primary"
          @click="handleEdit(item)"
        >
          <VIcon>tabler-edit</VIcon>
        </VBtn>

        <VBtn
          icon
          variant="text"
          size="small"
          color="error"
          @click="handleDelete(item)"
        >
          <VIcon>tabler-trash</VIcon>
        </VBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
