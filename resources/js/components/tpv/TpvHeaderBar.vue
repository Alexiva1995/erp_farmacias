<script setup>
import TpvRestaurantOrders from "@/components/tpv/TpvRestaurantOrders.vue";
import TpvSportsReservations from "@/components/tpv/TpvSportsReservations.vue";

defineProps({
  isCurrencyChanging: Boolean,
  isLoadingInitialOrder: Boolean,
  isRestaurant: Boolean,
  isSportsRental: Boolean,
  pedidosList: Array,
});

defineEmits(["selectPedido", "selectReservation", "handleNoShow"]);
</script>

<template>
  <div>
    <!-- Barra Superior de Acceso Rápido a Pedidos (Solo Restaurante) -->
    <div v-if="!isLoadingInitialOrder && isRestaurant" class="d-flex justify-space-between align-center mb-4 pa-2 bg-grey-lighten-4 rounded-lg border">
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-tools-kitchen-2" color="primary" />
        <span class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis">Modo Restaurante</span>
      </div>
    </div>

    <!-- Barra Superior de Acceso Rápido a Reservas (Solo Alquiler Deportivo) -->
    <div v-if="isSportsRental" class="d-flex justify-space-between align-center mb-4 pa-2 bg-grey-lighten-4 rounded-lg border">
      <div class="d-flex align-center gap-2">
        <VIcon icon="tabler-ball-football" color="primary" />
        <span class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis">Reservaciones del Día (Alquiler Deportivo)</span>
      </div>
    </div>

    <!-- Listado de Pedidos Activos / Mesas en la parte superior (Restaurante) -->
    <TpvRestaurantOrders
      v-if="!isLoadingInitialOrder && isRestaurant"
      :pedidos-list="pedidosList || []"
      @select-pedido="$emit('selectPedido', $event)"
    />

    <!-- Listado de Reservaciones en la parte superior (Alquiler Deportivo) -->
    <TpvSportsReservations
      v-if="isSportsRental"
      :pedidos-list="pedidosList || []"
      @select-reservation="$emit('selectReservation', $event)"
      @no-show="$emit('handleNoShow', $event)"
    />
  </div>
</template>
