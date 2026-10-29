(() => {
  const shell = document.getElementById('appShell');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const close = document.querySelector('[data-sidebar-close]');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  const root = document.documentElement;
  const profileToggle = document.querySelector('[data-profile-toggle]');
  const profileDropdown = document.getElementById('profileDropdown');
  const settingsModal = document.querySelector('[data-settings-modal]');
  const profileImageInput = document.querySelector('[data-profile-image-input]');
  const profileImageEdit = document.querySelector('[data-profile-image-edit]');
  const profileImageDelete = document.querySelector('[data-profile-image-delete]');
  const profileImageModal = document.querySelector('[data-profile-image-modal]');
  const profileImageModalInput = document.querySelector('[data-profile-image-modal-input]');
  const profileImageSave = document.querySelector('[data-profile-image-save]');
  const removeProfileImage = document.querySelector('[data-remove-profile-image]');
  const profileSettingsForm = document.querySelector('.profile-settings-form');
  const searchForm = document.querySelector('.global-search');

  const isMobile = () => window.matchMedia('(max-width: 760px)').matches;

  const setCollapsed = (collapsed) => {
    if (!shell || isMobile()) return;
    shell.classList.toggle('sidebar-collapsed', collapsed);
    localStorage.setItem('supporthub.sidebarCollapsed', collapsed ? '1' : '0');
  };

  const openMobile = () => shell?.classList.add('sidebar-open');
  const closeMobile = () => shell?.classList.remove('sidebar-open');

  if (shell && !isMobile() && localStorage.getItem('supporthub.sidebarCollapsed') === '1') {
    shell.classList.add('sidebar-collapsed');
  }

  toggle?.addEventListener('click', () => {
    if (isMobile()) openMobile();
    else setCollapsed(!shell.classList.contains('sidebar-collapsed'));
  });
  close?.addEventListener('click', closeMobile);
  overlay?.addEventListener('click', closeMobile);

  document.querySelectorAll('.nav a').forEach(link => {
    link.addEventListener('click', () => { if (isMobile()) closeMobile(); });
  });

  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      document.querySelector('.global-search input')?.focus();
    }
    if (event.key === 'Escape') {
      closeMobile();
      closeProfileMenu();
      closeProfileImageModal();
      closeSettings();
    }
  });

  const closeProfileMenu = () => {
    if (!profileDropdown || !profileToggle) return;
    profileDropdown.hidden = true;
    profileToggle.setAttribute('aria-expanded', 'false');
  };

  const openSettings = () => {
    if (!settingsModal) return;
    closeProfileMenu();
    settingsModal.hidden = false;
    document.body.classList.add('modal-open');
    settingsModal.querySelector('[data-settings-close]')?.focus();
  };

  const closeSettings = () => {
    if (!settingsModal || settingsModal.hidden) return;
    settingsModal.hidden = true;
    document.body.classList.remove('modal-open');
  };

  const openProfileImageModal = () => {
    if (!profileImageModal) return;
    profileImageModal.hidden = false;
    profileImageModalInput && (profileImageModalInput.value = '');
    profileImageModal.querySelector('[data-profile-image-close]')?.focus();
  };

  const closeProfileImageModal = () => {
    if (!profileImageModal || profileImageModal.hidden) return;
    profileImageModal.hidden = true;
    if (profileImageModalInput) profileImageModalInput.value = '';
  };

  profileToggle?.addEventListener('click', (event) => {
    event.stopPropagation();
    const willOpen = profileDropdown?.hidden;
    if (!profileDropdown) return;
    profileDropdown.hidden = !willOpen;
    profileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });

  document.querySelectorAll('[data-settings-open]').forEach(button => {
    button.addEventListener('click', openSettings);
  });
  document.querySelectorAll('[data-settings-close]').forEach(button => {
    button.addEventListener('click', closeSettings);
  });

  profileImageEdit?.addEventListener('click', openProfileImageModal);
  document.querySelectorAll('[data-profile-image-close]').forEach(button => {
    button.addEventListener('click', closeProfileImageModal);
  });
  profileImageSave?.addEventListener('click', () => {
    const file = profileImageModalInput?.files?.[0];
    if (!file || !profileImageInput || !profileSettingsForm) {
      window.alert('Select an image before saving.');
      return;
    }

    if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      window.alert('Choose a PNG, JPG, or JPEG image up to 10 MB.');
      return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    profileImageInput.files = transfer.files;
    profileSettingsForm.requestSubmit();
  });
  profileImageDelete?.addEventListener('click', () => {
    if (!removeProfileImage || !profileSettingsForm) return;
    removeProfileImage.value = '1';
    profileSettingsForm.requestSubmit();
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.profile-menu')) closeProfileMenu();
  });

  document.querySelectorAll('[data-theme-choice]').forEach(button => {
    const choice = button.dataset.themeChoice;
    button.classList.toggle('selected', root.dataset.theme === choice);
    button.addEventListener('click', () => {
      root.dataset.theme = choice;
      localStorage.setItem('supporthub.theme', choice);
      document.querySelectorAll('[data-theme-choice]').forEach(option => {
        option.classList.toggle('selected', option.dataset.themeChoice === choice);
      });
    });
  });

  document.querySelectorAll('[data-accent-choice]').forEach(button => {
    const choice = button.dataset.accentChoice;
    button.classList.toggle('selected', root.dataset.accent === choice);
    button.addEventListener('click', () => {
      root.dataset.accent = choice;
      localStorage.setItem('supporthub.accent', choice);
      document.querySelectorAll('[data-accent-choice]').forEach(option => {
        option.classList.toggle('selected', option.dataset.accentChoice === choice);
      });
    });
  });

  searchForm?.addEventListener('submit', () => {
    const input = searchForm.querySelector('input[name="search"]');
    if (input) input.value = input.value.trim();
  });

  document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
    setTimeout(() => el.remove(), 4500);
  });

  // Premium form micro-interaction for the ticket category / priority cards.
  document.querySelectorAll('[data-choice-group]').forEach(group => {
    group.querySelectorAll('input[type="radio"]').forEach(input => {
      const sync = () => {
        group.querySelectorAll('label.choice-card').forEach(label => {
          const radio = label.querySelector('input[type="radio"]');
          label.classList.toggle('selected', !!radio?.checked);
        });
      };
      input.addEventListener('change', sync);
      sync();
    });
  });
})();
