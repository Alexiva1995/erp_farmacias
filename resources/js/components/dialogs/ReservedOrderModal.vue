<script setup>
import { defineProps } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  order: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const getProductPriceSinIva = (product, currency) => {

  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.sale_price || 0;
  }

  let priceSinIva = basePrice;
  if (currency === "COP") {
    priceSinIva = roundUpToNearestHundred(priceSinIva);
  }
  return priceSinIva;
};

const getIva = (product, currency) => {
  let taxRate = (product.iva == 1) ? 0.16 : 0; 
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.sale_price || 0;
  }
  let Iva = basePrice * taxRate;
  if (currency === "COP") {
    Iva = roundUpToNearestHundred(Iva);
  }
  return Iva;
};

const getProductPrice = (product, currency) => {
  const taxRate = (product.iva == 1) ? 0.16 : 0; 
  let basePrice = 0;
  if (currency === "BS") {
    basePrice = product.price_bs || 0;
  } else if (currency === "COP") {
    basePrice = product.price_cop || 0;
  } else {
    basePrice = product.sale_price || 0;
  }
  let priceWithIva = basePrice * (1 + taxRate);
  if (currency === "COP") {
    priceWithIva = roundUpToNearestHundred(priceWithIva);
  }
  return priceWithIva;
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="700px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline me-2">Order N°{{ props.order.id }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div
          class="scrollable-list-container"
          :class="{ 'show-scroll': props.order.details.length > 2 }"
        >
         <p class="font-weight-bold text-h6">Total de productos:</p>

          <VList class="card-list" density="compact" nav>
            <VListItem
              v-for="product in props.order.details"
              :key="product.id"
              class="rounded-0"
            >
              <template #prepend>
                <span>{{ product.quantity }} x</span>
              </template>

              <VListItemTitle class="font-weight-medium me-4 mx-2">{{
                product.product.name
              }}</VListItemTitle>
              <VListItemSubtitle class="mx-2"
                >{{ product.product.active_ingredient }}
                {{ product.product.laboratory.name }}</VListItemSubtitle
              >

              <template #append>
                <div class="d-flex align-center">
                  <div class="d-flex flex-column align-end me-4">
                    <span class="text-body-2 text-medium-emphasis">Precio</span>
                    <span class="text-body-1 me-2">{{formatCurrency(getProductPriceSinIva(product.product, props.order.currency) * product.quantity, props.order.currency)}}</span>
                  </div>

                  <div class="d-flex flex-column align-end me-4">
                    <span class="text-body-2 text-medium-emphasis">IVA</span>
                    <span class="text-body-1">  {{
                        formatCurrency(
                          getIva(product.product, props.order.currency),
                          props.order.currency
                        )
                      }}</span>
                  </div>

                  <div class="d-flex flex-column align-end">
                    <span class="text-body-2 text-medium-emphasis">Total</span>
                    <span class="text-body-1 me-2 font-weight-bold">  {{
                        formatCurrency(
                          getProductPrice(product.product, props.order.currency),
                          props.order.currency
                        )
                      }}</span>
                  </div>
                </div>
              </template>
            </VListItem>
          </VList>
        </div>

        <VDivider />
        <div class="d-flex flex-wrap justify-space-between">
          <p class="font-weight-bold text-h6 mt-2">Total a pagar:</p>
          <p class="font-weight-bold text-h6 mt-2">
              {{props.order.total_amount}}  {{props.order.currency}}
          </p>
        </div>
      </VCardText>
    </VCard>
  </VDialog>
</template>
