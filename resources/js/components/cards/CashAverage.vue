<script setup></script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>Resumen de Caja</VCardTitle>
    </VCardItem>
    <VCardText>
      <div class="d-flex align-center justify-space-between py-2">
        <div class="d-flex align-center gap-x-2">
          <VIcon
            :icon="'tabler-shopping-cart'"  :style="{ color:'#D9D9D9'}"
            size="24"
          />
          <span class="text-body-1 text-high-emphasis">Promedio de Ventas</span>
        </div>
        <div class="d-flex align-center gap-x-4">
            <div><i class="tabler-chevron-up v-icon notranslate v-theme--light v-icon--size-default text-success me-1" aria-hidden="true"></i><span class="text-success font-weight-medium">15.8% </span></div>
            <h3 class="text-h3">0.00</h3>
        </div>
      </div>
    </VCardText>
  </VCard>
</template>
