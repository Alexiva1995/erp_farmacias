<script setup>
import { computed, onMounted } from "vue";
import { useBrandingStore } from "@/stores/useBrandingStore";
import OrderGeneralPharmacy from "@/components/tpv/orders/OrderGeneralPharmacy.vue";
import OrderGeneralRestaurant from "@/components/tpv/orders/OrderGeneralRestaurant.vue";

const brandingStore = useBrandingStore();

// Se decide automáticamente según la configuración del negocio (vertical o tpv_style)
const isRestaurantMode = computed(() => {
  const tpvStyle = String(brandingStore.settings?.tpv_style || "").toLowerCase();
  return Boolean(brandingStore.isRestaurant || tpvStyle === "restaurant");
});

onMounted(() => {
  brandingStore.fetchSettings();
});
</script>

<template>
  <div>
    <div class="d-flex flex-wrap justify-space-between align-center gap-2 mb-3">
      <div class="d-flex align-center gap-2">
        <VIcon
          :icon="isRestaurantMode ? 'tabler-tools-kitchen-2' : 'tabler-building-store'"
          color="primary"
          size="24"
        />
        <h1 class="text-h5 font-weight-bold mb-0">
          {{ isRestaurantMode ? "Historial de Comandas y Órdenes" : "Historial General de Órdenes" }}
        </h1>
        <VChip
          size="x-small"
          :color="isRestaurantMode ? 'info' : 'primary'"
          variant="tonal"
          class="font-weight-bold text-uppercase"
        >
          {{ isRestaurantMode ? "Modo Restaurante" : "Modo Farmacia" }}
        </VChip>
      </div>

      <VBtn
        to="/configuration/tpv"
        variant="text"
        color="secondary"
        size="small"
        prepend-icon="tabler-settings"
      >
        Configurar Estilo TPV
      </VBtn>
    </div>

    <OrderGeneralRestaurant v-if="isRestaurantMode" />
    <OrderGeneralPharmacy v-else />
  </div>
</template>
