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
    { title: 'FECHA',             key: 'created_at',     sortable: true,  width: '105px' },
    { title: 'CONCEPTO / PROVEEDOR', key: 'name',           sortable: true,  width: '260px' },
    { title: 'N° FACTURA / REF',  key: 'invoice_number', sortable: false, width: '140px' },
    { title: 'CATEGORÍA',         key: 'category.name',  sortable: false, width: '140px' },
    { title: 'FORMA DE PAGO / CUENTA', key: 'count',     sortable: false, width: '160px' },
    { title: 'MONTO TOTAL',       key: 'total_usd',      sortable: true,  align: 'end', width: '160px' },
    { title: 'ESTADO',            key: 'status',         sortable: false, align: 'center', width: '160px' },
  ];
  if (authStore.isAdmin) {
    h.push({ title: 'ACCIONES',   key: 'acciones',       sortable: false, align: 'center', width: '110px' });
  }
  return h;
});

const totalPageUsd = computed(() => {
  return props.items.reduce((acc, curr) => acc + Number(curr.total_usd || 0), 0);
});

// Helper para extraer proveedor o concepto limpio
function parseConcept(item) {
  if (!item?.name) return '—';
  if (item.name.includes('- Proveedor:')) {
    const parts = item.name.split('- Proveedor:');
    return parts[1]?.trim() || item.name;
  }
  if (item.name.includes('Proveedor ')) {
    const parts = item.name.split('Proveedor ');
    return parts[1]?.trim() || item.name;
  }
  return item.name;
}

