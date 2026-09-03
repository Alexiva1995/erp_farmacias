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

          <!-- Costo de Compra -->
          <VCol v-if="isFieldEnabled('unit_cost')" cols="12" :md="isNewProduct ? 4 : 6" class="mt-4">
            <AppTextField
              v-model="formData.unit_cost"
              placeholder="Costo de Compra"
              type="number"
              prefix="$"
              variant="outlined"
              density="comfortable"
              :readonly="!authStore.isAdmin && !isNewProduct"
              :error-messages="formErrors.unit_cost"
              class="rounded-lg font-weight-black"
              hide-details="auto"
            />
          </VCol>

          <!-- Precio de Venta -->
          <VCol v-if="isFieldEnabled('sale_price')" cols="12" :md="isNewProduct ? 4 : 6" class="mt-4">
            <AppTextField
              v-model="formData.sale_price"
              placeholder="Precio de Venta"
              type="number"
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
          <VCol v-if="isNewProduct && isFieldEnabled('stock')" cols="12" md="4" class="mt-4">
            <AppTextField
              v-model="formData.initial_stock"
              placeholder="Stock Inicial"
              type="number"
              variant="outlined"
              density="comfortable"
              :error-messages="formErrors.initial_stock"
              class="rounded-lg font-weight-black"
              hide-details="auto"
            />
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
                {{ item.quantity }} UNID.
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
