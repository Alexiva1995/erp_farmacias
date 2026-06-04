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
    max-width="460"
    @update:model-value="closeDialog"
  >
    <VCard class="detail-dialog-card overflow-hidden">
      <!-- Header Premium Institucional -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-link" color="primary" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none text-white">
            <h2 class="text-h6 font-weight-black leading-tight mb-0 uppercase text-white">
              Link de Carga Público
            </h2>
            <span class="text-super-xs opacity-75 font-weight-bold uppercase letter-spacing-1">
              {{ props.selectedSupplier?.name ?? 'Proveedor' }}
            </span>
          </div>
          <VSpacer />
          <VBtn icon="tabler-x" variant="tonal" color="white" size="small" class="rounded-lg" @click="closeDialog" />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-light">

        <!-- URL Preview (si existe) -->
        <template v-if="publicToken">
          <div class="d-flex align-center gap-2 mb-3">
            <div class="header-indicator primary shadow-sm" />
            <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Enlace Generado</span>
          </div>

          <VCard variant="flat" class="pa-3 bg-white rounded-xl border shadow-sm mb-4">
            <div class="d-flex align-center gap-2 mb-1">
              <VIcon icon="tabler-world" size="14" color="success" />
              <span class="text-super-xs font-weight-black text-disabled uppercase letter-spacing-1">URL Pública</span>
            </div>
            <div class="url-preview text-xs font-weight-black text-primary pa-2 rounded-lg">
              {{ publicUrl }}
            </div>
          </VCard>
        </template>

        <template v-else>
          <VAlert type="info" variant="tonal" density="compact" icon="tabler-info-circle" class="rounded-xl">
            <span class="text-super-xs font-weight-black">
              Genera un enlace público para que el proveedor <strong>{{ props.selectedSupplier?.name }}</strong> pueda cargar sus productos directamente.
            </span>
          </VAlert>
        </template>

        <!-- Acciones -->
        <div class="d-flex flex-column gap-3 mt-4">
          <VBtn
            v-if="publicToken"
            color="success"
            variant="flat"
            height="50"
            block
            class="font-weight-black rounded-lg uppercase"
            @click="copyToClipboard"
          >
            <VIcon start icon="tabler-copy" size="18" />
            Copiar Enlace
          </VBtn>

          <VBtn
            :color="publicToken ? 'warning' : 'primary'"
            :variant="publicToken ? 'tonal' : 'flat'"
            height="50"
            block
            :loading="loading"
            class="font-weight-black rounded-lg uppercase"
            :class="!publicToken ? 'shadow-primary' : ''"
            @click="generateToken"
          >
            <VIcon start :icon="publicToken ? 'tabler-refresh' : 'tabler-plus'" size="18" />
            {{ publicToken ? 'Generar Nuevo Link' : 'Generar Link' }}
          </VBtn>

          <VBtn
            color="secondary"
            variant="tonal"
            height="44"
            block
            class="font-weight-black rounded-lg uppercase"
            @click="closeDialog"
          >
            Cerrar
          </VBtn>
        </div>

      </VCardText>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary { background-color: rgb(var(--v-theme-primary)); }

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 { letter-spacing: 1px !important; }
.leading-none { line-height: 1 !important; }
.leading-tight { line-height: 1.25 !important; }

.url-preview {
  background-color: rgba(var(--v-theme-primary), 0.05);
  border: 1px dashed rgba(var(--v-theme-primary), 0.25);
  word-break: break-all;
  line-height: 1.5;
}
</style>
