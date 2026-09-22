<script setup>
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import { computed, ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'imported'])

const rawText = ref('')
const parsedRows = ref([])
const isParsing = ref(false)
const isSaving = ref(false)

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(val || 0)
}

const formatDate = (isoStr) => {
  if (!isoStr) return '-'
  const parts = isoStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return isoStr
}

// Total acumulado parseado
const totalParsedAmount = computed(() => {
  return parsedRows.value.reduce((sum, r) => sum + (Number(r.amount) || 0), 0)
})

// Parseador local reactivo instantáneo
const parseLocalText = (text) => {
  if (!text || text.trim() === '') {
    parsedRows.value = []
    return
  }

  const lines = text.trim().split(/\r?\n/)
  const results = []

  for (const rawLine of lines) {
    const line = rawLine.trim()
    if (!line) continue

    // Ignorar cabeceras
    if (/Periodo|Impuesto|Documento|Fecha de Operaci/i.test(line)) {
      continue
    }

    // Separar por tabulador o por 2 o más espacios
    const parts = line.split(/\t+|\s{2,}/).map(p => p.trim()).filter(Boolean)

    if (parts.length >= 6) {
      const period = parts[0]
      const taxType = parts[1]
      const documentNumber = parts[2]
      const opDate = formatToIso(parts[3])
      const dueDate = formatToIso(parts[4])
      const amount = parseAmount(parts[5])

      if (period && taxType && documentNumber && opDate && dueDate) {
        results.push({
          period,
          tax_type: taxType,
          document_number: documentNumber,
          operation_date: opDate,
          due_date: dueDate,
          amount,
          status: 'pending',
        })
      }
    }
  }

  parsedRows.value = results
}

const formatToIso = (dateStr) => {
  if (!dateStr) return null
  const clean = dateStr.trim()
  const parts = clean.split('/')
  if (parts.length === 3) {
    const day = parts[0].padStart(2, '0')
    const month = parts[1].padStart(2, '0')
    const year = parts[2]
    return `${year}-${month}-${day}`
  }
  return null
}

const parseAmount = (amountStr) => {
  if (!amountStr) return 0
  let clean = amountStr.replace(/[^\d.,]/g, '')
  clean = clean.replace(/\./g, '').replace(',', '.')
  return parseFloat(clean) || 0
}

watch(rawText, (val) => {
  parseLocalText(val)
})

const handlePasteFromClipboard = async () => {
  try {
    const text = await navigator.clipboard.readText()
    if (text) {
      rawText.value = text
      toast.success('Texto pegado desde el portapapeles.')
    }
  } catch (err) {
    toast.info('Por favor presiona Ctrl+V dentro del cuadro de texto.')
  }
}

const handleConfirmImport = async () => {
  if (parsedRows.value.length === 0) {
    toast.error('No hay compromisos válidos para importar.')
    return
  }

  isSaving.value = true
  try {
    const response = await axios.post('/fiscal-contributions/batch-import', {
      items: parsedRows.value,
      source: 'smart_paste',
    })

    toast.success(response.data?.message || 'Contribuciones importadas exitosamente.')
    emit('imported')
    emit('update:modelValue', false)
    rawText.value = ''
    parsedRows.value = []
  } catch (error) {
    console.error('Error al guardar importación:', error)
    toast.error(error.response?.data?.message || 'Error al guardar los registros en el sistema.')
  } finally {
    isSaving.value = false
  }
}

