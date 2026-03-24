<script setup>
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrder: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isVendedor: { type: Boolean, required: true },
});
const emit = defineEmits(["update:options", "return-product"]);
const { mobile } = useDisplay();

const selectedProductsToReturn = ref({});
const showProductsModal = ref(false);
const selectedOrderForModal = ref(null);
/** Cantidad a devolver por detail.id para reactividad al editar */
const returnsQuantityByDetailId = ref({});

watch(
  () => props.orders,
  (newOrders) => {
    newOrders.forEach((order) => {
      if (!selectedProductsToReturn.value[order.id]) {
        selectedProductsToReturn.value[order.id] = [];
      }
      order.details.forEach((detail) => {
        if (detail.returns_quantity === undefined) {
          detail.returns_quantity = detail.quantity;
        }
      });
    });
  },
  { immediate: true, deep: true }
);

watch(selectedOrderForModal, (order) => {
  if (!order?.details) {
    returnsQuantityByDetailId.value = {};
    return;
  }
  const next = {};
  order.details.forEach((d) => {
    next[d.id] = d.returns_quantity ?? d.quantity ?? 0;
  });
  returnsQuantityByDetailId.value = next;
}, { immediate: true });

const handleReturnSelectedProducts = async (order) => {
  const selected = selectedProductsToReturn.value[order.id];

  if (!selected || selected.length === 0) {
    toast.warning("Por favor, seleccione al menos un producto para devolver.");
    return;
  }

  const itemsToReturn = selected.map((selectedItem) => {
    const upToDateProduct = typeof selectedItem === "object" && selectedItem != null
      ? selectedItem
      : order.details.find((detail) => detail.id === selectedItem);
    if (
      !upToDateProduct ||
      isNaN(parseFloat(upToDateProduct.returns_quantity)) ||
      parseFloat(upToDateProduct.returns_quantity) <= 0
    ) {
      toast.warning(
        "Verifique las cantidades. Alguna cantidad a devolver no es válida."
      );
      return null;
    }

    if (
      parseFloat(upToDateProduct.returns_quantity) > upToDateProduct.quantity
    ) {
      toast.warning(
        `La cantidad a devolver de ${upToDateProduct.product.name} no puede ser mayor que la cantidad vendida.`
      );
      return null;
    }
    return {
      product: upToDateProduct.product,
      order: order,
      returns_quantity: parseFloat(upToDateProduct.returns_quantity),
    };
  });
  const validItemsToReturn = itemsToReturn.filter((item) => item !== null);
  if (validItemsToReturn.length === 0) {
    return;
  }

  // Devolución directa: sin selección de lote, se asigna USD al saldo del cliente
  emit("return-product", validItemsToReturn);
  closeProductsModal();
};

const openProductsModal = (item) => {
  selectedOrderForModal.value = item;
  showProductsModal.value = true;
};

const closeProductsModal = () => {
  showProductsModal.value = false;
  selectedOrderForModal.value = null;
};

const headers = [
  { title: "N° ORDEN", key: "id", sortable: true, width: "100px" },
  { title: "CLIENTE", key: "client", sortable: true },
  { title: "MONTO", key: "amount", sortable: true },
  { title: "FECHA", key: "date", sortable: true },
  { title: "PRODUCTOS", key: "products", sortable: false, width: "80px" },
];

const orderItemHeaders = [
  { title: "PRODUCTO", key: "product.name" },
  { title: "VENDIDO", key: "quantity" },
  { title: "PRECIO", key: "price" },
  { title: "DEVOLVER", key: "returns_quantity" },
  { title: "REEMBOLSO", key: "refund_amount" },
];

/** Indica si el detalle está seleccionado (checkbox marcado). */
const isDetailSelected = (detail) => {
  const orderId = selectedOrderForModal.value?.id;
  if (!orderId) return false;
  const selected = selectedProductsToReturn.value[orderId] || [];
  return selected.some((d) => d.id === detail.id);
};

