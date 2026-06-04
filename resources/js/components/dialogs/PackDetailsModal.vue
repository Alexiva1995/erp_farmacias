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

  return [];
});

const totalProductsCount = computed(() => {
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
      <!-- Header Premium Standard con ICONO -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon icon="tabler-discount" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ (props.pack.name || 'Detalle del Pack').toUpperCase() }}
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
              Información Completa de la Oferta
            </span>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="handleClose"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-0 bg-light">
        <div class="pa-6">
          <!-- Información general del pack -->
          <VRow class="mb-6">
            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator primary"></div>
                  <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1">Inversión Final</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-h4 font-weight-950 text-primary leading-tight">
                    {{ formatCurrency(parseFloat(props.pack.total_price || 0)) }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Precio Promocional</span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator secondary"></div>
                  <span class="text-super-xs font-weight-black text-secondary uppercase letter-spacing-1">Items Incluidos</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-h4 font-weight-950 text-secondary leading-tight">
                    {{ totalProductsCount }} <span class="text-subtitle-2 font-weight-black">UND</span>
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Suma de Cantidades</span>
                </div>
              </VCard>
            </VCol>

            <VCol cols="12" sm="4">
              <VCard variant="flat" class="pa-4 rounded-lg border bg-white elevation-1 relative overflow-hidden h-100">
                <div class="d-flex align-center gap-2 mb-3">
                  <div class="header-indicator success"></div>
                  <span class="text-super-xs font-weight-black text-success uppercase letter-spacing-1">Estado</span>
                </div>
                <div class="d-flex flex-column pt-1">
                  <span class="text-h4 font-weight-950 leading-tight" :class="props.pack.is_active ? 'text-success' : 'text-error'">
                    {{ props.pack.is_active ? "ACTIVO" : "INACTIVO" }}
                  </span>
                  <span class="text-super-xs text-disabled font-weight-bold uppercase mt-1">Disponibilidad TPV</span>
                </div>
              </VCard>
            </VCol>
          </VRow>

          <VDivider class="border-dashed mb-6" />

          <!-- Tabla de productos -->
          <div class="mb-4">
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-primary uppercase letter-spacing-1">Detalle de Productos</span>
            </div>

            <VDataTable
              v-if="!mobile"
              :headers="[
                { title: 'CANT', key: 'quantity', align: 'center', width: '80px', sortable: false },
                { title: 'PRODUCTO', key: 'name', sortable: false },
                { title: 'UNITARIO', key: 'unit_price', align: 'end', sortable: false },
                { title: 'DESC.', key: 'discount_percentage', align: 'center', sortable: false },
                { title: 'CON DESC.', key: 'price_with_discount', align: 'end', sortable: false },
                { title: 'SUBTOTAL', key: 'subtotal', align: 'end', sortable: false },
              ]"
              :items="packProducts"
              density="comfortable"
              class="internal-table rounded-lg border shadow-sm bg-white"
              no-data-text="No hay productos registrados"
              hide-default-footer
            >
              <template #item.quantity="{ item }">
                <VChip color="primary" variant="flat" size="x-small" class="font-weight-black">
                  {{ item.quantity }}
                </VChip>
              </template>

              <template #item.name="{ item }">
                <div class="d-flex flex-column py-2">
                  <span class="text-body-2 font-weight-black text-high-emphasis text-uppercase leading-tight">
                    {{ (item.name || '').toUpperCase() }}
                  </span>
                  <div class="d-flex align-center gap-1 text-super-xs mt-1">
                    <span class="text-disabled truncate">{{ item.active_ingredient || 'Principio No Registrado' }}</span>
                    <span class="text-disabled mx-1">|</span>
                    <span class="text-primary font-weight-black uppercase">{{ item.laboratory || 'Genérico' }}</span>
                  </div>
                </div>
              </template>

              <template #item.unit_price="{ item }">
                <span class="text-caption font-weight-medium">
                  {{ formatCurrency(item.unit_price) }}
                </span>
              </template>

              <template #item.discount_percentage="{ item }">
                <VChip v-if="item.discount_percentage > 0" color="error" variant="tonal" size="x-small" class="font-weight-black">
                  -{{ item.discount_percentage }}%
                </VChip>
                <span v-else class="text-disabled">-</span>
              </template>

              <template #item.price_with_discount="{ item }">
                <span class="text-caption font-weight-black text-primary">
                  {{ formatCurrency(calculatePriceWithDiscount(item)) }}
                </span>
              </template>

              <template #item.subtotal="{ item }">
                <span class="text-body-2 font-weight-950 text-success">
                  {{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity) }}
                </span>
              </template>
            </VDataTable>

            <!-- Móvil: Tarjetas Compactas -->
            <div v-else class="d-flex flex-column gap-3">
              <VCard v-for="(item, idx) in packProducts" :key="idx" variant="flat" class="border pa-4 rounded-lg bg-white elevation-1">
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar size="40" color="primary" variant="tonal" class="rounded-lg">
                    <VIcon icon="tabler-package" size="20" />
                  </VAvatar>
                  <div class="d-flex flex-column flex-grow-1 overflow-hidden">
                    <span class="text-body-2 font-weight-black text-high-emphasis uppercase truncate leading-tight">{{ item.name }}</span>
                    <span class="text-super-xs text-disabled uppercase">{{ item.laboratory || 'Genérico' }}</span>
                  </div>
                  <VChip color="primary" variant="flat" size="small" class="font-weight-black">x{{ item.quantity }}</VChip>
                </div>
                <VDivider class="border-dashed mb-3" />
                <div class="d-flex justify-space-between align-center">
                   <div class="d-flex flex-column mr-auto">
                    <span class="text-super-xs text-disabled uppercase font-weight-black">Precio Item</span>
                    <span class="text-caption font-weight-black text-primary">{{ formatCurrency(calculatePriceWithDiscount(item)) }}</span>
                  </div>
                  <div class="d-flex flex-column align-end">
                    <span class="text-super-xs text-disabled uppercase font-weight-black">Subtotal</span>
                    <span class="text-subtitle-2 font-weight-950 text-success">{{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity) }}</span>
                  </div>
                </div>
              </VCard>
            </div>
          </div>

          <!-- Límite de Oferta -->
          <VAlert
            v-if="props.pack.max_sale_date"
            variant="tonal"
            color="warning"
            icon="tabler-calendar-event"
            class="rounded-lg mt-6"
          >
            <div class="d-flex flex-column">
              <span class="text-caption font-weight-black uppercase letter-spacing-1">Fecha Límite de Oferta</span>
              <span class="text-body-2">Esta promoción es válida hasta el <strong>{{ new Date(props.pack.max_sale_date).toLocaleDateString("es-ES", { dateStyle: 'long' }) }}</strong>.</span>
            </div>
          </VAlert>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions class="pa-6 bg-white">
        <VBtn color="primary" variant="flat" class="rounded-lg font-weight-black px-12 shadow-primary text-button uppercase" block size="large" @click="handleClose">
          <VIcon start>tabler-check</VIcon>
          ENTENDIDO
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 16px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }
.header-indicator.secondary { background-color: rgb(var(--v-theme-secondary)); }
.header-indicator.success { background-color: rgb(var(--v-theme-success)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.internal-table :deep(thead th) {
  background-color: #f8fafc !important;
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

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight { line-height: 1.25 !important; }
.leading-none { line-height: 1 !important; }
.font-weight-950 { font-weight: 950 !important; }

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
