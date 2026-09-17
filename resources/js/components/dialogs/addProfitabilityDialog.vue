<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch, computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  percentage: { type: Number, default: 0 },
  settings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["refresh", "close-modal", "update:dialog"]);

const brandingStore = useBrandingStore();
const isMinimarket = computed(() => {
  return brandingStore.settings?.business_type === 'minimarket' || props.settings?.profitability_calculation_type === 'compound';
});

const localPercentage = ref(0);
const shippingCost = ref(0);
const packagingCost = ref(0);
const expenseMargin = ref(0);
const profitMargin = ref(0);
const taxUsa = ref(0);
const loading = ref(false);

const quickPercentages = [15, 20, 25, 30, 35];

const adjustPercentage = (delta) => {
  const current = Number(localPercentage.value) || 0;
  localPercentage.value = Math.max(0, current + delta);
};

const setQuickPercentage = (val) => {
  localPercentage.value = val;
};

watch(
  () => props.percentage,
  (val) => {
    localPercentage.value = val;
  },
  { immediate: true },
);

watch(
  () => props.settings,
  (val) => {
    if (val) {
      shippingCost.value = val.shipping_cost || 0;
      packagingCost.value = val.packaging_cost || 0;
      expenseMargin.value = val.expense_margin || 0;
      profitMargin.value = val.profit_margin || 0;
      taxUsa.value = val.tax_usa || 0;
    }
  },
  { immediate: true, deep: true }
);

