<script setup lang="js">

const props= defineProps({
  list: { type: Array, required: true },
})

const emit= defineEmits("eliminarItemOrden")

const headers = [
  { title: 'Nombre',                            key: 'product.name'},
  { title: 'Cantidad',                          key: 'reponer'},
  { title: 'Costo',                             key: 'precio_final_supplier'},
  { title: 'Total',                             key: 'totalPorveedor',sortable:false},
  { title: 'Action',                            key: "action"},
];

const groupBy = [{ key: 'supplier.name' }]
</script>

<template>
  <VCard>
    <v-data-table-virtual
      :headers="headers"
      :items="list"
      height="400"
      item-value="name"
      :group-by="groupBy"
      fixed-header
    >
      <template #item.precio_final_supplier="{ item }">
        <!-- <VIcon icon="tabler-currency-dollar" /> -->
        {{ parseFloat(item.precio_final_supplier).toFixed(2) }}
      </template>
      <template #item.reponer="{ item }">
        <VTextField
          class=""
          style="width: 100px"
          type="number"
          v-model="item.reponer"
          :max="item.productSupplier.quantity"
        />
        <!-- {{ item.reponer }} -->
      </template>
      <template #item.totalPorveedor="{ item }">
        <!-- <VIcon icon="tabler-currency-dollar " /> -->
        {{ parseFloat(item.precio_final_supplier).toFixed(2) * item.reponer }}
      </template>
      <template #item.action="{ item }">
        <VIcon
          icon="tabler-circle-minus"
          size="30"
          class="mx-auto d-block cursor-pointer"
          @click="() => emit('eliminarItemOrden', item)"
        />
      </template>
    </v-data-table-virtual>
  </VCard>
</template>
