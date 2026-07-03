<script>
// Navigation controls
const menuToggle = document.getElementById('menuToggle');
const sideNav = document.getElementById('sideNav');
const navOverlay = document.getElementById('navOverlay');
const navClose = document.getElementById('navClose');
const navAddMovie = document.getElementById('navAddMovie');
const navSettings = document.getElementById('navSettings');

function openNav() {
    sideNav.classList.add('active');
    navOverlay.classList.add('active');
    menuToggle.classList.add('hidden');
    document.body.style.overflow = 'hidden';
}

function closeNav() {
    sideNav.classList.remove('active');
    navOverlay.classList.remove('active');
    menuToggle.classList.remove('hidden');
    document.body.style.overflow = '';
}

if (menuToggle) {
    menuToggle.addEventListener('click', openNav);
}

if (navClose) {
    navClose.addEventListener('click', closeNav);
}

if (navOverlay) {
    navOverlay.addEventListener('click', closeNav);
}

if (navAddMovie) {
    navAddMovie.addEventListener('click', (e) => {
        e.preventDefault();
        closeNav();
        const addModal = document.getElementById('addMovieModal');
        if (addModal) {
            addModal.classList.add('show');
        }
    });
}

if (navSettings) {
    navSettings.addEventListener('click', (e) => {
        e.preventDefault();
        closeNav();
        const settingsModal = document.getElementById('settingsModal');
        if (settingsModal) {
            settingsModal.classList.add('show');
            // Load settings content
            if (typeof loadSettingsMovies === 'function') {
                loadSettingsMovies();
            }
            if (typeof loadMovieSelect === 'function') {
                loadMovieSelect();
            }
            if (typeof initVideoSettings === 'function') {
                initVideoSettings();
            }
        }
    });
}

// Close nav on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sideNav?.classList.contains('active')) {
        closeNav();
    }
});
</script>
