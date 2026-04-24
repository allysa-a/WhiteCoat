<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <Header/>
      </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div id="form-container" class="px-2 py-4 mx-2 md:mx-10 title-font my-5">
        <p class="text-center text-2xl font-bold">Prescription</p>
        <p class="text-center mb-2">Digital prescription for a patient</p>

        <div class="bg-[#D9D9D9] p-4 rounded-lg mb-2">
          <form @submit.prevent>
            <div>
              <p class="font-bold">Patient Information</p>

              <div class="flex justify-between items-center gap-2">
                <div class="w-full">
                  <label for="fullname">Full Name:</label>
                  <PatientNameWithSuggestions
                    v-model="form.name"
                    input-id="fullname"
                    input-name="fullname"
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
                <input v-model="form.address" id="address" name="address" placeholder="Address" class="form-input" />
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

              <div>
                <label for="maintenance" class="mt-2">Maintenance</label>
                <input
                  v-model="form.maintenance"
                  id="maintenance"
                  name="maintenance"
                  type="text"
                  placeholder="Maintenance"
                  class="form-input"
                />
              </div>

              <div>
                <label for="reason_for_referral" class="mt-2">Reason for Referral:</label>
                <input
                  v-model="form.reason_for_referral"
                  id="reason_for_referral"
                  name="reason_for_referral"
                  type="text"
                  placeholder="Reason for referral"
                  class="form-input"
                />
              </div>
            </div>
          </form>
        </div>

        <div class="bg-[#D9D9D9] p-4 rounded-lg mb-2">
          <form action="">
            <div>
              <div class="flex justify-between items-center">
                <p class="font-bold">Medication</p>
                <div>
                  <ion-button
                    fill="clear"
                    class="font-bold mb-4 border border-black hover:bg-white transition duration-300 ease-in-out text-sm text-gray-600 rounded-lg normal-case flex items-center gap-2"
                    @click.prevent="addMedication"
                  >
                    <font-awesome-icon :icon="['fas', 'plus']" class="text-lg mr-2 text-gray-600 cursor-pointer align-middle"/>
                    Add Medication
                  </ion-button>
                </div>
              </div>

              <!-- Medication List -->
              <div
                v-for="(med, index) in form.medications"
                :key="index"
                class="border border-black rounded-lg p-2 gap-2 mb-2"
              >
                <div class="flex justify-between items-center">
                  <p class="font-bold">Medication {{ index + 1 }}</p>
                  <ion-button
                    fill="clear"
                    color="danger"
                    class="font-bold mb-2 border border-black hover:bg-white transition duration-300 ease-in-out text-sm text-gray-600 rounded-lg normal-case"
                    @click.prevent="removeMedication(index)"
                  >
                    Remove
                  </ion-button>
                </div>

                <div>
                  <label>Medication:</label>
                  <input v-model="med.name" placeholder="e.g., Amoxicillin 500mg" class="form-input med-input" />
                </div>
                <div class="flex justify-between items-center gap-2">
                  <div class="w-full suggestion-wrap">
                    <label class="mt-2">Dosage:</label>
                    <input
                      v-model="med.dosage"
                      placeholder="Select or type dosage"
                      class="form-input med-input"
                      autocomplete="off"
                      @focus="openSuggestion(index, 'dosage')"
                      @input="openSuggestion(index, 'dosage')"
                      @blur="scheduleCloseSuggestions"
                    />
                    <div
                      v-if="isSuggestionOpen(index, 'dosage') && getFilteredSuggestions('dosage', med.dosage).length"
                      class="suggestion-list"
                    >
                      <button
                        v-for="option in getFilteredSuggestions('dosage', med.dosage)"
                        :key="`dosage-${index}-${option}`"
                        type="button"
                        class="suggestion-item"
                        @pointerdown.prevent="applySuggestion(index, 'dosage', option)"
                      >
                        {{ option }}
                      </button>
                    </div>
                  </div>
                  <div class="w-full suggestion-wrap">
                    <label class="mt-2">Frequency:</label>
                    <input
                      v-model="med.frequency"
                      placeholder="Select or type frequency"
                      class="form-input med-input"
                      autocomplete="off"
                      @focus="openSuggestion(index, 'frequency')"
                      @input="openSuggestion(index, 'frequency')"
                      @blur="scheduleCloseSuggestions"
                    />
                    <div
                      v-if="isSuggestionOpen(index, 'frequency') && getFilteredSuggestions('frequency', med.frequency).length"
                      class="suggestion-list"
                    >
                      <button
                        v-for="option in getFilteredSuggestions('frequency', med.frequency)"
                        :key="`frequency-${index}-${option}`"
                        type="button"
                        class="suggestion-item"
                        @pointerdown.prevent="applySuggestion(index, 'frequency', option)"
                      >
                        {{ option }}
                      </button>
                    </div>
                  </div>
                </div>
                <div class="suggestion-wrap">
                  <label class="mt-2">Duration:</label>
                  <input
                    v-model="med.duration"
                    placeholder="Select or type duration"
                    class="form-input med-input"
                    autocomplete="off"
                    @focus="openSuggestion(index, 'duration')"
                    @input="openSuggestion(index, 'duration')"
                    @blur="scheduleCloseSuggestions"
                  />
                  <div
                    v-if="isSuggestionOpen(index, 'duration') && getFilteredSuggestions('duration', med.duration).length"
                    class="suggestion-list"
                  >
                    <button
                      v-for="option in getFilteredSuggestions('duration', med.duration)"
                      :key="`duration-${index}-${option}`"
                      type="button"
                      class="suggestion-item"
                      @pointerdown.prevent="applySuggestion(index, 'duration', option)"
                    >
                      {{ option }}
                    </button>
                  </div>
                </div>
                <div class="suggestion-wrap">
                  <label class="mt-2">Instructions:</label>
                  <input
                    v-model="med.instructions"
                    placeholder="Select or type instructions"
                    class="form-input med-input"
                    autocomplete="off"
                    @focus="openSuggestion(index, 'instructions')"
                    @input="openSuggestion(index, 'instructions')"
                    @blur="scheduleCloseSuggestions"
                  />
                  <div
                    v-if="isSuggestionOpen(index, 'instructions') && getFilteredSuggestions('instructions', med.instructions).length"
                    class="suggestion-list"
                  >
                    <button
                      v-for="option in getFilteredSuggestions('instructions', med.instructions)"
                      :key="`instructions-${index}-${option}`"
                      type="button"
                      class="suggestion-item"
                      @pointerdown.prevent="applySuggestion(index, 'instructions', option)"
                    >
                      {{ option }}
                    </button>
                  </div>
                </div>
              </div>

            </div>
          </form>
        </div>

        <p v-if="prescriptionError" class="text-sm text-red-600 mt-2">{{ prescriptionError }}</p>
        <div class="flex justify-end mt-3 w-full">
          <ion-button
            fill="clear"
            class="font-bold rounded-lg w-full bg-[#023E8A] text-white normal-case"
            :disabled="isGenerating || !isPrescriptionValid"
            @click="createPrescription"
          >
            <span v-if="!isGenerating">Create Prescription</span>
            <span v-else>Generating…</span>
          </ion-button>
        </div>
      </div>

      <p class="text-gray-500 mt-1 mb-3 flex flex-nowrap items-center justify-center gap-1 text-center whitespace-nowrap leading-none px-2" style="font-size: clamp(8px, 2.7vw, 16px);">
        <span class="leading-none" style="font-size: 1.15em;">©</span>
        <span>All Right Reserved KAYE DELGADO &amp; SHIRLYN CANLOM</span>
      </p>

        <PrescriptionModal
          :form="form"
          :isOpen="showModal"
          :templateImageUrl="prescriptionTemplateUrl ?? undefined"
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
import { IonPage, IonHeader, IonToolbar, IonContent, IonButton } from "@ionic/vue";
import axios from "axios";
import Header from "../components/header.vue";
import PrescriptionModal from "../components/PrescriptionModal.vue";
import PatientNameWithSuggestions from "../components/PatientNameWithSuggestions.vue";

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

