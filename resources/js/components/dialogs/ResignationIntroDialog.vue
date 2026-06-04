<script setup>
import { useDisplay } from "vuetify";
import { ref, computed } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  employee: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "confirm", "download"]);

const { mobile } = useDisplay();

const closeDialog = () => {
  emit("update:modelValue", false);
};

const hasResignation = computed(() => !!props.employee?.resignation);

const onConfirm = () => {
  emit("confirm", props.employee);
  closeDialog();
};

const onDownload = () => {
  emit("download", props.employee);
  closeDialog();
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    persistent
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    @update:model-value="closeDialog"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card overflow-hidden border-0 elevation-12'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon icon="tabler-file-plus" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ hasResignation ? 'Gestión de Renuncia' : 'Nueva Carta de Renuncia' }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Expediente Digital del Empleado
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            color="white"
            size="small"
            class="rounded-lg ms-3"
            @click="closeDialog"
          >
            <VIcon>tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">
        <div v-if="props.employee" class="d-flex flex-column gap-6">
          
          <!-- Tarjeta del Empleado -->
          <VCard variant="flat" class="pa-4 bg-white rounded-lg elevation-1 border">
            <div class="d-flex align-center gap-4">
              <VAvatar color="primary" variant="tonal" size="54" class="rounded-lg shadow-sm">
                <VImg v-if="props.employee.photo_url" :src="props.employee.photo_url" cover />
                <span v-else class="text-h6 font-weight-black">{{ props.employee.name.charAt(0) }}{{ props.employee.last_name.charAt(0) }}</span>
              </VAvatar>
              <div>
                <div class="text-super-xs font-weight-black text-disabled uppercase mb-1">Empleado Seleccionado</div>
                <div class="text-h6 font-weight-black text-high-emphasis leading-tight">
                  {{ props.employee.name }} {{ props.employee.last_name }}
                </div>
                <div class="d-flex align-center gap-2 mt-1">
                  <span class="text-xs font-weight-bold text-medium-emphasis">{{ props.employee.identification }}</span>
                  <span class="text-xs text-disabled">•</span>
                  <VChip
                    :color="props.employee.is_active ? 'success' : 'error'"
                    size="x-small"
                    variant="tonal"
                    style="font-size: 0.65rem;"
                    class="font-weight-black"
                  >
                    {{ props.employee.is_active ? 'Activo' : 'Inactivo' }}
                  </VChip>
                </div>
              </div>
            </div>
          </VCard>

          <!-- Advertencia si ya existe -->
          <VExpandTransition>
            <div v-if="hasResignation">
              <div class="pa-4 bg-warning-light rounded-lg border-warning-dashed d-flex align-start gap-3">
                <VIcon icon="tabler-alert-triangle" color="warning" size="20" class="mt-1" />
                <div>
                  <div class="text-xs font-weight-black text-warning uppercase mb-1">Renuncia Detectada</div>
                  <div class="text-xs text-warning-darken font-weight-medium">
                    Este empleado ya posee una carta de renuncia generada. Puede editar los datos actuales o descargar el PDF existente.
                  </div>
                </div>
              </div>
            </div>
          </VExpandTransition>

          <!-- Info Alert -->
          <div class="pa-4 bg-info-light rounded-lg border-info-dashed d-flex align-start gap-3">
            <VIcon icon="tabler-info-circle" color="primary" size="20" class="mt-1" />
            <div class="text-xs text-primary font-weight-medium leading-relaxed">
              Se procederá al panel de configuración para gestionar los términos finales del contrato y generar el documento legal correspondiente.
            </div>
          </div>

          <div class="text-center">
            <div class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">¿Qué acción desea realizar?</div>
          </div>
        </div>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t d-flex flex-column gap-2">
        <VRow no-gutters class="w-100">
          <VCol cols="12" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="onConfirm"
            >
              <VIcon :icon="hasResignation ? 'tabler-edit' : 'tabler-plus'" class="me-2" />
              {{ hasResignation ? 'Editar Datos' : 'Crear Carta' }}
            </VBtn>
          </VCol>
          
          <VCol v-if="hasResignation" cols="12" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onDownload"
            >
              <VIcon icon="tabler-download" class="me-2" />
              Descargar PDF
            </VBtn>
          </VCol>

          <VCol cols="12" class="pa-1">
            <VBtn
              color="secondary"
              variant="text"
              block
              class="font-weight-bold"
              @click="closeDialog"
            >
              Cerrar
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
  border-radius: 8px !important;
}

.bg-light {
  background-color: #f8fafc !important;
}

.bg-warning-light {
  background-color: rgba(var(--v-theme-warning), 0.1) !important;
}

.border-warning-dashed {
  border: 1px dashed rgba(var(--v-theme-warning), 0.4) !important;
}

.text-warning-darken {
  color: #b45309 !important;
}

.bg-info-light {
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.border-info-dashed {
  border: 1px dashed rgba(var(--v-theme-primary), 0.3) !important;
}

.shadow-primary {
  box-shadow: 0 4px 14px 0 rgba(var(--v-theme-primary), 0.39) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
