<script setup>
import { formatCurrency } from "@/utils/currencyFormatter";
import { defineEmits, defineProps, computed } from "vue";

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
      laboratory: p.product_info?.laboratory?.name || "",
      photo_url: p.product_info?.photo_url || null,
      sale_price: p.product_info?.sale_price || 0,
      discount_percentage: p.discount_percentage || 0,
      unit_price: p.unit_price || p.product_info?.sale_price || 0,
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
    const config = typeof props.pack.pack_config === 'string' 
      ? JSON.parse(props.pack.pack_config) 
      : props.pack.pack_config;
    
    return Object.entries(config).map(([productId, config]) => {
      const quantity = typeof config === 'object' ? (config.quantity || 1) : config;
      const unitPrice = typeof config === 'object' ? (config.sale_price || 0) : 0;
      
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
    max-width="900px"
    persistent
    scrollable
  >
    <VCard v-if="props.pack">
      <!-- Header moderno -->
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-package" size="28" color="white" />
          <div class="d-flex flex-column">
            <span class="text-h5 text-white font-weight-bold">
              {{ props.pack.name }}
            </span>
            <span class="text-body-2 text-white opacity-90">
              Detalles del Pack
            </span>
          </div>
        </div>
        <VBtn 
          icon 
          variant="text" 
          color="white" 
          size="small" 
          @click="handleClose"
        >
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Información general del pack -->
        <VRow class="mb-6">
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="primary" class="pa-4">
              <div class="d-flex align-center gap-3">
                <VIcon icon="tabler-currency-dollar" size="32" color="primary" />
                <div>
                  <span class="text-caption text-disabled d-block">Precio Total</span>
                  <span class="text-h6 font-weight-bold text-primary">
                    {{ formatCurrency(parseFloat(props.pack.total_price || 0)) }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" color="success" class="pa-4">
              <div class="d-flex align-center gap-3">
                <VIcon icon="tabler-box" size="32" color="success" />
                <div>
                  <span class="text-caption text-disabled d-block">Total Productos</span>
                  <span class="text-h6 font-weight-bold text-success">
                    {{ totalProducts }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
          <VCol cols="12" md="3">
            <VCard variant="tonal" :color="props.pack.is_active ? 'success' : 'error'" class="pa-4">
              <div class="d-flex align-center gap-3">
                <VIcon 
                  :icon="props.pack.is_active ? 'tabler-check' : 'tabler-x'" 
                  size="32" 
                  :color="props.pack.is_active ? 'success' : 'error'" 
                />
                <div>
                  <span class="text-caption text-disabled d-block">Estado</span>
                  <span 
                    class="text-h6 font-weight-bold"
                    :class="props.pack.is_active ? 'text-success' : 'text-error'"
                  >
                    {{ props.pack.is_active ? 'Activo' : 'Inactivo' }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
          <VCol
            v-if="props.pack.max_quantity"
            cols="12"
            md="3"
          >
            <VCard variant="tonal" color="info" class="pa-4">
              <div class="d-flex align-center gap-3">
                <VIcon icon="tabler-shopping-cart" size="32" color="info" />
                <div>
                  <span class="text-caption text-disabled d-block">Ventas Máxima</span>
                  <span class="text-h6 font-weight-bold text-info">
                    {{ props.pack.max_quantity }} unidades
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <VDivider class="my-4" />

        <!-- Tabla de productos -->
        <div class="mb-4">
          <div class="d-flex align-center justify-space-between mb-4">
            <h3 class="text-h6 font-weight-medium">Productos del Pack</h3>
            <VChip variant="outlined" color="primary" size="small">
              {{ packProducts.length }} producto(s)
            </VChip>
          </div>

          <VDataTable
            :headers="[
              { title: 'Cantidad', key: 'quantity', align: 'center', width: '100px' },
              { title: 'Producto', key: 'name', sortable: false },
              { title: 'Laboratorio', key: 'laboratory', sortable: false },
              { title: 'Precio Unit.', key: 'unit_price', align: 'end', sortable: false },
              { title: 'Precio con Desc.', key: 'price_with_discount', align: 'end', sortable: false },
              { title: 'Subtotal', key: 'subtotal', align: 'end', sortable: false },
            ]"
            :items="packProducts"
            density="comfortable"
            class="rounded-lg"
            no-data-text="No hay productos en este pack"
          >
            <template #item.quantity="{ item }">
              <VChip color="primary" variant="tonal" size="small">
                {{ item.quantity }}
              </VChip>
            </template>

            <template #item.name="{ item }">
              <div class="d-flex flex-column">
                <span class="text-body-1 font-weight-medium">
                  {{ item.name }}
                </span>
                <span 
                  v-if="item.active_ingredient" 
                  class="text-caption text-disabled"
                >
                  {{ item.active_ingredient }}
                </span>
                <span v-else class="text-caption text-disabled">
                  ID: {{ item.id }}
                </span>
              </div>
            </template>

            <template #item.laboratory="{ item }">
              <span v-if="item.laboratory" class="text-body-2">
                {{ item.laboratory }}
              </span>
              <span v-else class="text-disabled text-caption">—</span>
            </template>

            <template #item.unit_price="{ item }">
              <span class="text-body-2">
                {{ formatCurrency(item.unit_price) }}
              </span>
            </template>

            <template #item.price_with_discount="{ item }">
              <div class="d-flex flex-column align-end">
                <span class="text-body-2 font-weight-medium">
                  {{ formatCurrency(calculatePriceWithDiscount(item)) }}
                </span>
                <span 
                  v-if="item.discount_percentage > 0"
                  class="text-caption text-disabled text-decoration-line-through"
                >
                  {{ formatCurrency(item.unit_price) }}
                </span>
              </div>
            </template>

            <template #item.subtotal="{ item }">
              <span class="text-body-1 font-weight-medium text-success">
                {{ formatCurrency(calculatePriceWithDiscount(item) * item.quantity) }}
              </span>
            </template>
          </VDataTable>
        </div>

        <!-- Información adicional -->
        <VRow v-if="props.pack.max_sale_date" class="mt-4">
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="pa-3">
              <div class="d-flex align-center gap-2">
                <VIcon icon="tabler-calendar" size="20" color="primary" />
                <div>
                  <span class="text-caption text-disabled d-block">Fecha Máxima de Venta</span>
                  <span class="text-body-1 font-weight-medium">
                    {{ new Date(props.pack.max_sale_date).toLocaleDateString('es-ES') }}
                  </span>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VBtn 
          color="primary" 
          variant="flat" 
          prepend-icon="tabler-check"
          block
          class="w-100"
          @click="handleClose"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
