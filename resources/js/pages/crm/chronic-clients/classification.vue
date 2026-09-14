<script setup>
import ProductConsumptionConfigTab from '@/pages/crm/chronic-clients/components/ProductConsumptionConfigTab.vue'
</script>

<template>
  <div class="product-consumption-classification-page">
    <!-- Encabezado de Página -->
    <VCard variant="flat" class="pa-4 border rounded-lg mb-4">
      <div class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="48" class="me-3">
            <VIcon icon="tabler-pill" size="28" />
          </VAvatar>
          <div>
            <h1 class="text-h5 font-weight-black text-high-emphasis leading-tight mb-0">
              Clasificación de Productos y Frecuencia
            </h1>
            <p class="text-caption text-medium-emphasis mb-0">
              Configura el tipo de consumo y la duración del tratamiento (días) para generar alertas de fidelización y recompra.
            </p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Tabla y Contenido de Clasificación -->
    <ProductConsumptionConfigTab />
  </div>
</template>

<style scoped>
.product-consumption-classification-page {
  animation: fadeIn 0.2s ease-in-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
