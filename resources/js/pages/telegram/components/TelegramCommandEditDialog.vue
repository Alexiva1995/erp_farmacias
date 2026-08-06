<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  commandData: {
    type: Object,
    default: () => ({}),
  },
  channelOptions: {
    type: Array,
    default: () => [],
  },
  saving: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const form = ref({
  id: null,
  command: '',
  alias: '',
  description: '',
  channel_id: null,
  payload_template: '',
  is_active: true,
})

watch(
  () => props.commandData,
  (newVal) => {
    if (newVal) {
      form.value = {
        id: newVal.id || null,
        command: newVal.command || '',
        alias: newVal.alias || '',
        description: newVal.description || '',
        channel_id: newVal.channel_id ?? null,
        payload_template: newVal.payload_template || '',
        is_active: newVal.is_active ?? true,
      }
    }
  },
  { immediate: true, deep: true }
)

const handleClose = () => {
  emit('update:modelValue', false)
}

const handleSave = () => {
  emit('save', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="620px"
    persistent
    @update:model-value="(val) => emit('update:modelValue', val)"
  >
    <VCard rounded="lg">
      <VCardTitle class="px-6 pt-6 d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VAvatar color="primary" variant="tonal" size="38" class="me-3">
            <VIcon icon="tabler-pencil" size="20" />
          </VAvatar>

          <div>
            <div class="text-h6 font-weight-bold">
              Editar Comando
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ form.command ? form.command : 'Configuración de Disparo' }}
            </div>
          </div>
        </div>

        <VBtn
          icon
          variant="text"
          size="small"
          :disabled="props.saving"
          @click="handleClose"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider class="mt-3" />

      <VCardText class="px-6 py-4">
        <VRow>
          <!-- Comando -->
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.command"
              label="Comando o Disparador"
              placeholder="ej: /factura"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-terminal-2"
              hide-details="auto"
            />
          </VCol>

          <!-- Alias / Nombre Visible -->
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.alias"
              label="Nombre / Alias"
              placeholder="ej: Escaneo Facturas IA"
              density="compact"
              variant="outlined"
              prepend-inner-icon="tabler-tag"
              hide-details="auto"
            />
          </VCol>

          <!-- Canal Destino -->
          <VCol cols="12">
            <VSelect
              v-model="form.channel_id"
              :items="props.channelOptions"
              item-title="title"
              item-value="value"
              label="Canal Destino de Telegram"
              prepend-inner-icon="tabler-brand-telegram"
              hint="Selecciona el canal de Telegram asignado para las respuestas de este comando."
              persistent-hint
              density="compact"
              variant="outlined"
            />
          </VCol>

          <!-- Descripción -->
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="Descripción del Comando"
              rows="3"
              density="compact"
              variant="outlined"
              placeholder="Describe la funcionalidad y las acciones automáticas que ejecuta..."
              hide-details="auto"
            />
          </VCol>

          <!-- Plantilla de Respuesta -->
          <VCol cols="12">
            <VTextarea
              v-model="form.payload_template"
              label="Plantilla de Respuesta (Opcional)"
              rows="2"
              density="compact"
              variant="outlined"
              placeholder="Plantilla predeterminada enviada al bot de Telegram..."
              hide-details="auto"
            />
          </VCol>

          <!-- Swtich Activo -->
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              label="Comando Habilitado"
              color="success"
              hide-details
              density="compact"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="px-6 py-4">
        <VSpacer />

        <VBtn
          variant="outlined"
          color="secondary"
          :disabled="props.saving"
          @click="handleClose"
        >
          Cancelar
        </VBtn>

        <VBtn
          color="primary"
          variant="elevated"
          :loading="props.saving"
          @click="handleSave"
        >
          <VIcon icon="tabler-device-floppy" class="me-1" />
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
