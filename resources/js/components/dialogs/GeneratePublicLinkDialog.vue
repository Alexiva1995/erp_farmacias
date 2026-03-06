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
watch(() => props.selectedSupplier?.id, (newId, oldId) => {
  if (newId !== oldId) {
    publicToken.value = props.selectedSupplier?.public_token || ''
  }
}, { immediate: true })

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

    // Actualizar el objeto por referencia para que el padre lo vea antes del refresh
    if (props.selectedSupplier) {
      props.selectedSupplier.public_token = newToken
    }

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
    max-width="600"
    @update:model-value="closeDialog"
  >
    <VCard elevation="10" border="sm" class="overflow-hidden">
      <!-- Header -->
      <VCardTitle class="bg-primary text-white d-flex align-center pa-4">
        <VIcon icon="tabler-link" class="me-2" />
        <span>Enlace Público de Carga</span>
        <VSpacer />
        <VBtn
          icon="tabler-x"
          variant="text"
          color="white"
          density="compact"
          @click="closeDialog"
        />
      </VCardTitle>

      <VCardText class="pa-6">
        <div class="mb-6">
          <p class="text-body-1 mb-2">
            Configuración para el proveedor: 
            <strong class="text-primary">{{ props.selectedSupplier?.name }}</strong>
          </p>
          <p class="text-body-2 text-disabled">
            Este enlace permite al proveedor subir sus facturas o listas de precios en formato Excel/CSV sin necesidad de acceder al panel administrativo.
          </p>
        </div>

        <!-- Vista cuando ya hay token -->
        <VScaleTransition>
          <div v-if="publicToken">
            <VLabel class="mb-2 font-weight-medium">URL de Acceso Directo</VLabel>
            <div class="d-flex align-center gap-2">
              <VTextField
                :model-value="publicUrl"
                readonly
                variant="outlined"
                density="compact"
                hide-details
                prepend-inner-icon="tabler-link"
                color="primary"
                class="bg-surface"
              />
              <VBtn
                color="primary"
                variant="elevated"
                @click="copyToClipboard"
                min-width="120"
              >
                <VIcon start size="18">tabler-copy</VIcon>
                Copiar
              </VBtn>
            </div>

            <VAlert
              type="success"
              variant="tonal"
              density="compact"
              class="mt-6"
              border="start"
            >
              <template #prepend>
                <VIcon icon="tabler-info-circle" size="20" />
              </template>
              <div class="text-caption">
                El enlace es permanente a menos que genere uno nuevo, lo cual invalidará el anterior.
              </div>
            </VAlert>
          </div>

          <!-- Vista cuando no hay token -->
          <div v-else class="text-center py-6">
            <VIcon
              icon="tabler-link-off"
              size="64"
              color="disabled"
              class="mb-4"
            />
            <VAlert
              type="info"
              variant="tonal"
              class="mb-6"
              border="start"
            >
              Este proveedor aún no tiene un enlace activo.
            </VAlert>
            <VBtn
              color="primary"
              size="large"
              :loading="loading"
              prepend-icon="tabler-plus"
              @click="generateToken"
              class="px-8"
            >
              Generar Enlace Seguro
            </VBtn>
          </div>
        </VScaleTransition>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VSpacer />
        <VBtn
          color="secondary"
          variant="text"
          @click="closeDialog"
        >
          Cerrar
        </VBtn>
        <VBtn
          v-if="publicToken"
          color="warning"
          variant="tonal"
          :loading="loading"
          @click="generateToken"
        >
          Regenerar Token
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}
</style>

