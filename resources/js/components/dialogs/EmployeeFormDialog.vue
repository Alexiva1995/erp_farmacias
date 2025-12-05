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

    if (props.selectedEmployee != null || isAdmin) {
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
    max-width="800px"
    persistent
    @update:model-value="closeDialog"
    :scrollable="true"
    content-class="d-flex"
  >
    <VCard>
      <VCardTitle class="d-flex align-center">
        <span class="headline">
          {{ props.selectedEmployee != null ? "Editar" : "Nuevo" }} empleado
        </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeDialog">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider />
      <VContainer>
        <VRow>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="name"
              label="Nombres"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.name"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="lastName"
              label="Apellidos"
              type="text"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.last_name"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="identification"
              label="Cédula"
              type="number"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.identification"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VTextField
              v-model="email"
              label="Correo"
              type="email"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.email"
            />
          </VCol>
          <VCol cols="12" sm="6" v-if="selectedEmployee == null || isAdmin">
            <VTextField
              v-model="password"
              label="Contraseña"
              type="password"
              variant="outlined"
              hide-details="auto"
              :error-messages="errors.password"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <VSelect
              v-model="role"
              label="Rol"
              variant="outlined"
              hide-details="auto"
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
      </VContainer>
      <VDivider />
      <VCardActions class="pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeDialog"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="submitForm"
          width="100%"
          class="flex-grow-1 w-0 mr-4"
        >
          Guardar Cambios
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
