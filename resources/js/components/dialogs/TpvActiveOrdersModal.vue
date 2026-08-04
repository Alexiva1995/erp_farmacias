<script setup>
import { formatCurrency } from '@/utils/currencyFormatter'

defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  pedidosList: {
    type: Array,
    default: () => [],
  },
  loadingPedidos: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'select-pedido'])

const handleClose = () => {
  emit('update:modelValue', false)
}

const handleSelect = (pedido) => {
  emit('select-pedido', pedido)
}
</script>

<template>
  <VDialog :model-value="modelValue" @update:model-value="handleClose" max-width="800px">
    <VCard class="rounded-lg">
      <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
        <span class="font-weight-black text-uppercase">Pedidos Activos / Mesas Abiertas</span>
        <VBtn icon="tabler-x" variant="text" color="white" @click="handleClose" />
      </VCardTitle>
      <VCardText class="pa-4">
        <VProgressLinear v-if="loadingPedidos" indeterminate color="primary" class="mb-4" />

        <div v-if="pedidosList.length === 0 && !loadingPedidos" class="text-center py-8 text-disabled">
          <VIcon icon="tabler-receipt-off" size="48" class="mb-2" />
          <div class="font-weight-bold">No hay pedidos o mesas pendientes activas.</div>
        </div>

        <VRow v-else>
          <VCol v-for="pedido in pedidosList" :key="pedido.id" cols="12" md="6">
            <VCard variant="outlined" class="rounded-lg product-row cursor-pointer" @click="handleSelect(pedido)">
              <VCardText class="pa-3">
                <div class="d-flex justify-space-between align-center mb-2">
                  <span class="text-primary font-weight-black text-subtitle-2">Pedido #{{ pedido.id }}</span>
                  <VChip color="warning" size="x-small" variant="flat" class="font-weight-black">PENDIENTE</VChip>
                </div>
                <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
                  Cliente: {{ pedido.client ? (pedido.client.name + ' ' + (pedido.client.last_name || '')) : 'Cliente General' }}
                </div>
                <div class="text-caption text-disabled mb-2">
                  Última actualización: {{ new Date(pedido.updated_at).toLocaleString() }}
                </div>
                <VDivider class="my-2 border-opacity-10" />
                <div class="d-flex justify-space-between align-center text-caption">
                  <span class="font-weight-bold text-disabled">Monto Total:</span>
                  <span class="font-weight-black text-primary">{{ formatCurrency(pedido.total_amount || 0, pedido.currency || 'USD') }}</span>
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>
  </VDialog>
</template>
