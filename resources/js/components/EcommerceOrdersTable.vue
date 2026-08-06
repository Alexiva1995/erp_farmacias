<script setup>
import { ref } from 'vue'

const props = defineProps({
  adminOrders: {
    type: Array,
    default: () => [],
  },
  actionLoadingId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['approve', 'cancel'])

const onApprove = (orderId) => {
  emit('approve', orderId)
}

const onCancel = (orderId) => {
  emit('cancel', orderId)
}
</script>

<template>
  <div class="border pa-6 rounded-lg bg-white d-flex flex-column gap-4 shadow-sm hover-card transition-all">
    <div>
      <h3 class="text-subtitle-2 font-weight-bold text-uppercase tracking-wider d-flex align-center gap-2 mb-2">
        <VIcon icon="tabler-shopping-cart" size="18" class="text-primary" />
        Aprobación de Pedidos E-commerce
      </h3>
      <p class="text-muted text-caption mb-6">
        Listado de compras web en estado pendiente. Aquí puedes revisar los métodos de pago (Zelle, Pago Móvil a tasa Binance) y aprobar o rechazar para procesar el despacho.
      </p>

      <!-- Tabla de pedidos -->
      <div v-if="adminOrders.length" class="border rounded-lg overflow-hidden shadow-soft">
        <VTable class="text-left" style="width: 100%;">
          <thead>
            <tr style="background-color: #FAFAFA; border-bottom: 2px solid #E8E8E8;">
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Pedido</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Cliente</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Contacto</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Método</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase text-right">Total</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase">Estado</th>
              <th class="py-3 px-4 font-weight-bold text-xxs tracking-widest text-uppercase text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="order in adminOrders" :key="order.id">
              <tr style="border-bottom: 1px solid #E8E8E8;">
                <td class="py-4 px-4 text-xs font-weight-bold">#{{ order.id }}</td>
                <td class="py-4 px-4 text-xs">
                  <div class="font-weight-bold text-dark">{{ order.customer_name }}</div>
                  <div class="text-xxs text-muted">{{ order.customer_email || 'Sin email' }}</div>
                </td>
                <td class="py-4 px-4 text-xs">
                  <div>{{ order.customer_phone || '-' }}</div>
                  <div class="text-xxs text-muted text-truncate" style="max-width: 180px;">
                    {{ order.shipping_address }}
                  </div>
                </td>
                <td class="py-4 px-4 text-xs font-weight-bold text-uppercase">
                  <span>{{ order.payment_method === 'pago_movil' ? 'Pago Móvil' : order.payment_method }}</span>
                </td>
                <td class="py-4 px-4 text-xs text-right font-weight-bold text-dark">${{ Number(order.total_amount).toFixed(2) }}</td>
                <td class="py-4 px-4 text-xs">
                  <VChip
                    size="x-small"
                    variant="tonal"
                    class="rounded-md text-uppercase"
                    :color="order.status === 'Paid' ? 'success' : (order.status === 'Cancelled' ? 'error' : 'warning')"
                  >
                    {{ order.status === 'Pending' ? 'Pendiente' : (order.status === 'Paid' ? 'Aprobado' : 'Cancelado') }}
                  </VChip>
                </td>
                <td class="py-4 px-4 text-center">
                  <div v-if="order.status === 'Pending'" class="d-flex align-center justify-center gap-2">
                    <VBtn
                      size="x-small"
                      color="success"
                      variant="flat"
                      class="rounded-md px-3 font-weight-bold"
                      :loading="actionLoadingId === order.id"
                      @click="onApprove(order.id)"
                    >
                      Aprobar
                    </VBtn>
                    <VBtn
                      size="x-small"
                      color="error"
                      variant="outlined"
                      class="rounded-md px-3 font-weight-bold"
                      :loading="actionLoadingId === order.id"
                      @click="onCancel(order.id)"
                    >
                      Rechazar
                    </VBtn>
                  </div>
                  <span v-else class="text-xxs text-muted font-weight-bold text-uppercase">Procesado</span>
                </td>
              </tr>
              <!-- Detalles de productos comprados en el pedido -->
              <tr style="background-color: #FCFCFC; border-bottom: 1px solid #E8E8E8;">
                <td colspan="7" class="py-3 px-6 text-xxs text-muted">
                  <div class="d-flex align-center gap-2">
                    <span class="font-weight-bold text-uppercase tracking-wider text-primary">Detalles del Pedido:</span>
                    <span v-for="(item, index) in order.items" :key="item.id">
                      {{ item.product_name }} <span v-if="item.variant_value" class="text-primary font-weight-medium">({{ item.variant_value }})</span> x{{ item.quantity }} (${{ Number(item.price).toFixed(2) }})<span v-if="index < order.items.length - 1" class="mx-1">|</span>
                    </span>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </VTable>
      </div>
      <div v-else class="text-center py-8 border border-dashed rounded-lg bg-light text-muted">
        <VIcon icon="tabler-mood-empty" size="24" class="mb-2 d-block mx-auto" />
        <span class="text-caption text-uppercase tracking-wider">No hay pedidos registrados en la tienda actualmente</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.bg-light {
  background-color: #f8f9fa;
}
.shadow-soft {
  box-shadow: 0 4px 18px 0 rgba(0,0,0,0.04) !important;
}
.hover-card {
  transition: all 0.3s ease;
}
.hover-card:hover {
  box-shadow: 0 8px 24px 0 rgba(0,0,0,0.06) !important;
}
</style>
