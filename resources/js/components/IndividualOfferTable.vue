<script setup>
import { computed } from 'vue';

const props = defineProps({
  productsOffer: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOffer: { type: Number, default: 0 },
  discount: { type: Number, default: 0 },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(["update:options", "edit-offer", "delete-offer"]);

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Producto", key: "product.name", sortable: true, width: "35%" },
  { title: "% Descuento", key: "discount_percent", sortable: true },
  { title: "Precio normal", key: "sale_price", sortable: false, align: "end" },
  { title: "Precio descuento", key: "discount_price", sortable: false, align: "end" },
  { title: "Fecha Inicio", key: "start_date", sortable: true },
  { title: "Fecha Final", key: "end_date", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
];
</script>

<template>
  <VCard>
    <VDataTableServer
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.productsOffer"
      :items-length="props.totalOffer"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => $emit('update:options', options)"
    >
      <template #item.product.name="{ item }">
        <div class="d-flex flex-column">
          <span class="text-body-1">{{ item.product?.name || 'N/A' }}</span>
          <span class="text-caption text-disabled">ID: {{ item.product?.id || 'N/A' }}</span>
        </div>
      </template>
      
      <template #item.discount_percent="{ item }">
        {{ item.discount_percent }}%
      </template>

      <template #item.sale_price="{ item }">
        <span class="text-body-2 text-medium-emphasis text-decoration-line-through">${{ (parseFloat(item.product?.sale_price) || 0).toFixed(2) }}</span>
      </template>

      <template #item.discount_price="{ item }">
        <span class="text-body-1 font-weight-medium text-success">${{ ((parseFloat(item.product?.sale_price) || 0) * (1 - (parseFloat(item.discount_percent) || 0) / 100)).toFixed(2) }}</span>
      </template>
      
      <template #item.start_date="{ item }">
        {{ new Date(item.start_date).toLocaleDateString() }}
      </template>
      
      <template #item.end_date="{ item }">
        {{ new Date(item.end_date).toLocaleDateString() }}
      </template>
      
      <template #item.actions="{ item }">
        <IconBtn @click="$emit('edit-offer', item)">
          <VIcon icon="tabler-edit" />
        </IconBtn>
        <IconBtn @click="$emit('delete-offer', item.id)">
          <VIcon icon="tabler-trash" />
        </IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
