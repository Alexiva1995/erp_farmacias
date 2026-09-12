<script setup>
import { computed } from 'vue'

const props = defineProps({
  client: { type: Object, required: true },
})

const getStatusColor = (status) => {
  if (status === 'urgent') return 'warning'
  if (status === 'active') return 'success'
  return 'error'
}

const getStatusLabel = (status) => {
  if (status === 'urgent') return 'Alerta (≤ 5 días)'
  if (status === 'active') return 'Activo'
  return 'Agotado'
}

const getStatusIcon = (status) => {
  if (status === 'urgent') return 'tabler-alert-triangle'
  if (status === 'active') return 'tabler-circle-check'
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
        :color="getStatusColor(client.status)"
        variant="tonal"
        class="font-weight-medium"
      >
        <VIcon :icon="getStatusIcon(client.status)" start size="12" />
        {{ getStatusLabel(client.status) }}
      </VChip>
    </div>

    <div class="text-caption text-medium-emphasis mb-3">
      <span v-if="client.client_doc">C.I: {{ client.client_doc }}</span>
      <span v-if="client.client_phone" class="ms-2">Tel: {{ client.client_phone }}</span>
    </div>

    <VDivider class="mb-3" />

    <div class="mb-2">
      <div class="text-caption text-disabled">Medicamento</div>
      <div class="font-weight-semibold text-primary">{{ client.product_name }}</div>
      <div class="text-caption text-medium-emphasis">
        {{ client.purchased_units }} un. ({{ client.total_treatment_days }} días de cobertura)
      </div>
    </div>

    <div class="d-flex justify-space-between text-caption text-medium-emphasis mb-3">
      <div>
        <div class="text-disabled">Última compra</div>
        <div>{{ client.last_purchase_date }}</div>
      </div>
      <div class="text-end">
        <div class="text-disabled">Días restantes</div>
        <div class="font-weight-bold" :class="client.days_remaining <= 5 ? 'text-warning' : 'text-high-emphasis'">
          {{ client.days_remaining }} días (Fin: {{ client.estimated_depletion_date }})
        </div>
      </div>
    </div>

    <div class="d-flex align-center justify-space-between pt-2 border-t">
      <div>
        <div class="font-weight-bold text-success">
          ${{ Number(client.price_usd || 0).toFixed(2) }}
        </div>
        <div class="text-caption text-medium-emphasis">
          Bs. {{ Number(client.price_ves || 0).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          <span v-if="client.price_cop > 0"> | COP {{ Number(client.price_cop || 0).toLocaleString('es-CO') }}</span>
        </div>
      </div>

      <VBtn
        v-if="client.whatsapp_url"
        color="success"
        variant="flat"
        size="small"
        prepend-icon="tabler-brand-whatsapp"
        :href="client.whatsapp_url"
        target="_blank"
        rel="noopener noreferrer"
        class="rounded-lg text-none"
      >
        WhatsApp
      </VBtn>
      <span v-else class="text-caption text-disabled">Sin teléfono</span>
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