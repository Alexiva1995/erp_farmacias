<script setup>
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Swal from "sweetalert2";
import { ref } from "vue";
import { useDisplay } from "vuetify";
import AppEmptyState from "@/components/AppEmptyState.vue";
import ResignationIntroDialog from "@/components/dialogs/ResignationIntroDialog.vue";

const props = defineProps({
  employees: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const resolveRoleName = (role) => {
  if (!role) return 'Sin Rol';
  
  const map = {
    'Admin': 'Administrador',
    'Supervisor': 'Supervisor',
    'Employee': 'Empleado',
  };
  
  return map[role.name] || role.name;
};

const toTitleCase = (str) => {
  if (!str) return '—';
  return str.toLowerCase().replace(/(?:^|\s|-)\S/g, (char) => char.toUpperCase());
};

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "Empleado", key: "name", sortable: false },
  { title: "Identificación", key: "identification", sortable: false },
  { title: "Estado", key: "is_active", sortable: false, align: 'center' },
  { title: "Acciones", key: "actions", sortable: false, align: 'end' },
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

// UI: Diálogos Premium
const isIntroDialogOpen = ref(false);
const selectedEmployeeForIntro = ref(null);
const confirmGenerateResignation = (employee) => {
  selectedEmployeeForIntro.value = employee;
  isIntroDialogOpen.value = true;
};

const handleIntroConfirm = (employee) => {
  emit("generate-resignation", employee);
};

const handleIntroDownload = (employee) => {
  emit("download-resignation", employee);
};
</script>

<template>
  <div class="employee-table-container">
    <!-- Vista Desktop -->
    <div class="d-none d-md-block">
      <VCard border variant="flat">
        <VDataTableServer
          :headers="headers"
          :items-per-page="props.itemsPerPage"
          :items="props.employees"
          :items-length="props.total"
          :loading="loading"
          :page="props.page"
          density="comfortable"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #no-data>
            <AppEmptyState
              title="No hay empleados"
              message="No se encontraron registros de empleados."
              icon="tabler-users-minus"
            />
          </template>
          <template #item.id="{ item }">
            <span class="font-weight-bold text-primary">{{ item.id }}</span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center gap-3 py-1">
              <VAvatar size="34" :color="item.is_active ? 'primary' : 'secondary'" variant="tonal" class="rounded-lg">
                <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                <span v-else class="text-xs font-weight-bold">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
              </VAvatar>
              <div class="d-flex flex-column">
                <span class="text-sm font-weight-medium text-high-emphasis leading-tight">
                  {{ toTitleCase(item.name + ' ' + item.last_name) }}
                </span>
                <span class="text-super-xs text-medium-emphasis font-weight-medium">
                  {{ resolveRoleName(item.role) }}
                </span>
              </div>
            </div>
          </template>

          <template #item.identification="{ item }">
            <span class="font-weight-semibold text-high-emphasis">{{ item.identification }}</span>
          </template>

          <template #item.is_active="{ item }">
            <VChip
              :color="item.is_active ? 'success' : 'error'"
              size="x-small"
              variant="tonal"
              class="font-weight-bold"
            >
              {{ item.is_active ? 'Activo' : 'Inactivo' }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex justify-end gap-1">
              <IconBtn :href="'/rrhh/employees/' + item.id" color="info" size="small">
                <VIcon icon="tabler-eye" size="18" />
                <VTooltip activator="parent">Ver Ficha</VTooltip>
              </IconBtn>

              <IconBtn 
                v-if="user?.role_id !== 2 && user?.role_id !== 3"
                @click="emit('edit-employee', item)" 
                color="warning" 
                size="small"
              >
                <VIcon icon="tabler-edit" size="18" />
                <VTooltip activator="parent">Editar Información</VTooltip>
              </IconBtn>

              <IconBtn @click="confirmGenerateResignation(item)" color="success" size="small">
                <VIcon icon="tabler-file-text" size="18" />
                <VTooltip activator="parent">Gestión de Renuncia</VTooltip>
              </IconBtn>

              <VMenu location="bottom end" transition="slide-y-transition">
                <template #activator="{ props: menuProps }">
                  <IconBtn v-bind="menuProps" color="secondary" size="small">
                    <VIcon icon="tabler-dots-vertical" size="18" />
                    <VTooltip activator="parent">Más Opciones</VTooltip>
                  </IconBtn>
                </template>
                
                <VList density="compact" class="premium-menu-list py-1">
                  <VListItem
                    v-if="user?.role_id !== 2 && user?.role_id !== 3"
                    prepend-icon="tabler-cancel"
                    title="Despedir Empleado"
                    @click="emit('fire-employee', item)"
                  />
                  <VListItem
                    v-if="user?.role_id !== 3"
                    prepend-icon="tabler-auth-2fa"
                    title="Reiniciar 2FA"
                    @click="emit('reset-2fa', item.id)"
                  />
                  <VDivider v-if="user?.role_id == 1" class="my-1" />
                  <VListItem
                    v-if="user?.role_id == 1"
                    prepend-icon="tabler-trash"
                    title="Eliminar Registro"
                    color="error"
                    class="text-error"
                    @click="emit('delete-employee', item)"
                  />
                </VList>
              </VMenu>
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </div>

    <!-- Vista Móvil (Cards) -->
    <div class="d-block d-md-none pa-2 bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="mb-2" />
      
      <AppEmptyState
        v-if="props.employees.length === 0 && !props.loading"
        title="No hay empleados"
        message="No se encontraron registros de empleados."
        icon="tabler-users-minus"
      />

      <div class="d-flex flex-column gap-3">
        <VCard
          v-for="item in props.employees"
          :key="item.id"
          variant="flat"
          border
          class="mb-1 overflow-hidden premium-card bg-white"
        >
          <div class="pa-4">
            <div class="d-flex justify-space-between align-start mb-3">
              <div class="d-flex align-center gap-3 min-width-0">
                <VAvatar size="42" :color="item.is_active ? 'primary' : 'secondary'" variant="tonal" class="rounded-lg">
                  <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                  <span v-else class="text-sm font-weight-bold">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
                </VAvatar>
                <div class="d-flex flex-column min-width-0">
                  <span class="text-primary font-weight-black text-xs uppercase mb-0.5">ID #{{ item.id }}</span>
                  <h3 class="text-sm font-weight-semibold text-high-emphasis leading-tight truncate">
                    {{ toTitleCase(item.name + ' ' + item.last_name) }}
                  </h3>
                  <div class="d-flex align-center gap-1 mt-0.5">
                    <span class="text-super-xs text-medium-emphasis font-weight-bold">{{ item.identification }}</span>
                    <span class="text-xs text-disabled">•</span>
                    <span class="text-super-xs text-primary font-weight-bold">{{ resolveRoleName(item.role) }}</span>
                  </div>
                </div>
              </div>
              
              <VMenu location="bottom end">
                <template #activator="{ props: menuProps }">
                  <IconBtn v-bind="menuProps" size="x-small">
                    <VIcon icon="tabler-dots-vertical" size="16" />
                  </IconBtn>
                </template>
                <VList density="compact" class="premium-menu-list py-1">
                  <VListItem
                    v-if="user?.role_id !== 2 && user?.role_id !== 3"
                    prepend-icon="tabler-edit"
                    title="Editar Información"
                    @click="emit('edit-employee', item)"
                  />
                  <VListItem
                    v-if="user?.role_id !== 2 && user?.role_id !== 3"
                    prepend-icon="tabler-cancel"
                    title="Despedir Empleado"
                    @click="emit('fire-employee', item)"
                  />
                  <VListItem
                    v-if="user?.role_id !== 3"
                    prepend-icon="tabler-auth-2fa"
                    title="Reiniciar 2FA"
                    @click="emit('reset-2fa', item.id)"
                  />
                  <VDivider v-if="user?.role_id == 1" class="my-1" />
                  <VListItem
                    v-if="user?.role_id == 1"
                    prepend-icon="tabler-trash"
                    title="Eliminar Registro"
                    color="error"
                    class="text-error"
                    @click="emit('delete-employee', item)"
                  />
                </VList>
              </VMenu>
            </div>

            <VDivider class="my-3 border-opacity-10" />

            <div class="d-flex align-center justify-space-between">
              <VChip :color="item.is_active ? 'success' : 'error'" size="x-small" variant="tonal" class="font-weight-bold">
                {{ item.is_active ? 'Activo' : 'Inactivo' }}
              </VChip>
              
              <div class="d-flex gap-1">
                <IconBtn :href="'/rrhh/employees/' + item.id" color="info" size="x-small">
                  <VIcon icon="tabler-eye" size="16" />
                  <VTooltip activator="parent">Ver Ficha</VTooltip>
                </IconBtn>
                
                <IconBtn @click="confirmGenerateResignation(item)" color="success" size="x-small">
                  <VIcon icon="tabler-file-text" size="16" />
                  <VTooltip activator="parent">Gestión de Renuncia</VTooltip>
                </IconBtn>
              </div>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Mobile Pagination -->
      <div class="d-flex justify-center mt-4 pb-2">
        <AppMobilePagination
          :page="props.page"
          :items-per-page="props.itemsPerPage"
          :total-items="props.total"
          :loading="props.loading"
          @change="(options) => emit('update:options', { ...options, sortBy: [], groupBy: [] })"
        />
      </div>
    </div>

    <ResignationIntroDialog
      v-model="isIntroDialogOpen"
      :employee="selectedEmployeeForIntro"
      @confirm="handleIntroConfirm"
      @download="handleIntroDownload"
    />
  </div>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
  line-height: normal;
}

.bg-light {
  background-color: #f8fafc !important;
}

.premium-card {
  border-radius: 12px !important;
  transition: transform 0.2s ease;
}

.premium-card:active {
  transform: scale(0.98);
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.leading-tight {
  line-height: 1.25 !important;
}

.gap-1 { gap: 4px !important; }
.gap-2 { gap: 8px !important; }
.gap-3 { gap: 12px !important; }

.premium-menu-list {
  border-radius: 10px !important;
}
</style>
