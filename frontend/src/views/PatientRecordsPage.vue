<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <div class="records-header">
          <button type="button" class="back-btn" aria-label="Back" @click="goBack">
            <font-awesome-icon :icon="['fas', 'arrow-left']" class="text-xl" />
          </button>
          <h1 class="records-title">Patient Records</h1>
        </div>
      </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="search-wrap">
        <font-awesome-icon :icon="['fas', 'magnifying-glass']" class="search-icon" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search Patient"
          class="search-input"
        />
      </div>

      <div v-if="loading" class="loading-wrap">
        <p class="text-gray-500">Loading patient records...</p>
      </div>
      <div v-else-if="!filteredPatients.length" class="empty-wrap">
        <p class="text-gray-600">No patient records.</p>
      </div>
      <div v-else class="list-wrap">
        <div
          v-for="patient in filteredPatients"
          :key="`${patient.lastType}-${patient.patientId ?? ''}-${patient.lastDate}`"
          class="patient-card"
          role="button"
          tabindex="0"
          @click="openPatient(patient)"
          @keydown.enter="openPatient(patient)"
        >
          <div class="patient-avatar">
            <font-awesome-icon :icon="['fas', 'user']" class="text-gray-500 text-xl" />
          </div>
          <div class="patient-info">
            <p class="patient-name">{{ patient.patientName || '—' }}</p>
            <p class="patient-meta">
              {{ patient.lastType === 'prescription' ? 'Prescription' : patient.lastType === 'labRequest' ? 'Lab Request' : 'Medical Certificate' }} · {{ patient.lastDateFormatted }}
            </p>
          </div>
        </div>
      </div>

      <HistoryDetailModal
        :isOpen="selectedEntry !== null"
        :entry="selectedEntry"
        :patientHistory="patientHistory"
        @close="selectedEntry = null; patientHistory = null"
        @deleted="handleHistoryDeleted"
      />

      <ion-toast
        :is-open="showDeletedToast"
        message="History record deleted."
        :duration="1400"
        @didDismiss="showDeletedToast = false"
      />
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { IonPage, IonHeader, IonToolbar, IonContent, IonToast } from '@ionic/vue';
import axios from 'axios';
import HistoryDetailModal from '../components/HistoryDetailModal.vue';
import type { HistoryEntryForModal } from '../components/HistoryDetailModal.vue';

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

type HistoryEntry = HistoryEntryForModal;

const router = useRouter();
const searchQuery = ref('');
const loading = ref(true);
const selectedEntry = ref<HistoryEntry | null>(null);
const patientHistory = ref<{
  patient: { id: number; name: string; age?: string; gender?: string; address?: string } | null;
  prescriptions: Record<string, unknown>[];
  medicalCertificates: Record<string, unknown>[];
  labRequests: Record<string, unknown>[];
} | null>(null);
const prescriptionHistory = ref<Array<{ patient_id?: number; patient_name?: string; name?: string; date_issued?: string; created_at?: string }>>([]);
const certificateHistory = ref<Array<{ patient_id?: number; patient_name?: string; name?: string; date_issued?: string; created_at?: string }>>([]);
const labRequestHistory = ref<Array<{ patient_id?: number; patient_name?: string; name?: string; date_issued?: string; created_at?: string }>>([]);
const showDeletedToast = ref(false);

function formatDate(d: string | undefined): string {
  if (!d) return '—';
  const date = new Date(d);
  if (isNaN(date.getTime())) return '—';
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const y = String(date.getFullYear()).slice(-2);
  return `${m}/${day}/${y}`;
}

/** Unified history: prescriptions + medical certificates + lab requests, mixed and sorted latest to oldest */
const unifiedHistory = computed<HistoryEntry[]>(() => {
  const items: HistoryEntry[] = [];
  const add = (patientName: string, patientId: number | undefined, dateStr: string | undefined, type: 'prescription' | 'issuance' | 'labRequest', record: Record<string, unknown>) => {
    const name = patientName || 'Unknown';
    items.push({
      patientName: name,
      patientId,
      lastDate: dateStr || '',
      lastDateFormatted: formatDate(dateStr),
      lastType: type,
      record: { ...record, patient_name: record.patient_name ?? record.name ?? name, name: record.patient_name ?? record.name ?? name },
    });
  };
  prescriptionHistory.value.forEach((row) => {
    const name = (row.patient_name || row.name || '').trim() || 'Unknown';
    add(name, row.patient_id, row.date_issued || row.created_at, 'prescription', row as Record<string, unknown>);
  });
  certificateHistory.value.forEach((row) => {
    const name = (row.patient_name || row.name || '').trim() || 'Unknown';
    add(name, row.patient_id, row.date_issued || row.created_at, 'issuance', row as Record<string, unknown>);
  });
  labRequestHistory.value.forEach((row) => {
    const name = (row.patient_name || row.name || '').trim() || 'Unknown';
    add(name, row.patient_id, row.date_issued || row.created_at, 'labRequest', row as Record<string, unknown>);
  });
  items.sort((a, b) => {
    const timeA = new Date(a.lastDate).getTime();
    const timeB = new Date(b.lastDate).getTime();
    return timeB - timeA; // latest to oldest
  });
  return items;
});

