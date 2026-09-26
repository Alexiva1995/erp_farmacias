<script setup>
import { computed } from 'vue'

// Modal de Auditoría de Factura y Trazabilidad de Ciclo de Vida
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  invoice: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const formatDate = (dateString) => {
  if (!dateString) return null
  try {
    const cleanDate = String(dateString).split('T')[0]
    const parts = cleanDate.split('-')
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`
    }
    const d = new Date(dateString)
    if (isNaN(d.getTime())) return dateString
    return d.toLocaleDateString('es-VE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    })
  } catch (e) {
    return dateString
  }
}

const steps = computed(() => [
  {
    step: 1,
    title: '1. Subida / Registro Inicial',
    description: 'Creación del registro de la factura en el sistema.',
    icon: 'tabler-cloud-upload',
    user: props.invoice?.uploaded_by_user?.name || props.invoice?.uploaded_by_user?.username || 'admin',
    date: formatDate(props.invoice?.created_at || props.invoice?.created_invoice_date),
    isDone: Boolean(props.invoice?.uploaded_by_user || props.invoice?.id),
  },
  {
    step: 2,
    title: '2. Carga / Registro de Productos',
    description: 'Ingreso y homologación de renglones, cantidades y costos.',
    icon: 'tabler-list-check',
    user: props.invoice?.registered_by_user?.name || props.invoice?.registered_by_user?.username || (props.invoice?.registered_by ? 'admin' : null),
    date: formatDate(props.invoice?.received_date || (props.invoice?.registered_by_user ? props.invoice?.updated_at : null)),
    isDone: Boolean(props.invoice?.registered_by_user || props.invoice?.registered_by),
  },
  {
    step: 3,
    title: '3. Verificación y Ubicación Física',
    description: 'Asignación de ubicaciones de almacén y validación de lotes.',
    icon: 'tabler-box-margin',
    user: props.invoice?.loaded_by_user?.name || props.invoice?.loaded_by_user?.username || (props.invoice?.loaded_by ? 'admin' : null),
    date: formatDate(props.invoice?.loaded_by_user ? props.invoice?.updated_at : null),
    isDone: Boolean(props.invoice?.loaded_by_user || props.invoice?.loaded_by),
  },
  {
    step: 4,
    title: '4. Aprobación y Orden de Compra',
    description: 'Validación final financiera y aprobación administrativa.',
    icon: 'tabler-circle-check',
    user: props.invoice?.ordered_by_user?.name || props.invoice?.ordered_by_user?.username || (props.invoice?.ordered_by ? 'admin' : null),
    date: formatDate(props.invoice?.payment_date || (props.invoice?.ordered_by_user ? props.invoice?.updated_at : null)),
    isDone: Boolean(props.invoice?.ordered_by_user || props.invoice?.ordered_by),
  },
])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="580"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-xl overflow-hidden shadow-lg border-0 d-flex flex-column bg-surface">
      <!-- Cabecera con Gradiente Institucional del Sistema -->
      <VCardTitle class="pa-0 flex-shrink-0">
        <div
          class="px-5 py-4 d-flex align-center justify-space-between text-white"
          style="background: linear-gradient(135deg, #7A0099, #E20074) !important;"
        >
          <div class="d-flex align-center">
            <VAvatar color="white" variant="flat" size="38" class="me-3 elevation-1 flex-shrink-0">
              <VIcon color="primary" size="22">tabler-shield-check</VIcon>
            </VAvatar>
            <div class="d-flex flex-column">
              <h2 class="text-subtitle-1 font-weight-black text-white leading-tight mb-0.5" style="color: white !important;">
                Historial de Auditoría — Factura #{{ invoice?.invoice_number || 'N/A' }}
              </h2>
              <span class="text-caption text-white opacity-90 font-weight-medium" style="color: white !important; font-size: 11px;">
                Trazabilidad del ciclo de vida y operadores responsables
              </span>
            </div>
          </div>
          <VBtn
            icon="tabler-x"
            variant="tonal"
            color="white"
            size="x-small"
            class="rounded-lg"
            @click="emit('update:modelValue', false)"
          />
        </div>
      </VCardTitle>

      <!-- Resumen Compacto de la Factura -->
      <div v-if="invoice" class="px-5 py-2.5 bg-surface border-b d-flex align-center justify-space-between flex-wrap gap-2">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-building" size="16" color="primary" />
          <span class="text-caption text-medium-emphasis font-weight-medium">Proveedor:</span>
          <span class="text-caption font-weight-bold text-high-emphasis text-uppercase">{{ invoice?.supplier?.name || 'N/A' }}</span>
        </div>
        <div class="d-flex align-center gap-2">
          <VChip size="x-small" variant="tonal" color="primary" class="font-weight-bold">
            Control: {{ invoice?.control_number || 'S/N' }}
          </VChip>
          <VChip
            size="x-small"
            variant="tonal"
            :color="invoice?.status === 'approved' || invoice?.status === 'ordered' ? 'success' : invoice?.status === 'rejected' ? 'error' : 'warning'"
            class="font-weight-bold text-uppercase"
          >
            {{ invoice?.status || 'pending' }}
          </VChip>
        </div>
      </div>

      <!-- Contenido del Stepper / Timeline Limpio -->
      <VCardText class="pa-5 bg-surface flex-grow-1">
        <div class="timeline-container ps-1">
          <div
            v-for="(st, idx) in steps"
            :key="st.step"
            class="timeline-item d-flex gap-3 position-relative"
            :class="{ 'is-last': idx === steps.length - 1 }"
          >
            <!-- Eje y Conector vertical -->
            <div class="timeline-indicator-col d-flex flex-column align-center position-relative">
              <div
                class="timeline-dot d-flex align-center justify-center rounded-circle elevation-1 transition-all"
                :class="st.isDone ? 'bg-success text-white' : 'bg-surface border text-disabled'"
                style="width: 32px; height: 32px; min-width: 32px; z-index: 2;"
              >
                <VIcon :icon="st.isDone ? 'tabler-check' : st.icon" size="16" />
              </div>
              <div
                v-if="idx < steps.length - 1"
                class="timeline-line"
                :class="st.isDone && steps[idx + 1].isDone ? 'line-completed' : 'line-pending'"
              />
            </div>

            <!-- Contenido del Paso -->
            <div class="timeline-content-col flex-grow-1 pb-5">
              <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-1">
                <span
                  class="font-weight-bold text-body-2"
                  :class="st.isDone ? 'text-high-emphasis' : 'text-medium-emphasis'"
                >
                  {{ st.title }}
                </span>

                <VChip
                  size="x-small"
                  :color="st.isDone ? 'success' : 'secondary'"
                  variant="tonal"
                  class="font-weight-bold px-2 flex-shrink-0"
                  style="height: 20px; font-size: 10px;"
                >
                  <VIcon start size="11">
                    {{ st.isDone ? 'tabler-circle-check' : 'tabler-clock' }}
                  </VIcon>
                  {{ st.isDone ? 'Completado' : 'Pendiente' }}
                </VChip>
              </div>

              <p class="text-caption text-medium-emphasis mb-2 leading-relaxed" style="font-size: 11.5px;">
                {{ st.description }}
              </p>

              <!-- Metadatos: Operador y Fecha -->
              <div class="d-flex align-center gap-4 text-caption text-medium-emphasis flex-wrap" style="font-size: 11px;">
                <div class="d-flex align-center gap-1">
                  <VIcon icon="tabler-user" size="14" class="text-medium-emphasis" />
                  <span>Operador:</span>
                  <strong :class="st.user ? 'text-high-emphasis' : 'text-disabled'">
                    {{ st.user || 'No asignado' }}
                  </strong>
                </div>

                <div v-if="st.date" class="d-flex align-center gap-1">
                  <VIcon icon="tabler-calendar" size="14" class="text-medium-emphasis" />
                  <span>Fecha:</span>
                  <strong class="text-high-emphasis">{{ st.date }}</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />

      <!-- Pie con Botón de Cierre Secundario Limpio -->
      <VCardActions class="px-5 py-3 bg-surface d-flex justify-end align-center">
        <VBtn
          variant="tonal"
          color="secondary"
          size="default"
          class="rounded-lg font-weight-bold px-5"
          @click="emit('update:modelValue', false)"
        >
          <VIcon start icon="tabler-x" size="16" />
          Cerrar
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.timeline-line {
  position: absolute;
  top: 32px;
  bottom: 0;
  width: 2px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1;
}

.line-completed {
  background-color: rgb(var(--v-theme-success));
}

.line-pending {
  background-color: rgba(var(--v-theme-on-surface), 0.12);
}

.timeline-item.is-last .timeline-content-col {
  padding-bottom: 0 !important;
}
</style>

