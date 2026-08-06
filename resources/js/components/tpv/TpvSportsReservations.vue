<script setup>
import { formatCurrency } from '@/utils/currencyFormatter'

defineProps({
  pedidosList: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['select-reservation', 'no-show'])

const handleSelect = (reserva) => {
  emit('select-reservation', reserva)
}

const handleNoShow = (reserva) => {
  emit('no-show', reserva)
}
</script>

<template>
  <div v-if="pedidosList.length > 0" class="mb-6">
    <VRow>
      <VCol v-for="reserva in pedidosList" :key="reserva.id" cols="12" sm="6" md="4" lg="3">
        <VCard
          variant="outlined"
          class="rounded-lg bg-white"
          style="border: 1px solid #e0e0e0; transition: transform 0.2s, box-shadow 0.2s;"
          @mouseover="$event.currentTarget.style.transform = 'translateY(-2px)'"
          @mouseleave="$event.currentTarget.style.transform = 'none'"
        >
          <div style="height: 4px;" :style="reserva.is_fixed ? 'background-color: #2196f3;' : 'background-color: #4caf50;'"></div>
          <VCardText class="pa-3">
            <div class="d-flex justify-space-between align-center mb-2">
              <span class="font-weight-black text-subtitle-2" :style="reserva.is_fixed ? 'color: #2196f3;' : 'color: #4caf50;'">
                {{ reserva.court?.name || 'Cancha' }}
              </span>
              <VChip :color="reserva.is_fixed ? 'info' : (reserva.status === 'verified' ? 'success' : 'warning')" size="x-small" variant="flat" class="font-weight-black rounded">
                {{ reserva.is_fixed ? 'FIJA' : (reserva.status === 'verified' ? 'VERIFICADA' : 'PENDIENTE') }}
              </VChip>
            </div>
            <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
              Cliente: {{ reserva.client_name }}
            </div>
            <div class="text-caption font-weight-bold text-primary mb-1">
              Horario: {{ reserva.start_time.substring(0, 5) }} - {{ reserva.end_time.substring(0, 5) }}
            </div>
            <div class="text-caption text-disabled mb-1">
              Teléfono: {{ reserva.client_whatsapp }}
            </div>
            <div class="d-flex justify-space-between align-center text-caption mb-2">
              <span class="font-weight-bold text-disabled">Tarifa/Hora:</span>
              <span class="font-weight-black text-subtitle-2" style="color: #4caf50;">
                {{ formatCurrency(parseFloat(reserva.court?.price || 0), 'COP') }}
              </span>
            </div>
            <VDivider class="my-2 border-opacity-10" />
            <div class="d-flex gap-2 mt-2">
              <VBtn
                color="success"
                size="small"
                class="flex-grow-1 font-weight-black text-uppercase"
                @click.stop="handleSelect(reserva)"
              >
                Pagar
              </VBtn>
              <VBtn
                color="error"
                variant="outlined"
                size="small"
                class="flex-grow-1 font-weight-black text-uppercase"
                @click.stop="handleNoShow(reserva)"
              >
                Faltó
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
