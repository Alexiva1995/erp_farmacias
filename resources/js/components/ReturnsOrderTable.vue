<script setup>


const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrder: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});
const emit = defineEmits(['update:options']);
const expanded = ref([]);

const handleReturnProduct = (product) => {
  emit('return-product', product);
};

const headers = [
  { title: 'N° Orden', key: 'id', sortable: true, width: '100px' },
  { title: 'Cliente', key: 'client', sortable: true },
  { title: 'Monto', key: 'total_amount', sortable: true },
  { title: 'Fecha', key: 'date', sortable: true },
  { title: 'Productos', key: 'data-table-expand', sortable: false }, 
];

const orderItemHeaders = [
  { title: 'Producto', key: 'product.name' },
  { title: 'Ingrediente Activo', key: 'product.active_ingredient' },
  { title: 'Laboratorio', key: 'product.laboratory.name' },
  { title: 'Cantidad', key: 'quantity' },
  { title: 'Acción', key: 'actions', sortable: false },
];

</script>
<template>
  <VCard>
    <VDataTableServer
       v-model:expanded="expanded"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.orders"
      :items-length="props.totalOrder"
      :loading="props.loading"
      item-key="id"
      class="text-no-wrap"
      fixed-header
      height="auto"
      show-expand
      @update:options="options => emit('update:options', options)"
    >

    <template #item.client="{ item }"><span class="font-weight-medium">{{item.client.name}} {{item.client.last_name}}</span></template>
    <template #item.date="{ item }"><span class="font-weight-medium">{{ new Date(item.created_at).toISOString().split("T")[0] }}</span></template>
    <template v-slot:item.data-table-expand="{ internalItem, isExpanded, toggleExpand }">
      <v-btn
        :append-icon="isExpanded(internalItem) ? 'mdi-chevron-up' : 'mdi-chevron-down'"
        :text="isExpanded(internalItem) ? 'Menos información' : 'Más información'"
        class="text-none"
        color="medium-emphasis"
        size="small"
        variant="text"
        width="205"
        border
        slim
        @click="toggleExpand(internalItem)"
      ></v-btn>
    </template>

   <template v-slot:expanded-row="{ columns, item }">
        <tr>
          <td :colspan="columns.length">
            <VCard flat class="my-4">
              <VCardText>
                <VDataTable
                  :headers="orderItemHeaders"
                  :items="item.details"
                  item-key="id" 
                  hide-default-footer
                  class="elevation-1"
                >
                  <template #item.product.name="{ item: detailItem }">
                    {{ detailItem.product ? detailItem.product.name : 'N/A' }}
                  </template>
                  <template #item.product.active_ingredient="{ item: detailItem }">
                    {{ detailItem.product ? detailItem.product.active_ingredient : 'N/A' }}
                  </template>
                  <template #item.product.laboratory.name="{ item: detailItem }">
                    {{ detailItem.product && detailItem.product.laboratory ? detailItem.product.laboratory.name : 'N/A' }}
                  </template>
                  <template #item.quantity="{ item: detailItem }">
                    {{ detailItem.quantity }}
                  </template>
                  <template #item.actions="{ item: detailItem }">
                    <VBtn
                      color="warning"
                      size="small"
                      @click="handleReturnProduct(detailItem)"
                    >
                      Devolver
                    </VBtn>
                  </template>
                </VDataTable>
              </VCardText>
            </VCard>
          </td>
        </tr>
      </template>

    </VDataTableServer>
  </VCard>
</template>
