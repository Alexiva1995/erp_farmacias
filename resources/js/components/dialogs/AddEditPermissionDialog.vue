<script setup>
const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  permissionName: {
    type: String,
    required: false,
    default: "",
  },
});

const emit = defineEmits(["update:isDialogVisible", "update:permissionName"]);

const currentPermissionName = ref("");

const onReset = () => {
  emit("update:isDialogVisible", false);
  currentPermissionName.value = "";
};

const onSubmit = () => {
  emit("update:isDialogVisible", false);
  emit("update:permissionName", currentPermissionName.value);
};

watch(
  () => props,
  () => {
    currentPermissionName.value = props.permissionName;
  }
);
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 600"
    :model-value="props.isDialogVisible"
    @update:model-value="onReset"
    persistent
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-lock" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.permissionName ? "Editar Permiso" : "Añadir Permiso" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                {{ props.permissionName ? 'Modificación de acceso' : 'Nuevo registro de seguridad' }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="onReset" class="rounded-lg">
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6">
        <VForm @submit.prevent="onSubmit" class="d-flex flex-column gap-6">
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Configuración del Permiso</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VAlert type="warning" variant="tonal" class="mb-6 rounded-lg">
                <template #prepend>
                  <VIcon icon="tabler-alert-triangle" size="24" class="me-2" />
                </template>
                <div class="text-xs font-weight-bold uppercase mb-1">Advertencia del Sistema</div>
                <div class="text-caption">
                  Al editar o añadir el nombre del permiso, podría afectar la funcionalidad de los permisos del sistema.
                </div>
              </VAlert>

              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="currentPermissionName"
                    label="Nombre del Permiso"
                    placeholder="Ej: Gestionar Usuarios"
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12">
                  <VCheckbox 
                    label="Establecer como permiso principal (Core)" 
                    hide-details 
                    density="compact"
                    class="mt-2"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>
        </VForm>
      </VCardText>

      <VCardActions class="pa-4 bg-light border-t">
        <VRow no-gutters class="w-100">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg text-button uppercase"
              @click="onReset"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="50"
              class="font-weight-black rounded-lg shadow-primary text-button uppercase"
              @click="onSubmit"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              {{ props.permissionName ? "Actualizar" : "Crear Permiso" }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.header-gradient {
  background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, #1e5128 100%);
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

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
