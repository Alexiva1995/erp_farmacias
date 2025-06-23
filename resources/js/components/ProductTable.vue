<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'edit-product', 'delete-product']);

const headers = [
  { title: 'id', key: 'id'},
  { title: 'Producto', key: 'name' },
  { title: 'Laboratorio', key: 'laboratory.name', sortable: true },
  { title: 'Stock', key: 'valid_stock', sortable: false },
  { title: 'Exp.', key: 'next_expiration', sortable: false },
  // { title: 'Laboratorio', key: 'laboratory.name' },
  // { title: 'Origen', key: 'origin.name' },
  { title: 'Precio Compra', key: 'cost_price' },
  { title: 'Precio Venta', key: 'sale_price' },
  { title: 'Acciones', key: 'actions', sortable: false },
];

const calculateValidStock = product => {
  if (!product.lots || !Array.isArray(product.lots)) return 0;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return product.lots
    .filter(lot => lot.expiration_date && new Date(lot.expiration_date) >= today)
    .reduce((sum, lot) => sum + Number(lot.quantity || 0), 0);
};

const nextExpirationDate = product => {
  if (!product.lots || !Array.isArray(product.lots) || product.lots.length === 0) return 'N/A';
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const validLots = product.lots.filter(lot => {
    if (!lot.expiration_date) return false;
    const expirationDate = new Date(lot.expiration_date);
    return !isNaN(expirationDate.getTime()) && expirationDate >= today;
  });
  if (validLots.length === 0) return 'Todos expiraron';
  validLots.sort((a, b) => new Date(a.expiration_date) - new Date(b.expiration_date));
  const closestDate = new Date(validLots[0].expiration_date);
  return closestDate.toISOString().split('T')[0];
};
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
      <template #item.id="{ item }"><span class="font-weight-medium">{{ item.id }}</span></template>    
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-x-4">
          <VAvatar v-if="item.photo_url" size="38" variant="tonal" rounded :image="item.photo_url" />
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
            <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
          </div>
        </div>
      </template>
      
      <template #item.valid_stock="{ item }"><span class="font-weight-medium">{{ calculateValidStock(item) }}</span></template>
      <template #item.next_expiration="{ item }"><span>{{ nextExpirationDate(item) }}</span></template>
      <template #item.cost_price="{ item }"><span class="font-weight-medium">${{ item.cost_price }}</span></template>
      <template #item.sale_price="{ item }"><span class="font-weight-medium">${{ item.sale_price }}</span></template>
      <template #item.actions="{ item }">
        <IconBtn @click="emit('edit-product', item)"><VIcon icon="tabler-edit" /></IconBtn>
        <IconBtn @click="emit('delete-product', item.id)"><VIcon icon="tabler-trash" /></IconBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