/**
 * Monto a devolver: si está seleccionado, cantidad_a_devolver * precio_unitario; si no, 0.
 * Usa returnsQuantityByDetailId para que sea reactivo al cambiar la cantidad.
 */
const getRefundAmount = (detail, isSelected) => {
  if (!isSelected) return 0;
  const qty = parseFloat(returnsQuantityByDetailId.value[detail.id] ?? detail?.returns_quantity ?? detail?.quantity ?? 0) || 0;
  const price = parseFloat(detail?.price ?? detail?.unit_price_usd ?? 0) || 0;
  return qty * price;
};

/** Formatea el monto a devolver con la moneda de la orden. */
const formatRefundAmount = (detail, isSelected) => {
  const amount = getRefundAmount(detail, isSelected);
  const currency = selectedOrderForModal.value?.currency || "USD";
  return formatCurrency(amount, currency.toUpperCase());
};

const setReturnsQuantity = (detailId, value) => {
  returnsQuantityByDetailId.value[detailId] = value;
  const order = selectedOrderForModal.value;
  if (order?.details) {
    const d = order.details.find((x) => x.id === detailId);
    if (d) d.returns_quantity = value;
  }
};

const totalRefundAmount = computed(() => {
  if (!selectedOrderForModal.value) return { amount: 0, formatted: formatCurrency(0, "USD") };
  const orderId = selectedOrderForModal.value.id;
  const currency = selectedOrderForModal.value.currency || "USD";
  const selected = selectedProductsToReturn.value[orderId] || [];
  const itemsToSum = Array.isArray(selected) ? selected : [];
  const amount = itemsToSum.reduce((sum, d) => sum + getRefundAmount(d, true), 0);
  return { amount, formatted: formatCurrency(amount, currency.toUpperCase()) };
});
</script>