const filteredPatients = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return unifiedHistory.value;
  return unifiedHistory.value.filter((p) => p.patientName.toLowerCase().includes(q));
});

function goBack() {
  router.push('/tabs/tab1');
}

async function openPatient(entry: HistoryEntry) {
  selectedEntry.value = entry;
  patientHistory.value = null;
  const pid = entry.patientId;
  let user_id: number | null = null;
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (stored) {
      const user = JSON.parse(stored) as { user_id?: number };
      user_id = user?.user_id ?? null;
    }
  } catch {
    /* ignore */
  }
  if (pid != null && user_id != null) {
    try {
      const res = await axios.get(`${API_URL}/api/doctor/patient/${pid}/history`, { params: { user_id } });
      patientHistory.value = res.data;
    } catch {
      patientHistory.value = null;
    }
  }
}

async function loadRecords() {
  let user_id: number | null = null;
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) return;
    const user = JSON.parse(stored) as { user_id?: number };
    user_id = user?.user_id ?? null;
  } catch {
    return;
  }
  if (user_id == null) return;
  loading.value = true;
  prescriptionHistory.value = [];
  certificateHistory.value = [];
  labRequestHistory.value = [];
  try {
    const presRes = await axios.get(`${API_URL}/api/doctor/prescription/history`, { params: { user_id } });
    prescriptionHistory.value = Array.isArray(presRes.data) ? presRes.data : [];
  } catch {
    prescriptionHistory.value = [];
  }
  try {
    const certRes = await axios.get(`${API_URL}/api/doctor/medical-certificate/history`, { params: { user_id } });
    certificateHistory.value = Array.isArray(certRes.data) ? certRes.data : [];
  } catch {
    certificateHistory.value = [];
  }
  try {
    const labRes = await axios.get(`${API_URL}/api/doctor/lab-request/history`, { params: { user_id } });
    labRequestHistory.value = Array.isArray(labRes.data) ? labRes.data : [];
  } catch {
    labRequestHistory.value = [];
  }
  loading.value = false;
}

async function handleHistoryDeleted() {
  selectedEntry.value = null;
  patientHistory.value = null;
  await loadRecords();
  showDeletedToast.value = true;
}

onMounted(loadRecords);
</script>

<style scoped>
ion-page,
ion-header,
ion-toolbar,
ion-content {
  --background: #f0f0f0;
  --color: #000000;
}

.records-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 8px;
  min-height: 56px;
}

.back-btn {
  background: none;
  border: none;
  padding: 8px;
  color: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.records-title {
  margin: 0;
  font-size: 1.5rem;
  font-weight: 700;
  color: #111;
}

.search-wrap {
  display: flex;
  align-items: center;
  margin: 8px 12px;
  padding: 0 8px;
  max-width: 100%;
  background: #d9d9d9;
  border-radius: 9999px;
}

.search-icon {
  flex-shrink: 0;
  margin-left: 14px;
  color: #6b7280;
  font-size: 0.9375rem;
}

.search-input {
  flex: 1;
  min-width: 0;
  padding: 10px 16px;
  font-size: 1rem;
  border: none;
  background: transparent;
  color: #000;
}

.loading-wrap,
.empty-wrap {
  padding: 2rem 1rem;
  text-align: center;
}

.list-wrap {
  padding: 8px 12px 24px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0 auto;
}

.patient-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  cursor: pointer;
}

.patient-card:hover {
  background: #f9fafb;
}

.patient-avatar {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.patient-info {
  flex: 1;
  min-width: 0;
}

.patient-name {
  font-weight: 600;
  color: #374151;
  margin: 0 0 2px 0;
  font-size: 0.9375rem;
}

.patient-meta {
  margin: 0;
  font-size: 0.8125rem;
  color: #6b7280;
}
</style>
