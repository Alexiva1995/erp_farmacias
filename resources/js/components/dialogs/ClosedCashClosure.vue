<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed, defineEmits, defineProps, ref } from "vue";
import { useDisplay } from "vuetify";

const { mobile } = useDisplay();

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  cashClosureData: { type: Object, default: () => null },
});

const emit = defineEmits(["update:isDialogVisible", "complete-cash-closure"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const sobranteCop = ref(0);

// Totales calculados
const totalCopConSobrante = computed(() => {
  const totalOriginal = parseFloat(props.cashClosureData?.total_cop) || 0;
  const sobrante = parseFloat(sobranteCop.value) || 0;
  return totalOriginal + sobrante;
});

const totalEfectivoCopConSobrante = computed(() => {
  const totalDeliveryCop = parseFloat(props.cashClosureData?.cop_delivered) || 0;
  const sobrantecOP = parseFloat(sobranteCop.value) || 0;
  return totalDeliveryCop + sobrantecOP;
});

const totalUsd = computed(() => parseFloat(props.cashClosureData?.total_usd) || 0);
const totalBs  = computed(() => parseFloat(props.cashClosureData?.total_bs)  || 0);
const totalCredits = computed(() => parseFloat(props.cashClosureData?.usd_credit) || 0);

// Total de abonos recibidos (pagos a crédito)
const totalGlobalDebt = computed(() => parseFloat(props.cashClosureData?.total_global_debt) || 0);

const totalAbonos = computed(() => {
  return [
    'usd_cash_payment_credit', 'usd_binance_payment_credit', 'usd_paypal_payment_credit',
    'bs_cash_payment_credit', 'bs_mobile_payment_credit', 'bs_transfer_payment_credit', 'bs_card_payment_credit',
    'cop_cash_payment_credit', 'cop_transfer_payment_credit'
  ].reduce((sum, key) => sum + (parseFloat(props.cashClosureData?.[key]) || 0), 0);
});

// Helper para mostrar solo campos > 0
const show = (val) => parseFloat(val) > 0;

// Bloques de desglose por sección
const usdFields = computed(() => [
  { label: 'Binance',            val: props.cashClosureData?.usd_binance },
  { label: 'Paypal',             val: props.cashClosureData?.usd_paypal },
  { label: 'Efectivo en USD',    val: props.cashClosureData?.usd_cash },
  { label: 'Diferencia cambio',  val: props.cashClosureData?.usd_conversion },
  { label: 'Saldo del cliente',  val: props.cashClosureData?.usd_balance },
].filter(f => show(f.val)));

const bsFields = computed(() => [
  { label: 'Efectivo',       val: props.cashClosureData?.bs_cash },
  { label: 'Pago Móvil',    val: props.cashClosureData?.bs_mobile },
  { label: 'Transferencia', val: props.cashClosureData?.bs_transfer },
  { label: 'T. Débito',     val: props.cashClosureData?.bs_card_debito },
  { label: 'T. Crédito',    val: props.cashClosureData?.bs_card_credit },
].filter(f => show(f.val)));

const copFields = computed(() => [
  { label: 'Efectivo COP',       val: props.cashClosureData?.cop_cash },
  { label: 'Transferencia',     val: props.cashClosureData?.cop_transfer },
  { label: 'Diferencia cambio', val: props.cashClosureData?.cop_conversion },
].filter(f => show(f.val)));

const creditFields = computed(() => [
  { label: 'Deuda Generada',     val: props.cashClosureData?.usd_credit },
  { label: 'Efectivo USD (Abono)', val: props.cashClosureData?.usd_cash_payment_credit },
  { label: 'Binance (Abono)',    val: props.cashClosureData?.usd_binance_payment_credit },
  { label: 'Paypal (Abono)',     val: props.cashClosureData?.usd_paypal_payment_credit },
  { label: 'Efectivo BS (Abono)', val: props.cashClosureData?.bs_cash_payment_credit },
  { label: 'Pago Móvil BS',     val: props.cashClosureData?.bs_mobile_payment_credit },
  { label: 'Transferencia BS',  val: props.cashClosureData?.bs_transfer_payment_credit },
  { label: 'Tarjetas BS',       val: props.cashClosureData?.bs_card_payment_credit },
  { label: 'Efectivo COP (Abono)', val: props.cashClosureData?.cop_cash_payment_credit },
  { label: 'Transferencia COP', val: props.cashClosureData?.cop_transfer_payment_credit },
].filter(f => show(f.val)));

const closeModal = () => emit("update:isDialogVisible", false);

const completeClosure = () => {
  const closureData = {
    cierre_id: props.cashClosureData.id,
    total_cop: totalCopConSobrante.value,
    sobrante_en_peso: sobranteCop.value,
    entregar_efectivo_cop: totalEfectivoCopConSobrante.value,
  };
  emit("complete-cash-closure", [closureData, props.cashClosureData]);
  closeModal();
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="780px"
    scrollable
    :fullscreen="mobile"
    :transition="mobile ? 'dialog-bottom-transition' : 'dialog-transition'"
  >
    <VCard class="rounded-xl border shadow-sm">
      <!-- Header Premium -->
      <VCardTitle class="d-flex justify-space-between align-center px-6 py-4 border-b bg-surface">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="tonal" rounded class="rounded-lg shadow-sm">
            <VIcon icon="tabler-cash-register" />
          </VAvatar>
          <div>
            <h3 class="text-h6 font-weight-black mb-0 uppercase leading-none">CIERRE DE CAJA</h3>
            <span class="text-xs text-disabled font-weight-medium uppercase">Revisa los totales antes de finalizar</span>
          </div>
        </div>
        <VBtn icon="tabler-x" variant="text" size="small" color="secondary" @click="closeModal" />
      </VCardTitle>

      <VCardText class="pa-6" style="background-color: #f8f9fa;">

        <!-- Sección USD -->
        <VCard v-if="totalUsd > 0" variant="outlined" class="mb-4 rounded-lg">
          <VCardItem class="pa-4 pb-2">
            <template #prepend>
              <VAvatar color="success" variant="tonal" size="32" rounded>
                <VIcon icon="tabler-currency-dollar" size="16" />
              </VAvatar>
            </template>
            <VCardTitle class="text-body-1 font-weight-bold">Total USD</VCardTitle>
            <template #append>
              <VChip color="success" size="small" variant="flat" class="font-weight-bold">
                {{ formatCurrency(totalUsd, 'USD') }}
              </VChip>
            </template>
          </VCardItem>
          <VCardText class="pa-0 px-4 pb-3" v-if="usdFields.length">
            <VRow dense>
              <VCol v-for="f in usdFields" :key="f.label" cols="6" sm="4">
                <div class="d-flex flex-column py-1">
                  <span class="text-caption text-medium-emphasis">{{ f.label }}</span>
                  <span class="text-body-2 font-weight-medium">{{ f.val }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Sección BS -->
        <VCard v-if="totalBs > 0" variant="outlined" class="mb-4 rounded-lg">
          <VCardItem class="pa-4 pb-2">
            <template #prepend>
              <VAvatar color="warning" variant="tonal" size="32" rounded>
                <VIcon icon="tabler-cash" size="16" />
              </VAvatar>
            </template>
            <VCardTitle class="text-body-1 font-weight-bold">Total Bs</VCardTitle>
            <template #append>
              <VChip color="warning" size="small" variant="flat" class="font-weight-bold">
                {{ formatCurrency(totalBs, 'BS') }}
              </VChip>
            </template>
          </VCardItem>
          <VCardText class="pa-0 px-4 pb-3" v-if="bsFields.length">
            <VRow dense>
              <VCol v-for="f in bsFields" :key="f.label" cols="6" sm="4">
                <div class="d-flex flex-column py-1">
                  <span class="text-caption text-medium-emphasis">{{ f.label }}</span>
                  <span class="text-body-2 font-weight-medium">{{ f.val }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Sección COP -->
        <VCard variant="outlined" class="mb-4 rounded-lg">
          <VCardItem class="pa-4 pb-2">
            <template #prepend>
              <VAvatar color="info" variant="tonal" size="32" rounded>
                <VIcon icon="tabler-coin" size="16" />
              </VAvatar>
            </template>
            <VCardTitle class="text-body-1 font-weight-bold">Total COP</VCardTitle>
            <template #append>
              <VChip color="info" size="small" variant="flat" class="font-weight-bold">
                {{ formatCurrency(totalCopConSobrante, 'COP') }}
              </VChip>
            </template>
          </VCardItem>
          <VCardText class="pa-0 px-4 pb-3">
            <VRow dense>
              <VCol v-for="f in copFields" :key="f.label" cols="6" sm="4">
                <div class="d-flex flex-column py-1">
                  <span class="text-caption text-medium-emphasis">{{ f.label }}</span>
                  <span class="text-body-2 font-weight-medium">{{ f.val }}</span>
                </div>
              </VCol>
            </VRow>
            <!-- Sobrante en Peso -->
            <VRow dense class="mt-1">
              <VCol cols="12" sm="6">
                <VCard variant="tonal" color="warning" class="pa-3 rounded-lg">
                  <div class="d-flex align-center gap-2 mb-2">
                    <VIcon icon="tabler-alert-triangle" size="16" color="warning" />
                    <span class="text-caption font-weight-bold text-warning">Sobrante en Peso (COP)</span>
                  </div>
                  <VTextField
                    v-model="sobranteCop"
                    placeholder="0"
                    type="number"
                    density="compact"
                    variant="solo"
                    bg-color="white"
                    hide-details
                    prefix="$"
                  />
                </VCard>
              </VCol>
              <VCol cols="12" sm="6">
                <div class="d-flex flex-column pa-3 h-100 justify-center">
                  <span class="text-caption text-medium-emphasis mb-1">Efectivo Físico (COP)</span>
                  <span class="text-h6 font-weight-bold text-info">
                    {{ formatCurrency(totalEfectivoCopConSobrante, 'COP') }}
                  </span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Sección Créditos (solo si hay deuda generada o abonos) -->
        <VCard v-if="totalCredits > 0 || totalAbonos > 0" variant="outlined" class="mb-4 rounded-lg">
          <VCardItem class="pa-4 pb-2">
            <template #prepend>
              <VAvatar color="secondary" variant="tonal" size="32" rounded>
                <VIcon icon="tabler-credit-card" size="16" />
              </VAvatar>
            </template>
            <VCardTitle class="text-body-1 font-weight-bold">Créditos y Abonos</VCardTitle>
            <template #append>
              <VChip color="secondary" size="small" variant="flat" class="font-weight-bold">
                {{ formatCurrency(totalCredits, 'USD') }}
              </VChip>
            </template>
          </VCardItem>
          <VCardText class="pa-0 px-4 pb-3" v-if="creditFields.length">
            <VRow dense>
              <VCol v-for="f in creditFields" :key="f.label" cols="6" sm="4">
                <div class="d-flex flex-column py-1">
                  <span class="text-caption text-medium-emphasis">{{ f.label }}</span>
                  <span class="text-body-2 font-weight-medium">{{ f.val }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <!-- Resumen final a entregar -->
        <VCard variant="flat" color="primary" class="rounded-lg">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-2 mb-3">
              <VIcon icon="tabler-report-money" color="white" size="20" />
              <span class="text-body-2 font-weight-bold text-white">RESUMEN A ENTREGAR</span>
            </div>
            <VRow dense>
              <VCol cols="12" sm="4" v-if="totalUsd > 0">
                <div class="d-flex flex-column">
                  <span class="text-caption text-white opacity-70">Efectivo USD</span>
                  <span class="text-h6 font-weight-bold text-white">{{ formatCurrency(parseFloat(cashClosureData?.usd_delivered) || 0, 'USD') }}</span>
                </div>
              </VCol>
              <VCol cols="12" sm="4">
                <div class="d-flex flex-column">
                  <span class="text-caption text-white opacity-70">Efectivo COP</span>
                  <span class="text-h6 font-weight-bold text-white">{{ formatCurrency(totalEfectivoCopConSobrante, 'COP') }}</span>
                </div>
              </VCol>
              <VCol cols="12" sm="4" v-if="totalCredits > 0">
                <div class="d-flex flex-column">
                  <span class="text-caption text-white opacity-70">Créditos</span>
                  <span class="text-h6 font-weight-bold text-white">{{ formatCurrency(totalCredits, 'USD') }}</span>
                </div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 d-flex gap-3">
        <VBtn color="secondary" variant="tonal" @click="closeModal" class="flex-grow-1" size="large">
          Cancelar
        </VBtn>
        <VBtn color="error" variant="flat" @click="completeClosure" class="flex-grow-1" size="large"
          prepend-icon="tabler-lock">
          Cerrar Caja
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
