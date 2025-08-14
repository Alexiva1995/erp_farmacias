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
          <th>Nombre</th>
          <th>Sugerencia</th>
          <th>Stock Pro.</th>
          <th>Costo Unit.</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid">
          <td>{{ item.product.name }}</td>
          <td class="">
            <VTextField
              type="number"
              v-model="item.reponer"
              :max="item.productSupplier.quantity"
            />
          </td>
          <td>{{ item.productSupplier.quantity }}</td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ item.productSupplier.unit_cost }}
          </td>
        </tr>
      </tbody>
    </VTable>
    <div v-else class="text-center mb-5">No hay productos que mostrar...</div>
  </VCard>
</template>
