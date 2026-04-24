<template>
  <ion-page>
    <ion-header>
      <ion-toolbar>
        <div class="flex justify-between px-4 py-2">
          <img src="../assets/logo/deped.png" alt="Logo" class="h-20 w-30">
          <img src="../assets/logo/division.png" alt="Logo" class="h-20 w-20">
        </div>
      </ion-toolbar>
    </ion-header>
    
    <ion-content class="ion-padding-bottom">
      <div class="pb-32">
        <div class="mt-8">
          <img src="../assets/logo/doctor.png" alt="doctor" class="h-40 w-60 mx-auto rounded-full"/>
          <p id="archivo" class="text-center font-bold text-3xl mt-2 mb-4">WhiteCoat</p>
        </div>

        <div class="bg-[#D9D9D9] mx-8 md:mx-auto p-6 rounded-4xl width-full md:w-sm items-center">
          <p class="text-5xl font-bold mb-5 mt-2 text-center">LOG IN</p>

          <form class="w-full" @submit.prevent="handleSubmit">
            <!-- Username or Email Input -->
            <div class="mb-4">
              <ion-input
                v-model="formData.loginInput"
                class="custom-input mx-auto h-15 shadow bg-white rounded-full border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                id="login-input"
                type="text"
                placeholder="Enter username or email"
              />
              <label class="block text-gray-700 text-lg font-bold mb-2 mt-1" for="login-input">
                Username or Email
              </label>
            </div>

            <!-- Password Input -->
            <div class="mb-6">
              <ion-input
                v-model="formData.password"
                class="custom-input mx-auto h-15 shadow bg-white rounded-full border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                id="password"
                type="password"
                placeholder="Enter your password"
              >
                <ion-input-password-toggle style="--color: black;" slot="end"></ion-input-password-toggle>
              </ion-input>
              <label class="block text-gray-700 text-lg font-bold mb-2 mt-1" for="password">
                Password
              </label>
              <div class="text-right mt-1">
                <button
                  type="button"
                  class="forgot-password-link"
                  @click="openForgotPasswordModal"
                >
                  Forgot Password?
                </button>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="infoMessage && !showOtpSection" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-center">
              {{ infoMessage }}
            </div>

            <div v-if="errorMessage && !showOtpSection" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-center">
              {{ errorMessage }}
            </div>

            <!-- Submit Button -->
            <div class="mx-auto text-center">
              <ion-button 
                fill="clear"
                :disabled="isLoading"
                class="p-2 font-bold text-xl text-[#0034B7] rounded-full mb-4 mx-auto border border-black font-bold hover:bg-[#0034B7] hover:text-white transition duration-300 ease-in-out"
                type="submit"
              >
                <span v-if="!isLoading">Sign In</span>
                <span v-else>Signing in...</span>
              </ion-button>
              <div class="text-center mt-2">
                <p class="text-gray-700">
                  Don't have an account?
                  <router-link to="/signup" class="text-[#0034B7] font-bold hover:underline">
                    Sign Up
                  </router-link>
                </p>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div v-if="showForgotPasswordModal" class="forgot-modal-overlay" @click.self="closeForgotPasswordModal">
        <div class="forgot-modal-card">
          <h2 class="forgot-modal-title">Reset Password</h2>
          <p class="forgot-modal-subtitle" v-if="!forgotPasswordOtpSent">Enter your email and we will send a 6-digit OTP.</p>
          <p class="forgot-modal-subtitle" v-else>Enter the OTP and your new password to complete reset.</p>

          <ion-input
            v-model="forgotPasswordEmail"
            class="custom-input forgot-email-input"
            type="email"
            placeholder="Enter your email"
            :readonly="forgotPasswordOtpSent"
          />

          <div v-if="forgotPasswordOtpSent" class="forgot-reset-fields">
            <ion-input
              v-model="forgotPasswordOtp"
              class="custom-input forgot-email-input"
              type="text"
              inputmode="numeric"
              :maxlength="6"
              placeholder="Enter 6-digit OTP"
            />
            <ion-input
              v-model="forgotPasswordNewPassword"
              class="custom-input forgot-email-input"
              type="password"
              placeholder="New password"
            >
              <ion-input-password-toggle slot="end" style="--color: black;" />
            </ion-input>
            <ion-input
              v-model="forgotPasswordConfirmPassword"
              class="custom-input forgot-email-input"
              type="password"
              placeholder="Confirm new password"
            >
              <ion-input-password-toggle slot="end" style="--color: black;" />
            </ion-input>
            <p class="forgot-password-rule">
              Password must be at least 16 characters and include uppercase, lowercase, number, and special character.
            </p>
            <button type="button" class="forgot-resend" :disabled="forgotPasswordLoading" @click="submitForgotPassword">
              Resend OTP
            </button>
          </div>

          <div v-if="forgotPasswordInfo" class="forgot-message forgot-message-info">
            {{ forgotPasswordInfo }}
          </div>

          <div v-if="forgotPasswordError" class="forgot-message forgot-message-error">
            {{ forgotPasswordError }}
          </div>

          <div class="forgot-modal-actions">
            <button type="button" class="forgot-btn forgot-btn-secondary" @click="closeForgotPasswordModal" :disabled="forgotPasswordLoading">
              Cancel
            </button>
            <button type="button" class="forgot-btn forgot-btn-primary" @click="forgotPasswordOtpSent ? submitForgotPasswordReset() : submitForgotPassword()" :disabled="forgotPasswordLoading">
              <span v-if="!forgotPasswordLoading">{{ forgotPasswordOtpSent ? 'Reset Password' : 'Send OTP' }}</span>
              <span v-else>Sending...</span>
            </button>
          </div>
        </div>
      </div>

      <div v-if="showOtpSection" class="otp-modal-overlay" @click.self="returnToLogin">
        <div class="otp-modal-card">
          <button class="otp-back" type="button" @click="returnToLogin">
            <ion-icon :icon="chevronBackOutline" />
          </button>

          <div class="otp-content">
            <h1 class="otp-title">Verification</h1>
            <p class="otp-subtitle">We've sent you a 6-digit verification code to your mobile number</p>
            <p class="otp-destination">{{ otpDestinationLabel }}</p>

            <div class="otp-box-wrapper" @click="focusOtpInput">
              <input
                ref="otpInputRef"
                v-model="formData.otp"
                class="otp-hidden-input"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                @input="handleOtpInput"
                @keyup.enter="handleSubmit"
              />

              <div class="otp-boxes" role="group" aria-label="One-time password input">
                <div
                  v-for="(digit, index) in otpDigits"
                  :key="index"
                  class="otp-box"
                  :class="{ 'otp-box-active': formData.otp.length === index }"
                >
                  {{ digit }}
                </div>
              </div>
            </div>

            <p class="otp-expiry">Code expires in {{ otpCountdownLabel }}</p>

            <button
              type="button"
              class="otp-resend"
              :disabled="resendLoading || isLoading"
              @click="handleResendVerification"
            >
              <span v-if="!resendLoading">Resend Code</span>
              <span v-else>Sending...</span>
            </button>

            <div v-if="infoMessage" class="otp-message otp-message-info">
              {{ infoMessage }}
            </div>

            <div v-if="errorMessage" class="otp-message otp-message-error">
              {{ errorMessage }}
            </div>
          </div>
        </div>
      </div>
    </ion-content>
  </ion-page>