const handleClose = () => {
  emit('update:modelValue', false)
  rawText.value = ''
  parsedRows.value = []
}
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="850px"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard class="rounded-lg shadow-lg bg-surface">
      <!-- Cabecera -->
      <VCardTitle class="pa-5 d-flex justify-space-between align-center border-b">
        <div class="d-flex align-center gap-2">
          <VAvatar color="primary" variant="tonal" size="36" class="rounded-lg">
            <VIcon icon="tabler-clipboard-text" size="22" />
          </VAvatar>
          <div>
            <span class="text-sm font-weight-black uppercase d-block">Pegado Inteligente desde SENIAT</span>
            <span class="text-xs text-disabled">Copia la tabla de compromisos del portal y pégala aquí directamente</span>
          </div>
        </div>
        <VBtn icon variant="text" size="small" @click="handleClose">
          <VIcon icon="tabler-x" />
        </VBtn>
      </VCardTitle>

      <VCardText class="pa-5">
        <!-- Input Area -->
        <div class="mb-4">
          <div class="d-flex justify-space-between align-center mb-2">
            <span class="text-xs font-weight-bold text-high-emphasis">Área de Pegado de Texto</span>
            <VBtn
              size="x-small"
              variant="tonal"
              color="primary"
              class="rounded-lg font-weight-bold"
              @click="handlePasteFromClipboard"
            >
              <VIcon start icon="tabler-clipboard" size="14" />
              Pegar del Portapapeles
            </VBtn>
          </div>

          <VTextarea
            v-model="rawText"
            rows="4"
            variant="outlined"
            density="compact"
            placeholder="Ejemplo:
09/2026   ANTICIPO-ISLR   2601586717   21/09/2026   22/09/2026   11.639,05
09/2026   IGTF            2692103258   21/09/2026   22/09/2026   20.088,77"
            hide-details
            class="font-mono text-xs"
          />
        </div>

        <!-- Previsualización de Registros Detectados -->
        <div v-if="parsedRows.length > 0" class="mt-4">
          <div class="d-flex justify-space-between align-center mb-2">
            <div class="d-flex align-center gap-2">
              <VChip size="small" color="success" class="font-weight-bold">
                {{ parsedRows.length }} compromisos detectados
              </VChip>
              <span class="text-xs font-weight-black text-success">
                Total: Bs. {{ formatCurrency(totalParsedAmount) }}
              </span>
            </div>
            <span class="text-super-xs text-disabled uppercase font-weight-bold">Previsualización</span>
          </div>

          <VTable density="compact" class="border rounded-lg overflow-hidden text-xs">
            <thead>
              <tr class="bg-surface-variant-opacity-2">
                <th class="font-weight-bold uppercase text-super-xs">Periodo</th>
                <th class="font-weight-bold uppercase text-super-xs">Impuesto</th>
                <th class="font-weight-bold uppercase text-super-xs">Documento</th>
                <th class="font-weight-bold uppercase text-super-xs">Fecha Operación</th>
                <th class="font-weight-bold uppercase text-super-xs">Fecha Vencimiento</th>
                <th class="font-weight-bold uppercase text-super-xs text-end">Monto (Bs.)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in parsedRows" :key="idx">
                <td class="font-weight-bold">{{ row.period }}</td>
                <td>
                  <VChip size="x-small" color="primary" variant="tonal" class="font-weight-bold">
                    {{ row.tax_type }}
                  </VChip>
                </td>
                <td class="font-weight-bold text-primary">{{ row.document_number }}</td>
                <td class="text-disabled">{{ formatDate(row.operation_date) }}</td>
                <td class="text-error font-weight-bold">{{ formatDate(row.due_date) }}</td>
                <td class="text-end font-weight-bold text-success">{{ formatCurrency(row.amount) }}</td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <div v-else-if="rawText.trim() !== ''" class="mt-4">
          <VAlert type="warning" variant="tonal" density="compact" class="text-xs rounded-lg">
            No se detectó un formato tabular válido del SENIAT. Asegúrate de copiar las columnas completas: Periodo, Impuesto, Documento, Fechas y Monto.
          </VAlert>
        </div>
      </VCardText>

      <!-- Botones de Acción -->
      <VCardActions class="pa-5 border-t d-flex justify-end gap-2">
        <VBtn
          variant="outlined"
          color="secondary"
          class="rounded-lg text-xs font-weight-bold"
          @click="handleClose"
        >
          Cancelar
        </VBtn>
        <VBtn
          variant="flat"
          color="primary"
          class="rounded-lg text-xs font-weight-bold shadow-sm"
          :disabled="parsedRows.length === 0"
          :loading="isSaving"
          @click="handleConfirmImport"
        >
          <VIcon start icon="tabler-check" size="18" />
          Guardar {{ parsedRows.length }} Compromisos
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.text-super-xs {
  font-size: 0.65rem !important;
}
.bg-surface-variant-opacity-2 {
  background-color: rgba(var(--v-theme-on-surface), 0.03) !important;
}
</style>
