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
  { title: "N° Orden", key: "id", sortable: true, width: "100px" },
  { title: "Cliente", key: "client", sortable: true },
  { title: "Monto", key: "amount", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Productos", key: "products", sortable: false, width: "80px" },
];

const orderItemHeaders = [
  { title: "Producto", key: "product.name" },
  { title: "Cantidad", key: "quantity" },
  { title: "Precio", key: "price" },
  { title: "Cantidad a Devolver", key: "returns_quantity" },
  { title: "Monto a devolver", key: "refund_amount" },
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
  <VCard>
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
          <div class="pa-8 text-center text-medium-emphasis">
            No hay órdenes para mostrar
          </div>
        </template>
      </VDataIterator>

       <!-- Paginación Móvil -->
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
      class="text-no-wrap"
      fixed-header
      height="auto"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.client="{ item }">
        <span class="font-weight-medium">
          {{ item.client.name }} {{ item.client.last_name }}
        </span>
      </template>
      <template #item.amount="{ item }">
        <span class="font-weight-medium">{{
          formatCurrency(
            parseFloat(item.total_amount),
            item.currency?.toUpperCase()
          )
        }}</span>
      </template>
      <template #item.date="{ item }">
        <span class="font-weight-medium">{{
          new Date(item.created_at).toISOString().split("T")[0]
        }}</span>
      </template>
      <template #item.products="{ item }">
        <VBtn
          icon
          color="medium-emphasis"
          size="small"
          variant="text"
          @click="openProductsModal(item)"
        >
          <VIcon icon="tabler-package" />
        </VBtn>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="showProductsModal"
      :max-width="mobile ? '100%' : '800'"
      :fullscreen="mobile"
      persistent
      transition="dialog-bottom-transition"
      @click:outside="closeProductsModal"
    >
      <VCard v-if="selectedOrderForModal">
        <VCardTitle class="d-flex align-center pa-4">
          <VIcon icon="tabler-package" start />
          <span>Orden #{{ selectedOrderForModal.id }}</span>
          <VSpacer />
          <span class="text-body-2 text-medium-emphasis">
            {{ selectedOrderForModal.client?.name }} {{ selectedOrderForModal.client?.last_name }}
          </span>
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-0">
          <template v-if="mobile">
            <div class="pa-3 d-flex flex-column gap-3 overflow-y-auto" style="max-block-size: calc(100vh - 200px);">
              <VCard
                v-for="detailItem in (selectedOrderForModal.details || [])"
                :key="detailItem.id"
                variant="flat"
                border
                class="rounded-lg pa-3"
                :color="isDetailSelected(detailItem) ? 'primary-lighten-5' : ''"
              >
                <div class="d-flex align-center gap-3 mb-2">
                  <VCheckbox
                    v-model="selectedProductsToReturn[selectedOrderForModal.id]"
                    :value="detailItem"
                    density="compact"
                    hide-details
                    class="mt-n1"
                  />
                  <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                    <span class="text-body-2 font-weight-bold truncate">{{ detailItem.product?.name }}</span>
                    <span class="text-caption text-medium-emphasis">{{ detailItem.product?.laboratory?.name }}</span>
                  </div>
                </div>

                <div class="d-flex justify-space-between align-center mb-2 text-caption">
                  <div>
                    <span class="text-medium-emphasis">Vendido:</span>
                    <span class="ms-1 font-weight-bold">{{ detailItem.quantity }}</span>
                  </div>
                  <div>
                    <span class="text-medium-emphasis">Precio:</span>
                    <span class="ms-1 font-weight-bold">{{ formatCurrency(parseFloat(detailItem.price), selectedOrderForModal.currency) }}</span>
                  </div>
                </div>

                <VDivider class="border-dashed mb-2" />

                <div class="d-flex justify-space-between align-center">
                   <div class="d-flex flex-column" style="max-inline-size: 100px;">
                    <span style="font-size: 0.6rem;" class="text-uppercase font-weight-bold text-medium-emphasis">Devolver</span>
                    <VTextField
                      :model-value="returnsQuantityByDetailId[detailItem.id] ?? detailItem.returns_quantity ?? detailItem.quantity"
                      type="number"
                      min="0"
                      density="compact"
                      variant="outlined"
                      hide-details
                      :max="detailItem.quantity"
                      @update:model-value="(v) => setReturnsQuantity(detailItem.id, v)"
                    />
                  </div>
                  <div class="d-flex flex-column text-end">
                    <span style="font-size: 0.6rem;" class="text-uppercase font-weight-bold text-medium-emphasis">Reembolso</span>
                    <span class="text-subtitle-2 font-weight-black text-primary">
                      {{ formatRefundAmount(detailItem, isDetailSelected(detailItem)) }}
                    </span>
                  </div>
                </div>
              </VCard>
            </div>
          </template>

          <VDataTable
            v-else
            v-model="selectedProductsToReturn[selectedOrderForModal.id]"
            :headers="orderItemHeaders"
            :items="selectedOrderForModal.details || []"
            item-key="id"
            item-value="id"
            return-object
            hide-default-footer
            class="elevation-0"
            show-select
          >
            <template #item.product.name="{ item: detailItem }">
              <div class="d-flex flex-column">
                <span class="text-body-2 font-weight-medium">
                  {{ detailItem.product ? detailItem.product.name : "N/A" }}
                </span>
                <span
                  v-if="detailItem.product?.laboratory?.name"
                  class="text-caption text-medium-emphasis"
                >
                  {{ detailItem.product.laboratory.name }}
                </span>
              </div>
            </template>
            <template #item.quantity="{ item: detailItem }">
              {{ detailItem.quantity }}
            </template>
            <template #item.price="{ item: detailItem }">
              {{ formatCurrency(parseFloat(detailItem.price), detailItem.currency) }}
            </template>
            <template #item.returns_quantity="{ item: detailItem }">
              <VTextField
                :model-value="returnsQuantityByDetailId[detailItem.id] ?? detailItem.returns_quantity ?? detailItem.quantity"
                type="number"
                min="0"
                density="compact"
                variant="outlined"
                hide-details
                style="max-inline-size: 90px;"
                :max="detailItem.quantity"
                :disabled="detailItem.quantity === 0"
                @update:model-value="(v) => setReturnsQuantity(detailItem.id, v)"
              />
            </template>
            <template #item.refund_amount="{ item: detailItem }">
              <span class="font-weight-medium text-primary">
                {{ formatRefundAmount(detailItem, isDetailSelected(detailItem)) }}
              </span>
            </template>
          </VDataTable>

          <VDivider />

          <div
            v-if="selectedProductsToReturn[selectedOrderForModal.id]?.length"
            class="pa-4 d-flex justify-end align-center text-h6"
          >
            <span class="text-medium-emphasis me-2">Total a devolver:</span>
            <span class="font-weight-bold text-primary">
              {{ totalRefundAmount.formatted }}
            </span>
          </div>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 d-flex gap-3">
          <VBtn
            color="secondary"
            variant="outlined"
            size="large"
            class="flex-grow-1"
            style="flex: 1 1 50%;"
            @click="closeProductsModal"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="warning"
            size="large"
            class="flex-grow-1"
            style="flex: 1 1 50%;"
            :disabled="
              !selectedProductsToReturn[selectedOrderForModal.id]?.length
            "
            @click="handleReturnSelectedProducts(selectedOrderForModal)"
          >
            <VIcon icon="tabler-arrow-back-up" start />
            Devolver ({{ selectedProductsToReturn[selectedOrderForModal.id]?.length || 0 }}) - {{ totalRefundAmount.formatted }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
