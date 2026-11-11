<script setup>
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatDateSimple } from "@/utils/formatters";

const props = defineProps({
  formData: { type: Object, required: true },
  formErrors: { type: Object, default: () => ({}) },
  isNewProduct: { type: Boolean, default: false },
  xs: { type: Boolean, default: false },
});

const authStore = useAuthStore();
const brandingStore = useBrandingStore();
const isRestaurant = computed(() => brandingStore.settings.business_type === "restaurant");
const isMiniMarket = computed(() => brandingStore.settings.business_type === "minimarket");
const isSportsRental = computed(() => brandingStore.settings.business_type === "sports_rental");

const isFieldEnabled = (fieldKey) => {
  const fields = brandingStore.settings?.product_form_fields;
  if (!fields || !Array.isArray(fields) || fields.length === 0) return true;
  return fields.includes(fieldKey);
};

const lotHeaders = [
  { title: "Nº DE LOTE", key: "lot_number", sortable: false },
  { title: "CANTIDAD", key: "quantity", sortable: false },
  { title: "FECHA EXPIRACIÓN", key: "expiration_date", sortable: false },
  { title: "UBICACIÓN", key: "location", sortable: false },
];

const onPurchaseCostChange = (val) => {
  const pCost = parseFloat(val) || 0;
  props.formData.purchase_cost = pCost > 0 ? pCost : null;
  const inner = parseFloat(props.formData.units_per_inner_pack) || 1;
  if (inner > 0 && pCost > 0) {
    props.formData.unit_cost = parseFloat((pCost / inner).toFixed(4));
  }
};

const onUnitCostChange = (val) => {
  const uCost = parseFloat(val) || 0;
  props.formData.unit_cost = uCost;
  const inner = parseFloat(props.formData.units_per_inner_pack) || 1;
  if (inner > 0 && uCost > 0) {
    props.formData.purchase_cost = parseFloat((uCost * inner).toFixed(4));
  }
};

const recipeUnitCost = computed(() => {
  const uCost = parseFloat(props.formData.unit_cost) || 0;
  const pres = parseFloat(props.formData.presentation) || 1;
  return pres > 0 ? (uCost / pres) : uCost;
});
</script>

