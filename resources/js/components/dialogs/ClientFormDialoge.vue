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
              <VIcon icon="tabler-users" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0">{{ props.titulo }}</h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.6rem;">
                Expediente de Cliente
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="close">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light" style="max-block-size: 70vh;">
        <VCard variant="flat" class="border pa-4 bg-white elevation-1 rounded-lg">
          <VRow>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="formData.identification_type"
                :error-messages="formError.identification_type"
                label="Tipo ID"
                variant="outlined"
                density="comfortable"
                :items="['V-', 'J-', 'G-', 'E-']"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.identification"
                :error-messages="formError.identification"
                label="Nº Identificación"
                variant="outlined"
                density="comfortable"
                :counter="9"
                :maxlength="9"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.name"
                :error-messages="formError.name"
                label="Nombre"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.last_name"
                :error-messages="formError.last_name"
                label="Apellido"
                variant="outlined"
                density="comfortable"
                :disabled="formData.identification_type == 'J-'"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.email"
                :error-messages="formError.email"
                label="Correo Electrónico"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VTextField
                v-model="formData.phone"
                :error-messages="formError.phone"
                label="Teléfono"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              v-if="formData.id != null && formData.identification_type != 'J-'"
            >
              <VDateInput
                v-model="formData.birthdate"
                :error-messages="formError.birthdate"
                label="Fecha de Nacimiento"
                variant="outlined"
                density="comfortable"
              />
            </VCol>
            <VCol cols="12" sm="6">
              <VSelect
                v-model="formData.company_id"
                :error-messages="formError.company_id"
                label="Empresa Vinculada"
                variant="outlined"
                density="comfortable"
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
                label="Dirección de Domicilio"
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
            {{ formData.id ? 'ACTUALIZAR FICHA' : 'CREAR CLIENTE' }}
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
</template>
