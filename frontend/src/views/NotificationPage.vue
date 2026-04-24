<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <Header />
      </ion-toolbar>
    </ion-header>

    <ion-content :fullscreen="true">
      <div class="notification-wrap mx-2 my-4">
        <div class="notification-heading-row">
          <div class="notification-heading">
            <p class="title">Notification</p>
            <p class="subtitle"> {{ unreadCount }} unread.</p>
          </div>
          <div class="heading-actions">
            <button type="button" class="refresh-btn" @click="loadNotifications" :disabled="loading">
              Refresh
            </button>
            <button type="button" class="select-btn" @click="toggleSelectionMode" :disabled="loading">
              {{ selectionMode ? 'Cancel' : 'Select' }}
            </button>
            <button
              v-if="selectionMode"
              type="button"
              class="delete-selected-btn"
              :disabled="selectedDeleteCount === 0"
              @click="deleteSelectedNotifications"
            >
              Delete Selected ({{ selectedDeleteCount }})
            </button>
          </div>
        </div>

        <div class="filter-row">
          <button
            type="button"
            :class="['filter-chip', consultationFilter === 'all' ? 'filter-chip-active' : '']"
            @click="setConsultationFilter('all')"
            :disabled="loading"
          >
            All
          </button>
          <button
            type="button"
            :class="['filter-chip', consultationFilter === 'medical' ? 'filter-chip-active' : '']"
            @click="setConsultationFilter('medical')"
            :disabled="loading"
          >
            Medical
          </button>
          <button
            type="button"
            :class="['filter-chip', consultationFilter === 'dental' ? 'filter-chip-active' : '']"
            @click="setConsultationFilter('dental')"
            :disabled="loading"
          >
            Dental
          </button>
        </div>

        <div v-if="loading" class="notification-card state-card">
          <p class="state-text">Loading notifications...</p>
        </div>

        <div v-else-if="errorMessage" class="notification-card state-card error-card">
          <p class="state-title">Unable to load notifications</p>
          <p class="state-text">{{ errorMessage }}</p>
        </div>

        <div v-else-if="!notifications.length" class="notification-card empty">
          <p class="empty-title">No notifications yet</p>
          <p class="empty-text">No Google Form responses found yet.</p>
        </div>

        <div v-else-if="!filteredNotifications.length" class="notification-card empty">
          <p class="empty-title">No {{ consultationFilterLabel }} notifications</p>
          <p class="empty-text">There are no records for this consultation filter.</p>
        </div>

        <div v-else class="notification-list">
          <button
            v-for="item in filteredNotifications"
            :key="item.id"
            type="button"
            :class="[
              'notification-card',
              'item-card',
              'item-button',
              isNotificationRead(item) ? 'is-read' : 'is-unread',
              selectionMode && isNotificationSelected(item.id) ? 'is-selected' : ''
            ]"
            @click="handleItemClick(item)"
          >
            <div class="item-layout">
              <div :class="['item-type-icon', getNotificationToneClass(item)]">
                <font-awesome-icon :icon="['fas', getNotificationIcon(item)]" class="item-type-icon-glyph" />
              </div>

              <div class="item-main">
                <div class="item-top">
                  <div class="item-top-left">
                    <span v-if="selectionMode" :class="['select-marker', isNotificationSelected(item.id) ? 'marker-selected' : '']">
                      {{ isNotificationSelected(item.id) ? 'x' : '' }}
                    </span>
                    <p class="item-title-line">
                      <span class="patient-name">{{ item.patientName }}</span>
                    </p>
                  </div>
                  <p class="submitted-time">{{ getRelativeTimeLabel(item.submittedAt) }}</p>
                </div>

                <p class="item-consultation-line">{{ getConsultationType(item) || 'Consultation' }}</p>

                <div class="item-meta-row">
                  <p class="item-meta-line">{{ getDateTimeLine(item) }} | {{ getServiceNeeded(item) || 'Service Needed' }}</p>
                  <span :class="['status-pill', isNotificationRead(item) ? 'status-read' : 'status-unread']">
                    {{ isNotificationRead(item) ? 'Read' : 'Unread' }}
                  </span>
                </div>
              </div>
            </div>
          </button>
        </div>
      </div>

      <ion-modal :is-open="selectedNotification !== null" @didDismiss="closeNotification" class="notification-detail-modal">
        <div class="modal-wrapper" v-if="selectedNotification">
          <div class="modal-header">
            <p class="modal-title">{{ selectedNotification.patientName }}</p>
            <button type="button" class="close-btn" aria-label="Close" @click="closeNotification">x</button>
          </div>

          <p class="modal-subtitle">Submitted {{ selectedNotification.submittedAtLabel }}</p>

          <div class="modal-meta-row">
            <span :class="['status-pill', selectedIsRead ? 'status-read' : 'status-unread']">
              {{ selectedIsRead ? 'Read' : 'Unread' }}
            </span>
            <button type="button" class="mark-btn" @click="toggleSelectedReadStatus">
              {{ selectedIsRead ? 'Mark as Unread' : 'Mark as Read' }}
            </button>
          </div>

          <div class="modal-body">
            <div class="field-grid">
              <div
                v-for="(value, label) in selectedNotification.fields"
                :key="`${selectedNotification.id}-${label}`"
                :class="['field-row', isAppointmentDateLabel(String(label)) ? 'field-row-date' : '']"
              >
                <p :class="['field-label', isAppointmentDateLabel(String(label)) ? 'field-label-date' : '']">{{ label }}</p>
                <a
                  v-if="isUrlValue(String(value))"
                  :href="String(value)"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="field-link"
                >
                  Open file
                </a>
                <p v-else :class="['field-value', isAppointmentDateLabel(String(label)) ? 'field-value-date' : '']">{{ value || '—' }}</p>
              </div>
            </div>
          </div>
        </div>
      </ion-modal>
    </ion-content>
  </ion-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { IonPage, IonHeader, IonToolbar, IonContent, IonModal, onIonViewWillEnter } from '@ionic/vue';
