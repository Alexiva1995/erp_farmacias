<script setup lang="js">
import { useDisplay } from "vuetify";

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  formData: { type: Object, default: () => ({}) },
  formError: { type: Object, default: () => ({}) },
  specialties: { type: Array, default: () => [] },
});

const emit = defineEmits(["modalClose", "save", "clearErrorForm"]);

const { mobile } = useDisplay();

function close() {
  emit("modalClose", false);
}

function generarFormData(estado) {
  let formData = new FormData();

  Object.entries(estado).forEach(([key, value]) => {
    if (value instanceof File) {
      formData.append(key, value);
    } else if (typeof value === "object" && value !== null) {
      formData.append(key, JSON.stringify(value));
    } else {
      formData.append(key, value);
    }
  });

  return formData;
}

function submitForm() {
  emit("clearErrorForm");
  let data = generarFormData(props.formData);
  emit("save", data);
}
</script>

<template>
  <VDialog
    :model-value="props.modalFormulario"
    max-width="680px"
    width="680px"
    persistent
    scrollable
    :retain-focus="false"
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    class="premium-dialog"
    @click:outside.prevent
    @keydown.esc.prevent="close"
  >
    <VCard v-if="props.modalFormulario" :class="mobile ? 'rounded-0' : 'rounded overflow-hidden border-0 shadow-xl bg-surface'">
      <!-- Cabecera Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 text-primary font-weight-black">
            <VIcon icon="tabler-stethoscope" size="22" />
          </VAvatar>
          <div class="d-flex flex-column leading-none">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.titulo || "Gestión de Especialista" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.05em;">
                Registro y Datos Profesionales
              </span>
            </div>
          </div>

          <VSpacer />
          <VBtn
            icon="tabler-x"
            variant="outlined"
            color="white"
            size="small"
            class="rounded"
            @click="close"
          />
        </div>
      </VCardTitle>

      <!-- Contenido -->
      <VCardText class="pa-4 pa-sm-5 bg-surface">
        <VForm @submit.prevent="submitForm">
          <div class="mb-2">
            <div class="d-flex align-center gap-1-5 mb-3">
              <div class="header-indicator primary" />
              <span class="text-xs font-weight-black text-high-emphasis uppercase letter-spacing-1">Información Profesional</span>
            </div>

            <VRow dense>
              <VCol cols="12" sm="7">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Nombre y Apellido *</span>
                <VTextField
                  v-model="formData.name"
                  placeholder="Ej: Dr. Alejandro Silva"
                  :error="!!formError.name"
                  :error-messages="formError.name"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-bold"
                />
              </VCol>

              <VCol cols="12" sm="5">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Nº Identificación *</span>
                <VTextField
                  v-model="formData.identification"
                  placeholder="Ej: 12345678"
                  :error="!!formError.identification"
                  :error-messages="formError.identification"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  class="rounded font-weight-bold"
                />
              </VCol>

              <VCol cols="12" class="mt-2">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Especialidad Médica *</span>
                <VAutocomplete
                  v-model="formData.specialty_id"
                  :items="props.specialties"
                  item-title="name"
                  item-value="id"
                  placeholder="BUSCAR O SELECCIONAR ESPECIALIDAD..."
                  :error="!!formError.specialty_id"
                  :error-messages="formError.specialty_id"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-award"
                  clearable
                  class="rounded font-weight-bold"
                />
              </VCol>

              <VCol cols="12" class="mt-2">
                <span class="text-super-xs font-weight-bold text-disabled uppercase mb-1 d-block">Dirección de Consultorio / Clínica</span>
                <VTextarea
                  v-model="formData.address"
                  placeholder="Ubicación completa del consultorio o clínica..."
                  rows="2"
                  :error="!!formError.address"
                  :error-messages="formError.address"
                  variant="outlined"
                  density="compact"
                  hide-details="auto"
                  prepend-inner-icon="tabler-map-pin"
                  class="rounded"
                />
              </VCol>
            </VRow>
          </div>
        </VForm>
      </VCardText>

      <VDivider />

      <!-- Footer Premium -->
      <VCardActions class="pa-3 pa-sm-4 bg-surface border-t">
        <VRow dense class="w-100 ma-0">
          <VCol cols="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="outlined"
              height="44"
              block
              class="font-weight-bold rounded-lg text-button uppercase"
              @click="close"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              height="44"
              block
              prepend-icon="tabler-device-floppy"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="submitForm"
            >
              {{ formData.id ? 'Guardar Cambios' : 'Registrar Médico' }}
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
    rgb(var(--v-theme-gradient-end, var(--v-theme-primary))) 100%
  );
}

.header-indicator {
  inline-size: 3px;
  block-size: 14px;
  border-radius: 2px;
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
  letter-spacing: 0.5px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
