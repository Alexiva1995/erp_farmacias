<script setup>
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { computed, defineProps, ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  loading: { type: Boolean, default: false },
  companiesData: {
    type: Array,
    default: () => [],
  },
  isEditing: { type: Boolean, default: false },
  companiesOfferToEdit: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue", "saved"]);

// Wizard steps
const currentStep = ref(1);
const totalSteps = 2;

// Form data
const companiesOfferData = ref({
  company_id: null,
  start_date: "",
  end_date: "",
  is_active: true,
});

const scalesData = ref([
  {
    min_volume: "",
    max_volume: "",
    discount_percentage: "",
  },
]);

const loading = ref(false);
const formErrors = ref({});

// Opciones para el select de estatus
const statusOptions = [
  { title: 'Activa', value: true },
  { title: 'Inactiva', value: false },
];

// Computed properties
const dialogTitle = computed(() => {
  return props.isEditing ? "Editar Oferta" : "Crear Nueva Oferta";
});

const stepTitle = computed(() => {
  return currentStep.value === 1
    ? "Información General"
    : "Escalas de Descuento";
});

const canProceedToNext = computed(() => {
  if (currentStep.value === 1) {
    return (
      companiesOfferData.value.company_id &&
      companiesOfferData.value.start_date &&
      companiesOfferData.value.end_date
    );
  }

  return true;
});

const isLastStep = computed(() => {
  return currentStep.value === totalSteps;
});

// Methods
const onCancel = () => {
  resetForm();
  emit("update:modelValue", false);
};

const resetForm = () => {
  currentStep.value = 1;
  companiesOfferData.value = {
    company_id: null,
    start_date: "",
    end_date: "",
    is_active: true,
  };
  scalesData.value = [
    {
      min_volume: "",
      max_volume: "",
      discount_percentage: "",
    },
  ];
  formErrors.value = {};
};

const nextStep = () => {
  if (currentStep.value < totalSteps) {
    currentStep.value++;
  }
};

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

const addScale = () => {
  scalesData.value.push({
    min_volume: "",
    max_volume: "",
    discount_percentage: "",
  });
};

const removeScale = (index) => {
  if (scalesData.value.length > 1) {
    scalesData.value.splice(index, 1);
  }
};

const validateScales = () => {
  const errors = [];

  for (let i = 0; i < scalesData.value.length; i++) {
    const scale = scalesData.value[i];

    if (!scale.min_volume || !scale.max_volume || !scale.discount_percentage) {
      errors.push(`La escala ${i + 1} tiene campos vacíos`);
    }

    if (parseInt(scale.min_volume) >= parseInt(scale.max_volume)) {
      errors.push(
        `En la escala ${i + 1}, el volumen máximo debe ser mayor al mínimo`
      );
    }

    if (
      parseFloat(scale.discount_percentage) < 0 ||
      parseFloat(scale.discount_percentage) > 100
    ) {
      errors.push(
        `En la escala ${i + 1}, el descuento debe estar entre 0 y 100`
      );
    }
  }

  return errors;
};

const onSave = async () => {
  if (currentStep.value === 1) {
    nextStep();

    return;
  }

  // Validate scales
  const scaleErrors = validateScales();
  if (scaleErrors.length > 0) {
    scaleErrors.forEach((error) => toast.error(error));

    return;
  }

  loading.value = true;
  try {
    const payload = {
      ...companiesOfferData.value,
      scales: scalesData.value.map((scale) => ({
        min_volume: parseInt(scale.min_volume),
        max_volume: parseInt(scale.max_volume),
        discount_percentage: parseFloat(scale.discount_percentage),
      })),
    };

    const url = props.isEditing
      ? `/tpv/promotions/company-offer/${props.companiesOfferToEdit.id}`
      : "/tpv/promotions/company-offer";

    const method = props.isEditing ? "put" : "post";

    const response = await axios[method](url, payload);
    toast.success("La oferta se a guardado correctamente");
    emit("saved");
    onCancel();
  } catch (error) {
    console.error("Error al guardar la oferta:", error);

    if (error.response?.data?.errors) {
      formErrors.value = error.response.data.errors;
      Object.values(error.response.data.errors)
        .flat()
        .forEach((err) => {
          toast.error(err);
        });
    } else {
      toast.error(
        error.response?.data?.message || "Error al guardar la oferta"
      );
    }
  } finally {
    loading.value = false;
  }
};

// Watchers
watch(
  () => props.modelValue,
  (isVisible) => {
    if (isVisible && props.isEditing && props.companiesOfferToEdit) {
      // Load data for editing
      companiesOfferData.value = {
        id: props.companiesOfferToEdit.id,
        company_id: props.companiesOfferToEdit.company_id,
        start_date: formatDateForInput(props.companiesOfferToEdit.start_date),
        end_date: formatDateForInput(props.companiesOfferToEdit.end_date),
        is_active: Boolean(props.companiesOfferToEdit.is_active), // Asegurar que sea booleano 
      };

      if (
        props.companiesOfferToEdit.scales &&
        props.companiesOfferToEdit.scales.length > 0
      ) {
        scalesData.value = props.companiesOfferToEdit.scales.map((scale) => ({
          id: scale.id,
          company_offer_id: scale.company_offer_id,
          min_volume: scale.min_volume.toString(),
          max_volume: scale.max_volume.toString(),
          discount_percentage: scale.discount_percentage.toString(),
        }));
      }
    } else if (isVisible) {
      resetForm();
    }
  }
);

const formatDateForInput = (dateString) => {
  if (!dateString) return "";
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");

  return `${year}-${month}-${day}`;
};
</script>

<template>
  <VDialog
    :model-value="props.modelValue"
    max-width="800px"
    persistent
    @update:model-value="onCancel"
  >
    <VCard :loading="loading" class="d-flex flex-column">
      <VCardTitle class="d-flex align-center p-4">
        <span class="text-h5 font-weight-bold">{{ dialogTitle }}</span>
        <VSpacer />
      </VCardTitle>

      <VDivider />

      <!-- Progress Steps -->
      <VCardText class="pa-4">
        <VStepper :model-value="currentStep" alt-labels>
          <VStepperHeader>
            <VStepperItem
              :value="1"
              title="Información General"
              :complete="currentStep > 1"
            />
            <VStepperDivider />
            <VStepperItem
              :value="2"
              title="Escalas de Descuento"
              :complete="currentStep > 2"
            />
          </VStepperHeader>
        </VStepper>
      </VCardText>

      <VDivider />

      <!-- Step 1: Informacion General -->
      <VCardText v-if="currentStep === 1" class="flex-grow-1 pa-6">
        <p class="text-h6 font-weight-medium mb-4">Información de la Oferta</p>

        <VRow>
          <VCol cols="12">
            <VSelect
              v-model="companiesOfferData.company_id"
              label="Seleccionar Empresa"
              :items="props.companiesData"
              :item-title="(item) => `${item.id} - ${item.name}`"
              item-value="id"
              placeholder="Buscar empresa..."
              variant="outlined"
              :error-messages="formErrors.company_id"
              clearable
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="companiesOfferData.start_date"
              label="Fecha de Inicio"
              type="date"
              placeholder="YYYY-MM-DD"
              variant="outlined"
              :error-messages="formErrors.start_date"
            />
          </VCol>

          <VCol cols="12" sm="6">
            <VTextField
              v-model="companiesOfferData.end_date"
              label="Fecha de Finalización"
              type="date"
              placeholder="YYYY-MM-DD"
              variant="outlined"
              :error-messages="formErrors.end_date"
            />
          </VCol>

          <VCol cols="12">
            <VSelect
              v-model="companiesOfferData.is_active"
              label="Estatus"
              :items="statusOptions"
              item-title="title"
              item-value="value"
              placeholder="Seleccione un estatus"
              variant="outlined"
              :error-messages="formErrors.is_active"
            />
          </VCol>
        </VRow>
      </VCardText>

      <!-- Step 2: Escala de Descuento -->
      <VCardText v-if="currentStep === 2" class="flex-grow-1 pa-6">
        <div class="d-flex justify-space-between align-center mb-4">
          <p class="text-h6 font-weight-medium mb-0">Escalas de Descuento</p>
          <VBtn
            prepend-icon="tabler-plus"
            color="primary"
            variant="outlined"
            @click="addScale"
          >
            Agregar Escala
          </VBtn>
        </div>

        <p class="text-caption text-medium-emphasis mb-4">
          Define los rangos de volumen y sus respectivos descuentos
        </p>

        <VRow
          v-for="(scale, index) in scalesData"
          :key="index"
          class="mb-4 scale-row"
        >
          <VCol cols="12" sm="4">
            <VTextField
              v-model="scale.min_volume"
              label="Volumen Mínimo Productos"
              type="number"
              placeholder="0"
              variant="outlined"
              :error-messages="formErrors[`scales.${index}.min_volume`]"
            />
          </VCol>

          <VCol cols="12" sm="4">
            <VTextField
              v-model="scale.max_volume"
              label="Volumen Máximo Productos"
              type="number"
              placeholder="0"
              variant="outlined"
              :error-messages="formErrors[`scales.${index}.max_volume`]"
            />
          </VCol>

          <VCol cols="12" sm="3">
            <VTextField
              v-model="scale.discount_percentage"
              label="% Descuento"
              type="number"
              placeholder="0"
              variant="outlined"
              suffix="%"
              :error-messages="
                formErrors[`scales.${index}.discount_percentage`]
              "
            />
          </VCol>

          <VCol cols="12" sm="1" class="d-flex align-center">
            <VBtn
              v-if="scalesData.length > 1"
              icon
              color="error"
              variant="text"
              @click="removeScale(index)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions class="pa-4 px-6">
        <VRow>
          <VCol cols="6" class="pe-2">
            <VBtn
              v-if="currentStep > 1"
              color="secondary"
              variant="outlined"
              block
              @click="prevStep"
            >
              <VIcon>mdi-arrow-left</VIcon>
              Anterior
            </VBtn>
            <VBtn
              v-else
              color="secondary"
              variant="outlined"
              block
              @click="onCancel"
            >
              Cancelar
            </VBtn>
          </VCol>

          <VCol cols="6" class="ps-2">
            <VBtn
              color="primary"
              variant="flat"
              block
              :disabled="!canProceedToNext"
              :loading="loading"
              @click="onSave"
            >
              <template v-if="currentStep === 1">
                Siguiente
                <VIcon>mdi-arrow-right</VIcon>
              </template>
              <template v-else>
                {{ props.isEditing ? "Actualizar" : "Guardar" }}
              </template>
            </VBtn>
          </VCol>
        </VRow>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.scale-row {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  padding: 16px;
  background-color: #fafafa;
}
</style>
