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
            <img src="../assets/logo/doctors.png" alt="doctors" class="h-40 w-60 mx-auto rounded-full"/>
            <p id="archivo" class="text-center font-bold text-3xl mt-2 mb-4">WhiteCoat</p>
          </div>
  
          <div class="bg-[#D9D9D9] mx-8 md:mx-auto p-6 rounded-4xl width-full md:w-sm items-center">
            <p class="text-5xl font-bold mb-5 mt-2 text-center">SIGN UP</p>

            <div class="mb-4 rounded-md border border-blue-300 bg-blue-50 px-3 py-2 text-xs text-blue-800 text-center">
              Use an active email. We will send a 6-digit OTP code that you must enter before signing in.
            </div>
            
            <form class="w-full" @submit.prevent="handleRegister">
              <!-- Username Input -->
              <div class="mb-4">
                <ion-input
                  v-model="formData.username"
                  class="custom-input mx-auto h-15 shadow bg-white rounded-full border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  type="text"
                  placeholder="Enter username"
                />
                <label class="block text-gray-700 text-lg font-bold mb-2 mt-1">
                  Username
                </label>
              </div>
  
              <!-- Email Input -->
              <div class="mb-4">
                <ion-input
                  v-model="formData.email"
                  class="custom-input mx-auto h-15 shadow bg-white rounded-full border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                  type="email"
                  placeholder="Enter email address"
                />
                <label class="block text-gray-700 text-lg font-bold mb-2 mt-1">
                  Email
                </label>
              </div>
  
              <!-- Password Input -->
              <div class="mb-6">
                <ion-input
                  v-model="formData.password"
                  class="custom-input mx-auto h-15 shadow bg-white rounded-full border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline"
                  type="password"
                  placeholder="Enter password"
                >
                  <ion-input-password-toggle style="--color: black;" slot="end"></ion-input-password-toggle>
                </ion-input>
                <label class="block text-gray-700 text-lg font-bold mb-2 mt-1">
                  Password
                </label>
              </div>
  
              <!-- Error Message -->
              <div v-if="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-center">
                {{ errorMessage }}
              </div>

              <div v-if="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4 text-center">
                {{ successMessage }}
              </div>
  
              <!-- Submit Button -->
              <div class="mx-auto text-center">
                <ion-button 
                  @click="handleRegister"
                  fill="clear"
                  :disabled="isLoading"
                  class="p-2 font-bold text-xl text-[#0034B7] rounded-full mb-4 mx-auto border border-black font-bold hover:bg-[#0034B7] hover:text-white transition duration-300 ease-in-out"
                  type="submit"
                >
                  <span v-if="!isLoading">Create Account</span>
                  <span v-else>Creating...</span>
                </ion-button>
              </div>
              
              <!-- Link to Login -->
              <div class="text-center mt-2">
                  <p class="text-gray-700">
                      Already have an account? 
                      <!-- Use the existing Login route (path '/') -->
                      <router-link :to="{ name: 'Login' }" class="text-[#0034B7] font-bold hover:underline">
                          Sign In
                      </router-link>
                  </p>
              </div>
            </form>
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
    IonPage, 
    IonHeader, 
    IonToolbar, 
    IonContent, 
    IonInput, 
    IonButton, 
    IonInputPasswordToggle 
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
      const API_URL = (import.meta as any).env?.VITE_API_BASE_URL || '';
  
      const handleRegister = async () => {
        errorMessage.value = '';
        successMessage.value = '';
        
        // Basic Validation
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
          if (error.response && error.response.data && error.response.data.message) {
            errorMessage.value = error.response.data.message;
          } else {
            errorMessage.value = 'Registration failed. Please try again.';
          }
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
  </style>