<script setup>
import { formatCurrency } from '@/utils/currencyFormatter'

defineProps({
  pedidosList: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['select-pedido'])

const handleSelect = (pedido) => {
  emit('select-pedido', pedido)
}
</script>

<template>
  <div v-if="pedidosList.length > 0" class="mb-6">
    <VRow>
      <VCol v-for="pedido in pedidosList" :key="pedido.id" cols="12" sm="6" md="4" lg="3">
        <VCard
          variant="outlined"
          class="rounded-lg cursor-pointer bg-white"
          style="border: 1px solid #e0e0e0; transition: transform 0.2s, box-shadow 0.2s;"
          @click="handleSelect(pedido)"
          @mouseover="$event.currentTarget.style.transform = 'translateY(-2px)'"
          @mouseleave="$event.currentTarget.style.transform = 'none'"
        >
          <div style="height: 4px; background-color: #d81b60;"></div>
          <VCardText class="pa-3">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="font-weight-black text-subtitle-2" style="color: #d81b60;">Pedido #{{ pedido.id }}</span>
              <VChip color="warning" size="x-small" variant="flat" class="font-weight-black rounded">PENDIENTE</VChip>
            </div>
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              Cliente: {{ pedido.client ? (pedido.client.name + ' ' + (pedido.client.last_name || '')) : 'Cliente General' }}
            </div>
            <div class="text-caption text-disabled mb-2">
              Última actualización: {{ new Date(pedido.updated_at).toLocaleString() }}
            </div>
            <VDivider class="my-2 border-opacity-10" />
            <div class="d-flex justify-space-between align-center text-caption mt-1">
              <span class="font-weight-bold text-disabled">Monto Total:</span>
              <span class="font-weight-black text-subtitle-2" style="color: #d81b60;">{{ formatCurrency(pedido.total_amount || 0, pedido.currency || 'USD') }}</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
