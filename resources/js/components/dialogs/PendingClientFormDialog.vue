<script setup lang="js">
import { VDateInput } from "vuetify/labs/VDateInput";

const props = defineProps({
  modalFormulario: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  companies: { type: Array, required: true },
  formData: { type: Object, default: () => [] },
  formError: { type: Object, default: () => [] },
});

const emit = defineEmits([
  "modalClose",
  "save",
  "clearErrorForm",
  "update:busqueda",
]);

function close() {
  emit("modalClose", false);
}

function generarFormData(estado) {
  let formData = new FormData();

  Object.entries(estado).forEach(([key, value]) => {
    if (value instanceof File) {
      formData.append(key, value); // Archivo (Blob/File)
    } else if (typeof value === "boolean") {
      formData.append(key, value ? "1" : "0"); // Boolean convertido a string
    } else if (typeof value === "object" && value !== null) {
      formData.append(key, JSON.stringify(value)); // Objetos anidados
    } else if (value !== null && value !== undefined) {
      formData.append(key, value); // Strings/números
    }
  });

  return formData;
}

function submitForm() {
  emit("clearErrorForm");
  let data = generarFormData(props.formData);
  if (props.formData.id != null) {
    // company_id
    if (props.formData.company_id == "" || props.formData.company_id == null) {
      data.delete("company_id");
    }
    // birthdate
    if (props.formData.birthdate == "" || props.formData.birthdate == null) {
      data.delete("birthdate");
    } else {
      let fecha = data.get("birthdate");
      data.delete("birthdate");
      fecha = formatearFechaCompleta(fecha);
      data.set("birthdate", fecha);
    }
  } else {
    if (props.formData.company_id == "" || props.formData.company_id == null) {
      data.delete("company_id");
    }
  }

  if (
    props.formData.phone !== "0" &&
    (props.formData.phone.length > 10 || props.formData.phone.length !== 10)
  ) {
    props.formError.phone =
      "El número de teléfono es incorrecto, coloque 0 o el número correcto";
  }

  emit("save", data);
}

function formatearFechaCompleta(fechaInput) {
  const fechaSinComillas = String(fechaInput).replace(/"/g, "");
  const fecha = new Date(fechaSinComillas);

  if (isNaN(fecha.getTime())) {
    console.error("Fecha inválida:", fechaInput);
    return null; // o devuelve el input original
  }

  const año = fecha.getUTCFullYear();
  const mes = String(fecha.getUTCMonth() + 1).padStart(2, "0");
  const dia = String(fecha.getUTCDate()).padStart(2, "0");
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
              icon="tabler-user-check"
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
                Aprobación y Registro de Cliente • Barrio Sucre
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

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">
        <!-- Información del Cliente -->
        <div class="d-flex align-center gap-2 mb-0">
          <div class="header-indicator primary shadow-sm" />
          <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Información del Cliente</span>
        </div>

        <VCard
          variant="flat"
          class="pa-5 bg-white rounded-xl border shadow-sm"
        >
          <VRow dense>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Tipo Identidad</span>
                <VSelect
                  v-model="formData.identification_type"
                  :error-messages="formError.identification_type"
                  placeholder="V-"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :items="['V-', 'J-', 'G-', 'E-']"
                  disabled
                  prepend-inner-icon="tabler-id"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Número Identificación</span>
                <VTextField
                  v-model="formData.identification"
                  :error-messages="formError.identification"
                  placeholder="000000000"
                  type="text"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  disabled
                  :counter="9"
                  :maxlength="9"
                  prepend-inner-icon="tabler-fingerprint"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Nombre(s)</span>
                <VTextField
                  v-model="formData.name"
                  :error-messages="formError.name"
                  placeholder="EJ: MARIA"
                  type="text"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-user"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Apellido(s)</span>
                <VTextField
                  v-model="formData.last_name"
                  :error-messages="formError.last_name"
                  placeholder="EJ: PEREZ"
                  type="text"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-user"
                  :disabled="formData.identification_type == 'J-'"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Correo Electrónico</span>
                <VTextField
                  v-model="formData.email"
                  :error-messages="formError.email"
                  placeholder="USUARIO@EMAIL.COM"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-mail"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Teléfono de Contacto</span>
                <VTextField
                  v-model="formData.phone"
                  :error-messages="formError.phone"
                  placeholder="04140000000"
                  type="text"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-phone"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
              v-if="formData.id != null && formData.identification_type != 'J-'"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Fecha de Nacimiento</span>
                <VDateInput
                  v-model="formData.birthdate"
                  :error-messages="formError.birthdate"
                  label=""
                  placeholder="DD/MM/AAAA"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-calendar"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <div class="mb-4">
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Empresa Asociada</span>
                <VSelect
                  v-model="formData.company_id"
                  :error-messages="formError.company_id"
                  placeholder="NINGUNA EMPRESA"
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  :items="props.companies"
                  item-title="name"
                  item-value="id"
                  clearable
                  prepend-inner-icon="tabler-building"
                  :disabled="formData.identification_type == 'J-'"
                  class="rounded-lg font-weight-black"
                />
              </div>
            </VCol>
            <VCol cols="12">
              <div>
                <span class="text-super-xs font-weight-black text-disabled uppercase mb-2 d-block">Dirección de Habitación</span>
                <VTextarea
                  v-model="formData.address"
                  :error-messages="formError.address"
                  placeholder="ESTADO, CIUDAD, CALLE..."
                  variant="outlined"
                  density="comfortable"
                  hide-details="auto"
                  prepend-inner-icon="tabler-map-pin"
                  class="rounded-lg font-weight-black"
                  rows="3"
                />
              </div>
            </VCol>
          </VRow>
        </VCard>
      </VCardText>

      <VDivider />

      <!-- Acciones de Modal -->
      <VCardActions class="pa-4 bg-white border-t px-6">
        <VRow
          dense
          class="w-100 ma-0"
        >
          <VCol
            cols="6"
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
            cols="6"
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
                icon="tabler-user-check"
                size="18"
              />
              {{ formData.id ? "Aprobar y Actualizar" : "Registrar" }}
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
    rgb(var(--v-theme-gradient-end)) 100%
  );
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

.italic {
  font-style: italic;
}

.uppercase {
  text-transform: uppercase;
}

.text-button {
  font-size: 0.875rem !important;
  letter-spacing: 1px !important;
}
</style>
