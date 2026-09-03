<script setup>
import { computed } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useBrandingStore } from "@/stores/useBrandingStore";
import { formatCurrency } from "@/utils/currencyFormatter";

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  mode: {
    type: String,
    default: "products",
  },
  onlyDeleted: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits([
  "toggle-favorite",
  "toggle-active",
  "view-stats",
  "edit",
  "restore",
  "merge",
  "delete",
  "count",
  "add-to-invoice",
]);

const authStore = useAuthStore();
const brandingStore = useBrandingStore();

const isRestaurant = computed(() => brandingStore.settings.business_type === "restaurant");
const isMiniMarket = computed(() => brandingStore.settings.business_type === "minimarket");
const isSportsRental = computed(() => brandingStore.settings.business_type === "sports_rental");

const formatStock = (item) => {
  const stock = Number(item.stock_calculado ?? 0);
  if (isSportsRental.value || isMiniMarket.value) {
    return Math.round(stock).toString();
  }
  if (!isRestaurant.value) {
    return stock % 1 === 0 ? stock.toString() : stock.toFixed(2).replace(".", ",");
  }
  if (!item.unit_of_measure) {
    const formatted = stock.toString().replace(".", ",");
    return `${formatted} UNDS`;
  }
  const presentation = Number(item.presentation) || 0;
  if (presentation > 0 && (item.unit_of_measure === "g" || item.unit_of_measure === "ml")) {
    const unit = item.unit_of_measure;
    const totalUnits = Math.round(stock * 1000);
    if (totalUnits < 0) {
      return `${totalUnits} ${unit}`;
    }
    const fullPackages = Math.floor(totalUnits / presentation);
    const remainder = totalUnits % presentation;
    if (fullPackages > 0 && remainder > 0) {
      return `${fullPackages} paq + ${remainder} ${unit}`;
    } else if (fullPackages > 0) {
      return `${fullPackages} paq`;
    } else {
      return `${remainder} ${unit}`;
    }
  }
  if (item.unit_of_measure === "g") {
    const val = Math.round(stock * 1000);
    return `${val} g`;
  }
  if (item.unit_of_measure === "ml") {
    const val = Math.round(stock * 1000);
    return `${val} ml`;
  }
  if (item.unit_of_measure === "und") {
    const formatted = stock.toString().replace(".", ",");
    return `${formatted} unidades`;
  }
  const formatted = stock.toString().replace(".", ",");
  return `${formatted} UNDS`;
};

const calculateSalePriceWithIva = (product) => {
  const isCopPrice = isRestaurant.value && Boolean(product.sale_price_cop);
  const basePrice = isRestaurant.value
    ? Number(product.sale_price_cop || 0)
    : Number(product.sale_price || 0);
  const finalPrice = product.iva == 1 ? basePrice * 1.16 : basePrice;
  return { price: finalPrice, isAlreadyConverted: isCopPrice };
};

const formatPriceWithCurrency = (price, isAlreadyConverted = false) => {
  const numPrice = Number(price);
  if (isNaN(numPrice)) return "—";

  const currency = brandingStore.settings?.default_currency || "USD";

  if (isAlreadyConverted || currency === "USD") {
    return formatCurrency(numPrice, currency);
  }

  const rates = Array.isArray(brandingStore.exchangeRates) ? brandingStore.exchangeRates : [];

  if (currency === "COP") {
    const copObj = rates.find((r) => r && (r.currency_code === "COP" || r.currency_code === "COPC"));
    const copRate = Number(copObj?.rate || 4000);
    const rounded = Math.ceil((numPrice * copRate) / 100) * 100;
    return formatCurrency(rounded, "COP");
  }

  if (currency === "BS" || currency === "Bs") {
    const bsObj = rates.find((r) => r && r.currency_code === "BS");
    const bsRate = Number(bsObj?.rate || 1);
    return formatCurrency(numPrice * bsRate, "BS");
  }

  return formatCurrency(numPrice, currency);
};
</script>

