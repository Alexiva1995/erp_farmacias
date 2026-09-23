<script setup>
import SupplierAssistantReportTableMobile from "@/components/SupplierAssistantReportTableMobile.vue";
import { useDisplay } from 'vuetify';
import { ref, computed, reactive } from 'vue';
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";

const props = defineProps({
  products: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  sortBy: { type: Array, default: () => [{"key":"solicitar","order":"desc"}] },
  totalProduct: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  selectedSupplierId: [Number, String],
  globalDiscountPercent: [Number, String, Object],
});

const emit = defineEmits(["update:options"]);
const { mobile } = useDisplay();

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

const headers = computed(() => [
  { title: "ID", key: "id", sortable: true, width: '70px', align: 'start' },
  { title: "Producto", key: "name", sortable: true, minWidth: '300px' },
  { title: "Costo Actual", key: "unit_cost", sortable: true, align: 'end' },
  { title: "Mejor Oferta", key: "best_supplier_percentage", sortable: true, align: 'end' },
  { title: "Ventas", key: "total_sold_completed", sortable: true, align: 'end' },
  { title: "Stock", key: "lote_quantity", sortable: true, align: 'end' },
  {
    title: "Prom.",
    key: "promedio_calculado",
    sortable: true,
    align: 'end',
    value: (item) =>
      item.promedio_calculado != "" && item.promedio_calculado != null
        ? parseFloat(item.promedio_calculado).toFixed(2)
        : 0,
  },
  {
    title: "Análisis",
    key: "solicitar",
    sortable: true,
    align: 'end',
    value: (item) =>
      item.solicitar != "" && item.solicitar != null
        ? roundIaAnalysis(item.solicitar)
        : 0,
  },
  { title: "Pedido M.", key: "manual_order", sortable: false, align: 'center', width: '140px' }
]);

const rowClass = (item) => {
  const analizado = roundIaAnalysis(item.solicitar);
  if (analizado > 0) return 'row-needs';
  if (analizado < 0) return 'row-excess';
  return '';
};

const getPriceDiff = (current, offer) => {
  if (!current || !offer || current <= 0) return 0;
  return ((offer - current) / current) * 100;
};

const getSelectedSupplierPrice = (item) => {
  if (!props.selectedSupplierId || !item.product_suppliers) return null;
  const supplier = item.product_suppliers.find(ps => ps.supplier_id == props.selectedSupplierId);
  if (!supplier) return null;
  
  const originalPrice = parseFloat(supplier.unit_cost_usd || 0);
  const discount = parseFloat(props.globalDiscountPercent || 0);
  
  if (discount > 0) {
    return originalPrice * (1 - discount / 100);
  }
  return originalPrice;
};

