<script setup lang="js">
import { VDateInput } from 'vuetify/labs/VDateInput';

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
      formData.append(key, value);
    } else if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0');
    } else if (typeof value === 'object' && value !== null) {
      formData.append(key, JSON.stringify(value));
    } else if (value !== null && value !== undefined) {
      formData.append(key, value);
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
      let fecha=data.get("birthdate")
      data.delete("birthdate")
      fecha=formatearFechaCompleta(fecha)
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
        return null;
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
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12 rounded-lg">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-3 d-flex align-center shadow-sm">
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="32" class="me-3 elevation-1">
              <VIcon icon="tabler-user-cog" color="primary" size="18" />
            </VAvatar>
            <div>
              <h2 class="text-subtitle-2 font-weight-black text-white leading-tight mb-0 uppercase">{{ props.titulo }}</h2>
              <div class="d-flex align-center gap-1 mt-0">
                <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
                  GESTIÓN DE CLIENTE CORPORATIVO
                </span>
              </div>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="x-small" @click="close">
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 bg-light" style="max-block-size: 80vh;">
        <VRow dense>
          <VCol cols="12" sm="6">
            <VSelect
              v-model="formData.identification_type"
              :error-messages="formError.identification_type"
              label="TIPO IDENTIDAD"
              variant="outlined"
              density="compact"
              :items="['V-', 'J-', 'G-', 'E-']"
              prepend-inner-icon="tabler-id"
              class="premium-input"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.identification"
              :error-messages="formError.identification"
              label="NÚMERO IDENTIFICACIÓN"
              type="text"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-fingerprint"
              class="premium-input"
              :counter="9"
              :maxlength="9"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.name"
              :error-messages="formError.name"
              label="NOMBRE(S)"
              type="text"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-user"
              class="premium-input"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.last_name"
              :error-messages="formError.last_name"
              label="APELLIDO(S)"
              type="text"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-user"
              class="premium-input"
              :disabled="formData.identification_type == 'J-'"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.email"
              :error-messages="formError.email"
              label="CORREO ELECTRÓNICO"
              type="email"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-mail"
              class="premium-input"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="formData.phone"
              :error-messages="formError.phone"
              label="TELÉFONO DE CONTACTO"
              type="text"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-phone"
              class="premium-input"
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
              label="FECHA DE NACIMIENTO"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-calendar"
              class="premium-input"
            />
          </VCol>
          <VCol cols="12">
            <VTextarea
              v-model="formData.address"
              :error-messages="formError.address"
              label="DIRECCIÓN DE HABITACIÓN"
              variant="outlined"
              density="compact"
              prepend-inner-icon="tabler-map-pin"
              class="premium-input"
              rows="3"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 bg-light">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6">
            <VBtn
              color="secondary"
              variant="tonal"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              CANCELAR
            </VBtn>
          </VCol>
          <VCol cols="6">
            <VBtn
              color="primary"
              variant="flat"
              block
              height="48"
              class="font-weight-black rounded-lg text-button uppercase shadow-sm"
              @click="submitForm"
            >
              {{ formData.id ? 'ACTUALIZAR' : 'GUARDAR' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #173b1f 100%);
}

.bg-light {
  background-color: #f8fafc !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.premium-input :deep(.v-field__input) {
  font-size: 0.8rem !important;
  font-weight: 600;
}

.premium-input :deep(.v-label) {
  font-size: 0.7rem !important;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.leading-tight { line-height: 1.25 !important; }

.uppercase { text-transform: uppercase; }

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
  text-transform: uppercase;
}
</style>