<template>
  <VCard class="elevation-1 rounded-lg overflow-hidden border-0">
    <!-- View Switcher -->
    <template v-if="mobile">
      <VDataIterator
        :items="props.orders"
        :items-per-page="props.itemsPerPage"
        :loading="props.loading"
      >
        <template v-slot:default="{ items }">
          <div class="pa-2 d-flex flex-column gap-2">
            <VCard
              v-for="item in items"
              :key="item.raw.id"
              variant="flat"
              border
              class="rounded-lg pa-3"
            >
              <div class="d-flex justify-space-between align-start mb-1">
                <div class="d-flex flex-column">
                  <span class="text-caption font-weight-bold text-primary leading-tight">Orden #{{ item.raw.id }}</span>
                  <div class="d-flex align-center gap-1 text-medium-emphasis mt-n1">
                    <VIcon size="12">tabler-calendar</VIcon>
                    <span style="font-size: 0.65rem;">{{ new Date(item.raw.created_at).toISOString().split("T")[0] }}</span>
                  </div>
                </div>
                <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-bold px-1" style="block-size: 18px; font-size: 0.6rem;">
                  {{ item.raw.currency?.toUpperCase() }}
                </VChip>
              </div>

              <div class="text-body-2 font-weight-bold truncate mb-2">
                {{ item.raw.client.name }} {{ item.raw.client.last_name }}
              </div>

              <VDivider class="border-dashed mb-2" />

              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span style="font-size: 0.6rem;" class="text-medium-emphasis text-uppercase font-weight-bold mb-n1">Monto Total</span>
                  <span class="text-subtitle-1 font-weight-black text-success">
                    {{ formatCurrency(parseFloat(item.raw.total_amount), item.raw.currency?.toUpperCase()) }}
                  </span>
                </div>
                <VBtn
                  icon="tabler-package"
                  variant="tonal"
                  color="info"
                  size="32"
                  @click="openProductsModal(item.raw)"
                />
              </div>
            </VCard>
          </div>
        </template>
        <template v-slot:no-data>
          <div class="pa-8 text-center text-medium-emphasis uppercase font-weight-bold text-xs">
            No hay órdenes para mostrar
          </div>
        </template>
      </VDataIterator>

       <!-- Pagination Mobile -->
      <div class="pa-4 border-t d-flex justify-center">
        <VPagination
          v-model="props.page"
          :length="Math.ceil(props.totalOrder / props.itemsPerPage)"
          size="small"
          total-visible="5"
          @update:model-value="(p) => emit('update:options', { ...props, page: p })"
        />
      </div>
    </template>

    <VDataTableServer
      v-else
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.orders"
      :items-length="props.totalOrder"
      :loading="props.loading"
      item-key="id"
      class="text-no-wrap premium-table"
      density="compact"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <!-- ID Column -->
      <template #item.id="{ item }">
        <VChip
          color="primary"
          size="small"
          variant="flat"
          class="font-weight-black shadow-sm"
        >
          #{{ item.id }}
        </VChip>
      </template>

      <!-- Client Column -->
      <template #item.client="{ item }">
        <div class="d-flex align-center gap-2">
          <VAvatar size="24" color="primary-lighten-5" class="border">
            <VIcon icon="tabler-user" size="14" color="primary" />
          </VAvatar>
          <span class="font-weight-bold text-high-emphasis">
            {{ item.client.name }} {{ item.client.last_name }}
          </span>
        </div>
      </template>

      <!-- Amount Column -->
      <template #item.amount="{ item }">
        <span class="font-weight-900 text-success text-subtitle-2 shadow-text">
          {{ formatCurrency(parseFloat(item.total_amount), item.currency?.toUpperCase()) }}
        </span>
      </template>

      <!-- Date Column -->
      <template #item.date="{ item }">
        <div class="d-flex align-center gap-2 text-medium-emphasis">
          <VIcon icon="tabler-calendar" size="16" />
          <span class="font-weight-medium">
            {{ new Date(item.created_at).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }}
          </span>
        </div>
      </template>

      <!-- Action Column -->
      <template #item.products="{ item }">
        <VBtn
          color="info"
          size="small"
          variant="tonal"
          icon
          class="rounded-lg shadow-sm"
          @click.stop="openProductsModal(item)"
        >
          <VIcon icon="tabler-package" />
        </VBtn>
      </template>
    </VDataTableServer>

    <!-- Dialog Details -->
    <VDialog
      v-model="showProductsModal"
      :max-inline-size="mobile ? '100%' : '1000'"
      :fullscreen="mobile"
      persistent
      transition="dialog-bottom-transition"
      class="premium-dialog"
      @click:outside="closeProductsModal"
    >
      <VCard v-if="selectedOrderForModal" class="detail-dialog-card overflow-hidden border-0 elevation-12">
        <!-- Header Premium -->
        <VCardTitle class="pa-0">
          <div class="header-gradient pa-4 d-flex align-center shadow-sm">
            <div class="d-flex align-center">
              <VAvatar color="white" variant="flat" size="40" class="me-4 elevation-2 shadow-primary-lg">
                <VIcon icon="tabler-package" color="primary" size="24" />
              </VAvatar>
              <div>
                <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">Detalles de la Orden</h2>
                <div class="d-flex align-center gap-2 mt-1">
                  <VChip size="x-small" color="white" variant="flat" class="text-primary font-weight-black px-2">
                    #{{ selectedOrderForModal.id }}
                  </VChip>
                  <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                    {{ selectedOrderForModal.client?.name }} {{ selectedOrderForModal.client?.last_name }}
                  </span>
                </div>
              </div>
            </div>
            <VSpacer />
            <VBtn
              icon
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg"
              @click="closeProductsModal"
            >
              <VIcon>tabler-x</VIcon>
            </VBtn>
          </div>
        </VCardTitle>

        <VCardText class="pa-0 bg-light">
          <template v-if="mobile">
            <!-- Mobile Detail List -->
            <div class="pa-3 d-flex flex-column gap-3 overflow-y-auto" style="max-block-size: calc(100vh - 220px);">
              <VCard
                v-for="detailItem in (selectedOrderForModal.details || [])"
                :key="detailItem.id"
                variant="flat"
                border
                class="rounded-lg pa-3 bg-white elevation-1 border-l-primary"
                :class="isDetailSelected(detailItem) ? 'selected-item-card' : ''"
              >
                <div class="d-flex align-center gap-3 mb-2">
                  <VCheckbox
                    v-model="selectedProductsToReturn[selectedOrderForModal.id]"
                    :value="detailItem"
                    density="compact"
                    hide-details
                    color="primary"
                    class="mt-n1"
                  />
                  <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                    <span class="text-body-2 font-weight-black truncate text-high-emphasis leading-tight">{{ detailItem.product?.name }}</span>
                    <span class="text-super-xs font-weight-bold text-disabled uppercase">{{ detailItem.product?.laboratory?.name }}</span>
                  </div>
                </div>

                <div class="d-flex justify-space-between align-center mb-2 px-1">
                  <div class="d-flex flex-column">
                    <span class="text-super-xs font-weight-bold text-disabled uppercase">Vendido</span>
                    <span class="text-xs font-weight-black">{{ detailItem.quantity }} unid.</span>
                  </div>
                  <div class="d-flex flex-column text-end">
                    <span class="text-super-xs font-weight-bold text-disabled uppercase">Precio</span>
                    <span class="text-xs font-weight-black">{{ formatCurrency(parseFloat(detailItem.price), selectedOrderForModal.currency) }}</span>
                  </div>
                </div>

                <VDivider class="border-dashed mb-2" />

                <div class="d-flex justify-space-between align-center px-1">
                   <div class="d-flex flex-column" style="max-inline-size: 110px;">
                    <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1">Devolver</span>
                    <AppTextField
                      :model-value="returnsQuantityByDetailId[detailItem.id] ?? detailItem.returns_quantity ?? detailItem.quantity"
                      type="number"
                      min="0"
                      density="compact"
                      variant="outlined"
                      hide-details
                      class="compact-qty-input shadow-sm"
                      :max="detailItem.quantity"
                      @update:model-value="(v) => setReturnsQuantity(detailItem.id, v)"
                    />
                  </div>
                  <div class="d-flex flex-column text-end">
                    <span class="text-super-xs font-weight-black text-disabled uppercase mb-1">Reembolso</span>
                    <span class="text-subtitle-2 font-weight-900 text-primary shadow-text">
                      {{ formatRefundAmount(detailItem, isDetailSelected(detailItem)) }}
                    </span>
                  </div>
                </div>
              </VCard>
            </div>
          </template>

          <!-- Desktop Detail Table -->
          <VDataTable
            v-else
            v-model="selectedProductsToReturn[selectedOrderForModal.id]"
            :headers="orderItemHeaders"
            :items="selectedOrderForModal.details || []"
            item-key="id"
            item-value="id"
            return-object
            hide-default-footer
            class="premium-table"
            show-select
          >
            <template #item.product.name="{ item: detailItem }">
              <div class="d-flex flex-column py-2">
                <span class="text-subtitle-2 font-weight-black text-high-emphasis leading-tight">
                  {{ detailItem.product ? detailItem.product.name : "N/A" }}
                </span>
                <span
                  v-if="detailItem.product?.laboratory?.name"
                  class="text-super-xs font-weight-bold text-disabled uppercase mt-1"
                >
                  <VIcon icon="tabler-building-factory" size="10" class="me-1" />
                  {{ detailItem.product.laboratory.name }}
                </span>
              </div>
            </template>

            <template #item.quantity="{ item: detailItem }">
              <VChip size="x-small" color="secondary" variant="tonal" class="font-weight-black">
                {{ detailItem.quantity }} unid.
              </VChip>
            </template>

            <template #item.price="{ item: detailItem }">
              <span class="font-weight-bold">
                {{ formatCurrency(parseFloat(detailItem.price), selectedOrderForModal.currency) }}
              </span>
            </template>

            <template #item.returns_quantity="{ item: detailItem }">
              <div class="pa-1 rounded-lg bg-light" style="max-inline-size: 100px;">
                <AppTextField
                  :model-value="returnsQuantityByDetailId[detailItem.id] ?? detailItem.returns_quantity ?? detailItem.quantity"
                  type="number"
                  min="0"
                  density="compact"
                  variant="plain"
                  hide-details
                  class="text-center font-weight-black compact-qty-input-text font-bold"
                  :max="detailItem.quantity"
                  :disabled="detailItem.quantity === 0"
                  @update:model-value="(v) => setReturnsQuantity(detailItem.id, v)"
                />
              </div>
            </template>

            <template #item.refund_amount="{ item: detailItem }">
              <span class="font-weight-900 text-primary text-subtitle-2 shadow-text">
                {{ formatRefundAmount(detailItem, isDetailSelected(detailItem)) }}
              </span>
            </template>
          </VDataTable>

          <!-- Total Summary Banner -->
          <div
            v-if="selectedProductsToReturn[selectedOrderForModal.id]?.length"
            class="pa-4 mx-4 my-2 d-flex justify-end align-center bg-primary-lighten-5 rounded-lg border-dashed-2 shadow-inner"
          >
            <div class="d-flex align-center gap-3">
              <VAvatar color="primary" size="32" variant="tonal" class="elevation-1">
                <VIcon icon="tabler-cash-banknote" size="20" />
              </VAvatar>
              <div class="text-end">
                <span class="text-super-xs font-weight-black text-primary uppercase d-block leading-none mb-1">Crédito a favor del cliente</span>
                <span class="text-h6 font-weight-950 text-primary leading-none">
                  {{ totalRefundAmount.formatted }}
                </span>
              </div>
            </div>
          </div>
        </VCardText>

        <VCardActions class="pa-4 bg-light border-t">
          <VRow dense class="w-100 ma-0">
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="secondary"
                variant="tonal"
                size="large"
                block
                height="52"
                class="font-weight-black rounded-lg text-button uppercase"
                @click="closeProductsModal"
              >
                Cancelar
              </VBtn>
            </VCol>
            <VCol cols="12" sm="6" class="pa-1">
              <VBtn
                color="warning"
                variant="flat"
                size="large"
                block
                height="52"
                class="font-weight-black rounded-lg shadow-warning-lg text-button uppercase"
                :disabled="!selectedProductsToReturn[selectedOrderForModal.id]?.length"
                @click="handleReturnSelectedProducts(selectedOrderForModal)"
              >
                <VIcon icon="tabler-arrow-back-up" class="me-2" />
                Devolver ({{ selectedProductsToReturn[selectedOrderForModal.id]?.length || 0 }})
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.premium-table :deep(.v-table__wrapper) {
  border-radius: 8px !important;
}

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.73rem !important;
  font-weight: 950 !important;
  text-transform: uppercase !important;
}

.premium-table :deep(td) {
  padding-block: 8px !important;
}

.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}

.shadow-sm {
  box-shadow: 0 2px 4px rgba(0, 0, 0, 5%) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 25%) !important;
}

.shadow-warning-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-warning), 25%) !important;
}

.shadow-inner {
  box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 6%) !important;
}

.shadow-text {
  text-shadow: 0 1px 2px rgba(0, 0, 0, 5%);
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 20%) !important;
}

.border-l-primary {
  border-inline-start: 4px solid rgb(var(--v-theme-primary)) !important;
}

.selected-item-card {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 0.08) !important;
}

.letter-spacing-1 {
  letter-spacing: 1.5px !important;
}

.leading-none {
  line-height: 1 !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.font-weight-900 {
  font-weight: 900 !important;
}

.font-weight-950 {
  font-weight: 950 !important;
}

.compact-qty-input :deep(.v-field__input) {
  font-weight: 900 !important;
  padding-block: 4px !important;
}

.compact-qty-input-text :deep(input) {
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 1.1rem !important;
  font-weight: 950 !important;
  text-align: center !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.v-btn--size-large {
  font-size: 0.9rem !important;
  letter-spacing: 0.5px;
}
</style>
