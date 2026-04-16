<script setup>
import { useAuthStore } from "@/stores/auth";
import { storeToRefs } from "pinia";
import Swal from "sweetalert2";
import { ref } from "vue";
import { useDisplay } from "vuetify";
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

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "id", sortable: true },
  { title: "EMPLEADO", key: "name", sortable: false },
  { title: "IDENTIFICACIÓN", key: "identification", sortable: false },
  { title: "ESTADO", key: "is_active", sortable: false, align: 'center' },
  { title: "ACCIONES", key: "actions", sortable: false, align: 'end' },
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
  <VCard class="employee-table-card border-0 shadow-none bg-transparent">
    <!-- Vista Desktop -->
    <VDataTableServer
      v-if="!mobile"
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.employees"
      :items-length="props.total"
      :loading="loading"
      :loading-text="'Cargando empleados...'"
      :page="props.page"
      class="rounded-lg border shadow-sm overflow-hidden premium-table"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.id="{ item }">
        <span class="font-weight-black text-primary">{{ item.id }}</span>
      </template>

      <template #item.name="{ item }">
        <div class="d-flex align-center gap-3 py-1">
          <VAvatar size="34" :color="item.is_active ? 'primary' : 'secondary'" variant="tonal" class="rounded-lg">
            <VImg v-if="item.photo_url" :src="item.photo_url" cover />
            <span v-else class="text-xs font-weight-bold">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-black text-high-emphasis leading-tight uppercase">{{ item.name }} {{ item.last_name }}</span>
            <span class="text-super-xs text-medium-emphasis uppercase font-weight-medium letter-spacing-05">{{ resolveRoleName(item.role) }}</span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-sm font-weight-black text-high-emphasis">{{ item.identification }}</span>
      </template>



      <template #item.is_active="{ item }">
        <VChip
          :color="item.is_active ? 'success' : 'error'"
          size="x-small"
          variant="tonal"
          class="font-weight-black"
        >
          {{ item.is_active ? 'ACTIVO' : 'INACTIVO' }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex justify-end gap-1">
          <VTooltip text="Ver Ficha" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" :href="'/rrhh/employees/' + item.id" color="info" variant="tonal" size="small">
                <VIcon icon="tabler-eye" size="18" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Editar Información" location="top">
            <template #activator="{ props }">
              <IconBtn 
                v-if="user?.role_id !== 2 && user?.role_id !== 3"
                v-bind="props" 
                @click="emit('edit-employee', item)" 
                color="warning" 
                variant="tonal" 
                size="small"
              >
                <VIcon icon="tabler-edit" size="18" />
              </IconBtn>
            </template>
          </VTooltip>

          <VTooltip text="Gestión de Renuncia" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="confirmGenerateResignation(item)" color="success" variant="tonal" size="small">
                <VIcon icon="tabler-file-text" size="18" />
              </IconBtn>
            </template>
          </VTooltip>

          <VMenu location="bottom end" transition="slide-y-transition">
            <template #activator="{ props }">
              <IconBtn v-bind="props" variant="tonal" color="secondary" size="small">
                <VIcon icon="tabler-dots-vertical" size="18" />
              </IconBtn>
            </template>
            
            <VList density="comfortable" class="premium-menu-list py-1">
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

    <!-- Vista Móvil (Cards) -->
    <div v-else class="mobile-employee-list">
      <div v-if="loading" class="text-center pa-8">
        <VProgressCircular indeterminate color="primary" />
      </div>
      
      <div v-else-if="props.employees.length === 0" class="text-center pa-8 text-disabled font-weight-bold">
        No se encontraron empleados
      </div>

      <div v-else class="d-flex flex-column gap-4">
        <VCard
          v-for="item in props.employees"
          :key="item.id"
          class="employee-mobile-card rounded-lg border-0"
        >
          <VCardText class="pa-4">
            <div class="d-flex align-center justify-space-between mb-4">
              <div class="d-flex align-center gap-3">
                <VAvatar size="48" :color="item.is_active ? 'primary' : 'secondary'" variant="tonal" class="rounded-lg">
                  <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                  <span v-else class="text-h6 font-weight-black">{{ item.name.charAt(0) }}{{ item.last_name.charAt(0) }}</span>
                </VAvatar>
                  <div class="d-flex flex-column">
                    <h3 class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight">
                      <span class="text-primary text-xs">{{ item.id }}</span>
                      <span class="mx-1 text-disabled">|</span>
                      {{ item.name }} {{ item.last_name }}
                    </h3>
                    <div class="d-flex align-center gap-1">
                      <span class="text-super-xs text-medium-emphasis uppercase font-weight-bold">{{ item.identification }}</span>
                      <span class="text-xs text-disabled">•</span>
                      <span class="text-super-xs text-primary uppercase font-weight-black">{{ resolveRoleName(item.role) }}</span>
                    </div>
                  </div>
              </div>
              
              <VMenu location="bottom end">
                <template #activator="{ props }">
                  <IconBtn v-bind="props" variant="text" color="secondary" size="small">
                    <VIcon icon="tabler-dots-vertical" size="18" />
                  </IconBtn>
                </template>
                <VList density="comfortable" class="premium-menu-list py-1">
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



            <div class="d-flex align-center justify-space-between mt-2">
              <VChip :color="item.is_active ? 'success' : 'error'" size="x-small" variant="flat" class="font-weight-black">
                {{ item.is_active ? 'ACTIVO' : 'INACTIVO' }}
              </VChip>
              
              <div class="d-flex gap-2">
                <VTooltip text="Ver Ficha" location="top">
                  <template #activator="{ props }">
                    <IconBtn v-bind="props" :href="'/rrhh/employees/' + item.id" color="info" variant="tonal" size="small" class="rounded-lg">
                      <VIcon icon="tabler-eye" size="18" />
                    </IconBtn>
                  </template>
                </VTooltip>
                
                <VTooltip text="Gestión de Renuncia" location="top">
                  <template #activator="{ props }">
                    <IconBtn v-bind="props" @click="confirmGenerateResignation(item)" color="success" variant="tonal" size="small" class="rounded-lg">
                      <VIcon icon="tabler-file-text" size="18" />
                    </IconBtn>
                  </template>
                </VTooltip>
              </div>
            </div>
          </VCardText>
        </VCard>
      </div>
    </div>
    <ResignationIntroDialog
      v-model="isIntroDialogOpen"
      :employee="selectedEmployeeForIntro"
      @confirm="handleIntroConfirm"
      @download="handleIntroDownload"
    />
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: white !important;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  letter-spacing: 0.05rem !important;
  text-transform: uppercase !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.1) !important;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  color: #334155 !important;
}

.employee-mobile-card {
  border: 1px solid rgba(var(--v-border-color), 0.05) !important;
  background: white !important;
  box-shadow: 0 4px 12px rgba(var(--v-shadow-key-umbra-color), 0.03) !important;
  border-radius: 8px !important;
}

.border-dashed {
  border-block-end: 1px dashed rgba(var(--v-border-color), 0.1) !important;
}

.leading-tight { line-height: 1.25 !important; }
.font-weight-950 { font-weight: 950 !important; }
.text-super-xs { font-size: 0.65rem !important; }
.letter-spacing-05 { letter-spacing: 0.5px !important; }

.premium-menu-list {
  border: 1px solid rgba(var(--v-border-color), 0.08) !important;
  border-radius: 12px !important;
}
</style>
