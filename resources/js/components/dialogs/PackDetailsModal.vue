<script setup>
import { useDisplay } from "vuetify";
import { formatCurrency } from "@/utils/currencyFormatter";
import { computed, defineEmits, defineProps } from "vue";

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

  // Si tiene pack_config (JSON)
  if (props.pack.pack_config) {
    const config =
      typeof props.pack.pack_config === "string"
        ? JSON.parse(props.pack.pack_config)
        : props.pack.pack_config;

    return Object.entries(config).map(([productId, config]) => {
      const quantity =
        typeof config === "object" ? config.quantity || 1 : config;
      const unitPrice = typeof config === "object" ? config.sale_price || 0 : 0;

      return {
        id: parseInt(productId),
        name: `Producto ID: ${productId}`,
        quantity: quantity,
        active_ingredient: "",
        laboratory: "",
        photo_url: null,
        sale_price: 0,
        discount_percentage: 0,
        unit_price: unitPrice,
      };
    });
  }

  return [];
});

const totalProducts = computed(() => {
  return packProducts.value.reduce((sum, p) => sum + p.quantity, 0);
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
    :max-inline-size="mobile ? '100%' : '900px'"
    :fullscreen="mobile"
    persistent
    scrollable
    transition="dialog-bottom-transition"
  >
    <VCard v-if="props.pack" class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium con Degradado -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-6 d-flex align-center justify-space-between text-white">
          <div class="d-flex align-center gap-4">
            <VAvatar size="48" color="rgba(255,255,255,0.2)" class="backdrop-blur">
              <VIcon icon="tabler-package" size="28" color="white" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-h5 font-weight-black leading-tight uppercase">
                {{ (props.pack.name || '').toUpperCase() }}
              </span>
              <span class="text-super-xs font-weight-medium opacity-90 uppercase letter-spacing-1">
                Detalles Completos de Oferta
              </span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="handleClose"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <div class="pa-6">
          <!-- Información general del pack en tarjetas premium -->
          <VRow class="mb-6">
            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="primary" class="pa-4 rounded-lg border-0 shadow-sm overflow-hidden relative">
                <VIcon icon="tabler-currency-dollar" size="64" class="card-icon-bg opacity-10" />
                <div class="relative z-10">
                  <span class="text-super-xs font-weight-black uppercase letter-spacing-1 d-block mb-1">Precio Total</span>
                  <span class="text-h5 font-weight-950 text-primary">
                    {{ formatCurrency(parseFloat(props.pack.total_price || 0)) }}
                  </span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VCard variant="tonal" color="success" class="pa-4 rounded-lg border-0 shadow-sm overflow-hidden relative">
                <VIcon icon="tabler-box" size="64" class="card-icon-bg opacity-10" />
                <div class="relative z-10">
                  <span class="text-super-xs font-weight-black uppercase letter-spacing-1 d-block mb-1">Items</span>
                  <span class="text-h5 font-weight-950 text-success">
                    {{ totalProducts }} UND
                  </span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="6" md="3">
              <VCard
                variant="tonal"
                :color="props.pack.is_active ? 'success' : 'error'"
                class="pa-4 rounded-lg border-0 shadow-sm overflow-hidden relative"
              >
                <VIcon :icon="props.pack.is_active ? 'tabler-check' : 'tabler-x'" size="64" class="card-icon-bg opacity-10" />
                <div class="relative z-10">
                  <span class="text-super-xs font-weight-black uppercase letter-spacing-1 d-block mb-1">Estado</span>
                  <span class="text-h5 font-weight-950" :class="props.pack.is_active ? 'text-success' : 'text-error'">
                    {{ props.pack.is_active ? "ACTIVO" : "INACTIVO" }}
                  </span>
                </div>
              </VCard>
            </VCol>

            <VCol v-if="props.pack.max_quantity" cols="12" sm="6" md="3">
              <VCard variant="tonal" color="info" class="pa-4 rounded-lg border-0 shadow-sm overflow-hidden relative">
                <VIcon icon="tabler-shopping-cart" size="64" class="card-icon-bg opacity-10" />
                <div class="relative z-10">
                  <span class="text-super-xs font-weight-black uppercase letter-spacing-1 d-block mb-1">Ventas Máx</span>
                  <span class="text-h5 font-weight-950 text-info">
                    {{ props.pack.max_quantity }}
                  </span>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="border-dashed mb-6" />

          <!-- Tabla de productos -->
          <div class="mb-4">
            <div class="d-flex align-center justify-space-between mb-4 ms-1">
              <h3 class="text-subtitle-1 font-weight-950 text-high-emphasis">PRODUCTOS INCLUIDOS</h3>
              <VChip color="primary" variant="tonal" size="small" class="font-weight-black">
                {{ packProducts.length }} PRODUCTOS
              </VChip>
            </div>

            <VDataTable
              v-if="!mobile"
              :headers="[
                { title: 'CANT', key: 'quantity', align: 'center', width: '80px', sortable: false },
                { title: 'PRODUCTO', key: 'name', sortable: false },
                { title: 'UNITARIO', key: 'unit_price', align: 'end', sortable: false },
                { title: 'CON DESC.', key: 'price_with_discount', align: 'end', sortable: false },
                { title: 'SUBTOTAL', key: 'subtotal', align: 'end', sortable: false },
              ]"
              :items="packProducts"
              density="comfortable"
              class="rounded-lg border-0 bg-transparent internal-table"
              no-data-text="No hay productos en este pack"
              hide-default-footer
            >
              <template #item.quantity="{ item }">
                <VChip color="primary" variant="flat" size="small" class="font-weight-black">
                  {{ item.quantity }}
                </VChip>
              </template>

              <template #item.name="{ item }">
                <div class="d-flex flex-column">
                  <span class="text-body-2 font-weight-black text-high-emphasis text-uppercase">
                    {{ (item.name || '').toUpperCase() }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs mt-1">
                    <span class="text-disabled truncate" style="max-inline-size: 150px;">{{ item.active_ingredient || '—' }}</span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 120px;">
                      {{ item.laboratory || 'Genérico' }}
                    </span>
                  </div>
                </div>
              </template>

              <template #item.unit_price="{ item }">
                <span class="text-caption font-weight-medium">
                  {{ formatCurrency(item.unit_price) }}
                </span>
              </template>

              <template #item.price_with_discount="{ item }">
                <div class="d-flex flex-column align-end">
                  <span class="text-caption font-weight-black text-primary">
                    {{ formatCurrency(calculatePriceWithDiscount(item)) }}
                  </span>
                  <span v-if="item.discount_percentage > 0" class="text-super-xs text-disabled text-decoration-line-through">
                    {{ formatCurrency(item.unit_price) }}
                  </span>
                </div>
              </template>

              <template #item.subtotal="{ item }">
                <span class="text-body-2 font-weight-950 text-success">
                  {{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity) }}
                </span>
              </template>
            </VDataTable>

            <!-- Vista de Tarjetas en Móvil -->
            <div v-else class="mobile-details-list">
              <div v-if="packProducts.length === 0" class="text-center pa-8 rounded-lg border-dashed border-2 text-disabled">
                No hay productos en este pack
              </div>
              <div v-else class="d-flex flex-column gap-4">
                <VCard
                  v-for="(item, index) in packProducts"
                  :key="index"
                  variant="outlined"
                  class="product-detail-card rounded-lg border-opacity-25 shadow-none"
                >
                  <VCardText class="pa-4">
                    <div class="d-flex align-center gap-3 mb-3">
                      <VAvatar v-if="item.photo_url" size="40" :image="item.photo_url" variant="tonal" class="rounded-lg shadow-sm" />
                      <VAvatar v-else size="40" color="primary" variant="tonal" class="rounded-lg">
                        <VIcon icon="tabler-package" size="20" />
                      </VAvatar>
                      <div class="d-flex flex-column flex-grow-1">
                        <span class="text-body-2 font-weight-black text-high-emphasis leading-tight mb-1 text-uppercase">{{ (item.name || '').toUpperCase() }}</span>
                        <div class="d-flex align-center gap-2">
                          <VChip color="primary" variant="flat" size="x-small" class="font-weight-black px-2">
                            {{ item.quantity }} UND
                          </VChip>
                          <div class="d-flex align-center gap-1 text-super-xs">
                            <span class="text-disabled truncate" style="max-inline-size: 100px;">{{ item.active_ingredient || '—' }}</span>
                            <span class="text-disabled mx-1">|</span>
                            <span class="text-primary font-weight-black text-uppercase truncate" style="max-inline-size: 80px;">
                              {{ item.laboratory || 'Genérico' }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <VDivider class="border-dashed my-3" />

                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-super-xs font-weight-bold text-low-emphasis uppercase">Precio Base</span>
                      <span class="text-caption font-weight-medium text-medium-emphasis">{{ formatCurrency(item.unit_price) }}</span>
                    </div>

                    <div class="d-flex justify-space-between align-center mb-2">
                      <span class="text-super-xs font-weight-black text-primary uppercase">Precio Pack</span>
                      <div class="d-flex align-center gap-2">
                        <span v-if="item.discount_percentage > 0" class="text-super-xs text-disabled text-decoration-line-through">
                          {{ formatCurrency(item.unit_price) }}
                        </span>
                        <span class="text-caption font-weight-black text-primary">
                          {{ formatCurrency(calculatePriceWithDiscount(item)) }}
                        </span>
                      </div>
                    </div>

                    <div class="d-flex justify-space-between align-center mt-3 pt-3 border-top-dashed">
                      <span class="text-super-xs font-weight-black text-success uppercase">Subtotal</span>
                      <span class="text-subtitle-1 font-weight-950 text-success">
                        {{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity) }}
                      </span>
                    </div>
                  </VCardText>
                </VCard>
              </div>
            </div>
          </div>

          <!-- Pie de página con información adicional -->
          <div v-if="props.pack.max_sale_date" class="mt-8">
            <VAlert
              variant="tonal"
              color="warning"
              icon="tabler-calendar-event"
              class="rounded-lg"
            >
              <template #title>
                <span class="text-caption font-weight-black uppercase letter-spacing-1">Fecha Límite de Oferta</span>
              </template>
              Esta oferta expira el <strong>{{ new Date(props.pack.max_sale_date).toLocaleDateString("es-ES", { dateStyle: 'long' }) }}</strong>.
            </VAlert>
          </div>
        </div>
      </VCardText>

      <VDivider class="border-dashed" />

      <VCardActions class="pa-6 bg-white">
        <VBtn
          color="primary"
          variant="flat"
          class="rounded-lg font-weight-black px-12 shadow-primary-lg"
          block
          size="large"
          @click="handleClose"
        >
          <VIcon start>tabler-check</VIcon>
          ENTENDIDO
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #2f3349 100%);
}

.backdrop-blur {
  backdrop-filter: blur(8px);
}

.bg-light {
  background-color: #f8fafc !important;
}

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.border-top-dashed {
  border-block-start: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.card-icon-bg {
  position: absolute;
  inset-block-start: -10px;
  inset-inline-end: -10px;
}

.relative { position: relative !important; }
.z-10 { z-index: 10 !important; }

.internal-table :deep(th) {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
  color: rgb(var(--v-theme-primary)) !important;
  font-size: 0.65rem !important;
  font-weight: 950 !important;
  letter-spacing: 0.5px;
  text-transform: uppercase !important;
}

.internal-table :deep(td) {
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  padding-block: 12px !important;
}

.text-super-xs {
  font-size: 0.68rem !important;
  line-height: normal;
}

.font-weight-950 { font-weight: 950 !important; }
.leading-tight { line-height: 1.25 !important; }
.letter-spacing-1 { letter-spacing: 1.5px !important; }

.shadow-sm {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 3%) !important;
}

.shadow-primary-lg {
  box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 25%) !important;
}

@media (max-width: 600px) {
  .text-h5 {
    font-size: 1.25rem !important;
  }
}
</style>
