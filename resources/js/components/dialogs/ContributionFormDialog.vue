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

const taxTypesList = [
  'ANTICIPO-ISLR',
  'IGTF',
  'IVA/35',
  'DPP',
  'ISLR',
  'PATENTE',
  'FONA',
  'FONACIT',
  'DEPORTE',
  'INCIDENCIAS',
  'OTRO',
]

const form = ref({
  id: null,
  period: '',
  tax_type: 'ANTICIPO-ISLR',
  document_number: '',
  operation_date: '',
  due_date: '',
  amount: 0,
  status: 'pending',
  payment_date: null,
  payment_reference: '',
  notes: '',
})

const resetForm = () => {
  const now = new Date()
  const currentMonthStr = String(now.getMonth() + 1).padStart(2, '0')
  const currentYear = now.getFullYear()
  const todayStr = now.toISOString().split('T')[0]

  form.value = {
    id: null,
    period: `${currentMonthStr}/${currentYear}`,
    tax_type: 'ANTICIPO-ISLR',
    document_number: '',
    operation_date: todayStr,
    due_date: todayStr,
    amount: 0,
    status: 'pending',
    payment_date: null,
    payment_reference: '',
    notes: '',
  }
}

watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    if (props.contribution) {
      form.value = {
        id: props.contribution.id,
        period: props.contribution.period || '',
        tax_type: props.contribution.tax_type || 'ANTICIPO-ISLR',
        document_number: props.contribution.document_number || '',
        operation_date: props.contribution.operation_date || '',
        due_date: props.contribution.due_date || '',
        amount: props.contribution.amount || 0,
        status: props.contribution.status || 'pending',
        payment_date: props.contribution.payment_date || null,
        payment_reference: props.contribution.payment_reference || '',
        notes: props.contribution.notes || '',
      }
    } else {
      resetForm()
    }
  }
})

const handleSave = async () => {
  if (!form.value.period || !form.value.tax_type || !form.value.document_number || !form.value.operation_date || !form.value.due_date || form.value.amount <= 0) {
    toast.error('Por favor completa todos los campos requeridos con montos válidos.')
    return
  }

  isSaving.value = true
  try {
    const isEdit = Boolean(form.value.id)
    const url = isEdit ? `/fiscal-contributions/${form.value.id}` : '/fiscal-contributions'
    const method = isEdit ? 'put' : 'post'

    const payload = {
      period: form.value.period,
      tax_type: form.value.tax_type,
      document_number: form.value.document_number,
      operation_date: form.value.operation_date,
      due_date: form.value.due_date,
      amount: form.value.amount,
      status: form.value.status,
      payment_date: form.value.status === 'paid' ? (form.value.payment_date || form.value.operation_date) : null,
      payment_reference: form.value.status === 'paid' ? form.value.payment_reference : null,
      notes: form.value.notes,
      source: 'manual',
    }

    const response = await axios[method](url, payload)
    toast.success(response.data?.message || 'Contribución guardada correctamente.')
    emit('saved')
    emit('update:modelValue', false)
  } catch (error) {
    console.error('Error al guardar contribución:', error)
    toast.error(error.response?.data?.message || 'Ocurrió un error al procesar la solicitud.')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="650px"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg shadow-lg bg-surface">
      <VCardTitle class="pa-5 d-flex justify-space-between align-center border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar color="secondary" variant="tonal" size="36" class="rounded-lg">
            <VIcon icon="tabler-receipt-tax" size="22" />
          </VAvatar>
          <span class="text-sm font-weight-black uppercase">
            {{ form.id ? 'Editar Contribución Fiscal' : 'Nueva Contribución Fiscal' }}
          </span>
        </div>
        <VBtn icon variant="text" size="small" @click="emit('update:modelValue', false)">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow dense>
          <!-- Periodo -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.period"
              label="Periodo (ej: 09/2026) *"
              placeholder="MM/AAAA"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- Tipo de Impuesto -->
          <VCol cols="12" sm="6">
            <VSelect
              v-model="form.tax_type"
              :items="taxTypesList"
              label="Tipo de Impuesto *"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- N° Documento -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model="form.document_number"
              label="N° Documento SENIAT *"
              placeholder="Ej: 2601586717"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- Monto Bs. -->
          <VCol cols="12" sm="6">
            <VTextField
              v-model.number="form.amount"
              label="Monto en Bolívares (Bs.) *"
              type="number"
              step="0.01"
              min="0"
              variant="outlined"
              density="compact"
              prefix="Bs."
            />
          </VCol>

          <!-- Fecha de Operación -->
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.operation_date"
              label="Fecha de Operación *"
              variant="outlined"
              density="compact"
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <!-- Fecha de Vencimiento -->
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.due_date"
              label="Fecha de Vencimiento *"
              variant="outlined"
              density="compact"
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <!-- Estado -->
          <VCol cols="12" sm="6">
            <VSelect
              v-model="form.status"
              :items="[
                { title: 'Pendiente', value: 'pending' },
                { title: 'Pagado', value: 'paid' },
              ]"
              item-title="title"
              item-value="value"
              label="Estado de Pago"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- Referencia de Pago (Si pagado) -->
          <VCol v-if="form.status === 'paid'" cols="12" sm="6">
            <VTextField
              v-model="form.payment_reference"
              label="Referencia de Pago"
              placeholder="N° de Transferencia / Recibo"
              variant="outlined"
              density="compact"
            />
          </VCol>

          <!-- Fecha de Pago (Si pagado) -->
          <VCol v-if="form.status === 'paid'" cols="12">
            <AppDateTimePicker
              v-model="form.payment_date"
              label="Fecha Efectiva de Pago"
              variant="outlined"
              density="compact"
              :config="{ altInput: true, altFormat: 'Y-m-d', dateFormat: 'Y-m-d' }"
            />
          </VCol>

          <!-- Observaciones -->
          <VCol cols="12">
            <VTextarea
              v-model="form.notes"
              label="Observaciones"
              rows="2"
              variant="outlined"
              density="compact"
              placeholder="Detalles adicionales..."
            />
          </VCol>
        </VRow>
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
          color="primary"
          class="rounded-lg text-xs font-weight-bold shadow-sm"
          :loading="isSaving"
          @click="handleSave"
        >
          <VIcon start icon="tabler-check" size="18" />
          {{ form.id ? 'Actualizar' : 'Guardar' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
