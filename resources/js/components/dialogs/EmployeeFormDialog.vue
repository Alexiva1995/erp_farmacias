<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { computed, ref, watch } from "vue";
import { useDisplay } from "vuetify";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  roles: { type: Array, default: () => [] },
  selectedEmployee: { type: Object, default: null },
  clearDataOnClose: { type: Boolean, default: true },
});

const emit = defineEmits(["update:modelValue", "refresh-table", "close"]);

const { isAdmin } = useAuthStore();
const { mobile } = useDisplay();

const errors = ref({});
const name = ref("");
const lastName = ref("");
const identification = ref("");
const email = ref("");
const password = ref("");
const role = ref(null);
const showPassword = ref(false);
const totalPackageUsd = ref("");

const roleItems = computed(() =>
  props.roles.map((role) => ({
    title:
      role.name === 'Admin'
        ? 'Administrador'
        : role.name === 'Employee'
        ? 'Empleado'
        : 'Supervisor',
    value: Number(role.id),
  }))
);

const closeDialog = () => {
  emit("close");
  emit("update:modelValue", false);

  if (props.clearDataOnClose) {
    handleClearFilters();
  }
};

const handleClearFilters = () => {
  errors.value = {};
  name.value = "";
  lastName.value = "";
  identification.value = "";
  email.value = "";
  password.value = "";
  role.value = null;
  totalPackageUsd.value = "";
};

const submitForm = async () => {
  errors.value = {};

  try {
    const form = new FormData();

    if (props.selectedEmployee != null && isAdmin) {
      form.append("_method", "PUT");
    }

    form.append("name", name.value);
    form.append("last_name", lastName.value);
    form.append("identification", identification.value);
    form.append("email", email.value);
    form.append("role", role.value);

    if (props.selectedEmployee != null && totalPackageUsd.value !== "" && totalPackageUsd.value != null) {
      form.append("total_package_usd", totalPackageUsd.value);
    }

    if (props.selectedEmployee == null) {
      form.append("password", password.value);
    } else if (password.value) {
      form.append("password", password.value);
    }

    if (props.selectedEmployee != null) {
      const { data } = await axios.post(
        `/rrhh/employees/${props.selectedEmployee.id}`,
        form
      );

      if (data.data.status) {
        toast.success("Empleado actualizado exitosamente");

        closeDialog();
        handleClearFilters();
        emit("refresh-table");
      } else {
        toast.error(
          "Hubo un error al actualizar al empleado, verifique e intente de nuevo"
        );
      }
    } else {
      const { data } = await axios.post("/rrhh/employees", form);

      if (data.data.status) {
        toast.success("Empleado registrado exitosamente");

        closeDialog();
        handleClearFilters();
        emit("refresh-table");
      } else {
        toast.error(
          "Hubo un error al registrar al empleado, verifique e intente de nuevo"
        );
      }
    }
  } catch (error) {
    toast.error(
      props.selectedEmployee
        ? "No se pudo actualizar al empleado"
        : "No se pudo registrar al empleado"
    );

    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
    }
  }
};

watch(
  () => props.selectedEmployee,
  (employee) => {
    if (employee) {
      name.value = employee.name;
      lastName.value = employee.last_name;
      identification.value = employee.identification;
      email.value = employee?.user?.email ?? employee.email ?? "";
      const currentRoleId = employee?.user?.role_id ?? employee.role_id ?? employee?.user?.role?.id ?? null;
      role.value = currentRoleId != null ? Number(currentRoleId) : null;
      password.value = "";
      totalPackageUsd.value = employee.total_package_usd != null ? String(employee.total_package_usd) : "";
    }
  },
  { immediate: true }
);
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    @click:outside.prevent
    @keydown.esc.prevent="closeDialog"
  >
    <VCard :class="mobile ? 'rounded-0' : 'detail-dialog-card overflow-hidden border-0 elevation-12'">
      <!-- Header Premium -->
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-4 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon :icon="props.selectedEmployee ? 'tabler-user-edit' : 'tabler-user-plus'" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0">
              {{ props.selectedEmployee != null ? "Editar Empleado" : "Nuevo Empleado" }}
            </h2>
            <div class="d-flex align-center gap-2 mt-1">
              <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold" style="font-size: 0.65rem;">
                Administración de Personal y Roles
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
        <VForm @submit.prevent="submitForm" class="d-flex flex-column gap-6">
          
          <!-- Seccion: Información Personal -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator primary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Datos Personales</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol cols="12" sm="6">
                  <AppTextField
                    v-model="name"
                    label="Nombres"
                    placeholder="Ej: Juan"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.name"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <AppTextField
                    v-model="lastName"
                    label="Apellidos"
                    placeholder="Ej: Pérez"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.last_name"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <AppTextField
                    v-model="identification"
                    label="Identificación"
                    type="number"
                    placeholder="Número de cédula"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.identification"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" sm="6">
                  <AppTextField
                    v-model="email"
                    label="Correo Electrónico"
                    type="email"
                    placeholder="ejemplo@correo.com"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.email"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
              </VRow>
            </VCard>
          </section>

          <!-- Seccion: Accesos y Contrato -->
          <section>
            <div class="d-flex align-center gap-2 mb-4">
              <div class="header-indicator secondary shadow-sm"></div>
              <span class="text-subtitle-2 font-weight-black text-high-emphasis uppercase letter-spacing-1">Seguridad y Finanzas</span>
            </div>

            <VCard variant="flat" class="pa-5 bg-white rounded-lg elevation-1 border">
              <VRow>
                <VCol cols="12" sm="6" v-if="props.selectedEmployee == null || isAdmin">
                  <AppTextField
                    v-model="password"
                    label="Contraseña"
                    placeholder="********"
                    :type="showPassword ? 'text' : 'password'"
                    variant="outlined"
                    density="comfortable"
                    :error-messages="errors.password"
                    :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                    @click:append-inner="showPassword = !showPassword"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                <VCol cols="12" :sm="props.selectedEmployee == null || isAdmin ? 6 : 12">
                  <AppSelect
                    v-model="role"
                    label="Rol de Sistema"
                    placeholder="Seleccionar rol"
                    variant="outlined"
                    density="comfortable"
                    :items="roleItems"
                    :error-messages="errors.role"
                    class="shadow-sm"
                    hide-details="auto"
                  />
                </VCol>
                
                <VCol v-if="props.selectedEmployee != null" cols="12">
                  <div class="mt-2">
                    <AppTextField
                      v-model="totalPackageUsd"
                      label="Paquete Salarial (USD)"
                      type="number"
                      min="0"
                      step="0.01"
                      prefix="$"
                      placeholder="Monto total para nómina"
                      variant="outlined"
                      density="comfortable"
                      :error-messages="errors.total_package_usd"
                      class="shadow-sm"
                      hide-details="auto"
                    />
                    <div class="text-super-xs text-disabled mt-1 px-1">
                      * Monto de referencia para el cálculo de nómina mensual.
                    </div>
                  </div>
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
              @click="closeDialog"
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
              @click="submitForm"
            >
              <VIcon icon="tabler-device-floppy" class="me-2" />
              {{ props.selectedEmployee != null ? 'Actualizar' : 'Registrar' }}
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

.border-t {
  border-block-start: 1px solid rgba(var(--v-border-color), 0.08) !important;
}
</style>