const handleManualOrder = async (item) => {
  const quantity = getInputValue(item);
  if (!quantity || quantity <= 0) {
    toast.info("Por favor ingrese una cantidad válida mayor a cero");
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
  <div class="assistant-report-container">
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Escritorio -->
      <div v-if="!mobile" class="d-none d-md-block">
        <VDataTableServer
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :headers="headers"
          :items="props.products"
          :items-length="props.totalProduct"
          :loading="props.loading"
          :sort-by="props.sortBy"
          :row-props="({ item }) => ({ class: rowClass(item) })"
          class="premium-table text-no-wrap"
          @update:options="(options) => emit('update:options', options)"
        >
          <!-- Slot de Sin Datos -->
          <template #no-data>
            <div class="d-flex flex-column align-center py-12 text-disabled text-center">
              <VIcon icon="tabler-package-off" size="48" class="mb-3" color="secondary" />
              <h4 class="text-sm font-weight-bold mb-1 text-high-emphasis">No se encontraron productos</h4>
              <span class="text-xs text-disabled">Selecciona otro laboratorio o intenta ajustar tus filtros de búsqueda.</span>
            </div>
          </template>

          <!-- ID -->
          <template #item.id="{ item }">
            <span class="text-caption font-weight-medium text-disabled font-mono">{{ item.id }}</span>
          </template>

          <!-- Producto -->
          <template #item.name="{ item }">
            <div class="d-flex align-center py-2">
              <div class="d-flex flex-column overflow-hidden">
                <span class="text-sm font-weight-black text-high-emphasis text-uppercase text-truncate" :title="item.name">
                  {{ item.name.toUpperCase() }}
                  <VChip v-if="item.is_colombian_origin == 1" size="x-small" color="info" variant="tonal" class="ms-1 px-1 font-weight-black chip-col">COL</VChip>
                </span>
                <div class="d-flex align-center gap-1 text-super-xs">
                  <span class="text-disabled truncate max-w-180">{{ item.active_ingredient || 'SIN INGREDIENTE' }}</span>
                  <span class="text-disabled mx-1">|</span>
                  <span class="text-primary font-weight-black text-uppercase truncate max-w-120">
                    {{ item.laboratory?.name || 'S/L' }}
                  </span>
                </div>
              </div>
            </div>
          </template>

          <!-- Costo Actual -->
          <template #item.unit_cost="{ item }">
            <div class="d-flex flex-column align-end">
              <span class="text-sm font-weight-bold text-high-emphasis">$ {{ Number(item.unit_cost || 0).toFixed(2) }}</span>
              <span class="text-xxs text-disabled mt-n1">Costo Ficha</span>
            </div>
          </template>

          <!-- Mejor Oferta / Cotización -->
          <template #item.best_supplier_percentage="{ item }">
            <div v-if="item.best_supplier && Number(item.best_supplier_price) > 0" class="d-flex flex-column align-end py-1">
              <div class="d-flex align-center gap-1 mb-1">
                <span 
                  class="text-xs font-weight-black"
                  :class="item.best_supplier_percentage < 0 ? 'text-success' : 'text-high-emphasis'"
                >
                  $ {{ Number(item.best_supplier_price || 0).toFixed(2) }}
                </span>
                <VChip 
                  v-if="item.best_supplier_percentage && !isNaN(item.best_supplier_percentage) && item.best_supplier_percentage !== 0"
                  variant="tonal" 
                  :color="item.best_supplier_percentage < 0 ? 'success' : 'warning'" 
                  size="x-small" 
                  class="px-1 font-weight-bold chip-percentage"
                >
                  {{ item.best_supplier_percentage > 0 ? '+' : '' }}{{ Math.round(item.best_supplier_percentage) }}%
                  {{ item.best_supplier_percentage < 0 ? 'Ahorro' : 'Mercado' }}
                </VChip>
              </div>
              <span 
                class="text-super-xs text-uppercase truncate mb-1 max-w-140"
                :class="props.selectedSupplierId && (item.best_supplier.id == props.selectedSupplierId || item.best_supplier.supplier_id == props.selectedSupplierId) ? 'text-primary font-weight-black' : 'text-disabled font-weight-medium'"
              >
                {{ item.best_supplier.name || item.best_supplier.supplier?.name || 'PROVEEDOR' }}
              </span>

              <div v-if="getSelectedSupplierPrice(item)" class="selected-supplier-box w-100 mt-1 pa-1 rounded border-t border-dashed">
                <div class="d-flex flex-column align-end">
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-user-check" size="10" color="primary" />
                    <span class="text-xs font-weight-black text-primary">$ {{ getSelectedSupplierPrice(item).toFixed(2) }}</span>
                  </div>
                  <span v-if="props.globalDiscountPercent > 0" class="text-super-xs text-info font-weight-bold">
                    {{ props.globalDiscountPercent }}% OFF
                  </span>
                </div>
              </div>
            </div>
            <div v-else-if="item.product_suppliers?.length" class="d-flex flex-column align-end py-1">
              <div class="d-flex align-center gap-1 mb-1">
                <span 
                  class="text-xs font-weight-black"
                  :class="getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd) < 0 ? 'text-success' : 'text-high-emphasis'"
                >
                  $ {{ Number(item.product_suppliers[0].unit_cost_usd || 0).toFixed(2) }}
                </span>
                <VChip 
                  variant="tonal" 
                  :color="getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd) < 0 ? 'success' : 'warning'" 
                  size="x-small" 
                  class="px-1 font-weight-bold chip-percentage"
                >
                  {{ getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd) > 0 ? '+' : '' }}{{ getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd).toFixed(0) }}%
                  {{ getPriceDiff(item.unit_cost, item.product_suppliers[0].unit_cost_usd) < 0 ? 'Ahorro' : 'Mercado' }}
                </VChip>
              </div>
              <span 
                class="text-super-xs text-uppercase truncate mb-1 max-w-140"
                :class="props.selectedSupplierId && (item.product_suppliers[0].supplier_id == props.selectedSupplierId || item.product_suppliers[0].supplier?.id == props.selectedSupplierId) ? 'text-primary font-weight-black' : 'text-disabled font-weight-medium'"
              >
                {{ item.product_suppliers[0].supplier?.name || 'PROVEEDOR' }}
              </span>

              <div v-if="getSelectedSupplierPrice(item)" class="selected-supplier-box w-100 mt-1 pa-1 rounded border-t border-dashed">
                <div class="d-flex flex-column align-end">
                  <div class="d-flex align-center gap-1">
                    <VIcon icon="tabler-user-check" size="10" color="primary" />
                    <span class="text-xs font-weight-black text-primary">$ {{ getSelectedSupplierPrice(item).toFixed(2) }}</span>
                  </div>
                  <span v-if="props.globalDiscountPercent > 0" class="text-super-xs text-info font-weight-bold">
                    {{ props.globalDiscountPercent }}% OFF
                  </span>
                </div>
              </div>
            </div>
            <span v-else class="text-xxs text-disabled italic">Sin cotización</span>
          </template>

          <!-- Ventas y Stock -->
          <template #item.total_sold_completed="{ item }">
            <span class="font-weight-bold text-high-emphasis">{{ item.total_sold_completed ? Math.round(Number(item.total_sold_completed)) : 0 }}</span>
          </template>
          
          <template #item.lote_quantity="{ item }">
            <VChip :color="item.lote_quantity > 0 ? 'secondary' : 'error'" variant="tonal" size="x-small" class="font-weight-black">
              {{ item.lote_quantity ? Math.round(Number(item.lote_quantity)) : 0 }}
            </VChip>
          </template>

          <template #item.promedio_calculado="{ item }">
            <span class="font-weight-bold text-high-emphasis">
              {{ item.promedio_calculado != null && item.promedio_calculado !== '' ? parseFloat(item.promedio_calculado).toFixed(2) : '0.00' }}
            </span>
          </template>

          <!-- Análisis Semántico -->
          <template #item.solicitar="{ item }">
            <div class="d-flex justify-end">
              <VChip
                v-if="roundIaAnalysis(item.solicitar) > 0"
                color="success"
                variant="tonal"
                size="small"
                class="font-weight-black px-2"
              >
                <VIcon icon="tabler-arrow-up-right" size="14" class="me-1" />
                +{{ roundIaAnalysis(item.solicitar) }} uds
              </VChip>
              <VChip
                v-else-if="roundIaAnalysis(item.solicitar) < 0"
                color="secondary"
                variant="tonal"
                size="small"
                class="font-weight-bold px-2 text-disabled"
              >
                <VIcon icon="tabler-alert-circle" size="14" class="me-1" />
                Exceso: {{ Math.abs(roundIaAnalysis(item.solicitar)) }} uds
              </VChip>
              <VChip
                v-else
                color="secondary"
                variant="outlined"
                size="small"
                class="font-weight-medium px-2 text-disabled opacity-70"
              >
                Equilibrio (0)
              </VChip>
            </div>
          </template>

          <!-- Pedido Manual -->
          <template #item.manual_order="{ item }">
            <div class="d-flex align-center justify-center gap-1">
              <VTextField
                :model-value="getInputValue(item)"
                @update:model-value="(val) => updateQuantity(item, val)"
                type="number"
                min="0"
                density="compact"
                hide-details
                class="manual-qty-input max-w-80"
                placeholder="0"
              />
              <VBtn
                icon
                size="30"
                :color="roundIaAnalysis(item.solicitar) > 0 || (manualQuantities[item.id] && manualQuantities[item.id] > 0) ? 'success' : 'secondary'"
                :variant="roundIaAnalysis(item.solicitar) > 0 || (manualQuantities[item.id] && manualQuantities[item.id] > 0) ? 'elevated' : 'tonal'"
                :loading="orderingIds[item.id]"
                :disabled="!getInputValue(item) || getInputValue(item) <= 0"
                @click="handleManualOrder(item)"
              >
                <VIcon icon="tabler-shopping-cart-plus" size="16" />
                <VTooltip activator="parent" location="top">
                  {{ getInputValue(item) > 0 ? `Añadir ${getInputValue(item)} uds al pedido` : 'Sin cantidad para pedir' }}
                </VTooltip>
              </VBtn>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards desacopladas) -->
      <div v-else class="d-block d-md-none">
        <SupplierAssistantReportTableMobile
          :products="props.products"
          :loading="props.loading"
          :total-product="props.totalProduct"
          :items-per-page="props.itemsPerPage"
          :page="props.page"
          :selected-supplier-id="props.selectedSupplierId"
          :global-discount-percent="props.globalDiscountPercent"
          @update:options="(options) => emit('update:options', options)"
        />
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

:deep(.row-needs) td {
  background-color: rgba(var(--v-theme-success), 0.04) !important;
}

:deep(.row-excess) td {
  background-color: rgba(var(--v-theme-secondary), 0.02) !important;
}

.chip-col,
.chip-percentage {
  font-size: 0.6rem !important;
}

.max-w-180 {
  max-inline-size: 180px;
}

.max-w-140 {
  max-inline-size: 140px;
}

.max-w-120 {
  max-inline-size: 120px;
}

.max-w-110 {
  max-inline-size: 110px;
}

.max-w-80 {
  max-inline-size: 80px;
}

.text-xxs {
  font-size: 0.65rem !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.font-mono {
  font-family: monospace;
}
</style>