import axios from 'axios';
import Header from '../components/header.vue';

type NotificationEntry = {
  id: string;
  patientName: string;
  submittedAt: string | null;
  submittedAtLabel: string;
  fields: Record<string, string>;
};

type ConsultationFilter = 'all' | 'medical' | 'dental';

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';
const READ_STATUS_STORAGE_KEY = 'whitecoat_notification_read_status';
const DELETED_STATUS_STORAGE_KEY = 'whitecoat_notification_deleted_status';

const notifications = ref<NotificationEntry[]>([]);
const loading = ref(false);
const errorMessage = ref('');
const selectedNotification = ref<NotificationEntry | null>(null);
const readStatus = ref<Record<string, boolean>>({});
const deletedStatus = ref<Record<string, boolean>>({});
const selectionMode = ref(false);
const selectedForDelete = ref<Record<string, boolean>>({});
const consultationFilter = ref<ConsultationFilter>('all');

const selectedDeleteCount = computed(() =>
  Object.values(selectedForDelete.value).filter((value) => value === true).length
);

const unreadCount = computed(() =>
  notifications.value.reduce((total, item) => total + (isNotificationRead(item) ? 0 : 1), 0)
);

const filteredNotifications = computed(() => {
  if (consultationFilter.value === 'all') return notifications.value;

  return notifications.value.filter((item) => {
    const type = getConsultationType(item).toLowerCase();
    return type.includes(consultationFilter.value);
  });
});

const consultationFilterLabel = computed(() => {
  if (consultationFilter.value === 'all') return 'all';
  return consultationFilter.value;
});

const selectedIsRead = computed(() => {
  if (!selectedNotification.value) return false;
  return isNotificationRead(selectedNotification.value);
});

function loadReadStatusFromStorage() {
  try {
    const raw = localStorage.getItem(READ_STATUS_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      readStatus.value = parsed as Record<string, boolean>;
      return;
    }
  } catch {
    // Ignore invalid persisted data and start fresh.
  }
  readStatus.value = {};
}

function loadDeletedStatusFromStorage() {
  try {
    const raw = localStorage.getItem(DELETED_STATUS_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : {};
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
      deletedStatus.value = parsed as Record<string, boolean>;
      return;
    }
  } catch {
    // Ignore invalid persisted data and start fresh.
  }
  deletedStatus.value = {};
}

