import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { reactive, ref } from "vue";

export function useFixedExpenses() {
  // Estado para cuotas
  const occurrencesLoading = ref(false);
  const occurrences = ref([]);
  const occurrencesTotal = ref(0);
  const occurrencesPage = ref(1);
  const occurrencesPerPage = ref(15);
  const occurrencesScope = ref("pending"); // 'pending' | 'history'
  const occurrencesSearch = ref("");
  const occurrencesCategoryId = ref(null);

  // Resumen de cuotas
  const summaryLoading = ref(false);
  const summary = reactive({
    overdue_count: 0,
    week_count: 0,
    total_count: 0,
    by_currency: [],
  });

  // Estado para plantillas
  const templatesLoading = ref(false);
  const templates = ref([]);
  const templatesTotal = ref(0);
  const templatesPage = ref(1);
  const templatesPerPage = ref(10);
  const templatesSearch = ref("");
  const templatesCategoryId = ref(null);
  const templatesIsActive = ref(null);

  // Categorías de gastos
  const categories = ref([]);

  // Modales
  const isTemplateDialogOpen = ref(false);
  const templateDialogTitle = ref("Nuevo Gasto Fijo");
  const selectedTemplate = ref(null);

  const isPayDialogOpen = ref(false);
  const selectedOccurrence = ref(null);

  const isSkipDialogOpen = ref(false);
  const skipNote = ref("");
  const occurrenceToSkip = ref(null);

  // Cargar categorías
  async function fetchCategories() {
    try {
      const response = await axios.get("/finances/expenses/category");
      if (response.status === 200) {
        categories.value = response.data.data || [];
      }
    } catch (error) {
      console.error("Error al cargar categorías:", error);
    }
  }

  // Cargar resumen de cuotas
  async function fetchSummary() {
    summaryLoading.value = true;
    try {
      const response = await axios.get("/finances/fixed-expenses/occurrences/summary");
      if (response.status === 200 && response.data.data) {
        Object.assign(summary, response.data.data);
      }
    } catch (error) {
      console.error("Error al cargar resumen:", error);
    } finally {
      summaryLoading.value = false;
    }
  }

  // Cargar cuotas (pendientes o historial)
  async function fetchOccurrences() {
    occurrencesLoading.value = true;
    try {
      const params = {
        scope: occurrencesScope.value,
        page: occurrencesPage.value,
        per_page: occurrencesPerPage.value,
      };

      if (occurrencesSearch.value) {
        params.search = occurrencesSearch.value;
      }
      if (occurrencesCategoryId.value) {
        params.category_id = occurrencesCategoryId.value;
      }

      const response = await axios.get("/finances/fixed-expenses/occurrences", { params });
      if (response.status === 200) {
        const payload = response.data.data;
        occurrences.value = payload.data || [];
        occurrencesTotal.value = payload.meta?.total || payload.total || occurrences.value.length;
      }
    } catch (error) {
      console.error("Error al cargar cuotas:", error);
      toast.error("No se pudieron cargar los avisos de gastos fijos");
    } finally {
      occurrencesLoading.value = false;
    }
  }

  // Cargar plantillas
  async function fetchTemplates() {
    templatesLoading.value = true;
    try {
      const params = {
        page: templatesPage.value,
        per_page: templatesPerPage.value,
      };

      if (templatesSearch.value) {
        params.search = templatesSearch.value;
      }
      if (templatesCategoryId.value) {
        params.category_id = templatesCategoryId.value;
      }
      if (templatesIsActive.value !== null) {
        params.is_active = templatesIsActive.value;
      }

      const response = await axios.get("/finances/fixed-expenses", { params });
      if (response.status === 200) {
        const payload = response.data.data;
        templates.value = payload.data || [];
        templatesTotal.value = payload.meta?.total || payload.total || templates.value.length;
      }
    } catch (error) {
      console.error("Error al cargar plantillas:", error);
      toast.error("No se pudieron cargar las plantillas de gastos fijos");
    } finally {
      templatesLoading.value = false;
    }
  }

  // Guardar o actualizar plantilla
  async function saveTemplate(payload) {
    try {
      if (payload.id) {
        await axios.put(`/finances/fixed-expenses/${payload.id}`, payload);
        toast.success("Gasto fijo actualizado con éxito");
      } else {
        await axios.post("/finances/fixed-expenses", payload);
        toast.success("Gasto fijo configurado con éxito");
      }
      isTemplateDialogOpen.value = false;
      await fetchTemplates();
      await fetchOccurrences();
      await fetchSummary();
      return true;
    } catch (error) {
      console.error("Error al guardar plantilla:", error);
      const msg = error.response?.data?.message || "Error al guardar el gasto fijo";
      toast.error(msg);
      return false;
    }
  }

  // Pausar o activar plantilla
  async function toggleActive(template) {
    try {
      const response = await axios.patch(`/finances/fixed-expenses/${template.id}/toggle-active`);
      if (response.status === 200) {
        toast.success(response.data.message || "Estado actualizado");
        await fetchTemplates();
        await fetchOccurrences();
        await fetchSummary();
      }
    } catch (error) {
      console.error("Error al cambiar estado:", error);
      toast.error("Error al actualizar el estado");
    }
  }

  // Eliminar plantilla
  async function deleteTemplate(template) {
    try {
      const response = await axios.delete(`/finances/fixed-expenses/${template.id}`);
      if (response.status === 200) {
        toast.success("Gasto fijo eliminado");
        await fetchTemplates();
        await fetchOccurrences();
        await fetchSummary();
      }
    } catch (error) {
      console.error("Error al eliminar gasto fijo:", error);
      toast.error("No se pudo eliminar el gasto fijo");
    }
  }

  // Disparar generación manual
  async function generateOccurrencesNow() {
    try {
      const response = await axios.post("/finances/fixed-expenses/occurrences/generate");
      if (response.status === 200) {
        const count = response.data.data?.generated_count ?? 0;
        toast.success(response.data.message || `Generación completada: ${count} cuotas`);
        await fetchOccurrences();
        await fetchSummary();
        await fetchTemplates();
      }
    } catch (error) {
      console.error("Error al generar cuotas:", error);
      toast.error("Error al ejecutar la generación de cuotas");
    }
  }

  // Confirmar pago de una cuota
  async function payOccurrence(occurrenceId, payload) {
    try {
      const response = await axios.post(`/finances/fixed-expenses/occurrences/${occurrenceId}/pay`, payload);
      if (response.status === 200) {
        toast.success("Pago registrado con éxito");
        isPayDialogOpen.value = false;
        await fetchOccurrences();
        await fetchSummary();
        return true;
      }
    } catch (error) {
      console.error("Error al registrar pago:", error);
      const msg = error.response?.data?.message || "Error al procesar el pago";
      toast.error(msg);
      return false;
    }
  }

  // Omitir una cuota
  async function confirmSkipOccurrence() {
    if (!occurrenceToSkip.value) return;

    try {
      const response = await axios.post(`/finances/fixed-expenses/occurrences/${occurrenceToSkip.value.id}/skip`, {
        note: skipNote.value,
      });
      if (response.status === 200) {
        toast.success("Cuota omitida con éxito");
        isSkipDialogOpen.value = false;
        occurrenceToSkip.value = null;
        skipNote.value = "";
        await fetchOccurrences();
        await fetchSummary();
      }
    } catch (error) {
      console.error("Error al omitir cuota:", error);
      toast.error("Error al omitir la cuota");
    }
  }

  function openNewTemplateDialog() {
    selectedTemplate.value = null;
    templateDialogTitle.value = "Configurar Nuevo Gasto Fijo";
    isTemplateDialogOpen.value = true;
  }

  function openEditTemplateDialog(template) {
    selectedTemplate.value = { ...template };
    templateDialogTitle.value = `Editar Gasto Fijo: ${template.name}`;
    isTemplateDialogOpen.value = true;
  }

  function openPayDialog(occurrence) {
    selectedOccurrence.value = occurrence;
    isPayDialogOpen.value = true;
  }

  function openSkipDialog(occurrence) {
    occurrenceToSkip.value = occurrence;
    skipNote.value = "";
    isSkipDialogOpen.value = true;
  }

  return {
    // Cuotas
    occurrencesLoading,
    occurrences,
    occurrencesTotal,
    occurrencesPage,
    occurrencesPerPage,
    occurrencesScope,
    occurrencesSearch,
    occurrencesCategoryId,
    fetchOccurrences,

    // Resumen
    summaryLoading,
    summary,
    fetchSummary,

    // Plantillas
    templatesLoading,
    templates,
    templatesTotal,
    templatesPage,
    templatesPerPage,
    templatesSearch,
    templatesCategoryId,
    templatesIsActive,
    fetchTemplates,
    saveTemplate,
    toggleActive,
    deleteTemplate,

    // Categorías
    categories,
    fetchCategories,

    // Acciones de cuotas
    generateOccurrencesNow,
    payOccurrence,
    confirmSkipOccurrence,

    // Modales
    isTemplateDialogOpen,
    templateDialogTitle,
    selectedTemplate,
    openNewTemplateDialog,
    openEditTemplateDialog,

    isPayDialogOpen,
    selectedOccurrence,
    openPayDialog,

    isSkipDialogOpen,
    skipNote,
    occurrenceToSkip,
    openSkipDialog,
  };
}
