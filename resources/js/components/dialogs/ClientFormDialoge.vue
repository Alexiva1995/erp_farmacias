<script setup lang="js">
import { VDateInput } from 'vuetify/labs/VDateInput';

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
    } else if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0'); // Boolean convertido a string
    } else if (typeof value === 'object' && value !== null) {
      formData.append(key, JSON.stringify(value)); // Objetos anidados
    } else if (value !== null && value !== undefined) {
      formData.append(key, value); // Strings/números
    }
  });

  return formData
}

function submitForm(){
  emit("clearErrorForm")
  let data=generarFormData(props.formData)
  if(props.formData.id!=null){
    // company_id
    if(props.formData.company_id=="" || props.formData.company_id==null){
      console.log("borrar empresa")
      data.delete("company_id")
    }
    // birthdate
    if(props.formData.birthdate=="" || props.formData.birthdate==null){
      console.log("borrar birthdate")
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
  else{
    if(props.formData.company_id=="" || props.formData.company_id==null){
      console.log("borrar empresa")
      data.delete("company_id")
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
              :counter="9"
              :maxlength="9"
              :rules="[
                (v) => (v && v.length >= 7) || 'Mínimo 7 caracteres',
                (v) => (v && v.length <= 9) || 'Máximo 9 caracteres',
              ]"
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
              :disabled="formData.identification_type == 'J-'"
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
          <VCol
            cols="12"
            sm="6"
            md="6"
            lg="6"
            v-if="formData.id != null && formData.identification_type != 'J-'"
          >
            <VDateInput
              v-model="formData.birthdate"
              :error-messages="formError.birthdate"
              label="Fecha de Nacimiento"
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
              clearable
              :disabled="formData.identification_type == 'J-'"
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
          <!-- Nuevo campo SPE -->
          <VCol cols="12">
            <div class="d-flex align-center mt-3 mb-2">
              <VCheckbox v-model="formData.is_spe" color="warning" class="me-2">
                <template #label>
                  <div class="d-flex align-center">
                    <VIcon icon="tabler-shield-check" class="me-2" size="20" />
                    <span class="text-subtitle-1 font-weight-medium">
                      ¿Es sujeto a pasivos especiales?
                    </span>
                  </div>
                </template>
              </VCheckbox>
              <VChip
                v-if="formData.is_spe"
                color="warning"
                size="small"
                class="ms-2"
              >
                <VIcon icon="tabler-shield-check" size="14" class="me-1" />
                SPE: -75% IVA
              </VChip>
            </div>
            <div
              v-if="formData.is_spe"
              class="bg-warning-lighten-4 pa-3 rounded mb-3"
            >
              <div class="text-caption text-warning-darken-2">
                <VIcon icon="tabler-info-circle" class="me-1" size="14" />
                Este cliente será marcado como sujeto a pasivos especiales. En
                sus compras se restara automáticamente el 75% del IVA.
              </div>
            </div>
          </VCol>
        </VRow>
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="close"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Cancelar</VBtn
        >
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
          >Guardar Cambios</VBtn
        >
      </VCardActions>
    </VCard>
  </VDialog>
</template>
