<script setup>
import { computed } from 'vue';
import { toast } from '@/plugins/sweetalert';

const props = defineProps({
  item: { type: Object, required: true },
});

	// Abrir whatsapp
const openWhatsApp = () => {
  if (!props.item.whatsapp_url) {
    toast.warning('El cliente no posee un teléfono válido.');
    return;
  }
  window.open(props.item.whatsapp_url, '_blank');
};

	// Copiar mensaje
const copyMessage = () => {
  if (!props.item.whatsapp_message) return;
  navigator.clipboard.writeText(props.item.whatsapp_message);
  toast.success('Mensaje copiado al portapapeles.');
};
</script>

<template>
  <VCard class="chronic-mobile-card rounded-mg border shadow-sm pa-3 mb-3">
    <!-- Encabezado: Cliente y Estado -->
    <div class="d-flex align-center justify-space-between gap-2 mb-2">
      <div class="d-flex align-center gap-2">
        <VAvatar color="primary" variant="tonal" size="32" class="font-weight-black">
          #{{ item.client_id }}
        </VAvatar>
        <div>
          <div class="text-subtitle-2 font-weight-black leading-tight">
            {{ item.client_name }}
          </div>
          <div class="text-super-xs text-disabled font-weight-bold">
            ID: {{ item.identification || 'S/C' }}
          </div>
        </div>
      </div>

      <VChip
        :color="item.status_color"
        size="small"
        variant="elevated"
        class="font-weight-bold text-super-xs"
      >
        <VIcon start size="12" :icon="item.is_urgent ? 'tabler-alert-triangle' : (item.is_expired ? 'tabler-clock-x' : 'tabler-check')" />
        {{ item.status_label }}
      </VChip>
    </div>

    <!-- Medicamento -->
    <div class="bg-light pa-2 rounded-mg border mb-2">
      <div class="text-body-2 font-weight-black text-high-emphasis">
        {{ item.product_name }}
      </div>
      <div class="text-super-xs text-disabled d-flex align-center justify-space-between mt-1">
        <span>Lab: {{ item.laboratory_name }}</span>
        <span>Barras: {{ item.product_barcode || 'S/C' }}</span>
      </div>
    </div>

    <!-- Detalles de Compra y Fechas -->
    <div class="d-flex justify-space-between align-center text-super-xs mb-2">
      <div>
        <span class="text-disabled">�iltima Compra: </span>
        <span class="font-weight-bold">{{ item.last_order_date_formatted }} ({{ item.purchased_quantity }} uds)</span>
      </div>
      <div>
        <span class="text-disabled">Fin Estimado: </span>
        <span class="font-weight-black" :class="item.is_urgent ? 'text-warning' : (item.is_expired ? 'text-error' : 'text-success')">
          {{ item.treatment_end_date_formatted }}
        </span>
      </div>
    </div>

    <!-- Precios y Acciones -->
    <div class="d-flex align-center justify-space-between grid-gap-2-top pt-2 border-t">
      <div>
        <div class="text-subtitle-2 font-weight-black text-primary">
          ${{ item.price_usd?.toFixed(2) }}
        </div>
        <div v-if="item.price_bs > 0 || item.price_cop > 0" class="text-super-xs text-disabled">
          <span v-if="item.price_bs > 0">Bs. {{ item.price_bs?.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} </span>
          <span v-if="item.price_cop > 0"> | COP {{ item.price_cop?.toLocaleString('es-CO') }}</span>
        </div>
      </div>

      <div class="d-flex align-center gap-1">
        <VBtn
          color="success"
          size="small"
          variant="elevated"
          class="font-weight-bold rounded-lg"
          :disabled="!item.phone"
          @click="openWhatsApp"
        >
          <VIcon start icon="tabler-brand-whatsapp" size="16" />
          Recordar
        </VBtn>

        <VBtn
          icon="tabler-copy"
          variant="tonal"
          color="secondary"
          size="small"
          class="rounded-lg"
          @click="copyMessage"
        >
          <VIcon size="16" />
        </VBtn>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.chronic-mobile-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
 }
.chronic-mobile-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}
.text-super-xs {
  font-size: 0.71rem !important;
}
</style>
