<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <Header />
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <div v-if="serverUnreachable" class="mx-3 mt-2 p-2 rounded bg-amber-100 text-amber-800 text-sm">
        Server not reachable. Using saved data. Start the PHP backend (e.g. <code class="bg-amber-200 px-1 rounded">php -S localhost:8000 -t public public/router.php</code> in the <code>backend-php</code> folder) to sync and save changes.
      </div>
      <div class="bg-white rounded-lg my-4 mx-3 md:mx-10 mx-2">
        <div class="bg-[#D9D9D9] p-2 rounded-t-lg mb-2 font-bold text-lg">
          <p>Doctor's Information</p>
        </div>

        <div class="px-2 pb-2">
          <ion-label>Profile picture</ion-label>
          <div class="flex items-center gap-3 mt-2">
            <img
              :src="profileImagePreview"
              @error="onProfileImageError"
              alt="Profile"
              class="h-20 w-20 rounded-full object-cover bg-[#D9D9D9]"
            />
            <input
              v-if="isEditing"
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
              @change="handleProfilePhotoUpload"
              class="text-sm file-input"
            />
          </div>
        </div>

        <div class="px-2">
          <ion-label>Username</ion-label>
          <ion-input
            v-model="doctor.username"
            :disabled="!isEditing"
            class="bg-[#D9D9D9] rounded-md p-2"
          />
        </div>

        <div class="px-2">
          <ion-label>Email</ion-label>
          <ion-input
            v-model="doctor.email"
            :disabled="!isEditing"
            class="bg-[#D9D9D9] rounded-md p-2"
          />
        </div>

        <div class="px-2 pb-2">
          <ion-label>Password</ion-label>
          <ion-input
            :key="`profile-password-${passwordFieldVersion}`"
            type="password"
            v-model="doctor.password"
            :disabled="!isEditing"
            class="bg-[#D9D9D9] rounded-md p-2"
          >
            <ion-input-password-toggle style="--color: black;" slot="end" />
          </ion-input>
        </div>

        <!-- Prescription template -->
        <div class="px-2 pb-4">
          <ion-label>Prescription template (Word .docx)</ion-label>

          <div v-if="doctor.prescriptionFileName" class="flex items-center gap-2 mt-2">
            <span
              v-if="isEditing"
              class="text-sm text-blue-600 underline cursor-pointer break-all"
              @click="viewFile('prescription')"
            >
              {{ doctor.prescriptionFileName }}
            </span>
            <span v-else class="text-sm text-gray-700 break-all">
              {{ doctor.prescriptionFileName }}
            </span>
          </div>

          <div v-if="isEditing" class="file-input-box mt-2">
            <input
              type="file"
              accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              @change="handlePrescriptionUpload"
              class="text-sm file-input"
            />
          </div>
        </div>

        <!-- Medical Certificate template -->
        <div class="px-2 pb-4">
          <ion-label>Medical Certificate template (Word .docx)</ion-label>

          <div v-if="doctor.medcertFileName" class="flex items-center gap-2 mt-2">
            <span
              v-if="isEditing"
              class="text-sm text-blue-600 underline cursor-pointer break-all"
              @click="viewFile('medical')"
            >
              {{ doctor.medcertFileName }}
            </span>
            <span v-else class="text-sm text-gray-700 break-all">
              {{ doctor.medcertFileName }}
            </span>
          </div>

          <div v-if="isEditing" class="file-input-box mt-2">
            <input
              type="file"
              accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              @change="handleMedicalCertificateUpload"
              class="text-sm file-input"
            />
          </div>
        </div>

        <!-- Laboratory Request template -->
        <div class="px-2 pb-4">
          <ion-label>Laboratory Request template (Word .docx)</ion-label>

          <div v-if="doctor.labRequestFileName" class="flex items-center gap-2 mt-2">
            <span
              v-if="isEditing"
              class="text-sm text-blue-600 underline cursor-pointer break-all"
              @click="viewFile('laboratory')"
            >
              {{ doctor.labRequestFileName }}
            </span>
            <span v-else class="text-sm text-gray-700 break-all">
              {{ doctor.labRequestFileName }}
            </span>
          </div>

          <div v-if="isEditing" class="file-input-box mt-2">
            <input
              type="file"
              accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
              @change="handleLabRequestUpload"
              class="text-sm file-input"
            />
          </div>
        </div>
        <div class="my-2 mx-2 flex justify-between items-center gap-2">
        <!-- Show Edit button only when NOT editing -->
        <ion-button
          v-if="!isEditing"
          fill="clear"
          @click="enableEdit"
          class="w-full mb-4 bg-[#023E8A] font-bold text-sm rounded-lg text-white"
        >
          Edit
        </ion-button>

        <!-- Show Update button only when editing -->
        <ion-button
          v-if="isEditing"
          fill="clear"
          @click="updateDoctor"
          :disabled="isSaving"
          class="w-full mb-4 bg-[#023E8A] font-bold text-sm rounded-lg text-white"
        >
          <span v-if="!isSaving">Update</span>
          <span v-else>Updating...</span>
        </ion-button>
      </div>
      </div>


      <div class="mb-3">
        <div class="flex justify-end">
          <ion-button fill="clear" @click="logout" class="text-red-600">
            <font-awesome-icon :icon="['fas', 'arrow-right-from-bracket']" class="text-sm mr-1" />
            <span>Sign Out</span>
          </ion-button>
        </div>
        <p class="text-gray-500 mt-1 flex flex-nowrap items-center justify-center gap-1 text-center whitespace-nowrap leading-none px-2" style="font-size: clamp(8px, 2.7vw, 16px);">
          <span class="leading-none" style="font-size: 1.15em;">©</span>
          <span>All Right Reserved KAYE DELGADO &amp; SHIRLYN CANLOM</span>
        </p>
      </div>
    </ion-content>
  </ion-page>
