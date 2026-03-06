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
            <VCard variant="flat" border class="pa-4 mb-4 bg-primary-light">
              <div class="d-flex align-center mb-3">
                <VIcon icon="tabler-copy" color="primary" class="me-2" />
                <span class="text-subtitle-2 font-weight-bold text-primary">Enlace de acceso rápido</span>
              </div>
              
              <div class="d-flex align-center gap-2">
                <VTextField
                  :model-value="publicUrl"
                  readonly
                  variant="outlined"
                  bg-color="surface"
                  density="comfortable"
                  hide-details
                  prepend-inner-icon="tabler-world-www"
                  color="primary"
                  class="flex-grow-1"
                />
                
                <VBtn
                  color="primary"
                  variant="elevated"
                  size="large"
                  @click="copyToClipboard"
                  class="font-weight-bold"
                >
                  <VIcon start size="18">tabler-copy</VIcon>
                  Copiar Link
                </VBtn>
              </div>
            </VCard>

            <VAlert
              type="success"
              variant="tonal"
              density="compact"
              border="start"
              class="mt-2"
            >
              <template #prepend>
                <VIcon icon="tabler-circle-check" size="20" />
              </template>
              <div class="text-caption">
                Este enlace es seguro y exclusivo para <strong>{{ props.selectedSupplier?.name }}</strong>. 
                Utilízalo para recibir sus archivos directamente en el sistema.
              </div>
            </VAlert>
          </div>

          <!-- Vista cuando no hay token -->
          <div v-else class="text-center py-6">
            <div class="mb-4">
              <VIcon
                icon="tabler-link-off"
                size="64"
                color="disabled"
                alpha="0.3"
              />
            </div>
            
            <VAlert
              type="info"
              variant="tonal"
              class="mb-6 mx-auto"
              style="max-inline-size: 400px;"
              border="start"
            >
              No se ha generado ningún enlace de carga para este proveedor todavía.
            </VAlert>
            
            <VBtn
              color="primary"
              size="large"
              :loading="loading"
              prepend-icon="tabler-plus"
              @click="generateToken"
              class="px-8 font-weight-bold elevation-4"
              rounded="lg"
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
          color="success"
          variant="elevated"
          prepend-icon="tabler-copy"
          @click="copyToClipboard"
          class="font-weight-bold"
        >
          Copiar Enlace Público
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.02);
}

.bg-primary-light {
  background-color: rgba(var(--v-theme-primary), 0.05);
}
</style>