</template>

<script lang="ts">
import { useRouter } from "vue-router";
import { useRoute } from "vue-router";
import { ref, reactive, onMounted, computed, watch, nextTick, onUnmounted } from 'vue';
import axios from 'axios';
import { chevronBackOutline } from 'ionicons/icons';
import { 
  IonPage, IonHeader, IonToolbar, IonContent, IonInput, IonButton, IonInputPasswordToggle, IonIcon
} from '@ionic/vue';

export default {
  name: 'LoginPage',
  components: { 
    IonPage, IonHeader, IonToolbar, IonContent, IonInput, IonButton, IonInputPasswordToggle, IonIcon
  },
  setup() {
    const router = useRouter();
    const route = useRoute();
    
    const formData = reactive({
      loginInput: '',
      password: '',
      otp: '',
    });

    const errorMessage = ref('');
    const infoMessage = ref('');
    const isLoading = ref(false);
    const resendLoading = ref(false);
    const showResendButton = ref(false);
    const showOtpSection = ref(false);
    const showForgotPasswordModal = ref(false);
    const forgotPasswordEmail = ref('');
    const forgotPasswordOtp = ref('');
    const forgotPasswordNewPassword = ref('');
    const forgotPasswordConfirmPassword = ref('');
    const forgotPasswordOtpSent = ref(false);
    const forgotPasswordLoading = ref(false);
    const forgotPasswordInfo = ref('');
    const forgotPasswordError = ref('');
    const otpInputRef = ref<HTMLInputElement | null>(null);
    const otpCountdownSeconds = ref(120);
    let otpTimer: ReturnType<typeof setInterval> | null = null;
    const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '');

    const isValidEmail = (value: string) => /\S+@\S+\.\S+/.test(value);

    const isStrongPassword = (value: string): boolean => {
      if (value.length < 16) return false;
      const hasUpper = /[A-Z]/.test(value);
      const hasLower = /[a-z]/.test(value);
      const hasNumber = /[0-9]/.test(value);
      const hasSpecial = /[^A-Za-z0-9]/.test(value);
      return hasUpper && hasLower && hasNumber && hasSpecial;
    };

    const otpDigits = computed(() => {
      const digits = String(formData.otp || '').split('');
      return Array.from({ length: 6 }, (_, idx) => digits[idx] || '');
    });

    const otpCountdownLabel = computed(() => {
      const minutes = Math.floor(otpCountdownSeconds.value / 60);
      const seconds = otpCountdownSeconds.value % 60;
      return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    });

    const otpDestinationLabel = computed(() => {
      const value = String(formData.loginInput || '').trim();
      if (!value) return 'your registered contact';
      return value;
    });

    const stopOtpTimer = () => {
      if (otpTimer !== null) {
        clearInterval(otpTimer);
        otpTimer = null;
      }
    };

    const startOtpTimer = () => {
      stopOtpTimer();
      otpCountdownSeconds.value = 120;
      otpTimer = setInterval(() => {
        if (otpCountdownSeconds.value > 0) {
          otpCountdownSeconds.value -= 1;
          return;
        }
        stopOtpTimer();
      }, 1000);
    };

    const focusOtpInput = () => {
      nextTick(() => {
        otpInputRef.value?.focus();
      });
    };

    const handleOtpInput = (event: Event) => {
      const target = event.target as HTMLInputElement;
      formData.otp = String(target.value || '').replace(/\D/g, '').slice(0, 6);
    };

    const returnToLogin = () => {
      showOtpSection.value = false;
      formData.otp = '';
      errorMessage.value = '';
      infoMessage.value = '';
      stopOtpTimer();
    };

    const openForgotPasswordModal = () => {
      forgotPasswordEmail.value = isValidEmail(String(formData.loginInput || '')) ? String(formData.loginInput) : '';
      forgotPasswordOtp.value = '';
      forgotPasswordNewPassword.value = '';
      forgotPasswordConfirmPassword.value = '';
      forgotPasswordOtpSent.value = false;
      forgotPasswordInfo.value = '';
      forgotPasswordError.value = '';
      showForgotPasswordModal.value = true;
    };

    const closeForgotPasswordModal = () => {
      showForgotPasswordModal.value = false;
      forgotPasswordLoading.value = false;
      forgotPasswordOtp.value = '';
      forgotPasswordNewPassword.value = '';
      forgotPasswordConfirmPassword.value = '';
      forgotPasswordOtpSent.value = false;
      forgotPasswordInfo.value = '';
      forgotPasswordError.value = '';
    };

    const submitForgotPassword = async () => {
      forgotPasswordError.value = '';
      forgotPasswordInfo.value = '';

      const email = String(forgotPasswordEmail.value || '').trim();
      if (!isValidEmail(email)) {
        forgotPasswordError.value = 'Please enter a valid email address.';
        return;
      }

      forgotPasswordLoading.value = true;
      try {
        const response = await axios.post(`${API_URL}/api/auth/forgot-password`, { email }, {
          headers: {
            'Content-Type': 'application/json',
          },
        });
        forgotPasswordOtpSent.value = true;
        forgotPasswordInfo.value = response.data?.message || 'If the account exists, a reset OTP has been sent to your email.';
      } catch (error: any) {
        forgotPasswordError.value = error?.response?.data?.message || 'Failed to send reset OTP. Please try again.';
      } finally {
        forgotPasswordLoading.value = false;
      }
    };

    const submitForgotPasswordReset = async () => {
      forgotPasswordError.value = '';
      forgotPasswordInfo.value = '';

      const email = String(forgotPasswordEmail.value || '').trim();
      if (!isValidEmail(email)) {
        forgotPasswordError.value = 'Please enter a valid email address.';
        return;
      }

      const otp = String(forgotPasswordOtp.value || '').replace(/\D/g, '').slice(0, 6);
      forgotPasswordOtp.value = otp;
      if (!/^\d{6}$/.test(otp)) {
        forgotPasswordError.value = 'Please enter the 6-digit OTP.';
        return;
      }

      if (!forgotPasswordNewPassword.value || !forgotPasswordConfirmPassword.value) {
        forgotPasswordError.value = 'Please enter and confirm your new password.';
        return;
      }

      if (forgotPasswordNewPassword.value !== forgotPasswordConfirmPassword.value) {
        forgotPasswordError.value = 'Passwords do not match.';
        return;
      }

      if (!isStrongPassword(forgotPasswordNewPassword.value)) {
        forgotPasswordError.value = 'Password does not meet required strength.';
        return;
      }

      forgotPasswordLoading.value = true;
      try {
        const response = await axios.post(`${API_URL}/api/auth/reset-password`, {
          email,
          otp,
          password: forgotPasswordNewPassword.value,
        }, {
          headers: {
            'Content-Type': 'application/json',
          },
        });
        forgotPasswordInfo.value = response.data?.message || 'Password reset successful. You can now sign in.';
        forgotPasswordOtp.value = '';
        forgotPasswordNewPassword.value = '';
        forgotPasswordConfirmPassword.value = '';
        forgotPasswordOtpSent.value = false;
      } catch (error: any) {
        forgotPasswordError.value = error?.response?.data?.message || 'Failed to reset password. Please try again.';
      } finally {
        forgotPasswordLoading.value = false;
      }
    };

    onMounted(() => {
      const verifySent = String(route.query.verifyEmailSent || '') === '1' || String(route.query.verifyOtpSent || '') === '1';
      const emailVerified = String(route.query.emailVerified || '');
      const queryEmail = String(route.query.email || '').trim();

      if (verifySent) {
        infoMessage.value = queryEmail
          ? `OTP sent to ${queryEmail}. Please check your inbox.`
          : 'OTP sent. Please check your inbox.';
        showOtpSection.value = true;
        showResendButton.value = true;
      }

      if (emailVerified === '1') {
        infoMessage.value = 'Email verified successfully. Sign in will now require OTP each time.';
      } else if (emailVerified === '0') {
        errorMessage.value = 'Verification link is invalid or expired. Please use OTP verification below.';
        showOtpSection.value = true;
        showResendButton.value = true;
      }

      if (queryEmail && formData.loginInput === '') {
        formData.loginInput = queryEmail;
      }
    });

    const handleSubmit = async () => {
      errorMessage.value = '';
      infoMessage.value = '';

      if (!showOtpSection.value) {
        showResendButton.value = false;
      }

      if (!formData.loginInput || !formData.password) {
        errorMessage.value = 'Please enter username/email and password.';
        return;
      }

      if (showOtpSection.value && !/^\d{6}$/.test(String(formData.otp || '').trim())) {
        errorMessage.value = 'Enter the 6-digit OTP sent to your email.';
        return;
      }

      isLoading.value = true;

      try {
        const loginPayload = {
          loginInput: formData.loginInput,
          password: formData.password,
        };
        if (showOtpSection.value) {
          loginPayload.otp = String(formData.otp || '').trim();
        }

        const response = await axios.post(`${API_URL}/api/auth/login`, loginPayload, {
          headers: {
            'Content-Type': 'application/json',
          },
        });

        if (response.data.token) {
          localStorage.setItem('token', response.data.token);
          localStorage.setItem('whitecoat_user', JSON.stringify(response.data.user));
          localStorage.removeItem('whitecoat_terms_accepted');
          window.dispatchEvent(new Event('whitecoat-user-updated'));
          router.push('/tabs/tab1');
          return;
        }

        if (response.data?.code === 'LOGIN_OTP_REQUIRED') {
          infoMessage.value = response.data?.message || 'OTP sent. Enter the 6-digit code to continue login.';
          showOtpSection.value = true;
          showResendButton.value = true;
          startOtpTimer();
          focusOtpInput();
          return;
        }

        errorMessage.value = response.data?.message || 'Authentication failed.';

      } catch (error: any) {
        const errorMsg = error.response?.data?.message || error.message || 'Could not connect to server.';
        const code = error.response?.data?.code;
        const status = error.response?.status;
        
        console.error('Login error:', { status, code, message: errorMsg, fullError: error });
        
        if (code === 'EMAIL_NOT_VERIFIED' || code === 'LOGIN_OTP_REQUIRED') {
          errorMessage.value = error.response?.data?.message || 'OTP is required to continue login.';
          showResendButton.value = true;
          showOtpSection.value = true;
          const unverifiedEmail = String(error.response?.data?.email || '').trim();
          if (unverifiedEmail && !isValidEmail(formData.loginInput)) {
            formData.loginInput = unverifiedEmail;
          }
        } else {
          errorMessage.value = errorMsg;
        }
      } finally {
        isLoading.value = false;
      }
    };

    const handleResendVerification = async () => {
      errorMessage.value = '';
      infoMessage.value = '';

      const email = String(formData.loginInput || '').trim();
      if (!email) {
        errorMessage.value = 'Enter your username/email first, then click Resend Code.';
        return;
      }

      if (!String(formData.password || '').trim()) {
        errorMessage.value = 'Enter your password first, then click Resend OTP.';
        return;
      }

      resendLoading.value = true;
      try {
        const response = await axios.post(`${API_URL}/api/auth/login`, {
          loginInput: email,
          password: String(formData.password || ''),
        }, {
          headers: {
            'Content-Type': 'application/json',
          },
        });

        if (response.data?.code === 'LOGIN_OTP_REQUIRED') {
          infoMessage.value = response.data?.message || 'OTP sent. Please check your inbox.';
        } else {
          infoMessage.value = response.data?.message || 'OTP sent. Please check your inbox.';
        }
        showOtpSection.value = true;
        showResendButton.value = true;
        formData.otp = '';
        startOtpTimer();
        focusOtpInput();
      } catch (error: any) {
        if (error.response?.data?.message) {
          errorMessage.value = error.response.data.message;
        } else {
          errorMessage.value = 'Failed to send OTP. Please try again.';
        }
      } finally {
        resendLoading.value = false;
      }
    };

    watch(showOtpSection, (visible) => {
      if (!visible) {
        stopOtpTimer();
        return;
      }
      startOtpTimer();
      focusOtpInput();
    });

    watch(() => formData.otp, (value) => {
      if (!showOtpSection.value || isLoading.value) return;
      if (String(value).length === 6) {
        handleSubmit();
      }
    });

    onUnmounted(() => {
      stopOtpTimer();
    });

    return {
      formData,
      handleSubmit,
      handleResendVerification,
      errorMessage,
      infoMessage,
      isLoading,
      resendLoading,
      showResendButton,
      showOtpSection,
      showForgotPasswordModal,
      forgotPasswordEmail,
      forgotPasswordOtp,
      forgotPasswordNewPassword,
      forgotPasswordConfirmPassword,
      forgotPasswordOtpSent,
      forgotPasswordLoading,
      forgotPasswordInfo,
      forgotPasswordError,
      otpInputRef,
      otpDigits,
      otpCountdownLabel,
      otpDestinationLabel,
      focusOtpInput,
      handleOtpInput,
      returnToLogin,
      openForgotPasswordModal,
      closeForgotPasswordModal,
      submitForgotPassword,
      submitForgotPasswordReset,
      chevronBackOutline,
    }
  }
};
</script>

