<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  companies: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  totalCompanies: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

const headers = [
  { title: "ID", key: "id", sortable: true, width: "80px" },
  { title: "Empresa", key: "company_name", sortable: true, width: "25%" },
  {
    title: "% Descuento",
    key: "discount_percentage",
    sortable: true,
    width: "120px",
  },
  { title: "Monto Min", key: "min_amount", sortable: true, width: "100px" },
  { title: "Monto Max", key: "max_amount", sortable: true, width: "100px" },
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
  const min = Math.min(...scales.map((s) => s.min_amount));
  const max = Math.max(...scales.map((s) => s.max_amount));

  return { min, max };
};

const handleView = (company) => {
  emit('view-offer', company);
};

const handleEdit = (company) => {
  emit("edit-offer", company);
};

const handleDelete = (company) => {
  emit("delete-offer", company);
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.companies"
      :items-length="props.totalCompanies"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.company_name="{ item }">
        <div class="d-flex flex-column">
          <span class="font-weight-medium">{{ item.company_name }}</span>
          <span class="text-caption text-disabled"
            >ID: {{ item.company_id }}</span
          >
        </div>
      </template>

      <template #item.discount_percentage="{ item }">
        <VChip size="small" color="primary" variant="flat">
          {{ getDiscountPercentage(item.scales) }}
        </VChip>
      </template>

      <template #item.min_amount="{ item }">
        {{ formatCurrency(getVolumeRange(item.scales).min, 'USD') }}
      </template>

      <template #item.max_amount="{ item }">
        {{ formatCurrency(getVolumeRange(item.scales).max, 'USD') }}
      </template>

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
