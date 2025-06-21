<script setup>
import axios from 'axios'
import { computed, ref } from 'vue'

// --- PROPS Y EMITS ---
const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  needsQrSetup: {
    type: Boolean,
    default: false,
  },
  qrCodeUrl: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'verified'])

// --- ESTADO REACTIVO ---
const code = ref('')
const isLoading = ref(false)
const error = ref('')

// --- LÓGICA DEL COMPONENTE ---

// Sincroniza la visibilidad del diálogo con el padre
const isDialogVisible = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

// Limpia el estado cuando el modal se cierra
const handleClose = () => {
  isDialogVisible.value = false
  code.value = ''
  error.value = ''
  isLoading.value = false
}

// Maneja el envío del código 2FA
const handleSubmit = async () => {
  isLoading.value = true
  error.value = ''

  try {
    // La ruta '/2fa-verify' debe coincidir con tu ruta de Laravel (route('auth.2fa.verify'))
    // Laravel Fortify usa por defecto '/two-factor-challenge'
    await axios.post('/api/two-factor-challenge', {
      code: code.value, // Fortify espera 'code' o 'recovery_code'
    })

    // Si la petición es exitosa (código 204 No Content), significa que la verificación pasó.
    emit('verified') // Notifica al componente padre que la verificación fue exitosa.
    handleClose() // Cierra el modal

  } catch (err) {
    if (err.response && err.response.status === 422) {
      // Error de validación de Laravel
      error.value = err.response.data.errors?.code?.[0] || 'El código proporcionado no es válido.'
    } else {
      // Otros errores
      error.value = 'Ocurrió un error inesperado. Inténtalo de nuevo.'
      console.error('Error en la verificación 2FA:', err)
    }
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <VDialog
    v-model="isDialogVisible"
    max-width="550"
    persistent
  >
    <VCard>
      <VCardItem class="py-4">
        <VCardTitle class="text-h5">
          Verificación de dos factores
        </VCardTitle>
      </VCardItem>

      <VCardText>
        <!-- Mensaje de error -->
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
        >
          {{ error }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow>
            <!-- Lado izquierdo: Instrucciones y Formulario -->
            <VCol
              cols="12"
              :md="props.needsQrSetup ? 6 : 12"
            >
              <p
                v-if="props.needsQrSetup"
                class="mb-4"
              >
                Escanea este código QR con tu aplicación de autenticación y luego ingresa el código generado.
              </p>
              <p v-else>
                Por favor, ingresa el código de verificación de tu aplicación de autenticación.
              </p>

              <AppTextField
                v-model="code"
                autofocus
                label="Código de Verificación"
                placeholder="123456"
                class="mb-4"
              />

              <VBtn
                block
                type="submit"
                :loading="isLoading"
                :disabled="isLoading"
              >
                Verificar
              </VBtn>
            </VCol>

            <!-- Lado derecho: Código QR (si es necesario) -->
            <VCol
              v-if="props.needsQrSetup && props.qrCodeUrl"
              cols="12"
              md="6"
              class="d-flex flex-column align-center justify-center"
            >
              <p class="text-body-2 mb-2">
                Escanear Código
              </p>
              <img
                :src="props.qrCodeUrl"
                alt="Código QR de autenticación"
                style="width: 200px; height: 200px; border: 1px solid #ddd; padding: 5px;"
              >
            </VCol>
          </VRow>
        </VForm>
      </VCardText>

      <VCardActions class="mt-2">
        <VSpacer />
        <VBtn
          color="secondary"
          text
          @click="handleClose"
        >
          Cancelar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
