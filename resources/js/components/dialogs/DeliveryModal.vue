<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";
import { formatDateTime } from "@/utils/formatDateTime";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const dialogVisible = computed({
  get() {
    return props.isDialogVisible;
  },
  set(value) {
    emit("update:isDialogVisible", value);
  },
});


const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
};

const ticketStyles = `
.pa-2 { padding: 8px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.text-left { text-align: left; }
.mb-2 { margin-bottom: 8px; }
.tbody-bordered { border: 1px solid #dfdfdff9; background-color: #f9f8f8; }
.center-block { margin-left: auto; margin-right: auto; }
.single-report-center { width: 50%; margin-left: auto; margin-right: auto; }
.w-75 {width: 75% !important;}
.w-100 {width: 100% !important;}
.mx-auto { margin-left: auto !important; margin-right: auto !important; }

.pdf-row-multi {
width: 100%;
display: block; 
}
.pdf-row-multi:after {
content: "";
display: table;
 clear: both;
}
.pdf-col-multi {
float: left;
width: 48%; 
box-sizing: border-box;
 padding: 0 5px;
margin-right: 2%;
min-height: 1px;
}
`;


const downloadReport = async () => {
  try {
    await nextTick();
    const element = document.getElementById("delivery-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;
    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Entregas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/downloadReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    let filename = "EntregasDiario.pdf";
    link.href = url;
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
     closeModal();
  } catch (error) {
    console.error("Error al descargar el PDF:", error);
  }
};
const printReport = async () => {
    try {
    await nextTick();
    const element = document.getElementById("delivery-report");
    if (!element) {
      console.error("No se encontró el contenido del reporte.");
      return;
    }
    const htmlContent = element.outerHTML;

    const params = {
      html_content: `<style>${ticketStyles}</style>${htmlContent}`,
      filename: "Entregas_Diario",
    };

    const response = await axios.post(
      "/finances/cash-closure/PrintReport",
      params,
      {
        responseType: "blob",
      }
    );
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
    const printWindow = window.open(url, '_blank');
    if (printWindow) {
            printWindow.focus();
        }
    window.URL.revokeObjectURL(url); 
     closeModal();
  } catch (error) {
    console.error("Error al visualizar el PDF:", error);
  }
};


const groupedCardTotals = computed(() => {
  const closings = props.cashData.cash_closings;

  if (!closings || !Array.isArray(closings)) {
    return {};
  }

  return closings.reduce((acc, closing) => {
    const sellerId = closing.seller_id;
    const sellerName = closing.seller?.username || `Vendedor ${sellerId}`;
    const bsCardAmount = parseFloat(closing.bs_card) || 0;
    const bsTransferAmount = parseFloat(closing.bs_transfer) || 0;
    const bsMobileAmount = parseFloat(closing.bs_mobile) || 0;
    const copTransferAmount = parseFloat(closing.cop_transfer) || 0;
    const usdTransferAmount = parseFloat(closing.usd_transfer) || 0;
    const usdPaypalAmount = parseFloat(closing.usd_paypal) || 0;
    const usdBinanceAmount = parseFloat(closing.usd_binance) || 0;
    const usdCreditAmount = parseFloat(closing.usd_credit) || 0;

    const usdCashAmount =  (parseFloat(closing.usd_cash) || 0) + (parseFloat(closing.usd_conversion) || 0);
    const bsCashAmount = parseFloat(closing.bs_cash) || 0;
    const copCashAmount = parseFloat(closing.cop_cash) || 0;

    const usdCashAmountCredit = parseFloat(closing.usd_cash_payment_credit) || 0;
    const usdPaypalAmountCredit = parseFloat(closing.usd_paypal_payment_credit) || 0;
    const usdBinanceAmountCredit = parseFloat(closing.usd_binance_payment_credit) || 0;

    const copTransferAmountCredit = parseFloat(closing.cop_transfer_payment_credit) || 0;
    const copCashAmountCredit = parseFloat(closing.cop_cash_payment_credit) || 0;

    const bsCardAmountCredit = parseFloat(closing.bs_card_payment_credit) || 0;
    const bsCashAmountCredit = parseFloat(closing.bs_cash_payment_credit) || 0;
    const bsTransferAmountCredit = parseFloat(closing.bs_transfer_payment_credit) || 0;
    const bsMobileAmountCredit = parseFloat(closing.bs_mobile_payment_credit) || 0;


    if (!acc[sellerId]) {
      acc[sellerId] = {
        seller_id: sellerId,
        seller_name: sellerName,
         total_bs_card: 0,
         total_bs_transfer: 0,
         total_bs_mobile: 0,
         total_cop_transfer: 0,
         total_usd_transfer: 0,
         total_usd_paypal: 0,
         total_usd_binance: 0,
         total_usd_credit: 0,
         total_usd_cash: 0,
         total_bs_cash: 0,
         total_cop_cash: 0,

         total_bs_card_paymentCredit: 0,
         total_bs_transfer_paymentCredit: 0,
         total_bs_mobile_paymentCredit: 0,
         total_cop_transfer_paymentCredit: 0,
         total_usd_paypal_paymentCredit: 0,
         total_usd_binance_paymentCredit: 0,
         total_usd_cash_paymentCredit: 0,
         total_bs_cash_paymentCredit: 0,
         total_cop_cash_paymentCredit: 0,
      };
    }
     acc[sellerId].total_bs_card += bsCardAmount;
     acc[sellerId].total_bs_transfer += bsTransferAmount;
     acc[sellerId].total_bs_mobile += bsMobileAmount;
     acc[sellerId].total_cop_transfer += copTransferAmount;
     acc[sellerId].total_usd_transfer += usdTransferAmount;
     acc[sellerId].total_usd_paypal += usdPaypalAmount;
     acc[sellerId].total_usd_binance += usdBinanceAmount; 
     acc[sellerId].total_usd_credit += usdCreditAmount;
     acc[sellerId].total_usd_cash += usdCashAmount;
     acc[sellerId].total_bs_cash += bsCashAmount;
     acc[sellerId].total_cop_cash += copCashAmount;

    acc[sellerId].total_bs_card_paymentCredit += bsCardAmountCredit;
    acc[sellerId].total_bs_transfer_paymentCredit += bsTransferAmountCredit;
    acc[sellerId].total_bs_mobile_paymentCredit += bsMobileAmountCredit;
     acc[sellerId].total_cop_transfer_paymentCredit += copTransferAmountCredit;
     acc[sellerId].total_usd_paypal_paymentCredit += usdPaypalAmountCredit;
     acc[sellerId].total_usd_binance_paymentCredit += usdBinanceAmountCredit; 
     acc[sellerId].total_usd_cash_paymentCredit += usdCashAmountCredit;
     acc[sellerId].total_bs_cash_paymentCredit += bsCashAmountCredit;
     acc[sellerId].total_cop_cash_paymentCredit += copCashAmountCredit;

    return acc;
  }, {});
});


const chunkArray = (array, size) => {
    if (!array || !array.length) return [];
    const chunkedArr = [];
    for (let i = 0; i < array.length; i += size) {
        chunkedArr.push(array.slice(i, i + size));
    }
    return chunkedArr;
};

const sellersArray = computed(() => {
    return Object.values(groupedCardTotals.value);
});

const groupedSellers = computed(() => {
    return chunkArray(sellersArray.value, 2);
});

const isSingleSeller = computed(() => {
    return sellersArray.value.length === 1;
});


const getDividerWidth = (name) => {
    if (!name) return '40%';
    const length = name.length;
    if (length > 20) {
        return '10%'; 
    } else if (length > 15) {
        return '25%'; 
    } else if (length > 6){
        return '35%'; 
    } else {
        return '40%'; 
    }
};

</script>
<template>
<VDialog v-model="dialogVisible" max-width="700px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
       <VCardText>
        <div id="delivery-report">
            <TicketHeader :logoSrc="BASE64_LOGO_DATA" />

    <div class="ticket-header d-flex justify-space-between align-start mt-2">
        <span class="font-weight-bold tituloAzulPrint"
          >Cierre Diario N° {{ props.cashData.id }}</span
        >
        <div class="text-right d-flex flex-column align-end">
          <p class="text-black font-weight-regular mb-0 textoPrint">
            {{ formatDateTime(props.cashData.created_at, "date") }} {{ formatDateTime(props.cashData.created_at, "time") }}
          </p>
        </div>
      </div>

 <div class="container mt-3">
            <table v-if="groupedSellers.length > 0" style="width: 100%; border-collapse: separate; border-spacing: 15px 15px;">
                <tbody>
                    <tr v-for="(pair, rowIndex) in groupedSellers" :key="rowIndex">
                        <td 
                            v-for="(cashGroups, colIndex) in pair" 
                            :key="colIndex"
                            :colspan="isSingleSeller ? '2' : '1'" 
                            :style="{
                                'vertical-align': 'top', 
                                'padding': '0',
                                'width': isSingleSeller ? '100%' : '50%'
                            }"
                        >
                            <div 
                                class="w-100" 
                                :style="{ 
                                    'padding': '5px',
                                    'width': isSingleSeller ? '80%' : '100%',
                                    'margin-left': isSingleSeller ? 'auto' : '0',
                                    'margin-right': isSingleSeller ? 'auto' : '0',
                                }"
                            >
                                <SectionDivider
                                    :isPdf="true"
                                    :text="cashGroups.seller_name" 
                                    :width="getDividerWidth(cashGroups.seller_name)"
                                    class="center-block"
                                />
                                
                                <table 
                                    class="table table-sm table-borderless" 
                                    :class="{
                                        'w-75 mx-auto center-block': isSingleSeller, 
                                        'w-100': !isSingleSeller,
                                    }"
                                >
                                    <tbody>
                                        <tr v-if="cashGroups.total_bs_card > 0">
                                            <td style="text-align: left"><span>Tarjeta (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_card }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_transfer > 0">
                                            <td style="text-align: left"><span>Trasnferencia (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_transfer }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_mobile > 0">
                                            <td style="text-align: left"><span>Pago Móvil (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_mobile }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_cop_transfer > 0">
                                            <td style="text-align: left"><span>Trasnferencia (COP)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_cop_transfer }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_transfer > 0">
                                            <td style="text-align: left"><span>Trasnferencia (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_transfer }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_paypal > 0">
                                            <td style="text-align: left"><span>Paypal (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_paypal }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_binance > 0">
                                            <td style="text-align: left"><span>Binance (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_binance }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_credit > 0">
                                            <td style="text-align: left"><span>Creditos (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_credit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_cash > 0">
                                            <td style="text-align: left"><span>Efectivo (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_cash }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_cop_cash > 0">
                                            <td style="text-align: left"><span>Efectivo (COP)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_cop_cash }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_cash > 0">
                                            <td style="text-align: left"><span>Efectivo (USD)</span></td>
                                            <td style="text-align: right"><span>{{formatCurrency(cashGroups.total_usd_cash)}}</span></td>
                                        </tr>


                                        <tr v-if="cashGroups.total_bs_card_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Tarjeta (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_card_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_transfer_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Trasnferencia (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_transfer_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_mobile_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Pago Móvil (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_mobile_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_cop_transfer_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Trasnferencia (COP)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_cop_transfer_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_paypal_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Paypal (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_paypal_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_binance_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Binance (USD)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_usd_binance_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_bs_cash_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Efectivo (Bs)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_bs_cash_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_cop_cash_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Efectivo (COP)</span></td>
                                            <td style="text-align: right"><span>{{cashGroups.total_cop_cash_paymentCredit }}</span></td>
                                        </tr>
                                        <tr v-if="cashGroups.total_usd_cash_paymentCredit > 0">
                                            <td style="text-align: left"><span>Abono Efectivo (USD)</span></td>
                                            <td style="text-align: right"><span>{{formatCurrency(cashGroups.total_usd_cash_paymentCredit)}}</span></td>
                                        </tr>

                                    </tbody>
                                </table>
                            </div>
                        </td>
                        
                        <td v-if="pair.length === 1 && !isSingleSeller" style="width: 50%; padding: 0;"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        </div>
       </VCardText>
      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn color="secondary" variant="outlined" @click="printReport" class="w-50">
          Imprimir
        </VBtn>
        <VBtn color="primary" variant="flat" @click="downloadReport" class="w-50">
          Descargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
