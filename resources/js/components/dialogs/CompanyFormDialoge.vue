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
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card rounded-xl border-0 shadow-xl overflow-hidden bg-surface'">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar
            color="white"
            variant="flat"
            size="40"
            class="me-3 elevation-1"
          >
            <VIcon
              icon="tabler-building"
              size="24"
              color="primary"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ props.titulo }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span
                class="text-white opacity-75 uppercase font-weight-bold"
                style="font-size: 0.6rem; letter-spacing: 0.05em;"
              >
                Registro de Entidad Institucional • Barrio Sucre
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg"
            @click="close"
          />
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-4">
        <!-- Información Institucional -->
        <div class="d-flex align-center gap-2 mb-2">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Institucional</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm"
        >
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-2">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Razón Social</span>
                <VTextField
                  v-model="formData.name"
                  :error-messages="formError.name"
                  placeholder="NOMBRE DE LA EMPRESA"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-building"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-2">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Identificación Fiscal</span>
                <VTextField
                  v-model="formData.identification"
                  :error-messages="formError.identification"
                  placeholder="RIF / J-00000000-0"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-fingerprint"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-2">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Tipo de Organización</span>
                <VSelect
                  v-model="formData.type_company"
                  :error-messages="formError.type_company"
                  placeholder="SELECCIONAR TIPO"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :items="['Empresa', 'Clinica']"
                  prepend-inner-icon="tabler-category"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol cols="12">
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Dirección Fiscal Completa</span>
                <VTextarea
                  v-model="formData.address"
                  :error-messages="formError.address"
                  placeholder="UBICACIÓN Y DETALLES DE CONTACTO"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-map-pin"
                  class="rounded-lg font-weight-black"
                  rows="2"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>

        <!-- Mensaje de Soporte -->
        <div class="mt-4 pa-4 rounded-xl bg-primary bg-opacity-10 border-dashed-2 d-flex align-center gap-4">
          <VAvatar
            color="primary"
            variant="tonal"
            size="40"
            class="rounded-lg"
          >
            <VIcon
              icon="tabler-shield-check"
              size="24"
            />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <span class="text-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1">Verificación de Datos</span>
            <p class="text-super-xs text-medium-emphasis mb-0 leading-tight">
              Asegúrese de que el RIF coincida con los documentos legales para evitar discrepancias en la facturación fiscal.
            </p>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="secondary"
              variant="tonal"
              height="50"
              block
              class="font-weight-black rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol
            cols="12"
            sm="6"
            class="pa-1"
          >
            <VBtn
              color="primary"
              variant="flat"
              height="50"
              block
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              <VIcon
                start
                icon="tabler-device-floppy"
                size="18"
              />
              {{ formData.id ? 'Actualizar Datos' : 'Registrar Empresa' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(
    135deg,
    rgb(var(--v-theme-primary)) 0%,
    #1e5128 100%
  );
}

.bg-light {
  background-color: #f8faff !important;
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

.leading-none {
  line-height: 1 !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.border-dashed-2 {
  border: 1px dashed rgba(var(--v-border-color), 0.3) !important;
}

.italic {
  font-style: italic;
}
</style>
