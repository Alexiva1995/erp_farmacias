<script setup>
import { toast } from "@/plugins/sweetalert";
import { useDisplay } from "vuetify";

const props = defineProps({
  checkingApiId: { type: Number, default: null },
  supplierConnections: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  totalSupplierConnections: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  searchQuery: { type: String, default: "" },
});

const emit = defineEmits([
  "update:options",
  "show-products",
  "update-products",
  "load-products",
  "delete-products",
  "open-discount-dialog",
  "update:searchQuery",
  "open-public-link",
]);

const { mdAndUp } = useDisplay();

const copyPublicLink = (item) => {
  if (!item.public_token) {
    toast.error("El enlace público no ha sido generado todavía.");
    return;
  }
  const baseUrl = window.location.origin;
  const publicUrl = `${baseUrl}/p/suppliers/upload/${item.public_token}`;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard
      .writeText(publicUrl)
      .then(() => toast.success("Enlace copiado al portapapeles"))
      .catch(() => toast.error("Error al copiar el enlace"));
  } else {
    const textArea = document.createElement("textarea");
    textArea.value = publicUrl;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand("copy");
      toast.success("Enlace copiado al portapapeles");
    } catch (error) {
      toast.error("Error al copiar el enlace");
    }
    textArea.remove();
  }
};

const headers = [
  { title: "ID", key: "id", sortable: false, width: "70px" },
  { title: "PROVEEDOR", key: "name", sortable: false },
  { title: "ÚLTIMA CONEXIÓN", key: "last_connection", sortable: false },
  { title: "TIPO", key: "type", sortable: false },
  { title: "ACCIONES", key: "actions", sortable: false, align: "end" },
];
</script>

