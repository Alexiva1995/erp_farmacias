<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { defineEmits, defineProps } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  pack: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const handleClose = () => {
  emit("update:isDialogVisible", false);
};
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    @update:model-value="handleClose"
    max-width="800px"
  >
    <VCard v-if="props.pack">
      <VCardTitle class="d-flex justify-space-between align-center">
        <span>Detalles del Pack: {{ props.pack.name }}</span>
        <IconBtn @click="handleClose">
          <VIcon icon="tabler-x" />
        </IconBtn>
      </VCardTitle>

      <VCardText>
        <VTable hover class="text-no-wrap mb-4">
          <thead>
            <tr>
              <th scope="col" style="width: 80px">Cant.</th>
              <th scope="col">Producto</th>
              <th scope="col">ID</th>
            </tr>
          </thead>
          <tbody>
            <template
              v-if="props.pack.products_info && props.pack.products_info.length"
            >
              <tr
                v-for="product in props.pack.products_info"
                :key="product.product_id"
              >
                <td class="font-weight-bold text-center">
                  {{ product.quantity }}
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <span class="font-weight-medium">{{
                      product.product_name
                    }}</span>
                    <span class="text-xs text-disabled">{{
                      product.product_info?.active_ingredient
                    }}</span>
                  </div>
                </td>
                <td class="text-disabled text-caption">
                  {{ product.product_id }}
                </td>
              </tr>
            </template>
            <template
              v-else-if="props.pack.products && props.pack.products.length"
            >
              <tr v-for="product in props.pack.products" :key="product.id">
                <td class="font-weight-bold text-center">
                  {{ product.pivot?.quantity || 1 }}
                </td>
                <td>
                  <div class="d-flex flex-column">
                    <span class="font-weight-medium">{{ product.name }}</span>
                    <span class="text-xs text-disabled">{{
                      product.active_ingredient
                    }}</span>
                  </div>
                </td>
                <td class="text-disabled text-caption">{{ product.id }}</td>
              </tr>
            </template>
            <template v-else-if="props.pack.pack_config">
              <tr
                v-for="(quantity, productId) in props.pack.pack_config"
                :key="productId"
              >
                <td class="font-weight-bold text-center">{{ quantity }}</td>
                <td>Producto ID: {{ productId }}</td>
                <td class="text-disabled text-caption">{{ productId }}</td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="3" class="text-center text-disabled">
                No hay información de productos disponible.
              </td>
            </tr>
          </tbody>
        </VTable>

        <div
          class="d-flex justify-space-between align-center bg-var-theme-background surface-variant rounded pa-4"
        >
          <span class="text-h6">Precio Total del Pack:</span>
          <span class="text-h6 text-success font-weight-bold">{{
            formatCurrency(parseFloat(props.pack.total_price))
          }}</span>
        </div>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn color="secondary" variant="tonal" @click="handleClose">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
