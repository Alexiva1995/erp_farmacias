<script setup>
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const loading = ref(false)
const publicToken = ref('')

// Sincronizar el token cuando cambia el proveedor
watch(() => props.selectedSupplier, (newVal) => {
  publicToken.value = newVal?.public_token || ''
}, { deep: true, immediate: true })

const publicUrl = computed(() => {
  if (!publicToken.value) return ''
  const baseUrl = window.location.origin
  return `${baseUrl}/p/suppliers/upload/${publicToken.value}`
})

const generateToken = async () => {
  loading.value = true
  try {
    const response = await axios.post(`/suppliers/${props.selectedSupplier.id}/generate-public-token`)
    
    const newToken = response.data.data
    publicToken.value = newToken

    toast.success('Token generado con éxito')
    emit('refresh')
  } catch (error) {
    toast.error('Error al generar el token')
  } finally {
    loading.value = false
  }
}

const copyToClipboard = () => {
  if (!publicUrl.value) return
  
  navigator.clipboard.writeText(publicUrl.value)
    .then(() => toast.success('Enlace copiado al portapapeles'))
    .catch(() => toast.error('Error al copiar el enlace'))
}

const closeDialog = () => {
  emit('update:isDialogVisible', false)
}
</script>

<template>
  <VDialog
    :model-value="props.isDialogVisible"
    max-width="350"
    @update:model-value="closeDialog"
  >
    <VCard elevation="10" border="sm" class="overflow-hidden">
      <!-- Header -->
      <VCardTitle class="bg-primary text-white d-flex align-center pa-4">
        <VIcon icon="tabler-link" class="me-2" />
        <span>Link de Carga Público</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          density="compact"
          @click="closeDialog"
        />
      </VCardTitle>

      <VCardText class="pa-6 d-flex flex-column align-center gap-4 text-center">
        <!-- Botón de Copiar (Aparece primero si el link ya existe) -->
        <VBtn
          v-if="publicToken"
          color="success"
          size="x-large"
          variant="elevated"
          block
          prepend-icon="tabler-copy"
          @click="copyToClipboard"
          class="font-weight-bold"
        >
          Copiar Link
        </VBtn>

        <!-- Botón de Generar / Regenerar -->
        <VBtn
          :color="publicToken ? 'warning' : 'primary'"
          :size="publicToken ? 'large' : 'x-large'"
          :variant="publicToken ? 'tonal' : 'elevated'"
          block
          :loading="loading"
          :prepend-icon="publicToken ? 'tabler-refresh' : 'tabler-plus'"
          @click="generateToken"
          class="font-weight-bold"
        >
          {{ publicToken ? 'Generar Nuevo Link' : 'Generar Link' }}
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>

