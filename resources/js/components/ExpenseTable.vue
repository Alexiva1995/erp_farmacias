<script setup lang="js">
import dayjs from 'dayjs';
import { computed, ref } from 'vue';
import { useAuthStore } from "@/stores/auth";
import AppEmptyState from "@/components/AppEmptyState.vue";

const authStore = useAuthStore();

const props = defineProps({
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  total: { type: Number, required: true },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  statuModule: { type: Object, required: true },
});

const emit = defineEmits(['update:options', 'approve']);

const selectedAuditExpense = ref(null);
const showAuditModal = ref(false);

function openAuditModal(item) {
  selectedAuditExpense.value = item;
  showAuditModal.value = true;
}

const headers = computed(() => {
  const h = [
    { title: 'ID',             key: 'id',            sortable: true,  width: '70px' },
    { title: 'Descripción',     key: 'name',          sortable: true,  width: '250px' },
    { title: 'Categoría',       key: 'category.name', sortable: false, width: '150px' },
    { title: 'Moneda',          key: 'currency',      sortable: false, width: '100px' },
    { title: 'Monto Total',     key: 'total_usd',     sortable: true,  align: 'end', width: '150px' },
    { title: 'Estado / Auditoría', key: 'status',      sortable: false, align: 'center', width: '180px' },
    { title: 'Fecha',           key: 'created_at',    sortable: true,  width: '100px' },
  ];
  if (authStore.isAdmin) {
    h.push({ title: 'Acciones',        key: 'acciones',      sortable: false, align: 'center', width: '120px' });
  }
  return h;
});

const actionLabels = {
  created: 'Creado',
  created_recurring: 'Creado (Recurrente)',
  updated: 'Modificado',
  status_changed: 'Cambio de Estado',
  invoice_uploaded: 'Factura Subida',
};

function getAuditColor(action) {
  switch (action) {
    case 'created':
    case 'created_recurring':
      return 'primary';
    case 'status_changed':
      return 'warning';
    case 'invoice_uploaded':
      return 'info';
    case 'updated':
      return 'secondary';
    default:
      return 'primary';
  }
}

function getAuditIcon(action) {
  switch (action) {
    case 'created':
    case 'created_recurring':
      return 'tabler-circle-plus';
    case 'status_changed':
      return 'tabler-refresh';
    case 'invoice_uploaded':
      return 'tabler-file-invoice';
    case 'updated':
      return 'tabler-edit';
    default:
      return 'tabler-point';
  }
}

function getStatusLabel(status) {
  if (!status) return 'N/A';
  if (status === 'Approved' || status === 'Aprobado') return 'Aprobado';
  if (status === 'Cancelled' || status === 'Cancelado') return 'Cancelado';
  return 'Pendiente';
}

function getStatusChipColor(status) {
  if (status === 'Approved' || status === 'Aprobado') return 'success';
  if (status === 'Cancelled' || status === 'Cancelado') return 'error';
  return 'warning';
}

function isImageFile(url) {
  if (!url || typeof url !== 'string') return false;
  return !url.toLowerCase().endsWith('.pdf');
}

function openImage(url) {
  if (url) {
    window.open(url, '_blank');
  }
}
</script>

