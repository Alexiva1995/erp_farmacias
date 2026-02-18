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
    @click:outside.prevent
    @keydown.esc.prevent="close"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-stethoscope" size="24" color="white" />
          <span class="text-h6 text-white">{{ props.titulo }}</span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="formData.name"
              :error-messages="formError.name"
              label="Nombre"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="formData.identification"
              :error-messages="formError.identification"
              label="Identificación"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="formData.address"
              :error-messages="formError.address"
              label="Dirección"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="submitForm"
            >
              {{ formData.id ? 'Actualizar' : 'Guardar' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