async function storeProfitability() {
  const percentageValue = isMinimarket.value
    ? (Number(expenseMargin.value) + Number(profitMargin.value))
    : Number(localPercentage.value);

  const data = {
    default_profitability_percentage: percentageValue,
    shipping_cost: shippingCost.value,
    packaging_cost: packagingCost.value,
    expense_margin: expenseMargin.value,
    profit_margin: profitMargin.value,
    tax_usa: taxUsa.value,
  };

  loading.value = true;
  try {
    await axios.post("/finances/profitability/store", data);
    toast.success(
      "Rentabilidad asignada correctamente. Se actualizaron los precios de venta de los productos no bloqueados.",
    );
    emit("refresh");
    emit("close-modal");
  } catch (error) {
    console.error("Error en la solicitud:", error);
    const message =
      error.response?.data?.message ||
      error.response?.data?.errors?.default_profitability_percentage?.[0] ||
      "Error al asignar rentabilidad.";
    toast.error(message);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <VDialog
    :model-value="props.dialog"
    max-width="480px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    :transition="$vuetify.display.smAndDown ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="emit('close-modal')"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Corporativo -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-percentage"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Asignar Rentabilidad
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Ajuste Global de Precios
              </span>
            </div>
          </div>
          <VSpacer />
          <IconBtn
            color="white"
            variant="tonal"
            size="small"
            class="rounded-lg"
            @click="emit('close-modal')"
            :disabled="loading"
          >
            <VIcon icon="tabler-x" size="20" />
            <VTooltip activator="parent" location="top">Cerrar</VTooltip>
          </IconBtn>
        </div>
      </VCardTitle>

      <!-- Contenido -->
      <VCardText class="pa-4 pa-sm-6 bg-background d-flex flex-column gap-4">
        <!-- Bloque Informativo de Alta Legibilidad -->
        <VAlert
          variant="tonal"
          type="warning"
          rounded="lg"
          density="comfortable"
          class="border-0"
          icon="tabler-alert-triangle"
        >
          <div class="text-xs font-weight-black uppercase mb-1">
            Información Importante
          </div>
          <div class="text-sm leading-tight text-high-emphasis">
            Se actualizará el margen de utilidad de <strong>todos los productos</strong> que no se encuentren bloqueados.
          </div>
        </VAlert>

        <!-- Minimarket (Campos Compuestos) -->
        <VCard
          v-if="isMinimarket"
          variant="flat"
          class="pa-4 bg-surface rounded-lg border"
        >
          <div class="text-xs font-weight-bold text-disabled uppercase mb-3 letter-spacing-1">
            Parámetros de Costo y Margen Global
          </div>
          <VRow dense>
            <VCol cols="12">
              <AppTextField
                v-model="taxUsa"
                label="TAX (USA) (%)"
                placeholder="Ej: 7"
                type="number"
                suffix="%"
                prepend-inner-icon="tabler-receipt-tax"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="shippingCost"
                label="Envío por Unidad (USD)"
                placeholder="Ej: 0.90"
                type="number"
                prepend-inner-icon="tabler-truck-delivery"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="packagingCost"
                label="Embalaje por Unidad (USD)"
                placeholder="Ej: 1.20"
                type="number"
                prepend-inner-icon="tabler-box"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="expenseMargin"
                label="Margen Gasto Fijo (%)"
                placeholder="Ej: 26"
                type="number"
                suffix="%"
                prepend-inner-icon="tabler-percentage"
                density="compact"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="profitMargin"
                label="Margen Ganancia (%)"
                placeholder="Ej: 30"
                type="number"
                suffix="%"
                prepend-inner-icon="tabler-trending-up"
                density="compact"
                hide-details="auto"
              />
            </VCol>
          </VRow>
        </VCard>

        <!-- Modo Estándar / Farmacia (Selector Robusto con Botones y Presets) -->
        <VCard
          v-else
          variant="flat"
          class="pa-4 bg-surface rounded-lg border"
        >
          <div class="text-xs font-weight-bold text-disabled uppercase mb-2 letter-spacing-1">
            Nuevo Porcentaje de Rentabilidad
          </div>

          <div class="d-flex align-center gap-2 mb-3">
            <VBtn
              icon
              variant="tonal"
              color="secondary"
              size="40"
              class="rounded-lg"
              @click="adjustPercentage(-5)"
              :disabled="Number(localPercentage) <= 0"
            >
              <VIcon icon="tabler-minus" size="20" />
              <VTooltip activator="parent" location="top">-5%</VTooltip>
            </VBtn>

            <VTextField
              v-model="localPercentage"
              type="number"
              suffix="%"
              placeholder="0"
              variant="outlined"
              density="comfortable"
              class="text-center font-weight-bold"
              hide-details
              autofocus
              @keyup.enter="storeProfitability"
            />

            <VBtn
              icon
              variant="tonal"
              color="primary"
              size="40"
              class="rounded-lg"
              @click="adjustPercentage(5)"
            >
              <VIcon icon="tabler-plus" size="20" />
              <VTooltip activator="parent" location="top">+5%</VTooltip>
            </VBtn>
          </div>

          <!-- Sugerencias Rápidas -->
          <div class="d-flex align-center justify-space-between flex-wrap gap-1">
            <span class="text-super-xs font-weight-bold text-medium-emphasis">Sugeridos:</span>
            <div class="d-flex align-center gap-1">
              <VChip
                v-for="p in quickPercentages"
                :key="p"
                size="small"
                variant="tonal"
                :color="Number(localPercentage) === p ? 'primary' : 'secondary'"
                class="font-weight-black cursor-pointer"
                @click="setQuickPercentage(p)"
              >
                {{ p }}%
              </VChip>
            </div>
          </div>

          <div class="mt-3 text-super-xs text-medium-emphasis">
            ℹ️ El precio de venta de los productos se recalculará automáticamente según su costo base.
          </div>
        </VCard>
      </VCardText>

      <VDivider />

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-surface d-flex justify-end gap-2">
        <VBtn
          color="secondary"
          variant="outlined"
          class="font-weight-bold rounded-lg px-4"
          @click="emit('close-modal')"
          :disabled="loading"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          class="font-weight-bold rounded-lg px-5"
          @click="storeProfitability"
          :loading="loading"
          :disabled="loading || (!isMinimarket && (localPercentage === null || localPercentage === ''))"
        >
          <VIcon
            start
            icon="tabler-device-floppy"
            size="18"
          />
          Guardar Ajuste
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    rgb(var(--v-theme-gradient-end)) 100%
  );
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.05rem !important;
}

.leading-none {
  line-height: 1 !important;
}
</style>

