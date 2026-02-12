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
    }
  } catch (error) {
    console.error("Error al obtener los detalles de la orden:", error);
  }
};

const handleCloseViewModal = () => {
  viewModal.value = false;
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

  <VDialog
    :model-value="props.modalFormulario"
    max-width="700px"
    persistent
    scrollable
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="close"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-trophy" size="24" color="white" />
          <span class="text-h6 text-white">
            Ganadores del Sorteo ({{ props.lista.length }})
          </span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VList lines="two" density="compact" class="rounded border">
          <template v-for="(item, index) in props.lista" :key="index">
            <VListItem>
              <template #prepend>
                <VAvatar
                  :color="index === 0 ? 'warning' : index === 1 ? 'secondary' : 'info'"
                  variant="tonal"
                  size="40"
                >
                  <span class="text-body-2 font-weight-bold">{{ index + 1 }}</span>
                </VAvatar>
              </template>
              <VListItemTitle class="text-body-2 font-weight-medium">
                {{ item.client || "Cliente no disponible" }}
              </VListItemTitle>
              <VListItemSubtitle class="text-caption">
                <VChip size="x-small" color="primary" variant="tonal" class="me-1">
                  Orden #{{ item.order_id }}
                </VChip>
              </VListItemSubtitle>
              <template #append>
                <IconBtn color="info" @click.stop="handleViewOrder(item.order_id)">
                  <VIcon icon="tabler-eye" size="20" />
                </IconBtn>
              </template>
            </VListItem>
            <VDivider v-if="index < props.lista.length - 1" />
          </template>
        </VList>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="12" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="close"
            >
              Cerrar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
