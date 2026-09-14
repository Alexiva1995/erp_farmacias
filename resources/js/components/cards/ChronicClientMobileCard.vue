<script setup>
import { computed } from 'vue'

const props = defineProps({
  client: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['mark-contacted', 'open-whatsapp', 'remove-phone'])

const toTitleCase = (str) => {
  if (!str) return ''
  return str
    .toLowerCase()
    .split(' ')
    .filter(Boolean)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

const getStatusColor = (client) => {
  if (client.is_urgent) return 'warning'
  if (client.is_active) return 'success'
  return 'error'
}

const getStatusLabel = (client) => {
  if (client.is_urgent) return 'Alerta (≤ 5 d)'
  if (client.is_active) return 'Activo'
  return 'Agotado'
}

const getStatusIcon = (client) => {
  if (client.is_urgent) return 'tabler-alert-triangle'
  if (client.is_active) return 'tabler-circle-check'
  return 'tabler-clock-off'
}
</script>

<template>
  <VCard variant="flat" border class="pa-4 rounded-xl chronic-mobile-card">
    <div class="d-flex align-center justify-space-between mb-2">
      <div class="font-weight-semibold text-high-emphasis">
        {{ toTitleCase(client.client_name) }}
      </div>
      <VChip
        size="x-small"
        :color="getStatusColor(client)"
        variant="tonal"
        class="font-weight-medium"
      >
        <VIcon :icon="getStatusIcon(client)" start size="12" />
        {{ getStatusLabel(client) }}
      </VChip>
    </div>

    <div class="text-caption text-medium-emphasis mb-3">
      <span v-if="client.identification">C.I: {{ client.identification }}</span>
      <span v-if="client.phone" class="ms-2">Tel: {{ client.phone }}</span>
    </div>

    <VDivider class="mb-3" />

    <!-- Productos / Medicamentos -->
    <div v-if="client.products && client.products.length > 1" class="mb-3 d-flex flex-column gap-2">
      <div class="text-caption text-disabled font-weight-medium">Medicamentos ({{ client.products.length }})</div>
      <div
        v-for="(p, idx) in client.products"
        :key="p.product_id"
        class="pa-2 rounded-lg bg-light"
      >
        <div class="font-weight-semibold text-high-emphasis text-sm">{{ p.product_name }}</div>
        <div class="d-flex justify-space-between text-caption text-medium-emphasis mt-1">
          <span>{{ p.purchased_quantity }} un. ({{ p.total_treatment_days }} d)</span>
          <span class="font-weight-medium text-high-emphasis">${{ Number(p.price_usd || 0).toFixed(2) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="mb-2">
      <div class="text-caption text-disabled">Medicamento</div>
      <div class="font-weight-semibold text-high-emphasis">{{ client.product_name }}</div>
      <div class="text-caption text-medium-emphasis">
        {{ client.purchased_quantity }} un. ({{ client.total_treatment_days }} d de cobertura)
      </div>
    </div>

    <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-3">
      <div>
        <div class="text-disabled">Última compra</div>
        <div>{{ client.last_order_date_formatted }}</div>
      </div>
      <div class="text-end">
        <div class="text-disabled">Días restantes</div>
        <div class="font-weight-semibold" :class="client.days_until_end <= 5 ? 'text-warning' : 'text-medium-emphasis'">
          {{ client.days_until_end }} d (Fin: {{ client.treatment_end_date_formatted }})
        </div>
      </div>
    </div>

    <div class="d-flex align-center justify-space-between pt-2 border-t">
      <div>
        <div class="font-weight-medium text-high-emphasis">
          ${{ Number(client.price_usd || 0).toFixed(2) }}
        </div>
        <div class="text-caption text-medium-emphasis">
          Bs. {{ Number(client.price_bs || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          <span v-if="client.price_cop > 0"> | COP {{ Number(client.price_cop || 0).toLocaleString('es-CO') }}</span>
        </div>
      </div>

      <div class="d-flex align-center gap-1">
        <IconBtn
          v-if="client.whatsapp_url || client.phone"
          color="success"
          size="small"
          @click="emit('open-whatsapp', client)"
        >
          <VIcon icon="tabler-brand-whatsapp" size="18" />
          <VTooltip activator="parent">Contactar por WhatsApp</VTooltip>
        </IconBtn>
        <span v-else class="text-caption text-disabled">—</span>

        <IconBtn
          color="primary"
          size="small"
          :loading="loading"
          @click="emit('mark-contacted', client)"
        >
          <VIcon icon="tabler-check" size="18" />
          <VTooltip activator="parent">Marcar como Contactado</VTooltip>
        </IconBtn>

        <IconBtn
          v-if="client.phone"
          color="error"
          size="small"
          @click="emit('remove-phone', client)"
        >
          <VIcon icon="tabler-x" size="18" />
          <VTooltip activator="parent">Remover teléfono sin WhatsApp</VTooltip>
        </IconBtn>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.chronic-mobile-card {
  transition: all 0.2s ease-in-out;
}
.chronic-mobile-card:hover {
  transform: translateY(-2px);
}
</style>