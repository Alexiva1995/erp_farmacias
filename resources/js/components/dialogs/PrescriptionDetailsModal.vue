<script setup>
import { computed, defineEmits } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  prescriptionData: {
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

const calculateProductSubtotal = (productData) => {
  const unitPrice = productData.sale_price;
  const quantity = productData.quantity;
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
        <span class="text-h5 font-weight-bold text-white">
          Detalles de la Oferta
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal" color="white">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      
      <VDivider />

      <VCardText class="flex-grow-1 pa-6">
        <VRow>
          <!-- Información General de la Oferta -->
          <VCol cols="12" md="6">
            <VCard variant="outlined" class="h-100">
              <VCardTitle class="text-h6 bg-light-primary">
                <VIcon color="primary" class="me-2" size="20">tabler-info-circle</VIcon>
                Información General del Recipe
              </VCardTitle>
              <VCardText>
                <VList class="py-0">
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">ID:</VListItemTitle>
                    <VListItemSubtitle class="text-body-1">{{ prescriptionData.id }}</VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Porcentaje de Descuento:</VListItemTitle>
                    <VListItemSubtitle class="text-h6 text-success">
                      {{ prescriptionData.discount_percentage }}%
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Costo Total:</VListItemTitle>
                    <VListItemSubtitle class="text-h6 text-primary">
                      {{ formatCurrency(prescriptionData.total_cost) }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Cantidad de Productos:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip variant="outlined" color="primary" size="small">
                        {{ prescriptionData.products_count }} productos
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Fecha de Inicio:</VListItemTitle>
                    <VListItemSubtitle>
                      {{ formatDate(prescriptionData.start_date) || 'Sin fecha definida' }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Fecha de Fin:</VListItemTitle>
                    <VListItemSubtitle>
                      {{ formatDate(prescriptionData.end_date) || 'Sin fecha definida' }}
                    </VListItemSubtitle>
                  </VListItem>
                  
                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Estado:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip 
                        :color="prescriptionData.is_active ? 'success' : 'error'" 
                        variant="flat" 
                        size="small"
                      >
                        {{ prescriptionData.is_active ? 'Activo' : 'Inactivo' }}
                      </VChip>
                    </VListItemSubtitle>
                  </VListItem>

                  <VDivider class="my-2" />
                  
                  <VListItem class="px-0">
                    <VListItemTitle class="font-weight-bold text-primary">Activa Actualmente:</VListItemTitle>
                    <VListItemSubtitle>
                      <VChip 
                        :color="prescriptionData.is_currently_active ? 'success' : 'warning'" 
                        variant="flat" 
                        size="small"
                      >
                        {{ prescriptionData.is_currently_active ? 'Sí' : 'No' }}
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
                  <template v-if="prescriptionData.products_with_details && prescriptionData.products_with_details.length > 0">
                    <VListItem
                      v-for="(productData, index) in prescriptionData.products_with_details"
                      :key="index"
                      class="px-0 mb-3"
                    >
                      <VCard variant="tonal" class="w-100">
                        <VCardText>
                          <!-- Encabezado del producto -->
                          <div class="d-flex justify-space-between align-start mb-2">
                            <div>
                              <div class="font-weight-bold text-body-1">
                                {{ productData.product_name }}
                              </div>
                              <div class="text-caption text-medium-emphasis">
                                {{ productData.active_ingredient }}
                              </div>
                              <div class="text-caption text-medium-emphasis">
                                Laboratorio: {{ productData.laboratory || 'N/A' }}
                              </div>
                            </div>
                            <VChip variant="outlined" color="primary" size="small">
                              {{ productData.quantity }} und.
                            </VChip>
                          </div>

                          <!-- Detalles del producto -->
                          <VRow class="mt-2">
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Precio Unitario:</div>
                              <div class="text-body-2">
                                {{ formatCurrency(productData.sale_price) }}
                              </div>
                            </VCol>
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Descuento:</div>
                              <div class="text-body-2 text-success">
                                {{ prescriptionData.discount_percentage }}%
                              </div>
                            </VCol>
                          </VRow>

                          <VRow class="mt-2">
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Precio Final:</div>
                              <div class="text-body-2 text-primary">
                                {{ formatCurrency(productData.final_price / productData.quantity) }} c/u
                              </div>
                            </VCol>
                            <VCol cols="6">
                              <div class="text-caption font-weight-bold">Ahorro:</div>
                              <div class="text-body-2 text-success">
                                {{ formatCurrency(productData.discount_amount) }}
                              </div>
                            </VCol>
                          </VRow>

                          <!-- Subtotal -->
                          <VDivider class="my-2" />
                          <div class="d-flex justify-space-between align-center">
                            <span class="font-weight-bold">Subtotal:</span>
                            <span class="text-h6 text-success">
                              {{ formatCurrency(productData.final_price) }}
                            </span>
                          </div>
                        </VCardText>
                      </VCard>
                    </VListItem>
                  </template>
                  
                  <VListItem v-else class="px-0">
                    <VListItemTitle class="text-medium-emphasis text-center">
                      <VIcon size="48" color="grey" class="mb-2">tabler-package-off</VIcon>
                      <div>No hay productos en esta oferta</div>
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
                      Costo Total: {{ formatCurrency(prescriptionData.total_cost) }}
                    </div>
                    <div class="text-caption text-white">
                      Descuento aplicado: {{ prescriptionData.discount_percentage }}%
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