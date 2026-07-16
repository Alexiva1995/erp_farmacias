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
const isMinimarket = computed(() => brandingStore.settings?.business_type === 'minimarket');

const localPercentage = ref(0);
const shippingCost = ref(0);
const packagingCost = ref(0);
const expenseMargin = ref(0);
const profitMargin = ref(0);
const loading = ref(false);

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
    max-width="460px"
    persistent
    :fullscreen="$vuetify.display.smAndDown"
    :transition="$vuetify.display.smAndDown ? 'dialog-bottom-transition' : 'scale-transition'"
    @update:model-value="emit('close-modal')"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Header Premium -->
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
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="emit('close-modal')"
          />
        </div>
      </VCardTitle>

      <!-- Contenido Premium -->
      <VCardText class="pa-4 pa-sm-6 bg-light">
        <!-- Bloque Informativo -->
        <VAlert
          variant="tonal"
          color="warning"
          rounded="lg"
          class="mb-6"
          border="start"
        >
          <template #prepend>
            <VIcon
              icon="tabler-alert-triangle"
              size="22"
              class="me-1"
            />
          </template>
          <div class="text-xs font-weight-black uppercase mb-1">
            Información Importante
          </div>
          <div class="text-sm opacity-90 leading-tight">
            Se actualizará el margen de utilidad de <strong>todos los productos</strong> no bloqueados.
          </div>
        </VAlert>

        <div class="d-flex align-center gap-2 mb-4">
          <div class="header-indicator primary shadow-sm" />
          <span
            class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1"
            >Configuración de Margen</span
          >
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-lg elevation-1 border"
        >
          <VRow v-if="isMinimarket">
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="shippingCost"
                label="Envío por Unidad (USD)"
                placeholder="Ej: 0.90"
                type="number"
                prepend-inner-icon="tabler-truck-delivery"
                density="comfortable"
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
                density="comfortable"
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
                density="comfortable"
                hide-details="auto"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <AppTextField
                v-model="profitMargin"
                label="Margen Ganancia Deseada (%)"
                placeholder="Ej: 30"
                type="number"
                suffix="%"
                prepend-inner-icon="tabler-trending-up"
                density="comfortable"
                hide-details="auto"
              />
            </VCol>
          </VRow>
          <VRow v-else>
            <VCol cols="12">
              <AppTextField
                v-model="localPercentage"
                label="Nuevo Porcentaje de Rentabilidad"
                placeholder="Ej: 25"
                type="number"
                suffix="%"
                autofocus
                prepend-inner-icon="tabler-chart-arrows-vertical"
                density="comfortable"
                hide-details="auto"
                :rules="[(v) => v >= 0 || 'El porcentaje no puede ser negativo']"
              />
              <div class="mt-2 text-super-xs text-disabled">
                * El precio de venta se recalculará automáticamente basado en el costo.
              </div>
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow
          no-gutters
          class="w-100"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="emit('close-modal')"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="storeProfitability"
              :loading="loading"
              :disabled="loading || (isMinimarket ? false : !localPercentage)"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
                class="me-2"
              />
              Guardar Ajuste
            </VBtn>
          </VCol>
        </VRow>
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

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
