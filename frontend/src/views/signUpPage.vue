<template>
  <ion-page>
    <ion-content>
      <div class="min-h-screen flex items-center justify-center bg-[#EEF4FB] p-4">
        <div class="w-full max-w-4xl flex rounded-3xl shadow-2xl overflow-hidden min-h-[580px] max-h-[680px]">

          <!-- Left Panel -->
          <div class="hidden md:flex flex-col justify-between w-2/5 p-10 relative overflow-hidden" style="background: linear-gradient(145deg, #5BA3D9 0%, #3B82C4 60%, #2B6FAF 100%);">
            <!-- Decorative circles -->
            <div class="absolute -top-16 -left-16 w-56 h-56 rounded-full bg-white opacity-10"></div>
            <div class="absolute bottom-10 -right-12 w-48 h-48 rounded-full bg-white opacity-10"></div>
            <div class="absolute top-1/2 -left-8 w-32 h-32 rounded-full bg-white opacity-10"></div>

            <!-- Logo -->
            <div class="flex items-center gap-3 z-10">
              <img src="../assets/logo/deped.png" alt="DepEd Logo" class="h-12 w-auto object-contain">
            </div>

            <!-- Center Content -->
            <div class="z-10">
              <img src="../assets/logo/doctorlogo.jpg" alt="Doctor" class="h-24 w-24 rounded-full object-cover border-4 border-white border-opacity-30 mb-6 shadow-lg">
              <h1 id="archivo" class="text-white text-4xl font-black leading-tight mb-3">White Coat</h1>
              <p class="text-[#B8D9F5] text-sm leading-relaxed max-w-xs">
                Your trusted health companion for DepEd schools. Secure, fast, and always accessible.
              </p>
            </div>

            <!-- Bottom note -->
            <p class="text-[#9AC8EE] text-xs z-10">© {{ new Date().getFullYear() }} White Coat — DepEd Division</p>
          </div>

          <!-- Right Panel -->
          <div class="flex-1 bg-white flex flex-col justify-center px-8 md:px-12 py-8">
            <!-- Mobile logo -->
            <div class="flex md:hidden items-center gap-3 mb-6">
              <img src="../assets/logo/deped.png" alt="DepEd Logo" class="h-10 w-auto object-contain">
              <span id="archivo" class="text-[#3B82C4] text-xl font-black">White Coat</span>
            </div>

            <h2 class="text-3xl font-bold text-gray-800 mb-1">Create an account</h2>
            <p class="text-gray-400 text-sm mb-4">Sign up to get started with White Coat</p>

            <!-- OTP Notice -->
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-3 py-2 rounded-xl text-xs mb-4 flex items-start gap-2">
              <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/>
              </svg>
              <span>Use an active email address. A 6-digit OTP will be sent and must be entered before you can sign in.</span>
            </div>

            <form class="w-full" @submit.prevent="handleRegister">
              <!-- Username -->
              <div class="mb-4">
                <label class="block text-gray-600 text-sm font-semibold mb-2" for="username">
                  Username
                </label>
                <ion-input
                  v-model="formData.username"
                  class="custom-input block w-full h-12 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-gray-700 text-sm focus:outline-none focus:border-[#3B82C4] transition"
                  id="username"
                  type="text"
                  placeholder="Enter username"
                />
              </div>

              <!-- Email -->
              <div class="mb-4">
                <label class="block text-gray-600 text-sm font-semibold mb-2" for="email">
                  Email
                </label>
                <ion-input
                  v-model="formData.email"
                  class="custom-input block w-full h-12 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-gray-700 text-sm focus:outline-none focus:border-[#3B82C4] transition"
                  id="email"
                  type="email"
                  placeholder="Enter email address"
                />
              </div>

              <!-- Password -->
              <div class="mb-4">
                <label class="block text-gray-600 text-sm font-semibold mb-2" for="password">
                  Password
                </label>
                <ion-input
                  v-model="formData.password"
                  class="custom-input block w-full h-12 bg-[#F3F8FC] rounded-xl border border-[#C9DFF0] px-4 text-gray-700 text-sm focus:outline-none focus:border-[#3B82C4] transition"
                  id="password"
                  type="password"
                  placeholder="Enter password (min. 16 characters)"
                >
                  <ion-input-password-toggle style="--color: #3B82C4;" slot="end"></ion-input-password-toggle>
                </ion-input>
                <p class="text-xs text-gray-400 mt-1.5">Must be at least 16 characters with uppercase, lowercase, number, and special character.</p>
              </div>

              <!-- Messages -->
              <div v-if="errorMessage" class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-xl text-sm mb-4 text-center">
                {{ errorMessage }}
              </div>
              <div v-if="successMessage" class="bg-green-50 border border-green-300 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 text-center">
                {{ successMessage }}
              </div>

              <!-- Submit -->
              <ion-button
                fill="clear"
                :disabled="isLoading"
                class="w-full h-12 bg-[#3B82C4] text-white font-bold text-base rounded-xl hover:bg-[#2B6FAF] transition duration-200 flex items-center justify-center"
                type="submit"
                expand="block"
                style="--background: #3B82C4; --background-hover: #2B6FAF; --color: white; --border-radius: 12px; height: 48px; font-weight: 700; font-size: 1rem;"
              >
                <span v-if="!isLoading">Create Account</span>
                <span v-else>Creating...</span>
              </ion-button>

              <!-- Sign In Link -->
              <p class="text-center text-gray-500 text-sm mt-4">
                Already have an account?
                <router-link :to="{ name: 'Login' }" class="text-[#3B82C4] font-bold hover:underline">Sign In</router-link>
              </p>
            </form>
          </div>

        </div>
      </div>
    </ion-content>
  </ion-page>
