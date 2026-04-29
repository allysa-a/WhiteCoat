<template>
  <ion-page>
    <ion-content>
      <div
        class="min-h-screen flex items-center justify-center bg-[#EEF4FB] p-4"
      >
        <div
          class="w-full max-w-4xl flex rounded-3xl shadow-2xl overflow-hidden min-h-[580px]"
        >
          <!-- Left Panel -->
          <div
            class="hidden md:flex flex-col justify-between w-2/5 p-10 relative overflow-hidden"
            style="
              background: linear-gradient(
                145deg,
                #5ba3d9 0%,
                #3b82c4 60%,
                #2b6faf 100%
              );
            "
          >
            <!-- Decorative circles -->
            <div
              class="absolute -top-16 -left-16 w-56 h-56 rounded-full bg-white opacity-10"
            ></div>
            <div
              class="absolute bottom-10 -right-12 w-48 h-48 rounded-full bg-white opacity-10"
            ></div>
            <div
              class="absolute top-1/2 -left-8 w-32 h-32 rounded-full bg-white opacity-10"
            ></div>

            <!-- Logo -->
            <div class="flex items-center gap-3 z-10">
              <img
                src="../assets/logo/deped.png"
                alt="DepEd Logo"
                class="h-12 w-auto object-contain"
              />
            </div>

            <!-- Center Content -->
            <div class="z-10">
              <img
                src="../assets/logo/doctorlogo.jpg"
                alt="Doctor"
                class="h-24 w-24 rounded-full object-cover border-4 border-white border-opacity-30 mb-6 shadow-lg"
              />
              <h1
                id="archivo"
                class="text-white text-4xl font-black leading-tight mb-3"
              >
                White Coat
              </h1>
              <p class="text-[#B8D9F5] text-sm leading-relaxed max-w-xs">
                Your trusted health companion for DepEd schools. Secure, fast,
                and always accessible.
              </p>
            </div>

            <!-- Bottom note -->
            <p class="text-[#9AC8EE] text-xs z-10">
              © {{ new Date().getFullYear() }} White Coat — DepEd Division
            </p>
          </div>

          <!-- Right Panel -->
          <div
            class="flex-1 bg-white flex flex-col justify-center px-8 md:px-12 py-10"
          >
            <!-- Mobile logo -->
            <div class="flex md:hidden items-center gap-3 mb-8">
              <img
                src="../assets/logo/deped.png"
                alt="DepEd Logo"
                class="h-10 w-auto object-contain"
              />
              <span id="archivo" class="text-[#3B82C4] text-xl font-black"
                >White Coat</span
              >
            </div>

            <h2 class="text-3xl font-bold text-gray-800 mb-1">Welcome back</h2>
            <p class="text-gray-400 text-sm mb-8">
              Sign in to your account to continue
            </p>

            <form class="w-full" @submit.prevent="handleSubmit">
              <!-- Username or Email -->
              <div class="mb-5">
                <label
                  class="block text-gray-600 text-sm font-semibold mb-2"
                  for="login-input"
                >
                  Username or Email
                </label>
                <ion-input
                  v-model="formData.loginInput"
                  class="custom-input block w-full h-12 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-gray-700 text-sm focus:outline-none focus:border-[#3B82C4] transition"
                  id="login-input"
                  type="text"
                  placeholder="Enter username or email"
                />
              </div>

              <!-- Password -->
              <div class="mb-2">
                <label
                  class="block text-gray-600 text-sm font-semibold mb-2"
                  for="password"
                >
                  Password
                </label>
                <ion-input
                  v-model="formData.password"
                  class="custom-input block w-full h-12 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-gray-700 text-sm focus:outline-none focus:border-[#3B82C4] transition"
                  id="password"
                  type="password"
                  placeholder="Enter your password"
                >
                  <ion-input-password-toggle
                    style="--color: #3b82c4"
                    slot="end"
                  ></ion-input-password-toggle>
                </ion-input>
              </div>

              <!-- Remember Me and Forgot Password -->
              <div class="flex items-center justify-between mt-3 mb-2">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input
                    v-model="rememberMe"
                    type="checkbox"
                    class="w-4 h-4 rounded border-[#C9DFF0] accent-[#3B82C4] cursor-pointer"
                  />
                  <span class="text-gray-500 text-sm">Remember me</span>
                </label>
                <button
                  type="button"
                  class="text-[#3B82C4] text-sm font-semibold hover:underline"
                  @click="openForgotPasswordModal"
                >
                  Forgot Password?
                </button>
              </div>

              <!-- Messages -->
              <div
                v-if="infoMessage && !showOtpSection"
                class="bg-green-50 border border-green-300 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 text-center"
              >
                {{ infoMessage }}
              </div>
              <div
                v-if="errorMessage && !showOtpSection"
                class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 text-center"
              >
                {{ errorMessage }}
              </div>

              <!-- Submit -->
              <ion-button
                fill="clear"
                :disabled="isLoading"
                class="w-full h-12 bg-[#3B82C4] text-white font-bold text-base rounded-xl hover:bg-[#2B6FAF] transition duration-200 flex items-center justify-center"
                type="submit"
                expand="block"
                style="
                  --background: #3b82c4;
                  --background-hover: #2b6faf;
                  --color: white;
                  --border-radius: 12px;
                  height: 48px;
                  font-weight: 700;
                  font-size: 1rem;
                "
              >
                <span v-if="!isLoading">Sign In</span>
                <span v-else>Signing in...</span>
              </ion-button>

              <!-- Sign Up Link -->
              <p class="text-center text-gray-500 text-sm mt-6">
                Don't have an account?
                <router-link
                  to="/signup"
                  class="text-[#3B82C4] font-bold hover:underline"
                  >Sign Up</router-link
                >
              </p>
            </form>
          </div>
        </div>
      </div>

      <!-- Forgot Password Modal -->
      <div
        v-if="showForgotPasswordModal"
        class="fixed inset-0 z-[998] bg-white/10 backdrop-blur-[2px] flex items-center justify-center p-4"
        @click.self="closeForgotPasswordModal"
      >
        <div class="w-full max-w-sm bg-white rounded-2xl p-6 shadow-2xl">
          <h2 class="text-xl font-bold text-gray-800 mb-1">Reset Password</h2>
          <p class="text-gray-400 text-sm mb-4" v-if="!forgotPasswordOtpSent">
            Enter your email and we'll send a 6-digit OTP.
          </p>
          <p class="text-gray-400 text-sm mb-4" v-else>
            Enter the OTP and your new password to complete reset.
          </p>

          <ion-input
            v-model="forgotPasswordEmail"
            class="custom-input w-full h-11 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-sm text-gray-700 mb-3"
            type="email"
            placeholder="Enter your email"
            :readonly="forgotPasswordOtpSent"
          />

          <div v-if="forgotPasswordOtpSent" class="space-y-3">
            <ion-input
              v-model="forgotPasswordOtp"
              class="custom-input w-full h-11 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-sm text-gray-700"
              type="text"
              inputmode="numeric"
              :maxlength="6"
              placeholder="Enter 6-digit OTP"
            />
            <ion-input
              v-model="forgotPasswordNewPassword"
              class="custom-input w-full h-11 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-sm text-gray-700"
              type="password"
              placeholder="New password"
            >
              <ion-input-password-toggle slot="end" style="--color: #3b82c4" />
            </ion-input>
            <ion-input
              v-model="forgotPasswordConfirmPassword"
              class="custom-input w-full h-11 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-sm text-gray-700"
              type="password"
              placeholder="Confirm new password"
            >
              <ion-input-password-toggle slot="end" style="--color: #3b82c4" />
            </ion-input>
            <p class="text-xs text-gray-400">
              Password must be at least 16 characters and include uppercase,
              lowercase, number, and special character.
            </p>
            <button
              type="button"
              class="text-[#3B82C4] text-sm font-bold hover:underline"
              :disabled="forgotPasswordLoading"
              @click="submitForgotPassword"
            >
              Resend OTP
            </button>
          </div>

          <div
            v-if="forgotPasswordInfo"
            class="mt-3 bg-green-50 border border-green-300 text-green-700 rounded-xl px-4 py-2 text-sm"
          >
            {{ forgotPasswordInfo }}
          </div>
          <div
            v-if="forgotPasswordError"
            class="mt-3 bg-red-50 border border-red-300 text-red-700 rounded-xl px-4 py-2 text-sm"
          >
            {{ forgotPasswordError }}
          </div>

          <div class="flex justify-end gap-3 mt-5">
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 font-semibold text-sm hover:bg-gray-200 transition"
              @click="closeForgotPasswordModal"
              :disabled="forgotPasswordLoading"
            >
              Cancel
            </button>
            <button
              type="button"
              class="px-4 py-2 rounded-xl bg-[#3B82C4] text-white font-bold text-sm hover:bg-[#2B6FAF] transition disabled:opacity-60"
              @click="
                forgotPasswordOtpSent
                  ? submitForgotPasswordReset()
                  : submitForgotPassword()
              "
              :disabled="forgotPasswordLoading"
            >
              <span v-if="!forgotPasswordLoading">{{
                forgotPasswordOtpSent ? "Reset Password" : "Send OTP"
              }}</span>
              <span v-else>Sending...</span>
            </button>
          </div>
        </div>
      </div>

      <!-- OTP Modal -->
      <div
        v-if="showOtpSection"
        class="fixed inset-0 z-[999] bg-white/10 backdrop-blur-sm flex items-center justify-center p-4"
        @click.self="returnToLogin"
      >
        <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-4">
          <button
            class="text-gray-600 hover:text-gray-900 text-2xl mb-4 flex items-center gap-1"
            type="button"
            @click="returnToLogin"
          >
            <ion-icon :icon="chevronBackOutline" />
          </button>

          <div class="text-center">
            <div
              class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-[#D9EDF9] mb-4"
            >
              <svg
                class="w-7 h-7 text-[#3B82C4]"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
              </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Verification</h1>
            <p class="text-gray-400 text-sm mt-2 mb-1">
              We've sent a 6-digit code to
            </p>
            <p class="font-bold text-gray-700 text-base mb-6">
              {{ otpDestinationLabel }}
            </p>

            <div class="relative mb-1" @click="focusOtpInput">
              <input
                ref="otpInputRef"
                v-model="formData.otp"
                class="absolute w-px h-px opacity-0 pointer-events-none"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                @input="handleOtpInput"
                @keyup.enter="handleSubmit"
              />
              <div class="grid grid-cols-6 gap-2">
                <div
                  v-for="(digit, index) in otpDigits"
                  :key="index"
                  class="h-12 rounded-xl bg-[#F3F8FC] border flex items-center justify-center text-xl font-bold text-gray-800 transition"
                  :class="
                    formData.otp.length === index
                      ? 'border-[#3B82C4] shadow-sm shadow-blue-200'
                      : 'border-[#C9DFF0]'
                  "
                >
                  {{ digit }}
                </div>
              </div>
            </div>

            <p class="text-gray-400 text-xs mt-4 mb-2">
              Code expires in
              <span class="font-bold text-[#3B82C4]">{{
                otpCountdownLabel
              }}</span>
            </p>

            <button
              type="button"
              class="text-[#3B82C4] text-sm font-bold hover:underline disabled:opacity-50"
              :disabled="resendLoading || isLoading"
              @click="handleResendVerification"
            >
              <span v-if="!resendLoading">Resend Code</span>
              <span v-else>Sending...</span>
            </button>

            <div
              v-if="infoMessage"
              class="mt-4 bg-green-50 border border-green-300 text-green-700 rounded-xl px-4 py-2 text-sm"
            >
              {{ infoMessage }}
            </div>
            <div
              v-if="errorMessage"
              class="mt-4 bg-red-50 border border-red-300 text-red-700 rounded-xl px-4 py-2 text-sm"
            >
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
import {
  ref,
  reactive,
  onMounted,
  computed,
  watch,
  nextTick,
  onUnmounted,
} from "vue";
import axios from "axios";
import { chevronBackOutline } from "ionicons/icons";
import {
  IonPage,
  IonHeader,
  IonToolbar,
  IonContent,
  IonInput,
  IonButton,
  IonInputPasswordToggle,
  IonIcon,
} from "@ionic/vue";

export default {
  name: "LoginPage",
  components: {
    IonPage,
    IonHeader,
    IonToolbar,
    IonContent,
    IonInput,
    IonButton,
    IonInputPasswordToggle,
    IonIcon,
  },
  setup() {
    const router = useRouter();
    const route = useRoute();

    const formData = reactive({
      loginInput: "",
      password: "",
      otp: "",
    });

    const errorMessage = ref("");
    const infoMessage = ref("");
    const isLoading = ref(false);
    const resendLoading = ref(false);
    const showResendButton = ref(false);
    const showOtpSection = ref(false);
    const rememberMe = ref(false);
    const showForgotPasswordModal = ref(false);
    const forgotPasswordEmail = ref("");
    const forgotPasswordOtp = ref("");
    const forgotPasswordNewPassword = ref("");
    const forgotPasswordConfirmPassword = ref("");
    const forgotPasswordOtpSent = ref(false);
    const forgotPasswordLoading = ref(false);
    const forgotPasswordInfo = ref("");
    const forgotPasswordError = ref("");
    const otpInputRef = ref<HTMLInputElement | null>(null);
    const otpCountdownSeconds = ref(120);
    let otpTimer: ReturnType<typeof setInterval> | null = null;
    const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || "").replace(
      /\/+$/,
      ""
    );

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
      const digits = String(formData.otp || "").split("");
      return Array.from({ length: 6 }, (_, idx) => digits[idx] || "");
    });

    const otpCountdownLabel = computed(() => {
      const minutes = Math.floor(otpCountdownSeconds.value / 60);
      const seconds = otpCountdownSeconds.value % 60;
      return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(
        2,
        "0"
      )}`;
    });

    const otpDestinationLabel = computed(() => {
      const value = String(formData.loginInput || "").trim();
      if (!value) return "your registered contact";
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
      formData.otp = String(target.value || "")
        .replace(/\D/g, "")
        .slice(0, 6);
    };

    const returnToLogin = () => {
      showOtpSection.value = false;
      formData.otp = "";
      errorMessage.value = "";
      infoMessage.value = "";
      stopOtpTimer();
    };

    const openForgotPasswordModal = () => {
      forgotPasswordEmail.value = isValidEmail(
        String(formData.loginInput || "")
      )
        ? String(formData.loginInput)
        : "";
      forgotPasswordOtp.value = "";
      forgotPasswordNewPassword.value = "";
      forgotPasswordConfirmPassword.value = "";
      forgotPasswordOtpSent.value = false;
      forgotPasswordInfo.value = "";
      forgotPasswordError.value = "";
      showForgotPasswordModal.value = true;
    };

    const closeForgotPasswordModal = () => {
      showForgotPasswordModal.value = false;
      forgotPasswordLoading.value = false;
      forgotPasswordOtp.value = "";
      forgotPasswordNewPassword.value = "";
      forgotPasswordConfirmPassword.value = "";
      forgotPasswordOtpSent.value = false;
      forgotPasswordInfo.value = "";
      forgotPasswordError.value = "";
    };

    const submitForgotPassword = async () => {
      forgotPasswordError.value = "";
      forgotPasswordInfo.value = "";

      const email = String(forgotPasswordEmail.value || "").trim();
      if (!isValidEmail(email)) {
        forgotPasswordError.value = "Please enter a valid email address.";
        return;
      }

      forgotPasswordLoading.value = true;
      try {
        const response = await axios.post(
          `${API_URL}/api/auth/forgot-password`,
          { email },
          {
            headers: {
              "Content-Type": "application/json",
            },
          }
        );
        forgotPasswordOtpSent.value = true;
        forgotPasswordInfo.value =
          response.data?.message ||
          "If the account exists, a reset OTP has been sent to your email.";
      } catch (error: any) {
        forgotPasswordError.value =
          error?.response?.data?.message ||
          "Failed to send reset OTP. Please try again.";
      } finally {
        forgotPasswordLoading.value = false;
      }
    };

    const submitForgotPasswordReset = async () => {
      forgotPasswordError.value = "";
      forgotPasswordInfo.value = "";

      const email = String(forgotPasswordEmail.value || "").trim();
      if (!isValidEmail(email)) {
        forgotPasswordError.value = "Please enter a valid email address.";
        return;
      }

      const otp = String(forgotPasswordOtp.value || "")
        .replace(/\D/g, "")
        .slice(0, 6);
      forgotPasswordOtp.value = otp;
      if (!/^\d{6}$/.test(otp)) {
        forgotPasswordError.value = "Please enter the 6-digit OTP.";
        return;
      }

      if (
        !forgotPasswordNewPassword.value ||
        !forgotPasswordConfirmPassword.value
      ) {
        forgotPasswordError.value =
          "Please enter and confirm your new password.";
        return;
      }

      if (
        forgotPasswordNewPassword.value !== forgotPasswordConfirmPassword.value
      ) {
        forgotPasswordError.value = "Passwords do not match.";
        return;
      }

      if (!isStrongPassword(forgotPasswordNewPassword.value)) {
        forgotPasswordError.value = "Password does not meet required strength.";
        return;
      }

      forgotPasswordLoading.value = true;
      try {
        const response = await axios.post(
          `${API_URL}/api/auth/reset-password`,
          {
            email,
            otp,
            password: forgotPasswordNewPassword.value,
          },
          {
            headers: {
              "Content-Type": "application/json",
            },
          }
        );
        forgotPasswordInfo.value =
          response.data?.message ||
          "Password reset successful. You can now sign in.";
        forgotPasswordOtp.value = "";
        forgotPasswordNewPassword.value = "";
        forgotPasswordConfirmPassword.value = "";
        forgotPasswordOtpSent.value = false;
      } catch (error: any) {
        forgotPasswordError.value =
          error?.response?.data?.message ||
          "Failed to reset password. Please try again.";
      } finally {
        forgotPasswordLoading.value = false;
      }
    };

    onMounted(() => {
      const verifySent =
        String(route.query.verifyEmailSent || "") === "1" ||
        String(route.query.verifyOtpSent || "") === "1";
      const emailVerified = String(route.query.emailVerified || "");
      const queryEmail = String(route.query.email || "").trim();
      const savedEmail = localStorage.getItem("whitecoat_remember_email");

      if (verifySent) {
        infoMessage.value = queryEmail
          ? `OTP sent to ${queryEmail}. Please check your inbox.`
          : "OTP sent. Please check your inbox.";
        showOtpSection.value = true;
        showResendButton.value = true;
      }

      if (emailVerified === "1") {
        infoMessage.value =
          "Email verified successfully. Sign in will now require OTP each time.";
      } else if (emailVerified === "0") {
        errorMessage.value =
          "Verification link is invalid or expired. Please use OTP verification below.";
        showOtpSection.value = true;
        showResendButton.value = true;
      }

      if (queryEmail && formData.loginInput === "") {
        formData.loginInput = queryEmail;
      }

      if (savedEmail) {
        formData.loginInput = savedEmail;
        rememberMe.value = true;
      }
    });

    const handleSubmit = async () => {
      errorMessage.value = "";
      infoMessage.value = "";

      if (!showOtpSection.value) {
        showResendButton.value = false;
      }

      if (!formData.loginInput || !formData.password) {
        errorMessage.value = "Please enter username/email and password.";
        return;
      }

      if (
        showOtpSection.value &&
        !/^\d{6}$/.test(String(formData.otp || "").trim())
      ) {
        errorMessage.value = "Enter the 6-digit OTP sent to your email.";
        return;
      }

      if (rememberMe.value) {
        localStorage.setItem("whitecoat_remember_email", formData.loginInput);
      } else {
        localStorage.removeItem("whitecoat_remember_email");
      }

      isLoading.value = true;

      try {
        const loginPayload: {
          loginInput: string;
          password: string;
          otp?: string;
        } = {
          loginInput: formData.loginInput,
          password: formData.password,
        };

        if (showOtpSection.value) {
          loginPayload.otp = String(formData.otp || "").trim();
        }

        const response = await axios.post(
          `${API_URL}/api/auth/login`,
          loginPayload,
          {
            headers: {
              "Content-Type": "application/json",
            },
          }
        );

        if (response.data.token) {
          localStorage.setItem("token", response.data.token);
          localStorage.setItem(
            "whitecoat_user",
            JSON.stringify(response.data.user)
          );
          localStorage.removeItem("whitecoat_terms_accepted");
          window.dispatchEvent(new Event("whitecoat-user-updated"));
          router.push("/tabs/tab1");
          return;
        }

        if (response.data?.code === "LOGIN_OTP_REQUIRED") {
          infoMessage.value =
            response.data?.message ||
            "OTP sent. Enter the 6-digit code to continue login.";
          showOtpSection.value = true;
          showResendButton.value = true;
          startOtpTimer();
          focusOtpInput();
          return;
        }

        errorMessage.value = response.data?.message || "Authentication failed.";
      } catch (error: any) {
        const errorMsg =
          error.response?.data?.message ||
          error.message ||
          "Could not connect to server.";
        const code = error.response?.data?.code;
        const status = error.response?.status;

        console.error("Login error:", {
          status,
          code,
          message: errorMsg,
          fullError: error,
        });

        if (code === "EMAIL_NOT_VERIFIED" || code === "LOGIN_OTP_REQUIRED") {
          errorMessage.value =
            error.response?.data?.message ||
            "OTP is required to continue login.";
          showResendButton.value = true;
          showOtpSection.value = true;
          const unverifiedEmail = String(
            error.response?.data?.email || ""
          ).trim();
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
      errorMessage.value = "";
      infoMessage.value = "";

      const email = String(formData.loginInput || "").trim();
      if (!email) {
        errorMessage.value =
          "Enter your username/email first, then click Resend Code.";
        return;
      }

      if (!String(formData.password || "").trim()) {
        errorMessage.value =
          "Enter your password first, then click Resend OTP.";
        return;
      }

      resendLoading.value = true;
      try {
        const response = await axios.post(
          `${API_URL}/api/auth/login`,
          {
            loginInput: email,
            password: String(formData.password || ""),
          },
          {
            headers: {
              "Content-Type": "application/json",
            },
          }
        );

        if (response.data?.code === "LOGIN_OTP_REQUIRED") {
          infoMessage.value =
            response.data?.message || "OTP sent. Please check your inbox.";
        } else {
          infoMessage.value =
            response.data?.message || "OTP sent. Please check your inbox.";
        }
        showOtpSection.value = true;
        showResendButton.value = true;
        formData.otp = "";
        startOtpTimer();
        focusOtpInput();
      } catch (error: any) {
        if (error.response?.data?.message) {
          errorMessage.value = error.response.data.message;
        } else {
          errorMessage.value = "Failed to send OTP. Please try again.";
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

    watch(
      () => formData.otp,
      (value) => {
        if (!showOtpSection.value || isLoading.value) return;
        if (String(value).length === 6) {
          handleSubmit();
        }
      }
    );

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
      rememberMe,
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
    };
  },
};
</script>

<style scoped>
ion-page,
ion-content {
  --background: #eef4fb;
}

#archivo {
  font-family: "Archivo Black", sans-serif;
}

.custom-input {
  --highlight-color: none;
  --padding-start: 16px;
  --padding-end: 16px;
  --color: #1f2937;
  --background: #f3f8fc;
  --border-radius: 12px;
}
</style>
