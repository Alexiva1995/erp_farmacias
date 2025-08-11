<script setup>
    import { computed, ref } from "vue";

    const props = defineProps({
      modelValue: { type: Boolean, required: true },
      supplier: { type: Object, default: () => ({}) },
      errors: { type: Object, default: () => ({}) },
    });

    const emit = defineEmits(["update:modelValue", "save", "clearErrors"]);

    const formData = ref({});
    const formErrors = ref({});

    const isNewPaymentRule = computed(() => !formData.value.id);

    const closeDialog = () => {
        emit("update:modelValue", false);
        formErrors.value = {};
        emit("clearErrors");
    };

    const submitForm = () => {
        formErrors.value = {}
        emit("clearErrors")

        emit('save', formData.value);
    };

     watch(
        () => props.errors,
        (newErrors) => {
            formErrors.value = newErrors || {};
        },
        { deep: true }
    );

    watch(
        () => props.supplier,
        (newSupplier) => {
            const rule = newSupplier?.payment_rule

            if (rule) {
                formData.value = {
                    days: rule.days,
                    discount_percentage: parseFloat(rule.discount_percentage),
                    id: rule.id,
                }
            } else {
                formData.value = {
                    days: null,
                    discount_percentage: null,
                }
            }

            formErrors.value = {};
        },
        { deep: true, immediate: true }
    );

</script>

<template>
    <VDialog
        :model-value="props.modelValue"
        max-width="800px"
        persistent
        @update:model-value="closeDialog"
        :scrollable="true"
        content-class="d-flex"
      >
        <VCard v-if="formData" class="d-flex flex-column">
            <VCardTitle class="d-flex align-center">
                <span class="text-h5 font-weight-bold">Regla de Pronto Pago</span>

                <VSpacer />

                <VBtn icon variant="text" @click="closeDialog">
                <VIcon>tabler-x</VIcon>
                </VBtn>
            </VCardTitle>

            <VDivider />

            <VCardText class="flex-grow-1" style="overflow-y: auto">
                <VForm @submit.prevent="submitForm">
                    <VRow>
                        <VCol cols="12" md="6">
                            <VTextField
                                v-model="formData.days"
                                label="Días"
                                type="number"
                                variant="outlined"
                                :error-messages="formErrors.days"
                            />
                        </VCol>
                        <VCol cols="12" md="6">
                            <VTextField
                                v-model="formData.discount_percentage"
                                label="% de Descuento"
                                type="number"
                                variant="outlined"
                                :error-messages="formErrors.discount_percentage"
                            />
                        </VCol>
                    </VRow>
                </VForm>
            </VCardText>

            <VDivider />

            <VCardActions class="pa-4">
                <VBtn
                color="secondary"
                variant="outlined"
                @click="closeDialog"
                class="flex-grow-1 w-0 mr-4"
                >
                Cancelar
                </VBtn>
                <VBtn
                color="primary"
                variant="flat"
                @click="submitForm"
                class="flex-grow-1 w-0"
                >
                Guardar
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
