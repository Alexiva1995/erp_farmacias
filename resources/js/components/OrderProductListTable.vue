<script setup lang="js">

const props= defineProps({
  list: { type: Array, required: true },
})

const headers = [
  { title: 'Nombre',                            key: 'product.name'},
  { title: 'Cantidad',                          key: 'reponer'},
  { title: 'Costo Unit.',                       key: 'productSupplier.unit_cost'},
  { title: 'Total',                             key: 'totalPorveedor',sortable:false},
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
      <template #item.reponer="{ item }">
        <!-- <VTextField
          class="w-100"
          type="number"
          v-model="item.reponer"
          readonly="true"
          :max="item.productSupplier.quantity"
        /> -->
        {{ item.reponer }}
      </template>
      <template #item.totalPorveedor="{ item }">
        <VIcon icon="tabler-currency-dollar" />
        {{ item.reponer * item.productSupplier.unit_cost }}
      </template>
    </v-data-table-virtual>
  </VCard>
</template>
