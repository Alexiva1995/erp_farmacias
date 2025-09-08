<script setup>
import { defineProps, defineEmits, computed, ref, watch } from "vue";

const props = defineProps({
  isDialogVisible: {
    type: Boolean,
    required: true,
  },
  packData: {
    type: Object,
    default: () => ({}),
  },
});

const currentProgress = ref(0);
const progressStages = [0, 100];
const currentStageIndex = ref(0);

const emit = defineEmits(["update:isDialogVisible", "modal-closed"]);

const dialogVisible = computed({
  get: () => props.isDialogVisible,
  set: (val) => emit("update:isDialogVisible", val),
});

const closeModal = () => {
  emit("update:isDialogVisible", false);
  emit("modal-closed");
   resetProgress();
};


const continueButtonText = computed(() => {
  return currentProgress.value === 100 ? "Finalizar" : "Continuar";
});

const handleCompletePurchase = () => {

 if (currentProgress.value === 0) {
 
 }


 if (currentProgress.value < 100) {
    currentStageIndex.value++;
    if (currentStageIndex.value < progressStages.length) {
      currentProgress.value = progressStages[currentStageIndex.value];
    } else {
      currentProgress.value = 100;
    }
  } else {
    emit(
      "purchase-completed",
    );
    dialogVisible.value = false;
    resetProgress();
  }

}

const resetProgress = () => {
  currentProgress.value = 0;
  currentStageIndex.value = 0;
};

watch(
  () => props.isDialogVisible,
  (newVal) => {
    if (newVal) {
      resetProgress();
    }
  }
);
</script>
<template>
  <VDialog v-model="dialogVisible">
    <VCard>
      <VCardTitle class="d-flex align-center p-2">
        <span class="text-h5 font-weight-bold pr-1">Pack  </span>
        <VSpacer />
        <VBtn icon variant="text" @click="closeModal">
          <VIcon>tabler-x</VIcon>
        </VBtn>
      </VCardTitle>
      <VDivider/>
            <VCardText v-if="currentProgress === 0">
            
            </VCardText>
             <VCardText v-if="currentProgress === 100">
             
             </VCardText>
            <VCardActions class="p-2 d-flex justify-space-between w-100 mx-auto">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="closeModal"
          class="w-50"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          variant="flat"
          @click="handleCompletePurchase"
          class="w-50"
        >
          {{ continueButtonText }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
