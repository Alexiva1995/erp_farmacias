<script setup>
import { computed, ref, watch } from "vue";
import AppTextField from "@core/components/app-form-elements/AppTextField.vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  location: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save"]);

const { mobile } = useDisplay();

const dialogVisible = computed({
  get: () => props.modelValue,
  set: (val) => emit("update:modelValue", val),
});

const localLocation = ref({ name: "" });
const isSaving = ref(false);
const errors = ref({});

const isNew = computed(() => !props.location.id);

watch(
  () => props.location,
  (val) => {
    localLocation.value = { ...val };
    errors.value = {};
  },
  { immediate: true }
);

const handleSave = async () => {
  isSaving.value = true;
  errors.value = {};
  
  try {
    emit("save", { 
      ...localLocation.value, 
      setSaving: (val) => isSaving.value = val, 
      setErrors: (val) => errors.value = val 
    });
  } catch (error) {
    console.error("Error al emitir guardado:", error);
    isSaving.value = false;
  }
};

const close = () => {
  dialogVisible.value = false;
};
</script>

<template>
  <VDialog
    v-model="dialogVisible"
    max-width="600"
    persistent
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
  >
    <VCard :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <!-- Cabecera Premium Estilo ERP -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-6 text-white relative shadow-sm">
          <div class="d-flex align-center gap-4">
            <VAvatar color="white" variant="flat" size="48" rounded="lg" class="elevation-4">
              <VIcon :icon="isNew ? 'tabler-map-pin-plus' : 'tabler-map-pin-edit'" size="28" color="primary" />
            </VAvatar>
            <div class="flex-grow-1">
              <div class="text-overline font-weight-black opacity-70 mb-n1 uppercase">
                Módulo de Inventario
              </div>
              <h3 class="text-h5 font-weight-black line-height-tight uppercase">
                {{ isNew ? 'Registrar Nueva Ubicación' : 'Editar Ubicación' }}
              </h3>
            </div>
            <VBtn
              icon="tabler-x"
              variant="tonal"
              color="white"
              size="small"
              class="rounded-lg opacity-80"
              @click="close"
            />
          </div>
          
          <!-- Decoración de Fondo sutil -->
          <div class="header-decoration"></div>
        </div>
      </VCardTitle>

      <VCardText class="pa-6 bg-light-surface overflow-y-auto">
        <!-- Sección de Información -->
        <div class="mb-6">
          <div class="d-flex align-center gap-2 mb-6">
            <div class="header-indicator primary rounded-pill"></div>
            <span class="text-xs font-weight-black text-uppercase letter-spacing-widest text-primary">Detalles del Espacio Físico</span>
          </div>

          <VCard variant="flat" class="pa-5 rounded-xl border border-dashed bg-white shadow-xs">
            <VRow>
              <VCol cols="12">
                <div class="text-super-xs font-weight-black text-disabled uppercase mb-2 ms-1">Nombre Descriptivo</div>
                <AppTextField
                  v-model="localLocation.name"
                  placeholder="Ej: PASILLO A-01, NEVERA 1, ESTANTE 5"
                  persistent-placeholder
                  :error-messages="errors.name"
                  autofocus
                  class="premium-input-xl"
                  @keyup.enter="handleSave"
                />
              </VCol>
            </VRow>
          </VCard>
        </div>

        <VAlert
          v-if="Object.keys(errors).length > 0 && !errors.name"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-6 rounded-lg font-weight-bold text-xs"
        >
          <template #prepend>
            <VIcon icon="tabler-alert-triangle" />
          </template>
          Por favor, verifique los datos ingresados antes de continuar.
        </VAlert>

        <div class="bg-primary-lighten-5 pa-4 rounded-lg d-flex align-start gap-3 border-s-4 border-primary">
          <VIcon icon="tabler-info-circle" color="primary" size="20" class="mt-1" />
          <div class="text-xs text-primary font-weight-medium">
            Las ubicaciones permiten una trazabilidad precisa de los lotes. Asegúrese de que el nombre sea único y fácil de identificar por el personal de almacén.
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-white border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="12" sm="4" class="pa-1">
            <VBtn
              variant="tonal"
              color="secondary"
              block
              height="48"
              class="rounded-lg font-weight-black text-button uppercase"
              :disabled="isSaving"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="8" class="pa-1">
            <VBtn
              variant="flat"
              color="primary"
              block
              height="48"
              class="rounded-lg font-weight-black shadow-primary-lg text-button uppercase"
              :loading="isSaving"
              @click="handleSave"
            >
              <VIcon start :icon="isNew ? 'tabler-device-floppy' : 'tabler-check'" class="me-2" />
              {{ isNew ? 'Confirmar Registro' : 'Actualizar Datos' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #004d40 100%);
}

.header-indicator {
  inline-size: 4px;
  block-size: 18px;
}

.header-indicator.primary {
  background: linear-gradient(to bottom, rgb(var(--v-theme-primary)), #004d40);
}

.premium-input-xl :deep(.v-field) {
  border-radius: 12px !important;
  transition: all 0.3s ease;
}

.premium-input-xl :deep(.v-field--focused) {
  box-shadow: 0 0 0 4px rgba(var(--v-theme-primary), 0.1) !important;
}

.bg-light-surface {
  background-color: #f8fafc !important;
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.05) !important;
}

.shadow-xs { box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05) !important; }
.shadow-primary-lg { box-shadow: 0 8px 24px rgba(var(--v-theme-primary), 0.25) !important; }

.header-decoration {
  position: absolute;
  block-size: 120px;
  inline-size: 120px;
  filter: blur(40px);
  inset-block-start: -60px;
  inset-inline-end: -40px;
  opacity: 0.2;
  pointer-events: none;
  background: white;
  border-radius: 50%;
}

.line-height-tight {
  line-height: 1.25;
}

.letter-spacing-widest {
  letter-spacing: 0.15em !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: 1;
}

.border-dashed {
  border: 2px dashed rgba(var(--v-border-color), 0.2) !important;
}

.uppercase {
  text-transform: uppercase;
}
</style>
