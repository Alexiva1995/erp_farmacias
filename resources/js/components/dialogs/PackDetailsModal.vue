<script setup>
import { useDisplay } from "vuetify";
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  pack: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const { mobile } = useDisplay();

const handleClose = () => {
  emit("update:isDialogVisible", false);
};

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

// Normalizar productos del pack
const packProducts = computed(() => {
  if (!props.pack) return [];

  // Si tiene products_info (formato del API)
  if (props.pack.products_info && Array.isArray(props.pack.products_info)) {
    return props.pack.products_info.map((p) => ({
      id: p.product_id,
      name: p.product_name,
      quantity: p.quantity || 1,
      active_ingredient: p.product_info?.active_ingredient || "",
      laboratory: p.product_info?.laboratory || "",
      photo_url: p.product_info?.photo_url || null,
      sale_price: p.sale_price || 0,
      discount_percentage: p.discount_percentage || 0,
      unit_price: p.sale_price || 0,
    }));
  }

  // Si tiene products (relación Eloquent)
  if (props.pack.products && Array.isArray(props.pack.products)) {
    return props.pack.products.map((p) => ({
      id: p.id,
      name: p.name,
      quantity: p.pivot?.quantity || 1,
      active_ingredient: p.active_ingredient || "",
      laboratory: p.laboratory?.name || "",
      photo_url: p.photo_url || null,
      sale_price: p.sale_price || 0,
      discount_percentage: 0,
      unit_price: p.sale_price || 0,
    }));
  }

  return [];
});

const totalProductsCount = computed(() => {
  return packProducts.value.reduce((sum, p) => sum + p.quantity, 0);
});

// Calcular precio regular base (sin descuento de pack)
const regularTotalPrice = computed(() => {
  return packProducts.value.reduce((sum, item) => sum + (Number(item.unit_price) || 0) * (item.quantity || 1), 0);
});

// Total de ahorro
const totalSavings = computed(() => {
  const finalPrice = parseFloat(props.pack?.total_price || 0);
  const base = regularTotalPrice.value;
  return base > finalPrice ? base - finalPrice : 0;
});

// Porcentaje de ahorro
const savingsPercentage = computed(() => {
  if (regularTotalPrice.value <= 0 || totalSavings.value <= 0) return 0;
  return Math.round((totalSavings.value / regularTotalPrice.value) * 100);
});