<style scoped>
ion-page, ion-header, ion-toolbar, ion-content {
    --background: #F0F0F0;
    --color: black;
}
#archivo {
  font-family: 'Archivo Black', sans-serif;
}
.custom-input {
  --highlight-color: none;
  --padding-start: 10px;
  --padding-end: 10px;
  --color: black;
}

.forgot-password-link {
  background: transparent;
  border: none;
  color: #0034b7;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
}

.forgot-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 998;
  background: rgba(18, 18, 24, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.forgot-modal-card {
  width: min(100%, 380px);
  background: #ffffff;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.22);
}

.forgot-modal-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: #151515;
}

.forgot-modal-subtitle {
  margin: 8px 0 14px;
  color: #6b7280;
  font-size: 0.93rem;
}

.forgot-email-input {
  --background: #f9fafb;
  --border-radius: 12px;
  --padding-start: 14px;
  --padding-end: 14px;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  margin-bottom: 10px;
}

.forgot-reset-fields {
  margin-top: 6px;
}

.forgot-password-rule {
  margin: 2px 2px 8px;
  font-size: 0.76rem;
  color: #6b7280;
}

.forgot-resend {
  border: none;
  background: transparent;
  color: #0034b7;
  font-size: 0.85rem;
  font-weight: 700;
  padding: 0;
  margin-bottom: 6px;
}