</template>

<script lang="ts">
import { reactive, ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";
import { useProfile } from "../composables/useProfile";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonContent,
  IonInput,
  IonLabel,
  IonButton,
  IonInputPasswordToggle,
} from "@ionic/vue";
import Header from "../components/header.vue";
import defaultProfileImg from "../assets/logo/profile.png";
import { resolveUploadUrl } from "../utils/uploadUrl";

export default {
  name: "DoctorInfoPage",

  components: {
    IonPage,
    IonHeader,
    IonToolbar,
    IonContent,
    IonInput,
    IonLabel,
    IonButton,
    IonInputPasswordToggle,
    Header,
  },

  setup() {
    const router = useRouter();
    const { setDisplayName, setProfilePhoto, initFromStorage, profilePhotoUrl } = useProfile();
    const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '');

    const buildApiGetUrl = (path: string, params: Record<string, string | number> = {}) => {
      const normalizedPath = path.startsWith('/') ? path : `/${path}`;
      const base = API_URL;

      if (base.includes('?api=')) {
        const [basePath, baseQuery = ''] = base.split('?');
        const search = new URLSearchParams(baseQuery);
        const apiPath = search.get('api') || '';
        search.set('api', `${apiPath}${normalizedPath}`);
        for (const [key, value] of Object.entries(params)) {
          search.set(key, String(value));
        }
        return `${basePath}?${search.toString()}`;
      }

      const search = new URLSearchParams();
      for (const [key, value] of Object.entries(params)) {
        search.set(key, String(value));
      }
      const query = search.toString();
      const url = `${base}${normalizedPath}`;
      return query ? `${url}?${query}` : url;
    };

    const doctor = reactive({
      user_id: null as number | null,
      username: "",
      email: "",
      password: "",
      profilePhotoUrl: "",
      profilePhotoFile: null as File | null,
      prescriptionFile: null as File | null,
      medicalCertificateFile: null as File | null,
      labRequestFile: null as File | null,
      prescriptionFileName: "",
      medcertFileName: "",
      labRequestFileName: "",
      prescriptionUrl: "",
      medicalCertificateUrl: "",
      labRequestUrl: "",
    });

    const isEditing = ref(false);
    const isSaving = ref(false);
    const passwordFieldVersion = ref(0);
    const profileImageVersion = ref(Date.now());
    const useDirectProfileImage = ref(true);
    const patients = ref<Array<{ patient_name?: string; age?: string; gender?: string; address?: string; last_date?: string }>>([]);
    const patientsLoading = ref(false);
    const serverUnreachable = ref(false);

    const formatDate = (d: string) => {
      if (!d) return "";
      const date = new Date(d);
      return date.toLocaleDateString();
    };

    const fetchProfile = async () => {
      if (!doctor.user_id) return;
      try {
        serverUnreachable.value = false;
        const res = await axios.get(`${API_URL}/api/doctor/profile`, { params: { user_id: doctor.user_id } });
        const p = res.data;
        doctor.username = p.username ?? doctor.username;
        doctor.email = p.email ?? doctor.email;
        if (p.profile_photo) {
          doctor.profilePhotoUrl = p.profile_photo;
          useDirectProfileImage.value = true;
          profileImageVersion.value = Date.now();
        }
        doctor.prescriptionUrl = p.prescription ?? "";
        doctor.medicalCertificateUrl = p.medical_certificate ?? "";
        doctor.labRequestUrl = p.lab_request ?? "";
        doctor.prescriptionFileName = p.prescription_file_name ?? "";
        doctor.medcertFileName = p.medical_certificate_file_name ?? "";
        doctor.labRequestFileName = p.lab_request_file_name ?? "";
        if (p.profile_photo) {
          setProfilePhoto(p.profile_photo);
          const userStr = localStorage.getItem("whitecoat_user");
          if (userStr) {
            try {
              const user = JSON.parse(userStr);
              user.profile_photo = p.profile_photo;
              localStorage.setItem("whitecoat_user", JSON.stringify(user));
            } catch {
              // ignore
            }
          }
        }
        const fileMeta = {
          user_id: doctor.user_id,
          prescriptionFileName: doctor.prescriptionFileName,
          medicalCertificateFileName: doctor.medcertFileName,
          labRequestFileName: doctor.labRequestFileName,
          prescriptionUrl: doctor.prescriptionUrl,
          medicalCertificateUrl: doctor.medicalCertificateUrl,
          labRequestUrl: doctor.labRequestUrl,
        };
        localStorage.setItem("whitecoat_files", JSON.stringify(fileMeta));
      } catch (err: unknown) {
        const isNetworkError = axios.isAxiosError(err) && (err.code === "ERR_NETWORK" || err.message === "Network Error");
        if (isNetworkError) serverUnreachable.value = true;
        // fallback: keep data from localStorage (already loaded in onMounted)
      }
    };

    const fetchPatients = async () => {
      if (!doctor.user_id) return;
      patientsLoading.value = true;
      try {
        const res = await axios.get(`${API_URL}/api/doctor/patients`, { params: { user_id: doctor.user_id } });
        patients.value = Array.isArray(res.data) ? res.data : [];
      } catch {
        patients.value = [];
      } finally {
        patientsLoading.value = false;
      }
    };

    onMounted(() => {
      initFromStorage();
      const stored = localStorage.getItem("whitecoat_user");
      if (stored) {
        try {
          const user = JSON.parse(stored);
          doctor.user_id = user.user_id ?? null;
          doctor.username = user.username ?? "";
          doctor.email = user.email ?? "";
          doctor.profilePhotoUrl = user.profile_photo ?? "";
        } catch {
          // ignore
        }
      }

      const storedFiles = localStorage.getItem("whitecoat_files");
      if (storedFiles) {
        try {
          const files = JSON.parse(storedFiles);
          if (!doctor.user_id || files.user_id === doctor.user_id) {
            doctor.prescriptionFileName = files.prescriptionFileName || "";
            doctor.medcertFileName = files.medicalCertificateFileName || "";
            doctor.labRequestFileName = files.labRequestFileName || "";
            doctor.prescriptionUrl = files.prescriptionUrl || "";
            doctor.medicalCertificateUrl = files.medicalCertificateUrl || "";
            doctor.labRequestUrl = files.labRequestUrl || "";
          }
        } catch {
          // ignore
        }
      }

      if (doctor.user_id) {
        fetchProfile();
        fetchPatients();
      }
    });

    const isLoggedIn = () => !!localStorage.getItem("whitecoat_user");

    const enableEdit = () => {
      if (!isLoggedIn()) {
        alert("Please log in to edit your information.");
        router.push({ name: "Login" });
        return;
      }
      passwordFieldVersion.value += 1;
      isEditing.value = true;
    };

    const handlePrescriptionUpload = (event: Event) => {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
        doctor.prescriptionFile = target.files[0];
        doctor.prescriptionFileName = target.files[0].name;
      }
    };

    const handleMedicalCertificateUpload = (event: Event) => {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
        doctor.medicalCertificateFile = target.files[0];
        doctor.medcertFileName = target.files[0].name;
      }
    };

    const handleLabRequestUpload = (event: Event) => {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
        doctor.labRequestFile = target.files[0];
        doctor.labRequestFileName = target.files[0].name;
      }
    };

    const handleProfilePhotoUpload = (event: Event) => {
      const target = event.target as HTMLInputElement;
      if (target.files && target.files[0]) {
        doctor.profilePhotoFile = target.files[0];
      }
    };

    const profileImagePreview = computed(() => {
      if (doctor.profilePhotoFile) {
        return URL.createObjectURL(doctor.profilePhotoFile);
      }

      if (doctor.user_id && useDirectProfileImage.value) {
        return buildApiGetUrl('/api/doctor/profile-photo', {
          user_id: doctor.user_id,
          v: profileImageVersion.value,
        });
      }

      const url = doctor.profilePhotoUrl || profilePhotoUrl.value;
      if (url) return resolveUploadUrl(url);
      return defaultProfileImg;
    });

    const onProfileImageError = (event: Event) => {
      const currentSrc = ((event.target as HTMLImageElement | null)?.src || "").toLowerCase();
      if (currentSrc.includes('profile-photo')) {
        useDirectProfileImage.value = false;
      }
    };

    const viewFile = (type: "prescription" | "medical" | "laboratory") => {
      let url = "";
      if (type === "prescription") url = doctor.prescriptionUrl;
      else if (type === "medical") url = doctor.medicalCertificateUrl;
      else if (type === "laboratory") url = doctor.labRequestUrl;

      if (url) {
        window.open(url, "_blank");
        return;
      }

      const fileToOpen =
        type === "prescription"
          ? doctor.prescriptionFile
          : type === "medical"
          ? doctor.medicalCertificateFile
          : doctor.labRequestFile;

      if (fileToOpen) {
        const fileURL = URL.createObjectURL(fileToOpen);
        window.open(fileURL, "_blank");
      } else {
        alert("No file uploaded yet.");
      }
    };

    const updateDoctor = async () => {
      if (!doctor.user_id) {
        alert("User information is missing. Please log in again.");
        return;
      }

      isSaving.value = true;

      try {
        await axios.put(`${API_URL}/api/doctor/profile`, {
          user_id: doctor.user_id,
          username: doctor.username,
          email: doctor.email,
          password: doctor.password,
        });

        const fileMeta: {
          user_id: number | null;
          prescriptionFileName?: string;
          medicalCertificateFileName?: string;
          labRequestFileName?: string;
          prescriptionUrl?: string;
          medicalCertificateUrl?: string;
          labRequestUrl?: string;
        } = { user_id: doctor.user_id };

        if (doctor.prescriptionFile) {
          const formData = new FormData();
          formData.append("user_id", String(doctor.user_id));
          formData.append("file", doctor.prescriptionFile);

          const res = await axios.post(
            `${API_URL}/api/doctor/upload/prescription`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
          );

          doctor.prescriptionFileName = res.data.fileName;
          doctor.prescriptionUrl = res.data.fileUrl;
          fileMeta.prescriptionFileName = res.data.fileName;
          fileMeta.prescriptionUrl = res.data.fileUrl;
        }

        if (doctor.medicalCertificateFile) {
          const formData = new FormData();
          formData.append("user_id", String(doctor.user_id));
          formData.append("file", doctor.medicalCertificateFile);

          const res = await axios.post(
            `${API_URL}/api/doctor/upload/medical-certificate`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
          );

          doctor.medcertFileName = res.data.fileName;
          doctor.medicalCertificateUrl = res.data.fileUrl;
          fileMeta.medicalCertificateFileName = res.data.fileName;
          fileMeta.medicalCertificateUrl = res.data.fileUrl;
        }

        if (doctor.labRequestFile) {
          const formData = new FormData();
          formData.append("user_id", String(doctor.user_id));
          formData.append("file", doctor.labRequestFile);

          const res = await axios.post(
            `${API_URL}/api/doctor/upload/lab-request`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
          );

          doctor.labRequestFileName = res.data.fileName;
          doctor.labRequestUrl = res.data.fileUrl;
          fileMeta.labRequestFileName = res.data.fileName;
          fileMeta.labRequestUrl = res.data.fileUrl;
        }

        if (doctor.profilePhotoFile) {
          const formData = new FormData();
          formData.append("user_id", String(doctor.user_id));
          formData.append("file", doctor.profilePhotoFile);

          const res = await axios.post(
            `${API_URL}/api/doctor/upload/profile-photo`,
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
          );

          doctor.profilePhotoUrl = res.data.fileUrl;
          setProfilePhoto(res.data.fileUrl);
          useDirectProfileImage.value = true;
          profileImageVersion.value = Date.now();
        }

        localStorage.setItem("whitecoat_files", JSON.stringify(fileMeta));

        const userPayload = {
          user_id: doctor.user_id,
          username: doctor.username,
          email: doctor.email,
          profile_photo: doctor.profilePhotoUrl || undefined,
        };
        localStorage.setItem("whitecoat_user", JSON.stringify(userPayload));
        window.dispatchEvent(new Event('whitecoat-user-updated'));
        setDisplayName(doctor.username);
        if (doctor.profilePhotoUrl) setProfilePhoto(doctor.profilePhotoUrl);

        alert("Doctor information updated successfully!");
        doctor.password = "";
        passwordFieldVersion.value += 1;
        isEditing.value = false;
      } catch (error: unknown) {
        const isNetworkError = axios.isAxiosError(error) && (error.code === "ERR_NETWORK" || error.message === "Network Error");
        if (isNetworkError) {
          alert("Cannot reach the server. Start the PHP backend first: in the backend-php folder run \"php -S localhost:8000 -t public public/router.php\".");
        } else if (axios.isAxiosError(error) && error.response?.data?.message) {
          alert(error.response.data.message);
        } else {
          console.error("Update doctor error:", error);
          alert("Failed to update doctor information. Please try again.");
        }
      } finally {
        isSaving.value = false;
      }
    };

    const logout = () => {
      localStorage.removeItem("token");
      localStorage.removeItem("whitecoat_user");
      localStorage.removeItem("whitecoat_files");
      window.dispatchEvent(new Event('whitecoat-user-updated'));
      router.push({ name: "Login" });
    };

    return {
      doctor,
      isEditing,
      isSaving,
      passwordFieldVersion,
      patients,
      patientsLoading,
      serverUnreachable,
      formatDate,
      enableEdit,
      handlePrescriptionUpload,
      handleMedicalCertificateUpload,
      handleLabRequestUpload,
      handleProfilePhotoUpload,
      onProfileImageError,
      profileImagePreview,
      viewFile,
      updateDoctor,
      logout,
    };
  },
};
</script>

<style scoped>
ion-page,
ion-header,
ion-toolbar,
ion-content {
  --background: #f0f0f0;
  --color: #000000;
}

ion-input {
  --padding-start: 8px;
  --padding-end: 8px;
  --border-color: transparent;
  --highlight-color: none;
}

.file-input-box {
  border: 2px solid #d1d5db;
  border-radius: 8px;
  background: #85b1dd;
  padding: 8px 10px;
  
}

.file-input {
  width: 100%;
}
</style>