// Calcular precio con descuento
const calculatePriceWithDiscount = (item) => {
  const basePrice = item.unit_price || 0;
  const discount = item.discount_percentage || 0;
  if (discount > 0) {
    return basePrice * (1 - discount / 100);
  }
  return basePrice;
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    :max-inline-size="mobile ? '100%' : '840px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
    class="premium-dialog"
  >
    <VCard v-if="props.pack" :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Header Premium Standard -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1">
            <VIcon icon="tabler-packages" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ props.pack.name || 'Detalle del Pack' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Información Completa de la Oferta
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="handleClose"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <!-- Opción A: Barra de Resumen Integrada (Kpi Strip) -->
        <div class="pa-3 px-4 rounded border bg-var-theme-background mb-4 d-flex align-center justify-space-between flex-wrap gap-3">
          <!-- Precio Pack / Inversión -->
          <div class="d-flex align-center gap-2">
            <VAvatar size="34" color="primary" variant="tonal" class="rounded">
              <VIcon icon="tabler-currency-dollar" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Precio Pack</span>
              <span class="text-h6 font-weight-black text-primary leading-tight">
                {{ formatCurrency(parseFloat(props.pack.total_price || 0), 'USD') }}
              </span>
            </div>
          </div>

          <VDivider vertical class="d-none d-sm-block" style="height: 32px;" />

          <!-- Total Productos / Ítems -->
          <div class="d-flex align-center gap-2">
            <VAvatar size="34" color="secondary" variant="tonal" class="rounded">
              <VIcon icon="tabler-packages" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Total Ítems</span>
              <span class="text-h6 font-weight-black text-high-emphasis leading-tight">
                {{ totalProductsCount }} <span class="text-caption font-weight-bold text-disabled">UNDS</span>
              </span>
            </div>
          </div>

          <VDivider vertical class="d-none d-sm-block" style="height: 32px;" />

          <!-- Ahorro del Pack -->
          <div class="d-flex align-center gap-2">
            <VAvatar size="34" :color="totalSavings > 0 ? 'success' : 'secondary'" variant="tonal" class="rounded">
              <VIcon :icon="totalSavings > 0 ? 'tabler-discount-check' : 'tabler-tag'" size="18" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-disabled uppercase">Ahorro Estimado</span>
              <span v-if="totalSavings > 0" class="text-h6 font-weight-black text-success leading-tight">
                {{ formatCurrency(totalSavings, 'USD') }} <span class="text-caption font-weight-black">({{ savingsPercentage }}% OFF)</span>
              </span>
              <span v-else class="text-body-2 font-weight-bold text-disabled leading-tight">
                Sin Ahorro
              </span>
            </div>
          </div>

          <VDivider vertical class="d-none d-sm-block" style="height: 32px;" />

          <!-- Estado directo -->
          <div class="d-flex align-center gap-2">
            <div class="d-flex flex-column">
              <span class="text-super-xs font-weight-black text-disabled uppercase mb-0-5">Disponibilidad</span>
              <VChip
                :color="props.pack.is_active ? 'success' : 'secondary'"
                variant="tonal"
                size="small"
                class="font-weight-black rounded"
              >
                {{ props.pack.is_active ? 'HABILITADO' : 'DESACTIVADO' }}
              </VChip>
            </div>
          </div>
        </div>

        <!-- Detalle de Productos -->
        <div class="mb-4">
          <div class="d-flex align-center gap-1-5 mb-3">
            <div class="header-indicator primary" />
            <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Detalle de Productos</span>
          </div>

          <VDataTable
            v-if="!mobile"
            :headers="[
              { title: 'CANT', key: 'quantity', align: 'center', width: '70px', sortable: false },
              { title: 'PRODUCTO', key: 'name', sortable: false },
              { title: 'UNITARIO', key: 'unit_price', align: 'end', sortable: false },
              { title: 'DESC.', key: 'discount_percentage', align: 'center', sortable: false },
              { title: 'CON DESC.', key: 'price_with_discount', align: 'end', sortable: false },
              { title: 'SUBTOTAL', key: 'subtotal', align: 'end', sortable: false },
            ]"
            :items="packProducts"
            density="compact"
            class="internal-table rounded-lg border shadow-none bg-surface"
            no-data-text="No hay productos registrados"
            hide-default-footer
          >
            <template #item.quantity="{ item }">
              <span class="qty-badge font-weight-black">
                {{ item.quantity }}
              </span>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex flex-column py-2">
                <span class="text-body-2 font-weight-black text-high-emphasis text-uppercase leading-tight">
                  {{ item.name }}
                </span>
                <div class="d-flex align-center gap-1 text-super-xs mt-1">
                  <span class="text-disabled truncate">{{ item.active_ingredient || 'Principio No Registrado' }}</span>
                  <span class="text-disabled mx-1">•</span>
                  <span class="text-medium-emphasis font-weight-bold uppercase">{{ item.laboratory || 'Genérico' }}</span>
                </div>
              </div>
            </template>

            <template #item.unit_price="{ item }">
              <span class="text-caption font-weight-medium text-medium-emphasis">
                {{ formatCurrency(item.unit_price, 'USD') }}
              </span>
            </template>

            <template #item.discount_percentage="{ item }">
              <VChip v-if="item.discount_percentage > 0" color="error" variant="tonal" size="x-small" class="font-weight-black rounded">
                -{{ item.discount_percentage }}%
              </VChip>
              <span v-else class="text-disabled">-</span>
            </template>

            <template #item.price_with_discount="{ item }">
              <span class="text-caption font-weight-bold text-high-emphasis">
                {{ formatCurrency(calculatePriceWithDiscount(item), 'USD') }}
              </span>
            </template>

            <template #item.subtotal="{ item }">
              <span class="text-body-2 font-weight-black text-success">
                {{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity, 'USD') }}
              </span>
            </template>
          </VDataTable>

          <!-- Móvil: Tarjetas Compactas -->
          <div v-else class="d-flex flex-column gap-2">
            <div v-for="(item, idx) in packProducts" :key="idx" class="border pa-3 rounded-lg bg-surface stat-box">
              <div class="d-flex align-center gap-2 mb-2">
                <span class="qty-badge font-weight-black">x{{ item.quantity }}</span>
                <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                  <span class="text-body-2 font-weight-black text-high-emphasis uppercase truncate leading-tight">{{ item.name }}</span>
                  <span class="text-super-xs text-disabled uppercase">{{ item.laboratory || 'Genérico' }}</span>
                </div>
              </div>
              <VDivider class="my-2" />
              <div class="d-flex justify-space-between align-center">
                <div class="d-flex flex-column">
                  <span class="text-super-xs text-disabled uppercase font-weight-bold">Precio Unit.</span>
                  <span class="text-caption font-weight-bold text-high-emphasis">{{ formatCurrency(calculatePriceWithDiscount(item), 'USD') }}</span>
                </div>
                <div class="d-flex flex-column align-end">
                  <span class="text-super-xs text-disabled uppercase font-weight-bold">Subtotal</span>
                  <span class="text-subtitle-2 font-weight-black text-success">{{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity, 'USD') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Alerta de Límite de Oferta -->
        <VAlert
          v-if="props.pack.max_sale_date"
          variant="tonal"
          color="info"
          icon="tabler-calendar-event"
          density="compact"
          class="rounded-lg mt-3"
        >
          <div class="d-flex flex-column">
            <span class="text-super-xs font-weight-black uppercase letter-spacing-1">Vigencia de la Oferta</span>
            <span class="text-caption">Esta promoción está configurada hasta el <strong>{{ new Date(props.pack.max_sale_date).toLocaleDateString("es-ES", { dateStyle: 'long' }) }}</strong>.</span>
          </div>
        </VAlert>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VBtn
          color="primary"
          variant="flat"
          height="44"
          block
          class="font-weight-black rounded shadow-primary text-button uppercase"
          @click="handleClose"
        >
          <VIcon start icon="tabler-check" size="18" />
          Cerrar Detalle
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
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.detail-dialog-card {
  border-radius: 5px !important;
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.success { background-color: rgb(var(--v-theme-success)); }
.header-indicator.error { background-color: rgb(var(--v-theme-error)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.bg-var-theme-background {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
  border-radius: 5px !important;
}

.stat-box {
  background-color: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), 0.12) !important;
}

.qty-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-inline-size: 24px;
  block-size: 24px;
  padding: 0 6px;
  border-radius: 6px;
  font-size: 0.75rem;
  background-color: rgba(var(--v-theme-primary), 0.1);
  color: rgb(var(--v-theme-primary));
}

.internal-table :deep(thead th) {
  background-color: rgba(var(--v-border-color), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.65rem !important;
  font-weight: 950 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.mt-0-5 {
  margin-top: 2px !important;
}

.pa-3-5 {
  padding: 14px !important;
}

.gap-1-5 {
  gap: 6px !important;
}

.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}

.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }
.font-weight-950 { font-weight: 950 !important; }

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
