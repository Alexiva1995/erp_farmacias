<script setup>
import { reactive, watch } from 'vue'
import { $api } from '@/utils/api'
import { toast } from '@/plugins/sweetalert'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  product: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const savingProduct = ref(false)
const form = reactive({
  id: null,
  name: '',
  consumption_type: 'chronic',
  treatment_duration_days: 30,
})

const formConsumptionTypes = [
  { title: 'Crónico (Uso Continuo - Recompra Recurrente)', value: 'chronic' },
  { title: 'Tratamiento Único / Ciclo (Seguimiento al finalizar)', value: 'single_treatment' },
  { title: 'Sin Alerta / Insumos (No alertar ni recordar)', value: 'no_alert' },
  { title: 'Esporádico / Ocasional', value: 'sporadic' },
]

watch(() => props.product, (newVal) => {
  if (newVal && newVal.id) {
    form.id = newVal.id
    form.name = newVal.name || ''
    form.consumption_type = newVal.consumption_type || 'chronic'
    form.treatment_duration_days = newVal.treatment_duration_days || (newVal.consumption_type === 'single_treatment' ? 7 : 30)
  }
}, { immediate: true, deep: true })

const close = () => {
  emit('update:modelValue', false)
}

const save = async () => {
  if (!form.id) return
  savingProduct.value = true
  try {
    const duration = ['chronic', 'single_treatment', 'sporadic'].includes(form.consumption_type)
      ? (form.treatment_duration_days
          ? parseInt(form.treatment_duration_days, 10)
          : (form.consumption_type === 'single_treatment' ? 7 : 30))
      : null

    const payload = {
      consumption_type: form.consumption_type,
      treatment_duration_days: duration,
    }

    const res = await $api(`/crm/chronic-clients/products-config/${form.id}`, {
      method: 'PUT',
      body: payload,
      data: payload,
    })

    toast.success('Clasificación de producto actualizada correctamente.')
    emit('saved', res?.data || { id: form.id, ...payload })
    close()
  } catch (e) {
    console.error('Error saveProductConsumption:', e)
    const errorMsg = e?.response?._data?.message || e?.message || 'No se pudo actualizar la clasificación del producto.'
    toast.error(errorMsg)
  } finally {
    savingProduct.value = false
  }
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580px"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface">
      <!-- Cabecera Premium Estándar -->
      <VCardTitle class="pa-0">
        <div class="pa-4 header-gradient d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1 rounded-lg"
          >
            <VIcon
              icon="tabler-adjustments-horizontal"
              size="24"
              class="modal-avatar-icon text-primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 text-uppercase">
              Configurar Consumo y Frecuencia
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 text-uppercase font-weight-bold text-caption text-truncate"
                style="letter-spacing: 0.05em; max-inline-size: 380px;"
                :title="form.name"
              >
                {{ form.name }}
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="close"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-6">
        <VRow dense>
          <VCol cols="12">
            <VSelect
              v-model="form.consumption_type"
              :items="formConsumptionTypes"
              item-title="title"
              item-value="value"
              label="Tipo de Consumo *"
              density="compact"
              class="mb-3"
              prepend-inner-icon="tabler-category"
            />
          </VCol>

          <VCol cols="12" v-if="['chronic', 'single_treatment', 'sporadic'].includes(form.consumption_type)">
            <VTextField
              v-model.number="form.treatment_duration_days"
              label="Frecuencia / Duración de Alerta (Días) *"
              type="number"
              min="1"
              max="365"
              density="compact"
              prepend-inner-icon="tabler-clock-hour-4"
              :hint="form.consumption_type === 'chronic' ? 'Días de cobertura estimada por cada unidad comprada (ej: 30 días).' : (form.consumption_type === 'single_treatment' ? 'Días que dura el ciclo completo antes de realizar seguimiento (ej: 7 o 14 días).' : 'Días tras la compra para consultar disponibilidad en botiquín (por defecto 30 días).')"
              persistent-hint
            />
          </VCol>

          <VCol cols="12" v-if="form.consumption_type === 'no_alert'">
            <VAlert type="info" variant="tonal" density="compact" class="mt-2">
              Este producto (insumo / descartable) quedará marcado sin alertas. Las futuras compras no generarán recordatorios ni aparecerán en la lista de pacientes crónicos.
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 pa-sm-6 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="48"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              :disabled="savingProduct"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="48"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              :loading="savingProduct"
              :disabled="savingProduct"
              @click="save"
            >
              <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
              Guardar
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgba(var(--v-theme-primary), 0.8) 100%);
}
.modal-avatar-icon {
  color: rgb(var(--v-theme-primary)) !important;
}
.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
