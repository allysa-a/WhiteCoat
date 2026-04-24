<template>
  <div class="flex justify-between p-2">
    <div class="flex items-center gap-1">
      <img
        :src="profileImageUrl"
        alt="profile"
        class="h-15 w-15 md:h-20 md:w-20 rounded-full object-cover"
      />
      <p class="md:text-xl text-sm font-bold text-gray-800">{{ headerGreeting }}</p>
    </div>
    <div>
      <img src="../assets/logo/division.png" alt="Logo" class="h-15 w-15 md:h-20 md:w-20">
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { useProfile } from '../composables/useProfile';
import { resolveUploadUrl } from '../utils/uploadUrl';

const { displayName, profilePhotoUrl, initFromStorage, setDisplayName, setProfilePhoto } = useProfile();
const API_URL = ((import.meta as any).env?.VITE_API_BASE_URL || '').replace(/\/+$/, '');
const defaultProfileImg = '/src/assets/logo/profile.png';
const userId = ref<number | null>(null);

const buildApiGetUrl = (path: string, params: Record<string, string | number> = {}) => {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const base = API_URL;

  if (base.includes('?api=')) {
    const [basePath, baseQuery = ''] = base.split('?');
    const search = new URLSearchParams(baseQuery);
    const apiPath = search.get('api') || '';
    search.set('api', `${apiPath}${normalizedPath}`);
    for (const [key, value] of Object.entries(params)) {
      search.set(key, String(value));
    }
    return `${basePath}?${search.toString()}`;
  }

  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    search.set(key, String(value));
  }
  const query = search.toString();
  const url = `${base}${normalizedPath}`;
  return query ? `${url}?${query}` : url;
};

const refreshHeaderProfile = async () => {
  initFromStorage();

  try {
    const stored = localStorage.getItem('whitecoat_user');
    if (!stored) {
      userId.value = null;
      return;
    }

    const user = JSON.parse(stored) as { user_id?: number };
    if (!user?.user_id) {
      userId.value = null;
      return;
    }
    userId.value = user.user_id;

    const res = await axios.get(`${API_URL}/api/doctor/profile`, {
      params: { user_id: user.user_id },
    });

    const profile = res.data as { username?: string; profile_photo?: string };
    if (typeof profile.username === 'string' && profile.username.trim()) {
      setDisplayName(profile.username);
    }
    if (typeof profile.profile_photo === 'string' && profile.profile_photo.trim()) {
      setProfilePhoto(profile.profile_photo);
      const merged = {
        ...user,
        username: profile.username ?? undefined,
        profile_photo: profile.profile_photo,
      };
      localStorage.setItem('whitecoat_user', JSON.stringify(merged));
    }
  } catch {
    // keep storage values when server is unavailable
  }
};

const onUserChanged = () => {
  refreshHeaderProfile();
};

onMounted(() => {
  refreshHeaderProfile();
  window.addEventListener('whitecoat-user-updated', onUserChanged as EventListener);
  window.addEventListener('storage', onUserChanged);
});

onBeforeUnmount(() => {
  window.removeEventListener('whitecoat-user-updated', onUserChanged as EventListener);
  window.removeEventListener('storage', onUserChanged);
});

const profileImageUrl = computed(() => {
  if (userId.value) {
    return buildApiGetUrl('/api/doctor/profile-photo', { user_id: userId.value });
  }
  const url = resolveUploadUrl(profilePhotoUrl.value);
  return url || defaultProfileImg;
});

const headerGreeting = computed(() => {
  const name = displayName.value;
  if (!name) return 'Hey, Doctor';
  return name.startsWith('Dr.') ? `Hey, ${name}` : `Hey, Dr. ${name}`;
});
</script>

<style scoped>
</style>