function saveReadStatusToStorage() {
  try {
    localStorage.setItem(READ_STATUS_STORAGE_KEY, JSON.stringify(readStatus.value));
  } catch {
    // Ignore storage errors.
  }
}

function saveDeletedStatusToStorage() {
  try {
    localStorage.setItem(DELETED_STATUS_STORAGE_KEY, JSON.stringify(deletedStatus.value));
  } catch {
    // Ignore storage errors.
  }
}

function syncReadStatusWithNotifications(rows: NotificationEntry[]) {
  const next: Record<string, boolean> = {};
  for (const row of rows) {
    next[row.id] = readStatus.value[row.id] === true;
  }
  readStatus.value = next;
  saveReadStatusToStorage();
}

function isNotificationRead(item: NotificationEntry): boolean {
  return readStatus.value[item.id] === true;
}

function isNotificationDeleted(itemId: string): boolean {
  return deletedStatus.value[itemId] === true;
}

function isNotificationSelected(itemId: string): boolean {
  return selectedForDelete.value[itemId] === true;
}

function toggleSelectionMode() {
  selectionMode.value = !selectionMode.value;
  selectedForDelete.value = {};
}

function setConsultationFilter(filter: ConsultationFilter) {
  consultationFilter.value = filter;
  selectedForDelete.value = {};
  selectionMode.value = false;
}

function toggleNotificationSelection(itemId: string) {
  selectedForDelete.value = {
    ...selectedForDelete.value,
    [itemId]: !isNotificationSelected(itemId),
  };
}

function handleItemClick(item: NotificationEntry) {
  if (selectionMode.value) {
    toggleNotificationSelection(item.id);
    return;
  }
  openNotification(item);
}

function deleteSelectedNotifications() {
  const idsToDelete = Object.entries(selectedForDelete.value)
    .filter(([, isSelected]) => isSelected === true)
    .map(([id]) => id);

  if (idsToDelete.length === 0) return;

  const shouldDelete = window.confirm(`Delete ${idsToDelete.length} notification(s)?`);
  if (!shouldDelete) return;

  for (const id of idsToDelete) {
    deletedStatus.value[id] = true;
    delete readStatus.value[id];
  }

  saveDeletedStatusToStorage();
  saveReadStatusToStorage();

  notifications.value = notifications.value.filter((item) => !idsToDelete.includes(item.id));

  if (selectedNotification.value && idsToDelete.includes(selectedNotification.value.id)) {
    closeNotification();
  }

  selectedForDelete.value = {};
  selectionMode.value = false;
}

function setNotificationRead(itemId: string, value: boolean) {
  readStatus.value = {
    ...readStatus.value,
    [itemId]: value,
  };
  saveReadStatusToStorage();
}

function toggleSelectedReadStatus() {
  if (!selectedNotification.value) return;
  const item = selectedNotification.value;
  setNotificationRead(item.id, !isNotificationRead(item));
}

