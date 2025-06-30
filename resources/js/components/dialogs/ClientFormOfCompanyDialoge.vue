<script setup lang="js">


const props= defineProps({
  status: {type: Object, required: true},
  titulo: {type: String, required: true},
  companies: {type: Array, required: true},
  formData: {type: Object, default: () => []},
  formError: {type: Object, default: () => []},
})

const emit= defineEmits(["modalClose", 'save', 'clearErrorForm',"update:busqueda"])

function close(){
  props.status.statu=false
  props.status.titulo=""
  props.status.company={}
  emit("modalClose")
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
  if(props.formData.id!=null){
    if(props.formData.birthdate=="" || props.formData.birthdate==null){
      data.delete("birthdate")
    }
    else{
      console.log(data.get("birthdate"))
      let fecha=data.get("birthdate")
      data.delete("birthdate")
      fecha=formatearFechaCompleta(fecha)
      console.log(fecha)
      data.set("birthdate",fecha)
    }
  }
  emit("save",data)
}

function formatearFechaCompleta(fechaInput) {
    const fechaSinComillas = String(fechaInput).replace(/"/g, '');
    const fecha = new Date(fechaSinComillas);

    if (isNaN(fecha.getTime())) {
        console.error('Fecha inválida:', fechaInput);
        return null; // o devuelve el input original
    }

    const año = fecha.getUTCFullYear();
    const mes = String(fecha.getUTCMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getUTCDate()).padStart(2, '0');
    return `${año}/${mes}/${dia}`;
}
</script>
<template>
  <VDialog :model-value="props.status.statu" max-width="800px" persistent>
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
            <VSelect
              v-model="formData.identification_type"
              :error-messages="formError.identification_type"
              label="Tipo"
              variant="outlined"
              :items="['V-', 'J-', 'G-', 'E-']"
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
              type="text"
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
