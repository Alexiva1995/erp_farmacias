<script setup lang="js">
import { computed } from 'vue';

const props = defineProps({
  list: { type: Array, required: true },
})

const productosTable = computed(() => {
  return props.list?.filter(pro => pro.increase == true) || [];
});
</script>

<template>
  <VCard>
    <VTable
      v-if="productosTable.length > 0"
      density="compact"
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th>Proveedor</th>
          <th>ID</th>
          <th style="min-width: 300px">Producto</th>
          <th>Ventas</th>
          <th>Promedio</th>
          <th>Costo A.</th>
          <th>Costo P.</th>
          <th>Stock A.</th>
          <th>Análisis</th>
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

          <td style="max-width: 300px">
            <div class="d-flex align-center py-2">
              <VAvatar
                v-if="item.product.photo_url"
                size="34"
                rounded
                variant="tonal"
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
              {{ item.product.name }}
              {{
                item.product.laboratory
                  ? " - " + item.product.laboratory.name
                  : ""
              }}
            </VTooltip>
          </td>

          <td>
            {{ item.product.total_group_sales }}
          </td>
          <td>
            {{ item.product.promedio_calculado }}
          </td>
          <td>
            <VIcon icon="tabler-currency-dollar" size="small" />
            {{ parseFloat(item.product.unit_cost).toFixed(2) }}
          </td>
          <td>
            <VIcon icon="tabler-currency-dollar" size="small" />
            {{ parseFloat(item.precio_final_supplier).toFixed(2) }}
          </td>
          <td>{{ item.product.stock }}</td>
          <td>
            <span
              :style="
                item.product.solicitar > 0 ? 'color:#28c76f;' : 'color:#dd4d4f;'
              "
              >{{ item.product.solicitar > 0 ? "+" : ""
              }}{{ item.product.solicitar }}</span
            >
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
  </VCard>
</template>
