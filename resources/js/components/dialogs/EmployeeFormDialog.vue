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
    max-width="700px"
    persistent
    scrollable
    :fullscreen="mobile"
    transition="dialog-bottom-transition"
    @click:outside.prevent
    @keydown.esc.prevent="closeDialog"
  >
    <VCard :class="mobile ? 'rounded-0' : 'rounded-xl overflow-hidden border-0 elevation-24'">
      <VCardTitle class="pa-0">
        <div class="header-gradient pa-5 d-flex align-center shadow-sm">
          <VAvatar color="white" variant="flat" size="44" class="me-4 elevation-2">
            <VIcon :icon="props.selectedEmployee ? 'tabler-user-edit' : 'tabler-user-plus'" color="primary" size="26" />
          </VAvatar>
          <div class="flex-grow-1">
            <h2 class="text-h6 font-weight-black text-white leading-tight mb-0 uppercase">
              {{ props.selectedEmployee != null ? "Editar" : "Nuevo" }} Empleado
            </h2>
            <span class="text-super-xs text-white opacity-75 uppercase font-weight-bold">
              Configuración de datos y accesos al sistema
            </span>
          </div>
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

      <VCardText class="pa-6 pa-md-8 bg-light">
        <VRow>
          <VCol cols="12" sm="6">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Nombres</span>
            <AppTextField
              v-model="name"
              placeholder="Ingresar nombres"
              :error-messages="errors.name"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Apellidos</span>
            <AppTextField
              v-model="lastName"
              placeholder="Ingresar apellidos"
              :error-messages="errors.last_name"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Identificación</span>
            <AppTextField
              v-model="identification"
              type="number"
              placeholder="Número de cédula"
              :error-messages="errors.identification"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Correo Electrónico</span>
            <AppTextField
              v-model="email"
              type="email"
              placeholder="ejemplo@correo.com"
              :error-messages="errors.email"
            />
          </VCol>
          <VCol cols="12" sm="6" v-if="props.selectedEmployee == null || isAdmin">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Contraseña</span>
            <AppTextField
              v-model="password"
              placeholder="********"
              :type="showPassword ? 'text' : 'password'"
              :error-messages="errors.password"
              :append-inner-icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
              @click:append-inner="showPassword = !showPassword"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <span class="text-super-xs font-weight-black text-primary uppercase letter-spacing-1 mb-1 d-block">Rol de Sistema</span>
            <VSelect
              v-model="role"
              placeholder="Seleccionar rol"
              variant="outlined"
              density="comfortable"
              :items="roleItems"
              :error-messages="errors.role"
            />
          </VCol>
          <VCol v-if="props.selectedEmployee != null" cols="12">
            <VCard variant="flat" class="pa-4 rounded-xl border border-dashed bg-white shadow-xs">
              <div class="d-flex align-center mb-2">
                <VIcon icon="tabler-coin" color="primary" class="me-2" size="20" />
                <span class="text-xs font-weight-black text-high-emphasis uppercase">Paquete Salarial (Referencia)</span>
              </div>
              <AppTextField
                v-model="totalPackageUsd"
                type="number"
                min="0"
                step="0.01"
                prefix="$"
                placeholder="Monto total en USD para nómina"
                :error-messages="errors.total_package_usd"
              />
            </VCard>
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-6 px-md-8 bg-white">
        <VRow class="w-100 ma-0">
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg"
              @click="closeDialog"
            >
              CANCELAR
            </VBtn>
          </VCol>
          <VCol cols="12" sm="6" class="pa-1">
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              block
              height="48"
              class="font-weight-black rounded-lg shadow-primary-sm"
              @click="submitForm"
            >
              {{ props.selectedEmployee != null ? 'ACTUALIZAR' : 'GUARDAR' }}
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

.shadow-xs {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 10%) !important;
}

.shadow-sm {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 5%) !important;
}

.shadow-primary-sm {
  box-shadow: 0 4px 12px rgba(var(--v-theme-primary), 25%) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
}

.letter-spacing-1 {
  letter-spacing: 1px !important;
}

.border-dashed {
  border-width: 2px !important;
  border-style: dashed !important;
}

.uppercase {
  text-transform: uppercase !important;
}

.leading-tight {
  line-height: 1.25 !important;
}
</style>
