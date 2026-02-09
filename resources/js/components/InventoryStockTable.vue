<script setup>
import { computed } from "vue";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  sortBy: { type: [String, Array], default: () => [] },
  orderBy: { type: String, default: "asc" },
});

const sortByModel = computed(() => {
  if (!props.sortBy) return [];
  const key = Array.isArray(props.sortBy) ? props.sortBy[0] : props.sortBy;
  return key ? [{ key, order: props.orderBy || "asc" }] : [];
});

const emit = defineEmits(["update:options"]);

const headers = [
  { title: "id", key: "id", sortable: true },
  { title: "Producto", key: "name", sortable: true, width: "300px" },
  { title: "Laboratorio", key: "laboratory.name", sortable: false },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Ventas", key: "total_sold_completed", sortable: true },
  { title: "Stock", key: "lote_quantity", sortable: true },
  {
    title: "Preferencia",
    key: "preferencia_product",
    sortable: true,
    value: (item) =>
      item.preferencia_product != "" && item.preferencia_product != null
        ? parseFloat(item.preferencia_product).toFixed(2)
        : 0,
  },
  {
    title: "Promedio",
    key: "promedio_calculado",
    sortable: true,
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "AO",
    key: "totalQuantityInAutoOrder",
    sortable: true,
  },
  {
    title: "Diferencia ",
    key: "diferencia_product",
    sortable: true,
  },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.products"
      :items-length="props.totalProduct"
      :loading="props.loading"
      :sort-by="sortByModel"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.name="{ item }">
        <div class="d-flex align-start gap-x-4" style="max-width: 300px; width: 100%;">
          <div class="d-flex flex-column" style="min-width: 0; flex: 1; word-wrap: break-word; overflow-wrap: break-word;">
            <span
              class="text-body-1 font-weight-medium text-high-emphasis"
              :class="{ 'text-primary': item.psychotropic == 1 }"
              style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.4; white-space: normal;"
            >
              {{ item.name.toUpperCase() }}

              <span v-if="item.is_colombian_origin == 1"> (COL)</span>
            </span>

            <span class="text-sm text-disabled" style="word-wrap: break-word; overflow-wrap: break-word; line-height: 1.3; white-space: normal;">{{
              item.active_ingredient
            }}</span>
          </div>
        </div>
      </template>

      <template #item.diferencia_product="{ item }">
        <span
          v-if="item.diferencia_product != null && item.diferencia_product != ''"
          :class="{
            'text-success': parseFloat(item.diferencia_product) > 0,
            'text-error': parseFloat(item.diferencia_product) < 0,
          }"
          class="font-weight-medium"
        >
          {{
            parseFloat(item.diferencia_product) > 0
              ? "+"
              : parseFloat(item.diferencia_product) < 0
              ? "-"
              : ""
          }}{{ Math.ceil(Math.abs(parseFloat(item.diferencia_product))) }}
        </span>
        <span v-else class="text-disabled">0</span>
      </template>
    </VDataTableServer>
  </VCard>
</template>

<style scoped>
:deep(.v-data-table td:nth-child(2)) {
  white-space: normal !important;
  word-wrap: break-word !important;
  overflow-wrap: break-word !important;
  max-width: 300px !important;
  width: 300px !important;
  vertical-align: top !important;
  padding-top: 12px !important;
  padding-bottom: 12px !important;
  overflow: hidden !important;
}

:deep(.v-data-table th:nth-child(2)) {
  max-width: 300px !important;
  width: 300px !important;
  white-space: normal !important;
}

:deep(.v-data-table__wrapper) {
  overflow-x: auto;
}
</style>
