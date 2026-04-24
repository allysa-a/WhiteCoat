<template>
  <div class="patient-name-suggestions-wrap">
    <input
      :value="modelValue"
      type="text"
      :id="inputId"
      :name="inputName"
      :placeholder="placeholder"
      class="form-input"
      autocomplete="off"
      @input="onInput"
      @focus="showDropdown = true"
      @blur="onBlur"
    />
    <ul
      v-if="showDropdown && filteredSuggestions.length > 0"
      class="suggestions-list"
      role="listbox"
    >
      <li
        v-for="(p, idx) in filteredSuggestions"
        :key="(p.patient_name || '') + (p.age ?? '') + idx"
        class="suggestions-item"
        role="option"
        @mousedown.prevent="selectPatient(p)"
      >
        <span class="suggestions-name">{{ p.patient_name || '—' }}</span>
        <span class="suggestions-meta">{{ [p.age, p.gender].filter(Boolean).join(' · ') || '—' }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

interface PatientSuggestion {
  patient_name?: string;
  full_name?: string;
  name?: string;
  age?: string | null;
  gender?: string | null;
  address?: string | null;
  patient_type?: string | null;
  maintenance?: string | null;
}

const props = withDefaults(
  defineProps<{
    modelValue: string;
    inputId?: string;
    inputName?: string;
    placeholder?: string;
  }>(),
  { inputId: 'patient-name', inputName: 'fullname', placeholder: 'Full name' }
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
  'select-patient': [patient: PatientSuggestion];
  selectPatient: [patient: PatientSuggestion];
}>();

const patients = ref<PatientSuggestion[]>([]);
const showDropdown = ref(false);
const currentUserId = ref<number | null>(null);
let blurTimer: ReturnType<typeof setTimeout> | null = null;
let searchTimer: ReturnType<typeof setTimeout> | null = null;

const filteredSuggestions = computed(() => {
  const q = (props.modelValue || '').trim().toLowerCase();
  if (!q) return patients.value.slice(0, 10);
  return patients.value.filter((p) =>
    getPatientName(p).toLowerCase().includes(q)
  ).slice(0, 10);
});

function getPatientName(p: PatientSuggestion): string {
  return (p.patient_name || p.full_name || p.name || '').trim();
}

function onInput(e: Event) {
  const target = e.target as HTMLInputElement;
  emit('update:modelValue', target.value);
  showDropdown.value = true;
  if (searchTimer) {
    clearTimeout(searchTimer);
  }
  searchTimer = setTimeout(() => {
    fetchPatients(target.value);
  }, 180);
}

function onBlur() {
  blurTimer = setTimeout(() => {
    showDropdown.value = false;
    blurTimer = null;
  }, 180);
}

function selectPatient(p: PatientSuggestion) {
  const name = getPatientName(p);
  emit('update:modelValue', name);
  emit('select-patient', p);
  emit('selectPatient', p);
  showDropdown.value = false;
  if (blurTimer) {
    clearTimeout(blurTimer);
    blurTimer = null;
  }
}

async function fetchPatients(query = '') {
  if (currentUserId.value == null) {
    patients.value = [];
    return;
  }

  try {
    const params: Record<string, string | number> = {
      user_id: currentUserId.value,
      limit: 50,
    };
    const trimmed = query.trim();
    if (trimmed !== '') {
      params.q = trimmed;
    }

    const res = await axios.get(`${API_URL}/api/doctor/patients`, { params });
    patients.value = Array.isArray(res.data)
      ? res.data.map((item: PatientSuggestion) => ({
          ...item,
          patient_name: getPatientName(item),
        }))
      : [];
  } catch {
    patients.value = [];
  }
}

onMounted(async () => {
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (stored) {
      const user = JSON.parse(stored) as { user_id?: number };
      currentUserId.value = user?.user_id ?? null;
    }
  } catch {
    /* ignore */
  }

  await fetchPatients(props.modelValue || '');
});
</script>

<style scoped>
.patient-name-suggestions-wrap {
  position: relative;
  width: 100%;
}

.suggestions-list {
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  margin: 0;
  padding: 0;
  list-style: none;
  background: #fff;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  max-height: 220px;
  overflow-y: auto;
  z-index: 50;
}

.suggestions-item {
  padding: 10px 12px;
  cursor: pointer;
  border-bottom: 1px solid #eee;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.suggestions-item:last-child {
  border-bottom: none;
}

.suggestions-item:hover {
  background: #f0f4ff;
}

.suggestions-name {
  font-weight: 600;
  color: #111;
}

.suggestions-meta {
  font-size: 0.8125rem;
  color: #6b7280;
}

.form-input {
  background: white;
  border-radius: 2px;
  padding: 8px 10px;
  width: 100%;
  border: 1px solid #ccc;
  font: inherit;
  box-sizing: border-box;
}
</style>
