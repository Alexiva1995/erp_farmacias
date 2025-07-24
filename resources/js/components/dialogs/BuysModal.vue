<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";
import { onMounted, onBeforeUnmount } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  orderData: {
    type: Object,
    default: () => ({}),
  },
  totalAmount: {
    type: Number,
    default: 0,
  },
  selectedCurrency: {
    type: String,
    default: "COP",
  },
  orderProducts: {
    type: Array,
    default: () => [],
  },
    selectedDisplayCurrency: {
    type: String,
    default: "COP",
  },
});

const emit = defineEmits([
  "update:isDialogVisible",
  "purchase-completed",
  "modal-closed",
]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const currentProgress = ref(0);
const progressStages = [0, 50, 100];
const currentStageIndex = ref(0);

const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const closeModal = () => {
  dialogVisible.value = false;
  emit("modal-closed");
  resetProgress();
};

const handleCompletePurchase = () => {
  if (currentProgress.value < 100) {
    currentStageIndex.value++;
    if (currentStageIndex.value < progressStages.length) {
      currentProgress.value = progressStages[currentStageIndex.value];
    } else {
      currentProgress.value = 100; // Asegura que no se pase de 100
    }
  } else {
    console.log("Completando compra...");
    console.log("Datos de la orden:", props.orderData);
    console.log("Monto total:", props.totalAmount, props.selectedCurrency);

    emit("purchase-completed", props.orderData.id);
    dialogVisible.value = false;
    resetProgress();
  }
};

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
};

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      resetProgress();
    }
  }
);

const getProductPrice = (product, currency) => {
  const taxRate = product.taxRate || 0;
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    // Default to USD price
    basePrice = product.price || 0;
  }

  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};


const progressDetailText = computed(() => {
    switch (currentProgress.value) {
        case 0:
            return "Detalles de compra";
        case 50:
            return "Métodos de pago";
        case 100:
            return "Ticke";
        default:
            return "";
    }
});

const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.orderProducts.forEach((product) => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">Compra</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />

      <div class="demo-space-y px-4 pt-4">
        <VProgressLinear
          v-model="currentProgress"
          color="primary"
          height="10"
          rounded
        />
        <p class="text-center mt-2 text-subtitle-2 text-medium-emphasis">
                    {{ progressDetailText }}
                </p>
      </div>

      <VCardText v-if="currentProgress === 0">
       <div class="d-flex flex-wrap justify-space-between">
           <p class="font-weight-bold text-h6">Total de productos:</p>
          <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium">{{
                totalSelectedQuantity
              }}</span>
            </VChip>
       </div>

        <div
            class="scrollable-list-container"
            :class="{ 'show-scroll': props.orderProducts.length > 2 }"
          >
            <VList class="card-list" density="compact" nav>
              <VListItem
                v-for="product in props.orderProducts"
                :key="product.id"
                class="rounded-0"
              >
                <template #prepend>
                  <span>{{ product.selectedQuantity }} x</span>
                </template>

                <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                  product.title
                }}</VListItemTitle>
                <VListItemSubtitle class="mx-2"
                  >{{ product.active_ingredient }}
                  {{ product.laboratory }}</VListItemSubtitle
                >

                <template #append>
                  <div class="d-flex align-center">
                    <span class="text-body-1 me-2">{{
                      formatCurrency(
                        getProductPrice(
                          product,
                          props.selectedDisplayCurrency
                        ) * product.selectedQuantity,
                        props.selectedDisplayCurrency
                      )
                    }}</span>
                  </div>
                </template>
              </VListItem>
            </VList>
          </div>
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-4">
            Total a pagar:
          </p>
          <p class="font-weight-bold text-h6 mt-4">
            {{ totalAmount }} {{ selectedCurrency }}
          </p>
        </div>
      </VCardText>

      <VCardText v-else-if="currentProgress === 50">
        <p class="font-weight-bold text-h6">Selecciona Método de Pago:</p>
        <div class="my-4">
          <VRadioGroup>
            <VRadio label="Tarjeta de Crédito" value="credit_card"></VRadio>
            <VRadio
              label="Transferencia Bancaria"
              value="bank_transfer"
            ></VRadio>
            <VRadio label="Pago Móvil" value="mobile_payment"></VRadio>
          </VRadioGroup>
        </div>
      </VCardText>

      <VCardText v-else-if="currentProgress === 100">
        <p class="font-weight-bold text-h6">Confirma tu Compra:</p>
        <p class="text-success text-body-1">
          Estás a punto de finalizar la compra.
        </p>
        <p class="mt-2">
          Total Final: **{{ totalAmount }} {{ selectedCurrency }}**
        </p>
        <p class="text-caption mt-2">
          Haz clic en "Finalizar Compra" para completar.
        </p>
      </VCardText>

      <VCardActions class="p-4 d-flex flex-wrap justify-space-between">
        <VBtn color="secondary" variant="outlined" @click="closeModal" class="flex-grow-1">
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="handleCompletePurchase" class="flex-grow-1">
          {{ continueButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
<style scoped>
.scrollable-list-container {
  max-height: 95px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto;
}
</style>
