<script setup>
import Swal from "sweetalert2";
const props = defineProps({
  offers: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  itemsPerPage: { type: Number, required: true },
  page: { type: Number, required: true },
  total: { type: Number, required: true },
});

const emit = defineEmits(['update:options', 'edit-offer', 'delete-offer']);

const headers = [
  { title: 'ID', key: 'id', sortable: true, width: '80px' },
  { title: 'Meses para Expirar', key: 'months_to_expiration', sortable: true },
  { title: '% Descuento', key: 'discount_percentage', sortable: true },
  { title: 'Lotes Asociados', key: 'product_lots_count', sortable: false },
  { title: 'Estado', key: 'is_active', sortable: true },
  { title: 'Creado', key: 'created_at', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'center', width: '120px' },
];

// Asegurar que se pase la oferta completa al editar
const handleEdit = (offer) => {
  emit('edit-offer', offer);
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString('es-ES');
};

const getStatusBadge = (isActive) => {
  return isActive ? 'success' : 'error';
};

const getStatusText = (isActive) => {
  return isActive ? 'Activo' : 'Inactivo';
};

// Función para ver detalles de los lotes
const viewProductLots = (offer) => {
  if (offer.product_lots && offer.product_lots.length > 0) {
    const lotDetails = offer.product_lots.map(lot => 
      `• ${lot.product?.name} - Lote: ${lot.lot_number} (Stock: ${lot.quantity})`
    ).join('\n');
    
    Swal.fire({
      title: 'Lotes Asociados',
      html: `<div style="text-align: left; max-height: 400px; overflow-y: auto;">
        <pre style="font-family: inherit; white-space: pre-wrap;">${lotDetails}</pre>
      </div>`,
      showCloseButton: true,
      showConfirmButton: false
    });
  } else {
    Swal.fire({
      title: 'Lotes Asociados',
      text: 'No hay lotes asociados a esta oferta',
      icon: 'info'
    });
  }
};
</script>

<template>
  <VCard>
    <VDataTableServer
      :headers="headers"
      :items="props.offers"
      :items-length="props.total"
      :loading="props.loading"
      :items-per-page="props.itemsPerPage"
      :page="props.page"
      class="text-no-wrap"
      @update:options="(options) => emit('update:options', options)"
    >
      <template #item.months_to_expiration="{ item }">
        <VChip color="warning" variant="flat">
          {{ item.months_to_expiration }} mes(es)
        </VChip>
      </template>

      <template #item.discount_percentage="{ item }">
        <VChip color="primary" variant="flat">
          {{ item.discount_percentage }}%
        </VChip>
      </template>

      <template #item.product_lots_count="{ item }">
        <VBadge
          :content="item.product_lots_count || 0"
          color="primary"
          inline
        >
          <VBtn 
            variant="text" 
            size="small"
            @click="viewProductLots(item)"
          >
            Lotes
          </VBtn>
        </VBadge>
      </template>

      <template #item.is_active="{ item }">
        <VChip
          :color="getStatusBadge(item.is_active)"
          variant="flat"
        >
          {{ getStatusText(item.is_active) }}
        </VChip>
      </template>

      <template #item.created_at="{ item }">
        {{ formatDate(item.created_at) }}
      </template>

      <template #item.actions="{ item }">
        <VBtn
          icon
          size="small"
          variant="text"
          color="primary"
          @click="handleEdit(item)"
        >
          <VIcon icon="tabler-edit" />
        </VBtn>
        
        <VBtn
          icon
          size="small"
          variant="text"
          color="error"
          @click="emit('delete-offer', item.id)"
        >
          <VIcon icon="tabler-trash" />
        </VBtn>
      </template>
    </VDataTableServer>
  </VCard>
</template>