</template>

<script lang="ts">
import { useRouter } from "vue-router";
import { ref, reactive } from 'vue';
import axios from 'axios';
import {
  IonPage, IonHeader, IonToolbar, IonContent, IonInput, IonButton, IonInputPasswordToggle
} from '@ionic/vue';

export default {
  name: 'SignUpPage',
  components: {
    IonPage, IonHeader, IonToolbar, IonContent, IonInput, IonButton, IonInputPasswordToggle
  },
  setup() {
    const router = useRouter();

    const formData = reactive({
      username: '',
      email: '',
      password: ''
    });

    const errorMessage = ref('');
    const successMessage = ref('');
    const isLoading = ref(false);
    const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '');

    const handleRegister = async () => {
      errorMessage.value = '';
      successMessage.value = '';

      if (!formData.username || !formData.email || !formData.password) {
        errorMessage.value = 'All fields are required.';
        return;
      }

      if (formData.password.length < 16) {
        errorMessage.value = 'Password must be at least 16 characters.';
        return;
      }

      isLoading.value = true;

      try {
        const response = await axios.post(`${API_URL}/api/auth/register`, {
          username: formData.username,
          email: formData.email,
          password: formData.password
        });

        successMessage.value = response.data?.message || 'Account created successfully. Please verify your email OTP before logging in.';
        formData.password = '';

        await router.push({
          name: 'Login',
          query: {
            verifyOtpSent: '1',
            email: formData.email,
          },
        });

      } catch (error: any) {
        console.error(error);
        errorMessage.value = error.response?.data?.message || 'Registration failed. Please try again.';
      } finally {
        isLoading.value = false;
      }
    };

    return {
      formData,
      handleRegister,
      errorMessage,
      successMessage,
      isLoading
    };
  }
};
</script>

<style scoped>
ion-page, ion-content {
  --background: #EEF4FB;
}

#archivo {
  font-family: 'Archivo Black', sans-serif;
}

.custom-input {
  --highlight-color: none;
  --padding-start: 16px;
  --padding-end: 16px;
  --color: #1f2937;
  --background: #F3F8FC;
  --border-radius: 12px;
}
</style>