<script setup>
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Swal from "sweetalert2";

const props = defineProps({
  employees: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const headers = [
  { title: "ID", key: "id", sortable: false },
  { title: "Nombre", key: "name", sortable: false },
  { title: "Apellido", key: "last_name", sortable: false },
  { title: "Identificación", key: "identification", sortable: false },
  { title: "Correo", key: "email", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const emit = defineEmits([
  "update:options",
  "fire-employee",
  "edit-employee",
  "delete-employee",
  "generate-resignation",
  "reset-2fa",
]);

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

const confirmGenerateResignation = async (employee) => {
  try {
    const confirmed = await Swal.fire({
      title: "¿Generar carta de renuncia?",
      html: `
        <div class="text-left">
          <p><strong>Empleado:</strong> ${employee.name} ${
        employee.last_name
      }</p>
          <p><strong>Identificación:</strong> ${employee.identification}</p>
          <p><strong>Correo:</strong> ${employee.email}</p>
          <p><strong>Estado:</strong> ${
            employee.is_active ? "Activo" : "Inactivo"
          }</p>
        </div>
        <div class="alert alert-info mt-3" style="background-color: transparent; border: 2px solid #17a2b8; padding: 10px; border-radius: 5px; color: #17a2b8;">
          <strong>ℹ️ Información:</strong> Se abrirá un formulario para completar los datos de la carta de renuncia.
        </div>
        <p class="mt-3"><strong>¿Desea generar una carta de renuncia para este empleado?</strong></p>
      `,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#ff9800",
      cancelButtonColor: "#6c757d",
      confirmButtonText: "Sí, generar carta",
      cancelButtonText: "Cancelar",
      width: "600px",
    });

    if (confirmed.isConfirmed) {
      emit("generate-resignation", employee);
    }
  } catch (error) {
    console.error("Error in confirmation dialog:", error);
  }
};
</script>
<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.employees"
      :items-length="props.total"
      :loading="loading"
      :loading-text="'Cargando empleados...'"
      :page="props.page"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.actions="{ item }">
        <VTooltip
          text="Editar empleado"
          location="top"
          v-if="user?.role_id !== 2 && user?.role_id !== 3"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('edit-employee', item)">
              <VIcon icon="tabler-pencil" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          text="Ver empleado"
          location="top"
          v-if="user?.role_id !== 2 && user?.role_id !== 3"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" icon :href="'/rrhh/employees/' + item.id">
              <VIcon icon="tabler-eye" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Generar Renuncia" location="top">
          <template #activator="{ props }">
            <IconBtn
              v-bind="props"
              @click="confirmGenerateResignation(item)"
              color="warning"
            >
              <VIcon icon="tabler-file-text" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          text="Despedir empleado"
          location="top"
          v-if="user?.role_id !== 2 && user?.role_id !== 3"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('fire-employee', item)">
              <VIcon icon="tabler-cancel" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          text="Reiniciar autenticación"
          location="top"
          v-if="user?.role_id !== 3"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('reset-2fa', item.id)">
              <VIcon icon="tabler-auth-2fa" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip text="Eliminar empleado" location="top">
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('delete-employee', item.id)">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
