<script setup>
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  contribution: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const isSaving = ref(false)
const paymentDate = ref('')
const paymentReference = ref('')

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val || 0)
}

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    paymentDate.value = new Date().toISOString().split('T')[0]
    paymentReference.value = props.contribution?.payment_reference || ''
  }
})

const handleConfirmPayment = async () => {
  if (!props.contribution) return

  isSaving.value = true
  try {
    const isPaying = props.contribution.status !== 'paid'
    const payload = {
      status: isPaying ? 'paid' : 'pending',
      payment_date: isPaying ? paymentDate.value : null,
      payment_reference: isPaying ? paymentReference.value : null,
    }

    const response = await axios.patch(`/fiscal-contributions/${props.contribution.id}/toggle-payment`, payload)
    toast.success(response.data?.message || 'Estado de pago actualizado.')
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    console.error('Error al actualizar pago:', error)
    toast.error(error.response?.data?.message || 'Error al actualizar el pago.')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="480px"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="props.contribution" class="rounded-lg shadow-lg bg-surface">
      <VCardTitle class="pa-5 d-flex justify-space-between align-center border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar
            :color="props.contribution.status === 'paid' ? 'warning' : 'success'"
            variant="tonal"
            size="36"
            class="rounded-lg"
          >
            <VIcon :icon="props.contribution.status === 'paid' ? 'tabler-rotate-clockwise' : 'tabler-circle-check'" size="22" />
          </VAvatar>
          <span class="text-sm font-weight-black uppercase">
            {{ props.contribution.status === 'paid' ? 'Revertir a Pendiente' : 'Marcar como Pagado' }}
          </span>
        </div>
        <VBtn icon variant="text" size="small" @click="emit('update:modelValue', false)">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Resumen del compromiso -->
        <div class="pa-3 rounded-lg bg-surface-variant-opacity-2 mb-4 border">
          <div class="d-flex justify-space-between align-center mb-1">
            <span class="text-xs text-disabled uppercase font-weight-bold">{{ props.contribution.tax_type }}</span>
            <span class="text-xs font-weight-black text-primary">Doc #{{ props.contribution.document_number }}</span>
          </div>
          <div class="d-flex justify-space-between align-center">
            <span class="text-xs font-weight-medium text-high-emphasis">Monto a Cancelar:</span>
            <span class="text-base font-weight-black text-success">Bs. {{ formatCurrency(props.contribution.amount) }}</span>
          </div>
        </div>

        <template v-if="props.contribution.status !== 'paid'">
          <div class="d-flex flex-column gap-3">
            <AppDateTimePicker
              v-model="paymentDate"
              label="Fecha de Pago *"
              variant="outlined"
              density="compact"
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />

            <VTextField
              v-model="paymentReference"
              label="Referencia Bancaria / N° Transferencia"
              placeholder="Ej: 0102-12345678"
              variant="outlined"
              density="compact"
            />
          </div>
        </template>
        <template v-else>
          <VAlert type="warning" variant="tonal" class="rounded-lg text-xs">
            ¿Confirmas que deseas devolver este compromiso al estado <strong>PENDIENTE</strong>? Se borrará la fecha y referencia de pago.
          </VAlert>
        </template>
      </VCardText>

      <VCardActions class="pa-5 border-t d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          class="rounded-lg text-xs font-weight-bold"
          @click="emit('update:modelValue', false)"
        >
          Cancelar
        </VBtn>
        <VBtn
          variant="flat"
          :color="props.contribution.status === 'paid' ? 'warning' : 'success'"
          class="rounded-lg text-xs font-weight-bold shadow-sm"
          :loading="isSaving"
          @click="handleConfirmPayment"
        >
          <VIcon start :icon="props.contribution.status === 'paid' ? 'tabler-rotate-clockwise' : 'tabler-check'" size="18" />
          {{ props.contribution.status === 'paid' ? 'Confirmar Reversión' : 'Confirmar Pago' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}
</style>
