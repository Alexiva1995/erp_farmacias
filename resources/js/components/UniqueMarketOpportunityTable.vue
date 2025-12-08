<script setup lang="js">
import { computed } from 'vue';

const props = defineProps({
  list: { type: Array, required: true }, // Recibimos el array directo
  loading: { type: Boolean, default: false }
})

// Simplemente retornamos la lista completa
const productosTable = computed(() => {
  return props.list || [];
});
</script>

<template>
  <VCard>
    <VProgressLinear v-if="loading" indeterminate color="primary" />

    <VTable v-if="productosTable.length > 0" class="text-no-wrap">
      <thead>
        <tr>
          <th>Proveedor</th>
          <th>ID</th>
          <th style="width: 300px; max-width: 300px">Producto</th>
          <th>Ventas</th>
          <th>Promedio</th>
          <th>Costo</th>
          <th>Stock A.</th>
          <th>Costo P.</th>
          <th style="width: 120px">Sugerencia</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="item in productosTable" :key="item.uuid || item.product.id">
          <td>{{ item.supplier.name }}</td>
          <td>{{ item.product.id }}</td>

          <td style="max-width: 300px">
            <div class="d-flex align-center">
              <VAvatar
                v-if="item.product.photo_url"
                size="34"
                variant="tonal"
                rounded
                class="me-3"
                :image="item.product.photo_url"
              />
              <div class="d-flex flex-column" style="min-width: 0">
                <span
                  class="text-body-2 font-weight-medium text-high-emphasis text-truncate"
                >
                  {{ item.product.name }}
                </span>
                <span class="text-caption text-disabled text-truncate">
                  {{
                    item.product.laboratory
                      ? item.product.laboratory.name
                      : "Sin Laboratorio"
                  }}
                </span>
              </div>
            </div>

            <VTooltip activator="parent" location="top">
              {{ item.product.name }} -
              {{ item.product.laboratory ? item.product.laboratory.name : "" }}
            </VTooltip>
          </td>

          <td>{{ item.product.total_group_sales }}</td>
          <td>{{ item.product.promedio_calculado }}</td>
          <td>
            <span :style="'color:#28c76f'">{{ item.product.cost_min }}</span> -
            <span :style="'color:#dd4d4f'">{{ item.product.cost_max }}</span> -
            <span :style="'color:#288bc7'">{{ item.product.unit_cost }}</span>
          </td>
          <td>{{ item.product.stock }}</td>
          <td>
            <VIcon icon="tabler-currency-dollar" size="16" class="me-1" />
            {{ parseFloat(item.precio_final_supplier).toFixed(2) }}
          </td>

          <td>
            <VTextField
              type="number"
              v-model="item.reponer"
              density="compact"
              hide-details
              variant="outlined"
              style="min-width: 100px"
              :max="item.productSupplier.quantity"
              :suffix="'/' + item.productSupplier.quantity"
            />
          </td>
        </tr>
      </tbody>
    </VTable>

    <div v-else class="pa-5 text-center text-medium-emphasis">
      No hay oportunidades de mercado disponibles.
    </div>

    <VDivider />
    <div class="d-flex align-center justify-end pa-4">
      <span class="text-sm text-medium-emphasis">
        Total: {{ productosTable.length }} productos
      </span>
    </div>
  </VCard>
</template>
