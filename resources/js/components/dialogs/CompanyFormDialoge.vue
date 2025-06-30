<script setup lang="js">
const props= defineProps({
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  companies: {type: Array, required: true},
  formData: {type: Object, default: () => []},
  formError: {type: Object, default: () => []},
})

const emit= defineEmits(["modalClose", 'save', 'clearErrorForm',"update:busqueda"])

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
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }}</span>
        <VSpacer />
        <VBtn icon variant="text" @click="close">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="formData.name"
              :error-messages="formError.name"
              label="Nombre"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="formData.identification"
              :error-messages="formError.identification"
              label="Identificación"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="formData.type_company"
              :error-messages="formError.type_company"
              label="Tipo"
              variant="outlined"
              :items="['Empresa', 'Clinica']"
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
      </VContainer>
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn color="secondary" variant="outlined" @click="close"
          >Cancelar</VBtn
        >
        <VBtn color="primary" variant="flat" @click="submitForm"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
