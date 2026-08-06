<script setup>
import AppMobilePagination from "@/components/AppMobilePagination.vue";
import { ref, reactive } from 'vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  selectedSupplierId: [Number, String],
  globalDiscountPercent: [Number, String, Object],
});

const emit = defineEmits(["update:options"]);

const manualQuantities = reactive({});
const orderingIds = ref({});

const roundIaAnalysis = (value) => {
  const number = Number(value);
  return isNaN(number) ? 0 : Math.ceil(number);
};

const getInputValue = (item) => {
  if (manualQuantities[item.id] !== undefined && manualQuantities[item.id] !== null) {
    return manualQuantities[item.id];
  }
  const sugerido = roundIaAnalysis(item.solicitar);
  return sugerido > 0 ? sugerido : null;
};

const updateQuantity = (item, val) => {
  manualQuantities[item.id] = val === "" ? null : Number(val);
};

const handleManualOrder = async (item) => {
  const quantity = getInputValue(item);
  if (!quantity || quantity <= 0) {
    toast.info("Por favor ingrese una cantidad válida");
    return;
  }

  let productSupplierId = null;
  let supplierName = "";

  if (props.selectedSupplierId) {
    const ps = item.product_suppliers?.find(p => p.supplier_id == props.selectedSupplierId);
    if (!ps) {
      toast.error("El proveedor seleccionado no ofrece este producto");
      return;
    }
    productSupplierId = ps.id;
    supplierName = ps.supplier?.name || "Proveedor Seleccionado";
  } else if (item.product_suppliers?.length > 0) {
    productSupplierId = item.product_suppliers[0].id;
    supplierName = item.product_suppliers[0].supplier?.name || "Mejor Oferta";
  }

  if (!productSupplierId) {
    toast.error("No se encontró un proveedor para este producto");
    return;
  }

  orderingIds.value[item.id] = true;
  try {
    const data = {
      productId: productSupplierId,
      quantity: quantity,
      discount: false,
    };
    
    await axios.post('/suppliers/add-product-to-order', data);
    toast.success(`Pedido de ${quantity} unidades añadido a ${supplierName}`);
    manualQuantities[item.id] = null;
  } catch (error) {
    console.error("Error al pedir:", error);
    toast.error("Hubo un error al procesar el pedido manual");
  } finally {
    orderingIds.value[item.id] = false;
  }
};
</script>

<template>
  <div class="pa-2">
    <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
    
    <template v-if="props.products.length > 0">
      <div class="d-flex flex-column gap-2">
        <VCard
          v-for="item in props.products"
          :key="item.id"
          variant="flat"
          class="border mb-1 rounded-lg overflow-hidden"
        >
          <div class="pa-3">
            <div class="d-flex justify-space-between align-start mb-2">
              <div class="flex-grow-1 pr-2">
                <div class="text-sm font-weight-black text-primary text-uppercase leading-tight truncate-2-lines mb-1">
                  {{ item.name }}
                </div>
                <div class="d-flex align-center flex-wrap gap-x-2 text-super-xs text-disabled">
                  <span>{{ item.laboratory?.name || 'S/L' }}</span>
                  <span>|</span>
                  <span>{{ item.active_ingredient || 'SIN INGREDIENTE' }}</span>
                  <VChip v-if="item.is_colombian_origin == 1" color="info" size="x-small" label class="ml-1 text-super-xs">COL</VChip>
                </div>
              </div>
              <div class="text-right d-flex flex-column align-end">
                <div 
                  class="analysis-badge pa-1 px-2 rounded d-flex flex-column align-center"
                  :class="roundIaAnalysis(item.solicitar) > 0 ? 'bg-success-subtle' : roundIaAnalysis(item.solicitar) < 0 ? 'bg-error-subtle' : 'bg-secondary-subtle'"
                >
                  <span class="text-super-xs font-weight-black text-uppercase opacity-70">Análisis</span>
                  <span class="text-sm font-weight-black leading-none mt-1">
                    {{ roundIaAnalysis(item.solicitar) > 0 ? '+' : '' }}{{ roundIaAnalysis(item.solicitar) }}
                  </span>
                </div>
              </div>
            </div>

            <VDivider class="my-2 border-opacity-10" />

            <div class="grid-mobile-info">
              <div class="info-item">
                <span class="label">Stock</span>
                <span class="value">{{ item.lote_quantity ? Math.round(Number(item.lote_quantity)) : 0 }}</span>
              </div>
              <div class="info-item">
                <span class="label">Ventas</span>
                <span class="value">{{ item.total_sold_completed ? Math.round(Number(item.total_sold_completed)) : 0 }}</span>
              </div>
              <div class="info-item">
                <span class="label">Costo Actual</span>
                <span class="value text-primary font-weight-bold">$ {{ Number(item.unit_cost || 0).toFixed(2) }}</span>
              </div>
            </div>

            <div v-if="item.product_suppliers?.length" class="mt-3 pa-2 bg-var-theme-background rounded d-flex align-center justify-space-between border-dashed-thin">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-tag" size="14" color="success" />
                <span class="text-super-xs font-weight-black text-success text-truncate max-w-100">{{ item.product_suppliers[0].supplier.name }}</span>
              </div>
              <span class="text-xs font-weight-black text-success">$ {{ Number(item.product_suppliers[0].unit_cost_usd || 0).toFixed(2) }}</span>
            </div>

            <!-- Acción Móvil: Pedido Manual -->
            <div class="mt-3 d-flex align-center gap-2">
              <VTextField
                :model-value="getInputValue(item)"
                @update:model-value="(val) => updateQuantity(item, val)"
                type="number"
                density="compact"
                hide-details
                placeholder="Cantidad"
                class="manual-qty-input flex-grow-1"
                variant="outlined"
              />
              <VBtn
                color="primary"
                variant="elevated"
                size="40"
                class="rounded-lg"
                :loading="orderingIds[item.id]"
                @click="handleManualOrder(item)"
              >
                <VIcon icon="tabler-shopping-cart-plus" size="20" />
              </VBtn>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Paginación Móvil -->
      <div class="d-flex justify-center mt-4">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.totalProduct"
          :loading="props.loading"
          :items-per-page-options="[10, 25, 50]"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </template>

    <div v-else class="d-flex flex-column align-center py-12 text-disabled text-center px-4">
      <VIcon icon="tabler-package-off" size="48" class="mb-3" color="secondary" />
      <h4 class="text-sm font-weight-bold mb-1 text-high-emphasis">No se encontraron productos</h4>
      <span class="text-xs text-disabled">Selecciona otro laboratorio o intenta ajustar tus filtros de búsqueda.</span>
    </div>
  </div>
</template>

<style scoped>
.max-w-100 {
  max-inline-size: 100px;
}

.grid-mobile-info {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}
.info-item {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.info-item .label {
  font-size: 0.6rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  font-weight: 800;
  text-align: center;
}
.info-item .value {
  font-size: 0.75rem;
  font-weight: 700;
  text-align: center;
}
.bg-success-subtle {
  background-color: rgba(var(--v-theme-success), 10%);
  color: rgb(var(--v-theme-success));
}
.bg-error-subtle {
  background-color: rgba(var(--v-theme-error), 10%);
  color: rgb(var(--v-theme-error));
}
.bg-secondary-subtle {
  background-color: rgba(var(--v-theme-secondary), 10%);
  color: rgb(var(--v-theme-secondary));
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}
.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}
.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3);
}
.leading-tight {
  line-height: 1.25;
}
.leading-none {
  line-height: 1;
}
.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}
</style>
