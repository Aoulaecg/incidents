document.querySelectorAll('[data-password-toggle]').forEach((passwordToggle) => {
    const passwordInput = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    if (!passwordInput) return;
    const showLabel = passwordToggle.getAttribute('aria-label');
    passwordToggle.hidden = false;
    passwordToggle.addEventListener('click', () => {
        const visible = passwordInput.type === 'password';
        passwordInput.type = visible ? 'text' : 'password';
        passwordToggle.setAttribute('aria-pressed', String(visible));
        passwordToggle.setAttribute('aria-label', visible ? showLabel.replace('Afficher', 'Masquer') : showLabel);
        passwordToggle.querySelector('[data-eye-show]').hidden = visible;
        passwordToggle.querySelector('[data-eye-hide]').hidden = !visible;
    });
});

const form = document.querySelector('[data-login-form]');
const submitButton = document.querySelector('[data-submit-button]');
const submitLabel = document.querySelector('[data-submit-label]');

form?.addEventListener('submit', () => {
    submitButton.disabled = true;
    submitLabel.textContent = 'Connexion en cours…';
    form.setAttribute('aria-busy', 'true');
});

window.addEventListener('pageshow', () => {
    if (form && submitButton && submitLabel) {
        submitButton.disabled = document.querySelector('#user_id')?.disabled ?? false;
        submitLabel.textContent = 'Se connecter';
        form.removeAttribute('aria-busy');
    }
});

document.querySelector('[data-error-summary]')?.focus();

const primaryParking = document.querySelector('[data-primary-parking]');
const rasForm = document.querySelector('#ras-form');
const rasDialog = document.querySelector('[data-ras-dialog]');
if (primaryParking && rasForm && rasDialog && typeof rasDialog.showModal === 'function') {
    const rasSelect = rasForm.querySelector('#ras_parking_id');
    const rasCheck = rasForm.querySelector('[name=confirm_ras]');
    rasForm.querySelector('[data-ras-fallback]').hidden = true;
    const trigger = rasForm.querySelector('[data-ras-trigger]');
    trigger.type = 'button';
    trigger.addEventListener('click', () => {
        if (!primaryParking.reportValidity()) return;
        rasSelect.value = primaryParking.value;
        rasDialog.querySelector('[data-ras-parking-name]').textContent = primaryParking.selectedOptions[0].textContent;
        rasDialog.showModal();
    });
    rasDialog.querySelector('[data-ras-cancel]').addEventListener('click', () => rasDialog.close());
    rasDialog.querySelector('[data-ras-confirm]').addEventListener('click', () => {
        rasCheck.checked = true;
        rasDialog.close();
        rasForm.requestSubmit();
    });
}
document.querySelectorAll('[data-declaration-form]').forEach((declarationForm) => {
    declarationForm.addEventListener('submit', () => {
        declarationForm.setAttribute('aria-busy', 'true');
        declarationForm.querySelectorAll('button').forEach((button) => { button.disabled = true; });
    });
    window.addEventListener('pageshow', () => {
        declarationForm.removeAttribute('aria-busy');
        declarationForm.querySelectorAll('button').forEach((button) => { button.disabled = false; });
    });
});

document.querySelectorAll('[data-parking-selector]').forEach((selector) => {
    const checkboxes = [...selector.querySelectorAll('input[type="checkbox"]')];
    const search = selector.querySelector('[data-parking-search]');
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').trim().replace(/\s+/g, ' ');
    const updateCount = () => {
        const count = checkboxes.filter((checkbox) => checkbox.checked).length;
        selector.querySelector('[data-parking-count]').textContent = `${count} sélectionné${count > 1 ? 's' : ''}`;
    };
    selector.querySelector('[data-parking-tools]').hidden = false;
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));
    selector.querySelector('[data-parking-select-all]').addEventListener('click', () => { checkboxes.forEach((checkbox) => { checkbox.checked = true; }); updateCount(); });
    selector.querySelector('[data-parking-clear]').addEventListener('click', () => { checkboxes.forEach((checkbox) => { checkbox.checked = false; }); updateCount(); });
    search.addEventListener('input', () => {
        const query = normalize(search.value);
        const options = [...selector.querySelectorAll('[data-parking-option]')];
        options.forEach((option) => { option.hidden = !normalize(option.querySelector('strong').textContent).includes(query); });
        selector.querySelector('[data-parking-no-match]').hidden = !options.length || options.some((option) => !option.hidden);
    });
    const role = document.getElementById(selector.dataset.parkingRole);
    if (role) {
        const updateRole = () => {
            const visible = role.value === 'supervisor';
            selector.hidden = !visible;
            selector.querySelectorAll('input, button').forEach((control) => { control.disabled = !visible; });
        };
        role.addEventListener('change', updateRole);
        window.addEventListener('pageshow', updateRole);
        updateRole();
    }
    updateCount();
});

const statusSelect = document.querySelector('[data-account-status]');
const deactivationConfirmation = document.querySelector('[data-deactivation-confirm]');
if (statusSelect && deactivationConfirmation) {
    const updateConfirmation = () => {
        const inactive = statusSelect.value === '0';
        deactivationConfirmation.hidden = !inactive;
        deactivationConfirmation.querySelector('input').required = inactive;
    };
    statusSelect.addEventListener('change', updateConfirmation);
    updateConfirmation();
}

const menuToggle = document.querySelector('[data-menu-toggle]');
const sidebar = document.querySelector('#admin-navigation');
const overlay = document.querySelector('[data-menu-close]');
if (menuToggle && sidebar && overlay) {
    const desktopMedia = window.matchMedia('(min-width: 992px)');
    document.body.classList.add('has-js');
    menuToggle.hidden = false;
    sidebar.inert = !desktopMedia.matches;
    const setMenu = (open) => {
        document.body.classList.toggle('menu-open', open);
        menuToggle.setAttribute('aria-expanded', String(open));
        menuToggle.setAttribute('aria-label', open ? 'Fermer la navigation' : 'Ouvrir la navigation');
        overlay.hidden = !open;
        sidebar.inert = !open && !desktopMedia.matches;
        if (open) sidebar.querySelector('a')?.focus();
        else menuToggle.focus();
    };
    menuToggle.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
    overlay.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('menu-open')) setMenu(false);
        if (event.key === 'Tab' && document.body.classList.contains('menu-open')) {
            const links = [...sidebar.querySelectorAll('a[href], button:not(:disabled)')];
            const first = links[0];
            const last = links.at(-1);
            if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
                event.preventDefault();
                first?.focus();
            }
        }
    });
    desktopMedia.addEventListener('change', (event) => {
        if (event.matches) {
            document.body.classList.remove('menu-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            overlay.hidden = true;
        }
        sidebar.inert = !event.matches && !document.body.classList.contains('menu-open');
    });
}
