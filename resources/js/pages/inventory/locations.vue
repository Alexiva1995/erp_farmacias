<script setup>
import { onMounted, ref } from "vue";
import LocationTable from "@/components/LocationTable.vue";
import LocationEditDialog from "@/components/dialogs/LocationEditDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import Swal from "sweetalert2";

const locations = ref([]);
const loading = ref(false);
const isEditDialogVisible = ref(false);
const currentLocation = ref({});

const fetchLocations = async () => {
  loading.value = true;
  try {
    const response = await axios.get("/locations");
    locations.value = response.data.data;
  } catch (error) {
    console.error("Error al cargar ubicaciones:", error);
    toast.error("No se pudieron cargar las ubicaciones.");
  } finally {
    loading.value = false;
  }
};

const handleAddLocation = () => {
  currentLocation.value = { name: "" };
  isEditDialogVisible.value = true;
};

const handleEditLocation = (location) => {
  currentLocation.value = { ...location };
  isEditDialogVisible.value = true;
};

const handleSaveLocation = async (locationData) => {
  const { setSaving, setErrors, ...payload } = locationData;
  const isNew = !payload.id;
  const url = isNew ? "/locations" : `/locations/${payload.id}`;
  const method = isNew ? "post" : "put";

  try {
    await axios[method](url, payload);
    toast.success(`Ubicación ${isNew ? "creada" : "actualizada"} con éxito.`);
    isEditDialogVisible.value = false;
    await fetchLocations();
  } catch (error) {
    if (error.response && error.response.status === 422) {
      setErrors(error.response.data.errors);
      toast.error("Por favor revisa los errores en el formulario.");
    } else {
      console.error("Error al guardar ubicación:", error);
      toast.error("Hubo un error al procesar la solicitud.");
    }
  } finally {
    setSaving(false);
  }
};

const handleDeleteLocation = async (id) => {
  const result = await Swal.fire({
    title: "¿Estás seguro?",
    text: "¡No podrás revertir la eliminación de esta ubicación!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Sí, eliminar",
    cancelButtonText: "Cancelar",
    confirmButtonColor: "v-theme-error",
    reverseButtons: true,
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/locations/${id}`);
      toast.success("Ubicación eliminada con éxito.");
      await fetchLocations();
    } catch (error) {
      console.error("Error al eliminar ubicación:", error);
      toast.error("No se pudo eliminar la ubicación.");
    }
  }
};

onMounted(() => {
  fetchLocations();
});
</script>

<template>
  <VContainer fluid class="locations-page">
    <VRow>
      <VCol cols="12">
        <LocationTable
          :locations="locations"
          :loading="loading"
          @add-location="handleAddLocation"
          @edit-location="handleEditLocation"
          @delete-location="handleDeleteLocation"
        />
      </VCol>
    </VRow>

    <LocationEditDialog
      v-model="isEditDialogVisible"
      :location="currentLocation"
      @save="handleSaveLocation"
    />
  </VContainer>
</template>

<style scoped>
.locations-page {
  padding-block: 24px;
}
</style>