function parseInvoiceNumber(item) {
  let val = item.invoice_number;
  if (!val && item.name && item.name.includes('Pago Factura #')) {
    const match = item.name.match(/Pago Factura #\s*([^\s-]+)/i);
    if (match && match[1]) val = match[1];
  }
  if (val) {
    return String(val).replace(/^(FAC[-_ ]*)/i, '').trim();
  }
  return null;
}

function formatPaymentChip(item) {
  const method = item.count || item.payment_method || 'Efectivo';
  const curr = (item.currency || 'USD').toUpperCase();
  if (method.toUpperCase().includes(curr)) {
    return method;
  }
  return `${curr} - ${method}`;
}

function formatOriginalAmount(amount, currency) {
  const isCop = (currency || '').toUpperCase() === 'COP';
  const digits = isCop ? 0 : 2;
  return Number(amount || 0).toLocaleString('es-VE', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  });
}

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

      <!-- Fecha (Primera columna con estilo destacado) -->
      <template #[`item.created_at`]="{ item }">
        <span class="font-weight-black text-primary font-mono text-sm">
          {{ dayjs(item.created_at?.replace('Z', '') || item.expense_date).format('DD/MM/YYYY') }}
        </span>
      </template>

      <!-- Concepto / Proveedor -->
      <template #[`item.name`]="{ item }">
        <div class="d-flex flex-column py-2">
          <span class="text-body-2 font-weight-bold text-high-emphasis leading-snug mb-1">
            {{ parseConcept(item) }}
          </span>
          <div class="d-flex align-center gap-1">
            <VIcon icon="tabler-user" size="13" class="text-medium-emphasis" />
            <span class="text-caption text-medium-emphasis font-weight-bold">
              {{ item.user?.username || 'Sistema' }}
            </span>
          </div>
        </div>
      </template>

      <!-- N° Factura / Control -->
      <template #[`item.invoice_number`]="{ item }">
        <div class="d-flex flex-column gap-1">
          <span v-if="parseInvoiceNumber(item)" class="font-mono text-xs font-weight-bold text-primary bg-primary-subtle px-2 py-1 rounded d-inline-block text-center">
            {{ parseInvoiceNumber(item) }}
          </span>
          <span v-else class="text-disabled font-weight-medium text-caption">—</span>
        </div>
      </template>

      <!-- Categoría -->
      <template #[`item.category.name`]="{ item }">
        <VChip size="small" color="secondary" variant="tonal" class="rounded-lg font-weight-medium px-2 text-caption">
          <VIcon icon="tabler-tag" size="13" start />
          {{ item.category?.name || 'S/C' }}
        </VChip>
      </template>

      <!-- Forma de Pago / Cuenta -->
      <template #[`item.count`]="{ item }">
        <div class="d-flex align-center gap-1">
          <VChip size="small" variant="tonal" color="primary" class="font-weight-bold text-caption">
            <VIcon icon="tabler-wallet" size="13" start />
            {{ formatPaymentChip(item) }}
          </VChip>
        </div>
      </template>

      <!-- Monto Total -->
      <template #[`item.total_usd`]="{ item }">
        <div class="d-flex flex-column align-end py-2">
          <span
            class="text-body-1 font-weight-black font-mono"
            :class="(item.currency || '').toUpperCase() === 'USD' ? 'text-money-green' : 'text-high-emphasis'"
          >
            {{ formatOriginalAmount(item.amount, item.currency) }} {{ item.currency || 'USD' }}
          </span>
          <span v-if="(item.currency || '').toUpperCase() !== 'USD' && item.total_usd > 0" class="text-caption font-weight-bold text-money-green mt-0 font-mono">
            ≈ ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }} USD
          </span>
        </div>
      </template>

      <!-- Estado & Auditoría -->
      <template #[`item.status`]="{ item }">
        <div class="d-flex flex-column align-center gap-1">
          <!-- Badge Accesible WCAG para Pendiente y demás -->
          <span
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            class="badge-pending"
          >
            Pendiente
          </span>
          <VChip
            v-else-if="item.status === 'Approved' || item.status === 'Aprobado'"
            size="small"
            color="success"
            variant="tonal"
            class="font-weight-black uppercase px-2"
          >
            Aprobado
          </VChip>
          <VChip
            v-else
            size="small"
            color="error"
            variant="tonal"
            class="font-weight-black uppercase px-2"
          >
            Cancelado
          </VChip>
          
          <!-- Traza rápida aprobador/cancelador -->
          <span v-if="item.status === 'Approved' && item.approved_by" class="text-super-xs font-weight-bold text-success">
            Por {{ item.approved_by.username }}
          </span>
          <span v-else-if="item.status === 'Cancelled' && item.cancelled_by" class="text-super-xs font-weight-bold text-error">
            Por {{ item.cancelled_by.username }}
          </span>
        </div>
      </template>

      <!-- Acciones (Soporte, Aprobar, Auditoría) -->
      <template #[`item.acciones`]="{ item }">
        <div class="d-flex justify-center align-center gap-1">
          <!-- Ver Soporte / Adjunto -->
          <VBtn
            v-if="item.url_file"
            icon="tabler-paperclip"
            size="small"
            variant="text"
            color="primary"
            :href="item.url_file"
            target="_blank"
            class="rounded-lg"
          >
            <VIcon icon="tabler-paperclip" size="18" />
            <VTooltip activator="parent" location="top">Ver Soporte Adjunto</VTooltip>
          </VBtn>

          <!-- Aprobar Gasto (si es pendiente) -->
          <VBtn
            v-if="item.status === 'Pending' || item.status === 'Pendiente'"
            variant="tonal"
            color="success"
            size="small"
            class="rounded-lg"
            :loading="statuModule.loadingItems.has(item.id)"
            @click="() => emit('approve', item.id)"
          >
            <VIcon icon="tabler-circle-check" size="18" />
            <VTooltip activator="parent" location="top">Aprobar Gasto</VTooltip>
          </VBtn>

          <!-- Ver Auditoría Histórica solo en Aprobados y Cancelados -->
          <VBtn
            v-else
            variant="text"
            color="secondary"
            size="small"
            class="rounded-lg"
            @click="openAuditModal(item)"
          >
            <VIcon icon="tabler-history" size="18" />
            <VTooltip activator="parent" location="top">Historial de Auditoría</VTooltip>
          </VBtn>
        </div>
      </template>

      <!-- Fila de Totales en el Pie de Tabla -->
      <template #body.append>
        <tr v-if="props.items.length > 0" class="summary-footer-row bg-light font-weight-black">
          <td colspan="5" class="text-subtitle-2 font-weight-black text-uppercase text-medium-emphasis ps-4 py-3">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-calculator" size="18" class="text-primary" />
              <span>TOTALES DE LA PÁGINA ({{ props.items.length }} GASTOS)</span>
            </div>
          </td>
          <td class="text-end font-mono text-body-2 font-weight-black text-high-emphasis pe-4 py-3">
            ${{ totalPageUsd.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </td>
          <td colspan="2" class="py-3"></td>
        </tr>
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
                <span class="font-weight-black text-primary font-mono text-xs">
                  {{ dayjs(item.created_at?.replace('Z', '') || item.expense_date).format('DD/MM/YYYY') }}
                </span>
                <span
                  v-if="item.status === 'Pending' || item.status === 'Pendiente'"
                  class="badge-pending"
                >
                  Pendiente
                </span>
                <VChip
                  v-else
                  size="x-small"
                  :color="item.status === 'Approved' ? 'success' : 'error'"
                  variant="tonal"
                  class="font-weight-black uppercase px-2"
                >
                  {{ item.status === 'Approved' ? 'Aprobado' : 'Cancelado' }}
                </VChip>
              </div>
              <div class="text-right">
                <span
                  class="text-body-2 font-weight-black font-mono"
                  :class="(item.currency || '').toUpperCase() === 'USD' ? 'text-money-green' : 'text-high-emphasis'"
                >
                  {{ formatOriginalAmount(item.amount, item.currency) }} {{ item.currency || 'USD' }}
                </span>
                <span v-if="(item.currency || '').toUpperCase() !== 'USD' && item.total_usd > 0" class="text-super-xs font-weight-bold text-money-green d-block font-mono">
                  ≈ ${{ Number(item.total_usd || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }} USD
                </span>
              </div>
            </div>

            <div class="ml-2 mb-1">
              <div class="text-body-2 font-weight-bold text-high-emphasis">{{ parseConcept(item) }}</div>
              <span class="text-super-xs text-medium-emphasis font-weight-bold">
                {{ item.user?.username || 'Sistema' }}
              </span>
            </div>

            <div class="ml-2 mb-2 d-flex flex-wrap gap-2 align-center">
              <span v-if="parseInvoiceNumber(item)" class="font-mono text-super-xs font-weight-bold text-primary bg-primary-subtle px-2 py-0.5 rounded">
                {{ parseInvoiceNumber(item) }}
              </span>
              <span class="text-super-xs font-weight-black text-medium-emphasis uppercase">
                {{ formatPaymentChip(item) }}
              </span>
            </div>

            <div class="d-flex justify-space-between align-center mt-3 pt-3 border-t ml-2">
              <div class="d-flex align-center gap-2">
                <VChip size="x-small" color="secondary" variant="tonal">
                  {{ item.category?.name || 'S/C' }}
                </VChip>
                <VBtn
                  v-if="item.url_file"
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  icon="tabler-paperclip"
                  :href="item.url_file"
                  target="_blank"
                />
              </div>
              <div class="d-flex align-center gap-1">
                <VBtn
                  v-if="item.status === 'Pending' || item.status === 'Pendiente'"
                  variant="tonal"
                  color="success"
                  size="x-small"
                  class="rounded-lg font-weight-black"
                  :loading="statuModule.loadingItems.has(item.id)"
                  @click="() => emit('approve', item.id)"
                >
                  Aprobar
                </VBtn>
                <VBtn
                  v-else
                  variant="tonal"
                  color="secondary"
                  size="x-small"
                  class="rounded-lg font-weight-black"
                  @click="openAuditModal(item)"
                >
                  Auditoría
                </VBtn>
              </div>
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
              <VChip size="small" color="primary" variant="flat" class="font-weight-black font-mono">
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
                  <VChip v-if="audit.old_values?.status" size="x-small" :color="audit.old_values.status === 'Approved' ? 'success' : audit.old_values.status === 'Cancelled' ? 'error' : 'warning'" variant="tonal" class="font-weight-bold uppercase">
                    {{ getStatusLabel(audit.old_values.status) }}
                  </VChip>
                  <VIcon icon="tabler-arrow-right" size="14" class="text-disabled" />
                  <VChip size="x-small" :color="audit.new_values?.status === 'Approved' ? 'success' : audit.new_values?.status === 'Cancelled' ? 'error' : 'warning'" variant="flat" class="font-weight-bold uppercase">
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
                      <span class="font-weight-bold text-high-emphasis ml-1 font-mono">{{ Number(audit.new_values.amount).toLocaleString('es-VE') }} {{ audit.new_values.currency || '' }}</span>
                    </div>
                    <div v-if="audit.new_values.total_usd" class="text-super-xs">
                      <span class="text-disabled uppercase font-weight-bold">Total USD:</span>
                      <span class="font-weight-bold text-high-emphasis ml-1 font-mono">${{ Number(audit.new_values.total_usd).toFixed(2) }}</span>
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
.text-money-green {
  color: #16a34a !important;
  font-weight: 700 !important;
}
.premium-table :deep(th) {
  background-color: #f8fafc !important;
  block-size: 48px !important;
  border-block-end: 2px solid rgba(var(--v-border-color), 0.12) !important;
  color: rgba(var(--v-theme-on-surface), 0.85) !important;
  font-size: 0.75rem !important;
  font-weight: 800 !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.premium-table :deep(td) {
  padding-block: 10px !important;
  border-block-end: 1px solid rgba(var(--v-border-color), 0.06) !important;
}

.summary-footer-row {
  border-top: 2px solid rgba(var(--v-border-color), 0.18) !important;
  background-color: #f1f5f9 !important;
}

.bg-surface-variant-light {
  background-color: rgba(var(--v-theme-surface-variant), 5%);
}

.bg-primary-opacity-1 {
  background: rgba(var(--v-theme-primary), 0.08);
}

.bg-primary-subtle {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.badge-pending {
  background-color: #fef3c7;
  color: #92400e;
  border: 1px solid #fcd34d;
  font-weight: 800;
  font-size: 0.72rem;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  display: inline-block;
}

.text-super-xs { font-size: 0.65rem !important; }
.w-1 { width: 4px !important; }
</style>
