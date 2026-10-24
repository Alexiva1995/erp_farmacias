<script setup>
import { ref, onMounted } from 'vue'
import axios from '@/plugins/axios'
import { toast } from '@/plugins/sweetalert'
import Swal from 'sweetalert2'

const stations = ref([])
const loading = ref(false)
const dialog = ref(false)
const isSaving = ref(false)

const form = ref({
  id: null,
  name: '',
  code: '',
  color: 'primary',
  icon: 'tabler-chef-hat',
  printer_ip: '',
  sort_order: 1,
  is_active: true,
})

const colors = [
  { title: 'Primario (Morado)', value: 'primary' },
  { title: 'Rojo (Caliente / Fuego)', value: 'error' },
  { title: 'Azul (Frío / Ensaladas)', value: 'info' },
  { title: 'Naranja / Amarillo (Postres)', value: 'warning' },
  { title: 'Verde (Bar / Bebidas)', value: 'success' },
  { title: 'Gris Neutro', value: 'secondary' },
]

const icons = [
  { title: 'Gorro Chef', value: 'tabler-chef-hat' },
  { title: 'Fuego / Caliente', value: 'tabler-flame' },
  { title: 'Hielo / Frío', value: 'tabler-snowflake' },
  { title: 'Copa / Coctelería', value: 'tabler-glass-full' },
  { title: 'Taza Café', value: 'tabler-coffee' },
  { title: 'Pastel / Postre', value: 'tabler-cake' },
  { title: 'Carne / Parrilla', value: 'tabler-meat' },
  { title: 'Pizza', value: 'tabler-pizza' },
]

const fetchStations = async () => {
  loading.value = true
  try {
    const { data } = await axios.get('/kitchen-stations')
    stations.value = data.data || []
  } catch (error) {
    console.error('Error al cargar estaciones:', error)
    toast.error('No se pudieron cargar las estaciones.')
  } finally {
    loading.value = false
  }
}

const openDialog = (station = null) => {
  if (station) {
    form.value = { ...station }
  } else {
    form.value = {
      id: null,
      name: '',
      code: '',
      color: 'primary',
      icon: 'tabler-chef-hat',
      printer_ip: '',
      sort_order: (stations.value.length + 1),
      is_active: true,
    }
  }
  dialog.value = true
}

const saveStation = async () => {
  if (!form.value.name || !form.value.code) {
    toast.warning('El nombre y el código de la estación son obligatorios.')
    return
  }

  isSaving.value = true
  try {
    if (form.value.id) {
      await axios.put(`/kitchen-stations/${form.value.id}`, form.value)
      toast.success('Estación actualizada con éxito.')
    } else {
      await axios.post('/kitchen-stations', form.value)
      toast.success('Estación creada con éxito.')
    }
    dialog.value = false
    fetchStations()
  } catch (error) {
    console.error('Error al guardar estación:', error)
    toast.error(error.response?.data?.message || 'Error al guardar estación.')
  } finally {
    isSaving.value = false
  }
}

const deleteStation = async (station) => {
  const result = await Swal.fire({
    title: '¿Eliminar estación?',
    text: `Se eliminará la estación "${station.name}".`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Eliminar',
    cancelButtonText: 'Cancelar',
    confirmButtonColor: '#d33',
  })

  if (result.isConfirmed) {
    try {
      await axios.delete(`/kitchen-stations/${station.id}`)
      toast.success('Estación eliminada.')
      fetchStations()
    } catch (error) {
      console.error('Error al eliminar estación:', error)
      toast.error('No se pudo eliminar la estación.')
    }
  }
}

onMounted(() => {
  fetchStations()
})
</script>

