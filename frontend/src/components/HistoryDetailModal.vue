<template>
  <ion-modal :is-open="isOpen" @didDismiss="closeModal" class="history-detail-modal">
    <div class="modal-wrapper" v-if="entry">
      <div class="modal-header">
        <span class="modal-title">{{ headerTitle }}</span>
        <button type="button" class="close-btn" aria-label="Close" @click="closeModal">
          <font-awesome-icon :icon="['fas', 'xmark']" class="text-xl" />
        </button>
      </div>

      <!-- Scrollable content area -->
      <div class="modal-body">
        <!-- Full history list: when patientHistory is set -->
        <div v-if="patientHistory && historyItems.length" class="history-list-section">
          <h3 class="history-list-title">All records</h3>
          <div class="history-list">
            <button
              v-for="(item, idx) in historyItems"
              :key="item.key"
              type="button"
              class="history-list-item"
              :class="{ active: selectedHistoryIndex === idx }"
              @click="selectedHistoryIndex = idx"
            >
              <span class="history-item-type">{{ item.typeLabel }}</span>
              <span class="history-item-date">{{ formatItemDate(item.date) }}</span>
            </button>
          </div>
        </div>

        <div class="detail-section">
          <div class="detail-row">
            <div class="detail-fields">
              <p class="detail-line"><span class="detail-label">Name:</span> {{ displayName }}</p>
              <p class="detail-line"><span class="detail-label">Age:</span> {{ effectiveRecord?.age ?? '—' }}</p>
              <p class="detail-line"><span class="detail-label">Sex:</span> {{ effectiveRecord?.gender ?? '—' }}</p>
              <p class="detail-line"><span class="detail-label">Type:</span> {{ effectiveTypeLabel }}</p>
              <p class="detail-line"><span class="detail-label">Issued at:</span> {{ issuedAtLong }}</p>
              <p class="detail-line"><span class="detail-label">Status:</span> Signed</p>
            </div>
            <div class="detail-avatar">
              <font-awesome-icon :icon="['fas', 'user']" class="text-gray-100 text-3xl" />
            </div>
          </div>
        </div>

        <!-- Error / info message -->
        <p v-if="actionError" class="action-error">{{ actionError }}</p>

        <div ref="previewRef">
          <div v-if="effectiveType === 'prescription'" class="prescription-document doc-block">
            <div class="doc-header">
              <h2 class="doc-title">Prescription</h2>
              <p class="doc-subtitle">Digital prescription for a patient</p>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Patient Information</h3>
              <div class="doc-grid">
                <div class="doc-field"><span class="label">Full Name:</span> {{ form.name || "—" }}</div>
                <div class="doc-field"><span class="label">Age:</span> {{ form.age || "—" }}</div>
                <div class="doc-field"><span class="label">Gender:</span> {{ form.gender || "—" }}</div>
                <div class="doc-field"><span class="label">Date Issued:</span> {{ formatDateOnly(form.dateIssued) || "—" }}</div>
                <div class="doc-field full-width"><span class="label">Address:</span> {{ form.address || "—" }}</div>
                <div class="doc-field full-width"><span class="label">Maintenance:</span> {{ form.maintenance || "—" }}</div>
                <div class="doc-field full-width"><span class="label">Reason for Referral:</span> {{ form.reason_for_referral || "—" }}</div>
              </div>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Medications</h3>
              <div v-for="(med, i) in form.medications" :key="i" class="med-block">
                <h4 class="med-title">Medication {{ i + 1 }}</h4>
                <div class="doc-grid">
                  <div class="doc-field full-width"><span class="label">Medication:</span> {{ med.name || "—" }}</div>
                  <div class="doc-field"><span class="label">Dosage:</span> {{ med.dosage || "—" }}</div>
                  <div class="doc-field"><span class="label">Frequency:</span> {{ med.frequency || "—" }}</div>
                  <div class="doc-field"><span class="label">Duration:</span> {{ med.duration || "—" }}</div>
                  <div class="doc-field full-width"><span class="label">Instructions:</span> {{ med.instructions || "—" }}</div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="effectiveType === 'issuance'" class="certificate-document doc-block">
            <div class="doc-header">
              <h2 class="doc-title">Medical Certificate</h2>
              <p class="doc-subtitle">Digital certificate for a patient</p>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Patient Information</h3>
              <div class="doc-grid">
                <div class="doc-field"><span class="label">Full Name:</span> {{ form.name || "—" }}</div>
                <div class="doc-field"><span class="label">Age:</span> {{ form.age || "—" }}</div>
                <div class="doc-field"><span class="label">Gender:</span> {{ form.gender || "—" }}</div>
                <div class="doc-field"><span class="label">Date Issued:</span> {{ formatDateOnly(form.dateIssued) || "—" }}</div>
                <div class="doc-field full-width"><span class="label">Address:</span> {{ form.address || "—" }}</div>
              </div>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Clinical Information</h3>
              <div class="doc-field full-width"><span class="label">Impression:</span> {{ form.impression || "—" }}</div>
              <div class="doc-field full-width"><span class="label">Remarks:</span> {{ form.remarks || "—" }}</div>
            </div>
          </div>

          <div v-else-if="effectiveType === 'labRequest'" class="lab-request-document doc-block">
            <div class="doc-header">
              <h2 class="doc-title">Laboratory Request</h2>
              <p class="doc-subtitle">Lab request for a patient</p>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Patient Information</h3>
              <div class="doc-grid">
                <div class="doc-field"><span class="label">Full Name:</span> {{ form.name || "—" }}</div>
                <div class="doc-field"><span class="label">Age:</span> {{ form.age || "—" }}</div>
                <div class="doc-field"><span class="label">Gender:</span> {{ form.gender || "—" }}</div>
                <div class="doc-field"><span class="label">Date Issued:</span> {{ formatDateOnly(form.dateIssued) || "—" }}</div>
                <div class="doc-field full-width"><span class="label">Address:</span> {{ form.address || "—" }}</div>
              </div>
            </div>
            <div class="doc-section">
              <h3 class="section-title">Laboratory Tests</h3>
              <div class="doc-field full-width"><span class="label">Selected tests:</span> {{ labRequestTestsDisplay }}</div>
              <div class="doc-field full-width"><span class="label">Other tests:</span> {{ labRequestOtherTestsDisplay }}</div>
              <div class="doc-field full-width"><span class="label">Impression:</span> {{ labRequestImpressionDisplay }}</div>
              <div class="doc-field full-width"><span class="label">Remarks:</span> {{ labRequestRemarksDisplay }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Fixed action buttons pinned to bottom -->
      <div class="modal-footer">
        <button type="button" class="action-btn" :disabled="isWorking" @click="saveAsWord">
          <span v-if="isWorking && workingAction === 'saveWord'" class="spinner" />
          <font-awesome-icon v-else :icon="['fas', 'file-word']" class="text-4xl" />
          <span>Save as Word</span>
        </button>
        <button type="button" class="action-btn delete-btn" :disabled="isWorking" @click="deleteRecord">
          <span v-if="isWorking && workingAction === 'delete'" class="spinner" />
          <font-awesome-icon v-else :icon="['fas', 'trash']" class="text-4xl" />
          <span>Delete</span>
        </button>
      </div>

      <div v-if="showDeleteConfirm" class="delete-confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="delete-confirm-title">
        <div class="delete-confirm-card">
          <div class="delete-confirm-icon-wrap">
            <div class="delete-confirm-icon-circle">
              <font-awesome-icon :icon="['fas', 'trash']" />
            </div>
          </div>

          <h3 id="delete-confirm-title" class="delete-confirm-title">Delete Patient Record?</h3>
          <p class="delete-confirm-message">
            Are you sure you want to delete this patient record?<br />
            This action cannot be undone.
          </p>

          <p v-if="actionError" class="delete-confirm-error">{{ actionError }}</p>

          <div class="delete-confirm-actions">
            <button type="button" class="delete-cancel-btn" :disabled="isWorking" @click="cancelDeleteConfirm">Cancel</button>
            <button type="button" class="delete-confirm-btn" :disabled="isWorking" @click="confirmDeleteRecord">
              <span v-if="isWorking && workingAction === 'delete'" class="confirm-btn-spinner" />
              <span v-else>Delete</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </ion-modal>
</template>

<script lang="ts">
import { defineComponent, ref, computed, watch } from "vue";
import { IonModal, IonIcon } from "@ionic/vue";
import axios from "axios";

const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';

export interface HistoryEntryForModal {
  patientName: string;
  patientId?: number;
  lastDate: string;
  lastDateFormatted: string;
  lastType: "prescription" | "issuance" | "labRequest";
  record: Record<string, unknown>;
}

export type PatientHistoryPayload = {
  patient: { id: number; name: string; age?: string; gender?: string; address?: string } | null;
  prescriptions: Record<string, unknown>[];
  medicalCertificates: Record<string, unknown>[];
  labRequests: Record<string, unknown>[];
};

const ENDPOINT_MAP = {
  prescription: "/api/doctor/prescription/generate",
  issuance:     "/api/doctor/issuance/generate",
  labRequest:   "/api/doctor/lab-request/generate",
} as const;

const FILENAME_MAP = {
  prescription: "prescription.docx",
  issuance:     "medical_certificate.docx",
  labRequest:   "lab_request.docx",
} as const;

const DOWNLOAD_LABEL_MAP = {
  prescription: "prescription",
  issuance: "medical certificate",
  labRequest: "lab",
} as const;

const DELETE_ENDPOINT_MAP = {
  prescription: "/api/doctor/prescription/history",
  issuance:     "/api/doctor/medical-certificate/history",
  labRequest:   "/api/doctor/lab-request/history",
} as const;

export default defineComponent({
  name: "HistoryDetailModal",
  components: { IonModal, IonIcon },
  props: {
    isOpen:         { type: Boolean, required: true },
    entry:          { type: Object as () => HistoryEntryForModal | null, default: null },
    patientHistory: { type: Object as () => PatientHistoryPayload | null, default: null },
  },
  emits: ["close", "deleted"],
  setup(props, { emit }) {
    const previewRef           = ref<HTMLElement | null>(null);
    const selectedHistoryIndex = ref(0);
    const isWorking            = ref(false);
    const workingAction        = ref<"saveWord" | "delete" | null>(null);
    const actionError          = ref("");
    const showDeleteConfirm    = ref(false);

    watch(() => props.patientHistory, () => { selectedHistoryIndex.value = 0; }, { flush: "sync" });
    watch(() => props.isOpen, (open) => {
      if (!open) {
        showDeleteConfirm.value = false;
      }
    });

    // ── History list ──────────────────────────────────────────────────────────
    const historyItems = computed(() => {
      const h = props.patientHistory;
      if (!h) return [];
      const items: {
        key: string;
        type: "prescription" | "issuance" | "labRequest";
        typeLabel: string;
        date: string;
        record: Record<string, unknown>;
      }[] = [];
      (h.prescriptions || []).forEach((p, i) => {
        const d = (p.date_issued as string) || (p.created_at as string) || "";
        items.push({ key: `p-${i}`, type: "prescription", typeLabel: "Prescription", date: d, record: p });
      });
      (h.medicalCertificates || []).forEach((m, i) => {
        const d = (m.date_issued as string) || (m.created_at as string) || "";
        items.push({ key: `m-${i}`, type: "issuance", typeLabel: "Medical Certificate", date: d, record: m });
      });
      (h.labRequests || []).forEach((l, i) => {
        const d = (l.date_issued as string) || (l.created_at as string) || "";
        items.push({ key: `l-${i}`, type: "labRequest", typeLabel: "Lab Request", date: d, record: l });
      });
      items.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
      return items;
    });

    const effectiveEntry = computed(() => {
      if (props.patientHistory && historyItems.value.length) {
        const idx  = Math.min(selectedHistoryIndex.value, historyItems.value.length - 1);
        const item = historyItems.value[idx];
        if (item) return { lastType: item.type, record: item.record };
      }
      return props.entry ? { lastType: props.entry.lastType, record: props.entry.record } : null;
    });

    const effectiveType = computed((): "prescription" | "issuance" | "labRequest" => {
      return effectiveEntry.value?.lastType ?? "prescription";
    });

    const effectiveTypeLabel = computed(() => {
      if (effectiveType.value === "prescription") return "Prescription";
      if (effectiveType.value === "issuance")     return "Medical Certificate";
      return "Lab Request";
    });

    const effectiveRecord = computed(() => effectiveEntry.value?.record ?? props.entry?.record);

    const headerTitle = computed(() => {
      if (props.patientHistory?.patient?.name) return props.patientHistory.patient.name + " – History";
      return effectiveTypeLabel.value;
    });

    function formatItemDate(d: string) {
      if (!d) return "—";
      const date = new Date(d);
      if (isNaN(date.getTime())) return "—";
      return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
    }

    const displayName = computed(() => {
      const r = effectiveRecord.value;
      if (!r) return props.patientHistory?.patient?.name ?? props.entry?.patientName ?? "—";
      return (r.patient_name as string) || (r.name as string) || props.entry?.patientName || props.patientHistory?.patient?.name || "—";
    });

    const issuedAtLong = computed(() => {
      const d = effectiveRecord.value?.date_issued as string | undefined;
      if (!d) return "—";
      const date = new Date(d);
      if (isNaN(date.getTime())) return "—";
      const mm   = String(date.getMonth() + 1).padStart(2, "0");
      const dd   = String(date.getDate()).padStart(2, "0");
      const yyyy = date.getFullYear();
      return `${mm}/${dd}/${yyyy}`;
    });

    function parseLabRequestNotes(raw: unknown): { impression: string; remarks: string } {
      const text = String(raw ?? "").trim();
      if (!text) return { impression: "", remarks: "" };

      const impressionMatch = text.match(/^Impression:\s*(.*?)(?:\r?\n|$)/i);
      if (!impressionMatch) return { impression: "", remarks: text };

      const impression = (impressionMatch[1] || "").trim();
      const remarks = text.slice(impressionMatch[0].length).trim();
      return { impression, remarks };
    }

    const form = computed(() => {
      const r = effectiveRecord.value;
      if (!r) return { name: "", age: "", gender: "", dateIssued: "", address: "", medications: [], impression: "", remarks: "" };
      const name       = (r.patient_name as string) || (r.name as string) || "";
      const dateIssued = (r.date_issued  as string) || "";

      if (effectiveType.value === "prescription") {
        let meds = r.medications;
        if (typeof meds === "string") { try { meds = JSON.parse(meds); } catch { meds = []; } }
        return {
          name,
          age: r.age ?? "",
          gender: r.gender ?? "",
          dateIssued,
          address: r.address ?? "",
          maintenance: (r.maintenance as string) ?? "",
          reason_for_referral: (r.reason_for_referral as string) ?? "",
          medications: Array.isArray(meds) ? meds : []
        };
      }
      if (effectiveType.value === "labRequest") {
        let tests = r.selected_tests;
        if (typeof tests === "string") { try { tests = JSON.parse(tests); } catch { tests = []; } }
        const testsStr = Array.isArray(tests) ? tests.join(", ") : String(tests || "");
        const parsedNotes = parseLabRequestNotes(r.remarks);
        return {
          name, age: r.age ?? "", gender: r.gender ?? "", dateIssued, address: r.address ?? "",
          labRequestTests: testsStr,
          labRequestOtherTests: (r.other_tests as string) ?? "",
          labRequestImpression: parsedNotes.impression,
          labRequestRemarks: parsedNotes.remarks,
        };
      }
      return { name, age: r.age ?? "", gender: r.gender ?? "", dateIssued, address: r.address ?? "", impression: r.impression ?? "", remarks: r.remarks ?? "" };
    });

    const labRequestTestsDisplay      = computed(() => (form.value as { labRequestTests?: string }).labRequestTests      ?? "—");
    const labRequestOtherTestsDisplay = computed(() => (form.value as { labRequestOtherTests?: string }).labRequestOtherTests ?? "—");
    const labRequestImpressionDisplay = computed(() => (form.value as { labRequestImpression?: string }).labRequestImpression ?? "—");
    const labRequestRemarksDisplay    = computed(() => (form.value as { labRequestRemarks?: string }).labRequestRemarks    ?? "—");

    // ── Helpers ───────────────────────────────────────────────────────────────
    function getUserId(): number | null {
      try {
        const stored = localStorage.getItem("whitecoat_user");
        if (!stored) return null;
        return (JSON.parse(stored) as { user_id?: number }).user_id ?? null;
      } catch { return null; }
    }

    function sanitizeFilenamePart(value: unknown): string {
      return String(value ?? "")
        .replace(/[<>:"/\\|?*\u0000-\u001F]+/g, " ")
        .replace(/\s+/g, " ")
        .trim()
        .replace(/[. ]+$/g, "");
    }

    function buildDownloadFilename(type: keyof typeof DOWNLOAD_LABEL_MAP, patientName: unknown, extension: string): string {
      const safeName = sanitizeFilenamePart(patientName) || "patient";
      const safeType = DOWNLOAD_LABEL_MAP[type];
      return `${safeType} ${safeName}.${extension.replace(/^\./, "")}`;
    }

    function buildPayload(): Record<string, unknown> {
      const f      = form.value as Record<string, unknown>;
      const userId = getUserId();
      const base   = { user_id: userId, name: f.name, age: f.age, gender: f.gender, dateIssued: f.dateIssued, address: f.address };

      if (effectiveType.value === "prescription") {
        const referral = String(f.reason_for_referral ?? "").trim() || "N/A";
        return {
          ...base,
          maintenance: f.maintenance ?? "",
          reason_for_referral: referral,
          medications: f.medications,
        };
      }
      if (effectiveType.value === "issuance")     return { ...base, impression: f.impression, remarks: f.remarks };

      const ft = f as { labRequestTests?: string; labRequestOtherTests?: string; labRequestImpression?: string; labRequestRemarks?: string };
      // Backend lab-request/generate expects camelCase: selectedTests, otherTests
      return {
        ...base,
        selectedTests: ft.labRequestTests ? ft.labRequestTests.split(",").map((s: string) => s.trim()).filter(Boolean) : [],
        otherTests:    ft.labRequestOtherTests ?? "",
        impression:    ft.labRequestImpression ?? "",
        remarks:       ft.labRequestRemarks ?? "",
      };
    }

    /** Call the backend — returns the filled .docx blob */
    async function generateBlob(options?: { format?: string }): Promise<{ blob: Blob; filename: string }> {
      const userId = getUserId();
      if (!userId) throw new Error("Please log in to perform this action.");

      const type = effectiveType.value;
      const extension = options?.format === "pdf" ? "pdf" : "docx";
      const payload = { ...buildPayload(), ...(options?.format && { format: options.format }) };
      const res = await axios.post(`${API_URL}${ENDPOINT_MAP[type]}`, payload, { responseType: "blob" });
      const blob     = res.data as Blob;
      const disp     = res.headers["content-disposition"] as string | undefined;
      const filename = disp
        ? (disp.split("filename=")[1] || "").replace(/"/g, "").trim() || buildDownloadFilename(type, form.value.name ?? displayName.value, extension)
        : buildDownloadFilename(type, form.value.name ?? displayName.value, extension);

      return { blob, filename };
    }

    async function handleError(e: unknown) {
      const err = e as { response?: { data?: Blob }; message?: string };
      if (err.response?.data instanceof Blob) {
        try {
          const text = await err.response.data.text();
          actionError.value = (JSON.parse(text) as { message?: string }).message || "Failed to generate document.";
        } catch { actionError.value = "Failed to generate document."; }
      } else {
        actionError.value = err.message || "Failed to generate document.";
      }
    }

    // ── Save as Word ──────────────────────────────────────────────────────────
    const saveAsWord = async () => {
      actionError.value   = "";
      isWorking.value     = true;
      workingAction.value = "saveWord";
      try {
        const { blob, filename } = await generateBlob({ format: "docx" });
        const url = URL.createObjectURL(blob);
        const a   = document.createElement("a");
        a.href     = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);
      } catch (e) {
        await handleError(e);
      } finally {
        isWorking.value     = false;
        workingAction.value = null;
      }
    };

    const deleteRecord = async () => {
      actionError.value = "";
      showDeleteConfirm.value = true;
    };

    const cancelDeleteConfirm = () => {
      if (isWorking.value && workingAction.value === "delete") return;
      actionError.value = "";
      showDeleteConfirm.value = false;
    };

    const confirmDeleteRecord = async () => {
      actionError.value = "";

      const rec = effectiveRecord.value as { id?: number | string } | undefined;
      const rawId = rec?.id;
      const recordId = Number(rawId);
      const userId = getUserId();

      if (!userId) {
        actionError.value = "Please log in to perform this action.";
        return;
      }
      if (!Number.isFinite(recordId) || recordId <= 0) {
        actionError.value = "This record cannot be deleted.";
        return;
      }

      isWorking.value = true;
      workingAction.value = "delete";
      try {
        await axios.delete(`${API_URL}${DELETE_ENDPOINT_MAP[effectiveType.value]}`, {
          params: { user_id: userId, id: recordId },
        });
        showDeleteConfirm.value = false;
        emit("deleted");
        emit("close");
      } catch (e) {
        const err = e as { response?: { data?: { message?: string } }; message?: string };
        actionError.value = err.response?.data?.message || err.message || "Failed to delete history record.";
      } finally {
        isWorking.value = false;
        workingAction.value = null;
      }
    };

    function formatDateOnly(d?: string) {
      if (!d) return "—";
      const date = new Date(d);
      if (isNaN(date.getTime())) return "—";

      const yyyy = date.getFullYear();
      const mm   = String(date.getMonth() + 1).padStart(2, "0");
      const dd   = String(date.getDate()).padStart(2, "0");

      return `${yyyy}-${mm}-${dd}`;   // 2026-02-16
    }

    const closeModal = () => emit("close");

    return {
      previewRef,
      displayName,
      issuedAtLong,
      form,
      formatDateOnly,
      closeModal,
      saveAsWord,
      deleteRecord,
      confirmDeleteRecord,
      cancelDeleteConfirm,
      historyItems,
      selectedHistoryIndex,
      effectiveType,
      effectiveTypeLabel,
      effectiveRecord,
      headerTitle,
      formatItemDate,
      labRequestTestsDisplay,
      labRequestOtherTestsDisplay,
      labRequestImpressionDisplay,
      labRequestRemarksDisplay,
      isWorking,
      workingAction,
      actionError,
      showDeleteConfirm,
    };
  },
});
</script>

<style scoped>
.history-detail-modal::part(content) {
  --width: min(95vw, 520px);
  --height: 90vh;
  --border-radius: 12px;
  --box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
}

.modal-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #fff;
  overflow: hidden;
  position: relative;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid black;
  flex-shrink: 0;
}

.modal-title { font-size: 1.125rem; font-weight: 700; color: #111; }

.close-btn {
  background: none;
  border: none;
  padding: 6px;
  cursor: pointer;
  color: #374151;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-body { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; }

.modal-footer {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 32px;
  padding: 16px;
  border-top: 1px solid black;
  flex-shrink: 0;
  background: #fff;
}

.action-btn {
  background: none;
  border: none;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  font-size: 0.875rem;
  color: black;
  font-weight: 600;
  min-width: 64px;
}

.action-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.action-btn:active:not(:disabled) { opacity: 0.7; }

.delete-btn { color: #b91c1c; }

.spinner {
  display: inline-block;
  width: 36px;
  height: 36px;
  border: 3px solid #d1d5db;
  border-top-color: #374151;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.action-error {
  margin: 0 16px 10px;
  padding: 8px 12px;
  font-size: 0.8125rem;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 6px;
}

.history-list-section { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
.history-list-title { font-size: 0.875rem; font-weight: 600; color: #374151; margin: 0 0 8px 0; }
.history-list { display: flex; flex-direction: column; gap: 4px; max-height: 140px; overflow-y: auto; }

.history-list-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  text-align: left;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #f9fafb;
  font-size: 0.875rem;
  cursor: pointer;
  color: #111;
}

.history-list-item:hover { background: #f3f4f6; }
.history-list-item.active { background: #374151; color: #fff; border-color: #374151; }
.history-item-type { font-weight: 500; }
.history-item-date { color: inherit; opacity: 0.9; font-size: 0.8125rem; }

.detail-section { padding: 16px; }
.detail-row { display: flex; gap: 12px; align-items: flex-start; }

.detail-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgb(196, 192, 192);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.detail-fields { flex: 1; min-width: 0; }
.detail-line { margin: 4px 0; font-size: 0.9375rem; color: #374151; }
.detail-label { font-weight: 600; color: #111; }

.doc-block {
  background: #d5cbcb;
  padding: 20px;
  margin: 0 20px 20px;
  border-radius: 8px;
  border: 1px solid black;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.doc-header { margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px solid black; }
.doc-title { font-size: 1.5rem; font-weight: 700; color: black; margin: 0 0 4px 0; }
.doc-subtitle { font-size: 0.875rem; color: #6b7280; margin: 0; }
.doc-section { margin-top: 16px; }

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #111;
  margin: 0 0 8px 0;
  padding-bottom: 4px;
  border-bottom: 1px solid black;
}

.doc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; align-items: start; }
.doc-field {
  font-size: 0.875rem;
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  min-width: 0;
}
.doc-field .label {
  font-weight: 600;
  color: #374151;
  flex-shrink: 0;
  white-space: nowrap;
}
.doc-field.full-width { grid-column: 1 / -1; }
.med-block { padding-top: 12px; }
.med-title { font-size: 0.9375rem; font-weight: 600; margin: 0 0 8px 0; color: black; }

.delete-confirm-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.28);
  z-index: 25;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.delete-confirm-card {
  width: min(100%, 360px);
  background: #fff;
  border-radius: 16px;
  padding: 18px 16px 14px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.18);
  text-align: center;
}

.delete-confirm-icon-wrap {
  display: flex;
  justify-content: center;
  margin-bottom: 8px;
}

.delete-confirm-icon-circle {
  width: 30px;
  height: 30px;
  border-radius: 9999px;
  border: 2px solid #fca5a5;
  color: #ef4444;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}

.delete-confirm-title {
  margin: 0;
  color: #111827;
  font-size: 1.1rem;
  font-weight: 700;
}

.delete-confirm-message {
  margin: 8px 0 0;
  font-size: 0.82rem;
  line-height: 1.35;
  color: #6b7280;
}

.delete-confirm-error {
  margin: 10px 0 0;
  color: #b91c1c;
  font-size: 0.78rem;
}

.delete-confirm-actions {
  margin-top: 14px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.delete-cancel-btn,
.delete-confirm-btn {
  border: none;
  border-radius: 7px;
  height: 34px;
  font-size: 0.84rem;
  font-weight: 600;
  cursor: pointer;
}

.delete-cancel-btn {
  background: #f3f4f6;
  color: #374151;
}

.delete-confirm-btn {
  background: #ef4444;
  color: #fff;
}

.delete-cancel-btn:disabled,
.delete-confirm-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.confirm-btn-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.5);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
</style>