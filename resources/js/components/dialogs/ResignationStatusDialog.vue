<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  employee: {
    type: Object,
    default: null,
  },
  newStatus: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue", "confirm", "cancel"]);

const handleClose = () => {
  if (!props.loading) {
    emit("update:modelValue", false);
    emit("cancel");
  }
};

const handleConfirm = () => {
  emit("confirm");
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="500px"
    persistent
    @update:model-value="handleClose"
  >
    <VCard class="detail-dialog-card rounded-lg overflow-hidden border-0 elevation-12">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-2">
            <VIcon
              :icon="props.newStatus ? 'tabler-user-check' : 'tabler-user-x'"
              :color="props.newStatus ? 'success' : 'error'"
              size="22"
            />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.newStatus ? "Activar Empleado" : "Desactivar Empleado" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-0.5">
              <span class="text-super-xs text-white opacity-75 font-weight-bold">
                Gestión de Estado de Personal
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
            @click="handleClose"
            :disabled="props.loading"
          >
            <VIcon size="18">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-5 bg-light">
        <div class="bg-white pa-4 rounded-lg border">
          <div class="d-flex align-center gap-2 mb-3">
            <div :class="['header-indicator shadow-sm', props.newStatus ? 'success' : 'error']"></div>
            <span class="text-caption font-weight-bold text-high-emphasis">Confirmación</span>
          </div>

          <div class="text-sm font-weight-bold text-high-emphasis mb-2">
            Empleado: <span class="text-primary">{{ props.employee?.name }}</span>
          </div>

          <p class="text-xs text-medium-emphasis mb-3">
            {{
              props.newStatus
                ? "¿Está seguro de que desea activar a este empleado en el sistema?"
                : "¿Está seguro de que desea desactivar a este empleado en el sistema?"
            }}
          </p>

          <VAlert
            :color="props.newStatus ? 'success' : 'error'"
            variant="tonal"
            density="compact"
            class="rounded-lg"
          >
            <template #prepend>
              <VIcon :icon="props.newStatus ? 'tabler-eye' : 'tabler-eye-off'" size="20" />
            </template>
            <div class="text-xs font-weight-bold">
              {{
                props.newStatus
                  ? "El empleado volverá a aparecer activo y podrá operar normalmente en el sistema."
                  : "El empleado figurará como inactivo hasta que vuelva a ser reactivado."
              }}
            </div>
          </VAlert>
        </div>
      </VCardText>

      <VCardActions class="pa-3 bg-light border-t">
        <VRow no-gutters class="w-100 gap-2 justify-end">
          <VCol cols="auto">
            <VBtn
              color="secondary"
              variant="outlined"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-4 text-none"
              :disabled="props.loading"
              @click="handleClose"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="auto">
            <VBtn
              :color="props.newStatus ? 'success' : 'error'"
              variant="flat"
              size="default"
              height="38"
              class="font-weight-bold rounded-lg px-5 text-none"
              :loading="props.loading"
              :disabled="props.loading"
              @click="handleConfirm"
            >
              {{ props.newStatus ? "Confirmar Activación" : "Confirmar Desactivación" }}
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

.header-indicator.success {
  background-color: rgb(var(--v-theme-success));
}

.header-indicator.error {
  background-color: rgb(var(--v-theme-error));
}

.bg-light {
  background-color: #f8fafc !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
