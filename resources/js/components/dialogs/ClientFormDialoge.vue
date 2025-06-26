<script setup lang="js">

import { VDateInput } from 'vuetify/labs/VDateInput'

const props= defineProps({
  modalFormulario: {type: Boolean, required: true},
  titulo: {type: String, required: true},
  companies: {type: Array, required: true},
  formData: {type: Object, default: () => []},
  formError: {type: Object, default: () => []},
})

const emit= defineEmits(["modalClose"])

function close(){
  emit("modalClose",false)
}


function submitForm(){
  console.log("enviar datos")
}
</script>
<template>
  <VDialog :model-value="props.modalFormulario" max-width="800px" persistent>
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">{{ props.titulo }} {{ formData.name }}</span>
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
              v-model="formData.last_name"
              :error-messages="formError.last_name"
              label="Apellido"
              type="text"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="formData.email"
              :error-messages="formError.email"
              label="Correo"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="formData.phone"
              :error-messages="formError.phone"
              label="Telefono"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VTextField
              v-model="formData.identification_type"
              :error-messages="formError.identification_type"
              label="Identificación"
              type="number"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6" v-if="formData.id != null">
            <VDateInput
              v-model="formData.birthdate"
              :error-messages="formError.birthdate"
              label="Fecha de Nacimiento"
              type="date"
              variant="outlined"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" lg="6">
            <VSelect
              v-model="formData.company_id"
              :error-messages="formError.company_id"
              label="Empresa"
              variant="outlined"
              :items="props.companies"
              item-title="name"
              item-value="id"
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
