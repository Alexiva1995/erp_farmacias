<script setup>
import { VForm } from "vuetify/components/VForm";

const props = defineProps({
  rolePermissions: {
    type: Object,
    required: false,
    default: () => ({
      name: "",
      permissions: [],
    }),
  },
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(["update:isDialogVisible", "update:rolePermissions"]);

// 👉 Permission List
const permissions = ref([
  {
    name: "User Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Content Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Disputes Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Database Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Financial Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Reporting",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "API Control",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Repository Management",
    read: false,
    write: false,
    create: false,
  },
  {
    name: "Payroll",
    read: false,
    write: false,
    create: false,
  },
]);

const isSelectAll = ref(false);
const role = ref("");
const refPermissionForm = ref();

const checkedCount = computed(() => {
  let counter = 0;
  permissions.value.forEach((permission) => {
    Object.entries(permission).forEach(([key, value]) => {
      if (key !== "name" && value) counter++;
    });
  });

  return counter;
});

const isIndeterminate = computed(
  () =>
    checkedCount.value > 0 && checkedCount.value < permissions.value.length * 3
);

// select all
watch(isSelectAll, (val) => {
  permissions.value = permissions.value.map((permission) => ({
    ...permission,
    read: val,
    write: val,
    create: val,
  }));
});

// if Indeterminate is false, then set isSelectAll to false
watch(isIndeterminate, () => {
  if (!isIndeterminate.value) isSelectAll.value = false;
});

// if all permissions are checked, then set isSelectAll to true
watch(
  permissions,
  () => {
    if (checkedCount.value === permissions.value.length * 3)
      isSelectAll.value = true;
  },
  { deep: true }
);

// if rolePermissions is not empty, then set permissions
watch(
  () => props,
  () => {
    if (props.rolePermissions && props.rolePermissions.permissions.length) {
      role.value = props.rolePermissions.name;
      permissions.value = permissions.value.map((permission) => {
        const rolePermission = props.rolePermissions?.permissions.find(
          (item) => item.name === permission.name
        );
        if (rolePermission) {
          return {
            ...permission,
            ...rolePermission,
          };
        }

        return permission;
      });
    }
  }
);

const onSubmit = () => {
  const rolePermissions = {
    name: role.value,
    permissions: permissions.value,
  };

  emit("update:rolePermissions", rolePermissions);
  emit("update:isDialogVisible", false);
  isSelectAll.value = false;
  refPermissionForm.value?.reset();
};

const onReset = () => {
  emit("update:isDialogVisible", false);
  isSelectAll.value = false;
  refPermissionForm.value?.reset();
};
</script>

<template>
  <VDialog
    :width="$vuetify.display.smAndDown ? 'auto' : 900"
    :model-value="props.isDialogVisible"
    @update:model-value="onReset"
    persistent
  >
    <VCard class="detail-dialog-card overflow-hidden border-0 elevation-12">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="40" class="me-3 elevation-1">
            <VIcon icon="tabler-shield-lock" color="primary" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.rolePermissions.name ? "Editar Rol" : "Añadir Nuevo Rol" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                {{ props.rolePermissions.name ? 'Gestión de privilegios' : 'Configuración de seguridad inicial' }}
              </span>
            </div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" color="white" size="small" @click="onReset" class="rounded-lg">
            <VIcon size="20">tabler-x</VIcon>
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText class="pa-4 pa-sm-6 bg-light d-flex flex-column gap-6 overflow-y-auto" style="max-block-size: 70vh;">
        <VForm ref="refPermissionForm" class="d-flex flex-column gap-6">
          
          <!-- Seccion: Información del Rol -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Identificación del Rol</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol cols="12">
                  <AppTextField
                    v-model="role"
                    label="Nombre del Rol"
                    placeholder="Ej: Administrador, Vendedor..."
                    variant="outlined"
                    density="comfortable"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Seccion: Permisos -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator secondary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Matriz de Permisos</span>
            </div>

            <VCard variant="flat" class="overflow-hidden bg-white rounded-lg elevation-1 border">
              <VTable class="premium-table text-no-wrap">
                <thead>
                  <tr>
                    <th class="text-left">Nombre del Permiso</th>
                    <th class="text-right">
                      <div class="d-flex justify-end align-center gap-2">
                        <VCheckbox
                          v-model="isSelectAll"
                          v-model:indeterminate="isIndeterminate"
                          label="Seleccionar Todo"
                          hide-details
                          density="compact"
                          class="font-weight-black"
                        />
                      </div>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="permission in permissions" :key="permission.name">
                    <tr>
                      <td class="font-weight-bold text-high-emphasis">
                        {{ permission.name }}
                      </td>
                      <td>
                        <div class="d-flex justify-end align-center gap-4">
                          <VCheckbox v-model="permission.read" label="Leer" hide-details density="compact" />
                          <VCheckbox v-model="permission.write" label="Escribir" hide-details density="compact" />
                          <VCheckbox v-model="permission.create" label="Crear" hide-details density="compact" />
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </VTable>
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
              Guardar Rol
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

.bg-light {
  background-color: #f8fafc !important;
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

.premium-table :deep(th) {
  background-color: #f8fafc !important;
  text-transform: uppercase !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.5px !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
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
