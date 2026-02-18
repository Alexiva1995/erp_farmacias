<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { useAuthStore } from "@/stores/auth";
import { watch } from "vue";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  roles: { type: Array, default: () => [] },
  selectedEmployee: { type: Object, default: null },
  clearDataOnClose: { type: Boolean, default: true },
});

const emit = defineEmits(["update:modelValue", "refresh-table", "close"]);

const { isAdmin } = useAuthStore();

const errors = ref({});
const name = ref("");
const lastName = ref("");
const identification = ref("");
const email = ref("");
const password = ref("");
const role = ref(null);

const closeDialog = () => {
  emit("close");

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

    if (props.selectedEmployee == null) {
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

    if (error.response.status === 422) {
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
      email.value = employee.email;
      role.value = employee.role_id;
      password.value = "";
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
    :retain-focus="false"
    @click:outside.prevent
    @keydown.esc.prevent="closeDialog"
  >
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-5 bg-primary">
        <div class="d-flex align-center gap-3">
          <VIcon icon="tabler-user-plus" size="24" color="white" />
          <span class="text-h6 text-white">
            {{ props.selectedEmployee != null ? "Editar" : "Nuevo" }} empleado
          </span>
        </div>
        <VBtn icon variant="text" color="white" size="small" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <VRow>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="name"
              label="Nombres"
              type="text"
              variant="outlined"
              :error-messages="errors.name"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="lastName"
              label="Apellidos"
              type="text"
              variant="outlined"
              :error-messages="errors.last_name"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="identification"
              label="Cédula"
              type="number"
              variant="outlined"
              :error-messages="errors.identification"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VTextField
              v-model="email"
              label="Correo"
              type="email"
              variant="outlined"
              :error-messages="errors.email"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6" v-if="selectedEmployee == null || isAdmin">
            <VTextField
              v-model="password"
              label="Contraseña"
              type="password"
              variant="outlined"
              :error-messages="errors.password"
            />
          </VCol>
          <VCol cols="12" sm="6" md="6">
            <VSelect
              v-model="role"
              label="Rol"
              variant="outlined"
              :items="
                roles.map((role) => ({
                  title:
                    role.name === 'Admin'
                      ? 'Administrador'
                      : role.name === 'Employee'
                      ? 'Empleado'
                      : 'Supervisor',
                  value: role.id,
                }))
              "
              :error-messages="errors.role"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />

      <VCardActions class="pa-4 px-5">
        <VRow class="w-100 ma-0">
          <VCol cols="6" class="pa-2">
            <VBtn
              color="secondary"
              variant="outlined"
              prepend-icon="tabler-x"
              block
              @click="closeDialog"
            >
              Cancelar
            </VBtn>
          </VCol>
          <VCol cols="6" class="pa-2">
            <VBtn
              color="primary"
              variant="flat"
              prepend-icon="tabler-check"
              block
              @click="submitForm"
            >
              {{ props.selectedEmployee != null ? 'Actualizar' : 'Guardar' }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
