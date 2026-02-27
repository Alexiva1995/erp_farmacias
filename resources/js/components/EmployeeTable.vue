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
  "download-resignation",
  "edit-resignation",
  "reset-2fa",
]);

const authStore = useAuthStore();
const { user } = storeToRefs(authStore);

const confirmGenerateResignation = async (employee) => {
  try {
    const hasResignation = !!employee.resignation;
    
    const swalOptions = {
      title: `<span style="font-size: 1.5rem; font-weight: 700; color: var(--v-theme-on-surface, #334155);">${hasResignation ? 'Gestión de Renuncia' : 'Nueva Carta de Renuncia'}</span>`,
      html: `
        <div style="text-align: left; font-family: 'Inter', sans-serif; color: var(--v-theme-on-surface, #475569);">
          <div style="background: rgba(var(--v-theme-on-surface), 0.03); border-radius: 12px; padding: 16px; margin-bottom: 20px; border: 1px solid rgba(var(--v-theme-on-surface), 0.1);">
             <div style="display: flex; align-items: center; margin-bottom: 16px;">
                <div style="background: #6366f1; color: white; border-radius: 10px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; margin-right: 12px; box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div>
                  <div style="font-size: 0.75rem; color: var(--v-theme-on-surface-variant, #64748b); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px;">Empleado Seleccionado</div>
                  <div style="font-size: 1.125rem; font-weight: 700; color: var(--v-theme-on-surface, #1e293b);">${employee.name} ${employee.last_name}</div>
                </div>
             </div>
             
             <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: rgba(var(--v-theme-surface), 1); padding: 12px; border-radius: 10px; border: 1px solid rgba(var(--v-theme-on-surface), 0.05);">
                  <div style="font-size: 0.65rem; color: var(--v-theme-on-surface-variant, #94a3b8); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Identificación</div>
                  <div style="font-size: 0.9rem; font-weight: 600; color: var(--v-theme-on-surface, #475569);">${employee.identification}</div>
                </div>
                <div style="background: rgba(var(--v-theme-surface), 1); padding: 12px; border-radius: 10px; border: 1px solid rgba(var(--v-theme-on-surface), 0.05);">
                  <div style="font-size: 0.65rem; color: var(--v-theme-on-surface-variant, #94a3b8); font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Estado Actual</div>
                  <div style="font-size: 0.9rem; font-weight: 700; color: ${employee.is_active ? '#10b981' : '#ef4444'}; display: flex; align-items: center; gap: 6px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: ${employee.is_active ? '#10b981' : '#ef4444'};"></span>
                    ${employee.is_active ? 'Activo' : 'Inactivo'}
                  </div>
                </div>
             </div>
          </div>

          ${hasResignation ? `
            <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <svg style="color: #f59e0b; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                <div style="color: #b45309; font-size: 0.875rem; font-weight: 600;">
                  Este registro ya posee una carta de renuncia generada.
                </div>
              </div>
            </div>
          ` : ''}

          <div style="background: rgba(59, 130, 246, 0.08); border-radius: 12px; padding: 14px; display: flex; align-items: flex-start; gap: 12px; border: 1px dashed rgba(59, 130, 246, 0.3);">
            <svg style="color: #3b82f6; margin-top: 2px; flex-shrink: 0;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <div style="font-size: 0.825rem; color: #1e40af; line-height: 1.5; font-weight: 500;">
              Se procederá al panel de configuración para gestionar los términos finales del contrato.
            </div>
          </div>
          
          <div style="margin-top: 28px; text-align: center; color: var(--v-theme-on-surface-variant, #64748b); font-size: 0.95rem; font-weight: 500;">
            ¿Qué acción desea realizar?
          </div>
        </div>
      `,
      showCancelButton: true,
      showDenyButton: hasResignation,
      confirmButtonColor: "#6366f1", // Color indigo principal
      denyButtonColor: "#334155",   // Color slate para secundario
      cancelButtonColor: "transparent",
      confirmButtonText: `<div style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> <span>${hasResignation ? 'Editar Datos' : 'Crear Carta'}</span></div>`,
      denyButtonText: `<div style="display: flex; align-items: center; gap: 8px;"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> <span>Descargar PDF</span></div>`,
      cancelButtonText: `<span style="color: var(--v-theme-on-surface-variant, #64748b); font-weight: 600;">Cancelar</span>`,
      width: "550px",
      background: 'var(--v-theme-surface, #ffffff)',
      color: 'var(--v-theme-on-surface, #334155)',
      padding: '2rem',
      customClass: {
        popup: 'premium-swal-popup',
        confirmButton: 'premium-swal-confirm',
        denyButton: 'premium-swal-deny',
        cancelButton: 'premium-swal-cancel'
      },
      buttonsStyling: true,
    };

    const result = await Swal.fire(swalOptions);

    if (result.isConfirmed) {
      emit("generate-resignation", employee);
    } else if (result.isDenied) {
      emit("download-resignation", employee);
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
            <IconBtn v-bind="props" @click="emit('edit-employee', item)" color="warning">
              <VIcon icon="tabler-edit" />
            </IconBtn>
          </template>
        </VTooltip>
        <VTooltip
          text="Ver empleado"
          location="top"
          v-if="user?.role_id !== 2 && user?.role_id !== 3"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" icon :href="'/rrhh/employees/' + item.id" color="info">
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
        <VTooltip
          text="Eliminar empleado"
          location="top"
          v-if="user?.role_id == 1"
        >
          <template #activator="{ props }">
            <IconBtn v-bind="props" @click="emit('delete-employee', item)" color="error">
              <VIcon icon="tabler-trash" />
            </IconBtn>
          </template>
        </VTooltip>
      </template>
    </VDataTableServer>
  </VCard>
</template>
