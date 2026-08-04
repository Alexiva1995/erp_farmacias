<script setup>
import { computed } from "vue";
import defaultAvatarImg from "@images/avatars/avatar-1.png";

const props = defineProps({
  employee: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  isAdmin: { type: Boolean, default: false },
  translatedRole: { type: String, default: 'Empleado' },
  avatarDisplaySrc: { type: String, default: '' },
  photoUploading: { type: Boolean, default: false },
  isProfileCollapsed: { type: Boolean, default: true },
  mobile: { type: Boolean, default: false },
  documentLabels: { type: Object, required: true },
});

const emit = defineEmits([
  "trigger-photo-input",
  "trigger-doc-input",
  "download-doc",
  "open-edit",
  "reset-2fa",
  "toggle-collapse",
]);

const initials = computed(() => {
  const name = props.employee.name || "";
  const lastName = props.employee.last_name || "";
  return (name.charAt(0) + lastName.charAt(0)).toUpperCase() || "EM";
});
</script>

<template>
  <VCard class="mb-6 border rounded-lg shadow-sm">
    <VCardText class="pa-6">
      <div class="d-flex flex-column flex-md-row align-start align-md-center justify-space-between gap-4">
        <!-- Avatar y Datos Principales -->
        <div class="d-flex align-center gap-4">
          <div class="position-relative me-2">
            <VAvatar size="72" color="primary" variant="tonal" class="rounded-circle border">
              <VImg v-if="avatarDisplaySrc && avatarDisplaySrc !== defaultAvatarImg" :src="avatarDisplaySrc" cover />
              <span v-else class="text-h5 font-weight-black text-primary">{{ initials }}</span>
            </VAvatar>
            <VBtn
              v-if="canEdit"
              icon="tabler-camera"
              size="24"
              color="primary"
              class="position-absolute rounded-circle"
              style="bottom: -2px; right: -4px; min-width: 26px; min-height: 26px; padding: 0;"
              :loading="photoUploading"
              @click="emit('trigger-photo-input')"
            />
          </div>

          <div class="ps-2">
            <div class="d-flex align-center gap-2">
              <h2 class="text-h6 font-weight-black text-high-emphasis me-2">
                {{ employee.name }} {{ employee.last_name }}
              </h2>
              <VChip size="x-small" color="primary" variant="tonal" class="font-weight-black">
                {{ translatedRole }}
              </VChip>
              <VChip
                size="x-small"
                :color="employee.is_active ? 'success' : 'error'"
                variant="tonal"
                class="font-weight-black"
              >
                {{ employee.is_active ? 'Activo' : 'Inactivo' }}
              </VChip>
            </div>
            <p class="text-caption text-medium-emphasis mt-1 mb-0 d-flex align-center gap-1">
              <span v-if="employee.identification" class="font-weight-bold text-high-emphasis">V-{{ employee.identification }}</span>
              <span v-if="employee.identification && (employee.email || employee.user?.email)">•</span>
              <span>{{ employee.email || employee.user?.email || 'Sin correo' }}</span>
            </p>
          </div>
        </div>

        <!-- Acciones Principales -->
        <div class="d-flex align-center gap-2">
          <VBtn
            v-if="canEdit"
            variant="tonal"
            color="primary"
            size="small"
            prepend-icon="tabler-edit"
            class="font-weight-bold"
            @click="emit('open-edit')"
          >
            Editar Perfil
          </VBtn>
          <VBtn
            v-if="isAdmin"
            variant="tonal"
            color="warning"
            size="small"
            prepend-icon="tabler-key"
            class="font-weight-bold"
            @click="emit('reset-2fa')"
          >
            Reset 2FA
          </VBtn>
        </div>
      </div>

      <!-- Sección de Documentos Expediente Directos -->
      <VDivider class="my-4" />

      <div class="d-flex align-center justify-space-between mb-3 cursor-pointer" @click="emit('toggle-collapse')">
        <span class="text-caption font-weight-black text-uppercase text-medium-emphasis letter-spacing-1">
          <VIcon icon="tabler-file-text" size="18" class="me-1 text-primary" /> Documentos Expediente
        </span>
        <VIcon :icon="isProfileCollapsed ? 'tabler-chevron-down' : 'tabler-chevron-up'" size="18" />
      </div>

      <VExpandTransition>
        <div v-show="!isProfileCollapsed">
          <VRow dense>
            <VCol
              v-for="(label, key) in documentLabels"
              :key="key"
              cols="12"
              sm="6"
              md="3"
            >
              <div class="pa-3 rounded-lg border bg-surface d-flex flex-column gap-2">
                <div class="d-flex align-center justify-space-between">
                  <span class="text-caption font-weight-bold text-high-emphasis">{{ label }}</span>
                  <VIcon
                    :icon="employee[key] ? 'tabler-circle-check-filled' : 'tabler-alert-circle'"
                    :color="employee[key] ? 'success' : 'warning'"
                    size="16"
                  />
                </div>
                
                <div class="d-flex gap-2 mt-1">
                  <!-- Botón único o doble directo de Ver/Descargar y Subir -->
                  <VBtn
                    v-if="employee[key]"
                    size="x-small"
                    color="info"
                    variant="tonal"
                    prepend-icon="tabler-download"
                    class="flex-grow-1 font-weight-bold"
                    @click="emit('download-doc', key)"
                  >
                    Ver / Descargar
                  </VBtn>
                  
                  <VBtn
                    v-if="canEdit"
                    size="x-small"
                    :color="employee[key] ? 'secondary' : 'primary'"
                    :variant="employee[key] ? 'tonal' : 'flat'"
                    :prepend-icon="employee[key] ? 'tabler-refresh' : 'tabler-upload'"
                    :class="employee[key] ? '' : 'flex-grow-1'"
                    class="font-weight-bold"
                    @click="emit('trigger-doc-input', key)"
                  >
                    {{ employee[key] ? '' : 'Subir' }}
                  </VBtn>
                </div>
              </div>
            </VCol>
          </VRow>
        </div>
      </VExpandTransition>
    </VCardText>
  </VCard>
</template>

<style scoped>
.letter-spacing-1 {
  letter-spacing: 0.5px !important;
}
</style>
