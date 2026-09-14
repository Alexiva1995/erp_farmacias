<script setup>
import { computed } from 'vue'

const props = defineProps({
  client: { type: Object, required: true },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['mark-contacted'])

const getStatusColor = (client) => {
  if (client.is_urgent) return 'warning'
  if (client.is_active) return 'success'
  return 'error'
}

const getStatusLabel = (client) => {
  if (client.is_urgent) return 'Alerta (≤ 5 días)'
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
      <div class="font-weight-bold text-high-emphasis">
        {{ client.client_name }}
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
      <div class="text-caption text-disabled font-weight-bold">Medicamentos ({{ client.products.length }})</div>
      <div
        v-for="(p, idx) in client.products"
        :key="p.product_id"
        class="pa-2 rounded-lg bg-light"
      >
        <div class="font-weight-semibold text-primary text-sm">{{ p.product_name }}</div>
        <div class="d-flex justify-space-between text-caption text-medium-emphasis mt-1">
          <span>{{ p.purchased_quantity }} un. ({{ p.total_treatment_days }} días)</span>
          <span class="font-weight-bold text-success">${{ Number(p.price_usd || 0).toFixed(2) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="mb-2">
      <div class="text-caption text-disabled">Medicamento</div>
      <div class="font-weight-semibold text-primary">{{ client.product_name }}</div>
      <div class="text-caption text-medium-emphasis">
        {{ client.purchased_quantity }} un. ({{ client.total_treatment_days }} días de cobertura)
      </div>
    </div>

    <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-3">
      <div>
        <div class="text-disabled">Última compra</div>
        <div>{{ client.last_order_date_formatted }}</div>
      </div>
      <div class="text-end">
        <div class="text-disabled">Días restantes</div>
        <div class="font-weight-bold" :class="client.days_until_end <= 5 ? 'text-warning' : 'text-high-emphasis'">
          {{ client.days_until_end }} días (Fin: {{ client.treatment_end_date_formatted }})
        </div>
      </div>
    </div>

    <div class="d-flex align-center justify-space-between pt-2 border-t">
      <div>
        <div class="font-weight-bold text-success">
          ${{ Number(client.price_usd || 0).toFixed(2) }}
        </div>
        <div class="text-caption text-medium-emphasis">
          Bs. {{ Number(client.price_bs || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          <span v-if="client.price_cop > 0"> | COP {{ Number(client.price_cop || 0).toLocaleString('es-CO') }}</span>
        </div>
      </div>

      <div class="d-flex align-center gap-2">
        <VBtn
          v-if="client.whatsapp_url"
          color="success"
          variant="flat"
          size="small"
          icon
          :href="client.whatsapp_url"
          target="_blank"
          rel="noopener noreferrer"
          class="rounded-lg"
          title="WhatsApp"
        >
          <VIcon icon="tabler-brand-whatsapp" size="20" />
        </VBtn>
        <span v-else class="text-caption text-disabled">Sin teléfono</span>

        <VBtn
          color="primary"
          variant="tonal"
          size="small"
          icon
          class="rounded-lg"
          :loading="loading"
          title="Marcar como Contactado"
          @click="emit('mark-contacted', client)"
        >
          <VIcon icon="tabler-check" size="20" color="success" />
        </VBtn>
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