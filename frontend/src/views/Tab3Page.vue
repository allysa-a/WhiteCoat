<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <Header/>
      </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div id="form-container" class="px-2 py-4 mx-2 md:mx-10 title-font my-5">
        <p class="text-center text-2xl font-bold">Medical Certificate</p>
        <p class="text-center mb-2">Digital certificate for a patient</p>

        <div class="bg-[#D9D9D9] p-4 rounded-lg mb-2">
          <form @submit.prevent>
            <div>
              <p class="font-bold">Patient Information</p>

              <div class="flex justify-between items-center gap-2">
                <div class="w-full">
                  <label for="name">Name:</label>
                  <PatientNameWithSuggestions
                    v-model="form.name"
                    input-id="name"
                    input-name="name"
                    placeholder="Full name"
                    @select-patient="onSelectPatient"
                  />
                </div>
                <div class="w-full">
                  <label for="age" class="mt-2">Age:</label>
                  <input v-model="form.age" id="age" name="age" type="number" placeholder="Age" class="form-input" />
                </div>
              </div>

              <div class="flex justify-between items-center gap-2">
                  <div class="w-full">
                    <label for="gender" class="mt-2">Gender:</label>
                    <select
                      v-model="form.gender"
                      id="gender"
                      name="gender"
                      class="form-input"
                    >
                      <option value="" disabled>Select gender</option>
                      <option value="Male">Male</option>
                      <option value="Female">Female</option>
                    </select>
                  </div>
                <div class="w-full">
                  <label for="dateIssued" class="mt-2">Date Issued:</label>
                  <input v-model="form.dateIssued" id="dateIssued" name="dateIssued" type="date" class="form-input" readonly disabled/>
                </div>
              </div>

              <div>
                <label for="address" class="mt-2">Address:</label>
                <textarea v-model="form.address" id="address" name="address" placeholder="Address" rows="2" class="form-textarea"></textarea>
              </div>

              <div>
                <label for="patient_type" class="mt-2">Patient Type:</label>
                <select
                  v-model="form.patient_type"
                  id="patient_type"
                  name="patient_type"
                  class="form-input"
                >
                  <option value="" disabled>Select patient type</option>
                  <option value="Non-Teaching">Non-Teaching</option>
                  <option value="Teaching">Teaching</option>
                  <option value="Learner">Learner</option>
                  <option value="School Head">School Head</option>
                  <option value="Related-Teaching">Related-Teaching</option>
                </select>
              </div>
            </div>

            <div class="mt-2">
              <p class="font-bold">Clinical Information</p>
              <div>
                <label for="impression" class="mt-2">Impression:</label>
                <textarea v-model="form.impression" id="impression" name="impression" placeholder="Describe the patient's clinical findings..." rows="3" class="form-textarea"></textarea>
              </div>
              <div>
                <label for="remarks" class="mt-2">Remarks:</label>
                <textarea v-model="form.remarks" id="remarks" name="remarks" placeholder="Regard with attention..." rows="3" class="form-textarea"></textarea>
              </div>
            </div>
          </form>
        </div>

        <p v-if="certificateError" class="text-sm text-red-600 mt-2">{{ certificateError }}</p>
        <div class="flex justify-end mt-3 w-full">
          <ion-button
            fill="clear"
            class="font-bold rounded-lg w-full bg-[#023E8A] text-white normal-case"
            :disabled="isGenerating || !isCertificateValid"
            @click="generateCertificate"
          >
            <span v-if="!isGenerating">Generate Certificate</span>
            <span v-else>Generating…</span>
          </ion-button>
        </div>
      </div>

      <p class="text-gray-500 mt-1 mb-3 flex flex-nowrap items-center justify-center gap-1 text-center whitespace-nowrap leading-none px-2" style="font-size: clamp(8px, 2.7vw, 16px);">
        <span class="leading-none" style="font-size: 1.15em;">©</span>
        <span>All Right Reserved KAYE DELGADO &amp; SHIRLYN CANLOM</span>
      </p>

      <MedicalCertificateModal
        :form="form"
        :isOpen="showModal"
        :templateImageUrl="medicalCertificateTemplateUrl ?? undefined"
        :generated-docx-blob="generatedDocxBlob"
        :generated-filename="generatedFilename"
        :user-id="lastGeneratedUserId ?? undefined"
        :api-url="API_URL"
        @close="showModal = false"
        @edit="showModal = false"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { IonPage, IonHeader, IonToolbar, IonContent } from "@ionic/vue";
import axios from "axios";
import Header from "../components/header.vue";
import MedicalCertificateModal from "../components/MedicalCertificateModal.vue";
import PatientNameWithSuggestions from "../components/PatientNameWithSuggestions.vue";

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

const showModal = ref(false);
const isGenerating = ref(false);
const certificateError = ref("");
const generatedDocxBlob = ref<Blob | null>(null);
const generatedFilename = ref("medical_certificate.docx");
const lastGeneratedUserId = ref<number | null>(null);
const medicalCertificateTemplateUrl = ref<string | null>(null);
const selectedPatientId = ref<string | number | null>(null);
const selectedPatientName = ref("");