function normalizeFieldKey(key: string): string {
  return key.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function isAppointmentDateLabel(label: string): boolean {
  const normalized = normalizeFieldKey(label);
  return normalized === 'appointmentdate';
}

function isUrlValue(value: string): boolean {
  const trimmed = value.trim();
  return /^https?:\/\//i.test(trimmed);
}

function getFieldByCandidates(fields: Record<string, string>, candidates: string[]): string {
  const normalized = candidates.map((candidate) => normalizeFieldKey(candidate));
  for (const [label, value] of Object.entries(fields)) {
    if (normalized.includes(normalizeFieldKey(label)) && String(value).trim() !== '') {
      return String(value).trim();
    }
  }
  return '';
}

function getConsultationType(item: NotificationEntry): string {
  return getFieldByCandidates(item.fields, ['Type of Consultation', 'Consultation Type']);
}

function getServiceNeeded(item: NotificationEntry): string {
  return getFieldByCandidates(item.fields, ['Service Needed', 'Appointment Type', 'Type of Appointment']);
}

function getDateTimeLine(item: NotificationEntry): string {
  const date = getFieldByCandidates(item.fields, ['Appointment Date', 'Date']);
  const time = getFieldByCandidates(item.fields, ['Appointment Time', 'Time']);
  const parts = [date, time].filter((value) => value !== '');
  return parts.length > 0 ? parts.join(' ') : item.submittedAtLabel;
}

function normalizeServiceText(item: NotificationEntry): string {
  return getServiceNeeded(item).toLowerCase().replace(/[^a-z0-9]/g, '');
}

function getNotificationIcon(item: NotificationEntry): string {
  const normalized = normalizeServiceText(item);
  if (normalized.includes('xray')) return 'x-ray';
  if (normalized.includes('laboratory')) return 'flask';
  if (normalized.includes('medicalcertificate')) return 'file-lines';
  return 'calendar-days';
}

function getNotificationToneClass(item: NotificationEntry): string {
  const normalized = normalizeServiceText(item);
  if (normalized.includes('xray')) return 'tone-red';
  if (normalized.includes('laboratory')) return 'tone-red';
  if (normalized.includes('medicalcertificate')) return 'tone-green';
  return 'tone-blue';
}

function getRelativeTimeLabel(submittedAt: string | null): string {
  if (!submittedAt) return 'just now';
  const submittedMs = new Date(submittedAt).getTime();
  if (Number.isNaN(submittedMs)) return 'just now';

  const diffMs = Date.now() - submittedMs;
  const minutes = Math.max(1, Math.floor(diffMs / 60000));

  if (minutes < 60) return `${minutes}m ago`;

  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;

  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

function openNotification(item: NotificationEntry) {
  setNotificationRead(item.id, true);
  selectedNotification.value = item;
}

function closeNotification() {
  selectedNotification.value = null;
}

async function loadNotifications() {
  loading.value = true;
  errorMessage.value = '';

  try {
    const response = await axios.get(`${API_URL}/api/doctor/notifications/google-form`);
    const rows = Array.isArray(response.data?.notifications) ? response.data.notifications : [];
    const visibleRows = rows.filter((item: NotificationEntry) => !isNotificationDeleted(item.id));
    syncReadStatusWithNotifications(visibleRows);
    selectedForDelete.value = {};
    notifications.value = visibleRows;
  } catch (error: any) {
    const apiMessage = error?.response?.data?.message;
    errorMessage.value = typeof apiMessage === 'string' && apiMessage.trim() !== ''
      ? apiMessage
      : 'Please check your Google Form feed URL configuration and try again.';
    notifications.value = [];
  } finally {
    loading.value = false;
  }
}

loadReadStatusFromStorage();
loadDeletedStatusFromStorage();
onMounted(loadNotifications);
onIonViewWillEnter(loadNotifications);
</script>

<style scoped>
ion-page,
ion-header,
ion-toolbar,
ion-content {
  --background: #f0f0f0;
  --color: #000000;
}

.notification-wrap {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.notification-heading-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}

.heading-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}

.filter-row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-chip {
  border: 1px solid #cbd5e1;
  border-radius: 9999px;
  background: #ffffff;
  color: #334155;
  font-size: 0.76rem;
  font-weight: 600;
  padding: 5px 11px;
  cursor: pointer;
}

.filter-chip-active {
  background: #2563eb;
  color: #ffffff;
  border-color: #2563eb;
}

.filter-chip:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.notification-heading .title {
  font-size: 1.35rem;
  font-weight: 700;
  color: #1f2937;
  margin: 0;
}

.notification-heading .subtitle {
  margin: 4px 0 0 0;
  font-size: 0.9rem;
  color: #6b7280;
}

.notification-card {
  background: #ffffff;
  border-radius: 10px;
  padding: 20px 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.state-card {
  padding: 16px;
}

.state-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #b91c1c;
}

.state-text {
  margin: 6px 0 0 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.error-card {
  border: 1px solid #fecaca;
}

.empty-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
}

.empty-text {
  margin: 6px 0 0 0;
  color: #6b7280;
  font-size: 0.9rem;
}

.notification-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 12px;
}

.item-card {
  padding: 12px;
}

.item-button {
  border: none;
  text-align: left;
  width: 100%;
  cursor: pointer;
}

.item-button.is-unread {
  border-left: 3px solid #2563eb;
}

.item-button.is-read {
  border-left: 4px solid transparent;
}

