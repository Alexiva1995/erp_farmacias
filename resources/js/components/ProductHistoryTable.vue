<script setup>
import { ref } from "vue";

const props = defineProps({
  products: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  totalProduct: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    default: 10,
  },
  page: {
    type: Number,
    default: 1,
  },
});

const emit = defineEmits(["update:options"]);

const headers = ref([
  { title: "Producto", key: "product.name", sortable: true },
  {
    title: "Stock Sistema",
    key: "system_quantity",
    align: "center",
    sortable: true,
  },
  {
    title: "Conteo Final",
    key: "final_quantity",
    align: "center",
    sortable: true,
  },
  {
    title: "Discrepancia",
    key: "discrepancy",
    align: "center",
    sortable: true,
  },
  { title: "Estado", key: "status", align: "center", sortable: true },
  {
    title: "Procesado Por",
    key: "supervisor.email",
    align: "center",
    sortable: false,
  },
  {
    title: "Fecha Proceso",
    key: "updated_at",
    align: "center",
    sortable: true,
  },
]);

const formatDate = (dateString) => {
  if (!dateString) return "N/A";
  try {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, "0");
    const day = date.getDate().toString().padStart(2, "0");

    return `${year}-${month}-${day}`;
  } catch (error) {
    return "N/A";
  }
};

const getStatusColor = (status) => {
  if (status === "confirmed") return "success";
  if (status === "rejected") return "error";
  return "grey";
};

const getStatusText = (status) => {
  if (status === "confirmed") return "Confirmado";
  if (status === "rejected") return "Rechazado";
  return "Pendiente";
};

const updateOptions = (options) => {
  emit("update:options", options);
};
</script>

<template>
  <VCard class="mt-4">
    <VCardTitle>Historial de Conteos Cíclicos</VCardTitle>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="updateOptions"
      item-value="id"
      hover
    >
      <template #item.product.name="{ item: count }">
        <div class="d-flex align-center gap-x-4 py-2">
          <VAvatar
            v-if="count.product.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="count.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': count.product.psychotropic == 1 }"
            >
              {{ count.product.name }}
              <span v-if="count.product.iva == 1"> (G)</span>
              <span v-if="count.product.is_colombian_origin == 1"> (COL)</span>
            </span>
            <span class="text-sm text-disabled">
              {{ count.product.active_ingredient }}
            </span>
          </div>
        </div>
      </template>

      <template #item.final_quantity="{ item: count }">
        <span class="font-weight-medium">
          {{ count.final_quantity ?? count.counted_quantity }}
        </span>
      </template>

      <template #item.discrepancy="{ item: count }">
        <span
          v-if="count.discrepancy !== null"
          :class="{
            'text-success': count.discrepancy > 0,
            'text-error': count.discrepancy < 0,
            'text-medium-emphasis': count.discrepancy === 0,
          }"
          class="font-weight-bold"
        >
          {{
            count.discrepancy > 0 ? `+${count.discrepancy}` : count.discrepancy
          }}
        </span>
        <span v-else class="text-disabled">N/A</span>
      </template>

      <template #item.status="{ item: count }">
        <VChip :color="getStatusColor(count.status)" size="small" label>
          {{ getStatusText(count.status) }}
        </VChip>
      </template>

      <template #item.updated_at="{ item: count }">
        <span>{{ formatDate(count.updated_at) }}</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>
