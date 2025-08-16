<script setup>
import CreditTable from "@/components/CreditTable.vue";
import axios from "@/plugins/axios";
import CreditsModal from "@/components/dialogs/CreditsModal.vue";
import { toast } from "@/plugins/sweetalert";
import CreditsTicket from "@/components/CreditsTicket.vue";
import { nextTick, ref, watch, onMounted } from 'vue';

const credits = ref([]);
const totalCredits = ref(0);
const loading = ref(false);

const page = ref(1);
const itemsPerPage = ref(10);
const sortBy = ref();
const orderBy = ref();

const showCreditsModal = ref(false);
const creditsData = ref(null);

const isPrinting = ref(false);
const paymentsForPrint = ref([]);
const changeAmountForPrint = ref(0);
const creditAmountForPrint = ref(0);

const selectedClient = ref(null);

const fetchCredits= async () => {
  loading.value = true;
  const params = {
    page: page.value,
    itemsPerPage: itemsPerPage.value,
    sortBy: sortBy.value,
    orderBy: orderBy.value,
  };
  Object.keys(params).forEach(
    (key) => (params[key] === null || params[key] === "") && delete params[key]
  );
  try {
    const response = await axios.get("/tpv/credits", { params });
    credits.value = response.data.data;
    totalCredits.value = response.data.total;
  } catch (error) {
    console.error("Hubo un error al obtener los créditos:", error);
    toast.error("Error al obtener los créditos.");
  } finally {
    loading.value = false;
  }
};

let debounceTimer;
watch(
  [
    page,
    itemsPerPage,
    sortBy,
    orderBy,
  ],
  () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCredits(), 300);
  },
  { deep: true }
);

onMounted(() => {
  fetchCredits();
});


const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  sortBy.value = options.sortBy[0]?.key;
  orderBy.value = options.sortBy[0]?.order;
};


const openCreditsModal = (credit) => {
    creditsData.value = credit; 
        console.log(creditsData);
    showCreditsModal.value = true;
};


const closeCreditsModal = () => {
    showCreditsModal.value = false;
    creditsData.value = null;
};

const handleCreditsCompletion = async (paymentsData, changeAmount, changeAmountUSD) => {
 try {

  const clientId = creditsData.value?.client?.id;

    if (!clientId) {
      toast.error("No se pudo obtener el ID del cliente. Intente de nuevo.");
      return; // Detiene la ejecución si no hay ID de cliente
    }

 const payload = {
      clientId: clientId,
      payments: paymentsData,
      changeAmount: changeAmount,
      changeAmountUSD: changeAmountUSD,
    };
    const response = await axios.post(`/tpv/credits/complete`, payload);
    if (response.status === 200 || response.status === 201) {
    
    toast.success("¡Pago finalizado y registrado con éxito!");
    await fetchCredits(); 
      paymentsForPrint.value = [...paymentsData];
      changeAmountForPrint.value = changeAmount;
      isPrinting.value = true;
      await nextTick();
      const printContents = document.getElementById("CreditPrint");
      if (printContents) {
      const printWindow = window.open("", "", "height=600,width=800");
      printWindow.document.write("<html><head><title>Farmacia Barrio Sucre</title>");
      const styleSheets = document.styleSheets;
      for (let i = 0; i < styleSheets.length; i++) {
        const sheet = styleSheets[i];
        try {
          if (sheet.cssRules) {
            let cssText = '';
            for (let j = 0; j < sheet.cssRules.length; j++) {
              cssText += sheet.cssRules[j].cssText;
            }
            printWindow.document.write(`<style>${cssText}</style>`);
          } else if (sheet.href) {
            printWindow.document.write(`<link rel="stylesheet" href="${sheet.href}">`);
          }
        } catch (e) {
          console.warn("No se pudo acceder a la hoja de estilo:", sheet.href || sheet, e);
        }
      }
      printWindow.document.write("</head><body>");
      printWindow.document.write(printContents.innerHTML);
      printWindow.document.write("</body></html>");
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
      printWindow.close();
      } else {
      console.warn("Elemento #CreditPrint no encontrado para impresión tipo ticket. Imprimiendo toda la página.");
      window.print();
    }

    }else{
      toast.error(`Error inesperado al finalizar el pago: ${response.data.message || 'Intente de nuevo.'}`);  
    }

    setTimeout(() => {
      isPrinting.value = false;
      paymentsForPrint.value = [];
      creditsData.value = null;
      selectedClient.value = null;
      orderItems.value = [];
      changeAmountForPrint.value = 0;
      creditAmountForPrint.value = 0;
      clientIdentification.value = "";
    }, 500);


 } catch (error) {
    console.error("Error al finalizar el pago del crédito:", error.response ? error.response.data : error.message);
    const errorMessage = error.response?.data?.message || "Hubo un problema al procesar su pago. Por favor, intente de nuevo.";
    toast.error(errorMessage);
    isPrinting.value = false;
    paymentsForPrint.value;
    changeAmountForPrint.value = 0;
    creditAmountForPrint.value = 0;
  }
}

</script>

<template>
    <CreditTable
      :credits="credits"
      :loading="loading"
      :total-credits="totalCredits"
      :items-per-page="itemsPerPage"
      :page="page"
      @update:options="updateTableOptions"
      @open-payment-modal="openCreditsModal"
      @reload="fetchCredits"
    />

   <CreditsModal
            v-if="showCreditsModal && creditsData"
            v-model:is-dialog-visible="showCreditsModal"
            :credits-data="creditsData"
            @modal-closed="closeCreditsModal"
            @purchase-completed="handleCreditsCompletion"
        />



      <div id="CreditPrint" :class="{ 'd-none': !isPrinting, 'print-container': true }">
      <CreditsTicket
        v-if="isPrinting && creditsData"
        :credits-data="creditsData"
        :payments="paymentsForPrint"
        :change-amount="changeAmountForPrint"
        :credit-amount="creditAmountForPrint"
      />
    </div>

</template>