<template>
  <div class="comparator-table-container">
    <!-- Barra de Búsqueda y Filtros Rápidos (Standard) -->
    <!-- Barra de Búsqueda y Filtros Rápidos (Estandarizado) -->
    <VCard class="mb-6 rounded-lg border shadow-sm overflow-hidden bg-surface">
      <VCardText class="pa-4">
        <VRow align="center" no-gutters class="gap-2">
          <!-- Buscador Principal -->
          <VCol cols="12" sm="5" md="4" lg="4">
            <VTextField
              :model-value="props.searchQuery"
              placeholder="Buscar proveedor por nombre..."
              clearable
              density="compact"
              hide-details
              prepend-inner-icon="tabler-search"
              @update:model-value="emit('update:searchQuery', $event)"
            />
          </VCol>

          <VSpacer />

          <!-- Acciones (Solo Iconos) -->
          <div class="d-flex align-center gap-1">
            <!-- Limpiar Filtros -->
            <VBtn
              icon
              variant="text"
              color="secondary"
              size="38"
              @click="emit('update:searchQuery', '')"
            >
              <VIcon icon="tabler-eraser" />
              <VTooltip activator="parent" location="top"
                >Limpiar Filtros</VTooltip
              >
            </VBtn>
          </div>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Tabla Principal (Unified VCard) -->
    <VCard class="rounded-lg border shadow-sm overflow-hidden bg-surface">
      <!-- Vista Desktop -->
      <div v-if="mdAndUp" class="d-none d-md-block">
        <VDataTableServer
          v-model:items-per-page="props.itemsPerPage"
          v-model:page="props.page"
          :headers="headers"
          :items="props.supplierConnections"
          :items-length="props.totalSupplierConnections"
          :loading="props.loading"
          hover
          density="compact"
          class="text-no-wrap premium-table"
          @update:options="(options) => emit('update:options', options)"
        >
          <template #item.id="{ item }">
            <span class="text-sm font-weight-black text-primary">{{
              item.id
            }}</span>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex flex-column py-2">
              <span
                class="text-sm font-weight-black text-high-emphasis text-uppercase"
              >
                {{ item.name }}
              </span>
              <span class="text-xs text-disabled">{{ item.type }}</span>
            </div>
          </template>

          <template #item.last_connection="{ item }">
            <span class="text-sm font-weight-medium">{{
              item.last_connection || "Sin conexión"
            }}</span>
          </template>

          <template #item.type="{ item }">
            <VChip
              size="x-small"
              :color="item.type === 'API' ? 'primary' : 'secondary'"
              variant="tonal"
              class="text-uppercase"
            >
              {{ item.type }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex align-center justify-end ga-2">
              <VTooltip text="Ver Productos!" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-eye"
                    variant="text"
                    color="primary"
                    size="small"
                    @click="emit('show-products', item)"
                  />
                </template>
              </VTooltip>

              <VTooltip :text="['API', 'FTP', 'SFTP', 'HTTP'].includes(item.type) ? 'Sincronizar' : 'Cargar Archivo'" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    :icon="
                      ['API', 'FTP', 'SFTP', 'HTTP'].includes(item.type)
                        ? checkingApiId === item.id
                          ? 'tabler-loader-2'
                          : 'tabler-refresh'
                        : 'tabler-upload'
                    "
                    variant="text"
                    color="info"
                    size="small"
                    :disabled="checkingApiId === item.id"
                    :class="{ 'spin-icon': checkingApiId === item.id }"
                    @click="
                      ['API', 'FTP', 'SFTP', 'HTTP'].includes(item.type)
                        ? emit('update-products', item)
                        : emit('load-products', item)
                    "
                  />
                </template>
              </VTooltip>

              <VTooltip text="Descuento" location="top">
                <template #activator="{ props: tooltipProps }">
                  <VBtn
                    v-bind="tooltipProps"
                    icon="tabler-percentage"
                    variant="text"
                    color="warning"
                    size="small"
                    @click="emit('open-discount-dialog', item)"
                  />
                </template>
              </VTooltip>

              <VMenu location="bottom end" transition="slide-y-transition">
                <template #activator="{ props: menuProps }">
                  <VBtn
                    v-bind="menuProps"
                    icon="tabler-dots-vertical"
                    variant="text"
                    color="secondary"
                    size="small"
                  />
                </template>
                <VList density="compact" class="py-2 rounded-lg">
                  <VListItem
                    @click="copyPublicLink(item)"
                    prepend-icon="tabler-copy"
                  >
                    <VListItemTitle>Copiar Link</VListItemTitle>
                  </VListItem>
                  <VListItem
                    @click="emit('open-public-link', item)"
                    prepend-icon="tabler-link"
                  >
                    <VListItemTitle>Configurar Link</VListItemTitle>
                  </VListItem>
                  <VDivider class="my-2" />
                  <VListItem
                    @click="emit('delete-products', item)"
                    prepend-icon="tabler-trash"
                    color="error"
                  >
                    <VListItemTitle>Borrar Productos</VListItemTitle>
                  </VListItem>
                </VList>
              </VMenu>
            </div>
          </template>
        </VDataTableServer>
      </div>

      <!-- Vista Móvil (Cards) -->
      <div v-else class="d-md-none pa-4 bg-var-theme-background">
        <div v-if="loading" class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <div
          v-else-if="props.supplierConnections.length === 0"
          class="text-center py-8 text-disabled text-sm"
        >
          No se encontraron proveedores
        </div>
        <div v-else class="d-flex flex-column gap-4">
          <VCard
            v-for="item in props.supplierConnections"
            :key="item.id"
            class="mobile-card border shadow-none"
          >
            <VCardText class="pa-4">
              <div class="d-flex justify-space-between align-start mb-3">
                <div class="d-flex flex-column">
                  <span class="text-xs font-weight-bold text-disabled mb-1"
                    >#{{ item.id }}</span
                  >
                  <span
                    class="text-body-1 font-weight-black text-high-emphasis text-uppercase text-wrap"
                  >
                    {{ item.name }}
                  </span>
                </div>
                <VChip size="x-small" color="primary" variant="tonal">{{
                  item.type
                }}</VChip>
              </div>

              <div class="d-flex flex-column gap-2 mb-4">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-calendar-time"
                    size="14"
                    class="text-disabled"
                  />
                  <span class="text-xs text-medium-emphasis">
                    Conexión:
                    <span class="text-high-emphasis font-weight-medium">{{
                      item.last_connection || "N/A"
                    }}</span>
                  </span>
                </div>
              </div>

              <VDivider class="mb-4" />

              <div class="d-flex align-center justify-space-between ga-2">
                <VBtn
                  variant="flat"
                  color="primary"
                  size="small"
                  class="flex-grow-1"
                  prepend-icon="tabler-eye"
                  @click="emit('show-products', item)"
                >
                  Ver
                </VBtn>

                <div class="d-flex ga-1">
                  <VBtn
                    :icon="['API', 'FTP', 'SFTP', 'HTTP'].includes(item.type) ? 'tabler-refresh' : 'tabler-upload'"
                    variant="tonal"
                    color="info"
                    size="small"
                    :disabled="checkingApiId === item.id"
                    :class="{ 'spin-icon': checkingApiId === item.id }"
                    @click="['API', 'FTP', 'SFTP', 'HTTP'].includes(item.type) ? emit('update-products', item) : emit('load-products', item)"
                  />
                  <VBtn
                    icon="tabler-percentage"
                    variant="tonal"
                    color="warning"
                    size="small"
                    @click="emit('open-discount-dialog', item)"
                  />
                </div>
              </div>
            </VCardText>
          </VCard>

          <VPagination
            v-model="props.page"
            :length="Math.ceil(totalSupplierConnections / itemsPerPage)"
            :total-visible="3"
            density="compact"
            @update:model-value="
              (val) =>
                emit('update:options', {
                  page: val,
                  itemsPerPage: itemsPerPage,
                })
            "
          />
        </div>
      </div>
    </VCard>
  </div>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: #fff !important;
  color: rgba(
    var(--v-theme-on-surface),
    var(--v-medium-emphasis-opacity)
  ) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase !important;
  letter-spacing: 0.5px !important;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.08) !important;
}

.text-xs {
  font-size: 0.75rem !important;
}

.gap-2 {
  gap: 8px !important;
}
.gap-4 {
  gap: 16px !important;
}
.ga-1 {
  gap: 4px !important;
}
.ga-2 {
  gap: 8px !important;
}

.bg-var-theme-background {
  background-color: rgba(var(--v-border-color), 0.05);
}

.mobile-card {
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.mobile-card:active {
  transform: scale(0.98);
}

.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
