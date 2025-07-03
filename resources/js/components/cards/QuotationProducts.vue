<script setup>
import { ref, computed, watch } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import { toast } from '@/plugins/sweetalert';
import Swal from 'sweetalert2';
import { roundUpToNearestHundred } from "@/utils/roundUpToNearesHundred.js"

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
    totalAmountBs: {
    type: Number,
    default: 0
  },
  totalAmountUsd: {
    type: Number,
    default: 0
  },
  totalAmountCop: {
    type: Number,
    default: 0
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



const generateWhatsappMessage = () => {
  if (props.quotationProducts.length === 0) {
    return ''; // Retorna vacío si no hay productos
  }

  const fecha = new Date();
  const productos_array = [];
  props.quotationProducts.forEach(product => {
    let priceWithIvaUsd = (product.price || 0) * (1 + (product.taxRate || 0));
    let priceWithIvaBs = (product.price_bs || 0) * (1 + (product.taxRate || 0));
    let  priceWithIvaCop = (product.price_cop || 0) * (1 + (product.taxRate || 0));

    priceWithIvaCop = roundUpToNearestHundred(priceWithIvaCop);

    let rows = '💊 ' + product.title + '\n' +
      'Cantidad: ' + product.selectedQuantity + ' UND(S) \n' +
      'Precio por unidad: \n' +
      'Bs.: ' + formatCurrency(priceWithIvaBs, 'BS') + '\n' +
      '💵 USD: ' + formatCurrency(priceWithIvaUsd, 'USD') + '\n' +
      '💰 COP: ' + formatCurrency(priceWithIvaCop, 'COP');
    productos_array.push(rows);
  });

  const whatsappMessage = 'Mensaje de presupuesto\n\n' +
    'Fecha: ' + fecha.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) + '\n' +
    '\nBuenas tardes, Estimado Cliente!\n' +
    '... le da la Bienvenida!\n' +
    'Para nosotros es un gusto servirle.\n\n' +
    'A continuación el detalle de su presupuesto: \n' +
    productos_array.join('\n\n') + '\n\n' +
    '\n\nTOTAL PRESUPUESTO:\n' +
    'Bs.: ' + formatCurrency(props.totalAmountBs, 'BS') + '\n' +
    '💵 USD: ' + formatCurrency(props.totalAmountUsd, 'USD') + '\n' +
    '💰 COP: ' + formatCurrency(roundUpToNearestHundred(props.totalAmountCop), 'COP') + '\n' +
    '\n👉 Condiciones\n' +
    '\nPrecios sujetos a cambio sin previo aviso.\n' +
    '\nEl Presupuesto es Válido hasta agotarse las existencias.\n' +
    '\nPara compras en taquilla debe presentar este presupuesto.\n';

  return whatsappMessage;
};


const handleShareButtonClick = () => {
  const whatsappMessage = generateWhatsappMessage();
  if (!whatsappMessage) {
    toast.error('No hay productos en la cotización para compartir.');
    return;
  }
  const encodedMessage = encodeURIComponent(whatsappMessage);
  const whatsappUrl = 'https://api.whatsapp.com/send?text=' + encodedMessage;
  window.open(whatsappUrl, '_blank');
};

const handleCopyWhatsappMessage = async () => {
  const whatsappMessage = generateWhatsappMessage();
  if (!whatsappMessage) {
    toast.error('No hay productos en la cotización para copiar.');
    return;
  }
  try {
    await navigator.clipboard.writeText(whatsappMessage);
    toast.success('Mensaje copiado al portapapeles correctamente.');
  } catch (err) {
    console.error('Error al copiar el mensaje:', err);
    toast.error('Error al copiar el mensaje al portapapeles.');
  }
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
               <div class="d-flex align-center" style="width: 60px;">
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

    <VCardActions class="pa-4 d-flex flex-wrap justify-space-between">
    <VBtn color="secondary" variant="outlined" @click="remove()" class="flex-grow-1"> Cancelar </VBtn>
    <VBtn color="primary" variant="flat" @click="handlePrintButtonClick" class="flex-grow-1"> Imprimir </VBtn>
    <VBtn color="success" variant="flat" @click="handleShareButtonClick" class="flex-grow-1"> Compartir </VBtn>
    <VBtn color="info" variant="flat" @click="handleCopyWhatsappMessage" class="flex-grow-1"> Copiar </VBtn>
    </VCardActions>

  </VCard>
</template>

<style scoped>
.scrollable-list-container {
  max-height: 130px;
  overflow-y: hidden;
  transition: overflow-y 0.3s ease-in-out;
}
.scrollable-list-container.show-scroll {
  overflow-y: auto; 
}
</style>
