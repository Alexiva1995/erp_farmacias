<script setup>
import EmployeeVoucherFormDialog from "@/components/dialogs/EmployeeVoucherFormDialog.vue";
import axios from "@/plugins/axios";
import { toast } from "@/plugins/sweetalert";
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const employee = ref({});
const vouchers = ref([]);
const roles = ref([]);

const page = ref(1);
const itemsPerPage = ref(10);
const totalVouchers = ref(0);

const tabs = ref("documents");
const showDialog = ref(false);
const showEditDialog = ref(false);
const employeeId = route.params.id;

const errors = ref({});
const photo = ref(null);
const photoPreview = ref(null);
const rif = ref(null);
const residence_letter = ref(null);
const cv = ref(null);

const fetchEmployee = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get(`/rrhh/employees/${employeeId}`);
    employee.value = {
      ...data.data,
      role_id: data.data.user.role.id,
      email: data.data.user.email,
    };
  } catch (error) {
    toast.error("No se pudo obtener los datos del empleado");

    router.push("/rrhh/employees");
  } finally {
    loading.value = false;
  }
};

const fetchEmployeeVouchers = async () => {
  try {
    const { data } = await axios.get(`/rrhh/employees/${employeeId}/vouchers`, {
      params: { perPage: itemsPerPage.value },
    });
    vouchers.value = data.data.data;
    totalVouchers.value = data.data.total;
  } catch (error) {
    toast.error("No se pudo obtener los bonos del empleado");
  }
};

const fetchRoles = async () => {
  try {
    const { data } = await axios.get("/roles");
    roles.value = data.data;
  } catch (error) {
    toast.error("No se pudo cargar los roles");
  }
};

onMounted(() =>
  Promise.all([fetchEmployee(), fetchEmployeeVouchers(), fetchRoles()])
);

const headers = [
  { title: "Nombre", key: "name", sortable: false },
  { title: "Monto", key: "amount", sortable: false },
  { title: "Tipo", key: "type", sortable: false },
  { title: "Frecuencia", key: "frequency", sortable: false },
  { title: "Acciones", key: "actions", sortable: false },
];

const handleDeleteVoucher = async (voucher) => {
  try {
    const form = new FormData();
    form.append("_method", "DELETE");
    await axios.post(`/rrhh/employees/vouchers/${voucher.id}`, form);

    toast.success("Se eliminó el bono del empleado");
    fetchEmployeeVouchers();
  } catch (error) {
    toast.error("No se pudo eliminar el bono del empleado");
  }
};

const handleDeleteEmployee = async (id) => {
  try {
    const form = new FormData();
    form.append("_method", "DELETE");
    await axios.post(`/rrhh/employees/${id}`, form);

    toast.success("Se eliminó el empleado exitosamente");
    router.push("/rrhh/employees");
  } catch (error) {
    toast.error("No se pudo eliminar al empleado");
  }
};

const handleUpdateEmployeeDocument = async () => {
  errors.value = {};
  try {
    const form = new FormData();
    form.append("_method", "PUT");
    const files = { photo, rif, residence_letter, cv };
    Object.entries(files).forEach(([key, ref]) => {
      if (ref.value instanceof File) form.append(key, ref.value);
    });

    const { data } = await axios.post(
      `/rrhh/employees/${employeeId}/documents`,
      form
    );

    if (data.status) {
      toast.success("Se guardaron los documentos del empleado exitosamente");

      photo.value = null;
      rif.value = null;
      residence_letter.value = null;
      cv.value = null;

      fetchEmployee();
    } else {
      toast.error("Verifique e intente de nuevo");
    }
  } catch (error) {
    console.log(error);
    toast.error("No se pudo subir los documentos del empleado");

    if (error.response.status === 422) {
      errors.value = error.response.data.errors;
    }
  }
};

const handleShowNewVoucherDialog = () => {
  showDialog.value = true;
};

const handleEditEmployee = () => {
  showEditDialog.value = true;
};

const handleRefreshTable = async () => {
  fetchEmployee();
};

const updateTableOptions = (options) => {
  page.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
};

