<script setup lang="js">
import axios from '@/plugins/axios'
import { ref } from 'vue'
import OrderViewModal from './OrderViewModal.vue'

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  lista: { type: Array, default: () => [] },
})

const emit = defineEmits(["modalClose", "viewOrder"])

// Define reactive variables
const orderData = ref(null)
const currency = ref('')
const orderItems = ref([])
const paymentsForPrint = ref([])
const changeAmountForPrint = ref(0)
const amountForPrint = ref(0)
const creditAmountForPrint = ref(0)
const creditForPrint = ref(false)
const viewModal = ref(false)

function close() {
  emit("modalClose", false)
}

function viewOrder(order) {
  emit("viewOrder", order)
}

const handleViewOrder = async (orderId) => {
  try {
    const response = await axios.get(`/tpv/orders/${orderId}/print`);
    if (response.data && response.data.data && response.data.data.order) {
      orderData.value = response.data.data.order;
      currency.value = response.data.data.order.currency.toUpperCase();
      orderItems.value = response.data.data.order.details.map((detail) => ({
        title: detail.product.name,
        selectedQuantity: detail.quantity,
        taxRate: detail.product.iva,
        price_bs: parseFloat(detail.price),
        price_cop: parseFloat(detail.price),
        price: parseFloat(detail.price),
        laboratory: detail.product.laboratory?.name,
      }));
      paymentsForPrint.value = response.data.data.order.payment_methods;
      changeAmountForPrint.value = parseFloat(
        response.data.data.order.money_returns
      );
      amountForPrint.value = parseFloat(response.data.data.order.total_amount);
      creditAmountForPrint.value = response.data.data.hasCreditPayment
        ? parseFloat(response.data.data.order.total_amount)
        : 0;
      creditForPrint.value = response.data.data.hasCreditPayment;
      viewModal.value = true;

    } else {
      console.error("Respuesta de API con formato incorrecto:", response.data);
      console.error("La respuesta del servidor no tiene el formato esperado.");
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
    console.error("Error al obtener los detalles de la orden.");
  }
};
</script>

<template>
  <OrderViewModal
    v-model:isDialogVisible="viewModal"
    :order-data="orderData"
    :order-products="orderItems"
    :total-amount="amountForPrint"
    :selected-currency="currency"
    :payments="paymentsForPrint"
    :change-amount="changeAmountForPrint"
    :credit-amount="creditAmountForPrint"
    :credit="creditForPrint"
    @close="handleCloseViewModal"
  />

  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"
          >Total de ganadores: {{ props.lista.length }}</span
        >
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />

      <VList lines="two">
        <VListItem v-for="(item, index) in props.lista" :key="item.id">
          <VListItemTitle class="d-flex justify-space-between align-center">
            <div>
              <span class="font-weight-bold">{{
                item.client || "Cliente no disponible"
              }}</span>
              <VChip size="small" color="primary" variant="flat" class="ml-2">
                Orden #{{ item.order_id }}
              </VChip>
            </div>
            <VBtn
              icon
              size="small"
              variant="text"
              color="info"
              @click.stop="handleViewOrder(item.order_id)"
            >
              <VIcon size="20">tabler-eye</VIcon>
            </VBtn>
          </VListItemTitle>
        </VListItem>
      </VList>
      <VDivider />
      <VCardActions>
        <VSpacer />
        <VContainer>
          <VRow justify="end">
            <VCol cols="12" sm="6" md="4" lg="3">
              <VBtn
                color="secondary"
                variant="outlined"
                @click="close"
                width="100%"
              >
                Cerrar
              </VBtn>
            </VCol>
          </VRow>
        </VContainer>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