const showModal = ref(false);
const isGenerating = ref(false);
const prescriptionError = ref("");
const generatedDocxBlob = ref<Blob | null>(null);
const generatedFilename = ref("prescription.docx");
const lastGeneratedUserId = ref<number | null>(null);
const selectedPatientId = ref<string | number | null>(null);
const selectedPatientName = ref("");

const form = ref({
  name: "",
  age: "",
  gender: "",
  dateIssued: new Date().toISOString().slice(0, 10),
  address: "",
  patient_type: "",
  maintenance: "",
  reason_for_referral: "",
  medications: [
    { name: "", dosage: "", frequency: "", duration: "", instructions: "" },
  ],
});

const prescriptionTemplateUrl = ref<string | null>(null);

const dosageOptions: Array<{ category: string; options: string[] }> = [
  {
    category: 'Tablet/Capsule',
    options: ['1/2 tablet', '1 tablet', '2 tablets', '1 capsule', '2 capsules'],
  },
  {
    category: 'Liquid',
    options: ['2.5 mL', '5 mL', '10 mL', '15 mL'],
  },
  {
    category: 'Drops',
    options: ['1 drop', '2 drops', '3 drops'],
  },
  {
    category: 'Weight',
    options: ['125 mg', '250 mg', '500 mg', '1 g'],
  },
];

