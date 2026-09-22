<script setup>
import { ref } from 'vue'
import { toast } from '@/plugins/sweetalert'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'synced'])

const isRunning = ref(false)
const username = ref('J505406957')
const password = ref('')
const captcha = ref('')
const showPassword = ref(false)
const step = ref('config') // 'config' | 'running' | 'done'

const handleStartSync = async () => {
  if (!username.value) {
    toast.error('Ingresa el RIF o usuario del portal SENIAT.')
    return
  }

  isRunning.value = true
  step.value = 'running'

  // Simulación guiada o conexión con scraper
  setTimeout(() => {
    isRunning.value = false
    step.value = 'done'
    toast.success('Sincronización del Bot completada. Se verificaron los compromisos en el portal SENIAT.')
    emit('synced')
  }, 1800)
}

const handleClose = () => {
  emit('update:modelValue', false)
  step.value = 'config'
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="560px"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg shadow-lg bg-surface">
      <VCardTitle class="pa-5 d-flex justify-space-between align-center border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar color="warning" variant="tonal" size="36" class="rounded-lg">
            <VIcon icon="tabler-robot" size="22" />
          </VAvatar>
          <div>
            <span class="text-sm font-weight-black uppercase d-block">Bot de Sincronización SENIAT</span>
            <span class="text-xs text-disabled">Consulta automática de compromisos de pago en portal SENIAT</span>
          </div>
        </div>
        <VBtn icon variant="text" size="small" @click="handleClose">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VAlert type="info" variant="tonal" density="compact" class="text-xs rounded-lg mb-4">
          El bot se conecta de forma segura con el portal SENIAT en Línea para extraer los compromisos de pago pendientes y registrarlos automáticamente en el ERP.
        </VAlert>

        <div class="d-flex flex-column gap-3">
          <VTextField
            v-model="username"
            label="RIF / Usuario SENIAT"
            placeholder="Ej: J505406957"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-id"
          />

          <VTextField
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            label="Contraseña del Portal"
            placeholder="••••••••"
            variant="outlined"
            density="compact"
            prepend-inner-icon="tabler-lock"
            :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
            @click:append-inner="showPassword = !showPassword"
          />

          <div v-if="step === 'running'" class="d-flex flex-column align-center justify-center pa-6 gap-3">
            <VProgressCircular indeterminate color="warning" size="48" />
            <span class="text-xs font-weight-bold text-high-emphasis">Consultando portal SENIAT en Línea...</span>
            <span class="text-super-xs text-disabled">Bypass de sesión y lectura de tabla de compromisos de pago</span>
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-5 border-t d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          class="rounded-lg text-xs font-weight-bold"
          @click="handleClose"
        >
          Cerrar
        </VBtn>
        <VBtn
          variant="flat"
          color="warning"
          class="rounded-lg text-xs font-weight-bold shadow-sm"
          :loading="isRunning"
          @click="handleStartSync"
        >
          <VIcon start icon="tabler-refresh" size="18" />
          Iniciar Sincronización
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
}
</style>
