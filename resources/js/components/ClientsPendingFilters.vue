<script setup>
// Filtros para clientes pendientes de aprobación
import AppFilterBase from "@/components/AppFilterBase.vue";

const props = defineProps({
  status: [String, Number, null],
});

const emit = defineEmits(["update:status", "clear"]);

const options = [
  { value: 0, title: "Pendiente" },
  { value: 1, title: "Parcial"   },
  { value: 2, title: "Completo"  },
];
</script>

<template>
  <AppFilterBase
    :search="''"
    :has-advanced-filters="props.status !== null && props.status !== undefined && props.status !== ''"
    search-placeholder="Buscar..."
    @clear="emit('clear')"
  >
    <!-- El selector de estado toma el slot del buscador para mantener visibilidad inmediata -->
    <template #search>
      <VSelect
        :model-value="props.status"
        :items="options"
        placeholder="Filtrar por estado"
        clearable
        density="compact"
        hide-details
        prepend-inner-icon="tabler-filter-cog"
        @update:model-value="emit('update:status', $event)"
      />
    </template>
  </AppFilterBase>
</template>