.item-button:hover {
  background: #f9fafb;
}

.item-button.is-selected {
  background: #eff6ff;
  border-left-color: #1d4ed8;
}

.item-layout {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.item-type-icon {
  width: 42px;
  height: 42px;
  min-width: 42px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.item-type-icon-glyph {
  color: #ffffff;
  font-size: 1rem;
}

.tone-blue {
  background: #2563eb;
}

.tone-green {
  background: #22c55e;
}

.tone-red {
  background: #ef4444;
}

.item-main {
  flex: 1;
  min-width: 0;
}

.item-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 10px;
}

.item-top-left {
  display: flex;
  align-items: flex-start;
  flex: 1;
  gap: 8px;
  min-width: 0;
}

.select-marker {
  width: 18px;
  height: 18px;
  border-radius: 4px;
  border: 1px solid #9ca3af;
  background: #ffffff;
  color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1;
}

.marker-selected {
  background: #2563eb;
  border-color: #2563eb;
}

.patient-name {
  color: #111827;
  font-weight: 700;
}

.item-title-line {
  margin: 0;
  color: #111827;
  font-size: 0.92rem;
  line-height: 1.25;
  font-weight: 600;
}

.submitted-time {
  margin: 0;
  color: #6b7280;
  font-size: 0.74rem;
  white-space: nowrap;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  padding: 2px 8px;
  font-size: 0.68rem;
  font-weight: 700;
}

.status-read {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-unread {
  background: #fee2e2;
  color: #b91c1c;
}

.item-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-top: 6px;
}

.item-consultation-line {
  margin: 4px 0 0 0;
  color: #374151;
  font-size: 0.92rem;
  line-height: 1.2;
  font-weight: 600;
}

.item-meta-line {
  margin: 0;
  color: #6b7280;
  font-size: 0.76rem;
}

.item-preview {
  margin: 10px 0 0 0;
  color: #4b5563;
  font-size: 0.85rem;
}

.field-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 8px;
  margin-top: 10px;
}

.field-row {
  background: #f9fafb;
  border-radius: 8px;
  padding: 8px 10px;
}

.field-row-date {
  background: #fff7cc;
  border: 1px solid #facc15;
}

.field-label {
  margin: 0;
  color: #374151;
  font-size: 0.75rem;
  font-weight: 600;
}

.field-value {
  margin: 2px 0 0 0;
  color: #111827;
  font-size: 0.9rem;
  word-break: break-word;
}

.field-label-date {
  color: #92400e;
}

.field-value-date {
  color: #92400e;
  font-weight: 700;
}

.field-link {
  display: inline-block;
  margin-top: 2px;
  color: #1d4ed8;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: underline;
}

.refresh-btn {
  border: none;
  border-radius: 8px;
  background: #1d4ed8;
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 8px 12px;
  cursor: pointer;
}

.select-btn {
  border: none;
  border-radius: 8px;
  background: #1f2937;
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 6px 10px;
  cursor: pointer;
}

.delete-selected-btn {
  border: none;
  border-radius: 8px;
  background: #b91c1c;
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 6px 10px;
  cursor: pointer;
}

.refresh-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.select-btn:disabled,
.delete-selected-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.notification-detail-modal {
  --width: min(95vw, 520px);
  --height: auto;
  --max-height: 86vh;
  --border-radius: 12px;
}

.notification-detail-modal::part(content) {
  border-radius: 12px;
  max-height: 86vh;
}

.modal-wrapper {
  display: flex;
  flex-direction: column;
  max-height: 86vh;
  background: #f0f0f0;
  padding: 12px;
}

.modal-body {
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.modal-title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
}

.close-btn {
  border: none;
  border-radius: 9999px;
  width: 28px;
  height: 28px;
  background: #e5e7eb;
  color: #111827;
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
}

.modal-subtitle {
  margin: 6px 0 10px 0;
  color: #6b7280;
  font-size: 0.82rem;
}

.modal-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 8px;
}

.mark-btn {
  border: none;
  border-radius: 8px;
  background: #111827;
  color: #ffffff;
  font-size: 0.78rem;
  font-weight: 600;
  padding: 6px 10px;
  cursor: pointer;
}
</style>
