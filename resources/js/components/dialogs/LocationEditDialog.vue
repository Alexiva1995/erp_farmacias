<script setup>
import { computed, ref, watch } from "vue";
import AppTextField from "@core/components/app-form-elements/AppTextField.vue";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  location: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "save"]);

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
    emit("save", { ...localLocation.value, setSaving: (val) => isSaving.value = val, setErrors: (val) => errors.value = val });
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
    max-width="500"
    persistent
    class="location-edit-dialog"
  >
    <VCard class="rounded-xl overflow-hidden border-0 shadow-premium">
      <!-- Cabecera Premium -->
      <div class="header-gradient pa-6 text-white relative">
        <div class="d-flex align-center gap-4">
          <VAvatar color="white" variant="tonal" size="56" rounded="lg" class="glass-avatar">
            <VIcon :icon="isNew ? 'tabler-map-pin-plus' : 'tabler-map-pin-edit'" size="32" color="white" />
          </VAvatar>
          <div class="flex-grow-1">
            <div class="text-overline font-weight-black opacity-70 mb-n1">
              {{ isNew ? 'Nueva' : 'Editar' }} Ubicación
            </div>
            <h3 class="text-h5 font-weight-black line-height-tight">
              {{ isNew ? 'Registrar Espacio' : localLocation.name }}
            </h3>
          </div>
          <VBtn
            icon="tabler-x"
            variant="text"
            color="white"
            size="small"
            class="mt-n8 me-n2 opacity-80"
            @click="close"
          />
        </div>
        
        <!-- Decoración -->
        <div class="header-decoration"></div>
      </div>

      <VCardText class="pa-6 bg-surface">
        <div class="mb-6">
          <div class="d-flex align-center gap-2 mb-4">
            <div class="header-indicator primary rounded-pill"></div>
            <span class="text-subtitle-2 font-weight-black text-uppercase letter-spacing-widest text-primary">Información General</span>
          </div>

          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="localLocation.name"
                label="Nombre de la Ubicación"
                placeholder="Ej: Pasillo A-01, Nevera 1, Estante 5"
                persistent-placeholder
                :error-messages="errors.name"
                autofocus
                @keyup.enter="handleSave"
              />
            </VCol>
          </VRow>
        </div>

        <VAlert
          v-if="Object.keys(errors).length > 0 && !errors.name"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-6 rounded-lg"
        >
          Ocurrió un error al procesar la solicitud.
        </VAlert>

        <div class="text-caption text-disabled d-flex align-center gap-1 mb-2">
          <VIcon icon="tabler-info-circle" size="14" />
          <span>Las ubicaciones se utilizan para organizar los lotes en el inventario.</span>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 bg-light-surface">
        <VSpacer />
        <VBtn
          variant="text"
          color="secondary"
          class="rounded-lg font-weight-bold px-6"
          :disabled="isSaving"
          @click="close"
        >
          Cancelar
        </VBtn>
        <VBtn
          variant="flat"
          color="primary"
          class="rounded-lg font-weight-bold px-8 shadow-sm"
          :loading="isSaving"
          @click="handleSave"
        >
          {{ isNew ? 'Crear Ubicación' : 'Guardar Cambios' }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.shadow-premium {
  box-shadow: 0 20px 50px 0 rgba(0, 0, 0, 15%) !important;
}

.header-gradient {
  background: linear-gradient(135deg, #0561e2 0%, #0037a5 100%);
}

.glass-avatar {
  border: 1px solid rgba(255, 255, 255, 20%);
  backdrop-filter: blur(4px);
  background: rgba(255, 255, 255, 15%) !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
}

.header-indicator.primary {
  background: linear-gradient(to bottom, #0561e2, #0037a5);
}

.letter-spacing-widest {
  letter-spacing: 0.1em !important;
}

.line-height-tight {
  line-height: 1.2;
}

.bg-light-surface {
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.header-decoration {
  position: absolute;
  block-size: 150px;
  inline-size: 150px;
  filter: blur(40px);
  inset-block-start: -70px;
  inset-inline-end: -50px;
  opacity: 0.3;
  pointer-events: none;
}
</style>
