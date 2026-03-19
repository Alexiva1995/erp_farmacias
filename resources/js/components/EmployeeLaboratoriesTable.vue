<script setup>
import { useDisplay } from "vuetify";

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
  { title: "ID Empleado", key: "employee_id", sortable: true },
  { title: "Empleado", key: "employee_name", sortable: true, width: "25%" },
  { title: "Identificación", key: "identification", sortable: false },
  {
    title: "Laboratorios Asignados",
    key: "laboratories",
    sortable: false,
    width: "35%",
  },
  { title: "Total Laboratorios", key: "laboratories_count", sortable: true },
  { title: "Acciones", key: "actions", sortable: false, align: "center" },
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
  const colors = [
    "primary",
    "secondary",
    "success",
    "info",
    "warning",
    "error",
  ];
  return colors[index % colors.length];
};
</script>

<template>
  <VCard class="overflow-hidden">
    <!-- Vista de Escritorio (Tabla) -->
    <VDataTableServer
      v-if="!mobile"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      :headers="headers"
      :items="props.employeeLaboratories"
      :items-length="props.totalRecords"
      :loading="props.loading"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.employee_id="{ item }">
        <span class="font-weight-medium">#{{ item.employee_id }}</span>
      </template>

      <template #item.employee_name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar
            :color="getAvatarColor(item.employee_id)"
            size="38"
            variant="tonal"
          >
            <span class="text-sm font-weight-bold">{{ getInitials(item.employee_name) }}</span>
          </VAvatar>
          <div class="d-flex flex-column">
            <span class="text-body-1 font-weight-medium text-high-emphasis">
              {{ item.employee_name }}
            </span>
            <span class="text-xs text-disabled">
              {{ item.is_active ? "Activo" : "Inactivo" }}
            </span>
          </div>
        </div>
      </template>

      <template #item.identification="{ item }">
        <span class="text-sm text-medium-emphasis">
          {{ item.identification || "N/A" }}
        </span>
      </template>

      <template #item.laboratories="{ item }">
        <div class="d-flex flex-wrap gap-2">
          <VChip
            v-for="lab in item.laboratories"
            :key="lab.id"
            size="small"
            color="primary"
            variant="tonal"
          >
            {{ lab.name }}
          </VChip>
          <VChip
            v-if="item.laboratories.length === 0"
            size="small"
            color="default"
            variant="outlined"
          >
            Sin laboratorios
          </VChip>
        </div>
      </template>

      <template #item.laboratories_count="{ item }">
        <VChip
          :color="item.laboratories_count > 0 ? 'success' : 'default'"
          size="small"
          variant="tonal"
        >
          {{ item.laboratories_count }}
        </VChip>
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <IconBtn @click="emit('edit-assignment', item)">
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent" location="top">
              Editar Laboratorios
            </VTooltip>
          </IconBtn>

          <VMenu>
            <template #activator="{ props: menuProps }">
              <IconBtn
                v-bind="menuProps"
                :disabled="item.laboratories.length === 0"
              >
                <VIcon icon="tabler-trash" />
                <VTooltip activator="parent" location="top">
                  Eliminar Laboratorio
                </VTooltip>
              </IconBtn>
            </template>
            <VList>
              <VListItem
                v-for="lab in item.laboratories"
                :key="lab.id"
                @click="emit('delete-assignment', item.employee_id, lab.id)"
              >
                <template #prepend>
                  <VIcon icon="tabler-trash" size="20" class="me-2" />
                </template>
                <VListItemTitle>Eliminar {{ lab.name }}</VListItemTitle>
              </VListItem>
            </VList>
          </VMenu>
        </div>
      </template>

      <template #bottom>
        <VDivider />
        <div class="d-flex justify-end pa-2">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
            @update:model-value="
              (newPage) => emit('update:options', { ...props, page: newPage })
            "
          />
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil (Cards Premium) -->
    <div v-else class="pa-4 bg-var-theme-background">
      <VProgressLinear
        v-if="props.loading"
        indeterminate
        color="primary"
        class="mb-4 rounded"
      />

      <div v-if="props.employeeLaboratories.length === 0 && !props.loading" class="text-center pa-10">
        <VIcon icon="tabler-user-off" size="48" class="text-disabled mb-2" />
        <p class="text-disabled">No se encontraron empleados</p>
      </div>

      <VRow>
        <VCol
          v-for="item in props.employeeLaboratories"
          :key="item.employee_id"
          cols="12"
        >
          <VCard variant="outlined" class="rounded-lg border-opacity-25 bg-surface">
            <VCardText class="pa-4">
              <div class="d-flex align-start justify-space-between mb-4">
                <div class="d-flex align-center gap-3">
                  <VAvatar
                    :color="getAvatarColor(item.employee_id)"
                    size="44"
                    variant="tonal"
                  >
                    <span class="text-h6">{{ getInitials(item.employee_name) }}</span>
                  </VAvatar>
                  <div class="d-flex flex-column">
                    <span class="text-body-1 font-weight-bold text-high-emphasis">
                      {{ item.employee_name }}
                    </span>
                    <span class="text-xs text-disabled">
                      ID: #{{ item.employee_id }} • {{ item.identification || 'Sin DNI' }}
                    </span>
                  </div>
                </div>
                
                <div class="d-flex gap-1">
                  <VBtn
                    icon="tabler-edit"
                    variant="text"
                    color="primary"
                    size="small"
                    @click="emit('edit-assignment', item)"
                  />
                  <VMenu>
                    <template #activator="{ props: menuProps }">
                      <VBtn
                        v-bind="menuProps"
                        icon="tabler-dots-vertical"
                        variant="text"
                        color="secondary"
                        size="small"
                        :disabled="item.laboratories.length === 0"
                      />
                    </template>
                    <VList>
                      <VListItem
                        v-for="lab in item.laboratories"
                        :key="lab.id"
                        @click="emit('delete-assignment', item.employee_id, lab.id)"
                      >
                        <template #prepend>
                          <VIcon icon="tabler-trash" size="18" class="me-2" />
                        </template>
                        <VListItemTitle class="text-sm">Quitar {{ lab.name }}</VListItemTitle>
                      </VListItem>
                    </VList>
                  </VMenu>
                </div>
              </div>

              <VDivider class="mb-4 border-dashed" />

              <div class="mb-2">
                <div class="text-xs font-weight-medium text-disabled text-uppercase mb-2">
                  Laboratorios Asignados ({{ item.laboratories_count }})
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <VChip
                    v-for="lab in item.laboratories"
                    :key="lab.id"
                    size="small"
                    color="primary"
                    variant="tonal"
                    label
                  >
                    {{ lab.name }}
                  </VChip>
                  <div v-if="item.laboratories.length === 0" class="text-caption text-disabled italic">
                    Sin laboratorios asignados
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Paginación Móvil -->
      <div v-if="props.totalRecords > 0" class="mt-6 d-flex justify-center">
        <VPagination
          :model-value="props.page"
          :length="Math.ceil(props.totalRecords / props.itemsPerPage)"
          size="small"
          @update:model-value="
            (newPage) => emit('update:options', { ...props, page: newPage })
          "
        />
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.border-dashed {
  border-style: dashed !important;
}
</style>
