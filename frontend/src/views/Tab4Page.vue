<template>
    <ion-page>
      <ion-header>
        <ion-toolbar>
          <Header/>
        </ion-toolbar>
      </ion-header>
      <ion-content :fullscreen="true">
        <div id="form-container" class="px-2 py-4 mx-2 md:mx-10 title-font my-5">
          <p class="text-center text-2xl font-bold">Laboratory Request</p>
          <p class="text-center mb-2">Select the required laboratory tests</p>
  
          <div class="bg-[#D9D9D9] p-4 rounded-lg mb-2">
            <form @submit.prevent>
  
              <!-- Patient Information -->
              <div class="mb-3">
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
  
              <!-- Hematology -->
              <div class="mb-3">
                <button type="button" class="category-toggle" @click="toggleLabSection('hematology')">
                  <span class="font-bold">Hematology</span>
                  <span class="category-toggle-icon">{{ openLabSections.hematology ? '-' : '+' }}</span>
                </button>
                <div v-show="openLabSections.hematology" class="grid grid-cols-2 gap-x-2 mt-1">
                  <label v-for="item in hematologyTests" :key="item" class="flex items-center gap-2 py-1">
                    <input
                      type="checkbox"
                      :value="item"
                      v-model="selectedTests"
                      class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                    />
                    <span class="text-sm">{{ item }}</span>
                  </label>
                </div>
              </div>
  
              <!-- Clinical Microscopy -->
              <div class="mb-3">
                <button type="button" class="category-toggle" @click="toggleLabSection('clinicalMicroscopy')">
                  <span class="font-bold">Clinical Microscopy</span>
                  <span class="category-toggle-icon">{{ openLabSections.clinicalMicroscopy ? '-' : '+' }}</span>
                </button>
                <div v-show="openLabSections.clinicalMicroscopy" class="grid grid-cols-2 gap-x-2 mt-1">
                  <label v-for="item in clinicalMicroscopyTests" :key="item" class="flex items-center gap-2 py-1">
                    <input
                      type="checkbox"
                      :value="item"
                      v-model="selectedTests"
                      class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                    />
                    <span class="text-sm">{{ item }}</span>
                  </label>
                </div>
              </div>
  
              <!-- Blood Chemistry -->
              <div class="mb-3">
                <button type="button" class="category-toggle" @click="toggleLabSection('bloodChemistry')">
                  <span class="font-bold">Blood Chemistry</span>
                  <span class="category-toggle-icon">{{ openLabSections.bloodChemistry ? '-' : '+' }}</span>
                </button>
                <div v-show="openLabSections.bloodChemistry" class="grid grid-cols-2 gap-x-2 mt-1">
                  <label v-for="item in bloodChemistryTests" :key="item" class="flex items-center gap-2 py-1">
                    <input
                      type="checkbox"
                      :value="item"
                      v-model="selectedTests"
                      class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                    />
                    <span class="text-sm">{{ item }}</span>
                  </label>
                </div>
              </div>
  

              <!-- Serology and Diagnostic side by side -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                <!-- Serology -->
                <div>
                  <button type="button" class="category-toggle" @click="toggleLabSection('serology')">
                    <span class="font-bold">Serology</span>
                    <span class="category-toggle-icon">{{ openLabSections.serology ? '-' : '+' }}</span>
                  </button>
                  <div v-show="openLabSections.serology" class="grid grid-cols-2 gap-x-2 mt-1">
                    <label v-for="item in serologyTests" :key="item" class="flex items-center gap-2 py-1">
                      <input
                        type="checkbox"
                        :value="item"
                        v-model="selectedTests"
                        class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                      />
                      <span class="text-sm">{{ item }}</span>
                    </label>
                  </div>
                </div>
                <!-- Diagnostic -->
                <div>
                  <button type="button" class="category-toggle" @click="toggleLabSection('diagnostic')">
                    <span class="font-bold">Diagnostic</span>
                    <span class="category-toggle-icon">{{ openLabSections.diagnostic ? '-' : '+' }}</span>
                  </button>
                  <div v-show="openLabSections.diagnostic" class="mt-1">
                    <div class="grid grid-cols-2 gap-x-2 mb-2">
                      <label v-for="item in diagnosticTests" :key="item" class="flex items-center gap-2 py-1">
                        <input
                          type="checkbox"
                          :value="item"
                          v-model="selectedDiagnosticTests"
                          class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                        />
                        <span class="text-sm">{{ item }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Others (outside categories) -->
              <div class="mb-3">
                <p class="font-bold mb-1">Others</p>
                <div class="mt-1">
                  <div class="grid grid-cols-2 gap-x-2 mb-2">
                    <label v-for="item in otherTests" :key="item" class="flex items-center gap-2 py-1">
                      <input
                        type="checkbox"
                        :value="item"
                        v-model="selectedTests"
                        class="w-4 h-4 accent-[#023E8A] cursor-pointer flex-shrink-0"
                      />
                      <span class="text-sm">{{ item }}</span>
                    </label>
                  </div>
                  <div class="mb-2">
                    <label for="otherLab" class="text-sm">Please specify:</label>
                    <input
                      v-model="otherLabInput"
                      id="otherLab"
                      name="otherLab"
                      placeholder="Other laboratory test(s)..."
                      class="form-input mt-1"
                    />
                  </div>
                </div>
              </div>

              <!-- Impression (outside Diagnostic category) -->
              <div class="mb-3">
                <p class="font-bold mb-1">Impression</p>
                <textarea
                  v-model="diagnosticImpression"
                  id="diagnosticImpression"
                  name="diagnosticImpression"
                  placeholder="Diagnostic impression..."
                  rows="2"
                  class="form-textarea mt-1"
                ></textarea>
              </div>
  
              <!-- Remarks -->
              <div>
                <p class="font-bold mb-1">Remarks</p>
                <textarea
                  v-model="remarks"
                  id="remarks"
                  name="remarks"
                  placeholder="Additional notes or instructions..."
                  rows="3"
                  class="form-textarea"
                ></textarea>
              </div>
  
            </form>
          </div>
  
          <p v-if="labError" class="text-sm text-red-600 mt-2">{{ labError }}</p>
          <div class="flex justify-end mt-3 w-full">
            <ion-button
              fill="clear"
              class="font-bold rounded-lg w-full bg-[#023E8A] text-white normal-case"
              :disabled="isGenerating || !isLabRequestValid"
              @click="generateLabRequest"
            >
              <span v-if="!isGenerating">Generate Lab Request</span>
              <span v-else>Generating…</span>
            </ion-button>
          </div>
        </div>

        <p class="text-gray-500 mt-1 mb-3 flex flex-nowrap items-center justify-center gap-1 text-center whitespace-nowrap leading-none px-2" style="font-size: clamp(8px, 2.7vw, 16px);">
          <span class="leading-none" style="font-size: 1.15em;">©</span>
          <span>All Right Reserved KAYE DELGADO &amp; SHIRLYN CANLOM</span>
        </p>

        <LabRequestModal
          :form="form"
          :selectedTests="allSelectedTests"
          :otherLabInput="otherLabInput"
          :impression="diagnosticImpression"
          :remarks="remarks"
          :isOpen="showModal"
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
import { ref, computed } from 'vue';
import { IonPage, IonHeader, IonToolbar, IonContent, IonButton } from '@ionic/vue';
import axios from 'axios';
import Header from '../components/header.vue';
import LabRequestModal from '../components/LabRequestModal.vue';
import PatientNameWithSuggestions from '../components/PatientNameWithSuggestions.vue';

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

const showModal = ref(false);
const isGenerating = ref(false);
const labError = ref('');
const generatedDocxBlob = ref<Blob | null>(null);
const generatedFilename = ref('lab_request.docx');
const lastGeneratedUserId = ref<number | null>(null);
const selectedPatientId = ref<string | number | null>(null);
const selectedPatientName = ref('');

const form = ref({
  name: '',
  age: '',
  gender: '',
  dateIssued: new Date().toISOString().slice(0, 10),
  address: '',
  patient_type: '',
});

const selectedTests = ref<string[]>([]);
const selectedDiagnosticTests = ref<string[]>([]);
const otherLabInput = ref('');
const diagnosticImpression = ref('');
const remarks = ref('');

const allSelectedTests = computed(() => {
  return Array.from(
    new Set(
      [...selectedTests.value, ...selectedDiagnosticTests.value]
        .map((t) => String(t || '').trim())
        .filter((t) => t !== '')
    )
  );
});

type LabSectionKey = 'hematology' | 'clinicalMicroscopy' | 'bloodChemistry' | 'serology' | 'diagnostic';

const openLabSections = ref<Record<LabSectionKey, boolean>>({
  hematology: false,
  clinicalMicroscopy: false,
  bloodChemistry: false,
  serology: false,
  diagnostic: false,
});

function toggleLabSection(section: LabSectionKey): void {
  openLabSections.value[section] = !openLabSections.value[section];
}

const hematologyTests = [
  'CBC',
  'Blood Typing (ABO/Rh)',
  'ESR',
  'Platelet Count',
  'Prothrombin Time (PT)',
  'APTT',
];

const clinicalMicroscopyTests = [
  'Urinalysis',
  'Fecalysis',
  'Occult Blood',
  'Pregnancy Test',
];

const bloodChemistryTests = [
  'Fasting Blood Sugar',
  'HbA1c',
  'Creatinine',
  'Uric Acid',
  'Cholesterol',
  'Triglycerides',
  'SGPT (ALT)',
  'SGOT (AST)',
  'BUN',
];

const serologyTests = [
  'HBsAg',
  'Anti-HCV',
  'HIV (Anti-HIV 1&2)',
  'VDRL / RPR',
  'Dengue NS1 Antigen',
  'CRP',
];

const otherTests = [
  'TSH / Thyroid Panel',
  'Culture & Sensitivity',
  'COVID-19 Antigen',
  'Sputum AFB',
];

const diagnosticTests = [
  'X-rays',
  'CT scans',
  'MRI',
  'Ultrasound',
  'PET scans',
  'Radionuclide',
  'Pap smear',
];

function onSelectPatient(p: {
  id?: string | number;
  patient_name?: string;
  age?: string | null;
  gender?: string | null;
  address?: string | null;
  patient_type?: string | null;
}) {
  form.value.name = (p.patient_name || '').trim();
  form.value.age = p.age ?? '';
  form.value.gender = p.gender ?? '';
  form.value.address = p.address ?? '';
  form.value.patient_type = p.patient_type ?? '';
  selectedPatientId.value = p.id ?? null;
  selectedPatientName.value = (p.patient_name || '').trim();
}

function resolveSelectedPatientId(): number | null {
  const rawId = selectedPatientId.value;
  if (rawId === null || rawId === undefined) return null;
  const currentName = (form.value.name || '').trim().toLowerCase();
  const sourceName = selectedPatientName.value.trim().toLowerCase();
  if (!currentName || !sourceName || currentName !== sourceName) return null;
  const idText = String(rawId).trim();
  if (!/^[0-9]+$/.test(idText)) return null;
  const numericId = Number(idText);
  return Number.isFinite(numericId) && numericId > 0 ? numericId : null;
}

const isLabRequestValid = computed(() => {
  const f = form.value;
  if (!(f.name && String(f.name).trim())) return false;
  if (!(f.age !== null && f.age !== undefined && String(f.age).trim() !== '')) return false;
  if (!(f.gender && String(f.gender).trim())) return false;
  if (!(f.address && String(f.address).trim())) return false;
  if (!(f.patient_type && String(f.patient_type).trim())) return false;
  const hasTests = allSelectedTests.value.length > 0;
  const hasOther = otherLabInput.value != null && String(otherLabInput.value).trim() !== '';
  return hasTests || hasOther;
});

const generateLabRequest = async () => {
  labError.value = '';
  if (!isLabRequestValid.value) {
    labError.value = 'Please fill in all required fields: Name, Age, Gender, Address, Patient Type, and either at least one selected test or Other Tests.';
    return;
  }

  let user: { user_id?: number } = {};

  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) {
      labError.value = 'Please log in to generate a laboratory request.';
      return;
    }

    user = JSON.parse(stored);
    if (!user.user_id) {
      labError.value = 'Please log in to generate a laboratory request.';
      return;
    }
  } catch {
    labError.value = 'Please log in to generate a laboratory request.';
    return;
  }

  // ✅ Clean selected tests
  const cleanedSelectedTests = allSelectedTests.value;

  // ✅ Clean other tests
  const cleanedOtherTests = otherLabInput.value.trim();

  // ✅ Clean remarks
  const cleanedDiagnosticImpression = diagnosticImpression.value.trim();
  const cleanedRemarks = remarks.value.trim();

  isGenerating.value = true;

  try {
    const res = await axios.post(
      `${API_URL}/api/doctor/lab-request/generate`,
      {
        user_id: user.user_id,
        patient_id: resolveSelectedPatientId(),
        name: form.value.name.trim(),
        age: form.value.age,
        gender: form.value.gender,
        dateIssued: form.value.dateIssued,
        address: form.value.address.trim(),
        patient_type: form.value.patient_type,

        // ✅ Proper separation
        selectedTests: cleanedSelectedTests,
        otherTests: cleanedOtherTests,
        impression: cleanedDiagnosticImpression,
        remarks: cleanedRemarks,
      },
      { responseType: 'blob' }
    );

    const blob = res.data as Blob;
    const disposition = res.headers['content-disposition'];
    const filename = disposition
      ? (disposition.split('filename=')[1] || '')
          .replace(/"/g, '')
          .trim() || 'lab_request.docx'
      : 'lab_request.docx';

    generatedDocxBlob.value = blob;
    generatedFilename.value = filename;
    lastGeneratedUserId.value = user.user_id ?? null;
    showModal.value = true;
  } catch (e: unknown) {
    const err = e as {
      response?: { data?: Blob; status?: number };
      message?: string;
    };

    if (err.response?.data instanceof Blob) {
      try {
        const text = await err.response.data.text();
        const j = JSON.parse(text) as { message?: string };
        labError.value =
          j.message || 'Failed to generate laboratory request.';
      } catch {
        labError.value = 'Failed to generate laboratory request.';
      }
    } else {
      const data = err.response?.data as unknown;

      labError.value =
        (data &&
        typeof data === 'object' &&
        'message' in data
          ? (data as { message?: string }).message
          : null) ||
        'Failed to generate laboratory request. Check that you uploaded a Word .docx template in Profile.';
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
  #form-container {
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
  .category-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f5f7fa;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 8px 10px;
    cursor: pointer;
    text-align: left;
  }
  .category-toggle-icon {
    font-weight: 700;
    width: 18px;
    text-align: center;
  }
  </style>