const dosageOptionValues = computed(() =>
  dosageOptions.flatMap((group) => group.options)
);

type SuggestionField = 'dosage' | 'frequency' | 'duration' | 'instructions';

const activeSuggestion = ref<{ index: number; field: SuggestionField } | null>(null);
let suggestionCloseTimer: ReturnType<typeof setTimeout> | null = null;

function openSuggestion(index: number, field: SuggestionField): void {
  if (suggestionCloseTimer) {
    clearTimeout(suggestionCloseTimer);
    suggestionCloseTimer = null;
  }
  activeSuggestion.value = { index, field };
}

function scheduleCloseSuggestions(): void {
  if (suggestionCloseTimer) clearTimeout(suggestionCloseTimer);
  suggestionCloseTimer = setTimeout(() => {
    activeSuggestion.value = null;
  }, 120);
}

function isSuggestionOpen(index: number, field: SuggestionField): boolean {
  return !!activeSuggestion.value && activeSuggestion.value.index === index && activeSuggestion.value.field === field;
}

function getFilteredSuggestions(field: SuggestionField, query: string): string[] {
  const source = field === 'dosage'
    ? dosageOptionValues.value
    : field === 'frequency'
      ? frequencyOptions
      : field === 'duration'
        ? durationOptionValues.value
        : instructionOptionValues.value;
  const q = String(query || '').trim().toLowerCase();
  if (!q) return source;
  return source.filter((item) => item.toLowerCase().includes(q));
}

function applySuggestion(index: number, field: SuggestionField, value: string): void {
  const med = form.value.medications[index];
  if (!med) return;
  if (field === 'dosage') {
    med.dosage = value;
  } else if (field === 'frequency') {
    med.frequency = value;
  } else if (field === 'duration') {
    med.duration = value;
  } else {
    med.instructions = value;
  }
  activeSuggestion.value = null;
}

const frequencyOptions: string[] = [
  'Once daily',
  'Twice daily (BID)',
  'Three times daily (TID)',
  'Four times daily (QID)',
  'Every 4 hours',
  'Every 6 hours',
  'Every 8 hours',
  'Every 12 hours',
  'Every 24 hours',
  'Every other day',
  'Once weekly',
  'As needed (PRN)',
];

const durationOptions: Array<{ category: string; options: string[] }> = [
  {
    category: 'Days',
    options: ['1 day', '3 days', '5 days', '7 days', '10 days', '14 days'],
  },
  {
    category: 'Weeks',
    options: ['1 week', '2 weeks', '3 weeks', '4 weeks'],
  },
  {
    category: 'Months',
    options: ['1 month', '3 months', '6 months'],
  },
  {
    category: 'Special',
    options: ['Until finished', 'As needed', 'Until follow-up visit'],
  },
];

const durationOptionValues = computed(() =>
  durationOptions.flatMap((group) => group.options)
);

const instructionOptions: Array<{ category: string; options: string[] }> = [
  {
    category: 'Meal related',
    options: ['Before meals', 'After meals', 'With meals', 'Empty stomach'],
  },
  {
    category: 'Timing',
    options: ['Morning', 'Evening', 'At bedtime'],
  },
  {
    category: 'Method',
    options: ['Swallow whole', 'Shake well before use', 'Take with water'],
  },
  {
    category: 'Symptom',
    options: ['As needed for pain', 'As needed for fever'],
  },
  {
    category: 'Safety',
    options: ['Complete the full course', 'Avoid alcohol'],
  },
];

const instructionOptionValues = computed(() =>
  instructionOptions.flatMap((group) => group.options)
);