<template>
  <div :class="[xs ? 'gap-4' : 'gap-6', 'd-flex flex-column']">
    <div class="d-flex flex-column gap-3">
      <div v-if="!isSportsRental" class="d-flex align-center gap-2">
        <div class="header-indicator primary shadow-sm" />
        <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración Logística</span>
      </div>

      <VCard
        variant="flat"
        :class="[xs ? 'pa-3' : 'pa-5', 'bg-surface rounded-xl border shadow-sm']"
      >
        <VRow dense>
          <VCol v-if="!isSportsRental" cols="12">
            <div class="d-flex flex-wrap gap-3 w-100 mb-2">
              <VCard
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.iva"
                  label="IVA"
                  :true-value="1"
                  :false-value="0"
                  color="success"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isRestaurant && !isMiniMarket"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.is_novaventa"
                  label="Novaventa"
                  :true-value="1"
                  :false-value="0"
                  color="secondary"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isRestaurant && !isMiniMarket"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.psychotropic"
                  label="Psicotrópico"
                  :true-value="1"
                  :false-value="0"
                  color="warning"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isRestaurant && !isMiniMarket"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.is_colombian_origin"
                  label="COL"
                  :true-value="1"
                  :false-value="0"
                  color="primary"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isMiniMarket"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.is_scarce"
                  label="Redundante"
                  :true-value="1"
                  :false-value="0"
                  color="error"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isMiniMarket"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.is_unified_group"
                  label="Unificado"
                  :true-value="1"
                  :false-value="0"
                  color="info"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="!isRestaurant && !isMiniMarket && !isSportsRental"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.is_chronic"
                  label="Crónico"
                  :true-value="1"
                  :false-value="0"
                  color="purple"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>

              <VCard
                v-if="isRestaurant"
                variant="flat"
                class="pa-3 bg-light rounded-xl border-dashed-2 d-flex align-center flex-grow-1"
                style="min-width: 140px; max-width: 220px;"
              >
                <VSwitch
                  v-model="formData.no_pvp"
                  label="NO PVP"
                  :true-value="1"
                  :false-value="0"
                  color="error"
                  density="compact"
                  hide-details
                  class="font-weight-black scale-90"
                />
              </VCard>
            </div>
          </VCol>

          <!-- Días de Duración del Tratamiento Crónico -->
          <VCol v-if="formData.is_chronic" cols="12" md="6" class="mt-2 mb-2">
            <VTextField v-model="formData.treatment_duration_days" label="Días de tratamiento por unidad"
              type="number"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="tabler-calendar-time"
              :error-messages="formErrors.treatment_duration_days"
              class="rounded-lg font-weight-black"
              hide-details="auto"
            />
          </VCol>

          <!-- Costo de Compra / Empaque Mayorista (Restaurante) -->
          <template v-if="isRestaurant && (Number(formData.units_per_inner_pack) > 1)">
            <VCol cols="12" sm="6" :md="isNewProduct ? 3 : 4" class="mt-4">
              <VTextField
                :model-value="formData.purchase_cost"
                @update:model-value="onPurchaseCostChange"
                :label="`Costo por ${formData.purchase_unit_name || 'Compra'} (x${formData.units_per_inner_pack})`"
                type="number"
                step="any"
                prefix="$"
                variant="outlined"
                density="comfortable"
                :readonly="!authStore.isAdmin && !isNewProduct"
                :error-messages="formErrors.purchase_cost"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>

            <VCol cols="12" sm="6" :md="isNewProduct ? 3 : 4" class="mt-4">
              <VTextField
                :model-value="formData.unit_cost"
                @update:model-value="onUnitCostChange"
                :label="`Costo por ${formData.pack_unit_name || 'Paquete'}`"
                type="number"
                step="any"
                prefix="$"
                variant="outlined"
                density="comfortable"
                :readonly="!authStore.isAdmin && !isNewProduct"
                :error-messages="formErrors.unit_cost"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>
          </template>

          <template v-else>
            <!-- Costo de Compra Estándar -->
            <VCol v-if="isFieldEnabled('unit_cost')" cols="12" :md="isNewProduct ? 4 : 6" class="mt-4">
              <VTextField
                v-model="formData.unit_cost"
                :label="isRestaurant ? `Costo por ${formData.pack_unit_name || 'Paquete'}` : 'Costo de Compra'"
                type="number"
                step="any"
                prefix="$"
                variant="outlined"
                density="comfortable"
                :readonly="!authStore.isAdmin && !isNewProduct"
                :error-messages="formErrors.unit_cost"
                class="rounded-lg font-weight-black"
                hide-details="auto"
              />
            </VCol>
          </template>

          <!-- Precio de Venta -->
          <VCol v-if="isFieldEnabled('sale_price')" cols="12" :md="isRestaurant && (Number(formData.units_per_inner_pack) > 1) ? (isNewProduct ? 3 : 4) : (isNewProduct ? 4 : 6)" class="mt-4">
            <VTextField v-model="formData.sale_price" label="Precio de Venta"
              type="number"
              step="any"
              prefix="$"
              variant="outlined"
              density="comfortable"
              :readonly="(authStore.isVendedor || authStore.isSupervisor) && !isNewProduct"
              :error-messages="formErrors.sale_price"
              class="rounded-lg font-weight-black"
              hide-details="auto"
            />
          </VCol>

          <!-- Stock Inicial -->
          <VCol v-if="isNewProduct && isFieldEnabled('stock')" cols="12" :md="isRestaurant && (Number(formData.units_per_inner_pack) > 1) ? 3 : 4" class="mt-4">
            <VTextField v-model="formData.initial_stock"
              :label="isRestaurant && Number(formData.presentation) > 0 ? `Stock Inicial (${formData.pack_unit_name || 'Paquetes'})` : 'Stock Inicial'"
              type="number"
              step="any"
              variant="outlined"
              density="comfortable"
              :error-messages="formErrors.initial_stock"
              class="rounded-lg font-weight-black"
              :hint="isRestaurant && Number(formData.presentation) > 0 ? `En paquetes/envases (${formData.presentation} ${formData.unit_of_measure || ''} c/u)` : undefined"
              :persistent-hint="Boolean(isRestaurant && Number(formData.presentation) > 0)"
              hide-details="auto"
            />
          </VCol>

          <!-- Desglose de Costo Unitario de Receta para Restaurante -->
          <VCol v-if="isRestaurant && Number(formData.presentation) > 0" cols="12" class="pt-2">
            <div class="pa-2 bg-light rounded-lg border text-xs d-flex align-center justify-space-between flex-wrap text-medium-emphasis">
              <span class="d-flex align-center gap-1">
                <VIcon icon="tabler-calculator" size="16" color="success" />
                Costo en receta: 
                <strong class="text-success">${{ recipeUnitCost.toFixed(4) }}</strong> por cada <strong>1 {{ formData.unit_of_measure || 'und' }}</strong>
              </span>
              <span class="text-super-xs text-disabled">
                ({{ formData.presentation }} {{ formData.unit_of_measure || 'und' }} por {{ formData.pack_unit_name || 'paquete' }})
              </span>
            </div>
          </VCol>
        </VRow>
      </VCard>
    </div>

    <!-- Lotes y Ubicación -->
    <div
      v-if="!isNewProduct && formData.lots?.length > 0"
      class="d-flex flex-column gap-3"
    >
      <div class="d-flex align-center justify-space-between mb-0">
        <div class="d-flex align-center gap-2">
          <div class="header-indicator secondary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Lotes y Ubicación</span>
        </div>
        <VChip
          size="x-small"
          color="secondary"
          variant="flat"
          class="font-weight-black rounded-lg px-3 shadow-sm"
        >
          {{ formData.lots.length }} LOTES ACTIVOS
        </VChip>
      </div>

      <VCard
        variant="flat"
        class="bg-surface rounded-xl border shadow-sm overflow-hidden"
      >
        <!-- Desktop Table -->
        <div class="d-none d-sm-block">
          <VDataTable
            :headers="lotHeaders"
            :items="formData.lots"
            density="comfortable"
            class="table-standard"
            hide-default-footer
          >
            <template #item.quantity="{ item }">
              <VChip
                size="x-small"
                :color="item.quantity > 0 ? 'success' : 'error'"
                variant="tonal"
                class="font-weight-black px-2 rounded-lg"
              >
                {{ item.quantity }} {{ isRestaurant ? 'PAQ.' : 'UNID.' }}
              </VChip>
            </template>
            <template #item.expiration_date="{ item }">
              <span class="text-caption font-weight-black text-high-emphasis">{{ formatDateSimple(item.expiration_date) }}</span>
            </template>
          </VDataTable>
        </div>

        <!-- Mobile Cards -->
        <div class="d-block d-sm-none pa-3">
          <div class="d-flex flex-column gap-2">
            <VCard
              v-for="item in formData.lots"
              :key="item.id"
              variant="flat"
              class="pa-3 bg-light rounded-xl border"
            >
              <div class="d-flex justify-space-between align-center mb-2">
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">LOTE: {{ item.lot_number }}</span>
                <VChip
                  size="x-small"
                  :color="item.quantity > 0 ? 'success' : 'error'"
                  variant="flat"
                  class="font-weight-black"
                >
                  {{ item.quantity }} <small class="ml-1 font-weight-bold">UNDS</small>
                </VChip>
              </div>
              <div class="d-flex justify-space-between text-super-xs text-medium-emphasis mt-2 border-t pt-2 opacity-80">
                <span class="d-flex align-center gap-1">
                  <VIcon icon="tabler-map-pin" size="12" color="primary" />
                  {{ item.location || "SIN UBICACIÓN" }}
                </span>
                <span class="d-flex align-center gap-1">
                  <VIcon icon="tabler-calendar" size="12" color="primary" />
                  {{ formatDateSimple(item.expiration_date) }}
                </span>
              </div>
            </VCard>
          </div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<style scoped>
.header-indicator {
  border-radius: 8px !important;
  block-size: 16px;
  inline-size: 4px;
}
.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}
.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}
.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}
.scale-90 {
  transform: scale(0.9);
  transform-origin: left center;
}
.letter-spacing-1 {
  letter-spacing: 1px !important;
}
.uppercase {
  text-transform: uppercase;
}
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}
</style>