.forgot-message {
  border-radius: 10px;
  padding: 8px 10px;
  font-size: 0.85rem;
  margin-top: 6px;
}

.forgot-message-info {
  background: #eaf8ef;
  color: #146c2e;
}

.forgot-message-error {
  background: #fdecec;
  color: #9a2626;
}

.forgot-modal-actions {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.forgot-btn {
  border-radius: 10px;
  padding: 8px 14px;
  font-weight: 700;
  border: none;
  cursor: pointer;
}

.forgot-btn-secondary {
  background: #e5e7eb;
  color: #111827;
}

.forgot-btn-primary {
  background: #0034b7;
  color: #ffffff;
}

.forgot-btn:disabled {
  opacity: 0.6;
  cursor: default;
}

.otp-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 999;
  background: rgba(18, 18, 24, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 18px;
}

.otp-modal-card {
  width: min(100%, 390px);
  background: #f7f7fa;
  border-radius: 22px;
  box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
  padding: 16px 18px 24px;
}

.otp-back {
  border: none;
  background: transparent;
  color: #1f1f1f;
  font-size: 24px;
  line-height: 1;
  padding: 4px;
}

.otp-content {
  margin-top: 22px;
  text-align: center;
}

.otp-title {
  font-size: 34px;
  line-height: 1.1;
  font-weight: 700;
  color: #1d1d1f;
  margin: 0;
}

.otp-subtitle {
  margin: 12px auto 4px;
  max-width: 320px;
  font-size: 14px;
  line-height: 1.45;
  color: #8a8a95;
}

.otp-destination {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: #2c2c32;
}

.otp-box-wrapper {
  margin-top: 30px;
  position: relative;
}

.otp-hidden-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}

.otp-boxes {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 10px;
}

.otp-box {
  height: 52px;
  border-radius: 12px;
  background: #ffffff;
  border: 1px solid #ececf3;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  font-weight: 700;
  color: #232327;
}

.otp-box-active {
  border-color: #6a5cff;
  box-shadow: 0 0 0 2px rgba(106, 92, 255, 0.16);
}

.otp-expiry {
  margin-top: 16px;
  margin-bottom: 8px;
  font-size: 13px;
  color: #8e8e99;
}

.otp-resend {
  border: none;
  background: transparent;
  color: #5f52e5;
  font-size: 14px;
  font-weight: 700;
  padding: 0;
}

.otp-resend:disabled {
  opacity: 0.6;
}

.otp-message {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  font-size: 13px;
}

.otp-message-info {
  background: #eaf8ef;
  color: #146c2e;
}

.otp-message-error {
  background: #fdecec;
  color: #9a2626;
}
</style>