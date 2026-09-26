<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'copy'])

const close = () => {
  emit('update:modelValue', false)
}

const formatBs = (value) => {
  const num = Number(value) || 0
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(num)
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="item" class="rounded-xl overflow-hidden shadow-lg border-0 d-flex flex-column bg-surface">
      <!-- Header Corporativo Institucional del Sistema -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div
          class="px-5 py-4 d-flex align-center justify-space-between text-white"
          style="background: linear-gradient(135deg, #7A0099, #E20074) !important;"
        >
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 flex-shrink-0">
              <VIcon color="primary" size="22">tabler-arrow-back-up</VIcon>
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0.5" style="color: white !important;">
                Detalle de Devolución — Factura {{ item.invoice_number }}
              </h2>
              <span class="text-caption text-white opacity-90 font-weight-medium" style="color: white !important; font-size: 11px;">
                Información del producto devuelto y reembolso
              </span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="x-small"
            class="rounded-lg"
            @click="close"
          />
        </div>
      </VCardTitle>

      <!-- Resumen de Factura y Proveedor -->
      <div class="px-5 py-2.5 bg-surface border-b d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-building" size="16" color="primary" />
          <span class="text-caption text-medium-emphasis font-weight-medium">Proveedor:</span>
          <span class="text-caption font-weight-bold text-high-emphasis text-uppercase">{{ item.supplier_name }}</span>
        </div>
        <div class="d-flex align-center gap-2">
          <VChip size="x-small" variant="tonal" color="primary" class="font-weight-bold">
            Factura: {{ item.invoice_number }}
          </VChip>
          <VChip
            size="x-small"
            variant="tonal"
            :color="item.status === 'approved' ? 'success' : item.status === 'rejected' ? 'error' : 'warning'"
            class="font-weight-bold text-uppercase"
          >
            {{ item.status_label || item.status }}
          </VChip>
        </div>
      </div>

      <VCardText class="pa-5 bg-surface">
        <VList density="compact" class="pa-0 bg-transparent">
          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="primary" variant="tonal" class="me-3">
                <VIcon icon="tabler-package" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Producto a Devolver</VListItemTitle>
            <VListItemSubtitle class="text-body-1 font-weight-bold text-high-emphasis">{{ item.product_name }}</VListItemSubtitle>
          </VListItem>

          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="info" variant="tonal" class="me-3">
                <VIcon icon="tabler-barcode" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Código de Barras / SKU</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-medium text-high-emphasis">{{ item.barcode || item.sku || 'N/A' }}</VListItemSubtitle>
          </VListItem>

          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="primary" variant="tonal" class="me-3">
                <VIcon icon="tabler-numbers" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Cantidad Devuelta</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-bold text-high-emphasis">{{ item.quantity }} unidades</VListItemSubtitle>
          </VListItem>

          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="secondary" variant="tonal" class="me-3">
                <VIcon icon="tabler-cash" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Monto Reembolso</VListItemTitle>
            <VListItemSubtitle class="text-body-1 font-weight-black text-high-emphasis">
              Bs {{ formatBs(item.amount_refunded_bs || item.amount_refunded) }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem v-if="item.supplier_discount_percentage > 0" class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="warning" variant="tonal" class="me-3">
                <VIcon icon="tabler-discount-2" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Descuento Proveedor</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-medium text-high-emphasis">{{ item.supplier_discount_percentage }}%</VListItemSubtitle>
          </VListItem>

          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="primary" variant="tonal" class="me-3">
                <VIcon icon="tabler-calendar-event" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Lote y Vencimiento</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-medium text-high-emphasis">
              Lote: {{ item.lot_number || 'N/A' }} | Venc: {{ item.expiration_date || 'N/A' }}
            </VListItemSubtitle>
          </VListItem>

          <VListItem class="px-0 py-1">
            <template #prepend>
              <VAvatar size="32" color="secondary" variant="tonal" class="me-3">
                <VIcon icon="tabler-calendar" size="18" />
              </VAvatar>
            </template>
            <VListItemTitle class="font-weight-medium text-caption text-medium-emphasis">Fecha de Registro</VListItemTitle>
            <VListItemSubtitle class="text-body-2 font-weight-medium text-high-emphasis">{{ item.return_date || 'N/A' }}</VListItemSubtitle>
          </VListItem>
        </VList>
      </VCardText>

      <VCardActions class="pa-4 bg-surface border-t flex-wrap ga-2">
        <VBtn
          color="primary"
          variant="flat"
          prepend-icon="tabler-copy"
          class="font-weight-bold"
          @click="emit('copy', item)"
        >
          Copiar Datos
        </VBtn>

        <VSpacer />

        <VBtn variant="outlined" color="secondary" class="font-weight-bold" @click="close">
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
