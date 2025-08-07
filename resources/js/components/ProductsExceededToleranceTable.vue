<script setup lang="js">
import { computed } from 'vue';



const props= defineProps({
  dataProductos: { type: Array, required: true },
  list: { type: Array, required: true },
  productsExceededTolerance: { type: Array, required: true },
})

const productsExceededTolerance = computed(() => {
  return props.list?.filter(pro => pro.increase == true) || [];
});

const emit= defineEmits("actualizarCantidad")

console.log("data tabla => ",productsExceededTolerance)
</script>

<template>
  <VCard>
    <VTable
      v-if="productsExceededTolerance.length > 0"
      height="450"
      fixed-header
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th>Nombre</th>
          <th>cantidad</th>
          <th>Stock Pro.</th>
          <th>Costo Unit.</th>
        </tr>
      </thead>

      <tbody>
        <!-- <tr v-for="item in props.list" :key="item.product.id"> -->
        <tr v-for="item in productsExceededTolerance" :key="item.uuid">
          <td>
            {{ item.product.name }}
          </td>
          <td class="">
            <VTextField
              type="number"
              v-model="item.reponer"
              :max="item.productSupplier.quantity"
            />
          </td>
          <td>{{ item.productSupplier.quantity }}</td>
          <td>
            {{ item.productSupplier.unit_cost }}
          </td>
        </tr>
      </tbody>
    </VTable>
    <div v-else class="text-center mb-5">Cargando datos...</div>
  </VCard>
</template>
