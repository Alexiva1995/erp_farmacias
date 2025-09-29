<script setup>
import { defineProps, defineEmits, computed, nextTick  } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  monthlyCashData: {
    type: Object,
    default: () => ({}),
  },
    originalIds: {
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

const logoSrc = computed(() => {
  return BASE64_LOGO_DATA;
});

const downloadReport = async () => {
   try {
        await nextTick();
        const element = document.getElementById('monthly-cash-report');
        if (!element) {
            console.error("No se encontró el contenido del reporte.");
            return;
        }
        const htmlContent = element.outerHTML; 

        const params = {
            html_content: htmlContent, 
            filename: 'Resumen_Cajas_Mensuales'
        };

        const response = await axios.post('/finances/cash-closure/downloadMonthlyReport', params, {
            responseType: 'blob', 
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
       // const contentDisposition = response.headers['content-disposition'];
        let filename = 'reporte.pdf'; 
        /*if (contentDisposition) {
            const matches = /filename="([^"]+)"/.exec(contentDisposition);
            if (matches && matches[1]) {
                filename = matches[1];
            }
        }*/

        link.href = url;
        link.setAttribute('download', filename); 
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        
        closeModal(); 

    } catch (error) {
        console.error("Error al descargar el PDF:", error);
    }
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="500px">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline"></span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
       <VCardText>
       <div id="monthly-cash-report"> 
       <TicketHeader 
       :logoSrc=BASE64_LOGO_DATA
       />

       <div class="container mt-3">
       <div class="row">
            <div class="col-md-6 mb-1" v-for="(cashData, index) in props.monthlyCashData" :key="index">
                <div class="d-flex align-items-center justify-content-center">
                    <hr class="flex-grow-1 border border-secondary" style="max-width: 50%;">
                    <span class="px-1 textModal">{{ cashData.seller_name }}</span>
                    <hr class="flex-grow-1 border border-secondary" style="max-width: 50%;">
                </div>
    
                <div class="row">
                    <template v-if="cashData && props.monthlyCashData.length === 1">
                        <div v-if="cashData.total_sales !== '0.00'">
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-start"><span>USD:</span></td>
                                        <td class="text-end"><span>{{ cashData.total_usd }}</span></td>
                                        <td class="text-end"><span>{{ cashData.total_usd }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-start"><span>BS:</span></td>
                                        <td class="text-end"><span>{{ cashData.total_bs }}</span></td>
                                        <td class="text-end"><span>{{ cashData.total_bs }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-start"><span>COP:</span></td>
                                        <td class="text-end"><span>{{ cashData.total_cop }}</span></td>
                                        <td class="text-end"><span>{{ cashData.total_cop }}</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-start"><span></span></td>
                                        <td class="text-end fw-bold"><span>TOTAL VENTA</span></td>
                                        <td class="text-end fw-bold"><span>{{ cashData.total_sales }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        </div>
        </div>
       </VCardText>

        <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="w-50"
        >
          Imprimir
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="downloadReport"
          class="w-50"
        >
          Descargar
        </VBtn>
      </VCardActions>

    </VCard>
  </VDialog>
</template>