<template>
  <div class="kitchen-stations-page pb-12">
    <!-- Header -->
    <VCard variant="flat" class="border rounded-lg mb-6 pa-4">
      <div class="d-flex flex-wrap align-center justify-space-between gap-4">
        <div class="d-flex align-center gap-3">
          <VAvatar color="primary" variant="flat" size="44">
            <VIcon icon="tabler-tools-kitchen-2" color="white" size="24" />
          </VAvatar>
          <div>
            <h2 class="text-h6 font-weight-black text-uppercase mb-0">Estaciones de Cocina</h2>
            <div class="text-xs text-medium-emphasis">
              Configura las áreas físicas de preparación (Cocina Caliente, Fría, Bar, Postres)
            </div>
          </div>
        </div>

        <VBtn color="primary" class="font-weight-bold" @click="openDialog()">
          <VIcon icon="tabler-plus" start size="18" />
          Nueva Estación
        </VBtn>
      </div>
    </VCard>

    <!-- Lista de Estaciones -->
    <VRow v-if="stations.length > 0">
      <VCol
        v-for="st in stations"
        :key="st.id"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard variant="flat" class="border rounded-lg pa-4 h-100 d-flex flex-column justify-space-between station-card">
          <div>
            <div class="d-flex align-center justify-space-between mb-3">
              <VAvatar :color="st.color || 'primary'" variant="tonal" size="40">
                <VIcon :icon="st.icon || 'tabler-chef-hat'" size="22" />
              </VAvatar>
              <VChip size="x-small" :color="st.is_active ? 'success' : 'secondary'" variant="flat" class="font-weight-black">
                {{ st.is_active ? 'ACTIVA' : 'INACTIVA' }}
              </VChip>
            </div>

            <h3 class="text-subtitle-1 font-weight-black text-high-emphasis mb-1">
              {{ st.name }}
            </h3>
            <div class="text-xs text-medium-emphasis mb-2 font-mono">
              Código: <strong>{{ st.code }}</strong>
            </div>

            <div class="text-xs text-disabled d-flex align-center gap-1 mb-1">
              <VIcon icon="tabler-meat" size="14" />
              <span>{{ st.dishes_count || 0 }} platos asignados</span>
            </div>
            <div v-if="st.printer_ip" class="text-xs text-disabled d-flex align-center gap-1">
              <VIcon icon="tabler-printer" size="14" />
              <span>IP: {{ st.printer_ip }}</span>
            </div>
          </div>

          <div class="d-flex justify-end gap-2 pt-4 border-t mt-4">
            <IconBtn size="small" color="primary" variant="tonal" @click="openDialog(st)">
              <VIcon icon="tabler-edit" size="18" />
            </IconBtn>
            <IconBtn size="small" color="error" variant="tonal" @click="deleteStation(st)">
              <VIcon icon="tabler-trash" size="18" />
            </IconBtn>
          </div>
        </VCard>
      </VCol>
    </VRow>

    <div v-else-if="!loading" class="pa-12 text-center text-disabled">
      <VIcon icon="tabler-tools-kitchen-2" size="64" class="opacity-30 mb-2" />
      <p>No hay estaciones registradas. Crea tu primera estación de cocina.</p>
    </div>

    <!-- Modal Crear/Editar Estación -->
    <VDialog v-model="dialog" max-width="540px" persistent>
      <VCard class="rounded-lg">
        <VCardTitle class="pa-4 bg-primary text-white d-flex align-center justify-space-between">
          <span class="text-subtitle-1 font-weight-black text-uppercase text-white">
            {{ form.id ? 'Editar Estación' : 'Nueva Estación de Cocina' }}
          </span>
          <VBtn icon variant="text" color="white" size="small" @click="dialog = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="pa-5 d-flex flex-column gap-4">
          <AppTextField
            v-model="form.name"
            label="Nombre de la Estación"
            placeholder="Ej: Cocina Caliente"
            required
          />

          <VRow dense>
            <VCol cols="6">
              <AppTextField
                v-model="form.code"
                label="Código Identificador"
                placeholder="Ej: HOT, BAR"
                required
              />
            </VCol>
            <VCol cols="6">
              <AppTextField
                v-model="form.sort_order"
                label="Orden en KDS"
                type="number"
                step="1"
                min="1"
              />
            </VCol>
          </VRow>

          <VRow dense>
            <VCol cols="6">
              <VSelect
                v-model="form.color"
                :items="colors"
                item-title="title"
                item-value="value"
                label="Color Visual"
              />
            </VCol>
            <VCol cols="6">
              <VSelect
                v-model="form.icon"
                :items="icons"
                item-title="title"
                item-value="value"
                label="Ícono"
              />
            </VCol>
          </VRow>

          <AppTextField
            v-model="form.printer_ip"
            label="IP Impresora Térmica (Opcional)"
            placeholder="192.168.1.200"
          />

          <VSwitch
            v-model="form.is_active"
            label="Estación Activa"
            color="primary"
            density="compact"
            hide-details
          />
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="tonal" color="secondary" @click="dialog = false">
            Cancelar
          </VBtn>
          <VBtn color="primary" variant="flat" :loading="isSaving" @click="saveStation">
            {{ form.id ? 'Actualizar' : 'Crear Estación' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.station-card {
  transition: transform 0.2s ease;
}
.station-card:hover {
  transform: translateY(-2px);
}
.font-mono {
  font-family: monospace;
}
</style>
