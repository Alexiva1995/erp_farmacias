<script setup>
const props = defineProps({
  lots: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    required: true,
  },
  totalLots: {
    type: Number,
    required: true,
  },
  itemsPerPage: {
    type: Number,
    required: true,
  },
})

const emit = defineEmits(['update:options', 'apply-discount', 'expire-lot'])

const headers = [
  { title: 'Producto', key: 'product.name', sortable: true },
  { title: 'Nº Lote', key: 'lot_number', sortable: false },
  { title: 'Fecha Vencimiento', key: 'expiration_date', sortable: true },
  { title: 'Unidades', key: 'quantity', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center' },
]

const updateOptions = options => {
  emit('update:options', options)
}

const formatDate = dateString => {
  const options = { year: 'numeric', month: '2-digit', day: '2-digit' }
  return new Date(dateString).toLocaleDateString('es-ES', options)
}
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="props.lots"
      :items-length="props.totalLots"
      :loading="props.loading"
      :items-per-page="props.itemsPerPage"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.product.name="{ item }">
        <div class="d-flex align-center">
          <div class="d-flex flex-column">
            <h6 class="text-base">
              {{ item.product.name }}
            </h6>
          </div>
        </div>
      </template>

      <template #item.expiration_date="{ item }">
        <VChip
          :color="new Date(item.expiration_date) < new Date() ? 'error' : 'warning'"
          size="small"
          label
        >
          {{ formatDate(item.expiration_date) }}
        </VChip>
      </template>
      
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VTooltip location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                size="small"
                color="default"
                variant="text"
                @click="$emit('apply-discount', item)"
              >
                <VIcon
                  size="22"
                  icon="tabler-percentage"
                />
              </VBtn>
            </template>
            <span>Aplicar Descuento</span>
          </VTooltip>

          <VTooltip location="top">
            <template #activator="{ props: tooltipProps }">
              <VBtn
                v-bind="tooltipProps"
                icon
                size="small"
                color="default"
                variant="text"
                @click="$emit('expire-lot', item)"
              >
                <VIcon
                  size="22"
                  icon="tabler-calendar-off"
                />
              </VBtn>
            </template>
            <span>Marcar como Caducado</span>
          </VTooltip>
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
