<script setup>
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { computed, ref } from 'vue'

const props = defineProps({
  isDialogVisible: { type: Boolean, required: true },
  selectedSupplier: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:isDialogVisible', 'refresh'])

const loading = ref(false)
const publicToken = ref(props.selectedSupplier?.public_token || '')

const publicUrl = computed(() => {
  if (!publicToken.value) return ''
  const baseUrl = window.location.origin
  return `${baseUrl}/p/suppliers/upload/${publicToken.value}`
})

const generateToken = async () => {
  loading.value = true
  try {
    const response = await axios.post(`/suppliers/${props.selectedSupplier.id}/generate-public-token`)
    
    publicToken.value = response.data.data
    toast.success('Token generado con éxito')
    emit('refresh')
  } catch (error) {
    toast.error('Error al generar el token')
  } finally {
    loading.value = false
  }
}

// Alternativa: Si el token ya viene en el objeto 'selectedSupplier'
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
    max-width="600"
    @update:model-value="closeDialog"
  >
    <VCard title="Link Público para Proveedor">
      <VCardText>
        <p class="mb-4">
          Proporcione este enlace al proveedor <strong>{{ props.selectedSupplier?.name }}</strong> para que pueda subir su lista de precios sin necesidad de una cuenta.
        </p>

        <div v-if="props.selectedSupplier?.public_token" class="d-flex align-center gap-2">
          <VTextField
            :model-value="publicUrl"
            readonly
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="tabler-link"
          />
          <VBtn color="primary" @click="copyToClipboard">
            <VIcon start>tabler-copy</VIcon>
            Copiar
          </VBtn>
        </div>

        <div v-else class="text-center py-4">
          <VAlert type="info" variant="tonal" class="mb-4">
            Este proveedor aún no tiene un enlace público generado.
          </VAlert>
          <VBtn color="primary" :loading="loading" @click="generateToken">
            Generar Nuevo Enlace
          </VBtn>
        </div>
      </VCardText>

      <VCardText class="d-flex justify-end flex-wrap gap-3">
        <VBtn
          color="secondary"
          variant="tonal"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
      </VCardText>
    </VCard>
  </VDialog>
</template>
