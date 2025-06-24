<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'edit-product']);

const headers = [
  { title: 'Unidades', key: 'quantity', sortable: true },
  { title: 'Código', key: 'id', sortable: true },
  { title: 'Código de Barra', key: 'barcode', sortable: true },
  { title: 'Producto', key: 'name'},
  { title: 'Precio en USD', key: 'sale_price', sortable: false },
  { title: 'Precio en Bs', key: 'bs_price' },
  { title: 'Precio en COP', key: 'cop_price' },
  { title: 'Añadir', key: 'añadir', sortable: false },
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
      class="text-no-wrap"
      @update:options="options => emit('update:options', options)"
    >
      <template #item.quantity="{ item }"><span class="font-weight-medium">{{ item.quantity }}</span></template>
      <template #item.id="{ item }"><span class="font-weight-medium">{{ item.id }}</span></template>
      <template #item.barcode="{ item }"><span class="font-weight-medium">{{ item.barcode }}</span></template>    
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar v-if="item.photo_url" size="38" variant="tonal" rounded :image="item.photo_url" />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
            <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
          </div>
        </div>
      </template>
      
      <template #item.sale_price="{ item }"><span class="font-weight-medium">${{ item.sale_price }}</span></template>
      <template #item.bs_price="{ item }"><span class="font-weight-medium">${{ item.bs_price }}</span></template>
      <template #item.cop_price="{ item }"><span class="font-weight-medium">${{ item.cop_price }}</span></template>
      <template #item.añadir="{ item }">
        <IconBtn @click="emit('edit-product', item)"><VIcon icon="tabler-edit" /></IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
