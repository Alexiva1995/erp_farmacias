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

// Variables dinámicas admitidas para la plantilla
const availableVariables = ['{fecha}', '{tasa_bcv}', '{tasa_cop}', '{usuario}', '{sucursal}']

// Reglas de validación
const commandRules = [
  (v) => !!v || 'El comando es obligatorio.',
  (v) => (v && v.startsWith('/')) || 'El comando debe iniciar con "/" (ej: /tasa).',
]

const aliasRules = [
  (v) => !!v || 'El nombre o alias es obligatorio.',
]

const insertVariable = (variable) => {
  form.value.payload_template = (form.value.payload_template || '') + ` ${variable}`
}

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
  if (!form.value.command || !form.value.command.startsWith('/') || !form.value.alias) {
    return
  }
  emit('save', { ...form.value })
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="640px"
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
              Editar Comando y Notificación
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ form.command || 'Configuración de Parámetros' }}
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
              label="Comando / Disparador"
              placeholder="ej: /tasa"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-terminal-2"
              :rules="commandRules"
              hide-details="auto"
            />
          </VCol>

          <!-- Alias / Nombre Visible -->
          <VCol cols="12" md="6">
            <VTextField
              v-model="form.alias"
              label="Nombre / Alias"
              placeholder="ej: Notificación Tasas BCV"
              density="comfortable"
              variant="outlined"
              prepend-inner-icon="tabler-tag"
              :rules="aliasRules"
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
              hint="Canal o chat grupal donde se emitirán los mensajes asociados a este comando."
              persistent-hint
              density="comfortable"
              variant="outlined"
            />
          </VCol>

          <!-- Descripción -->
          <VCol cols="12">
            <VTextarea
              v-model="form.description"
              label="Descripción"
              rows="2"
              density="comfortable"
              variant="outlined"
              placeholder="Explica cuándo y cómo se dispara este comando..."
              hide-details="auto"
            />
          </VCol>

          <!-- Plantilla de Respuesta -->
          <VCol cols="12">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="text-caption font-weight-bold text-medium-emphasis">Plantilla de Respuesta</span>
              <div class="d-flex gap-1 flex-wrap">
                <VChip
                  v-for="v in availableVariables"
                  :key="v"
                  size="x-small"
                  variant="outlined"
                  color="primary"
                  class="cursor-pointer"
                  @click="insertVariable(v)"
                >
                  + {{ v }}
                </VChip>
              </div>
            </div>
            <VTextarea
              v-model="form.payload_template"
              rows="3"
              density="comfortable"
              variant="outlined"
              placeholder="Mensaje personalizado. Haz clic en las etiquetas para insertarlas..."
              hide-details="auto"
            />
          </VCol>

          <!-- Switch Activo -->
          <VCol cols="12">
            <VSwitch
              v-model="form.is_active"
              label="Habilitar envío y respuesta para este comando"
              color="success"
              hide-details="auto"
              density="comfortable"
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
