<template>
  <ion-page>
    <div v-if="showTermsModal" class="terms-overlay">
      <div class="terms-modal">
        <h2 class="text-2xl font-bold text-center mb-4">Terms &amp; Conditions</h2>
        <div class="terms-content text-sm md:text-base leading-relaxed">
          <p class="font-bold mb-3">DATA PRIVACY AND CONFIDENTIALITY AGREEMENT FOR SYSTEM ADMINISTRATORS</p>
          <p class="mb-3">
            By accessing this administrative panel WHITECOAT, you as primary user agree to the following terms in compliance with the Philippines Data Privacy Act of 2012:
          </p>
          <p class="mb-2"><span class="font-semibold">Confidentiality:</span> All Personal Information (PI) and Sensitive Personal Information (SPI) accessed within this system is strictly confidential. You shall not disclose, copy, or distribute any data to unauthorized parties.</p>
          <p class="mb-2"><span class="font-semibold">Authorized Use Only:</span> Data shall only be processed for legitimate, declared purposes related to your role. Unauthorized browsing or processing is a violation of the DPA.</p>
          <p class="mb-2"><span class="font-semibold">Access Control:</span> Your access credentials are for your use only. Sharing passwords is strictly prohibited.</p>
          <p class="mb-2"><span class="font-semibold">Security Measures:</span> You must ensure that your session is locked or closed when leaving your workstation. You must immediately report any suspected security breach to the Data Protection Officer (DPO).</p>
          <p class="mb-2"><span class="font-semibold">Data Retention/Disposal:</span> You must adhere to the company's data retention policy. Data should not be downloaded or stored on local devices unless authorized and securely encrypted.</p>
          <p class="mt-4">I have read, understood, and agree to abide by these terms.</p>
          <ion-item lines="none" class="terms-checkbox-item mt-4">
            <ion-checkbox v-model="isTermsAccepted" label-placement="end">I Accept</ion-checkbox>
          </ion-item>
        </div>

        <div class="flex justify-end gap-3">
          <ion-button fill="outline" color="medium" @click="handleDisagree">Disagree</ion-button>
          <ion-button :disabled="!isTermsAccepted" @click="handleTermsSubmit">Submit</ion-button>
        </div>
      </div>
    </div>

    <ion-tabs>
      <ion-router-outlet></ion-router-outlet>
      <ion-tab-bar slot="bottom" :class="{ 'pointer-events-none opacity-40': showTermsModal }">
        <ion-tab-button tab="tab1" href="/tabs/tab1">
          <font-awesome-icon :icon="[ 'fas', 'home' ]" class="text-4xl md:text-2xl font-bold" />
        </ion-tab-button>

        <ion-tab-button tab="tab2" href="/tabs/tab2">
          <font-awesome-icon :icon="[ 'fas', 'prescription' ]" class="text-4xl md:text-2xl font-bold" />
        </ion-tab-button>

        <ion-tab-button tab="tab3" href="/tabs/tab3">
          <font-awesome-icon :icon="[ 'fas', 'file-lines' ]" class="text-4xl md:text-2xl font-bold" />
        </ion-tab-button>

        <ion-tab-button tab="tab4" href="/tabs/tab4">
          <font-awesome-icon :icon="[ 'fas', 'flask' ]" class="text-4xl md:text-2xl font-bold" />
        </ion-tab-button>

        <ion-tab-button tab="tab5" href="/tabs/tab5">
          <font-awesome-icon :icon="[ 'fas', 'gear' ]" class="text-4xl md:text-2xl font-bold" />
        </ion-tab-button>
      </ion-tab-bar>
    </ion-tabs>
  </ion-page>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { IonTabBar, IonTabButton, IonTabs, IonPage, IonRouterOutlet, IonButton, IonCheckbox, IonItem, onIonViewWillEnter } from '@ionic/vue';

const router = useRouter();
const isTermsAccepted = ref(false);
const showTermsModal = ref(localStorage.getItem('whitecoat_terms_accepted') !== 'true');

const syncTermsModalState = () => {
  const needsAgreement = localStorage.getItem('whitecoat_terms_accepted') !== 'true';
  showTermsModal.value = needsAgreement;

  if (!needsAgreement) {
    isTermsAccepted.value = false;
  }
};

const handleTermsSubmit = () => {
  if (!isTermsAccepted.value) return;
  localStorage.setItem('whitecoat_terms_accepted', 'true');
  showTermsModal.value = false;
};

const handleDisagree = () => {
  localStorage.removeItem('token');
  localStorage.removeItem('whitecoat_user');
  localStorage.removeItem('whitecoat_files');
  localStorage.removeItem('whitecoat_terms_accepted');
  window.dispatchEvent(new Event('whitecoat-user-updated'));
  router.push({ name: 'Login' });
};

onIonViewWillEnter(() => {
  syncTermsModalState();
});

onMounted(() => {
  window.addEventListener('whitecoat-user-updated', syncTermsModalState);
  syncTermsModalState();
});

onUnmounted(() => {
  window.removeEventListener('whitecoat-user-updated', syncTermsModalState);
});
</script>

<style scoped>
ion-tab-button, ion-tab-bar, ion-page {
  --background: #023E8A;;
  --color-selected: #023E8A;
  --color: white;
  --color-hover: none;
}
ion-tab-button.tab-selected::part(native) {
  background: white;
}
ion-tab-button:hover font-awesome-icon {
  color: white;
}
ion-tab-button.tab-selected:hover font-awesome-icon {
  transition: color 0.3s;
}

.terms-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.terms-modal {
  width: 100%;
  max-width: 760px;
  background: #f0f0f0;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  padding: 20px;
}

.terms-content {
  max-height: 380px;
  overflow-y: auto;
  border: 1px solid #d1d5db;
  background: #ffffff;
  padding: 14px;
}

.terms-checkbox-item {
  --background: transparent;
  --inner-padding-end: 0;
  --padding-start: 0;
}
</style>
