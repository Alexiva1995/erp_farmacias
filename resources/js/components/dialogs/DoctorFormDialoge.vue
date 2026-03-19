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
      <!-- Cabecera Compacta Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-stethoscope" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">{{ props.titulo }}</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem;">
                Registro de Especialista
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="close">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light" style="max-height: 70vh;">
        <VCard variant="flat" class="border pa-4 bg-white elevation-1 rounded-lg">
          <VRow>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.name"
                :error-messages="formError.name"
                label="Nombre completo"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.identification"
                :error-messages="formError.identification"
                label="Nº Identificación"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12">
              <VTextarea
                v-model="formData.address"
                :error-messages="formError.address"
                label="Dirección de Consultorio"
                variant="outlined"
                density="comfortable"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light border-t">
        <div class="d-flex flex-column flex-sm-row gap-3 w-100">
          <VBtn
            color="secondary"
            variant="tonal"
            size="large"
            block
            height="52"
            class="flex-grow-1 font-weight-black rounded-lg text-button uppercase"
            @click="close"
          >
            CANCELAR
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            size="large"
            block
            height="52"
            class="flex-grow-1 font-weight-black rounded-lg shadow-primary text-button uppercase"
            @click="submitForm"
          >
            <VIcon icon="tabler-device-floppy" class="me-2" />
            {{ formData.id ? 'ACTUALIZAR DATOS' : 'GUARDAR REGISTRO' }}
          </VBtn>
        </div>
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

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.leading-tight { line-height: 1.25 !important; }

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}
</style>