const handleDownloadFile = async (file) => {
  try {
    const response = await axios.get(
      `/rrhh/employees/${employeeId}/download/${file}`,
      {
        responseType: "blob",
      }
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const contentDisposition = response.headers["content-disposition"];
    const userName = `${employee.value.user.username} V-${employee.value.identification}`;

    let fileName = `${
      file === "cv"
        ? "CV"
        : file === "rif"
        ? "Rif"
        : "Carta de residencia consejo comunal "
    } (${userName}).pdf`;
    if (contentDisposition) {
      const fileNameMatch = contentDisposition.match(/filename="(.+)"/);
      if (fileNameMatch && fileNameMatch.length === 2)
        fileName = fileNameMatch[1];
    }

    link.setAttribute("download", fileName);
    document.body.appendChild(link);
    link.click();

    link.remove();
    window.URL.revokeObjectURL(url);
    toast.success("Archivo descargado exitosamente");
  } catch (error) {
    toast.error("No se pudo descargar el archivo");
  }
};

watch(photo, (newFile, oldFile) => {
  if (oldFile) URL.revokeObjectURL(photoPreview.value);
  photoPreview.value = newFile ? URL.createObjectURL(newFile) : null;
});
</script>

<template>
  <div>
    <EmployeeFormDialog
      v-model="showEditDialog"
      :roles="roles"
      :selectedEmployee="employee"
      @refresh-table="handleRefreshTable"
    />

    <VRow>
      <VCol cols="12" md="4">
        <VCard>
          <VCardText class="text-center">
            <div class="d-flex flex-column pt-6 pb-4">
              <VAvatar
                size="90"
                variant="tonal"
                rounded
                class="mx-auto"
                :image="'/storage/' + employee.photo"
              />
              <h3 class="text-h3">
                {{ `${employee.name} ${employee.last_name}` }}
              </h3>
            </div>
            <div class="text-left">
              <h5 class="text-h5">Información</h5>
              <VDivider class="pb-4" />
              <p>
                <span class="font-weight-bold">Nombre:</span>
                {{ `${employee.name} ${employee.last_name}` }}
              </p>
              <p>
                <span class="font-weight-bold">Cédula:</span>
                {{ employee.identification }}
              </p>
              <p>
                <span class="font-weight-bold">Correo:</span>
                {{ employee?.user?.email }}
              </p>
              <p>
                <span class="font-weight-bold">Estado:</span>
                <v-badge
                  :color="employee.is_active ? 'success' : 'error'"
                  :content="employee.is_active ? ' Activo' : ' Despedido'"
                  inline
                ></v-badge>
              </p>
              <p>
                <span class="font-weight-bold">Rol:</span>
                {{ employee?.user?.role?.name }}
              </p>
            </div>
            <div class="mb-2 d-flex justify-center ga-3">
              <VBtn @click="handleEditEmployee">
                <VIcon icon="tabler-pencil" />
                <span>Editar</span>
              </VBtn>
              <VBtn
                color="error"
                variant="tonal"
                @click="handleDeleteEmployee(employeeId)"
              >
                <VIcon icon="tabler-trash" />
                <span>Eliminar</span>
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="8" class="pa-1">
        <VSlideGroup v-model="tabs" :show-arrows="false">
          <VSlideGroupItem value="documents" v-slot="{ isSelected, toggle }">
            <v-btn
              :color="isSelected ? 'primary' : 'transparent'"
              class="ma-2"
              @click="toggle"
            >
              <VIcon icon="tabler-file" class="mr-2" size="18" />
              <span>Documentos</span>
            </v-btn>
          </VSlideGroupItem>
          <VSlideGroupItem value="salary" v-slot="{ isSelected, toggle }">
            <v-btn
              :color="isSelected ? 'primary' : 'transparent'"
              class="ma-2"
              @click="toggle"
            >
              <VIcon icon="tabler-currency-dollar" class="mr-2" size="18" />
              <span>Detalles salariales</span>
            </v-btn>
          </VSlideGroupItem>
        </VSlideGroup>

        <VWindow :model-value="tabs" class="pa-2 disable-tab-transition">
          <VWindowItem value="documents">
            <VCard>
              <VCardText>
                <VRow>
                  <VCol cols="12" sm="12" md="6">
                    <VFileInput
                      v-model="photo"
                      label="Foto de perfil"
                      accept=".jpg, .jpeg, .png"
                      variant="outlined"
                      prepend-icon="tabler-photo"
                      clearable
                      :error-messages="errors.photo"
                    />
                    <VImg
                      v-if="Object.hasOwn(employee, 'photo')"
                      :aspect-ratio="1"
                      class="bg-surface elevation-10 mt-4 mx-auto"
                      :src="
                        photoPreview
                          ? photoPreview
                          : '/storage/' + employee.photo
                      "
                      width="200"
                      cover
                    />
                  </VCol>
                  <VCol cols="12" sm="12" md="6">
                    <VFileInput
                      v-model="rif"
                      label="Rif"
                      accept="application/pdf"
                      variant="outlined"
                      prepend-icon="tabler-file"
                      :append-icon="employee.rif && 'tabler-download'"
                      @click:append="handleDownloadFile('rif')"
                      clearable
                      :error-messages="errors.rif"
                    />

                    <VFileInput
                      v-model="residence_letter"
                      class="my-2"
                      label="Carta de residencia consejo comunal"
                      accept="application/pdf"
                      variant="outlined"
                      prepend-icon="tabler-file"
                      :append-icon="
                        employee.residence_letter && 'tabler-download'
                      "
                      @click:append="handleDownloadFile('residence_letter')"
                      clearable
                      :error-messages="errors.residence_letter"
                    />
                    <VFileInput
                      v-model="cv"
                      label="CV"
                      accept="application/pdf"
                      variant="outlined"
                      prepend-icon="tabler-file"
                      :append-icon="employee.cv && 'tabler-download'"
                      @click:append="handleDownloadFile('cv')"
                      clearable
                      :error-messages="errors.cv"
                    />
                  </VCol>
                  <VBtn
                    v-if="photo || cv || residence_letter || rif"
                    class="mt-4 mx-auto"
                    @click="handleUpdateEmployeeDocument"
                  >
                    <span>Guardar cambios</span>
                  </VBtn>
                </VRow>
              </VCardText>
            </VCard>
          </VWindowItem>
          <VWindowItem value="salary">
            <VCard>
              <VCardText>
                <VBtn
                  class="d-flex ms-auto"
                  @click="handleShowNewVoucherDialog"
                >
                  <VIcon icon="tabler-plus" />
                  <span class="ms-2">Nuevo</span>
                </VBtn>
                <h5 class="text-h5">Bonos</h5>
                <VDivider />

                <EmployeeVoucherFormDialog
                  v-model="showDialog"
                  :selected-employee="employee"
                  :vouchers="vouchers"
                  @refresh-table="fetchEmployeeVouchers"
                />

                <VDataTableServer
                  :headers="headers"
                  :items="vouchers"
                  :items-length="totalVouchers"
                  :items-per-page="itemsPerPage"
                  :page="page"
                  @update:options="(options) => updateTableOptions(options)"
                >
                  <template #item.name="{ item }">
                    <span>{{ item?.concept?.name }}</span>
                  </template>

                  <template #item.frequency="{ item }">
                    <span>
                      {{
                        item?.concept?.frequency === "annual"
                          ? "Anual"
                          : item?.concept?.frequency === "monthly"
                          ? "Mensual"
                          : "Quincenal"
                      }}
                    </span>
                  </template>

                  <template #item.type="{ item }">
                    <span>{{
                      item?.concept?.type === "salary" ? "Bono" : "Deducción"
                    }}</span>
                  </template>

                  <template #item.actions="{ item }">
                    <VTooltip text="Eliminar bono" location="top">
                      <template #activator="{ props }">
                        <VBtn
                          v-bind="props"
                          icon
                          variant="text"
                          @click="handleDeleteVoucher(item)"
                        >
                          <VIcon icon="tabler-trash" color="error" />
                        </VBtn>
                      </template>
                    </VTooltip>
                  </template>
                </VDataTableServer>
              </VCardText>
            </VCard>
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>
  </div>
</template>
