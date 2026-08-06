<script setup>
import { useDisplay } from "vuetify";

defineProps({
  commands: {
    type: Array,
    required: true,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["refresh"]);

const { mobile } = useDisplay();

const getCommandColor = (cmd) => {
  if (!cmd) return "secondary";
  if (cmd.includes("REPORT_Z")) return "error";
  if (cmd.includes("REPORT_X")) return "info";
  if (cmd.includes("ANNUL")) return "warning";
  if (cmd.includes("PRINT_INVOICE")) return "success";
  return "secondary";
};

const getStatusColor = (status) => {
  if (status === "success") return "success";
  if (status === "error") return "error";
  return "warning";
};

const getStatusIcon = (status) => {
  if (status === "success") return "tabler-circle-check";
  if (status === "error") return "tabler-alert-circle";
  return "tabler-clock";
};
</script>

<template>
  <VCard border variant="flat" class="rounded-lg">
    <VCardItem>
      <template #prepend>
        <div class="pa-2 bg-secondary-tonal rounded-lg me-1">
          <VIcon icon="tabler-history" color="secondary" size="24" />
        </div>
      </template>
      <VCardTitle class="font-weight-black">Historial de Comandos</VCardTitle>
      <VCardSubtitle>Últimas operaciones enviadas a la impresora fiscal</VCardSubtitle>
      <template #append>
        <VBtn
          icon="tabler-refresh"
          variant="text"
          color="secondary"
          size="small"
          :loading="loading"
          @click="$emit('refresh')"
        />
      </template>
    </VCardItem>

    <VDivider class="opacity-10" />

    <!-- Skeleton Loaders durante carga inicial -->
    <div v-if="loading && commands.length === 0" class="pa-4">
      <VSkeletonLoader type="table-row-divider@4" />
    </div>

    <!-- Vista Desktop -->
    <VTable v-else-if="!mobile" hover class="premium-table text-no-wrap">
      <thead>
        <tr>
          <th class="text-xs uppercase font-weight-black">Comando</th>
          <th class="text-xs uppercase font-weight-black">Estado</th>
          <th class="text-xs uppercase font-weight-black">Respuesta / Detalle</th>
          <th class="text-xs uppercase font-weight-black">Fecha / Hora</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="cmd in commands" :key="cmd.id">
          <td>
            <VChip
              :color="getCommandColor(cmd.command)"
              size="x-small"
              variant="tonal"
              class="font-weight-black text-super-xs"
            >
              {{ (cmd.command || "").replace("_", " ") }}
            </VChip>
          </td>
          <td>
            <div class="d-flex align-center gap-2">
              <VIcon
                :icon="getStatusIcon(cmd.status)"
                :color="getStatusColor(cmd.status)"
                size="18"
              />
              <span class="text-sm font-weight-medium text-capitalize">
                {{ cmd.status }}
              </span>
            </div>
          </td>
          <td class="text-truncate" style="max-width: 320px">
            <span class="text-sm text-medium-emphasis">
              {{ cmd.response || (cmd.status === "pending" ? "Esperando respuesta del puente..." : "-") }}
            </span>
          </td>
          <td>
            <span class="text-sm text-medium-emphasis">{{ cmd.created_at || "Reciente" }}</span>
          </td>
        </tr>
        <tr v-if="commands.length === 0">
          <td colspan="4" class="text-center py-8">
            <div class="d-flex flex-column align-center gap-2">
              <VIcon icon="tabler-database-off" size="40" class="text-disabled" />
              <span class="text-medium-emphasis font-weight-medium">No hay comandos registrados en la cola.</span>
            </div>
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Vista Móvil -->
    <div v-else class="pa-4 flex-column d-flex gap-4">
      <div
        v-for="cmd in commands"
        :key="cmd.id"
        class="pa-4 border rounded-lg bg-light-surface d-flex flex-column gap-3"
      >
        <div class="d-flex justify-space-between align-center">
          <VChip
            :color="getCommandColor(cmd.command)"
            size="x-small"
            variant="tonal"
            class="font-weight-black text-super-xs"
          >
            {{ (cmd.command || "").replace("_", " ") }}
          </VChip>
          <div class="d-flex align-center gap-1">
            <VIcon
              :icon="getStatusIcon(cmd.status)"
              :color="getStatusColor(cmd.status)"
              size="16"
            />
            <span
              class="text-xs font-weight-bold text-capitalize text-uppercase"
              :class="`text-${getStatusColor(cmd.status)}`"
            >
              {{ cmd.status }}
            </span>
          </div>
        </div>

        <div v-if="cmd.response" class="bg-surface pa-3 rounded border-s-4 border-s-primary">
          <p class="text-xs text-medium-emphasis mb-0 leading-normal">
            {{ cmd.response }}
          </p>
        </div>

        <div class="d-flex align-center justify-space-between mt-1">
          <div class="d-flex align-center gap-1 text-disabled">
            <VIcon icon="tabler-calendar" size="14" />
            <span class="text-super-xs font-weight-medium">{{ cmd.created_at || "Reciente" }}</span>
          </div>
        </div>
      </div>

      <div
        v-if="commands.length === 0"
        class="text-center py-8 d-flex flex-column align-center gap-2 border rounded-lg border-dashed"
      >
        <VIcon icon="tabler-database-off" size="32" class="text-disabled" />
        <span class="text-xs text-medium-emphasis font-weight-medium">No hay historial disponible</span>
      </div>
    </div>
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  color: rgba(var(--v-theme-on-surface), 0.9) !important;
  border-inline: none !important;
  background-color: rgba(var(--v-theme-on-surface), 0.02) !important;
}

.premium-table :deep(td) {
  border-inline: none !important;
  color: rgba(var(--v-theme-on-surface), 0.8) !important;
  padding-block: 12px !important;
}

.bg-secondary-tonal {
  background-color: rgba(var(--v-theme-secondary), 0.12) !important;
}

.text-super-xs {
  font-size: 0.65rem !important;
  letter-spacing: 0.05em !important;
}
</style>
