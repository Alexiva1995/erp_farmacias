<script setup lang="js">
const props= defineProps({
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  formData: {type: Object, default: () => []},
  formError: {type: Object, default: () => []},
})

const emit= defineEmits(["modalClose", 'save', 'clearErrorForm'])

function close(){
  emit("modalClose",false)
}

function generarFormData(estado){

  let formData = new FormData();

  Object.entries(estado).forEach(([key, value]) => {
    if (value instanceof File) {
      formData.append(key, value); // Archivo (Blob/File)
    } else if (typeof value === 'object' && value !== null) {
      formData.append(key, JSON.stringify(value)); // Objetos anidados
    } else {
      formData.append(key, value); // Strings/números
    }
  });

  return formData
}

function submitForm(){
  emit("clearErrorForm")
  let data=generarFormData(props.formData)
  emit("save",data)
}
</script>

<template>
  <VDialog
    :model-value="props.modalFormulario"
    max-width="700px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="$vuetify.display.xs"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="close"
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-stethoscope" color="primary" size="24" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                {{ props.titulo || "Gestión de Especialista" }}
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
                Registro y datos profesionales
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            @click="close"
            class="rounded-lg"
          >
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VDivider />

      <!-- Contenido -->
      <VCardText class="pa-4 pa-sm-6 bg-light overflow-y-auto" style="max-height: 70vh;">
        <VForm @submit.prevent="submitForm">
          <div class="d-flex flex-column gap-6">
            <!-- Datos del Médico -->
            <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator primary"></div>
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Información Profesional</span>
              </div>

              <VRow dense>
                <VCol cols="12" sm="7">
                  <VTextField
                    v-model="formData.name"
                    label="Nombre y Apellido"
                    placeholder="Ej: Dr. Alejandro Silva"
                    :error-messages="formError.name"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12" sm="5">
                  <VTextField
                    v-model="formData.identification"
                    label="Nº Identificación"
                    placeholder="Ej: 12345678"
                    :error-messages="formError.identification"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12">
                  <VTextarea
                    v-model="formData.address"
                    label="Dirección de Consultorio / Clínica"
                    placeholder="Ubicación completa del consultorio"
                    rows="2"
                    :error-messages="formError.address"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-map-pin"
                    class="shadow-sm"
                  />
                </VCol>
              </VRow>
            </VCard>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <!-- Footer Premium -->
      <VCardActions class="pa-4 bg-light border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon icon="tabler-device-floppy" size="18" class="me-2" />
              {{ formData.id ? 'Guardar Cambios' : 'Registrar Médico' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.detail-dialog-card {
  border-radius: 12px !important;
}

.header-indicator {
  inline-size: 4px;
  block-size: 16px;
  border-radius: 10px;
}

.header-indicator.primary {
  background-color: rgb(var(--v-theme-primary));
}

.header-indicator.secondary {
  background-color: rgb(var(--v-theme-secondary));
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.leading-tight {
  line-height: 1.25 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}
</style>
