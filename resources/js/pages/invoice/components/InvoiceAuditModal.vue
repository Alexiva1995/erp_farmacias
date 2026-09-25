<script setup>
// Modal de Auditoría de Factura
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  invoice: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="550"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="overflow-hidden">
      <VCardTitle class="bg-primary text-white d-flex align-center justify-space-between pa-4">
        <span class="text-h6 font-weight-bold text-white">Historial de Auditoría - Factura #{{ invoice?.invoice_number }}</span>
        <VBtn icon="tabler-x" variant="text" color="white" @click="emit('update:modelValue', false)" />
      </VCardTitle>

      <VCardText class="pa-6">
        <p class="text-subtitle-2 text-disabled mb-6 uppercase letter-spacing-1 font-weight-black">
          Ciclo de Vida de la Factura y Operadores Responsables
        </p>

        <div class="d-flex flex-column gap-6">
          <!-- Subido por -->
          <div class="d-flex align-start gap-4">
            <VAvatar size="36" color="success" variant="tonal" class="flex-shrink-0">
              <VIcon icon="tabler-cloud-upload" size="18" class="text-success" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-high-emphasis text-body-1">1. Subida / Registro Inicial</span>
                <VChip size="x-small" color="success" class="font-weight-bold">Completado</VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mt-1">
                Operador: <strong class="text-high-emphasis">{{ invoice?.uploaded_by_user?.name || 'Sistema / N/A' }}</strong>
              </p>
            </div>
          </div>

          <VDivider />

          <!-- Registrado por -->
          <div class="d-flex align-start gap-4">
            <VAvatar size="36" :color="invoice?.registered_by_user ? 'info' : 'secondary'" variant="tonal" class="flex-shrink-0">
              <VIcon icon="tabler-list-check" size="18" :class="invoice?.registered_by_user ? 'text-info' : 'text-secondary'" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-high-emphasis text-body-1">2. Carga / Registro de Productos</span>
                <VChip size="x-small" :color="invoice?.registered_by_user ? 'success' : 'warning'" class="font-weight-bold">
                  {{ invoice?.registered_by_user ? 'Completado' : 'Pendiente' }}
                </VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mt-1">
                Operador: <strong class="text-high-emphasis">{{ invoice?.registered_by_user?.name || 'No asignado' }}</strong>
              </p>
            </div>
          </div>

          <VDivider />

          <!-- Cargado por (Ubicación de Lotes) -->
          <div class="d-flex align-start gap-4">
            <VAvatar size="36" :color="invoice?.loaded_by_user ? 'warning' : 'secondary'" variant="tonal" class="flex-shrink-0">
              <VIcon icon="tabler-box-margin" size="18" :class="invoice?.loaded_by_user ? 'text-warning' : 'text-secondary'" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-high-emphasis text-body-1">3. Verificación y Ubicación Física</span>
                <VChip size="x-small" :color="invoice?.loaded_by_user ? 'success' : 'warning'" class="font-weight-bold">
                  {{ invoice?.loaded_by_user ? 'Completado' : 'Pendiente' }}
                </VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mt-1">
                Operador: <strong class="text-high-emphasis">{{ invoice?.loaded_by_user?.name || 'No asignado' }}</strong>
              </p>
            </div>
          </div>

          <VDivider />

          <!-- Ordenado por -->
          <div class="d-flex align-start gap-4">
            <VAvatar size="36" :color="invoice?.ordered_by_user ? 'primary' : 'secondary'" variant="tonal" class="flex-shrink-0">
              <VIcon icon="tabler-circle-check" size="18" :class="invoice?.ordered_by_user ? 'text-primary' : 'text-secondary'" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="d-flex align-center justify-space-between">
                <span class="font-weight-bold text-high-emphasis text-body-1">4. Aprobación y Orden de Compra</span>
                <VChip size="x-small" :color="invoice?.ordered_by_user ? 'success' : 'warning'" class="font-weight-bold">
                  {{ invoice?.ordered_by_user ? 'Completado' : 'Pendiente' }}
                </VChip>
              </div>
              <p class="text-body-2 text-medium-emphasis mt-1">
                Operador: <strong class="text-high-emphasis">{{ invoice?.ordered_by_user?.name || 'No asignado' }}</strong>
              </p>
            </div>
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-light d-flex justify-end border-t">
        <VBtn color="secondary" variant="flat" @click="emit('update:modelValue', false)">
          Cerrar Ventana
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
