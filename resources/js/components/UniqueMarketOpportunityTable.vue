<script setup lang="js">
import { computed } from 'vue';

const props = defineProps({
  paginationData: { type: Object, required: true },
  loading: { type: Boolean, default: false }
})

const emit = defineEmits(['change-page']);

const productosTable = computed(() => {
  return props.paginationData.data || [];
});

const totalPages = computed(() => {
  return props.paginationData.last_page || 1;
});

const currentPage = computed({
  get: () => props.paginationData.current_page || 1,
  set: (val) => emit('change-page', val)
});
</script>

<template>
  <VCard>
    <VProgressLinear v-if="loading" indeterminate color="primary" />

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

          <!-- CAMBIO 1: Forzamos el ancho desde el encabezado -->
          <th style="width: 250px">Producto</th>

          <th>Ventas</th>
          <th>Promedio</th>
          <th>Costo Lot.</th>
          <th>Costo A.</th>
          <th>Stock A.</th>
          <th>Costo P.</th>
          <th>Sugerencia</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid">
          <td>{{ item.supplier.name }}</td>
          <td>{{ item.product.id }}</td>

          <td style="width: 220px; max-width: 220px">
            <div class="text-truncate">
              {{ item.product.name }}
            </div>

            <VTooltip activator="parent" location="top">
              {{ item.product.name }}
            </VTooltip>
          </td>

          <td>{{ item.product.total_group_sales }}</td>
          <td>{{ item.product.promedio_calculado }}</td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ parseFloat(item.cost_lot).toFixed(2) }}
          </td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ parseFloat(item.product.unit_cost).toFixed(2) }}
          </td>
          <td>{{ item.product.stock }}</td>
          <td>
            <VIcon icon="tabler-currency-dollar" />
            {{ parseFloat(item.precio_final_supplier).toFixed(2) }}
          </td>
          <td class="row">
            <VTextField
              type="number"
              v-model="item.reponer"
              density="compact"
              hide-details
              style="min-width: 100px"
              :max="item.productSupplier.quantity"
              :suffix="'/' + item.productSupplier.quantity"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Resto del componente igual... -->
    <div v-else class="pa-5 text-center text-medium-emphasis">
      No hay oportunidades de mercado en esta página.
    </div>

    <VDivider />

    <div class="d-flex align-center justify-end pa-4">
      <span class="text-sm text-medium-emphasis me-4">
        Total: {{ props.paginationData.total || 0 }} productos
      </span>
      <VPagination
        v-model="currentPage"
        :length="totalPages"
        total-visible="5"
        size="small"
        rounded="circle"
        active-color="primary"
        :disabled="loading"
      />
    </div>
  </VCard>
</template>