<template>
  <VCard variant="flat" class="overflow-hidden">
    <!-- Vista Pro Desktop -->
    <VDataTableServer
      v-if="!$vuetify.display.smAndDown"
      :headers="headers"
      :items-per-page="props.itemsPerPage"
      :items="props.items"
      :items-length="props.total"
      :loading="loading"
      :page="props.page"
      hover
      class="premium-table"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #no-data>
        <AppEmptyState
          title="No hay gastos"
          message="No se encontraron registros de gastos en este momento."
          icon="tabler-currency-dollar-off"
        />
      </template>

      <!-- ID -->
      <template #[`item.id`]="{ item }">
        <span class="font-weight-black text-primary text-sm">#{{ item.id }}</span>
      </template>

      <!-- Nombre / Descripción -->
      <template #[`item.name`]="{ item }">
        <div class="d-flex flex-column py-2">
          <span class="text-sm font-weight-black text-high-emphasis leading-tight mb-1">
            {{ item.name }}
          </span>
          <div class="d-flex align-center gap-1 mt-1">
            <VIcon icon="tabler-user-check" size="12" class="text-disabled" />
            <span class="text-super-xs font-weight-black text-disabled uppercase">
              Creado por: {{ item.user?.username || 'Sistema' }}
            </span>
          </div>
        </div>
      </template>

      <!-- Categoría -->
      <template #[`item.category.name`]="{ item }">
        <VChip size="x-small" color="primary" variant="tonal" class="rounded-lg font-weight-black px-2">
          <VIcon icon="tabler-tag" size="12" start />
          {{ item.category?.name || 'S/C' }}
        </VChip>
      </template>

      <!-- Moneda -->
      <template #[`item.currency`]="{ item }">
        <VChip size="x-small" variant="tonal" class="font-weight-black">
          {{ item.currency }}
        </VChip>
      </template>

      <!-- Monto -->
      <template #[`item.total_usd`]="{ item }">
        <div class="d-flex flex-column align-end py-2">
          <span class="text-sm font-weight-black text-error">
            ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
          </span>
          <span v-if="item.currency !== 'USD'" class="text-super-xs font-weight-black text-disabled mt-1">
            Orig: {{ Number(item.amount).toLocaleString('es-VE', { minimumFractionDigits: 2 }) }} {{ item.currency }}
          </span>
        </div>
      </template>

      <!-- Estado & Metadatos de Auditoría -->
      <template #[`item.status`]="{ item }">
        <div class="d-flex flex-column align-center gap-1">
          <VChip
            size="small"
            :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
            variant="tonal"
            class="font-weight-black uppercase px-2"
          >
            {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
          </VChip>
          
          <!-- Traza rápida aprobador/cancelador -->
          <span v-if="item.status === 'Approved' && item.approved_by" class="text-super-xs font-weight-bold text-success">
            Aprobado por {{ item.approved_by.username }}
          </span>
          <span v-else-if="item.status === 'Cancelled' && item.cancelled_by" class="text-super-xs font-weight-bold text-error">
            Cancelado por {{ item.cancelled_by.username }}
          </span>
        </div>
      </template>

      <!-- Fecha -->
      <template #[`item.created_at`]="{ item }">
        <span class="text-xs font-weight-bold text-disabled">{{ dayjs(item.created_at.replace('Z', '')).format('DD/MM/YYYY') }}</span>
      </template>

      <!-- Acciones & Botón Historial de Auditoría -->
      <template #[`item.acciones`]="{ item }">
        <div class="d-flex justify-center align-center gap-1">
          <VBtn
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            variant="text"
            color="success"
            size="small"
            class="rounded-lg"
            :loading="statuModule.loadingItems.has(item.id)"
            @click="() => emit('approve', item.id)"
          >
            <VIcon icon="tabler-circle-check" size="20" />
            <VTooltip activator="parent" location="top">Aprobar Gasto</VTooltip>
          </VBtn>

          <!-- Ver Auditoría Histórica -->
          <VBtn
            variant="text"
            color="info"
            size="small"
            class="rounded-lg"
            @click="openAuditModal(item)"
          >
            <VIcon icon="tabler-history" size="20" />
            <VTooltip activator="parent" location="top">Ver Historial de Auditoría</VTooltip>
          </VBtn>
        </div>
      </template>
    </VDataTableServer>

    <!-- Vista Móvil: Cards -->
    <div v-else class="pa-4 bg-surface-variant-light">
      <div v-if="loading" class="text-center py-12">
        <VProgressCircular indeterminate color="primary" />
      </div>
      
      <template v-else-if="props.items.length > 0">
        <div class="d-flex flex-column gap-3">
          <VCard
            v-for="item in props.items"
            :key="item.id"
            variant="flat"
            class="rounded-lg border shadow-soft pa-4 bg-white position-relative overflow-hidden"
          >
            <!-- Línea de Estado lateral -->
            <div 
              class="position-absolute left-0 top-0 bottom-0 w-1"
              :class="item.status === 'Approved' ? 'bg-success' : item.status === 'Cancelled' ? 'bg-error' : 'bg-warning'"
            ></div>

            <div class="d-flex justify-space-between align-center mb-2 ml-2">
              <div class="d-flex align-center gap-2">
                <VChip
                  size="x-small"
                  variant="flat"
                  class="rounded-lg font-weight-black bg-primary-opacity-1 text-primary"
                >
                  #{{ item.id }}
                </VChip>
                <VChip
                  size="x-small"
                  :color="item.status === 'Approved' ? 'success' : item.status === 'Cancelled' ? 'error' : 'warning'"
                  variant="tonal"
                  class="font-weight-black uppercase px-2"
                >
                  {{ item.status === 'Pending' ? 'Pendiente' : item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
                </VChip>
              </div>
              <div class="text-right">
                <span class="text-super-xs font-weight-black text-disabled uppercase d-block">Monto en USD</span>
                <span class="text-sm font-weight-black text-error">
                  ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>

            <div class="ml-2 mb-1">
              <div class="text-sm font-weight-black text-high-emphasis">{{ item.name }}</div>
            </div>

            <div class="ml-2 mb-3 mt-1 d-flex flex-wrap gap-2">
               <span class="text-super-xs font-weight-black text-disabled uppercase">
                 {{ item.count }}
               </span>
               <span class="text-disabled text-super-xs">•</span>
               <span class="text-super-xs font-weight-black text-disabled uppercase">
                 {{ item.currency }}
               </span>
            </div>

            <div class="d-flex justify-space-between align-center mt-3 pt-3 border-t ml-2">
              <div class="d-flex align-center gap-1">
                <VIcon icon="tabler-tag" size="14" class="text-disabled" />
                <span class="text-super-xs font-weight-black uppercase text-disabled">{{ item.category?.name || 'S/C' }}</span>
              </div>
              <VBtn
                variant="tonal"
                color="info"
                size="x-small"
                class="rounded-lg font-weight-black"
                @click="openAuditModal(item)"
              >
                Historial Auditable
              </VBtn>
            </div>
          </VCard>
        </div>
        
        <!-- Paginación Móvil -->
        <div class="d-flex justify-center mt-6">
          <VPagination
            :model-value="props.page"
            :length="Math.ceil(props.total / props.itemsPerPage)"
            total-visible="3"
            density="compact"
            active-color="primary"
            @update:model-value="(val) => emit('update:options', { page: val, itemsPerPage: props.itemsPerPage })"
          />
        </div>
      </template>

      <div v-else class="text-center py-12 text-disabled uppercase font-weight-bold border rounded-lg">
        No se encontraron gastos
      </div>
    </div>

    <!-- MODAL HISTORIAL DE AUDITORÍA INMUTABLE -->
    <VDialog v-model="showAuditModal" max-width="650px">
      <VCard class="rounded-xl">
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-shield-check" size="22" />
            <span class="font-weight-black text-subtitle-1">Historial de Auditoría e Inmutabilidad</span>
          </div>
          <VBtn icon="tabler-x" variant="text" color="white" density="compact" @click="showAuditModal = false" />
        </VCardTitle>

        <VCardText class="pa-6 bg-surface">
          <div v-if="selectedAuditExpense" class="mb-4">
            <div class="d-flex align-center justify-space-between mb-2">
              <span class="font-weight-black text-h6 text-high-emphasis">Gasto #{{ selectedAuditExpense.id }}: {{ selectedAuditExpense.name }}</span>
              <VChip size="small" color="primary" variant="flat" class="font-weight-black">
                ${{ Number(selectedAuditExpense.total_usd || 0).toFixed(2) }} USD
              </VChip>
            </div>
            
            <VDivider class="my-3" />

            <!-- Resumen de Autoría -->
            <div class="pa-3 bg-light rounded-lg border mb-4">
              <div class="d-flex flex-wrap gap-4 text-xs font-weight-bold">
                <div>Registrado por: <span class="text-primary">{{ selectedAuditExpense.user?.username || 'Sistema' }}</span></div>
                <div v-if="selectedAuditExpense.approved_by">
                  Aprobado por: <span class="text-success">{{ selectedAuditExpense.approved_by.username }}</span> ({{ selectedAuditExpense.approved_at }})
                </div>
                <div v-if="selectedAuditExpense.cancelled_by">
                  Cancelado por: <span class="text-error">{{ selectedAuditExpense.cancelled_by.username }}</span> ({{ selectedAuditExpense.cancelled_at }})
                </div>
              </div>
            </div>

            <!-- Trazabilidad de Auditoría en Timeline -->
            <h4 class="text-subtitle-2 font-weight-black text-disabled uppercase mb-3">Trazabilidad de Registro y Modificaciones:</h4>
            
            <VTimeline v-if="selectedAuditExpense.audits && selectedAuditExpense.audits.length > 0" density="compact" align="start">
              <VTimelineItem
                v-for="audit in selectedAuditExpense.audits"
                :key="audit.id"
                :dot-color="getAuditColor(audit.action)"
                size="x-small"
              >
                <div class="d-flex justify-space-between align-center mb-1">
                  <div class="d-flex align-center gap-2">
                    <VIcon :icon="getAuditIcon(audit.action)" size="16" :color="getAuditColor(audit.action)" />
                    <span class="font-weight-black text-xs text-high-emphasis uppercase">
                      {{ actionLabels[audit.action] || audit.action }}
                    </span>
                  </div>
                  <span class="text-super-xs text-disabled">{{ audit.created_at }}</span>
                </div>
                
                <div class="text-super-xs font-weight-bold text-primary mb-2">
                  Usuario: {{ audit.user_name }}
                </div>

                <!-- Caso: Factura / Comprobante subido -->
                <div v-if="audit.action === 'invoice_uploaded' && (audit.new_values?.url_file || selectedAuditExpense.url_file)" class="bg-light pa-3 rounded-lg border">
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-xs font-weight-bold text-high-emphasis d-flex align-center gap-1">
                      <VIcon icon="tabler-paperclip" size="14" color="primary" />
                      Comprobante Adjunto
                    </span>
                    <VBtn
                      size="x-small"
                      variant="tonal"
                      color="primary"
                      prepend-icon="tabler-external-link"
                      :href="audit.new_values?.url_file || selectedAuditExpense.url_file"
                      target="_blank"
                    >
                      Abrir original
                    </VBtn>
                  </div>
                  <div
                    v-if="isImageFile(audit.new_values?.url_file || selectedAuditExpense.url_file)"
                    class="rounded-lg overflow-hidden border bg-white d-flex justify-center pa-2"
                  >
                    <VImg
                      :src="audit.new_values?.url_file || selectedAuditExpense.url_file"
                      max-height="180"
                      class="cursor-pointer rounded"
                      cover
                      @click="openImage(audit.new_values?.url_file || selectedAuditExpense.url_file)"
                    />
                  </div>
                  <div v-else class="pa-2 bg-white rounded border d-flex align-center gap-2 text-xs font-weight-bold text-primary">
                    <VIcon icon="tabler-file-text" size="20" />
                    Documento PDF / Archivo adjunto
                  </div>
                </div>

                <!-- Caso: Cambio de Estado -->
                <div v-else-if="audit.action === 'status_changed'" class="bg-light pa-3 rounded-lg border d-flex align-center gap-2">
                  <VChip v-if="audit.old_values?.status" size="x-small" :color="getStatusChipColor(audit.old_values.status)" variant="tonal" class="font-weight-bold uppercase">
                    {{ getStatusLabel(audit.old_values.status) }}
                  </VChip>
                  <VIcon icon="tabler-arrow-right" size="14" class="text-disabled" />
                  <VChip size="x-small" :color="getStatusChipColor(audit.new_values?.status)" variant="flat" class="font-weight-bold uppercase">
                    {{ getStatusLabel(audit.new_values?.status) }}
                  </VChip>
                </div>

                <!-- Caso: Creación / Modificación -->
                <div v-else-if="audit.new_values" class="bg-light pa-3 rounded-lg border">
                  <div class="d-flex flex-wrap gap-2">
                    <div v-if="audit.new_values.name" class="text-super-xs">
                      <span class="text-disabled uppercase font-weight-bold">Descripción:</span>
                      <span class="font-weight-bold text-high-emphasis ml-1">{{ audit.new_values.name }}</span>
                    </div>
                    <div v-if="audit.new_values.amount" class="text-super-xs">
                      <span class="text-disabled uppercase font-weight-bold">Monto:</span>
                      <span class="font-weight-bold text-high-emphasis ml-1">{{ Number(audit.new_values.amount).toLocaleString('es-VE') }} {{ audit.new_values.currency || '' }}</span>
                    </div>
                    <div v-if="audit.new_values.total_usd" class="text-super-xs">
                      <span class="text-disabled uppercase font-weight-bold">Total USD:</span>
                      <span class="font-weight-bold text-error ml-1">${{ Number(audit.new_values.total_usd).toFixed(2) }}</span>
                    </div>
                    <div v-if="audit.new_values.expense_date" class="text-super-xs">
                      <span class="text-disabled uppercase font-weight-bold">Fecha:</span>
                      <span class="font-weight-bold text-high-emphasis ml-1">{{ audit.new_values.expense_date }}</span>
                    </div>
                  </div>
                </div>
              </VTimelineItem>
            </VTimeline>

            <div v-else class="text-center py-6 text-disabled text-xs uppercase font-weight-bold border rounded-lg">
              Sin registros adicionales de auditoría.
            </div>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </VCard>
</template>

<style scoped>
.premium-table :deep(th) {
  background-color: white !important;
  block-size: 52px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
  font-size: 0.75rem !important;
  font-weight: 700 !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.premium-table :deep(td) {
  padding-block: 12px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.05) !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.bg-primary-opacity-1 {
  background: rgba(var(--v-theme-primary), 0.08);
}

.text-super-xs { font-size: 0.65rem !important; }
.w-1 { width: 4px !important; }
</style>
