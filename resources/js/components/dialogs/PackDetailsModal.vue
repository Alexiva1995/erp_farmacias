<script setup>
import { computed, defineEmits } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  packData: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(["update:isDialogVisible"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('es-ES', {
    style: 'currency',
    currency: 'USD'
  }).format(amount || 0);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('es-ES');
};

const calculateProductSubtotal = (productConfig) => {
  const unitPrice = productConfig.sale_price;
  const quantity = productConfig.quantity;
  return unitPrice * quantity;
};

const closeModal = () => {
  dialogVisible.value = false;
};
</script>

<template>
  <VDialog v-model="dialogVisible" max-width="900" persistent>
    <VCard class="d-flex flex-column">
      <VCardTitle class="d-flex align-center p-4 bg-primary">
        <VIcon color="white" class="me-2">tabler-package</VIcon>
        <span class="text-h5 font-weight-bold text-white">
          Detalles del Pack: {{ packData.name }}
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal" color="white">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      
      <VDivider />

      <VCardText class="flex-grow-1 pa-6">
        <VRow>
          <!-- Información General del Pack -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100">
              <VCardTitle class="text-h6 bg-light-primary">
                <VIcon color="primary" class="me-2" size="20">tabler-info-circle</VIcon>
                Información General
              </VCardTitle>
              <VCardText>
                <VList class="py-0">
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Nombre del Pack:</VListItemTitle>
                    <VListItemSubtitle class="text-body-1">{{ packData.name }}</VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Precio Total:</VListItemTitle>
                    <VListItemSubtitle class="text-h6 text-success">
                      {{ formatCurrency(packData.total_price) }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Cantidad de Productos:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip variant="outlined" color="primary" size="small">
                        {{ Object.keys(packData.pack_config || {}).length }} productos
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Cantidad Máxima de Ventas:</VListItemTitle>
                    <VListItemSubtitle>
                      {{ packData.max_quantity || 'Ilimitado' }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Fecha Límite de Venta:</VListItemTitle>
                    <VListItemSubtitle>
                      {{ formatDate(packData.max_sale_date) || 'Sin fecha límite' }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Estado:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip 
                        :color="packData.is_active ? 'success' : 'error'" 
                        variant="flat" 
                        size="small"
                      >
                        {{ packData.is_active ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                </VList>
              </VCardText>
            </VCard>
          </VCol>

          <!-- Productos Incluidos -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100">
              <VCardTitle class="text-h6 bg-light-info">
                <VIcon color="info" class="me-2" size="20">tabler-shopping-cart</VIcon>
                Productos Incluidos
              </VCardTitle>
              <VCardText>
                <VList class="py-0">
                  <template v-if="packData.pack_config && Object.keys(packData.pack_config).length > 0">
                    <VListItem
                      v-for="(productConfig, productId, index) in packData.pack_config"
                      :key="productId"
                      class="px-0 mb-3"
                    >
                      <VCard variant="tonal" class="w-100">
                        <VCardText>
                          <!-- Encabezado del producto -->
                          <div class="d-flex justify-space-between align-start mb-2">
                            <div>
                              <div class="font-weight-bold text-body-1">
                                Producto {{ index + 1 }}
                              </div>
                              <div class="text-caption text-medium-emphasis">
                                ID: {{ productId }}
                              </div>
                            </div>
                            <VChip variant="outlined" color="primary" size="small">
                              {{ productConfig.quantity }} und.
                            </VChip>
                          </div>

                          <!-- Detalles del producto -->
                          <VRow class="mt-2">
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Descuento Aplicado:</div>
                              <div class="text-body-2 text-success">
                                {{ productConfig.discount_percentage }}%
                              </div>
                            </VCol>
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Precio Unitario:</div>
                              <div class="text-body-2">
                                {{ formatCurrency(productConfig.sale_price) }}
                              </div>
                            </VCol>
                          </VRow>

                          <!-- Subtotal -->
                          <VDivider class="my-2" />
                          <div class="d-flex justify-space-between align-center">
                            <span class="font-weight-bold">Subtotal:</span>
                            <span class="text-h6 text-success">
                              {{ formatCurrency(calculateProductSubtotal(productConfig)) }}
                            </span>
                          </div>
                        </VCardText>
                      </VCard>
                    </VListItem>
                  </template>
                  
                  <VListItem v-else class="px-0">
                    <VListItemTitle class="text-medium-emphasis text-center">
                      <VIcon size="48" color="grey" class="mb-2">tabler-package-off</VIcon>
                      <div>No hay productos en este pack</div>
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <!-- Resumen Final -->
        <VRow class="mt-4">
          <VCol cols="12">
            <VCard color="primary" variant="flat">
              <VCardText class="text-center py-4">
                <div class="d-flex justify-center align-center">
                  <VIcon color="white" size="32" class="me-3">tabler-cash</VIcon>
                  <div>
                    <div class="text-h5 font-weight-bold text-white">
                      Precio Final del Pack: {{ formatCurrency(packData.total_price) }}
                    </div>
                    <div class="text-caption text-white">
                      Incluye todos los descuentos aplicados
                    </div>
                  </div>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <!-- Botón de Cerrar -->
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-x"
          @click="closeModal"
        >
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-light-primary {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.bg-light-info {
  background-color: rgba(var(--v-theme-info), 0.08);
}

.v-list-item {
  min-height: 48px;
}
</style>