<template>
  <VCard variant="flat" class="product-mobile-card border mb-1">
    <div class="pa-2 pa-sm-3">
      <div class="d-flex gap-2 align-start">
        <!-- Corazón interactivo de favorito para móvil -->
        <VBtn
          v-if="((!isRestaurant && !isSportsRental) || isMiniMarket) && brandingStore.settings.enable_favorites"
          icon
          variant="text"
          density="compact"
          class="flex-shrink-0 mt-0"
          :color="item.is_favorite ? 'error' : 'secondary'"
          @click.stop="emit('toggle-favorite', item)"
        >
          <VIcon :icon="item.is_favorite ? 'tabler-heart-filled' : 'tabler-heart'" size="18" />
        </VBtn>
        <div class="flex-grow-1 min-width-0">
          <div class="d-flex align-center gap-1 mb-1">
            <h3 class="product-mobile-title font-weight-bold text-high-emphasis text-uppercase truncate-2-lines mb-0">
              <a
                :href="'/inventory/traceability?q=' + item.id"
                target="_blank"
                class="text-decoration-none text-primary font-weight-black"
              >
                {{ item.id }}
              </a>
              <template v-if="!isMiniMarket && item.laboratory?.name">
                <span class="mx-1 text-disabled font-weight-regular">|</span>
                <span class="text-primary font-weight-bold">{{ item.laboratory.name }}</span>
              </template>
              <template v-else-if="isMiniMarket && item.category?.name">
                <span class="mx-1 text-disabled font-weight-regular">|</span>
                <span class="text-primary font-weight-bold">{{ item.category.name }}</span>
              </template>
              <span class="mx-1 text-disabled font-weight-regular">|</span>
              <span>{{ item.name.toUpperCase() }}</span>
            </h3>
            <VChip v-if="item.psychotropic" color="warning" size="x-small" label variant="flat" class="text-super-xs flex-shrink-0">PSI</VChip>
          </div>
          
          <div v-if="(!isRestaurant && item.active_ingredient && item.active_ingredient !== 'N/A') || (isRestaurant && item.presentation)" class="d-flex align-center flex-wrap gap-x-2 text-xs">
            <span v-if="!isRestaurant && item.active_ingredient && item.active_ingredient !== 'N/A'" class="text-medium-emphasis text-truncate" style="max-inline-size: 260px;">
              {{ item.active_ingredient }}
            </span>
            <span v-if="isRestaurant && item.presentation" class="text-medium-emphasis text-truncate" style="max-inline-size: 260px;">
              {{ item.presentation }} {{ item.unit_of_measure ? `(${item.unit_of_measure})` : '' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Caja compacta de Stock y P.V.P -->
      <div class="d-flex align-center justify-space-between bg-var-theme-background px-2 py-1 mt-2 rounded border-dashed-thin">
        <div class="d-flex align-center gap-2">
          <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">Stock:</span>
          <span :class="(item.stock_calculado ?? 0) > 0 ? 'text-success' : 'text-error'" class="text-xs font-weight-bold">
            {{ formatStock(item) }}
          </span>
        </div>
        <div v-if="!isRestaurant" class="d-flex align-center gap-1.5 text-right">
          <span class="text-super-xs text-disabled text-uppercase font-weight-bold letter-spacing-1">
            {{ item.iva == 1 ? 'P.V.P (IVA):' : 'P.V.P:' }}
          </span>
          <span class="text-xs font-weight-black text-primary">
            {{ formatPriceWithCurrency(calculateSalePriceWithIva(item).price, calculateSalePriceWithIva(item).isAlreadyConverted) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Acciones Rectangulares perfectamente centradas -->
    <div class="d-flex align-center border-t border-opacity-10 mobile-actions-bar">
      <template v-if="mode === 'products'">
        <VBtn 
          v-if="brandingStore.settings.enable_bulk_toggle_active !== false"
          :color="item.is_active !== false && item.is_active !== 0 ? 'success' : 'error'" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('toggle-active', item)"
        >
          <VIcon icon="tabler-power" size="18" />
        </VBtn>
        <VDivider v-if="brandingStore.settings.enable_bulk_toggle_active !== false" vertical class="border-opacity-10" />
        <VBtn 
          color="primary" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('view-stats', item)"
        >
          <VIcon icon="tabler-eye" size="18" />
        </VBtn>
        <VDivider vertical class="border-opacity-10" />
        <VBtn 
          color="warning" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('edit', item)"
        >
          <VIcon icon="tabler-edit" size="18" />
        </VBtn>
        <VDivider v-if="onlyDeleted" vertical class="border-opacity-10" />
        <VBtn 
          v-if="onlyDeleted"
          color="success" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('restore', item.id)"
        >
          <VIcon icon="tabler-rotate-clockwise" size="18" />
        </VBtn>
        <VDivider v-if="authStore.isAdmin && brandingStore.settings.enable_merge" vertical class="border-opacity-10" />
        <VBtn 
          v-if="authStore.isAdmin && brandingStore.settings.enable_merge" 
          color="info" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('merge', item)"
        >
          <VIcon icon="tabler-package" size="18" />
        </VBtn>
        <VDivider v-if="authStore.isAdmin" vertical class="border-opacity-10" />
        <VBtn 
          v-if="authStore.isAdmin" 
          color="error" 
          variant="text" 
          class="flex-grow-1 rounded-0 mobile-action-btn d-flex align-center justify-center" 
          height="38"
          @click="emit('delete', item.id)"
        >
          <VIcon icon="tabler-trash" size="18" />
        </VBtn>
      </template>

      <template v-else-if="mode === 'inventory'">
        <VBtn 
          block 
          color="success" 
          variant="flat" 
          class="rounded-0 mobile-action-btn d-flex align-center justify-center"
          height="40"
          @click="emit('count', item)"
        >
          <VIcon icon="tabler-scan" size="20" />
        </VBtn>
      </template>

      <template v-else-if="mode === 'add-to-invoice'">
        <VBtn 
          block 
          color="success" 
          variant="flat" 
          class="rounded-0 mobile-action-btn d-flex align-center justify-center"
          height="40"
          @click="emit('add-to-invoice', item)"
        >
          <VIcon icon="tabler-plus" size="20" />
        </VBtn>
      </template>
    </div>
  </VCard>
</template>

<style scoped>
.product-mobile-card {
  overflow: hidden;
  border-radius: 8px !important;
  background: rgb(var(--v-theme-surface));
}

.product-mobile-title {
  font-size: 0.8125rem !important;
  line-height: 1.25 !important;
  letter-spacing: -0.01em;
}

.mobile-actions-bar {
  min-height: 38px;
}

.mobile-action-btn {
  padding: 0 !important;
  min-width: 0 !important;
}

.mobile-action-btn :deep(.v-btn__content) {
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  width: 100% !important;
  height: 100% !important;
}

.truncate-2-lines {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.border-dashed-thin {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }
</style>
