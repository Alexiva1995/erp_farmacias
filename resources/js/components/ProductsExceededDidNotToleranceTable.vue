<script setup lang="js">
import { computed } from 'vue';

const props= defineProps({
  list: { type: Array, required: true },
})

const productosTable = computed(() => {
  return props.list?.filter(pro => pro.increase == false) || [];
});
</script>

<template>
  <VCard>
    <VTable
      v-if="productosTable.length > 0"
      height="450"
      fixed-header
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th>Proveedor</th>
          <th>ID</th>
          <th>Producto</th>
          <th>Ventas</th>
          <th>Promedio</th>
          <th>Costo A.</th>
          <th>Costo P.</th>
          <th>Stock A.</th>
          <th>Sugerencia</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid">
          <td>
            {{ item.supplier.name }}
          </td>
          <td>
            {{ item.product.id }}
          </td>
          <td>
            {{ item.product.name }}
          </td>
          <td>
            {{ item.product.total_group_sales }}
          </td>
          <td>
            {{ item.product.promedio_calculado }}
          </td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ parseFloat(item.product.unit_cost).toFixed(2) }}
          </td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ parseFloat(item.precio_final_supplier).toFixed(2) }}
          </td>
          <td>{{ item.product.stock }}</td>
          <td class="row">
            <VTextField
              type="number"
              v-model="item.reponer"
              :max="item.productSupplier.quantity"
              :suffix="'/' + item.productSupplier.quantity"
            />
          </td>
        </tr>
      </tbody>
    </VTable>
  </VCard>
</template>
