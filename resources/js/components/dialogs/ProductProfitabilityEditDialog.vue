<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { ref, watch, computed } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";

const props = defineProps({
  dialog: { type: Boolean, required: true },
  product: { type: Object, required: true },
  settings: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["refresh", "close-modal", "productUpdated"]);

const percentage = ref(0);
const shippingCost = ref(0);
const packagingCost = ref(0);
const expenseMargin = ref(0);
const profitMargin = ref(0);
const taxUsa = ref(0);
const loading = ref(false);

const brandingStore = useBrandingStore();
const isMinimarket = computed(() => {
  return brandingStore.settings?.business_type === 'minimarket' || props.settings?.profitability_calculation_type === 'compound';
});

const previewSalePrice = computed(() => {
  const cost = Number(props.product.unit_cost || 0);
  const roundUp = !!(brandingStore.settings?.round_usd_up || props.settings?.round_usd_up);
  let price = 0;
  if (isMinimarket.value) {
    const costWithTax = cost * (1 + Number(taxUsa.value) / 100);
    const fixedExpenseAmount = costWithTax * (Number(expenseMargin.value) / 100);
    const profitDenominator = 1 - (Number(profitMargin.value) / 100);
    if (profitDenominator <= 0) return "9999.99";
    price = (costWithTax + Number(shippingCost.value) + Number(packagingCost.value) + fixedExpenseAmount) / profitDenominator;
  } else {
    price = cost * (1 + Number(percentage.value) / 100);
  }
  return roundUp ? Math.ceil(price).toFixed(2) : price.toFixed(2);
});

const quickPercentages = [15, 20, 25, 30, 35];

const adjustPercentage = (delta) => {
  const current = Number(percentage.value) || 0;
  percentage.value = Math.max(0, current + delta);
};

const setQuickPercentage = (val) => {
  percentage.value = val;
};

watch(
  () => props.product,
  (val) => {
    if (val) {
      percentage.value = val.percentage || 0;
      shippingCost.value = val.shipping_cost || 0;
      packagingCost.value = val.packaging_cost || 0;
      expenseMargin.value = val.expense_margin || 0;
      profitMargin.value = val.profit_margin || 0;
      taxUsa.value = val.tax_usa || 0;
    }
  },
  { immediate: true, deep: true }
);

async function saveProfitability() {
  loading.value = true;
  const isUpdate = !!props.product.id;
  const url = isUpdate 
    ? "/finances/profitability/product/update" 
    : "/finances/profitability/product/store";
  
  const percentageValue = isMinimarket.value 
    ? (Number(expenseMargin.value) + Number(profitMargin.value)) 
    : Number(percentage.value);

  const data = {
    id: props.product.id,
    product_id: props.product.product_id,
    profitability_percentage: percentageValue,
    is_locked: 1,
    shipping_cost: shippingCost.value,
    packaging_cost: packagingCost.value,
    expense_margin: expenseMargin.value,
    profit_margin: profitMargin.value,
    tax_usa: taxUsa.value,
  };

  try {
    const response = await axios.post(url, data);
    toast.success("Rentabilidad del producto actualizada correctamente.");
    emit("close-modal");
    if (response.data?.data) {
      emit("productUpdated", response.data.data);
    } else {
      emit("refresh");
    }
  } catch (error) {
    console.error("Error al guardar rentabilidad:", error);
    toast.error("Error al guardar la rentabilidad del producto.");
  } finally {
    loading.value = false;
  }
}

const checkExistenceAndSave = async () => {
  if (props.product.id) {
    await saveProfitability();
    return;
  }

  try {
    const response = await axios.get(`/finances/profitability/product/${props.product.product_id}`);
    if (response.status === 200) {
      await saveProfitability();
    }
  } catch (error) {
    await saveProfitability();
  }
};
</script>

<template>
  <VDialog 
    :model-value="props.dialog" 
    max-width="520px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    @update:model-value="emit('close-modal')"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Encabezado Corporativo Estándar -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-trending-up"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              Ajuste de Rentabilidad
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Configuración de Margen Individual • {{ brandingStore.settings?.branch_name || 'Sucursal Principal' }}
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

      <VCardText class="pa-4 pa-sm-6 d-flex flex-column gap-4 bg-background">
        <!-- Perfil del Producto -->
        <VCard
          variant="flat"
          class="pa-4 bg-surface rounded-lg border"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <VChip
              size="small"
              color="primary"
              variant="tonal"
              class="font-weight-black rounded"
            >
              ID: {{ props.product.product_id }}
            </VChip>
            <div class="d-flex align-center gap-1 text-disabled">
              <VIcon
                icon="tabler-barcode"
                size="15"
              />
              <span class="text-super-xs font-weight-bold uppercase">Producto TPV</span>
            </div>
          </div>
          <h3 class="text-subtitle-1 font-weight-black text-high-emphasis leading-snug uppercase mb-0">
            {{ props.product.name || "Ajuste Directo de Margen" }}
          </h3>
        </VCard>

        <!-- Resumen Financiero en Vivo -->
        <VCard variant="flat" class="pa-3 bg-surface rounded-lg border">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">Costo Base</span>
              <span class="text-sm font-weight-bold text-high-emphasis">
                ${{ Number(props.product.unit_cost || 0).toFixed(2) }} USD
              </span>
            </div>
            <VDivider vertical class="mx-2" style="height: 32px;" />
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-bold text-medium-emphasis uppercase">PVP Actual</span>
              <span class="text-sm font-weight-bold text-medium-emphasis">
                ${{ Number(props.product.sale_price || 0).toFixed(2) }} USD
              </span>
            </div>
            <VDivider vertical class="mx-2" style="height: 32px;" />
            <div class="d-flex flex-column align-end">
              <span class="text-super-xs font-weight-bold text-primary uppercase">PVP Proyectado</span>
              <span class="text-base font-weight-black text-success">
                ${{ previewSalePrice }} USD
              </span>
            </div>
          </div>
        </VCard>

        <!-- Sección Minimarket (Campos Compuestos) -->
        <VCard
          v-if="isMinimarket"
          variant="flat"
          class="pa-4 bg-surface rounded-lg border"
        >
          <div class="text-xs font-weight-bold text-disabled uppercase mb-3 letter-spacing-1">
            Parámetros de Costo y Margen
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

        <!-- Sección Farmacia/Estándar (Input Numérico Robusto con Sugerencias) -->
        <VCard
          v-else
          variant="flat"
          class="pa-4 bg-surface rounded-lg border"
        >
          <div class="d-flex align-center justify-space-between mb-2">
            <span class="text-xs font-weight-bold text-disabled uppercase letter-spacing-1">
              Margen de Utilidad Objetivo
            </span>
          </div>

          <div class="d-flex align-center gap-2 mb-3">
            <VBtn
              icon
              variant="tonal"
              color="secondary"
              size="40"
              class="rounded-lg"
              @click="adjustPercentage(-5)"
              :disabled="Number(percentage) <= 0"
            >
              <VIcon icon="tabler-minus" size="20" />
              <VTooltip activator="parent" location="top">-5%</VTooltip>
            </VBtn>

            <VTextField
              v-model="percentage"
              type="number"
              suffix="%"
              placeholder="0"
              variant="outlined"
              density="comfortable"
              class="text-center font-weight-bold"
              hide-details
              autofocus
              @keyup.enter="checkExistenceAndSave"
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

          <!-- Botones de Sugerencia Rápida -->
          <div class="d-flex align-center justify-space-between flex-wrap gap-1">
            <span class="text-super-xs font-weight-bold text-medium-emphasis">Sugeridos:</span>
            <div class="d-flex align-center gap-1">
              <VChip
                v-for="p in quickPercentages"
                :key="p"
                size="small"
                variant="tonal"
                :color="Number(percentage) === p ? 'primary' : 'secondary'"
                class="font-weight-black cursor-pointer"
                @click="setQuickPercentage(p)"
              >
                {{ p }}%
              </VChip>
            </div>
          </div>
        </VCard>

        <!-- Alerta Semántica Tonal -->
        <VAlert
          type="warning"
          variant="tonal"
          density="compact"
          class="rounded-lg border-0"
          icon="tabler-shield-lock"
        >
          <span class="text-caption font-weight-medium text-high-emphasis">
            Al guardar, el producto quedará <strong>bloqueado</strong> y excluido de ajustes masivos de rentabilidad global.
          </span>
        </VAlert>
      </VCardText>

      <VDivider />

      <!-- Acciones del Modal (Botones al 50% de ancho) -->
      <VCardActions class="pa-4 bg-surface px-6">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="emit('close-modal')"
              :disabled="loading"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="loading"
              :disabled="loading || (!isMinimarket && (percentage === null || percentage === ''))"
              @click="checkExistenceAndSave"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
              {{ props.product.id ? 'Actualizar' : 'Guardar' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 0.05rem !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.leading-snug {
  line-height: 1.35 !important;
}

.border-b {
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
}
</style>

