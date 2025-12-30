<script setup>
import { toast } from "@/plugins/sweetalert";
import { formatCurrency } from "@/utils/currencyFormatter";
import Swal from "sweetalert2";
import { ref, watch } from "vue";
import SelectLotForReturnDialog from "@/components/dialogs/SelectLotForReturnDialog.vue";

const props = defineProps({
  orders: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalOrder: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  isVendedor: { type: Boolean, required: true },
});
const emit = defineEmits(["update:options", "return-product"]);
const expanded = ref([]);
const selectedProductsToReturn = ref({});

const showLotDialog = ref(false);
const pendingReturnData = ref(null);

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

const handleReturnProduct = (detailItem, order) => {
  const quantity = parseFloat(detailItem.returns_quantity);

  if (isNaN(quantity) || quantity <= 0) {
    toast.warning("La cantidad a devolver debe ser mayor a cero.");
    return;
  }

  if (quantity > detailItem.quantity) {
    toast.warning(
      "La cantidad a devolver no puede ser mayor que la cantidad vendida."
    );
    return;
  }

  // Guardar datos pendientes y abrir diálogo de selección de lote
  pendingReturnData.value = {
    product: detailItem.product,
    order: order,
    returns_quantity: quantity,
  };
  showLotDialog.value = true;
};

const handleLotSelected = (selectedLot) => {
  if (!pendingReturnData.value) {
    console.error("No hay datos pendientes de devolución");
    return;
  }

  if (!selectedLot || !selectedLot.id) {
    console.error("Lote seleccionado inválido:", selectedLot);
    toast.error("El lote seleccionado no es válido.");
    return;
  }

  // Emitir directamente la devolución sin confirmación adicional
  emit("return-product", {
    product: pendingReturnData.value.product,
    order: pendingReturnData.value.order,
    returns_quantity: pendingReturnData.value.returns_quantity,
    product_lot_id: selectedLot.id,
  });

  pendingReturnData.value = null;
};

const handleReturnSelectedProducts = async (order) => {
  const selected = selectedProductsToReturn.value[order.id];

  if (!selected || selected.length === 0) {
    toast.warning("Por favor, seleccione al menos un producto para devolver.");
    return;
  }

  const itemsToReturn = selected.map((selectedItem) => {
    const upToDateProduct = order.details.find(
      (detail) => detail.id === selectedItem
    );
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

  // Si solo hay un producto, mostrar diálogo de selección de lote
  if (validItemsToReturn.length === 1) {
    pendingReturnData.value = validItemsToReturn[0];
    showLotDialog.value = true;
    return;
  }

  // Para múltiples productos, mostrar advertencia
  Swal.fire({
    title: "Devolución múltiple",
    text: "Para devoluciones múltiples, debe seleccionar el lote para cada producto individualmente.",
    icon: "info",
    confirmButtonText: "Entendido",
  });
};

const headers = [
  { title: "N° Orden", key: "id", sortable: true, width: "100px" },
  { title: "Cliente", key: "client", sortable: true },
  { title: "Monto", key: "amount", sortable: true },
  { title: "Fecha", key: "date", sortable: true },
  { title: "Productos", key: "data-table-expand", sortable: false },
];

const orderItemHeaders = [
  { title: "Producto", key: "product.name" },
  { title: "Cantidad", key: "quantity" },
  { title: "Precio", key: "price" },
  { title: "Cantidad a Devolver", key: "returns_quantity" },
];
</script>
<template>
  <VCard>
    <VDataTableServer
      v-model:expanded="expanded"
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
      show-expand
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
      <template
        v-slot:item.data-table-expand="{
          internalItem,
          isExpanded,
          toggleExpand,
        }"
      >
        <v-btn
          :append-icon="
            isExpanded(internalItem) ? 'mdi-chevron-up' : 'mdi-chevron-down'
          "
          :text="
            isExpanded(internalItem) ? 'Menos información' : 'Más información'
          "
          class="text-none"
          color="medium-emphasis"
          size="small"
          variant="text"
          width="205"
          border
          slim
          @click="toggleExpand(internalItem)"
        ></v-btn>
      </template>

      <template v-slot:expanded-row="{ columns, item }">
        <tr>
          <td :colspan="columns.length">
            <VCard flat class="my-4">
              <VCardText>
                <VDataTable
                  v-model="selectedProductsToReturn[item.id]"
                  :headers="orderItemHeaders"
                  :items="item.details"
                  item-key="id"
                  hide-default-footer
                  class="elevation-1"
                  show-select
                >
                  <template #item.product.name="{ item: detailItem }">
                    <div class="d-flex align-center gap-x-4">
                      <div class="d-flex flex-column">
                        <span
                          class="text-body-1 font-weight-medium text-high-emphasis"
                        >
                          {{
                            detailItem.product ? detailItem.product.name : "N/A"
                          }}
                        </span>
                        <span class="text-sm text-disabled">
                          {{
                            detailItem.product
                              ? detailItem.product.active_ingredient
                              : "N/A"
                          }}
                        </span>
                        <span class="text-sm text-disabled">
                          {{
                            detailItem.product && detailItem.product.laboratory
                              ? detailItem.product.laboratory.name
                              : "N/A"
                          }}
                        </span>
                      </div>
                    </div>
                  </template>
                  <template #item.quantity="{ item: detailItem }">
                    {{ detailItem.quantity }}
                  </template>
                  <template #item.price="{ item: detailItem }">
                    {{
                      formatCurrency(
                        parseFloat(detailItem.price),
                        detailItem.currency
                      )
                    }}
                  </template>
                  <template #item.returns_quantity="{ item: detailItem }">
                    <VTextField
                      v-model="detailItem.returns_quantity"
                      type="number"
                      min="0"
                      density="compact"
                      variant="outlined"
                      hide-details
                      single-line
                      style="max-width: 90px; min-width: 90px"
                      class="my-2 quantity-input-field"
                      :max="detailItem.quantity"
                      :disabled="detailItem.quantity === 0"
                    />
                  </template>
                </VDataTable>
                <div class="d-flex justify-end mt-4">
                  <VBtn
                    color="warning"
                    size="large"
                    :disabled="selectedProductsToReturn[item.id].length === 0"
                    @click="handleReturnSelectedProducts(item)"
                  >
                    Devolver ({{ selectedProductsToReturn[item.id].length }})
                  </VBtn>
                </div>
              </VCardText>
            </VCard>
          </td>
        </tr>
      </template>
    </VDataTableServer>

    <SelectLotForReturnDialog
      v-model="showLotDialog"
      :product="pendingReturnData?.product || {}"
      @select-lot="handleLotSelected"
    />
  </VCard>
</template>
