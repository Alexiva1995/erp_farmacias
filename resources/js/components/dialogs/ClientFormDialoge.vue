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
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
              <VIcon icon="tabler-users" color="primary" size="24" />
            </VAvatar>
            <div>
              <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
                {{ props.titulo || "Gestión de Cliente" }}
              </h2>
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold letter-spacing-1">
                Expediente y datos de contacto
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
      <VCardText class="pa-4 pa-sm-6 bg-light overflow-y-auto" style="max-height: 75vh;">
        <VForm @submit.prevent="submitForm">
          <div class="d-flex flex-column gap-6">
            <!-- Datos de Identificación -->
            <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator primary"></div>
                <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1">Identificación</span>
              </div>

              <VRow dense>
                <VCol cols="12" sm="4">
                  <VSelect
                    v-model="formData.identification_type"
                    label="Tipo ID"
                    placeholder="Tipo"
                    :items="['V-', 'J-', 'G-', 'E-']"
                    :error-messages="formError.identification_type"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12" sm="8">
                  <VTextField
                    v-model="formData.identification"
                    label="Nº de Documento"
                    placeholder="Ej: 12345678"
                    :counter="10"
                    :maxlength="10"
                    :error-messages="formError.identification"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
              </VRow>
            </VCard>

            <!-- Datos Personales -->
            <VCard variant="flat" class="border pa-4 bg-white rounded-lg elevation-1">
              <div class="d-flex align-center gap-2 mb-4">
                <div class="header-indicator secondary"></div>
                <span class="text-xs font-weight-black text-secondary uppercase letter-spacing-1">Información de Contacto</span>
              </div>

              <VRow dense>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="formData.name"
                    label="Nombre / Razón Social"
                    placeholder="Ej: Juan Pérez"
                    :error-messages="formError.name"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="formData.last_name"
                    label="Apellido"
                    placeholder="Ej: García"
                    :disabled="formData.identification_type == 'J-'"
                    :error-messages="formError.last_name"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="formData.email"
                    label="Correo Electrónico"
                    placeholder="usuario@ejemplo.com"
                    type="email"
                    prepend-inner-icon="tabler-mail"
                    :error-messages="formError.email"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField
                    v-model="formData.phone"
                    label="Teléfono"
                    placeholder="Ej: 04121234567"
                    prepend-inner-icon="tabler-phone"
                    :error-messages="formError.phone"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>

                <VCol cols="12" sm="6" v-if="formData.id != null && formData.identification_type != 'J-'">
                  <VDateInput
                    v-model="formData.birthdate"
                    label="Fecha de Nacimiento"
                    :error-messages="formError.birthdate"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                  />
                </VCol>

                <VCol cols="12" :sm="formData.id != null && formData.identification_type != 'J-' ? 6 : 12">
                  <VSelect
                    v-model="formData.company_id"
                    label="Empresa Vinculada"
                    placeholder="Seleccionar empresa"
                    :items="props.companies"
                    item-title="name"
                    item-value="id"
                    clearable
                    :disabled="formData.identification_type == 'J-'"
                    :error-messages="formError.company_id"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="tabler-building-store"
                    class="shadow-sm"
                  />
                </VCol>

                <VCol cols="12">
                  <VTextarea
                    v-model="formData.address"
                    label="Dirección de Domicilio"
                    placeholder="Dirección completa para entregas"
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
              {{ formData.id ? 'Guardar Cambios' : 'Crear Cliente' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: var(--brand-gradient) !important;
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
