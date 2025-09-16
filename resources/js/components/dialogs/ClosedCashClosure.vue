<script setup>
import { defineProps, defineEmits, computed } from "vue";
import { formatCurrency } from "@/utils/currencyFormatter";
import ExpiredDetailView from "@/components/ExpiredDetailView.vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  cashClosureData: {
    type: Object,
    default: () => null,
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
};
</script>
<template>
  <VDialog v-model="dialogVisible">
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="text-h5 font-weight-bold pr-1">Cierre de Caja </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <p class="text-h6 font-weight-medium mb-0">
          Total de USD
          {{ formatCurrency(parseFloat(props.cashClosureData.total_usd)) }}
        </p>

        <VRow class="py-2">
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Binance:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_binance }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Paypal:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_paypal }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Efectivo en USD:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Diferencia por cambio:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_conversion }}
              </span>
            </div>
          </VCol>
        </VRow>
        <VDivider />

        <p class="text-h6 font-weight-medium mb-0 p-2">
          Total de Bs
          {{ formatCurrency(parseFloat(props.cashClosureData.total_bs)) }}
        </p>

        <VRow class="py-2">
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Efectivo:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.bs_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Pago movil:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.bs_mobile }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Transferencia:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.bs_transfer }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Tarjeta:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.bs_card }}
              </span>
            </div>
          </VCol>
        </VRow>
        <VDivider />
        <p class="text-h6 font-weight-medium mb-0">
          Total de COP
          {{
            formatCurrency(parseFloat(props.cashClosureData.total_cop), "COP")
          }}
        </p>

        <VRow class="py-2">
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Efectivo en COP:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Transferencia:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_transfer }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Diferencia por cambio:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_conversion }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Sobrante en peso:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
               <VTextField
                    placeholder="Monto Sobrante"
                    type="number"
                    class="p-2"
                  />
              </span>
            </div>
          </VCol>
        </VRow>
        <VDivider />
        <p class="text-h6 font-weight-medium mb-0">
          Total de Créditos
          {{ formatCurrency(parseFloat(props.cashClosureData.usd_credit)) }}
        </p>

        <VRow class="py-2">
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Créditos:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_credit }}
              </span>
            </div>
          </VCol>
        </VRow>
        <VDivider />
        <p class="text-h6 font-weight-medium mb-0">Abonos de créditos</p>

        <VRow class="py-2">
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Efectivo en USD:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Binance:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_transfer }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Paypal:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_conversion }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Efectivo:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_spare }}
              </span>
            </div>
          </VCol>
        </VRow>

        <VRow>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Pago Movil:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Transferencias:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_transfer }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis">Tarjetas:</span>
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_conversion }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Efectivo en COP:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_spare }}
              </span>
            </div>
          </VCol>
        </VRow>
        <VRow>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Transferencias:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_cash }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Diferencia por cambio:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_transfer }}
              </span>
            </div>
          </VCol>
        </VRow>

        <VDivider />

        <VRow>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Entregar Efectivo en USD:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.usd_delivered }}
              </span>
            </div>
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <div class="d-flex flex-column">
              <span class="text-caption text-medium-emphasis"
                >Entregar Efectivo en COP:</span
              >
              <span class="text-body-1 font-weight-medium text-high-emphasis">
                {{ props.cashClosureData.cop_delivered }}
              </span>
            </div>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="w-50"
        >
          Cancelar
        </VBtn>
        <VBtn color="primary" variant="flat" @click="" class="w-50">
          Completar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
