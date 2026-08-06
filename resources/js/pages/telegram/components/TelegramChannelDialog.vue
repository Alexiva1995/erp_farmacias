<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  channelData: {
    type: Object,
    default: () => null,
  },
  saving: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'save'])

const form = ref({
  id: null,
  name: '',
  chat_id: '',
  module: 'general',
  description: '',
  is_active: true,
})

const moduleOptions = [
  { title: 'General / Todas las alertas', value: 'general' },
  { title: 'Farmacia', value: 'farmacia' },
  { title: 'Restaurante', value: 'restaurante' },
  { title: 'Cosméticos', value: 'cosmeticos' },
  { title: 'Alquileres / Canchas', value: 'alquileres' },
]

watch(
  () => props.channelData,
  (val) => {
    if (val) {
      form.value = { ...val }
    } else {
      form.value = {
        id: null,
        name: '',
        chat_id: '',
        module: 'general',
        description: '',
        is_active: true,
      }
    }
  },
  { immediate: true }
)

const handleSave = () => {
  emit('save', { ...form.value })
}

const close = () => {
  emit('update:modelValue', false)
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="600px"
    @update:model-value="close"
  >
    <VCard>
      <VCardTitle class="px-6 pt-6 d-flex align-center justify-space-between">
        <span class="text-h6 font-weight-bold">
          {{ form.id ? 'Editar Canal de Telegram' : 'Añadir Nuevo Canal de Telegram' }}
        </span>
        <VBtn icon variant="text" size="small" @click="close">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VDivider class="mt-2" />

      <VCardText class="px-6 py-4">
        <VForm @submit.prevent="handleSave">
          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.name"
                label="Nombre del Canal *"
                placeholder="ej: Alertas de Ventas Farmacia"
                prepend-inner-icon="tabler-tag"
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VTextField
                v-model="form.chat_id"
                label="Telegram Chat ID *"
                placeholder="ej: -1001987654321"
                prepend-inner-icon="tabler-message"
                hint="ID numérico del grupo o canal de Telegram"
                persistent-hint
                required
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSelect
                v-model="form.module"
                :items="moduleOptions"
                item-title="title"
                item-value="value"
                label="Módulo Asignado"
                prepend-inner-icon="tabler-category"
              />
            </VCol>

            <VCol cols="12" md="6">
              <VSwitch
                v-model="form.is_active"
                label="Canal Habilitado"
                color="success"
                class="mt-2"
                hide-details
              />
            </VCol>

            <VCol cols="12">
              <VTextarea
                v-model="form.description"
                label="Descripción / Propósito"
                rows="2"
                placeholder="Detalla qué tipo de mensajes o notificaciones deben llegar a este canal..."
              />
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VDivider />

      <VCardActions class="px-6 py-4">
        <VSpacer />
        <VBtn variant="outlined" color="secondary" @click="close">
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :loading="props.saving"
          :disabled="!form.name || !form.chat_id"
          @click="handleSave"
        >
          {{ form.id ? 'Guardar Cambios' : 'Registrar Canal' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
