<script setup>
// Barra de acciones flotante/sticky inferior para la factura
const props = defineProps({
  isLocationMode: {
    type: Boolean,
    default: false,
  },
  isApprovalMode: {
    type: Boolean,
    default: false,
  },
  isEditableMode: {
    type: Boolean,
    default: false,
  },
  isEditMode: {
    type: Boolean,
    default: false,
  },
  isSaving: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'saveLocations',
  'reject',
  'confirmApproval',
  'cancelEdit',
  'saveProgress',
  'finalize',
  'backToList',
])
</script>

<template>
  <div class="sticky-bottom-actions pa-4 bg-surface elevation-10">
    <VCardActions class="pa-0">
      <!-- Modo Asignación de Ubicación -->
      <div v-if="isLocationMode" class="d-flex w-100">
        <VBtn
          :loading="loading"
          size="large"
          color="primary"
          variant="flat"
          class="w-100 font-weight-bold"
          @click="emit('saveLocations')"
        >
          <VIcon icon="tabler-device-floppy" class="me-2" />
          Guardar Ubicaciones
        </VBtn>
      </div>

      <!-- Modo Aprobación -->
      <VRow v-else-if="isApprovalMode" dense class="w-100 ma-0">
        <VCol cols="12" sm="6" order="2" order-sm="1" class="pa-1">
          <VBtn
            :loading="isSaving"
            size="large"
            color="error"
            variant="outlined"
            block
            class="font-weight-bold rounded-lg"
            @click="emit('reject')"
          >
            <VIcon icon="tabler-arrow-back-up" class="me-2" />
            Rechazar y Devolver
          </VBtn>
        </VCol>
        <VCol cols="12" sm="6" order="1" order-sm="2" class="pa-1">
          <VBtn
            :loading="isSaving"
            size="large"
            color="success"
            variant="flat"
            block
            class="font-weight-bold rounded-lg"
            @click="emit('confirmApproval')"
          >
            <VIcon icon="tabler-check" class="me-2" />
            Confirmar Aprobación
          </VBtn>
        </VCol>
      </VRow>

      <!-- Modo Editable -->
      <div v-else-if="isEditableMode" class="w-100">
        <VRow v-if="isEditMode" dense class="ma-0">
          <VCol cols="6" sm="4" order="2" order-sm="1" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              size="large"
              block
              class="font-weight-bold rounded-lg"
              @click="emit('cancelEdit')"
            >
              <VIcon icon="tabler-x" class="me-1" />
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" sm="4" order="3" order-sm="2" class="pa-1">
            <VBtn
              :loading="loading"
              size="large"
              color="info"
              variant="tonal"
              block
              class="font-weight-bold rounded-lg"
              @click="emit('saveProgress')"
            >
              <VIcon icon="tabler-device-floppy" class="me-1" />
              Guardar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="4" order="1" order-sm="3" class="pa-1">
            <VBtn
              :loading="loading"
              size="large"
              color="success"
              variant="flat"
              block
              class="font-weight-bold rounded-lg"
              @click="emit('finalize')"
            >
              <VIcon icon="tabler-circle-check" class="me-1" />
              Finalizar Factura
            </VBtn>
          </VCol>
        </VRow>
        <template v-else>
          <VBtn
            :loading="loading"
            size="large"
            color="primary"
            variant="flat"
            class="w-100 font-weight-bold"
            @click="emit('finalize')"
          >
            <VIcon icon="tabler-circle-check" class="me-2" />
            Finalizar Factura
          </VBtn>
        </template>
      </div>

      <!-- Modo Solo Lectura -->
      <div v-else class="d-flex w-100">
        <VBtn
          size="large"
          color="primary"
          variant="tonal"
          class="w-100 font-weight-bold"
          @click="emit('backToList')"
        >
          <VIcon icon="tabler-arrow-left" class="me-2" />
          Volver a la Lista
        </VBtn>
      </div>
    </VCardActions>
  </div>
</template>
