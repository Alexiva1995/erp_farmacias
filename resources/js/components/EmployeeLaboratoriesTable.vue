<script setup>
import { useDisplay } from "vuetify";
import { useAbility } from "@casl/vue";

const { can } = useAbility();

const props = defineProps({
  employeeLaboratories: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalRecords: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
});

const emit = defineEmits([
  "update:options",
  "edit-assignment",
  "delete-assignment",
]);

const { mobile } = useDisplay();

const headers = [
  { title: "ID", key: "employee_id", sortable: true },
  { title: "EMPLEADO", key: "employee_name", sortable: true, width: "25%" },
  { title: "IDENTIFICACIÓN", key: "identification", sortable: false },
  { title: "LABORATORIOS ASIGNADOS", key: "laboratories", sortable: false, width: "35%", align: "center" },
  { title: "TOTAL", key: "laboratories_count", sortable: true, align: "center" },
  { title: "ACCIONES", key: "actions", sortable: false, align: "center" },
];

const getInitials = (name) => {
  if (!name) return "N/A";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .substring(0, 2);
};

const getAvatarColor = (index) => {
  const colors = ["primary", "secondary", "success", "info", "warning", "error"];
  return colors[index % colors.length];
};
</script>

<template>
  <VCard class="border shadow-sm overflow-hidden">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers.filter(h => h.key !== 'actions' || can('manage', 'admin'))"
      :items="props.employeeLaboratories"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="premium-table text-no-wrap"
      density="compact"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.employee_id="{ item }">
        <span class="font-weight-black text-primary tabular-nums text-xs uppercase">{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3 py-2">
          <VAvatar :color="!item.photo_url ? getAvatarColor(item.employee_id) : undefined" size="34" variant="tonal" class="rounded">
            <VImg v-if="item.photo_url" :src="item.photo_url" cover />
            <span v-else class="text-super-xs font-weight-black">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-sm font-weight-black text-high-emphasis leading-tight text-uppercase">
              {{ item.employee_name }}
            </span>
            <span class="text-super-xs text-disabled mt-1 font-weight-black uppercase">
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-xs text-medium-emphasis font-weight-black tabular-nums">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.laboratories="{ item }">
        <div class="d-flex flex-wrap justify-center gap-1 max-w-ch-40 mx-auto">
          <VChip
            v-for="lab in item.laboratories"
            :key="lab.id"
            size="x-small"
            color="primary"
            variant="tonal"
            class="rounded font-weight-black text-uppercase"
          >
            {{ lab.name }}
          </VChip>
          <span v-if="item.laboratories.length === 0" class="text-super-xs text-disabled font-weight-black uppercase italic">Sin laboratorios</span>
        </div>
      </template>

      <template #item.laboratories_count="{ item }">
        <VChip
          :color="item.laboratories_count > 0 ? 'success' : 'surface-variant'"
          size="x-small"
          variant="tonal"
          class="font-weight-black rounded px-3 text-uppercase"
          style="min-inline-size: 36px;"
        >
          {{ item.laboratories_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1 justify-center" v-if="can('manage', 'admin')">
          <IconBtn
            size="small"
            color="primary"
            variant="tonal"
            class="rounded"
            @click="emit('edit-assignment', item)"
          >
            <VIcon icon="tabler-edit" size="18" />
          </IconBtn>
          
          <VMenu v-if="item.laboratories.length > 0">
            <template #activator="{ props: menuProps }">
              <IconBtn
                v-bind="menuProps"
                size="small"
                color="error"
                variant="tonal"
                class="rounded"
              >
                <VIcon icon="tabler-trash" size="18" />
              </IconBtn>
            </template>
            <VList density="compact" class="rounded-lg py-1 border shadow-lg">
              <VListItem
                v-for="lab in item.laboratories"
                :key="lab.id"
                @click="emit('delete-assignment', item.employee_id, lab.id)"
              >
                <template #prepend>
                  <VIcon icon="tabler-circle-x" size="16" color="error" class="me-2" />
                </template>
                <VListItemTitle class="text-xs font-weight-black text-error text-uppercase">{{ lab.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider class="opacity-10" />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards Premium) -->
    <div v-else class="bg-light">
      <VProgressLinear v-if="props.loading" indeterminate color="primary" class="rounded" />

      <div v-if="props.employeeLaboratories.length === 0 && !props.loading" class="text-center pa-10">
        <VIcon icon="tabler-user-off" size="48" class="text-disabled mb-2 opacity-20" />
        <p class="text-disabled text-sm uppercase font-weight-black">No se encontraron empleados</p>
      </div>

      <div class="pa-4">
        <VRow>
          <VCol v-for="item in props.employeeLaboratories" :key="item.employee_id" cols="12">
            <VCard class="rounded-lg border shadow-sm overflow-hidden">
              <div class="pa-4">
                <div class="d-flex justify-space-between align-start mb-3">
                  <div class="d-flex align-center gap-3 min-width-0">
                    <VAvatar :color="!item.photo_url ? getAvatarColor(item.employee_id) : undefined" size="44" variant="tonal" class="rounded shadow-sm">
                      <VImg v-if="item.photo_url" :src="item.photo_url" cover />
                      <span v-else class="text-body-1 font-weight-black text-uppercase">{{ getInitials(item.employee_name) }}</span>
                    </VAvatar>
                    <div class="d-flex flex-column min-width-0">
                      <span class="text-sm font-weight-black text-high-emphasis text-uppercase leading-tight truncate">
                        {{ item.employee_name }}
                      </span>
                      <span class="text-super-xs text-primary mt-1 font-weight-black uppercase truncate">
                        ID: #{{ item.employee_id }} • {{ item.identification || 'SIN DNI' }}
                      </span>
                    </div>
                  </div>
                  <div class="d-flex gap-1 flex-shrink-0" v-if="can('manage', 'admin')">
                    <IconBtn
                      size="small"
                      color="primary"
                      variant="tonal"
                      class="rounded"
                      @click="emit('edit-assignment', item)"
                    >
                      <VIcon icon="tabler-edit" size="18" />
                    </IconBtn>
                    <VMenu v-if="item.laboratories.length > 0">
                      <template #activator="{ props: menuProps }">
                        <IconBtn
                          v-bind="menuProps"
                          size="small"
                          color="error"
                          variant="tonal"
                          class="rounded"
                        >
                          <VIcon icon="tabler-trash" size="18" />
                        </IconBtn>
                      </template>
                      <VList density="compact" class="rounded-lg py-1 border shadow-lg">
                        <VListItem
                          v-for="lab in item.laboratories"
                          :key="lab.id"
                          @click="emit('delete-assignment', item.employee_id, lab.id)"
                        >
                          <template #prepend>
                            <VIcon icon="tabler-circle-x" size="16" color="error" class="me-2" />
                          </template>
                          <VListItemTitle class="text-xs font-weight-black text-error text-uppercase">{{ lab.name }}</VListItemTitle>
                        </VListItem>
                      </VList>
                    </VMenu>
                  </div>
                </div>

                <VDivider class="my-3 border-opacity-10" />

                <div>
                  <div class="text-super-xs font-weight-black text-disabled text-uppercase mb-2 d-flex justify-space-between align-center">
                    <span>LABORATORIOS ASIGNADOS</span>
                    <VChip size="x-small" color="success" variant="tonal" class="rounded px-2 font-weight-black uppercase">
                      {{ item.laboratories_count }}
                    </VChip>
                  </div>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="lab in item.laboratories"
                      :key="lab.id"
                      size="x-small"
                      color="secondary"
                      variant="tonal"
                      class="rounded font-weight-black text-uppercase"
                    >
                      {{ lab.name }}
                    </VChip>
                    <div v-if="item.laboratories.length === 0" class="text-super-xs text-disabled italic font-weight-black uppercase">
                      Sin laboratorios asignados
                    </div>
                  </div>
                </div>
              </div>
            </VCard>
          </VCol>
        </VRow>

        <!-- Paginación Móvil -->
        <div v-if="props.totalRecords > props.itemsPerPage" class="mt-6 d-flex justify-center">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            size="small"
            @update:model-value="(newPage) => emit('update:options', { ...props, page: newPage })"
          />
        </div>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
:deep(.premium-table) {
  background: transparent !important;

  thead th {
    background: white !important;
    color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
    font-size: 0.75rem !important;
    font-weight: 900 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05rem !important;
    border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.05) !important;
  }

  tbody tr {
    transition: background-color 0.2s ease;
    &:hover {
      background-color: rgba(var(--v-theme-primary), 0.02) !important;
    }
    td {
      padding-block: 8px !important;
      border-block-end: 1px solid rgba(var(--v-theme-on-surface), 0.03) !important;
    }
  }
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
  line-height: 1;
}

.max-w-ch-40 {
  max-width: 40ch;
}

.bg-light {
  background-color: rgba(var(--v-theme-on-surface), 0.015);
}

.leading-tight {
  line-height: 1.2;
}

.truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.min-width-0 {
  min-width: 0;
}
</style>

