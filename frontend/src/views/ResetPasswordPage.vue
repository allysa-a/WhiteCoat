<template>
  <ion-page>
    <ion-content class="ion-padding">
      <div class="reset-wrap">
        <div class="reset-card">
          <h1 class="reset-title">Reset Password</h1>
          <p class="reset-subtitle">Enter your email, OTP, and create a new secure password.</p>

          <form @submit.prevent="handleResetPassword">
            <div class="field-wrap">
              <ion-input
                v-model="email"
                type="email"
                placeholder="Email"
                class="reset-input"
              />
            </div>

            <div class="field-wrap">
              <ion-input
                v-model="otp"
                type="text"
                inputmode="numeric"
                :maxlength="6"
                placeholder="6-digit OTP"
                class="reset-input"
              />
            </div>

            <div class="field-wrap">
              <ion-input
                v-model="password"
                type="password"
                placeholder="New password"
                class="reset-input"
              >
                <ion-input-password-toggle slot="end" style="--color: black;" />
              </ion-input>
            </div>

            <div class="field-wrap">
              <ion-input
                v-model="confirmPassword"
                type="password"
                placeholder="Confirm new password"
                class="reset-input"
              >
                <ion-input-password-toggle slot="end" style="--color: black;" />
              </ion-input>
            </div>

            <p class="password-rule">
              Password must be at least 16 characters and include uppercase, lowercase, number, and special character.
            </p>

            <div v-if="infoMessage" class="reset-message reset-message-info">
              {{ infoMessage }}
            </div>

            <div v-if="errorMessage" class="reset-message reset-message-error">
              {{ errorMessage }}
            </div>

            <ion-button expand="block" type="submit" :disabled="submitting" class="submit-btn">
              <span v-if="!submitting">Reset Password</span>
              <span v-else>Resetting...</span>
            </ion-button>
          </form>

          <button type="button" class="back-link" @click="goToLogin">
            Back to Login
          </button>
        </div>
      </div>
    </ion-content>
  </ion-page>
</template>

<script lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { IonPage, IonContent, IonInput, IonButton, IonInputPasswordToggle } from '@ionic/vue';

export default {
  name: 'ResetPasswordPage',
  components: {
    IonPage,
    IonContent,
    IonInput,
    IonButton,
    IonInputPasswordToggle,
  },
  setup() {
    const router = useRouter();
    const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '');

    const email = ref('');
    const otp = ref('');
    const password = ref('');
    const confirmPassword = ref('');
    const submitting = ref(false);
    const errorMessage = ref('');
    const infoMessage = ref('');

    const isStrongPassword = (value: string): boolean => {
      if (value.length < 16) return false;
      const hasUpper = /[A-Z]/.test(value);
      const hasLower = /[a-z]/.test(value);
      const hasNumber = /[0-9]/.test(value);
      const hasSpecial = /[^A-Za-z0-9]/.test(value);
      return hasUpper && hasLower && hasNumber && hasSpecial;
    };

    const handleResetPassword = async () => {
      errorMessage.value = '';
      infoMessage.value = '';

      const cleanEmail = String(email.value || '').trim();
      if (!/\S+@\S+\.\S+/.test(cleanEmail)) {
        errorMessage.value = 'Please enter a valid email address.';
        return;
      }

      if (!password.value || !confirmPassword.value) {
        errorMessage.value = 'Please complete both password fields.';
        return;
      }

      const cleanOtp = String(otp.value || '').replace(/\D/g, '').slice(0, 6);
      otp.value = cleanOtp;
      if (!/^\d{6}$/.test(cleanOtp)) {
        errorMessage.value = 'Please enter the 6-digit OTP sent to your email.';
        return;
      }

      if (password.value !== confirmPassword.value) {
        errorMessage.value = 'Passwords do not match.';
        return;
      }

      if (!isStrongPassword(password.value)) {
        errorMessage.value = 'Password does not meet the required strength.';
        return;
      }

      submitting.value = true;
      try {
        const response = await axios.post(`${API_URL}/api/auth/reset-password`, {
          email: cleanEmail,
          otp: cleanOtp,
          password: password.value,
        });
        infoMessage.value = response.data?.message || 'Password reset successful. You can now sign in.';
        email.value = '';
        otp.value = '';
        password.value = '';
        confirmPassword.value = '';
      } catch (error: any) {
        errorMessage.value = error?.response?.data?.message || 'Failed to reset password. Please try again.';
      } finally {
        submitting.value = false;
      }
    };

    const goToLogin = () => {
      router.push('/');
    };

    return {
      email,
      password,
      confirmPassword,
      otp,
      submitting,
      errorMessage,
      infoMessage,
      handleResetPassword,
      goToLogin,
    };
  },
};
</script>

<style scoped>
ion-page,
ion-content {
  --background: #f3f4f6;
  --color: #111827;
}

.reset-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.reset-card {
  width: min(100%, 460px);
  background: #ffffff;
  border-radius: 18px;
  box-shadow: 0 16px 38px rgba(0, 0, 0, 0.18);
  padding: 26px 22px;
}

.reset-title {
  margin: 0;
  font-size: 1.8rem;
  font-weight: 700;
  color: #111827;
}

.reset-subtitle {
  margin: 6px 0 18px;
  color: #6b7280;
  font-size: 0.95rem;
}

.field-wrap {
  margin-bottom: 12px;
}

.reset-input {
  --background: #f9fafb;
  --border-radius: 12px;
  --padding-start: 14px;
  --padding-end: 14px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
}

.password-rule {
  margin: 4px 2px 12px;
  font-size: 0.82rem;
  color: #6b7280;
}

.reset-message {
  border-radius: 10px;
  padding: 10px 12px;
  font-size: 0.9rem;
  margin-bottom: 12px;
}

.reset-message-info {
  background: #eaf8ef;
  color: #146c2e;
}

.reset-message-error {
  background: #fdecec;
  color: #9a2626;
}

.submit-btn {
  --background: #0034b7;
  --background-hover: #143aa0;
  --border-radius: 12px;
  font-weight: 700;
}

.back-link {
  margin-top: 10px;
  border: none;
  background: transparent;
  color: #0034b7;
  font-weight: 700;
  cursor: pointer;
}
</style>
