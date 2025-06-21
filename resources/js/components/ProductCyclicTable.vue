
<script setup>
const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emits = defineEmits(['update:options', 'product-click']);

const headers = [
  { title: 'id', key: 'id'},
  { title: 'Producto', key: 'name' },
  { title: 'Precio Venta', key: 'sale_price' },
];

const emitProductClick = (product) => {
    console.log(product)
    emits('product-click', product);
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
      @update:options="$emit('update:options', $event)"
      item-value="id"
      hover 
    >
      
 <template #item="{ item }">
        <tr @click="emitProductClick(item)">
          <td><span class="font-weight-medium">{{ item.id }}</span></td>
          <td>
            <div class="d-flex align-center gap-x-4">
              <VAvatar v-if="item.photo_url" size="38" variant="tonal" rounded :image="item.photo_url" />
              <div class="d-flex flex-column">
                <span class="text-body-1 font-weight-medium text-high-emphasis">{{ item.name }}</span>
                <span class="text-sm text-disabled">{{ item.active_ingredient }}</span>
              </div>
            </div>
          </td>
          <td><span class="font-weight-medium">${{ item.sale_price }}</span></td>
          </tr>
      </template>

    </VDataTableServer>
  </VCard>
</template>
