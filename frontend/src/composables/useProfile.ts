import { ref } from 'vue';

const displayName = ref('');
const profilePhotoUrl = ref('');

const STORAGE_KEY = 'whitecoat_user';

export function useProfile() {
  function initFromStorage() {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const user = JSON.parse(stored) as { username?: string; profile_photo?: string };
        displayName.value = user?.username?.trim() || '';
        profilePhotoUrl.value = user?.profile_photo?.trim() || '';
      } else {
        displayName.value = '';
        profilePhotoUrl.value = '';
      }
    } catch {
      displayName.value = '';
      profilePhotoUrl.value = '';
    }
  }

  function setDisplayName(name: string) {
    displayName.value = (name || '').trim();
  }

  function setProfilePhoto(url: string) {
    profilePhotoUrl.value = (url || '').trim();
  }

  return {
    displayName,
    profilePhotoUrl,
    initFromStorage,
    setDisplayName,
    setProfilePhoto,
  };
}