const form = ref({
  name: "",
  age: "",
  gender: "",
  dateIssued: new Date().toISOString().slice(0, 10),
  address: "",
  patient_type: "",
  impression: "",
  remarks: "",
});

onMounted(() => {
  try {
    const stored = localStorage.getItem("whitecoat_files");
    if (stored) {
      const files = JSON.parse(stored);
      if (files.medicalCertificateUrl) medicalCertificateTemplateUrl.value = files.medicalCertificateUrl;
    }
  } catch {
    // ignore
  }
});

function onSelectPatient(p: { patient_name?: string; age?: string | null; gender?: string | null; address?: string | null; patient_type?: string | null }) {
  form.value.name = (p.patient_name || "").trim();
  form.value.age = p.age ?? "";
  form.value.gender = p.gender ?? "";
  form.value.address = p.address ?? "";
  form.value.patient_type = p.patient_type ?? "";
  selectedPatientId.value = (p as { id?: string | number }).id ?? null;
  selectedPatientName.value = (p.patient_name || "").trim();
}

function resolveSelectedPatientId(): number | null {
  const rawId = selectedPatientId.value;
  if (rawId === null || rawId === undefined) return null;
  const currentName = (form.value.name || "").trim().toLowerCase();
  const sourceName = selectedPatientName.value.trim().toLowerCase();
  if (!currentName || !sourceName || currentName !== sourceName) return null;
  const idText = String(rawId).trim();
  if (!/^[0-9]+$/.test(idText)) return null;
  const numericId = Number(idText);
  return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

const isCertificateValid = computed(() => {
  const f = form.value;
  return !!(
    f.name && String(f.name).trim() &&
    f.age !== null && f.age !== undefined && String(f.age).trim() !== "" &&
    f.gender && String(f.gender).trim() &&
    f.address && String(f.address).trim() &&
    f.patient_type && String(f.patient_type).trim() &&
    f.impression && String(f.impression).trim() &&
    f.remarks && String(f.remarks).trim()
  );
});

const generateCertificate = async () => {
  certificateError.value = "";
  if (!isCertificateValid.value) {
    certificateError.value = "Please fill in all required fields: Name, Age, Gender, Address, Patient Type, Impression, and Remarks.";
    return;
  }
  let user: { user_id?: number } = {};
  try {
    const stored = localStorage.getItem("whitecoat_user");
    if (!stored) {
      certificateError.value = "Please log in to generate a medical certificate.";
      return;
    }
    user = JSON.parse(stored);
    if (!user.user_id) {
      certificateError.value = "Please log in to generate a medical certificate.";
      return;
    }
  } catch {
    certificateError.value = "Please log in to generate a medical certificate.";
    return;
  }

  isGenerating.value = true;
  try {
    const res = await axios.post(
      `${API_URL}/api/doctor/medical-certificate/generate`,
      {
        user_id: user.user_id,
        patient_id: resolveSelectedPatientId(),
        name: form.value.name,
        age: form.value.age,
        gender: form.value.gender,
        dateIssued: form.value.dateIssued,
        address: form.value.address,
        patient_type: form.value.patient_type,
        impression: form.value.impression,
        remarks: form.value.remarks,
      },
      { responseType: "blob" }
    );

    const blob = res.data as Blob;
    const disposition = res.headers["content-disposition"];
    const filename = disposition
      ? (disposition.split("filename=")[1] || "").replace(/"/g, "").trim() || "medical_certificate.docx"
      : "medical_certificate.docx";

    generatedDocxBlob.value = blob;
    generatedFilename.value = filename;
    lastGeneratedUserId.value = user.user_id ?? null;
    showModal.value = true;
  } catch (e: unknown) {
    const err = e as { response?: { data?: Blob; status?: number }; message?: string };
    if (err.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text();
        const j = JSON.parse(text) as { message?: string };
        certificateError.value = j.message || "Failed to generate medical certificate.";
      } catch {
        certificateError.value = "Failed to generate medical certificate.";
      }
    } else {
      const data = err.response?.data as unknown;
      certificateError.value =
        (data && typeof data === "object" && "message" in data
          ? (data as { message?: string }).message
          : null) ||
        "Failed to generate medical certificate. Check that you uploaded a Word .docx template in Profile.";
    }
  } finally {
    isGenerating.value = false;
  }
};
</script>

<style scoped>
ion-page, ion-header, ion-toolbar, ion-content {
    --background: #F0F0F0;
    --color: #000000;
}
#form-container{
    background-color: white;
}
.form-input,
.form-textarea {
  background: white;
  border-radius: 2px;
  padding: 8px 10px;
  width: 100%;
  border: 1px solid #ccc;
  font: inherit;
  box-sizing: border-box;
}
.form-textarea {
  resize: vertical;
  min-height: 60px;
}
</style>
