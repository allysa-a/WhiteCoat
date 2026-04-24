<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <Header/>
      </ion-toolbar>
    </ion-header>
    <ion-content :fullscreen="true">
      <div class="search-wrap mx-2 mt-4">
        <font-awesome-icon :icon="['fas', 'magnifying-glass']" class="search-icon" />
        <input
          v-model="searchQuery"
          type="search"
          placeholder="Search Patient"
          class="search-input"
        />
      </div>
      <div class="px-2 my-2 flex flex-col justify-center items-center mx-2">
        <div class="flex items-center justify-between w-full mb-4">
          <p class="font-bold text-xl text-gray-800">HISTORY</p>
          <div class="header-action-buttons">
            <button type="button" class="link-logo-btn" aria-label="Open quick links" @click="openLinkModal">
              <img src="../assets/link-logo-blue.svg" alt="Link" class="link-logo-icon" />
            </button>
            <button type="button" class="prescribe-arrow-btn" aria-label="Go to Notification" @click="goToNotifications">
              <span class="notification-icon-wrap">
                <font-awesome-icon :icon="['fas', 'bell']" class="text-2xl text-white" />
                <span v-if="notificationCount > 0" class="notification-count-badge">{{ notificationCountLabel }}</span>
              </span>
            </button>
          </div>
        </div>

        <!-- History Section with fixed height -->
        <div class="history-container w-full">
          <div v-if="loading" class="w-full py-8 flex justify-center md:mx-5 mx-2">
            <p class="text-gray-500">Loading history...</p>
          </div>
          <div v-else-if="!filteredPatients.length" class="mt-4 w-full bg-[#D9D9D9] py-8 px-2 rounded-sm flex flex-col items-center justify-center">
            <p class="text-center text-gray-600">No history...</p>
          </div>
          <div v-else class="w-full flex flex-col gap-3">
            <div
              v-for="patient in displayedPatients"
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
          <div class="flex justify-end items-center mt-3">
            <button type="button" class="show-all-link" @click="goToPatientRecords">
              Show all
            </button>
          </div>
        </div>

        <!-- Reports and Analytics Section -->
        <div class="reports-analytics-container w-full mt-6">
          <ReportsAnalytics :refresh-key="analyticsRefreshKey" />
        </div>
      </div>

      <div v-if="showLinkModal" class="quick-link-overlay" @click.self="closeLinkModal">
        <div class="quick-link-modal">
          <h3 class="quick-link-title">GOOGLE LINKS</h3>

          <div class="google-links-grid">
            <div class="google-link-item">
              <button
                type="button"
                class="google-icon-btn"
                aria-label="Open Google Forms"
                @click="openExternalLink(googleFormLink)"
              >
                <img src="../assets/google-forms.svg" alt="Google Forms" class="google-icon" />
              </button>
              <p class="google-icon-title">Google Form</p>
              <a
                :href="googleFormLink"
                target="_blank"
                rel="noopener noreferrer"
                class="google-link-anchor"
              >
                {{ googleFormLink }}
              </a>
              <button
                type="button"
                class="copy-link-btn"
                aria-label="Copy Google Forms link"
                @click="copyToClipboard(googleFormLink)"
              >
                <font-awesome-icon :icon="['fas', 'copy']" class="copy-icon" />
                <span>Copy Link</span>
              </button>
            </div>

            <div class="google-link-item">
              <button
                type="button"
                class="google-icon-btn"
                aria-label="Open Google Sheets"
                @click="openExternalLink(googleSheetsLink)"
              >
                <img src="../assets/google-sheets.svg" alt="Google Sheets" class="google-icon" />
              </button>
              <p class="google-icon-title">Google Sheets</p>
              <a
                :href="googleSheetsLink"
                target="_blank"
                rel="noopener noreferrer"
                class="google-link-anchor"
              >
                {{ googleSheetsLink }}
              </a>
              <button
                type="button"
                class="copy-link-btn"
                aria-label="Copy Google Sheets link"
                @click="copyToClipboard(googleSheetsLink)"
              >
                <font-awesome-icon :icon="['fas', 'copy']" class="copy-icon" />
                <span>Copy Link</span>
              </button>
            </div>
          </div>

          <button type="button" class="quick-link-secondary" @click="closeLinkModal">Close</button>
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

      <ion-toast
        :is-open="showCopiedToast"
        message="Link copied to clipboard!"
        :duration="1400"
        @didDismiss="showCopiedToast = false"
      />

      <p class="text-gray-500 mt-1 mb-3 flex flex-nowrap items-center justify-center gap-1 text-center whitespace-nowrap leading-none px-2" style="font-size: clamp(8px, 2.7vw, 16px);">
        <span class="leading-none" style="font-size: 1.15em;">©</span>
        <span>All Right Reserved KAYE DELGADO &amp; SHIRLYN CANLOM</span>
      </p>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { IonPage, IonHeader, IonToolbar, IonContent, IonToast, onIonViewWillEnter } from '@ionic/vue';
