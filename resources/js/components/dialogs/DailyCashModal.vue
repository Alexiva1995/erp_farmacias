<script setup>
import { defineProps, defineEmits, computed, nextTick } from "vue";
import { BASE64_LOGO_DATA } from "@/constants/logo.js";
import TicketHeader from "@/components/TicketHeader.vue";
import axios from "@/plugins/axios";
import SectionDivider from "@/components/SectionDivider.vue";

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

const filteredCashClosings = computed(() => {
  if (!props.cashData || !props.cashData.cash_closings) {
    return [];
  }
  return props.cashData.cash_closings.filter(
    (closing) => closing.total_sales !== '0.00'
  );
});
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
        <div id="daily-cash-report">
          <TicketHeader :logoSrc="BASE64_LOGO_DATA" />
        </div>
     
        <div class="container mt-3">
            <div>
              <div
                class="row"
                v-if="filteredCashClosings.length > 0"
                :class="{
                  'd-flex flex-wrap': filteredCashClosings.length > 1,
                  'mb-4': true,
                  'pdf-row-multi': filteredCashClosings.length > 1,
                }"
              >
                <div
                  v-for="(cashData, index) in filteredCashClosings"
                  :key="index"
                  :class="{
                    'col-6 w-50': filteredCashClosings.length > 1,
                    'col-12': filteredCashClosings.length === 1,
                    'mb-4': true,
                    'pdf-col-multi': filteredCashClosings.length > 1,
                  }"
                >
                  <div class="w-100" v-if="cashData.total_sales !== '0.00'">
                    <SectionDivider
                      :isPdf="true"
                      :text="cashData.seller.username"
                      width="30%"
                      class="center-block"
                    />

                    
                      <table
                        class="table table-sm table-borderless"
                        :class="{
                          'w-75 mx-auto center-block': filteredCashClosings.length === 1,
                          'w-100': filteredCashClosings.length > 1,
                        }"
                      >
                        <tbody>
                            <tr>
                            <td class="text-left"><span>ID: {{cashData.id}}</span></td>
                          </tr>
                          <tr>
                            <td class="text-left"><span>USD:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_usd }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-left"><span>BS:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_bs }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_bs_in_usd }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-left"><span>COP:</span></td>
                            <td class="text-right">
                              <span>{{ cashData.total_cop }}</span>
                            </td>
                            <td class="text-right">
                              <span>{{ cashData.total_cop_in_usd }}</span>
                            </td>
                          </tr>
                          <tr>
                            <td class="text-left"><span></span></td>
                            <td class="text-right fw-bold">
                              <span>TOTAL VENTA</span>
                            </td>
                            <td class="text-right fw-bold">
                              <span>{{ cashData.total_sales }}</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                  </div>
                </div>
            </div>
        </div>
        </div>
        <div class="mt-3">
         <SectionDivider
                  :isPdf="true"
                  text="TOTAL VENTA DIA"
                  width="35%"
                  class="mx-auto center-block"
                />
                <div>
                  <table
                    class="table table-borderless table-sm w-75 mx-auto center-block"
                  >
                    <tbody>
                      <tr>
                        <td class="text-left"><span>USD:</span></td>
                        <td class="text-right">
                          <span>{{ props.cashData.total_usd }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{ props.cashData.total_usd }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-left"><span>BS:</span></td>
                        <td class="text-right">
                          <span>{{ props.cashData.total_bs }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{
                            props.cashData.total_bs
                          }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-left"><span>COP:</span></td>
                        <td class="text-right">
                          <span>{{ props.cashData.total_cop }}</span>
                        </td>
                        <td class="text-right">
                          <span>{{
                            props.cashData.total_cop
                          }}</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="text-start"><span></span></td>
                        <td class="text-right fw-bold"><span>TOTAL</span></td>
                        <td class="text-right fw-bold">
                          <span>{{
                            props.cashData.total_sales
                          }}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
         </div>

      </VCardText>
      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn color="secondary" variant="outlined" @click="" class="w-50">
          Imprimir
        </VBtn>
        <VBtn color="primary" variant="flat" @click="" class="w-50">
          Descargar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
