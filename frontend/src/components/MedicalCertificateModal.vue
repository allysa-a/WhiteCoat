<template>
  <ion-modal :is-open="isOpen" @didDismiss="closeModal" class="certificate-modal">
    <div class="modal-wrapper">
      <div class="modal-header">
        <span class="modal-title">Medical Certificate</span>
        <button type="button" class="close-btn" aria-label="Close" @click="closeModal">
          <font-awesome-icon :icon="['fas', 'xmark']" class="text-xl" />
        </button>
      </div>

      <div class="preview-area preview-a4">
        <iframe
          v-if="pdfPreviewUrl"
          :src="pdfPreviewUrl"
          class="pdf-iframe"
          title="Medical certificate document"
        />
        <p v-else-if="isLoadingPdf" class="preview-placeholder">Loading document preview…</p>
        <p v-else class="preview-placeholder">Document ready. Print or save below to view your template.</p>
      </div>

      <p v-if="printMessage" class="print-message">{{ printMessage }}</p>
      <div class="modal-actions">
        <button type="button" class="action-btn" @click="saveAsWord" :disabled="!generatedDocxBlob">
          <font-awesome-icon :icon="['fas', 'floppy-disk']" class="text-xl" />
          <span>Save as Word</span>
        </button>
        <button type="button" class="action-btn" @click="onEdit">
          <font-awesome-icon :icon="['fas', 'pen-to-square']" class="text-xl" />
          <span>Edit</span>
        </button>
      </div>
    </div>
  </ion-modal>
</template>

<script lang="ts">
import { defineComponent, ref, watch } from "vue";
import { IonModal, IonIcon } from "@ionic/vue";
import axios from "axios";

export default defineComponent({
  name: "MedicalCertificateModal",
  components: { IonModal, IonIcon },
  props: {
    form: { type: Object, required: true },
    isOpen: { type: Boolean, required: true },
    templateImageUrl: { type: String, default: null },
    generatedDocxBlob: { type: Object as () => Blob | null, default: null },
    generatedFilename: { type: String, default: "medical_certificate.docx" },
    userId: { type: Number, default: null },
    apiUrl: { type: String, default: () => ((import.meta as any).env?.VITE_API_BASE_URL || '') },
  },
  emits: ["close", "edit"],
  setup(props, { emit }) {
    const isPrinting = ref(false);
    const pdfPreviewUrl = ref<string | null>(null);
    const isLoadingPdf = ref(false);
    const printMessage = ref("");

    const closeModal = () => emit("close");
    const onEdit = () => emit("edit");

    const buildPayload = () => ({
      user_id: props.userId,
      name: (props.form?.name ?? "").toString().trim(),
      age: props.form?.age ?? "",
      gender: props.form?.gender ?? "",
      dateIssued: props.form?.dateIssued ?? "",
      address: (props.form?.address ?? "").toString().trim(),
      impression: (props.form?.impression ?? "").toString().trim(),
      remarks: (props.form?.remarks ?? "").toString().trim(),
    });

    const fetchPdfPreview = async () => {
      const uid = props.userId;
      const baseUrl = (props.apiUrl || "").replace(/\/$/, "");
      if (uid == null || !baseUrl) return;
      isLoadingPdf.value = true;
      pdfPreviewUrl.value = null;
      try {
        const res = await axios.post(
          `${baseUrl}/api/doctor/medical-certificate/generate`,
          buildPayload(),
          { responseType: "blob", params: { format: "pdf" } }
        );
        const blob = res.data as Blob;
        if (blob.type === "application/pdf") {
          pdfPreviewUrl.value = URL.createObjectURL(blob);
        }
      } catch {
        // PDF not available
      } finally {
        isLoadingPdf.value = false;
      }
    };

    watch(() => props.isOpen, (open) => {
      if (open) fetchPdfPreview();
      else {
        if (pdfPreviewUrl.value) {
          URL.revokeObjectURL(pdfPreviewUrl.value);
          pdfPreviewUrl.value = null;
        }
      }
    });

    const saveAsWord = () => {
      const blob = props.generatedDocxBlob;
      if (!blob) return;
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = props.generatedFilename || "medical_certificate.docx";
      a.click();
      URL.revokeObjectURL(url);
    };

    return {
      isPrinting,
      printMessage,
      pdfPreviewUrl,
      isLoadingPdf,
      closeModal,
      onEdit,
      saveAsWord
    };
  },
});
</script>

<style scoped>
.certificate-modal::part(content) {
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
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.modal-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: #111;
}

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
.close-btn:hover {
  color: #111;
}

.preview-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  background: #f9fafb;
  display: flex;
  justify-content: center;
  align-items: center;
}
.preview-area.preview-a4 {
  aspect-ratio: 210 / 297;
  min-height: 300px;
}

.pdf-iframe {
  width: 100%;
  height: 100%;
  min-height: 400px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  background: #fff;
}

.preview-placeholder {
  color: #6b7280;
  font-size: 0.9375rem;
  margin: 0;
  padding: 24px;
}

.print-message {
  padding: 10px 16px;
  margin: 0;
  font-size: 0.875rem;
  color: #0c5460;
  background: #d1ecf1;
  border: 1px solid #bee5eb;
  border-radius: 6px;
}

.modal-actions {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 6px;
  padding: 12px 16px;
  border-top: 1px solid #e5e7eb;
  background: #fff;
  flex-shrink: 0;
}

.action-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
  font-size: 1rem;
  cursor: pointer;
  color: #374151;
  width: 100%;
}
.action-btn:hover:not(:disabled) {
  background: #f9fafb;
  border-color: #9ca3af;
}
.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