import axios from 'axios';
import Header from '../components/header.vue';
import HistoryDetailModal from '../components/HistoryDetailModal.vue';
import ReportsAnalytics from '../components/ReportsAnalytics.vue';
import type { HistoryEntryForModal } from '../components/HistoryDetailModal.vue';

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';
const READ_STATUS_STORAGE_KEY = 'whitecoat_notification_read_status';
const DELETED_STATUS_STORAGE_KEY = 'whitecoat_notification_deleted_status';

type HistoryEntry = HistoryEntryForModal;

const searchQuery = ref('');
const loading = ref(true);
const displayLimit = 3;
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
const analyticsRefreshKey = ref(0);
const showDeletedToast = ref(false);
const showCopiedToast = ref(false);
const notificationCount = ref(0);
const showLinkModal = ref(false);
const googleFormLink = 'https://docs.google.com/forms/d/e/1FAIpQLSdL_cEpHnkiWHdLOqCUeq9GtuAR7VQ5yI8TBgyi64QZZtuCVQ/viewform?pli=1&pli=1';
const googleSheetsLink = 'https://docs.google.com/spreadsheets/d/14k3w0Nf-8mPz93pUuztS8v_MNTXrkGnpSAznaIUeK8g/edit?resourcekey=&gid=317370873#gid=317370873';

const notificationCountLabel = computed(() => {
  return notificationCount.value > 99 ? '99+' : String(notificationCount.value);
});

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

const displayedPatients = computed(() => filteredPatients.value.slice(0, displayLimit));

const router = useRouter();
function goToNotifications() {
  router.push('/tabs/notifications');
}
function goToPatientRecords() {
  router.push('/tabs/history');
}

function openLinkModal() {
  showLinkModal.value = true;
}

function closeLinkModal() {
  showLinkModal.value = false;
}

function openExternalLink(url: string) {
  window.open(url, '_blank', 'noopener,noreferrer');
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text).then(() => {
    showCopiedToast.value = true;
  }).catch(err => {
    console.error('Failed to copy:', err);
  });
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

async function loadTabData() {
  let user_id: number | null = null;
  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) {
      prescriptionHistory.value = [];
      certificateHistory.value = [];
      labRequestHistory.value = [];
      analyticsRefreshKey.value++;
      return;
    }
    const user = JSON.parse(stored) as { user_id?: number };
    user_id = user?.user_id ?? null;
  } catch {
    prescriptionHistory.value = [];
    certificateHistory.value = [];
    labRequestHistory.value = [];
    analyticsRefreshKey.value++;
    return;
  }
  if (user_id == null) {
    prescriptionHistory.value = [];
    certificateHistory.value = [];
    labRequestHistory.value = [];
    analyticsRefreshKey.value++;
    return;
  }
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
  analyticsRefreshKey.value++;
}

