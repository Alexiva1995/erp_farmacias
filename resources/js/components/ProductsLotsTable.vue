<script setup>
const props = defineProps({
  lots: { type: Array, required: true },
  totalLots: { type: Number, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-lot"]);

const headers = [
  { title: "ID", key: "product.id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true },
  { title: "Proveedor", key: "supplier.name", sortable: true },
  { title: "Ubicacion", key: "location", sortable: true },
  { title: "Costo", key: "unit_cost", sortable: true },
  { title: "Unds", key: "quantity", sortable: true },
  { title: "Exp", key: "expiration_date", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];
const formatDate = (dateString) => {
  const options = { year: "numeric", month: "2-digit", day: "2-digit" };
  return new Date(dateString).toLocaleDateString("es-ES", options);
};
</script>

<template>
  <VCard title="Listado de Lotes">
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.lots"
      :items-length="props.totalLots"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.product.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar
            v-if="item.product.photo_url"
            size="38"
            variant="tonal"
            rounded
            :image="item.product.photo_url"
          />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{
              item.product.name
            }}</span>
            <span class="text-sm text-disabled">{{ item.lot_number }} <span class="text-sm text-disabled" v-if=' item.lot_number && item.product.laboratory?.name'>-</span> {{ item.product.laboratory?.name }}</span>   
          </div>
        </div>
      </template>

      <template #item.supplier.name="{ item }">
        <span v-if="item.supplier" class="text-body-1 text-high-emphasis">{{
          item.supplier.name
        }}</span>
        <span v-else class="text-disabled">N/A</span>
      </template>

      <template #item.unit_cost="{ item }">
        <span class="font-weight-medium"
          >${{ parseFloat(item.unit_cost).toFixed(2) }}</span
        >
      </template>
      <template #item.expiration_date="{ item }">
        <span class="font-weight-medium">{{
          formatDate(item.expiration_date)
        }}</span>
      </template>

      <template #item.actions="{ item }">
        <IconBtn @click="emit('edit-lot', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
