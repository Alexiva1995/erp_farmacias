<script setup>
import { ref, computed, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";

// --- PROPS ---
const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  color: {
    type: String,
    required: false,
    default: "primary",
  },
  icon: {
    type: String,
    required: false,
  },
  stats: {
    type: String,
    required: false,
  },
  height: {
    type: Number,
    required: false,
  },
  series: {
    type: Array,
    required: false,
  },
  chartOptions: {
    type: null,
    required: false,
  },
  searchQuery: {
    type: String,
    default: "",
  },
    quotationProducts: {
    type: Array,
    default: () => [],
  },
  selectedDisplayCurrency: {
    type: String,
    default: "USD",
  },
});

const emit = defineEmits(["update:searchQuery", 
"remove-quotation-product","remove","print-quotation",
"add-product-by-barcode"]);

const removeQuotationProduct = (productId) => {
  emit('remove-quotation-product', productId);
};

const remove = () => {
  emit('remove');
};


const getProductPrice = (product, currency) => {
  if (currency === 'BS') {
    return product.price_bs || 0;
  } else if (currency === 'COP') {
    return product.price_cop || 0;
  } else {
    return product.price || 0;
  }
};


const totalSelectedQuantity = computed(() => {
  let total = 0;
  props.quotationProducts.forEach(product => {
    const quantity = parseInt(product.selectedQuantity);
    if (!isNaN(quantity) && quantity > 0) {
      total += quantity;
    }
  });
  return total;
});

const handlePrintButtonClick = () => {
  emit('print-quotation');
};

const chipColor = "primary";
</script>

<template>
  <VCard min-height="280" class="d-flex flex-column">
    <VCardText class="d-flex flex-column pb-0 mb-4">
      <VRow>
        <VCol cols="12" sm="12" md="12">
          <div class="d-flex align-center gap-4 flex-wrap">
            <AppTextField
              :model-value="props.searchQuery"
              placeholder="Código de Barra"
              clearable
              @update:model-value="emit('update:searchQuery', $event)"
              class="flex-grow-1"
            />

            <VChip
              label
              :color="chipColor"
              variant="tonal"
              density="default"
              size="small"
              draggable="false"
              class="ms-auto"
            >
              <span class="font-weight-medium">{{totalSelectedQuantity}}</span>
            </VChip>
          </div>
        </VCol>
      </VRow>
    </VCardText>

    <VCardText class="d-flex flex-column pb-0 flex-grow-1">
      <div
        class="scrollable-list-container"
        :class="{ 'show-scroll': props.quotationProducts.length > 2 }" >
        <VList class="card-list" density="compact" nav>
          <VListItem v-if="props.quotationProducts.length === 0"> <VListItemTitle class="text-center text-medium-emphasis">No hay productos en la cotización.</VListItemTitle>
          </VListItem>

          <VListItem
            v-for="product in props.quotationProducts"
            :key="product.id"
            class="rounded-0"
          >
            <template #prepend>
               <div class="d-flex align-center">
                <VTextField
                  v-model.number="product.selectedQuantity" type="number"
                  variant="outlined"
                  density="compact"
                  hide-details
                  single-line
                  class="cost-input-field text-center"
                  min="1"
                  :max="product.availableQuantity" >
                </VTextField>
              </div>
            </template>

            <VListItemTitle class="font-weight-medium me-4 mx-2">{{ product.title }}</VListItemTitle>
            <VListItemSubtitle class='mx-2'>{{product.active_ingredient}}</VListItemSubtitle>
            <VListItemSubtitle class='mx-2'>{{product.laboratory}}</VListItemSubtitle>

            <template #append>
              <div class="d-flex align-center">
                <span class="text-body-1 me-2">{{
                    formatCurrency(
                    getProductPrice(product, props.selectedDisplayCurrency) * product.selectedQuantity,
                    props.selectedDisplayCurrency
                  )
                }}</span>
                <VBtn
                  icon="tabler-trash"
                  variant="text"
                  color="error"
                  @click="removeQuotationProduct(product.id)" />
              </div>
            </template>
          </VListItem>
        </VList>
      </div>
    </VCardText>

    <VCardActions class="pa-4 d-flex flex-wrap gap-4">
      <VBtn color="secondary" variant="outlined"  @click="remove()"> Cancelar </VBtn>
      <VBtn color="primary" variant="flat" @click="handlePrintButtonClick"> Imprimir </VBtn>
      <VBtn color="success" variant="flat"> Compartir </VBtn>
    </VCardActions>
  </VCard>
</template>

<style scoped>
/* Contenedor del scroll */
.scrollable-list-container {
  max-height: 130px; /* Ajusta esta altura según tus necesidades */
  overflow-y: hidden; /* Oculto por defecto */
  transition: overflow-y 0.3s ease-in-out; /* Transición suave si quieres */
}

/* Mostrar scroll solo cuando la clase 'show-scroll' esté presente */
.scrollable-list-container.show-scroll {
  overflow-y: auto; /* Muestra el scroll vertical */
}
</style>