async function loadNotificationCount() {
  try {
    const res = await axios.get(`${API_URL}/api/doctor/notifications/google-form`);
    const rows = Array.isArray(res?.data?.notifications) ? res.data.notifications : [];

    let readStatus: Record<string, boolean> = {};
    let deletedStatus: Record<string, boolean> = {};

    try {
      const readRaw = localStorage.getItem(READ_STATUS_STORAGE_KEY);
      const readParsed = readRaw ? JSON.parse(readRaw) : {};
      if (readParsed && typeof readParsed === 'object' && !Array.isArray(readParsed)) {
        readStatus = readParsed as Record<string, boolean>;
      }
    } catch {
      readStatus = {};
    }

    try {
      const deletedRaw = localStorage.getItem(DELETED_STATUS_STORAGE_KEY);
      const deletedParsed = deletedRaw ? JSON.parse(deletedRaw) : {};
      if (deletedParsed && typeof deletedParsed === 'object' && !Array.isArray(deletedParsed)) {
        deletedStatus = deletedParsed as Record<string, boolean>;
      }
    } catch {
      deletedStatus = {};
    }

    const unread = rows.reduce((total: number, row: any) => {
      const id = String(row?.id ?? '');
      if (!id) return total;
      if (deletedStatus[id] === true) return total;
      if (readStatus[id] === true) return total;
      return total + 1;
    }, 0);

    notificationCount.value = unread;
  } catch {
    notificationCount.value = 0;
  }
}

async function handleHistoryDeleted() {
  selectedEntry.value = null;
  patientHistory.value = null;
  await loadTabData();
  showDeletedToast.value = true;
}

onMounted(() => {
  loadTabData();
  loadNotificationCount();
});

onIonViewWillEnter(() => {
  loadTabData();
  loadNotificationCount();
});
</script>

<style scoped>
ion-page,
ion-header,
ion-toolbar,
ion-content {
  --background: #f0f0f0;
  --color: #000000;
}

.search-wrap {
  display: flex;
  align-items: center;
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

.search-input::placeholder {
  color: #6b7280;
}

.header-action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.prescribe-arrow-btn {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #374151;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.prescribe-arrow-btn:hover {
  opacity: 0.9;
}

.link-logo-btn {
  width: 40px;
  height: 40px;
  background: transparent;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
}

.link-logo-btn:hover {
  opacity: 0.9;
}

.link-logo-icon {
  width: 34px;
  height: 34px;
  display: block;
}

.quick-link-overlay {
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(15, 23, 42, 0.38);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.quick-link-modal {
  width: min(100%, 400px);
  background: #ffffff;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 14px 32px rgba(0, 0, 0, 0.22);
  text-align: center;
}

.quick-link-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  color: #111827;
  letter-spacing: 0.04em;
}

.google-links-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin: 14px 0;
}

.google-link-item {
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px;
}

.google-icon-btn {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  border: none;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.google-icon {
  width: 32px;
  height: 32px;
  display: block;
}

.google-link-anchor {
  margin-top: 8px;
  display: block;
  font-size: 0.72rem;
  line-height: 1.35;
  color: #1d4ed8;
  text-decoration: underline;
  word-break: break-all;
}

.google-icon-title {
  margin: 8px 0 0;
  font-size: 0.85rem;
  font-weight: 700;
  color: #0f172a;
}

.quick-link-secondary {
  width: 100%;
  border: none;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 700;
  cursor: pointer;
}

.quick-link-secondary {
  background: #e5e7eb;
  color: #111827;
}

.notification-icon-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.notification-count-badge {
  position: absolute;
  top: -8px;
  right: -12px;
  min-width: 18px;
  height: 18px;
  border-radius: 9999px;
  background: #dc2626;
  color: #ffffff;
  font-size: 0.65rem;
  line-height: 18px;
  font-weight: 700;
  text-align: center;
  padding: 0 4px;
  border: 1px solid #ffffff;
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

.show-all-link {
  background: none;
  border: none;
  color: #2563eb;
  font-size: 0.9375rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline;
  padding: 4px 0;
}

.show-all-link:hover {
  color: #1d4ed8;
}

.history-container {
  max-height: 280px;
  overflow-y: auto;
  padding-bottom: 8px;
}

.history-container::-webkit-scrollbar {
  width: 6px;
}

.history-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.history-container::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 10px;
}

.history-container::-webkit-scrollbar-thumb:hover {
  background: #555;
}

.reports-analytics-container {
  min-height: 200px;
}

.copy-link-btn {
  width: 100%;
  border: none;
  border-radius: 6px;
  background: #e0e7ff;
  color: #4f46e5;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 6px 8px;
  margin-top: 8px;
  transition: all 0.2s ease;
}

.copy-icon {
  font-size: 0.75rem;
}

.copy-link-btn:hover {
  background: #c7d2fe;
  color: #4338ca;
}

.copy-link-btn:active {
  transform: scale(0.98);
}
</style>