function onSelectPatient(p: { patient_name?: string; age?: string | null; gender?: string | null; address?: string | null; patient_type?: string | null; maintenance?: string | null }) {
  form.value.name = (p.patient_name || "").trim();
  form.value.age = p.age ?? "";
  form.value.gender = p.gender ?? "";
  form.value.address = p.address ?? "";
  form.value.patient_type = p.patient_type ?? "";
  form.value.maintenance = p.maintenance ?? "";
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

onMounted(() => {
  try {
    const stored = localStorage.getItem("whitecoat_files");
    if (stored) {
      const files = JSON.parse(stored);
      if (files.prescriptionUrl) prescriptionTemplateUrl.value = files.prescriptionUrl;
    }
  } catch {
    // ignore
  }
});

const addMedication = () => {
  form.value.medications.push({
    name: "",
    dosage: "",
    frequency: "",
    duration: "",
    instructions: "",
  });
};

const removeMedication = (index: number) => {
  if (form.value.medications.length <= 1) return;
  form.value.medications.splice(index, 1);
};

const isPrescriptionValid = computed(() => {
  const f = form.value;
  if (!(f.name && String(f.name).trim())) return false;
  if (!(f.age !== null && f.age !== undefined && String(f.age).trim() !== "")) return false;
  if (!(f.gender && String(f.gender).trim())) return false;
  if (!(f.address && String(f.address).trim())) return false;
  if (!(f.patient_type && String(f.patient_type).trim())) return false;
  if (!(f.reason_for_referral && String(f.reason_for_referral).trim())) return false;
  if (!Array.isArray(f.medications) || f.medications.length === 0) return false;

  return f.medications.every(
    (m: { name?: string; dosage?: string; frequency?: string; duration?: string; instructions?: string }) =>
      !!(m.name && String(m.name).trim()) &&
      !!(m.dosage && String(m.dosage).trim()) &&
      !!(m.frequency && String(m.frequency).trim()) &&
      !!(m.duration && String(m.duration).trim()) &&
      !!(m.instructions && String(m.instructions).trim())
  );
});

const createPrescription = async () => {
  prescriptionError.value = "";
  if (!isPrescriptionValid.value) {
    prescriptionError.value = "Please fill in all required fields, including Patient Type, Reason for Referral, and complete details for every medication.";
    return;
  }
  let user: { user_id?: number } = {};
  try {
    const stored = localStorage.getItem("whitecoat_user");
    if (!stored) {
      prescriptionError.value = "Please log in to create a prescription.";
      return;
    }
    user = JSON.parse(stored);
    if (!user.user_id) {
      prescriptionError.value = "Please log in to create a prescription.";
      return;
    }
  } catch {
    prescriptionError.value = "Please log in to create a prescription.";
    return;
  }

  isGenerating.value = true;
  try {
    const res = await axios.post(
      `${API_URL}/api/doctor/prescription/generate`,
      {
        user_id: user.user_id,
        patient_id: resolveSelectedPatientId(),
        name: form.value.name,
        age: form.value.age,
        gender: form.value.gender,
        dateIssued: form.value.dateIssued,
        address: form.value.address,
        patient_type: form.value.patient_type,
        maintenance: form.value.maintenance,
        reason_for_referral: form.value.reason_for_referral,
        medications: form.value.medications,
      },
      { responseType: "blob" }
    );

    const blob = res.data as Blob;
    const disposition = res.headers["content-disposition"];
    const filename = disposition
      ? (disposition.split("filename=")[1] || "").replace(/"/g, "").trim() || "prescription.docx"
      : "prescription.docx";

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
        prescriptionError.value = j.message || "Failed to generate prescription.";
      } catch {
        prescriptionError.value = "Failed to generate prescription.";
      }
    } else {
      const data = err.response?.data as unknown;
      prescriptionError.value =
        (data && typeof data === "object" && "message" in data
          ? (data as { message?: string }).message
          : null) ||
        "Failed to generate prescription. Check that you uploaded a Word .docx template in Profile.";
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
.suggestion-wrap {
  position: relative;
}
.suggestion-list {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.12);
  max-height: 180px;
  overflow-y: auto;
  z-index: 20;
}
.suggestion-item {
  width: 100%;
  text-align: left;
  background: #fff;
  border: none;
  border-bottom: 1px solid #f1f1f1;
  padding: 9px 10px;
  font: inherit;
  cursor: pointer;
}
.suggestion-item:last-child {
  border-bottom: none;
}
.suggestion-item:hover {
  background: #f8fafc;
}
</style>
