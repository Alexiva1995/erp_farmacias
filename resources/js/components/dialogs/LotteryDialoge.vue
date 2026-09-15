<script setup lang="js">
import axios from '@/plugins/axios'
import { ref } from 'vue'
import { useDisplay } from 'vuetify'
import OrderViewModal from './OrderViewModal.vue'

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  lista: { type: Array, default: () => [] },
})

const emit = defineEmits(["modalClose", "viewOrder"])
const { mobile } = useDisplay()

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

const toTitleCase = (str) => {
  if (!str) return 'Cliente no disponible';
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

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
    max-width="680px"
    width="680px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="close"
  >
    <VCard v-if="props.modalFormulario" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'">
      <!-- Cabecera Premium con Gradiente -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary font-weight-black">
            <VIcon icon="tabler-trophy" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Ganadores del Sorteo
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                {{ props.lista.length }} Ganador{{ props.lista.length === 1 ? '' : 'es' }} Seleccionado{{ props.lista.length === 1 ? '' : 's' }}
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="close"
          />
        </div>
      </VCardTitle>

      <!-- Lista de Ganadores -->
      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <div class="d-flex flex-column gap-2">
          <div
            v-for="(item, index) in props.lista"
            :key="index"
            class="d-flex align-center justify-space-between pa-3 rounded-lg border winner-card bg-surface"
          >
            <div class="d-flex align-center gap-3 min-width-0">
              <VAvatar
                :color="index === 0 ? 'warning' : index === 1 ? 'secondary' : 'primary'"
                variant="tonal"
                size="36"
                class="font-weight-black rounded-lg"
              >
                {{ index + 1 }}
              </VAvatar>
              <div class="d-flex flex-column min-width-0">
                <span class="text-sm font-weight-medium text-high-emphasis truncate">
                  {{ toTitleCase(item.client) }}
                </span>
                <div class="d-flex align-center gap-2 mt-1 flex-wrap">
                  <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                    Orden #{{ item.order_id }}
                  </VChip>
                  <span v-if="item.phone" class="text-xs text-medium-emphasis d-flex align-center gap-1">
                    <VIcon icon="tabler-phone" size="13" />
                    {{ item.phone }}
                  </span>
                </div>
              </div>
            </div>

            <IconBtn
              color="primary"
              size="small"
              @click.stop="handleViewOrder(item.order_id)"
            >
              <VIcon icon="tabler-eye" size="18" />
              <VTooltip activator="parent">Ver Detalle de Orden</VTooltip>
            </IconBtn>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Footer -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              prepend-icon="tabler-x"
              class="font-weight-bold rounded-lg text-button uppercase"
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

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.winner-card {
  border-color: rgba(var(--v-border-color), 0.12) !important;
  transition: all 0.2s ease;
}

.winner-card:hover {
  border-color: rgba(var(--v-theme-primary), 0.3) !important;
  background-color: rgba(var(--v-theme-primary), 0.02) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
